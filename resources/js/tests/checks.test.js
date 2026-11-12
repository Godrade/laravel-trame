import { test } from 'node:test';
import assert from 'node:assert/strict';
import * as C from '../editor/checks.js';

const defs = {
    heading: { fields: [{ name: 'text', type: 'text', translatable: true }, { name: 'level', type: 'select' }] },
    image: { fields: [{ name: 'image', type: 'image' }, { name: 'alt', type: 'text', translatable: true }] },
    gallery: { fields: [{ name: 'images', type: 'repeater', fields: [{ name: 'image', type: 'image' }, { name: 'alt', type: 'text', translatable: true }, { name: 'caption', type: 'text', translatable: true }] }] },
};

const doc = (...blocks) => ({
    sections: [{ id: 'sec_1', type: 'section', rows: [{ id: 'row_1', type: 'row', columns: [{ id: 'col_1', blocks }] }] }, { id: 'glb_1', type: 'global', ref: 3 }],
});

test('lists blocks to translate, repeater sub-fields included, nothing for the fallback locale', () => {
    const d = doc(
        { id: 'a', type: 'heading', data: { text: { fr: 'Bonjour', en: 'Hello' } } },
        { id: 'b', type: 'heading', data: { text: { fr: 'Salut' } } },
        { id: 'c', type: 'gallery', data: { images: [{ id: 'i1', image: 1, alt: { fr: 'Chat' }, caption: { fr: '<p> </p>' } }] } },
        { id: 'd', type: 'heading', data: { text: {} } },
    );

    const map = C.missingTranslations(d, defs, 'en', 'fr');

    assert.deepEqual([...map.keys()], ['b', 'c']);
    assert.deepEqual(map.get('c'), ['images.alt']);
    assert.equal(C.missingTranslations(d, defs, 'fr', 'fr').size, 0);
});

test('flags images without alternative text, using the fallback like the rendering', () => {
    const d = doc(
        { id: 'a', type: 'image', data: { image: 1, alt: { fr: 'Un chat' } } },
        { id: 'b', type: 'image', data: { image: 2, alt: {} } },
        { id: 'c', type: 'image', data: { image: null, alt: {} } },
        { id: 'd', type: 'gallery', data: { images: [{ image: 1, alt: {} }, { image: 2, alt: { fr: 'Ok' } }, { image: 3 }] } },
        { id: 'e', type: 'card', data: { image: 4, alt: { en: 'Only en' } } },
    );

    assert.deepEqual(
        C.accessibilityIssues(d, 'en', 'fr').map((i) => [i.id, i.code, i.params.count ?? null]),
        [['b', 'image_alt', null], ['d', 'gallery_alt', 2]],
    );
    assert.deepEqual(C.accessibilityIssues(d, 'fr', 'fr').map((i) => i.id), ['b', 'd', 'e']);
});

test('flags skipped heading levels and repeated h1', () => {
    const d = doc(
        { id: 'a', type: 'heading', data: { level: 1 } },
        { id: 'b', type: 'heading', data: { level: 3 } },
        { id: 'c', type: 'heading', data: { level: 2 } },
        { id: 'd', type: 'heading', data: { level: 1 } },
    );

    assert.deepEqual(C.accessibilityIssues(d, 'fr', 'fr').map((i) => [i.id, i.code]), [['b', 'heading_skip'], ['d', 'heading_h1']]);
});
