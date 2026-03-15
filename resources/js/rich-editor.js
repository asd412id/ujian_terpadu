import {
    ClassicEditor,
    Essentials,
    Bold,
    Italic,
    Underline,
    Strikethrough,
    Superscript,
    Subscript,
    Heading,
    Paragraph,
    Alignment,
    List,
    BlockQuote,
    Table,
    TableToolbar,
    TableColumnResize,
    Image,
    ImageUpload,
    ImageResize,
    ImageStyle,
    ImageToolbar,
    ImageCaption,
    FontColor,
    FontBackgroundColor,
    FontSize,
    Highlight,
    Undo,
    Link,
    Indent,
    IndentBlock,
    PasteFromOffice,
    AutoImage,
    HorizontalLine,
    SpecialCharacters,
    SpecialCharactersMathematical,
    SpecialCharactersEssentials,
    Code,
    RemoveFormat,
    FindAndReplace,
    Plugin,
    FileRepository,
} from 'ckeditor5';

import 'ckeditor5/ckeditor5.css';

/**
 * Custom Upload Adapter for CKEditor 5.
 * Integrates with the existing Laravel upload endpoint.
 * POST field: 'image', expects JSON response: { url: string }
 */
class LaravelUploadAdapter {
    constructor(loader, uploadUrl) {
        this.loader = loader;
        this.uploadUrl = uploadUrl;
    }

    upload() {
        return this.loader.file.then(file => new Promise((resolve, reject) => {
            const data = new FormData();
            data.append('image', file);

            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!token) {
                reject('Sesi telah berakhir. Silakan refresh halaman.');
                return;
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', this.uploadUrl, true);
            xhr.setRequestHeader('X-CSRF-TOKEN', token);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.upload.addEventListener('progress', evt => {
                if (evt.lengthComputable) {
                    this.loader.uploadTotal = evt.total;
                    this.loader.uploaded = evt.loaded;
                }
            });

            xhr.addEventListener('load', () => {
                if (xhr.status === 419) {
                    reject('Sesi telah berakhir (CSRF expired). Silakan refresh halaman.');
                    return;
                }
                if (xhr.status === 413) {
                    reject('Ukuran gambar terlalu besar untuk server. Maksimal 5MB.');
                    return;
                }
                if (xhr.status < 200 || xhr.status >= 300) {
                    try {
                        const err = JSON.parse(xhr.responseText);
                        reject(err.message || 'Gagal upload gambar.');
                    } catch {
                        reject('Gagal upload gambar. Pastikan format jpeg/png/gif/webp dan ukuran < 5MB.');
                    }
                    return;
                }
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.url) {
                        resolve({ default: response.url });
                    } else {
                        reject('Server tidak mengembalikan URL gambar.');
                    }
                } catch {
                    reject('Respons server tidak valid.');
                }
            });

            xhr.addEventListener('error', () => reject('Gagal upload gambar. Periksa koneksi internet Anda.'));
            xhr.addEventListener('abort', () => reject('Upload gambar dibatalkan.'));
            xhr.send(data);
        }));
    }

    abort() {
        // Optional: implement XHR abort if stored
    }
}

/**
 * CKEditor Upload Adapter Plugin for Laravel backend.
 */
function LaravelUploadAdapterPlugin(editor) {
    editor.plugins.get(FileRepository).createUploadAdapter = (loader) => {
        const uploadUrl = editor.config.get('laravelUpload.uploadUrl');
        return new LaravelUploadAdapter(loader, uploadUrl);
    };
}

/**
 * Alpine.js component for CKEditor 5 rich text editor.
 *
 * Usage in Blade:
 *   <div x-data="richEditor({
 *       name: 'pertanyaan',
 *       content: '<p>existing content</p>',
 *       placeholder: 'Tuliskan pertanyaan...',
 *       uploadUrl: '/dinas/soal/upload-image',
 *       minimal: false
 *   })">
 *       <div x-ref="editorEl"></div>
 *       <input type="hidden" :name="name" x-ref="hiddenInput">
 *   </div>
 */
export function richEditor({
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

        init() {
            const fullPlugins = [
                Essentials, Bold, Italic, Underline, Strikethrough,
                Superscript, Subscript, Code,
                Heading, Paragraph, Alignment,
                List, BlockQuote, Indent, IndentBlock,
                Table, TableToolbar, TableColumnResize,
                Image, ImageUpload, ImageResize, ImageStyle, ImageToolbar, ImageCaption,
                FontColor, FontBackgroundColor, FontSize,
                Link, PasteFromOffice, AutoImage, HorizontalLine,
                SpecialCharacters, SpecialCharactersMathematical, SpecialCharactersEssentials,
                RemoveFormat, FindAndReplace,
                Undo,
            ];

            const miniPlugins = [
                Essentials, Bold, Italic, Underline,
                Superscript, Subscript,
                Paragraph,
                Image, ImageUpload, ImageResize, ImageToolbar,
                FontColor, FontSize,
                Link, PasteFromOffice, AutoImage,
                SpecialCharacters, SpecialCharactersMathematical, SpecialCharactersEssentials,
                RemoveFormat,
                Undo,
            ];

            const fullToolbar = [
                'undo', 'redo',
                '|',
                'heading',
                '|',
                'bold', 'italic', 'underline', 'strikethrough', 'code',
                'superscript', 'subscript',
                '|',
                'fontSize', 'fontColor', 'fontBackgroundColor',
                '|',
                'alignment',
                '|',
                'bulletedList', 'numberedList', 'outdent', 'indent',
                '|',
                'blockQuote', 'insertTable', 'horizontalLine',
                '|',
                'link', 'uploadImage', 'specialCharacters',
                '|',
                'removeFormat', 'findAndReplace',
            ];

            const miniToolbar = [
                'undo', 'redo',
                '|',
                'bold', 'italic', 'underline',
                'superscript', 'subscript',
                '|',
                'fontSize', 'fontColor',
                '|',
                'link', 'uploadImage', 'specialCharacters',
                '|',
                'removeFormat',
            ];

            const plugins = minimal ? miniPlugins : fullPlugins;
            const toolbar = minimal ? miniToolbar : fullToolbar;

            // Add upload adapter plugin if uploadUrl is provided
            if (uploadUrl) {
                plugins.push(LaravelUploadAdapterPlugin);
            }

            const editorConfig = {
                plugins,
                toolbar: {
                    items: toolbar,
                    shouldNotGroupWhenFull: !minimal,
                },
                placeholder,
                heading: {
                    options: [
                        { model: 'paragraph', title: 'Paragraf', class: 'ck-heading_paragraph' },
                        { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                        { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
                        { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' },
                    ],
                },
                table: {
                    contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'],
                },
                image: {
                    toolbar: ['imageStyle:inline', 'imageStyle:block', '|', 'toggleImageCaption', '|', 'imageResize:50', 'imageResize:75', 'imageResize:original'],
                    resizeUnit: '%',
                    resizeOptions: [
                        { name: 'imageResize:original', value: null, label: 'Ukuran asli' },
                        { name: 'imageResize:50', value: '50', label: '50%' },
                        { name: 'imageResize:75', value: '75', label: '75%' },
                    ],
                },
                fontSize: {
                    options: ['tiny', 'small', 'default', 'big', 'huge'],
                    supportAllValues: false,
                },
                fontColor: {
                    colors: [
                        { color: '#000000', label: 'Hitam' },
                        { color: '#374151', label: 'Abu Tua' },
                        { color: '#dc2626', label: 'Merah' },
                        { color: '#ea580c', label: 'Oranye' },
                        { color: '#ca8a04', label: 'Kuning Tua' },
                        { color: '#16a34a', label: 'Hijau' },
                        { color: '#2563eb', label: 'Biru' },
                        { color: '#7c3aed', label: 'Ungu' },
                        { color: '#db2777', label: 'Merah Muda' },
                        { color: '#0891b2', label: 'Cyan' },
                    ],
                    columns: 5,
                },
                fontBackgroundColor: {
                    colors: [
                        { color: '#fef08a', label: 'Kuning' },
                        { color: '#bbf7d0', label: 'Hijau Muda' },
                        { color: '#bfdbfe', label: 'Biru Muda' },
                        { color: '#e9d5ff', label: 'Ungu Muda' },
                        { color: '#fecdd3', label: 'Merah Muda' },
                        { color: '#fed7aa', label: 'Oranye Muda' },
                        { color: '#ccfbf1', label: 'Teal Muda' },
                        { color: '#fce7f3', label: 'Pink Muda' },
                    ],
                    columns: 4,
                },
                laravelUpload: {
                    uploadUrl,
                },
                licenseKey: 'GPL',
                language: 'id',
            };

            ClassicEditor
                .create(this.$refs.editorEl, editorConfig)
                .then(editor => {
                    this.editor = editor;

                    // Set initial content if available
                    if (this.htmlContent) {
                        editor.setData(this.htmlContent);
                    }

                    // Sync changes to hidden input
                    editor.model.document.on('change:data', () => {
                        this.htmlContent = editor.getData();
                        if (this.$refs.hiddenInput) {
                            this.$refs.hiddenInput.value = this.htmlContent;
                        }
                    });

                    // Set initial value to hidden input
                    this.$nextTick(() => {
                        if (this.$refs.hiddenInput) {
                            this.$refs.hiddenInput.value = this.htmlContent;
                        }
                    });
                })
                .catch(error => {
                    console.error('CKEditor initialization error:', error);
                });
        },

        destroy() {
            if (this.editor) {
                this.editor.destroy()
                    .catch(error => console.error('CKEditor destroy error:', error));
            }
        },
    };
}
