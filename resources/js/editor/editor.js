import { api } from './api.js';
import * as D from './document.js';

/*
 * Non-reactive state kept outside Alpine: the node index (rebuilt after each
 * change, invalidated through the reactive `version` counter) and timers.
 */
let index = new Map();
let previewTimer = null;
let previewSeq = 0;
let toastTimer = null;
let pageState = null; // page document stashed while a template is being edited

const TRAME_TYPE = 'application/x-trame';

const FRAME_WIDTHS = { base: 390, md: 820 };

export default function editor() {
    const cfg = window.TrameEditor;

    return {
        cfg,
        t: cfg.strings,

        ready: false,
        loadError: null,
        defs: null,
        blockDefs: {},
        doc: { sections: [] },
        revision: 0,
        publishedAt: null,
        hasUnpublished: false,

        locale: cfg.locale,
        breakpoint: 'lg',
        panel: 'add',
        selectedId: null,
        version: 0,

        dirty: false,
        saving: false,
        publishing: false,
        errors: {},
        history: [],
        future: [],
        lastCoalesce: null,
        savedSnapshot: null,

        templates: [],
        mediaCache: {},
        media: { open: false, field: null, ctx: null, items: [], page: 1, total: 0, loading: false, uploading: false },
        templateDialog: { open: false, nodeId: null, name: '', global: false },
        templateMode: null, // { id, name, kind, is_global } while editing a template
        toast: null,

        frameReady: false,
        frameWidth: 1280,
        frameHeight: 800,
        scale: 1,
        presets: D.PRESETS,
        breakpoints: D.BREAKPOINTS,

        async init() {
            try {
                const [defs, content, templates] = await Promise.all([
                    api.get(cfg.endpoints.definitions),
                    api.get(cfg.endpoints.content),
                    api.get(cfg.endpoints.templates),
                ]);

                this.defs = defs;
                this.blockDefs = Object.fromEntries(defs.blocks.map((block) => [block.type, block]));
                this.templates = templates.data ?? [];
                this.load(content);

                if (!defs.locales.includes(this.locale)) {
                    this.locale = defs.fallback_locale;
                }

                this.ready = true;
            } catch (error) {
                this.loadError = error.message;

                return;
            }

            window.addEventListener('message', (event) => this.onFrameMessage(event));
            window.addEventListener('beforeunload', (event) => {
                if (this.dirty || (pageState && this.snapshot() !== pageState.savedSnapshot)) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });

            this.$nextTick(() => {
                new ResizeObserver(() => this.fit()).observe(this.$refs.stage);
                this.fit();
            });

            this.$watch('breakpoint', () => this.fit());
            this.$watch('locale', () => this.refreshPreview());
        },

        /* ------------------------------------------------------------------ state */

        load(content) {
            this.doc = content.document ?? { sections: [] };
            this.revision = content.revision ?? 0;
            this.publishedAt = content.published_at;
            this.hasUnpublished = content.has_unpublished_changes;
            this.history = [];
            this.future = [];
            this.savedSnapshot = this.snapshot();
            this.dirty = false;
            this.reindex();
        },

        reindex() {
            index = D.buildIndex(this.doc);
            this.version++;
        },

        get fallback() {
            return this.defs?.fallback_locale ?? this.locale;
        },

        get selected() {
            this.version;

            return this.selectedId ? index.get(this.selectedId) ?? null : null;
        },

        snapshot() {
            return JSON.stringify(this.doc);
        },

        /**
         * Every document change goes through here: history, dirty flag, index, preview.
         * Consecutive changes with the same coalesce key (typing) share one undo step.
         */
        mutate(fn, coalesceKey = null) {
            const now = Date.now();
            const coalesce = coalesceKey !== null && this.lastCoalesce?.key === coalesceKey && now - this.lastCoalesce.at < 1200;

            if (!coalesce) {
                this.history.push(this.snapshot());

                if (this.history.length > 100) {
                    this.history.shift();
                }
            }

            this.future = [];
            this.lastCoalesce = coalesceKey !== null ? { key: coalesceKey, at: now } : null;

            const result = fn();
            this.changed();

            return result;
        },

        changed() {
            // Undoing back to the saved state is not a change.
            this.dirty = this.snapshot() !== this.savedSnapshot;
            this.reindex();

            if (this.selectedId && !index.has(this.selectedId)) {
                this.selectedId = null;
            }

            this.schedulePreview();
        },

        undo() {
            if (!this.history.length) {
                return;
            }

            this.future.push(this.snapshot());
            this.doc = JSON.parse(this.history.pop());
            this.lastCoalesce = null;
            this.changed();
        },

        redo() {
            if (!this.future.length) {
                return;
            }

            this.history.push(this.snapshot());
            this.doc = JSON.parse(this.future.pop());
            this.lastCoalesce = null;
            this.changed();
        },

        /* ---------------------------------------------------------------- preview */

        get frameSrc() {
            return `${cfg.endpoints.frame}?locale=${encodeURIComponent(cfg.locale)}`;
        },

        schedulePreview() {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(() => this.refreshPreview(), 250);
        },

        async refreshPreview() {
            clearTimeout(previewTimer);

            const seq = ++previewSeq;

            try {
                const result = await api.post(cfg.endpoints.preview, { document: this.doc, locale: this.locale });

                if (seq !== previewSeq) {
                    return;
                }

                this.errors = result.errors ?? {};
                this.postFrame({ type: 'trame:render', html: result.html, selected: this.selectedId });
            } catch (error) {
                if (seq === previewSeq) {
                    this.fail(error);
                }
            }
        },

        postFrame(message) {
            this.$refs.frame?.contentWindow?.postMessage(message, window.location.origin);
        },

        onFrameMessage(event) {
            if (event.origin !== window.location.origin || event.source !== this.$refs.frame?.contentWindow) {
                return;
            }

            const message = event.data ?? {};

            switch (message.type) {
                case 'trame:ready':
                    this.frameReady = true;
                    this.refreshPreview();
                    break;
                case 'trame:select':
                    this.select(message.id);
                    break;
                case 'trame:action':
                    this.action(message.action, message.id);
                    break;
                case 'trame:move':
                    this.moveTo(message.id, message.parentId, message.index);
                    break;
                case 'trame:key':
                    this.onKey(message, true);
                    break;
                case 'trame:drop':
                    this.dropNode(message.payload, message.parentId, message.index);
                    break;
                case 'trame:inline':
                    this.inlineEdit(message.id, message.field, message.value);
                    break;
            }
        },

        fit() {
            const stage = this.$refs.stage;

            if (!stage) {
                return;
            }

            const available = Math.max(320, stage.clientWidth - 48);
            const width = FRAME_WIDTHS[this.breakpoint] ?? Math.max(1280, available);

            this.frameWidth = width;
            this.scale = Math.min(1, available / width);
            this.frameHeight = Math.max(400, (stage.clientHeight - 48) / this.scale);
        },

        /* -------------------------------------------------------------- selection */

        select(id, scroll = false) {
            this.selectedId = id && index.has(id) ? id : null;
            this.postFrame({ type: 'trame:select', id: this.selectedId, scroll });
        },

        selectParent() {
            const entry = this.selected;

            if (entry?.parentId) {
                this.select(entry.parentId, true);
            }
        },

        get tree() {
            this.version;
            const errors = this.errorMap;

            return D.flatten(this.doc).map((item) => ({
                ...item,
                label: this.labelOf(index.get(item.id)),
                error: errors.has(item.id),
            }));
        },

        get errorMap() {
            this.version;

            return D.errorMap(this.errors, index);
        },

        get errorCount() {
            return Object.keys(this.errors ?? {}).length;
        },

        labelOf(entry) {
            if (!entry) {
                return '';
            }

            const node = entry.node;

            switch (entry.kind) {
                case 'section':
                    return `${this.t.section} ${entry.position + 1}`;
                case 'row':
                    return `${this.t.row} · ${(node.columns ?? []).map((c) => D.effectiveSpan(c.span, this.breakpoint)).join(' / ')}`;
                case 'column':
                    return `${this.t.column} · ${D.effectiveSpan(node.span, this.breakpoint)}/12`;
                case 'global':
                    return `◆ ${this.templateName(node.ref)}`;
                default: {
                    const definition = this.blockDefs[node.type];
                    const label = definition?.label ?? node.type;
                    const textField = definition?.fields.find((f) => f.translatable && ['text', 'textarea', 'rich_text'].includes(f.type));
                    const excerpt = textField ? D.stripTags(D.readField(node.data, textField, this.locale) || D.readField(node.data, textField, this.fallback)) : '';

                    return excerpt ? `${label} · ${excerpt.slice(0, 32)}${excerpt.length > 32 ? '…' : ''}` : label;
                }
            }
        },

        kindLabel(entry) {
            if (!entry) {
                return '';
            }

            return entry.kind === 'block' ? this.blockDefs[entry.node.type]?.label ?? entry.node.type : this.t[entry.kind];
        },

        /* ------------------------------------------------------------- structure */

        addSection(preset, afterId = null) {
            const section = D.makeSection(preset.spans, this.defs.layout, this.fallback);
            const anchor = afterId ? D.closest(index, afterId, 'section') ?? index.get(afterId) : null;

            this.mutate(() => {
                const position = anchor && anchor.level === 'section' ? anchor.position + 1 : this.doc.sections.length;
                this.doc.sections.splice(position, 0, section);
            });

            this.select(section.rows[0].columns[0].id, true);
        },

        addRow(preset) {
            const section = this.selected ? D.closest(index, this.selected.id, 'section') : null;

            if (!section) {
                return this.addSection(preset);
            }

            const row = D.makeRow(preset.spans, this.defs.layout, this.fallback);
            this.mutate(() => {
                section.node.rows ??= [];
                section.node.rows.push(row);
            });
            this.select(row.columns[0].id, true);
        },

        applyPreset(rowId, preset) {
            const entry = index.get(rowId);

            if (entry?.kind === 'row') {
                this.mutate(() => D.applyPreset(entry.node, preset.spans, this.defs.layout, this.fallback));
            }
        },

        addColumn(rowId) {
            const entry = index.get(rowId);

            if (entry?.kind !== 'row') {
                return;
            }

            if (entry.node.columns.length >= D.MAX_COLUMNS) {
                return this.notify(this.t.max_columns, 'error');
            }

            const column = this.mutate(() => D.addColumn(entry.node, this.defs.layout, this.fallback));
            this.select(column.id);
        },

        /** Column that receives new blocks, created on demand. */
        insertionPoint() {
            const entry = this.selected;

            if (entry) {
                if (entry.level === 'block') {
                    return { column: index.get(entry.parentId), position: entry.position + 1 };
                }

                if (entry.kind === 'column') {
                    return { column: entry, position: entry.node.blocks.length };
                }

                const row = entry.kind === 'row' ? entry : null;
                const section = D.closest(index, entry.id, 'section');
                const firstRow = row ?? (section ? index.get(section.node.rows?.find((r) => r.type !== 'global')?.id) : null);

                if (firstRow?.node.columns?.length) {
                    const column = index.get(firstRow.node.columns[0].id);

                    return { column, position: column.node.blocks.length };
                }
            }

            return null;
        },

        addBlock(type) {
            const block = D.makeBlock(this.blockDefs[type], this.fallback);
            this.insertBlockNode(block);
            this.select(block.id, true);
        },

        insertBlockNode(node) {
            const point = this.insertionPoint();

            this.mutate(() => {
                if (point) {
                    point.column.node.blocks.splice(point.position, 0, node);

                    return;
                }

                const section = D.makeSection([12], this.defs.layout, this.fallback);
                section.rows[0].columns[0].blocks.push(node);
                this.doc.sections.push(section);
            });
        },

        duplicate(id) {
            const entry = index.get(id);

            if (!entry) {
                return;
            }

            if (entry.level === 'column' && entry.list.length >= D.MAX_COLUMNS) {
                return this.notify(this.t.max_columns, 'error');
            }

            const copy = D.reId(entry.node);
            this.mutate(() => entry.list.splice(entry.position + 1, 0, copy));
            this.select(copy.id);
        },

        remove(id) {
            const entry = index.get(id);

            if (!entry) {
                return;
            }

            if (entry.level === 'column' && entry.list.length === 1) {
                return this.notify(this.t.last_column, 'error');
            }

            const parentId = entry.parentId;
            this.mutate(() => entry.list.splice(entry.position, 1));
            this.select(parentId);
        },

        move(id, delta) {
            if (index.has(id)) {
                this.mutate(() => D.moveBy(index, id, delta));
                this.postFrame({ type: 'trame:select', id: this.selectedId, scroll: true });
            }
        },

        moveTo(id, parentId, position) {
            const before = this.snapshot();
            const ok = this.mutate(() => D.moveTo(this.doc, index, id, parentId, position));

            if (!ok) {
                // Refused move (e.g. last column): drop the history entry and re-sync the frame.
                this.history.pop();
                this.doc = JSON.parse(before);
                this.reindex();
                this.refreshPreview();
            }
        },

        action(name, id) {
            const actions = {
                up: () => this.move(id, -1),
                down: () => this.move(id, 1),
                duplicate: () => this.duplicate(id),
                delete: () => this.remove(id),
                parent: () => {
                    this.select(id);
                    this.selectParent();
                },
                template: () => this.openTemplateDialog(id),
            };

            actions[name]?.();
        },

        /* ------------------------------------------------- palette drag & drop */

        /** Palette/template items are draggable into the canvas (native DnD crosses the iframe). */
        dragStart(event, payload) {
            const level = payload.kind === 'template'
                ? this.templates.find((t) => t.id === payload.id)?.kind
                : payload.kind;

            if (!level) {
                return;
            }

            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData(TRAME_TYPE, JSON.stringify(payload));
            event.dataTransfer.setData(`${TRAME_TYPE}-level-${level}`, '1');
        },

        dragEnd() {
            this.postFrame({ type: 'trame:drag-end' });
        },

        /** Build the node a palette payload stands for. */
        nodeFromPayload(payload) {
            if (payload?.kind === 'block' && this.blockDefs[payload.type]) {
                return { level: 'block', node: D.makeBlock(this.blockDefs[payload.type], this.fallback) };
            }

            if (payload?.kind === 'section' && Array.isArray(payload.spans)) {
                return { level: 'section', node: D.makeSection(payload.spans, this.defs.layout, this.fallback) };
            }

            const template = payload?.kind === 'template' ? this.templates.find((t) => t.id === payload.id) : null;

            if (template) {
                return {
                    level: template.kind,
                    node: template.is_global ? D.makeGlobal(template.kind, template.id) : D.reId(template.content),
                };
            }

            return null;
        },

        dropNode(payload, parentId, position) {
            const built = this.nodeFromPayload(payload);

            if (!built) {
                return;
            }

            const ok = this.mutate(() => {
                const list = D.targetList(this.doc, index, built.level, parentId ?? null);

                if (!list) {
                    return false;
                }

                list.splice(Math.max(0, Math.min(position, list.length)), 0, built.node);

                return true;
            });

            if (!ok) {
                this.history.pop();

                return;
            }

            const first = built.level === 'section' && built.node.rows ? built.node.rows[0]?.columns?.[0]?.id : null;
            this.select(first ?? built.node.id);
        },

        /* ----------------------------------------------------- in-place edits */

        inlineEdit(id, name, value) {
            const entry = index.get(id);
            const field = entry?.kind === 'block' ? this.blockDefs[entry.node.type]?.fields.find((f) => f.name === name) : null;

            if (!field) {
                return;
            }

            if (!D.isObject(entry.node.data)) {
                entry.node.data = {};
            }

            this.mutate(() => D.writeField(entry.node.data, field, this.locale, value), `${id}:${name}:${this.locale}`);
        },

        /* --------------------------------------------------------- template mode */

        /** Edit a template's content with the full editor, the page being stashed meanwhile. */
        editTemplate(template) {
            if (!template || this.templateMode) {
                return;
            }

            pageState = {
                doc: this.doc,
                history: this.history,
                future: this.future,
                savedSnapshot: this.savedSnapshot,
                selectedId: this.selectedId,
                errors: this.errors,
            };

            this.templateMode = { id: template.id, name: template.name, kind: template.kind, is_global: template.is_global };
            this.doc = D.wrapTemplate(template.kind, template.content, this.defs.layout, this.fallback);
            this.history = [];
            this.future = [];
            this.errors = {};
            this.savedSnapshot = this.snapshot();
            this.lastCoalesce = null;
            this.reindex();
            this.dirty = false;
            this.select(D.templateRootId(template.kind, this.doc));
            this.refreshPreview();
        },

        async saveTemplateContent() {
            const mode = this.templateMode;
            const content = D.unwrapTemplate(mode.kind, this.doc);

            if (!content) {
                return this.notify(this.t.template_single, 'error');
            }

            this.saving = true;
            const sent = this.snapshot();

            try {
                const saved = await api.put(cfg.endpoints.template.replace('__ID__', mode.id), {
                    name: mode.name,
                    is_global: mode.is_global,
                    content,
                });

                this.templates = this.templates.map((t) => (t.id === saved.id ? saved : t));
                this.savedSnapshot = sent;
                this.dirty = this.snapshot() !== sent;
                this.errors = {};
                this.notify(this.t.template_saved, 'success');

                return true;
            } catch (error) {
                if (error.status === 422 && error.payload?.errors) {
                    this.errors = D.templateErrors(mode.kind, error.payload.errors);
                    this.notify(this.t.validation_failed, 'error');
                } else {
                    this.fail(error);
                }

                return false;
            } finally {
                this.saving = false;
            }
        },

        leaveTemplate() {
            if (!this.templateMode || (this.dirty && !window.confirm(this.t.discard_changes))) {
                return;
            }

            Object.assign(this, {
                doc: pageState.doc,
                history: pageState.history,
                future: pageState.future,
                savedSnapshot: pageState.savedSnapshot,
                errors: pageState.errors,
                templateMode: null,
                lastCoalesce: null,
            });

            this.reindex();
            this.dirty = this.snapshot() !== this.savedSnapshot;
            this.select(pageState.selectedId);
            pageState = null;
            this.refreshPreview();
        },

        /* ---------------------------------------------------------------- fields */

        get panelFields() {
            const entry = this.selected;

            if (!entry || !this.defs) {
                return [];
            }

            if (entry.kind === 'block') {
                return [...(this.blockDefs[entry.node.type]?.fields ?? []), ...this.blockLayoutFields];
            }

            return { section: this.defs.layout.section, row: this.defs.layout.row, column: this.defs.layout.column }[entry.kind] ?? [];
        },

        /** Settings shared by every block (margins), stored in node.settings rather than node.data. */
        get blockLayoutFields() {
            // Group headings (Spacing, Appearance) come from the server definitions.
            return (this._blockLayoutFields ??= (this.defs?.layout.block ?? []).map((field) => ({ ...field, scope: 'settings' })));
        },

        scopeOf(field) {
            return field?.scope ?? (this.selected?.kind === 'block' ? 'data' : 'settings');
        },

        /**
         * Object holding the field's value (node.data or node.settings). Reading never
         * changes the document: a missing object is only created when writing (create).
         */
        container(field = null, create = false, ctx = null) {
            // Inside a repeater, the item itself holds the sub-field values.
            if (ctx) {
                return ctx;
            }

            const entry = this.selected;

            if (!entry) {
                return null;
            }

            const key = this.scopeOf(field);

            if (!D.isObject(entry.node[key])) {
                if (!create) {
                    return {};
                }

                entry.node[key] = {};
            }

            return entry.node[key];
        },

        value(field, ctx = null) {
            this.version;
            this.locale;
            this.breakpoint;

            return field.responsive
                ? D.readResponsive(this.container(field, false, ctx), field, this.breakpoint)
                : D.readField(this.container(field, false, ctx), field, this.locale);
        },

        setValue(field, value, ctx = null) {
            if (!this.selected) {
                return;
            }

            if (ctx) {
                this.mutate(() => D.writeField(ctx, field, this.locale, value), `${this.selectedId}:${ctx.id}:${field.name}:${this.locale}`);

                return;
            }

            // The container is created inside mutate() so undo also removes it.
            if (field.responsive) {
                this.mutate(() => D.writeResponsive(this.container(field, true), field, this.breakpoint, value), `${this.selectedId}:${field.name}:${this.breakpoint}`);

                return;
            }

            this.mutate(() => D.writeField(this.container(field, true), field, this.locale, value), `${this.selectedId}:${field.name}:${this.locale}`);
        },

        /** Responsive field without its own value on the current breakpoint. */
        isInherited(field, ctx = null) {
            this.version;

            return !!field.responsive && D.isResponsiveInherited(this.container(field, false, ctx), field, this.breakpoint);
        },

        /* ------------------------------------------------------------- repeaters */

        /** Items of a repeater field (read only: never creates the list). */
        items(field) {
            this.version;

            const list = this.container(field)[field.name];

            return Array.isArray(list) ? list : [];
        },

        canAddItem(field) {
            return this.items(field).length < (field.max ?? Infinity);
        },

        /** The list is created inside mutate() so undo also removes it. */
        itemsForWrite(field) {
            const container = this.container(field, true);

            if (!Array.isArray(container[field.name])) {
                container[field.name] = [];
            }

            return container[field.name];
        },

        addItem(field) {
            if (!this.selected || !this.canAddItem(field)) {
                return;
            }

            this.mutate(() => D.addItem(this.itemsForWrite(field), field.fields, this.fallback, field.max ?? Infinity));
        },

        removeItem(field, item) {
            if (this.selected) {
                this.mutate(() => D.removeItem(this.itemsForWrite(field), item.id));
            }
        },

        moveItem(field, item, delta) {
            if (this.selected) {
                this.mutate(() => D.moveItem(this.itemsForWrite(field), item.id, delta));
            }
        },

        /** Short label of an item: first text sub-field (editing locale, then fallback), else its image name. */
        itemPreview(field, item) {
            this.version;

            for (const sub of field.fields ?? []) {
                if (['text', 'textarea'].includes(sub.type)) {
                    const text = D.stripTags(String(D.readField(item, sub, this.locale) || D.readField(item, sub, this.fallback) || ''));

                    if (text) {
                        return text;
                    }
                }
            }

            const image = (field.fields ?? []).find((sub) => sub.type === 'image');
            const media = image ? this.mediaItem(item[image.name]) : null;

            return media?.name ?? '';
        },

        /** Errors of one item sub-field, from paths like data.images.0.caption(.fr). */
        itemFieldErrors(field, index, sub, item) {
            return this.fieldErrors(sub, item, `${this.scopeOf(field)}.${field.name}.${index}.${sub.name}`);
        },

        /* ----------------------------------------------------------- visibility */

        get canHide() {
            return ['section', 'row', 'column', 'block'].includes(this.selected?.kind);
        },

        isHidden(breakpoint) {
            this.version;

            return D.hiddenOn(this.selected?.node).includes(breakpoint);
        },

        toggleHidden(breakpoint) {
            const entry = this.selected;

            if (entry) {
                this.mutate(() => D.toggleHidden(entry.node, breakpoint));
            }
        },

        placeholder(field, ctx = null) {
            if (!field.translatable || this.locale === this.fallback) {
                return '';
            }

            return D.stripTags(D.readField(this.container(field, false, ctx), field, this.fallback));
        },

        isSelectedOption(field, option, ctx = null) {
            return String(this.value(field, ctx)) === String(option.value);
        },

        fieldErrors(field, ctx = null, path = null) {
            const prefix = path ?? `${this.scopeOf(field)}.${field.name}`;
            const all = this.errorMap.get(this.selectedId) ?? [];

            // A repeater only shows its own list errors; its items show theirs.
            // Errors on another locale (e.g. required fallback while editing "en") are tagged with it.
            const errors = field.type === 'repeater'
                ? all.filter((error) => error.rel === prefix)
                : all.filter((error) => error.rel === prefix || error.rel.startsWith(`${prefix}.`));
            // "field required" + "field.fr required" say the same thing: keep the precise one.
            const precise = errors.some((error) => error.rel !== prefix);

            return errors
                .filter((error) => !(precise && error.rel === prefix))
                .flatMap((error) => {
                    const locale = field.translatable ? error.rel.slice(prefix.length + 1) : '';
                    const tag = locale && locale !== this.locale ? ` [${locale.toUpperCase()}]` : '';

                    return error.messages.map((message) => message + tag);
                });
        },

        get nodeErrors() {
            return (this.errorMap.get(this.selectedId) ?? []).filter((error) => !/^(data|settings)\./.test(error.rel)).flatMap((e) => e.messages);
        },

        swatch(color) {
            return color?.startsWith('#') ? color : `var(--color-${color}, transparent)`;
        },

        /* -------------------------------------------------------------- columns */

        spanValue(breakpoint = this.breakpoint) {
            return D.effectiveSpan(this.selected?.node.span, breakpoint);
        },

        spanIsInherited(breakpoint = this.breakpoint) {
            return breakpoint !== 'base' && this.selected?.node.span?.[breakpoint] === undefined;
        },

        setSpan(value, breakpoint = this.breakpoint) {
            const entry = this.selected;

            if (entry?.kind !== 'column') {
                return;
            }

            this.mutate(() => {
                entry.node.span ??= { base: 12 };

                if (value === null) {
                    delete entry.node.span[breakpoint];
                } else {
                    entry.node.span[breakpoint] = Math.max(1, Math.min(12, Number(value)));
                }
            }, `span:${entry.id}:${breakpoint}`);
        },

        /* ---------------------------------------------------------------- media */

        mediaItem(id) {
            if (id === null || id === undefined || id === '') {
                return null;
            }

            if (!(id in this.mediaCache)) {
                this.mediaCache[id] = null;
                api.get(cfg.endpoints.mediaShow.replace('__ID__', encodeURIComponent(id)))
                    .then((item) => (this.mediaCache[id] = item))
                    .catch(() => (this.mediaCache[id] = { id, url: '', name: this.t.error }));
            }

            return this.mediaCache[id];
        },

        openMedia(field, ctx = null) {
            this.media.field = field;
            this.media.ctx = ctx;
            this.media.open = true;
            this.loadMedia(1);
        },

        async loadMedia(page) {
            this.media.loading = true;

            try {
                const result = await api.get(`${cfg.endpoints.media}?page=${page}`);
                this.media.items = result.data;
                this.media.total = result.total;
                this.media.page = page;
                result.data.forEach((item) => (this.mediaCache[item.id] = item));
            } catch (error) {
                this.fail(error);
            } finally {
                this.media.loading = false;
            }
        },

        async upload(files) {
            if (!files?.length) {
                return;
            }

            this.media.uploading = true;

            try {
                let last = null;

                for (const file of files) {
                    const form = new FormData();
                    form.append('file', file);
                    last = await api.upload(cfg.endpoints.media, form);
                    this.mediaCache[last.id] = last;
                }

                await this.loadMedia(1);

                if (last && files.length === 1) {
                    this.pickMedia(last);
                }
            } catch (error) {
                this.fail(error);
            } finally {
                this.media.uploading = false;
            }
        },

        pickMedia(item) {
            if (this.media.field) {
                this.setValue(this.media.field, item.id, this.media.ctx);
            }

            this.media.open = false;
        },

        /* ------------------------------------------------------------- templates */

        templateName(ref) {
            return this.templates.find((t) => t.id === ref)?.name ?? this.t.missing_template;
        },

        templatesOf(kind) {
            return this.templates.filter((t) => t.kind === kind);
        },

        canTemplate(entry) {
            return entry && ['section', 'row', 'block'].includes(entry.kind);
        },

        openTemplateDialog(id) {
            const entry = index.get(id);

            if (!this.canTemplate(entry)) {
                return;
            }

            this.select(id);
            this.templateDialog = { open: true, nodeId: id, name: this.labelOf(entry), global: false };
        },

        async saveTemplate() {
            const dialog = this.templateDialog;
            const entry = index.get(dialog.nodeId);

            if (!entry || !dialog.name.trim()) {
                return;
            }

            try {
                const template = await api.post(cfg.endpoints.templates, {
                    name: dialog.name.trim(),
                    kind: entry.level,
                    is_global: dialog.global,
                    content: entry.node,
                });

                this.templates.push(template);
                this.templateDialog.open = false;

                if (template.is_global) {
                    const ref = D.makeGlobal(entry.level, template.id);
                    this.mutate(() => entry.list.splice(entry.position, 1, ref));
                    this.select(ref.id);
                }

                this.notify(this.t.template_saved, 'success');
            } catch (error) {
                this.fail(error);
            }
        },

        insertTemplate(template) {
            const node = template.is_global ? D.makeGlobal(template.kind, template.id) : D.reId(template.content);

            if (template.kind === 'block') {
                this.insertBlockNode(node);
            } else if (template.kind === 'row') {
                const section = this.selected ? D.closest(index, this.selected.id, 'section') : null;

                this.mutate(() => {
                    if (section) {
                        section.node.rows.push(node);
                    } else {
                        const wrapper = D.makeSection([12], this.defs.layout, this.fallback);
                        wrapper.rows = [node];
                        this.doc.sections.push(wrapper);
                    }
                });
            } else {
                const anchor = this.selected ? D.closest(index, this.selected.id, 'section') ?? this.selected : null;

                this.mutate(() => {
                    const position = anchor?.level === 'section' ? anchor.position + 1 : this.doc.sections.length;
                    this.doc.sections.splice(position, 0, node);
                });
            }

            this.select(node.id, true);
        },

        async deleteTemplate(template) {
            if (!window.confirm(this.t.confirm_delete_template)) {
                return;
            }

            try {
                await api.delete(cfg.endpoints.template.replace('__ID__', template.id));
                this.templates = this.templates.filter((t) => t.id !== template.id);
                this.refreshPreview();
            } catch (error) {
                this.fail(error);
            }
        },

        detach(id) {
            const entry = index.get(id);
            const template = entry ? this.templates.find((t) => t.id === entry.node.ref) : null;

            if (!template) {
                return this.notify(this.t.missing_template, 'error');
            }

            const copy = D.reId(template.content);
            this.mutate(() => entry.list.splice(entry.position, 1, copy));
            this.select(copy.id);
        },

        /* --------------------------------------------------------- save/publish */

        get status() {
            if (this.templateMode) {
                return this.saving ? this.t.saving : this.dirty ? this.t.unsaved : '';
            }

            if (this.saving) {
                return this.t.saving;
            }

            if (this.dirty) {
                return this.t.unsaved;
            }

            if (!this.publishedAt) {
                return this.t.draft;
            }

            return this.hasUnpublished ? this.t.unpublished_changes : this.t.published;
        },

        async save() {
            if (this.saving) {
                return false;
            }

            this.saving = true;
            const sent = this.snapshot();

            try {
                const result = await api.put(cfg.endpoints.content, { revision: this.revision, document: JSON.parse(sent) });

                this.revision = result.revision;
                this.hasUnpublished = result.has_unpublished_changes;
                this.publishedAt = result.published_at;
                this.errors = {};
                this.savedSnapshot = sent;
                this.dirty = this.snapshot() !== sent;
                this.notify(this.t.saved, 'success');

                return true;
            } catch (error) {
                if (error.status === 422 && error.payload?.errors) {
                    this.errors = error.payload.errors;
                    this.panel = 'structure';
                    const first = this.errorMap.keys().next().value;

                    if (first) {
                        this.select(first, true);
                    }

                    this.notify(this.t.validation_failed, 'error');
                } else {
                    this.fail(error);
                }

                return false;
            } finally {
                this.saving = false;
            }
        },

        async publish() {
            if (this.publishing || (this.dirty && !(await this.save()))) {
                return;
            }

            this.publishing = true;

            try {
                const result = await api.post(cfg.endpoints.publish);
                this.publishedAt = result.published_at;
                this.hasUnpublished = result.has_unpublished_changes;
                this.notify(this.t.publish_done, 'success');
            } catch (error) {
                this.fail(error);
            } finally {
                this.publishing = false;
            }
        },

        async unpublish() {
            try {
                const result = await api.post(cfg.endpoints.unpublish);
                this.publishedAt = result.published_at;
                this.hasUnpublished = result.has_unpublished_changes;
                this.notify(this.t.unpublish_done, 'success');
            } catch (error) {
                this.fail(error);
            }
        },

        /* --------------------------------------------------------------- misc */

        onKey(event, fromFrame = false) {
            const mod = event.metaKey || event.ctrlKey;
            const key = (event.key ?? '').toLowerCase();
            const target = fromFrame ? null : event.target;
            const typing = target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

            if (mod && key === 's') {
                event.preventDefault?.();
                this.templateMode ? this.saveTemplateContent() : this.save();
            } else if (mod && key === 'z' && !typing) {
                event.preventDefault?.();
                event.shiftKey ? this.redo() : this.undo();
            } else if (mod && key === 'y' && !typing) {
                event.preventDefault?.();
                this.redo();
            } else if ((key === 'delete' || key === 'backspace') && !typing && this.selectedId) {
                event.preventDefault?.();
                this.remove(this.selectedId);
            } else if (key === 'escape' && !typing) {
                this.media.open || this.templateDialog.open ? ((this.media.open = false), (this.templateDialog.open = false)) : this.selectParent();
            }
        },

        notify(message, tone = 'info') {
            clearTimeout(toastTimer);
            this.toast = { message, tone };
            toastTimer = setTimeout(() => (this.toast = null), tone === 'error' ? 6000 : 2500);
        },

        fail(error) {
            if (error.status === 409) {
                return this.notify(this.t.conflict, 'error');
            }

            if (error.status === 419 || error.status === 401) {
                return this.notify(this.t.session_expired, 'error');
            }

            this.notify(`${this.t.error}${error.status ? ` ${error.status}` : ''} : ${error.message}`, 'error');
        },
    };
}
