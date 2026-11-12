/**
 * Feature "checks": translations missing in the edited locale and accessibility issues,
 * shown in the structure tree, on the locale switcher and in the right panel when
 * nothing is selected. See ../checks.js for the rules.
 */
import { accessibilityIssues, missingTranslations } from '../checks.js';

export default function checks() {
    // Results depend only on the document version and the locale: computed once per change.
    let cache = { key: null, value: null };

    const memo = (key, compute) => {
        if (cache.key !== key) {
            cache = { key, value: compute() };
        }

        return cache.value;
    };

    return {
        /** Map blockId => field names missing in `locale` (default: the edited locale). */
        missingIn(locale = this.locale) {
            this.version;

            if (!this.defs) {
                return new Map();
            }

            return memo(`missing:${this.version}:${locale}`, () => missingTranslations(this.doc, this.blockDefs, locale, this.fallback));
        },

        /** Number of blocks to translate into `locale` (0 for the fallback locale). */
        missingCount(locale) {
            this.version;

            return this.defs ? missingTranslations(this.doc, this.blockDefs, locale, this.fallback).size : 0;
        },

        isUntranslated(id) {
            return this.missingIn().has(id);
        },

        get checkIssues() {
            this.version;
            this.locale;

            if (!this.defs) {
                return [];
            }

            return memo(`issues:${this.version}:${this.locale}`, () => accessibilityIssues(this.doc, this.locale, this.fallback)).map((issue) => ({
                ...issue,
                message: this.t[`check_${issue.code}`]
                    ?.replace(':count', issue.params.count ?? '')
                    .replace(':from', issue.params.from ?? '')
                    .replace(':to', issue.params.to ?? ''),
            }));
        },

        hasIssue(id) {
            return this.checkIssues.some((issue) => issue.id === id);
        },

        /** Blocks to translate in the edited locale, with their label, for the checks panel. */
        get untranslatedBlocks() {
            return [...this.missingIn().keys()].map((id) => ({ id, label: this.tree.find((item) => item.id === id)?.label ?? id }));
        },
    };
}
