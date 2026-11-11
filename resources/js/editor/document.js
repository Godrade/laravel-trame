/**
 * Pure document operations (no DOM, no Alpine). Covered by resources/js/tests.
 *
 * Document shape (see README): sections > rows > columns > blocks.
 * Any section, row or block may be a global reference: { id, type: 'global', ref }.
 */

export const BREAKPOINTS = ['base', 'md', 'lg'];

export const MAX_COLUMNS = 12;

/** Column presets: md spans (mobile always stacks at 12). */
export const PRESETS = [
    { key: '12', spans: [12] },
    { key: '6-6', spans: [6, 6] },
    { key: '4-4-4', spans: [4, 4, 4] },
    { key: '3-3-3-3', spans: [3, 3, 3, 3] },
    { key: '8-4', spans: [8, 4] },
    { key: '4-8', spans: [4, 8] },
];

const ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

export function uid(prefix) {
    const bytes = crypto.getRandomValues(new Uint8Array(10));

    return `${prefix}_${Array.from(bytes, (b) => ALPHABET[b % ALPHABET.length]).join('')}`;
}

export function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

export function isObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

/** Initial values for a list of field definitions. */
export function fieldDefaults(fields, fallback) {
    const data = {};

    for (const field of fields ?? []) {
        if (field.translatable) {
            data[field.name] = field.default != null ? { [fallback]: field.default } : {};
        } else if (field.responsive) {
            data[field.name] = field.default != null ? { base: field.default } : null;
        } else {
            data[field.name] = field.default ?? (field.type === 'toggle' ? false : null);
        }
    }

    return data;
}

export function spanFor(md) {
    return md >= MAX_COLUMNS ? { base: 12 } : { base: 12, md };
}

export function makeColumn(md, layout, fallback) {
    return { id: uid('col'), span: spanFor(md), settings: fieldDefaults(layout.column, fallback), blocks: [] };
}

export function makeRow(spans, layout, fallback) {
    return {
        id: uid('row'),
        type: 'row',
        settings: fieldDefaults(layout.row, fallback),
        columns: spans.map((md) => makeColumn(md, layout, fallback)),
    };
}

export function makeSection(spans, layout, fallback) {
    return {
        id: uid('sec'),
        type: 'section',
        settings: fieldDefaults(layout.section, fallback),
        rows: [makeRow(spans, layout, fallback)],
    };
}

export function makeBlock(definition, fallback) {
    return { id: uid('blk'), type: definition.type, v: definition.version, data: fieldDefaults(definition.fields, fallback) };
}

export function makeGlobal(level, ref) {
    return { id: uid({ section: 'sec', row: 'row', block: 'blk' }[level] ?? 'glb'), type: 'global', ref };
}

/** Deep copy with fresh ids on every node (sections, rows, columns, blocks). */
export function reId(node) {
    const copy = clone(node);

    const walk = (item) => {
        if (!isObject(item)) {
            return;
        }

        if (typeof item.id === 'string') {
            item.id = uid(item.id.split('_')[0] || 'n');
        }

        for (const key of ['rows', 'columns', 'blocks']) {
            if (Array.isArray(item[key])) {
                item[key].forEach(walk);
            }
        }
    };

    walk(copy);

    return copy;
}

/**
 * Index every node by id.
 * Entry: { id, kind, level, node, list, position, parentId, path }
 *   kind  : section | row | column | block | global
 *   level : list the node lives in: section | row | column | block
 */
export function buildIndex(doc) {
    const index = new Map();

    const add = (node, level, list, position, parentId, path) => {
        if (!isObject(node) || typeof node.id !== 'string') {
            return null;
        }

        const kind = node.type === 'global' ? 'global' : level;
        const entry = { id: node.id, kind, level, node, list, position, parentId, path };

        index.set(node.id, entry);

        return entry;
    };

    (doc?.sections ?? []).forEach((section, i) => {
        const s = add(section, 'section', doc.sections, i, null, `document.sections.${i}`);

        if (!s || s.kind === 'global') {
            return;
        }

        (section.rows ?? []).forEach((row, j) => {
            const r = add(row, 'row', section.rows, j, section.id, `${s.path}.rows.${j}`);

            if (!r || r.kind === 'global') {
                return;
            }

            (row.columns ?? []).forEach((column, k) => {
                const c = add(column, 'column', row.columns, k, row.id, `${r.path}.columns.${k}`);

                if (!c) {
                    return;
                }

                (column.blocks ?? []).forEach((block, b) => {
                    add(block, 'block', column.blocks, b, column.id, `${c.path}.blocks.${b}`);
                });
            });
        });
    });

    return index;
}

/** Flat tree for the structure panel. */
export function flatten(doc) {
    const items = [];
    const index = buildIndex(doc);

    const depth = { section: 0, row: 1, column: 2, block: 3 };

    for (const entry of index.values()) {
        items.push({ id: entry.id, kind: entry.kind, level: entry.level, depth: depth[entry.level], position: entry.position });
    }

    return items;
}

export function ancestors(index, id) {
    const chain = [];
    let entry = index.get(id);

    while (entry) {
        chain.push(entry);
        entry = entry.parentId ? index.get(entry.parentId) : null;
    }

    return chain;
}

/** Closest entry of a given level (the entry itself included). */
export function closest(index, id, level) {
    return ancestors(index, id).find((entry) => entry.level === level && entry.kind !== 'global') ?? null;
}

/** Move a node by one position inside its own list. */
export function moveBy(index, id, delta) {
    const entry = index.get(id);

    if (!entry) {
        return false;
    }

    const target = entry.position + delta;

    if (target < 0 || target >= entry.list.length) {
        return false;
    }

    const [node] = entry.list.splice(entry.position, 1);
    entry.list.splice(target, 0, node);

    return true;
}

/** Child list a node of `level` would live in, for a given parent id (null = root). */
export function targetList(doc, index, level, parentId) {
    if (level === 'section') {
        return parentId === null ? doc.sections : null;
    }

    const parent = parentId ? index.get(parentId) : null;

    if (!parent || parent.kind === 'global') {
        return null;
    }

    const expected = { row: 'section', column: 'row', block: 'column' }[level];

    if (parent.level !== expected) {
        return null;
    }

    const key = { row: 'rows', column: 'columns', block: 'blocks' }[level];
    parent.node[key] ??= [];

    return parent.node[key];
}

/**
 * Move a node into another parent at a final position (SortableJS semantics:
 * `position` is the index once the move is done).
 */
export function moveTo(doc, index, id, parentId, position) {
    const entry = index.get(id);

    if (!entry) {
        return false;
    }

    const list = targetList(doc, index, entry.level, parentId ?? null);

    if (!list) {
        return false;
    }

    if (entry.level === 'column' && list !== entry.list && list.length >= MAX_COLUMNS) {
        return false;
    }

    if (entry.level === 'column' && list !== entry.list && entry.list.length === 1) {
        return false;
    }

    const [node] = entry.list.splice(entry.position, 1);
    const at = Math.max(0, Math.min(position, list.length));
    list.splice(at, 0, node);

    return true;
}

/** Apply a column preset to a row, merging blocks of removed columns into the last kept one. */
export function applyPreset(row, spans, layout, fallback) {
    const columns = row.columns ?? [];
    const kept = columns.slice(0, spans.length);

    if (columns.length > spans.length) {
        const last = kept[kept.length - 1];
        columns.slice(spans.length).forEach((column) => last.blocks.push(...(column.blocks ?? [])));
    }

    while (kept.length < spans.length) {
        kept.push(makeColumn(spans[kept.length], layout, fallback));
    }

    kept.forEach((column, i) => {
        column.span = spanFor(spans[i]);
    });

    row.columns = kept;

    return row;
}

/** Add a column and rebalance md spans evenly when 12 divides the count. */
export function addColumn(row, layout, fallback) {
    if ((row.columns ?? []).length >= MAX_COLUMNS) {
        return null;
    }

    const count = row.columns.length + 1;
    const column = makeColumn(Math.max(1, Math.floor(12 / count)), layout, fallback);
    row.columns.push(column);

    if (12 % count === 0) {
        row.columns.forEach((col) => {
            col.span = spanFor(12 / count);
        });
    }

    return column;
}

export function effectiveSpan(span, breakpoint) {
    const s = span ?? {};

    if (breakpoint === 'lg') {
        return s.lg ?? s.md ?? s.base ?? 12;
    }

    if (breakpoint === 'md') {
        return s.md ?? s.base ?? 12;
    }

    return s.base ?? 12;
}

/**
 * Map server error keys ("document.sections.0.rows.0...data.text.fr") to the
 * deepest node they belong to: Map id => [{ rel, messages }].
 */
export function errorMap(errors, index) {
    const map = new Map();
    const entries = [...index.values()].sort((a, b) => b.path.length - a.path.length);

    for (const [key, messages] of Object.entries(errors ?? {})) {
        const entry = entries.find((e) => key === e.path || key.startsWith(`${e.path}.`));

        if (!entry) {
            continue;
        }

        const rel = key === entry.path ? '' : key.slice(entry.path.length + 1);
        const list = map.get(entry.id) ?? [];
        list.push({ rel, messages });
        map.set(entry.id, list);
    }

    return map;
}

/** Read a field value for the editing locale. */
export function readField(container, field, locale) {
    const value = isObject(container) ? container[field.name] : undefined;

    if (field.translatable) {
        return isObject(value) ? value[locale] ?? '' : '';
    }

    return value === undefined ? field.default ?? null : value;
}

/** Write a field value (translatable fields only touch the editing locale). */
export function writeField(container, field, locale, value) {
    if (field.translatable) {
        const translations = isObject(container[field.name]) ? container[field.name] : {};

        if (value === '' || value === null || value === undefined) {
            delete translations[locale];
        } else {
            translations[locale] = value;
        }

        container[field.name] = translations;
    } else {
        container[field.name] = value;
    }
}

/** Responsive value as an object; a legacy scalar is the base value. */
function responsiveObject(value) {
    if (isObject(value)) {
        return value;
    }

    return value === null || value === undefined ? {} : { base: value };
}

/** Effective value of a responsive field on a breakpoint (lg inherits md, md inherits base). */
export function readResponsive(container, field, breakpoint) {
    const value = responsiveObject(isObject(container) ? container[field.name] : undefined);
    const chain = BREAKPOINTS.slice(0, BREAKPOINTS.indexOf(breakpoint) + 1).reverse();

    for (const key of chain) {
        if (value[key] !== undefined && value[key] !== null) {
            return value[key];
        }
    }

    return field.default ?? null;
}

export function isResponsiveInherited(container, field, breakpoint) {
    const value = responsiveObject(isObject(container) ? container[field.name] : undefined);

    return breakpoint !== 'base' && (value[breakpoint] === undefined || value[breakpoint] === null);
}

/** Set (or, with null on md/lg, reset to inherited) a responsive field on one breakpoint. */
export function writeResponsive(container, field, breakpoint, value) {
    const next = { ...responsiveObject(container[field.name]) };

    if ((value === null || value === undefined) && breakpoint !== 'base') {
        delete next[breakpoint];
    } else {
        next[breakpoint] = value;
    }

    container[field.name] = next;
}

/** Breakpoints a node is hidden on (settings.hide), in breakpoint order. */
export function hiddenOn(node) {
    const hide = node?.settings?.hide;

    return Array.isArray(hide) ? BREAKPOINTS.filter((breakpoint) => hide.includes(breakpoint)) : [];
}

/** Toggle visibility on one breakpoint; settings.hide is removed when empty. */
export function toggleHidden(node, breakpoint) {
    const hide = hiddenOn(node);
    const next = hide.includes(breakpoint) ? hide.filter((b) => b !== breakpoint) : BREAKPOINTS.filter((b) => b === breakpoint || hide.includes(b));

    if (next.length) {
        node.settings = { ...(isObject(node.settings) ? node.settings : {}), hide: next };
    } else if (isObject(node.settings)) {
        delete node.settings.hide;

        // Blocks (nodes with "data") only carry settings for hide.
        if ('data' in node && Object.keys(node.settings).length === 0) {
            delete node.settings;
        }
    }

    return next;
}

export function stripTags(html) {
    return String(html ?? '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/* ------------------------------------------------------------- templates */

/**
 * A template holds a single section, row or block. To edit it with the
 * regular editor it is wrapped into a document (section > row > column as
 * needed); unwrapTemplate() takes it back out.
 */
export function wrapTemplate(kind, content, layout, fallback) {
    const node = clone(content);

    if (kind === 'section') {
        return { sections: [node] };
    }

    const section = makeSection([12], layout, fallback);

    if (kind === 'row') {
        section.rows = [node];
    } else {
        section.rows[0].columns[0].blocks = [node];
    }

    return { sections: [section] };
}

/** The template node, or null when the wrapper no longer holds exactly one. */
export function unwrapTemplate(kind, doc) {
    const sections = doc?.sections ?? [];

    if (sections.length !== 1) {
        return null;
    }

    if (kind === 'section') {
        return sections[0];
    }

    const rows = sections[0].rows ?? [];

    if (rows.length !== 1) {
        return null;
    }

    if (kind === 'row') {
        return rows[0];
    }

    const columns = rows[0].columns ?? [];
    const blocks = columns.length === 1 ? columns[0].blocks ?? [] : [];

    return blocks.length === 1 ? blocks[0] : null;
}

export function templateRootId(kind, doc) {
    return unwrapTemplate(kind, doc)?.id ?? null;
}

const TEMPLATE_PATHS = {
    section: 'document.sections.0',
    row: 'document.sections.0.rows.0',
    block: 'document.sections.0.rows.0.columns.0.blocks.0',
};

/** Server errors on a template ("content.…") mapped onto the wrapper document paths. */
export function templateErrors(kind, errors) {
    const out = {};

    for (const [key, messages] of Object.entries(errors ?? {})) {
        out[key === 'content' || key.startsWith('content.') ? TEMPLATE_PATHS[kind] + key.slice('content'.length) : key] = messages;
    }

    return out;
}
