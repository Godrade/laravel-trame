/**
 * Feature "patterns": ready-made sections from defs.patterns, inserted after the
 * selected section (or at the end). See features/index.js.
 */
export default function patterns({ D }) {
    return {
        get patternList() {
            return this.defs?.patterns ?? [];
        },

        /** Position of the section holding the selected node, or -1. */
        selectedSectionPosition() {
            const id = this.selectedId;

            if (!id) {
                return -1;
            }

            const contains = (node) => {
                if (!node || typeof node !== 'object') {
                    return false;
                }

                if (node.id === id) {
                    return true;
                }

                return ['rows', 'columns', 'blocks'].some((key) => Array.isArray(node[key]) && node[key].some(contains));
            };

            return this.doc.sections.findIndex(contains);
        },

        insertPattern(pattern) {
            const section = D.reId(pattern.content);
            const after = this.selectedSectionPosition();

            this.mutate(() => {
                this.doc.sections.splice(after >= 0 ? after + 1 : this.doc.sections.length, 0, section);
            });

            this.select(section.id, true);
        },
    };
}
