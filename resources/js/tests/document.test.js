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
