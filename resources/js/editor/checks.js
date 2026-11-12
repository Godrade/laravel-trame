/**
 * Content checks run in the editor (no DOM, no Alpine). Covered by resources/js/tests/checks.test.js.
 *
 * - missingTranslations: blocks whose translatable fields have a value in the fallback
 *   locale but none in the checked locale.
 * - accessibilityIssues: images without alternative text, heading levels that skip a step
 *   or repeat the page title (h1).
 *
 * Global references are skipped: their content lives in the template.
 */
import { isObject } from './document.js';

const filled = (value) => (typeof value === 'string' ? value.replace(/<[^>]*>/g, '').trim() !== '' : value !== null && value !== undefined);

/** Blocks in document order (sections > rows > columns > blocks), globals excluded. */
export function blocksInOrder(doc) {
    const out = [];

    for (const section of doc?.sections ?? []) {
        for (const row of section?.type === 'global' ? [] : section?.rows ?? []) {
            for (const column of row?.type === 'global' ? [] : row?.columns ?? []) {
                for (const block of column?.blocks ?? []) {
                    if (block && block.type !== 'global') {
                        out.push(block);
                    }
                }
            }
        }
    }

    return out;
}

/** Translatable field names (repeater sub-fields as "images.caption") missing in `locale`. */
function missingFields(container, fields, locale, fallback, prefix = '') {
    const missing = [];

    for (const field of fields ?? []) {
        const value = isObject(container) ? container[field.name] : undefined;

        if (field.type === 'repeater') {
            for (const item of Array.isArray(value) ? value : []) {
                for (const name of missingFields(item, field.fields, locale, fallback, `${prefix}${field.name}.`)) {
                    if (!missing.includes(name)) {
                        missing.push(name);
                    }
                }
            }

            continue;
        }

        if (field.translatable && isObject(value) && filled(value[fallback]) && !filled(value[locale])) {
            missing.push(prefix + field.name);
        }
    }

    return missing;
}

/** Map blockId => field names to translate into `locale` (empty for the fallback locale). */
export function missingTranslations(doc, blockDefs, locale, fallback) {
    const map = new Map();

    if (!locale || locale === fallback) {
        return map;
    }

    for (const block of blocksInOrder(doc)) {
        const fields = missingFields(block.data, blockDefs?.[block.type]?.fields, locale, fallback);

        if (fields.length) {
            map.set(block.id, fields);
        }
    }

    return map;
}

/** Value of a translatable field for a locale, the fallback filling the gaps (as rendered). */
function translated(value, locale, fallback) {
    if (!isObject(value)) {
        return value;
    }

    return filled(value[locale]) ? value[locale] : value[fallback];
}

/**
 * Issues: [{ id, code, params }]. Codes: image_alt, gallery_alt (params.count),
 * heading_skip (params.from, params.to), heading_h1 (more than one h1).
 */
export function accessibilityIssues(doc, locale, fallback) {
    const issues = [];
    let previousLevel = null;
    let h1Seen = false;

    for (const block of blocksInOrder(doc)) {
        const data = isObject(block.data) ? block.data : {};

        if ((block.type === 'image' || block.type === 'card') && data.image != null && !filled(translated(data.alt, locale, fallback))) {
            issues.push({ id: block.id, code: 'image_alt', params: {} });
        }

        if (block.type === 'gallery') {
            const count = (Array.isArray(data.images) ? data.images : []).filter((item) => item?.image != null && !filled(translated(item.alt, locale, fallback))).length;

            if (count) {
                issues.push({ id: block.id, code: 'gallery_alt', params: { count } });
            }
        }

        if (block.type === 'heading') {
            const level = Number(data.level ?? 2);

            if (level === 1 && h1Seen) {
                issues.push({ id: block.id, code: 'heading_h1', params: {} });
            }

            if (previousLevel !== null && level > previousLevel + 1) {
                issues.push({ id: block.id, code: 'heading_skip', params: { from: previousLevel, to: level } });
            }

            h1Seen ||= level === 1;
            previousLevel = level;
        }
    }

    return issues;
}
