import { test } from 'node:test';
import assert from 'node:assert/strict';
import * as D from '../editor/document.js';

const layout = {
    section: [{ name: 'padding', type: 'select', default: 'md' }],
    row: [{ name: 'gap', type: 'select', default: 'md' }],
    column: [{ name: 'valign', type: 'select', default: 'start' }],
};

const heading = { type: 'heading', version: 1, fields: [{ name: 'text', type: 'text', translatable: true, default: null }, { name: 'level', type: 'select', default: 2 }] };

function doc(...presets) {
    return { sections: presets.map((spans) => D.makeSection(spans, layout, 'fr')) };
}

test('makeSection applies presets and defaults', () => {
    const section = D.makeSection([8, 4], layout, 'fr');

    assert.match(section.id, /^sec_[a-z0-9]{10}$/);
    assert.deepEqual(section.settings, { padding: 'md' });
    assert.deepEqual(section.rows[0].columns.map((c) => c.span), [{ base: 12, md: 8 }, { base: 12, md: 4 }]);
    assert.deepEqual(D.makeSection([12], layout, 'fr').rows[0].columns[0].span, { base: 12 });
});

test('makeBlock fills translatable defaults on the fallback locale only', () => {
    const block = D.makeBlock({ ...heading, fields: [{ ...heading.fields[0], default: 'Titre' }, heading.fields[1]] }, 'fr');

    assert.deepEqual(block.data, { text: { fr: 'Titre' }, level: 2 });
    assert.equal(block.v, 1);
});

test('buildIndex exposes paths matching server error keys', () => {
    const d = doc([6, 6]);
    d.sections[0].rows[0].columns[1].blocks.push(D.makeBlock(heading, 'fr'));
    const index = D.buildIndex(d);
    const block = d.sections[0].rows[0].columns[1].blocks[0];

    assert.equal(index.get(block.id).path, 'document.sections.0.rows.0.columns.1.blocks.0');
    assert.equal(index.get(block.id).parentId, d.sections[0].rows[0].columns[1].id);
    assert.equal(index.size, 5);
});

test('global nodes are indexed but not descended into', () => {
    const d = { sections: [{ id: 'sec_g', type: 'global', ref: 3 }] };
    const entry = D.buildIndex(d).get('sec_g');

    assert.equal(entry.kind, 'global');
    assert.equal(entry.level, 'section');
});

test('errorMap attaches errors to the deepest node', () => {
    const d = doc([12]);
    const block = D.makeBlock(heading, 'fr');
    d.sections[0].rows[0].columns[0].blocks.push(block);
    const index = D.buildIndex(d);

    const map = D.errorMap({
        'document.sections.0.rows.0.columns.0.blocks.0.data.text.fr': ['Required'],
        'document.sections.0.settings.padding': ['Invalid'],
    }, index);

    assert.deepEqual(map.get(block.id), [{ rel: 'data.text.fr', messages: ['Required'] }]);
    assert.deepEqual(map.get(d.sections[0].id), [{ rel: 'settings.padding', messages: ['Invalid'] }]);
});

test('moveTo moves blocks across columns with final-index semantics', () => {
    const d = doc([6, 6]);
    const [left, right] = d.sections[0].rows[0].columns;
    const a = D.makeBlock(heading, 'fr');
    const b = D.makeBlock(heading, 'fr');
    left.blocks.push(a, b);

    assert.ok(D.moveTo(d, D.buildIndex(d), a.id, right.id, 0));
    assert.deepEqual(left.blocks.map((x) => x.id), [b.id]);
    assert.deepEqual(right.blocks.map((x) => x.id), [a.id]);

    right.blocks.push(D.makeBlock(heading, 'fr'), D.makeBlock(heading, 'fr'));
    assert.ok(D.moveTo(d, D.buildIndex(d), a.id, right.id, 2));
    assert.equal(right.blocks[2].id, a.id);
});

test('moveTo refuses invalid parents and emptying a row', () => {
    const d = doc([12], [6, 6]);
    const block = D.makeBlock(heading, 'fr');
    d.sections[0].rows[0].columns[0].blocks.push(block);
    const index = D.buildIndex(d);

    assert.equal(D.moveTo(d, index, block.id, d.sections[0].id, 0), false, 'block into a section');
    assert.equal(D.moveTo(d, index, d.sections[0].rows[0].columns[0].id, d.sections[1].rows[0].id, 0), false, 'last column of a row');
    assert.ok(D.moveTo(d, index, d.sections[1].id, null, 0), 'section reorder');
    assert.equal(d.sections[0].rows[0].columns.length, 2);
});

test('applyPreset merges blocks of removed columns', () => {
    const row = D.makeRow([4, 4, 4], layout, 'fr');
    row.columns.forEach((column, i) => column.blocks.push({ id: `blk_${i}`, type: 'heading', data: {} }));

    D.applyPreset(row, [6, 6], layout, 'fr');

    assert.equal(row.columns.length, 2);
    assert.deepEqual(row.columns[1].blocks.map((b) => b.id), ['blk_1', 'blk_2']);
    assert.deepEqual(row.columns[0].span, { base: 12, md: 6 });

    D.applyPreset(row, [3, 3, 3, 3], layout, 'fr');
    assert.equal(row.columns.length, 4);
});

test('addColumn rebalances when 12 divides the count', () => {
    const row = D.makeRow([6, 6], layout, 'fr');
    D.addColumn(row, layout, 'fr');

    assert.deepEqual(row.columns.map((c) => c.span.md), [4, 4, 4]);
});

test('reId gives fresh ids to every nested node', () => {
    const section = D.makeSection([6, 6], layout, 'fr');
    section.rows[0].columns[0].blocks.push(D.makeBlock(heading, 'fr'));
    const copy = D.reId(section);

    const ids = (node) => [node.id, ...['rows', 'columns', 'blocks'].flatMap((key) => (node[key] ?? []).flatMap(ids))];

    assert.equal(ids(copy).length, ids(section).length);
    assert.equal(ids(copy).filter((id) => ids(section).includes(id)).length, 0);
    assert.match(copy.rows[0].columns[0].blocks[0].id, /^blk_/);
});

test('effectiveSpan inherits from smaller breakpoints', () => {
    assert.equal(D.effectiveSpan({ base: 12, md: 6 }, 'lg'), 6);
    assert.equal(D.effectiveSpan({ base: 12 }, 'md'), 12);
    assert.equal(D.effectiveSpan({ base: 12, md: 6, lg: 3 }, 'base'), 12);
});

test('read/writeField only touch the editing locale', () => {
    const field = { name: 'text', translatable: true };
    const data = { text: [] }; // PHP empty array arrives as []

    D.writeField(data, field, 'en', 'Hello');
    D.writeField(data, field, 'fr', 'Bonjour');
    D.writeField(data, field, 'en', '');

    assert.deepEqual(data.text, { fr: 'Bonjour' });
    assert.equal(D.readField(data, field, 'en'), '');
    assert.equal(D.readField({ level: undefined }, { name: 'level', default: 2 }, 'fr'), 2);
});

test('templates wrap into a document and unwrap back', () => {
    const block = D.makeBlock(heading, 'fr');
    const doc = D.wrapTemplate('block', block, layout, 'fr');

    assert.equal(doc.sections[0].rows[0].columns[0].blocks[0].id, block.id);
    assert.deepEqual(D.unwrapTemplate('block', doc), block);
    assert.equal(D.templateRootId('block', doc), block.id);

    doc.sections[0].rows[0].columns[0].blocks.push(D.makeBlock(heading, 'fr'));
    assert.equal(D.unwrapTemplate('block', doc), null, 'two blocks cannot be saved as one block template');

    const row = D.makeRow([6, 6], layout, 'fr');
    assert.deepEqual(D.unwrapTemplate('row', D.wrapTemplate('row', row, layout, 'fr')), row);

    const section = D.makeSection([12], layout, 'fr');
    const sectionDoc = D.wrapTemplate('section', section, layout, 'fr');
    sectionDoc.sections.push(D.makeSection([12], layout, 'fr'));
    assert.equal(D.unwrapTemplate('section', sectionDoc), null);
});

test('template errors map onto wrapper paths', () => {
    assert.deepEqual(D.templateErrors('row', { 'content.columns.0.blocks.1.data.text.fr': ['Required'], name: ['x'] }), {
        'document.sections.0.rows.0.columns.0.blocks.1.data.text.fr': ['Required'],
        name: ['x'],
    });
});

/* ---------------------------------------------------------- responsive settings */

const align = { name: 'align', type: 'select', responsive: true, default: 'start' };

test('responsive fields default to a base value', () => {
    assert.deepEqual(D.fieldDefaults([align], 'fr'), { align: { base: 'start' } });
});

test('responsive values inherit from the breakpoint below', () => {
    const data = { align: { base: 'center', md: 'end' } };

    assert.equal(D.readResponsive(data, align, 'base'), 'center');
    assert.equal(D.readResponsive(data, align, 'md'), 'end');
    assert.equal(D.readResponsive(data, align, 'lg'), 'end');
    assert.equal(D.isResponsiveInherited(data, align, 'lg'), true);
    assert.equal(D.isResponsiveInherited(data, align, 'md'), false);
    assert.equal(D.readResponsive({ align: 'end' }, align, 'lg'), 'end', 'legacy scalar is the base value');
    assert.equal(D.readResponsive({}, align, 'md'), 'start', 'falls back to the field default');
});

test('writing a responsive value only touches one breakpoint, null resets it', () => {
    const data = { align: 'center' };

    D.writeResponsive(data, align, 'lg', 'start');
    assert.deepEqual(data.align, { base: 'center', lg: 'start' });

    D.writeResponsive(data, align, 'lg', null);
    assert.deepEqual(data.align, { base: 'center' });
});

test('toggling visibility keeps breakpoint order and drops empty settings on blocks', () => {
    const block = { id: 'blk_1', type: 'heading', data: {} };

    assert.deepEqual(D.toggleHidden(block, 'lg'), ['lg']);
    assert.deepEqual(D.toggleHidden(block, 'base'), ['base', 'lg']);
    assert.deepEqual(D.hiddenOn(block), ['base', 'lg']);
    D.toggleHidden(block, 'base');
    D.toggleHidden(block, 'lg');
    assert.equal(block.settings, undefined);

    const section = { id: 'sec_1', type: 'section', settings: { padding: 'md' }, rows: [] };
    D.toggleHidden(section, 'md');
    D.toggleHidden(section, 'md');
    assert.deepEqual(section.settings, { padding: 'md' });
});

const itemFields = [
    { name: 'image', type: 'image', default: null },
    { name: 'caption', type: 'text', translatable: true },
    { name: 'featured', type: 'toggle' },
];

test('fieldDefaults gives an empty list for repeaters', () => {
    assert.deepEqual(D.fieldDefaults([{ name: 'images', type: 'repeater', fields: itemFields }], 'fr'), { images: [] });
});

test('makeItem builds an id and sub-field defaults', () => {
    const item = D.makeItem(itemFields, 'fr');

    assert.match(item.id, /^itm_[a-z0-9]{10}$/);
    assert.deepEqual({ ...item, id: null }, { id: null, image: null, caption: {}, featured: false });
});

test('addItem appends, and stops at max', () => {
    const list = [];
    const first = D.addItem(list, itemFields, 'fr', 2);
    D.addItem(list, itemFields, 'fr', 2);

    assert.equal(list.length, 2);
    assert.equal(list[0], first);
    assert.equal(D.addItem(list, itemFields, 'fr', 2), null);
    assert.equal(list.length, 2);
    assert.notEqual(list[0].id, list[1].id);
});

test('removeItem removes by id', () => {
    const list = [{ id: 'a' }, { id: 'b' }];

    assert.equal(D.removeItem(list, 'a'), true);
    assert.deepEqual(list, [{ id: 'b' }]);
    assert.equal(D.removeItem(list, 'zzz'), false);
});

test('moveItem moves by delta and clamps at the bounds', () => {
    const list = [{ id: 'a' }, { id: 'b' }, { id: 'c' }];

    assert.equal(D.moveItem(list, 'a', 1), true);
    assert.deepEqual(list.map((i) => i.id), ['b', 'a', 'c']);
    assert.equal(D.moveItem(list, 'a', 5), true);
    assert.deepEqual(list.map((i) => i.id), ['b', 'c', 'a']);
    assert.equal(D.moveItem(list, 'a', 1), false);
    assert.equal(D.moveItem(list, 'b', -1), false);
    assert.equal(D.moveItem(list, 'nope', 1), false);
});

test('responsive fields accept a per-breakpoint default', () => {
    const columns = { name: 'columns', type: 'select', responsive: true, default: { base: '2', md: '3' } };

    assert.deepEqual(D.fieldDefaults([columns], 'fr'), { columns: { base: '2', md: '3' } });
    assert.equal(D.readResponsive({}, columns, 'base'), '2');
    assert.equal(D.readResponsive({}, columns, 'lg'), '3');
    assert.equal(D.readResponsive({ columns: { base: '1' } }, columns, 'lg'), '1', 'a stored value wins over the default');
});

test('detects per-screen overrides of responsive fields', () => {
    const align = { name: 'align', type: 'select', responsive: true, default: 'start' };

    assert.equal(D.hasResponsiveOverrides({ align: { base: 'center' } }, align), false);
    assert.equal(D.hasResponsiveOverrides({ align: 'center' }, align), false);
    assert.equal(D.hasResponsiveOverrides({ align: { base: 'center', lg: 'start' } }, align), true);
    assert.equal(D.hasResponsiveOverrides({}, align), false);
});

test('locked sections freeze their whole subtree', () => {
    const doc = {
        sections: [
            { id: 'sec_a', type: 'section', settings: { locked: true }, rows: [{ id: 'row_a', type: 'row', settings: {}, columns: [{ id: 'col_a', span: { base: 12 }, settings: {}, blocks: [{ id: 'blk_a', type: 'heading', data: {} }] }] }] },
            { id: 'sec_b', type: 'section', settings: {}, rows: [] },
        ],
    };
    const index = D.buildIndex(doc);

    assert.deepEqual(D.lockedSectionIds(doc), ['sec_a']);
    assert.equal(D.isInLockedSection(index, 'sec_a'), true);
    assert.equal(D.isInLockedSection(index, 'blk_a'), true);
    assert.equal(D.isInLockedSection(index, 'sec_b'), false);
});
