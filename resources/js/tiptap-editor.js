import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import Underline from '@tiptap/extension-underline';
import { Table } from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import Placeholder from '@tiptap/extension-placeholder';
import { MathExtension } from '@aarkue/tiptap-math-extension';
import 'katex/dist/katex.min.css';

/**
 * Alpine.js component for Tiptap rich text editor.
 *
 * Usage in Blade:
 *   <div x-data="tiptapEditor({
 *       name: 'pertanyaan',
 *       content: '<p>existing content</p>',
 *       placeholder: 'Tuliskan pertanyaan...',
 *       uploadUrl: '/dinas/soal/upload-image',
 *       minimal: false
 *   })">
 *       <template x-ref="toolbarSlot"></template>
 *       <div x-ref="editorEl"></div>
 *       <input type="hidden" :name="name" x-ref="hiddenInput">
 *   </div>
 */
export function tiptapEditor({
    name = '',
    content = '',
    placeholder = 'Tulis di sini...',
    uploadUrl = '',
    minimal = false,
} = {}) {
    return {
        name,
        editor: null,
        htmlContent: content || '',
        showColorPicker: false,
        showHighlightPicker: false,
        showTableMenu: false,
        showMathInput: false,
        mathLatex: '',

        init() {
            const extensions = [
                StarterKit.configure({
                    heading: minimal ? false : { levels: [2, 3, 4] },
                    codeBlock: false,
                    code: false,
                }),
                Image.configure({
                    inline: true,
                    allowBase64: false,
                    HTMLAttributes: {
                        class: 'tiptap-image',
                    },
                }),
                TextAlign.configure({
                    types: ['heading', 'paragraph'],
                }),
                TextStyle,
                Color,
                Highlight.configure({ multicolor: true }),
                Underline,
                Placeholder.configure({
                    placeholder,
                    emptyEditorClass: 'is-editor-empty',
                }),
                MathExtension.configure({
                    delimiters: 'dollar',
                    evaluation: false,
                }),
            ];

            if (!minimal) {
                extensions.push(
                    Table.configure({ resizable: true }),
                    TableRow,
                    TableCell,
                    TableHeader,
                );
            }

            this.editor = new Editor({
                element: this.$refs.editorEl,
                extensions,
                content: this.htmlContent || '',
                editorProps: {
                    attributes: {
                        class: 'tiptap-content prose prose-sm max-w-none focus:outline-none',
                    },
                    handlePaste: (view, event) => {
                        const items = event.clipboardData?.items;
                        if (!items) return false;
                        for (const item of items) {
                            if (item.type.startsWith('image/')) {
                                event.preventDefault();
                                const file = item.getAsFile();
                                if (file) this.uploadAndInsertImage(file);
                                return true;
                            }
                        }
                        return false;
                    },
                    handleDrop: (view, event) => {
                        const files = event.dataTransfer?.files;
                        if (!files?.length) return false;
                        let handled = false;
                        for (const file of files) {
                            if (file.type.startsWith('image/')) {
                                this.uploadAndInsertImage(file);
                                handled = true;
                            }
                        }
                        if (handled) event.preventDefault();
                        return handled;
                    },
                },
                onUpdate: ({ editor }) => {
                    this.htmlContent = editor.getHTML();
                    if (this.$refs.hiddenInput) {
                        this.$refs.hiddenInput.value = this.htmlContent;
                    }
                },
            });

            // Set initial value to hidden input
            this.$nextTick(() => {
                if (this.$refs.hiddenInput) {
                    this.$refs.hiddenInput.value = this.htmlContent;
                }
            });
        },

        destroy() {
            if (this.editor) {
                this.editor.destroy();
            }
        },

        async uploadAndInsertImage(file) {
            if (!uploadUrl) {
                alert('Konfigurasi upload gambar tidak tersedia.');
                return;
            }

            const formData = new FormData();
            formData.append('image', file);

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                const res = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    alert(err.message || 'Gagal upload gambar. Pastikan ukuran < 5MB.');
                    return;
                }

                const data = await res.json();
                if (data.url) {
                    this.editor.chain().focus().setImage({ src: data.url }).run();
                }
            } catch (e) {
                console.error('Image upload failed:', e);
                alert('Gagal upload gambar. Periksa koneksi internet.');
            }
        },

        // Toolbar action helpers
        toggleBold() { this.editor.chain().focus().toggleBold().run(); },
        toggleItalic() { this.editor.chain().focus().toggleItalic().run(); },
        toggleUnderline() { this.editor.chain().focus().toggleUnderline().run(); },
        toggleStrike() { this.editor.chain().focus().toggleStrike().run(); },
        toggleBulletList() { this.editor.chain().focus().toggleBulletList().run(); },
        toggleOrderedList() { this.editor.chain().focus().toggleOrderedList().run(); },
        toggleBlockquote() { this.editor.chain().focus().toggleBlockquote().run(); },
        setAlign(align) { this.editor.chain().focus().setTextAlign(align).run(); },
        undo() { this.editor.chain().focus().undo().run(); },
        redo() { this.editor.chain().focus().redo().run(); },
        setHeading(level) { this.editor.chain().focus().toggleHeading({ level }).run(); },
        setParagraph() { this.editor.chain().focus().setParagraph().run(); },

        setColor(color) {
            this.editor.chain().focus().setColor(color).run();
            this.showColorPicker = false;
        },
        unsetColor() {
            this.editor.chain().focus().unsetColor().run();
            this.showColorPicker = false;
        },
        setHighlight(color) {
            this.editor.chain().focus().toggleHighlight({ color }).run();
            this.showHighlightPicker = false;
        },
        unsetHighlight() {
            this.editor.chain().focus().unsetHighlight().run();
            this.showHighlightPicker = false;
        },

        insertTable() {
            this.editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
            this.showTableMenu = false;
        },
        addRowAfter() { this.editor.chain().focus().addRowAfter().run(); },
        addColAfter() { this.editor.chain().focus().addColumnAfter().run(); },
        deleteRow() { this.editor.chain().focus().deleteRow().run(); },
        deleteCol() { this.editor.chain().focus().deleteColumn().run(); },
        deleteTable() { this.editor.chain().focus().deleteTable().run(); },

        triggerImageUpload() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.onchange = (e) => {
                const file = e.target.files[0];
                if (file) this.uploadAndInsertImage(file);
            };
            input.click();
        },

        insertInlineMath() {
            this.editor.chain().focus().insertContent('$x$').run();
        },
        insertBlockMath() {
            this.editor.chain().focus().insertContent('$$\nx^2 + y^2 = z^2\n$$').run();
        },

        // Check states for toolbar button active states
        isActive(type, attrs = {}) {
            return this.editor?.isActive(type, attrs) ?? false;
        },
    };
}
