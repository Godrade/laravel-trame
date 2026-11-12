/** Feature "revisions": draft history and restore. See features/index.js. */
export default function revisions({ cfg, api }) {
    return {
        revs: { open: false, loading: false, failed: false, items: [], confirming: null, restoring: false },

        get publishedRevision() {
            return this.revs.items.find((item) => item.kind === 'publish') ?? null;
        },

        async openRevisions() {
            this.revs.open = true;
            this.revs.confirming = null;
            this.revs.loading = true;
            this.revs.failed = false;

            try {
                const result = await api.get(cfg.endpoints.revisions);
                this.revs.items = result.data;
            } catch (error) {
                this.revs.failed = true;
                this.revs.items = [];
            } finally {
                this.revs.loading = false;
            }
        },

        closeRevisions() {
            this.revs.open = false;
            this.revs.confirming = null;
        },

        revisionKind(kind) {
            return this.t[`revision_kind_${kind}`] ?? kind;
        },

        revisionAge(item) {
            const seconds = Math.round((new Date(item.updated_at) - Date.now()) / 1000);
            const formatter = new Intl.RelativeTimeFormat(document.documentElement.lang || undefined, { numeric: 'auto' });
            const units = [['day', 86400], ['hour', 3600], ['minute', 60]];

            for (const [unit, size] of units) {
                if (Math.abs(seconds) >= size) {
                    return formatter.format(Math.round(seconds / size), unit);
                }
            }

            return formatter.format(0, 'second');
        },

        /** First click confirms inline when unsaved changes would be lost. */
        async restoreRevision(item) {
            if (this.readOnly || this.revs.restoring) {
                return;
            }

            if (this.dirty && this.revs.confirming !== item.id) {
                this.revs.confirming = item.id;

                return;
            }

            this.revs.restoring = true;

            try {
                const result = await api.post(cfg.endpoints.revisionRestore.replace('__ID__', item.id), {});
                this.load(result);
                this.closeRevisions();
                this.notify(this.t.revision_restored, 'success');
            } catch (error) {
                this.fail(error);
            } finally {
                this.revs.restoring = false;
            }
        },
    };
}
