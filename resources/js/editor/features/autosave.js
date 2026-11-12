/** Feature "autosave": silent save 3 s after the last change. See features/index.js. */
export const AUTOSAVE_DELAY = 3000;

export default function autosave() {
    let timer = null;
    let failedSnapshot = null; // snapshot a silent save was rejected with 422 for

    return {
        autosaveEnabled: true,
        lastAutosaveAt: null,

        get autosaveLabel() {
            if (!this.lastAutosaveAt) {
                return '';
            }

            const time = new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
                hour: '2-digit',
                minute: '2-digit',
            }).format(this.lastAutosaveAt);

            return this.t.autosaved_at.replace(':time', time);
        },

        scheduleAutosave() {
            clearTimeout(timer);

            if (this.autosaveEnabled && this.dirty && !this.readOnly && !this.templateMode) {
                timer = setTimeout(() => this.runAutosave(), AUTOSAVE_DELAY);
            }
        },

        async runAutosave() {
            if (!this.autosaveEnabled || !this.dirty || this.readOnly || this.templateMode) {
                return;
            }

            if (this.saving) {
                this.scheduleAutosave();

                return;
            }

            if (failedSnapshot !== null && failedSnapshot === this.snapshot()) {
                return;
            }

            failedSnapshot = null;

            if (await this.save({ silent: true })) {
                this.lastAutosaveAt = new Date();
            }
        },

        init() {
            window.addEventListener('trame:changed', () => this.scheduleAutosave());
            window.addEventListener('trame:loaded', () => {
                clearTimeout(timer);
                failedSnapshot = null;
            });
            window.addEventListener('trame:saved', (event) => {
                // A change made while saving still needs its own save.
                if (this.dirty && event.detail.silent) {
                    this.scheduleAutosave();
                }
            });
            window.addEventListener('trame:save-failed', (event) => {
                if (event.detail.status === 422 && event.detail.silent) {
                    failedSnapshot = this.snapshot();
                }
            });
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    clearTimeout(timer);
                    this.runAutosave();
                }
            });
        },
    };
}
