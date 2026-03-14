{{-- Mini Tiptap Toolbar (for Opsi PG, Pernyataan B/S) --}}
<div class="tiptap-toolbar" x-show="editor" x-cloak>
    <button type="button" @click="toggleBold()" :class="{ 'is-active': isActive('bold') }" title="Bold">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"/><path d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"/></svg>
    </button>
    <button type="button" @click="toggleItalic()" :class="{ 'is-active': isActive('italic') }" title="Italic">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
    </button>
    <button type="button" @click="toggleUnderline()" :class="{ 'is-active': isActive('underline') }" title="Underline">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3v7a6 6 0 006 6 6 6 0 006-6V3"/><line x1="4" y1="21" x2="20" y2="21"/></svg>
    </button>

    <div class="toolbar-sep"></div>

    {{-- Text Color (mini) --}}
    <div class="relative">
        <button type="button" @click="showColorPicker = !showColorPicker" title="Warna Teks">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M11 2L5.5 16h2.25l1.12-3h6.25l1.12 3h2.25L13 2h-2zm-1.38 9L12 4.67 14.38 11H9.62z"/><rect x="3" y="18" width="18" height="3" rx="1" fill="currentColor" opacity=".5"/></svg>
        </button>
        <div x-show="showColorPicker" @click.away="showColorPicker = false" x-transition
             class="absolute top-full left-0 mt-1 z-50 bg-white rounded-lg shadow-lg border border-gray-200 p-2" style="width: 170px">
            <div class="color-grid">
                <template x-for="c in ['#000000','#374151','#dc2626','#ea580c','#ca8a04','#16a34a','#2563eb','#7c3aed','#db2777','#0891b2','#059669','#4f46e5']">
                    <button type="button" class="color-swatch" :style="`background:${c}`" @click="setColor(c)"></button>
                </template>
            </div>
            <button type="button" @click="unsetColor()" class="w-full text-xs text-gray-500 hover:text-gray-800 mt-1 py-1">Hapus warna</button>
        </div>
    </div>

    <div class="toolbar-sep"></div>

    {{-- Image --}}
    <button type="button" @click="triggerImageUpload()" title="Upload Gambar">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
    </button>

    {{-- Math formula --}}
    <button type="button" @click="insertInlineMath()" title="Rumus Matematika (inline)">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M7.5 3h-3C3.67 3 3 3.67 3 4.5v3h2V5h2V3zm9 0v2h2v2.5h2v-3C20.5 3.67 19.83 3 19 3h-2.5zM3 16.5v3c0 .83.67 1.5 1.5 1.5h3v-2H5v-2.5H3zm17 0V19h-2.5v2H19c.83 0 1.5-.67 1.5-1.5v-3h-2zM12 6l-4 8h2.5l.7-1.5h3.6l.7 1.5H18L14 6h-2zm-.7 5L12 9.2l.7 1.8h-1.4z"/></svg>
    </button>

    <div class="toolbar-sep"></div>

    {{-- Undo/Redo --}}
    <button type="button" @click="undo()" title="Undo">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10h10a5 5 0 015 5v0a5 5 0 01-5 5H8"/><polyline points="7 14 3 10 7 6"/></svg>
    </button>
    <button type="button" @click="redo()" title="Redo">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10H11a5 5 0 00-5 5v0a5 5 0 005 5h5"/><polyline points="17 14 21 10 17 6"/></svg>
    </button>
</div>
