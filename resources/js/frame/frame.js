/*
 * Trame iframe bridge. Runs inside the preview document (host CSS, real
 * rendering) and talks to the editor through postMessage (same origin only):
 *   -> trame:ready | trame:select {id} | trame:action {action, id}
 *      trame:move {id, parentId, index} | trame:key {key, metaKey, ctrlKey, shiftKey}
 *   <- trame:render {html, selected} | trame:select {id, scroll}
 */
import Sortable from 'sortablejs';

const origin = window.location.origin;
const send = (message) => window.parent.postMessage(message, origin);

const COLORS = { section: '#6366f1', row: '#0ea5e9', column: '#10b981', block: '#f97316', global: '#d97706' };

let selectedId = null;
let hovered = null;
let sortables = [];

/* ------------------------------------------------------------------ chrome */

const style = document.createElement('style');
style.textContent = `
    [data-trame-root] { min-height: 100vh; }
    [data-trame-root]:empty { min-height: 100vh; }
    [data-trame-node="column"] { min-height: 3.5rem; outline: 1px dashed rgba(16, 185, 129, .35); outline-offset: -1px; }
    [data-trame-node="row"]:empty, [data-trame-node="section"] > div:empty { min-height: 4rem; outline: 1px dashed rgba(14, 165, 233, .5); }
    [data-trame-node] a, [data-trame-node] button { cursor: default; }
    .trame-ghost { opacity: .35; }
    .trame-chosen { cursor: grabbing !important; }
    #trame-hover, #trame-selected { position: absolute; pointer-events: none; z-index: 2147483000; border: 2px solid; border-radius: 3px; display: none; box-sizing: border-box; }
    #trame-hover { border-style: dashed; }
    #trame-selected .trame-bar { position: absolute; left: -2px; bottom: 100%; display: flex; align-items: center; gap: 2px; padding: 2px; border-radius: 4px 4px 0 0; pointer-events: auto; font: 600 11px/1 ui-sans-serif, system-ui, sans-serif; color: #fff; white-space: nowrap; }
    #trame-selected.trame-flip .trame-bar { bottom: auto; top: 0; border-radius: 0 0 4px 0; }
    #trame-selected .trame-bar span { padding: 4px 6px; }
    #trame-selected .trame-bar button { all: unset; cursor: pointer; padding: 3px 5px; border-radius: 3px; font-size: 12px; line-height: 1; }
    #trame-selected .trame-bar button:hover { background: rgba(255,255,255,.25); }
    #trame-hover .trame-tag { position: absolute; right: -2px; top: -2px; transform: translateY(-100%); padding: 3px 6px; color: #fff; font: 600 10px/1 ui-sans-serif, system-ui, sans-serif; border-radius: 3px 3px 0 0; }
`;
document.head.append(style);

const hoverBox = document.createElement('div');
hoverBox.id = 'trame-hover';
hoverBox.innerHTML = '<div class="trame-tag"></div>';

const selectedBox = document.createElement('div');
selectedBox.id = 'trame-selected';
selectedBox.innerHTML = `<div class="trame-bar">
    <span></span>
    <button type="button" data-action="parent" title="Parent">↖</button>
    <button type="button" data-action="up" title="Up">↑</button>
    <button type="button" data-action="down" title="Down">↓</button>
    <button type="button" data-action="duplicate" title="Duplicate">⧉</button>
    <button type="button" data-action="delete" title="Delete">✕</button>
</div>`;

document.body.append(hoverBox, selectedBox);

selectedBox.querySelector('.trame-bar').addEventListener('click', (event) => {
    const button = event.target.closest('button[data-action]');

    if (button && selectedId) {
        event.preventDefault();
        event.stopPropagation();
        send({ type: 'trame:action', action: button.dataset.action, id: selectedId });
    }
});

/* ---------------------------------------------------------------- helpers */

const root = () => document.querySelector('[data-trame-root]');

/** Selectable node for an element: the outermost global wrapper wins (its inner ids are not in the page). */
function nodeFor(element) {
    let node = element?.closest?.('[data-trame-id]') ?? null;
    let global = element?.closest?.('[data-trame-node="global"]') ?? null;
    let outer = null;

    while (global) {
        outer = global;
        global = global.parentElement?.closest('[data-trame-node="global"]') ?? null;
    }

    return outer ?? node;
}

function find(id) {
    return id ? document.querySelector(`[data-trame-id="${CSS.escape(id)}"]`) : null;
}

function kindOf(element) {
    return element?.dataset.trameNode ?? 'block';
}

function labelOf(element) {
    const kind = kindOf(element);

    return kind === 'block' ? element.dataset.trameType ?? 'block' : kind;
}

function place(box, element) {
    if (!element || !element.isConnected) {
        box.style.display = 'none';

        return;
    }

    const rect = element.getBoundingClientRect();
    const color = COLORS[kindOf(element)] ?? COLORS.block;

    Object.assign(box.style, {
        display: 'block',
        top: `${rect.top + window.scrollY}px`,
        left: `${rect.left + window.scrollX}px`,
        width: `${Math.max(rect.width, 8)}px`,
        height: `${Math.max(rect.height, 8)}px`,
        borderColor: color,
    });

    box.querySelectorAll('.trame-bar, .trame-tag').forEach((el) => (el.style.background = color));
    box.classList.toggle('trame-flip', rect.top < 26);
}

function draw() {
    const selected = find(selectedId);
    place(selectedBox, selected);
    selectedBox.querySelector('.trame-bar span').textContent = selected ? labelOf(selected) : '';

    const hover = hovered && hovered !== selected ? hovered : null;
    place(hoverBox, hover);
    hoverBox.querySelector('.trame-tag').textContent = hover ? labelOf(hover) : '';
}

let frame = null;
const redraw = () => {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(draw);
};

/* ----------------------------------------------------------- drag & drop */

function initSortables() {
    sortables.forEach((sortable) => sortable.destroy());
    sortables = [];

    const container = root();

    if (!container) {
        return;
    }

    const insideGlobal = (el) => Boolean(el.closest('[data-trame-node="global"]'));

    const create = (el, group) => {
        sortables.push(
            Sortable.create(el, {
                group,
                draggable: '[data-trame-id]',
                animation: 150,
                fallbackOnBody: true,
                swapThreshold: 0.65,
                invertSwap: true,
                emptyInsertThreshold: 24,
                ghostClass: 'trame-ghost',
                chosenClass: 'trame-chosen',
                onStart: () => {
                    hovered = null;
                    selectedBox.style.display = 'none';
                    hoverBox.style.display = 'none';
                },
                onEnd: (event) => {
                    if (event.from === event.to && event.oldDraggableIndex === event.newDraggableIndex) {
                        redraw();

                        return;
                    }

                    const parent = event.to === container ? null : event.to.closest('[data-trame-id]');

                    send({
                        type: 'trame:move',
                        id: event.item.dataset.trameId,
                        parentId: parent?.dataset.trameId ?? null,
                        index: event.newDraggableIndex,
                    });
                },
            }),
        );
    };

    create(container, 'trame-sections');

    container.querySelectorAll('[data-trame-node="section"]').forEach((section) => {
        const inner = section.firstElementChild;

        if (inner && !insideGlobal(section)) {
            create(inner, 'trame-rows');
        }
    });

    container.querySelectorAll('[data-trame-node="row"]').forEach((row) => {
        if (!insideGlobal(row)) {
            create(row, `trame-columns-${row.dataset.trameId}`);
        }
    });

    container.querySelectorAll('[data-trame-node="column"]').forEach((column) => {
        if (!insideGlobal(column)) {
            create(column, 'trame-blocks');
        }
    });
}

/* ----------------------------------------------------------------- events */

document.addEventListener(
    'mouseover',
    (event) => {
        if (selectedBox.contains(event.target)) {
            return;
        }

        const node = nodeFor(event.target);

        if (node !== hovered) {
            hovered = node;
            redraw();
        }
    },
    true,
);

document.addEventListener('mouseleave', () => {
    hovered = null;
    redraw();
});

document.addEventListener(
    'click',
    (event) => {
        if (selectedBox.contains(event.target)) {
            return;
        }

        // Links, buttons and forms are inert while editing.
        event.preventDefault();
        event.stopPropagation();

        const node = nodeFor(event.target);
        selectedId = node?.dataset.trameId ?? null;
        redraw();
        send({ type: 'trame:select', id: selectedId });
    },
    true,
);

document.addEventListener('submit', (event) => event.preventDefault(), true);

document.addEventListener('keydown', (event) => {
    const key = event.key.toLowerCase();
    const mod = event.metaKey || event.ctrlKey;

    if ((mod && ['s', 'z', 'y'].includes(key)) || ['delete', 'backspace', 'escape'].includes(key)) {
        event.preventDefault();
        send({ type: 'trame:key', key: event.key, metaKey: event.metaKey, ctrlKey: event.ctrlKey, shiftKey: event.shiftKey });
    }
});

window.addEventListener('scroll', redraw, { passive: true });
window.addEventListener('resize', redraw);
new ResizeObserver(redraw).observe(document.body);

window.addEventListener('message', (event) => {
    if (event.origin !== origin || event.source !== window.parent) {
        return;
    }

    const message = event.data ?? {};

    if (message.type === 'trame:render') {
        const template = document.createElement('template');
        template.innerHTML = String(message.html ?? '').trim();
        const next = template.content.querySelector('[data-trame-root]');
        const current = root();

        if (next && current) {
            current.replaceWith(next);
        }

        selectedId = message.selected ?? selectedId;
        hovered = null;
        initSortables();
        redraw();
    }

    if (message.type === 'trame:select') {
        selectedId = message.id ?? null;
        redraw();

        if (message.scroll) {
            find(selectedId)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }
});

initSortables();
send({ type: 'trame:ready' });
