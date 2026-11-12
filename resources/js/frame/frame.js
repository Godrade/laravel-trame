/*
 * Trame iframe bridge. Runs inside the preview document (host CSS, real
 * rendering) and talks to the editor through postMessage (same origin only):
 *   -> trame:ready | trame:select {id} | trame:action {action, id}
 *      trame:move {id, parentId, index} | trame:key {key, metaKey, ctrlKey, shiftKey}
 *      trame:drop {payload, parentId, index}   (drag from the editor palette)
 *      trame:inline {id, field, value}          (in-place text editing)
 *   <- trame:render {html, selected} | trame:select {id, scroll}
 */
import Sortable from 'sortablejs';

const origin = window.location.origin;
const send = (message) => window.parent.postMessage(message, origin);

const COLORS = { section: '#6366f1', row: '#0ea5e9', column: '#10b981', block: '#f97316', global: '#d97706' };

let selectedId = null;
let hovered = null;
let sortables = [];
let editing = null; // { el, id, field, rich }
let pendingRender = null;

/* ------------------------------------------------------------------ chrome */

const style = document.createElement('style');
style.textContent = `
    [data-trame-root] { min-height: 100vh; }
    [data-trame-root]:empty { min-height: 100vh; }
    [data-trame-node="column"] { min-height: 3.5rem; outline: 1px dashed rgba(16, 185, 129, .35); outline-offset: -1px; }
    [data-trame-node="row"]:empty, [data-trame-node="section"] > div:empty { min-height: 4rem; outline: 1px dashed rgba(14, 165, 233, .5); }
    [data-trame-node] a, [data-trame-node] button { cursor: default; }
    .trame-ghost { opacity: .35; }
    [data-trame-field]:hover { cursor: text; }
    [data-trame-field][contenteditable]:not([contenteditable="false"]) { outline: 2px solid #f97316 !important; outline-offset: 2px; cursor: text; }
    /* Hidden on the previewed breakpoint (Tailwind defaults: md 48rem, lg 64rem): kept selectable, dimmed */
    @media (max-width: 47.999rem) { [data-trame-hide~="base"] { opacity: .35; background-image: repeating-linear-gradient(135deg, rgba(113,113,122,.12) 0 6px, transparent 6px 12px); } }
    @media (min-width: 48rem) and (max-width: 63.999rem) { [data-trame-hide~="md"] { opacity: .35; background-image: repeating-linear-gradient(135deg, rgba(113,113,122,.12) 0 6px, transparent 6px 12px); } }
    @media (min-width: 64rem) { [data-trame-hide~="lg"] { opacity: .35; background-image: repeating-linear-gradient(135deg, rgba(113,113,122,.12) 0 6px, transparent 6px 12px); } }
    #trame-drop { position: absolute; z-index: 2147483001; height: 4px; margin-top: -2px; border-radius: 2px; background: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,.25); pointer-events: none; display: none; }
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

const dropLine = document.createElement('div');
dropLine.id = 'trame-drop';

document.body.append(hoverBox, selectedBox, dropLine);

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

/** Sent by the editor ("trame:labels"): node and block names, action titles, in its language. */
let labels = { kinds: {}, blocks: {}, actions: {} };

function labelOf(element) {
    const kind = kindOf(element);

    if (kind === 'block') {
        const type = element.dataset.trameType ?? 'block';

        return labels.blocks[type] ?? type;
    }

    return labels.kinds[kind] ?? kind;
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

    // Nothing moves inside a global template or a locked section (simple mode).
    const insideGlobal = (el) => Boolean(el.closest('[data-trame-node="global"], [data-trame-locked]'));

    const create = (el, group, options = {}) => {
        sortables.push(
            Sortable.create(el, {
                ...options,
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

    // A locked section stays in place; clicks still select it.
    create(container, 'trame-sections', { filter: '[data-trame-locked]', preventOnFilter: false });

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

    if (editing) {
        if (key === 'escape' || (key === 'enter' && !editing.rich && !event.shiftKey)) {
            event.preventDefault();
            editing.el.blur();
        } else if (mod && key === 's') {
            event.preventDefault();
            editing.el.blur();
            send({ type: 'trame:key', key: event.key, metaKey: event.metaKey, ctrlKey: event.ctrlKey, shiftKey: event.shiftKey });
        }

        return;
    }

    if ((mod && ['s', 'z', 'y'].includes(key)) || ['delete', 'backspace', 'escape'].includes(key)) {
        event.preventDefault();
        send({ type: 'trame:key', key: event.key, metaKey: event.metaKey, ctrlKey: event.ctrlKey, shiftKey: event.shiftKey });
    }
});

window.addEventListener('scroll', redraw, { passive: true });
window.addEventListener('resize', redraw);
new ResizeObserver(redraw).observe(document.body);

/* ---------------------------------------------------------- render sync */

function applyRender(message) {
    const template = document.createElement('template');
    template.innerHTML = String(message.html ?? '').trim();
    const next = template.content.querySelector('[data-trame-root]');
    const current = root();

    if (next && current) {
        current.replaceWith(next);
    }

    selectedId = message.selected ?? selectedId;
    hovered = null;
    markLocked(message.locked ?? []);
    initSortables();
    redraw();
}

/** Sections whose structure is locked in the editor's simple mode (sent with each render). */
function markLocked(ids) {
    const locked = new Set(ids);

    root()?.querySelectorAll('[data-trame-node="section"]').forEach((section) => {
        section.toggleAttribute('data-trame-locked', locked.has(section.dataset.trameId));
    });
}

/* ----------------------------------------------------- in-place editing */

function startEditing(el) {
    const block = el.closest('[data-trame-id]');

    if (!block || editing?.el === el) {
        return;
    }

    const rich = el.hasAttribute('data-trame-rich');
    editing = { el, id: block.dataset.trameId, field: el.dataset.trameField, rich };
    selectedId = editing.id;
    send({ type: 'trame:select', id: selectedId });

    sortables.forEach((sortable) => sortable.option('disabled', true));
    el.setAttribute('contenteditable', rich ? 'true' : 'plaintext-only');
    el.focus();
    redraw();
}

function editedValue() {
    if (!editing) {
        return '';
    }

    return editing.rich
        ? editing.el.innerHTML.trim()
        // textContent, not innerText: innerText applies CSS text-transform (uppercase labels).
        : editing.el.textContent.replace(/\s+/g, ' ').trim();
}

document.addEventListener(
    'dblclick',
    (event) => {
        const el = event.target.closest?.('[data-trame-field]');

        if (!el || el.closest('[data-trame-node="global"]')) {
            return;
        }

        event.preventDefault();
        startEditing(el);
    },
    true,
);

document.addEventListener(
    'input',
    (event) => {
        if (editing && editing.el.contains(event.target)) {
            send({ type: 'trame:inline', id: editing.id, field: editing.field, value: editedValue() });
            redraw();
        }
    },
    true,
);

document.addEventListener(
    'focusout',
    (event) => {
        if (!editing || event.target !== editing.el) {
            return;
        }

        editing.el.setAttribute('contenteditable', 'false');
        editing = null;
        sortables.forEach((sortable) => sortable.option('disabled', false));

        if (pendingRender) {
            const message = pendingRender;
            pendingRender = null;
            applyRender(message);
        } else {
            redraw();
        }
    },
    true,
);

// Rich fields: paste as plain text (the server sanitizer would strip most markup anyway).
document.addEventListener(
    'paste',
    (event) => {
        if (editing?.rich) {
            event.preventDefault();
            document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        }
    },
    true,
);

/* ------------------------------------------- drop from the editor palette */

const TRAME_TYPE = 'application/x-trame';

function draggedLevel(event) {
    const types = [...(event.dataTransfer?.types ?? [])];

    if (!types.includes(TRAME_TYPE)) {
        return null;
    }

    const marker = types.find((type) => type.startsWith(`${TRAME_TYPE}-level-`));

    return marker ? marker.slice(`${TRAME_TYPE}-level-`.length) : null;
}

const outsideGlobal = (el) => el && !el.closest('[data-trame-node="global"], [data-trame-locked]');

/** Where a node of `level` would land under the pointer: { container, parentId, index, items }. */
function dropTarget(level, x, y) {
    const el = document.elementFromPoint(x, y);
    let container = null;
    let parentId = null;

    if (level === 'section') {
        container = root();
    } else if (level === 'row') {
        const section = el?.closest('[data-trame-node="section"]');
        container = outsideGlobal(section) ? section.firstElementChild : null;
        parentId = section?.dataset.trameId ?? null;
    } else if (level === 'block') {
        const column = el?.closest('[data-trame-node="column"]');
        container = outsideGlobal(column) ? column : null;
        parentId = column?.dataset.trameId ?? null;
    }

    if (!container) {
        return null;
    }

    const items = [...container.children].filter((child) => child.hasAttribute('data-trame-id'));
    const index = items.filter((item) => {
        const rect = item.getBoundingClientRect();

        return rect.top + rect.height / 2 < y;
    }).length;

    return { container, parentId, index, items };
}

function showDropLine(target) {
    if (!target) {
        dropLine.style.display = 'none';

        return;
    }

    const box = target.container.getBoundingClientRect();
    const before = target.items[target.index];
    const after = target.items[target.index - 1];
    const top = before
        ? before.getBoundingClientRect().top
        : after
          ? after.getBoundingClientRect().bottom
          : box.top + Math.min(box.height / 2, 16);

    Object.assign(dropLine.style, {
        display: 'block',
        top: `${top + window.scrollY}px`,
        left: `${box.left + window.scrollX}px`,
        width: `${Math.max(box.width, 24)}px`,
    });
}

// Capture on window so SortableJS never sees foreign drags.
window.addEventListener(
    'dragover',
    (event) => {
        const level = draggedLevel(event);

        if (!level) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const target = dropTarget(level, event.clientX, event.clientY);
        event.dataTransfer.dropEffect = target ? 'copy' : 'none';
        showDropLine(target);
    },
    true,
);

window.addEventListener(
    'drop',
    (event) => {
        const level = draggedLevel(event);

        if (!level) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        showDropLine(null);

        const target = dropTarget(level, event.clientX, event.clientY);
        let payload = null;

        try {
            payload = JSON.parse(event.dataTransfer.getData(TRAME_TYPE));
        } catch {
            return;
        }

        if (target && payload) {
            send({ type: 'trame:drop', payload, parentId: target.parentId, index: target.index });
        }
    },
    true,
);

window.addEventListener(
    'dragleave',
    (event) => {
        if (draggedLevel(event) && !event.relatedTarget) {
            showDropLine(null);
        }
    },
    true,
);

/* ----------------------------------------------------------------- inbox */

window.addEventListener('message', (event) => {
    if (event.origin !== origin || event.source !== window.parent) {
        return;
    }

    const message = event.data ?? {};

    if (message.type === 'trame:render') {
        // Never replace the DOM under the caret: apply once editing ends.
        if (editing) {
            pendingRender = message;
        } else {
            applyRender(message);
        }
    }

    if (message.type === 'trame:labels') {
        labels = { kinds: {}, blocks: {}, actions: {}, ...message.labels };
        selectedBox.querySelectorAll('[data-action]').forEach((button) => {
            const title = labels.actions?.[button.dataset.action];

            if (title) {
                button.title = title;
                button.setAttribute('aria-label', title);
            }
        });
        redraw();
    }

    if (message.type === 'trame:select') {
        selectedId = message.id ?? null;
        redraw();

        if (message.scroll) {
            find(selectedId)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }

    if (message.type === 'trame:drag-end') {
        showDropLine(null);
    }
});

initSortables();
send({ type: 'trame:ready' });
