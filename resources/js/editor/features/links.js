/**
 * Feature "links": internal links in url fields. A value "page:12" points to a page;
 * the picker lists pages from the links endpoint. See features/index.js.
 */
const REF = /^page:\d+$/;

export default function links({ cfg, api }) {
    let timer = null;
    let loadingAll = null;

    return {
        linkPicker: { open: false, search: '', items: [], loading: false, target: null, known: {} },

        isPageRef(value) {
            return typeof value === 'string' && REF.test(value);
        },

        endpointLinks(search = '') {
            const params = new URLSearchParams({ locale: this.locale });

            if (search) {
                params.set('q', search);
            }

            return `${cfg.endpoints.links}?${params}`;
        },

        rememberLinks(items) {
            const known = { ...this.linkPicker.known };

            for (const item of items) {
                known[item.ref] = item;
            }

            this.linkPicker.known = known;
        },

        /** Title of a referenced page; the list is fetched once, lazily. */
        linkTitle(ref) {
            const item = this.linkPicker.known[ref];

            if (item) {
                return item.label;
            }

            if (!loadingAll) {
                loadingAll = Promise.resolve()
                    .then(() => api.get(this.endpointLinks()))
                    .then((result) => this.rememberLinks(result.data ?? []))
                    .catch(() => {});
            }

            return `${this.t.link_page_label} ${ref.slice(5)}`;
        },

        openLinks(field, ctx = null) {
            this.linkPicker.target = { field, ctx };
            this.linkPicker.search = '';
            this.linkPicker.open = true;
            this.loadLinks();
        },

        searchLinks() {
            clearTimeout(timer);
            timer = setTimeout(() => this.loadLinks(), 250);
        },

        async loadLinks() {
            const search = this.linkPicker.search.trim();

            this.linkPicker.loading = true;

            try {
                const result = await api.get(this.endpointLinks(search));

                if (search === this.linkPicker.search.trim()) {
                    this.linkPicker.items = result.data ?? [];
                    this.rememberLinks(this.linkPicker.items);
                }
            } catch {
                this.linkPicker.items = [];
                this.notify(this.t.links_error, 'error');
            } finally {
                this.linkPicker.loading = false;
            }
        },

        pickLink(item) {
            const { field, ctx } = this.linkPicker.target;

            this.rememberLinks([item]);
            this.setValue(field, item.ref, ctx);
            this.linkPicker.open = false;
        },

        clearLink(field, ctx = null) {
            this.setValue(field, '', ctx);
        },
    };
}
