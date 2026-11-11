import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

/**
 * Rich text field. The Tiptap instance lives in a closure: putting it in
 * Alpine's reactive data breaks ProseMirror.
 */
export default function richField(field) {
    let editor = null;

    const html = () => (editor && !editor.isEmpty ? editor.getHTML() : '');

    return {
        active: {},

        init() {
            editor = new Editor({
                element: this.$refs.area,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3, 4] },
                        codeBlock: false,
                        horizontalRule: false,
                        link: { openOnClick: false, autolink: true, protocols: ['mailto', 'tel'] },
                    }),
                ],
                content: this.value(field) || '',
                onUpdate: () => this.setValue(field, html()),
                onTransaction: () => this.syncActive(),
            });

            // External changes (locale switch, undo/redo) => reload content without emitting.
            this.$watch('locale', () => this.reload());
            this.$watch('version', () => {
                if ((this.value(field) || '') !== html()) {
                    this.reload();
                }
            });
        },

        destroy() {
            editor?.destroy();
            editor = null;
        },

        reload() {
            editor?.commands.setContent(this.value(field) || '', { emitUpdate: false });
        },

        run(command) {
            if (!editor) {
                return;
            }

            const chain = editor.chain().focus();
            const commands = {
                bold: () => chain.toggleBold(),
                italic: () => chain.toggleItalic(),
                h2: () => chain.toggleHeading({ level: 2 }),
                h3: () => chain.toggleHeading({ level: 3 }),
                bulletList: () => chain.toggleBulletList(),
                orderedList: () => chain.toggleOrderedList(),
                blockquote: () => chain.toggleBlockquote(),
            };

            commands[command]?.().run();
        },

        link() {
            if (!editor) {
                return;
            }

            const url = window.prompt(this.t.link_prompt, editor.getAttributes('link').href ?? '');

            if (url === null) {
                return;
            }

            const chain = editor.chain().focus().extendMarkRange('link');
            (url.trim() === '' ? chain.unsetLink() : chain.setLink({ href: url.trim() })).run();
        },

        syncActive() {
            if (!editor) {
                return;
            }

            this.active = {
                bold: editor.isActive('bold'),
                italic: editor.isActive('italic'),
                h2: editor.isActive('heading', { level: 2 }),
                h3: editor.isActive('heading', { level: 3 }),
                bulletList: editor.isActive('bulletList'),
                orderedList: editor.isActive('orderedList'),
                blockquote: editor.isActive('blockquote'),
                link: editor.isActive('link'),
            };
        },
    };
}
