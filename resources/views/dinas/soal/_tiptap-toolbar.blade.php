{{-- Full Tiptap Toolbar (for Pertanyaan, Essay, Pembahasan) --}}
<div class="tiptap-toolbar" x-show="editor" x-cloak>
    {{-- Text format --}}
    <button type="button" @click="toggleBold()" :class="{ 'is-active': isActive('bold') }" title="Bold">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"/><path d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"/></svg>
    </button>
    <button type="button" @click="toggleItalic()" :class="{ 'is-active': isActive('italic') }" title="Italic">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
    </button>
    <button type="button" @click="toggleUnderline()" :class="{ 'is-active': isActive('underline') }" title="Underline">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3v7a6 6 0 006 6 6 6 0 006-6V3"/><line x1="4" y1="21" x2="20" y2="21"/></svg>
    </button>
    <button type="button" @click="toggleStrike()" :class="{ 'is-active': isActive('strike') }" title="Strikethrough">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="12" x2="20" y2="12"/><path d="M17.5 7.5c-.7-1.5-2.3-2.5-4.5-2.5-3 0-5 1.5-5 4 0 6 10 6 10 0 0-2.5-2-4-5-4"/></svg>
    </button>

    <div class="toolbar-sep"></div>

    {{-- Text Color --}}
    <div class="relative">
        <button type="button" @click="showColorPicker = !showColorPicker; showHighlightPicker = false; showTableMenu = false" title="Warna Teks">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M11 2L5.5 16h2.25l1.12-3h6.25l1.12 3h2.25L13 2h-2zm-1.38 9L12 4.67 14.38 11H9.62z"/><rect x="3" y="18" width="18" height="3" rx="1" fill="currentColor" opacity=".5"/></svg>
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

    {{-- Highlight --}}
    <div class="relative">
        <button type="button" @click="showHighlightPicker = !showHighlightPicker; showColorPicker = false; showTableMenu = false" title="Highlight">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M15.24 2.86l5.9 5.9-9.9 9.9-5.9-5.9 9.9-9.9zM3 18l3 3h6l-3-3H3z" opacity=".7"/></svg>
        </button>
        <div x-show="showHighlightPicker" @click.away="showHighlightPicker = false" x-transition
             class="absolute top-full left-0 mt-1 z-50 bg-white rounded-lg shadow-lg border border-gray-200 p-2" style="width: 170px">
            <div class="color-grid">
                <template x-for="c in ['#fef08a','#bbf7d0','#bfdbfe','#e9d5ff','#fecdd3','#fed7aa','#ccfbf1','#fce7f3','#dbeafe','#d9f99d','#fde68a','#e2e8f0']">
                    <button type="button" class="color-swatch" :style="`background:${c}`" @click="setHighlight(c)"></button>
                </template>
            </div>
            <button type="button" @click="unsetHighlight()" class="w-full text-xs text-gray-500 hover:text-gray-800 mt-1 py-1">Hapus highlight</button>
        </div>
    </div>

    <div class="toolbar-sep"></div>

    {{-- Heading --}}
    <button type="button" @click="setHeading(2)" :class="{ 'is-active': isActive('heading', {level:2}) }" title="Heading 2" class="!w-auto px-1.5 text-xs font-bold">
        H2
    </button>
    <button type="button" @click="setHeading(3)" :class="{ 'is-active': isActive('heading', {level:3}) }" title="Heading 3" class="!w-auto px-1.5 text-xs font-bold">
        H3
    </button>
    <button type="button" @click="setParagraph()" :class="{ 'is-active': isActive('paragraph') }" title="Paragraf" class="!w-auto px-1.5 text-xs">
        P
    </button>

    <div class="toolbar-sep"></div>

    {{-- Alignment --}}
    <button type="button" @click="setAlign('left')" :class="{ 'is-active': isActive({textAlign:'left'}) }" title="Rata Kiri">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>
    </button>
    <button type="button" @click="setAlign('center')" :class="{ 'is-active': isActive({textAlign:'center'}) }" title="Rata Tengah">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
    </button>
    <button type="button" @click="setAlign('right')" :class="{ 'is-active': isActive({textAlign:'right'}) }" title="Rata Kanan">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="6" y1="18" x2="21" y2="18"/></svg>
    </button>

    <div class="toolbar-sep"></div>

    {{-- List --}}
    <button type="button" @click="toggleBulletList()" :class="{ 'is-active': isActive('bulletList') }" title="Bullet List">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4.5" cy="6" r="1.5" fill="currentColor"/><circle cx="4.5" cy="12" r="1.5" fill="currentColor"/><circle cx="4.5" cy="18" r="1.5" fill="currentColor"/></svg>
    </button>
    <button type="button" @click="toggleOrderedList()" :class="{ 'is-active': isActive('orderedList') }" title="Ordered List">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><text x="3" y="8" font-size="7" fill="currentColor" stroke="none" font-weight="bold">1</text><text x="3" y="14" font-size="7" fill="currentColor" stroke="none" font-weight="bold">2</text><text x="3" y="20" font-size="7" fill="currentColor" stroke="none" font-weight="bold">3</text></svg>
    </button>
    <button type="button" @click="toggleBlockquote()" :class="{ 'is-active': isActive('blockquote') }" title="Blockquote">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M6 17h3l2-4V7H5v6h3l-2 4zm8 0h3l2-4V7h-6v6h3l-2 4z"/></svg>
    </button>

    <div class="toolbar-sep"></div>

    {{-- Table --}}
    <div class="relative">
        <button type="button" @click="showTableMenu = !showTableMenu; showColorPicker = false; showHighlightPicker = false" title="Tabel">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
        </button>
        <div x-show="showTableMenu" @click.away="showTableMenu = false" x-transition
             class="absolute top-full left-0 mt-1 z-50 bg-white rounded-lg shadow-lg border border-gray-200 py-1 w-40">
            <button type="button" @click="insertTable()" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-100">Sisipkan Tabel 3x3</button>
            <button type="button" @click="addRowAfter(); showTableMenu = false" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-100">Tambah Baris</button>
            <button type="button" @click="addColAfter(); showTableMenu = false" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-100">Tambah Kolom</button>
            <button type="button" @click="deleteRow(); showTableMenu = false" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-100 text-red-600">Hapus Baris</button>
            <button type="button" @click="deleteCol(); showTableMenu = false" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-100 text-red-600">Hapus Kolom</button>
            <button type="button" @click="deleteTable(); showTableMenu = false" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-100 text-red-600">Hapus Tabel</button>
        </div>
    </div>

    {{-- Image --}}
    <button type="button" @click="triggerImageUpload()" title="Upload Gambar">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
    </button>

    {{-- Math formula --}}
    <div class="relative">
        <button type="button" @click="showMathInput = !showMathInput; showColorPicker = false; showHighlightPicker = false; showTableMenu = false" title="Rumus Matematika">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M7.5 3h-3C3.67 3 3 3.67 3 4.5v3h2V5h2V3zm9 0v2h2v2.5h2v-3C20.5 3.67 19.83 3 19 3h-2.5zM3 16.5v3c0 .83.67 1.5 1.5 1.5h3v-2H5v-2.5H3zm17 0V19h-2.5v2H19c.83 0 1.5-.67 1.5-1.5v-3h-2zM12 6l-4 8h2.5l.7-1.5h3.6l.7 1.5H18L14 6h-2zm-.7 5L12 9.2l.7 1.8h-1.4z"/></svg>
        </button>
        <div x-show="showMathInput" @click.away="showMathInput = false" x-transition
             class="absolute top-full left-0 mt-1 z-50 bg-white rounded-lg shadow-lg border border-gray-200 py-2 w-52 no-mathjax">
            <button type="button" @click="insertInlineMath(); showMathInput = false" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-100 flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-5 h-5 bg-blue-50 rounded text-blue-600 font-mono text-[10px] font-bold shrink-0">f(x)</span>
                <span>Rumus Inline</span>
            </button>
            <button type="button" @click="insertBlockMath(); showMathInput = false" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-100 flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-5 h-5 bg-indigo-50 rounded text-indigo-600 font-mono text-[10px] font-bold shrink-0">F(x)</span>
                <span>Rumus Block (baris terpisah)</span>
            </button>
            <div class="border-t border-gray-100 mt-1 pt-1 px-3">
                <p class="text-[10px] text-gray-400 leading-tight">Klik rumus di editor untuk mengedit. Gunakan sintaks LaTeX.</p>
            </div>
        </div>
    </div>

    <div class="toolbar-sep"></div>

    {{-- Undo/Redo --}}
    <button type="button" @click="undo()" title="Undo">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10h10a5 5 0 015 5v0a5 5 0 01-5 5H8"/><polyline points="7 14 3 10 7 6"/></svg>
    </button>
    <button type="button" @click="redo()" title="Redo">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10H11a5 5 0 00-5 5v0a5 5 0 005 5h5"/><polyline points="17 14 21 10 17 6"/></svg>
    </button>
</div>
