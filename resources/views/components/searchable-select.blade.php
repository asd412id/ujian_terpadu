@props([
    'options' => [],
    'value' => '',
    'placeholder' => 'Pilih...',
    'name' => '',
    'required' => false,
    'class' => '',
])

@php
    $inputName = $attributes->whereStartsWith('wire:model')->first() ? '' : $name;
    $wireModel = $attributes->whereStartsWith('wire:model')->first();
    $selectedValue = old($name, $value);
    $optionsJson = $options instanceof \Illuminate\Support\Collection ? $options->toArray() : (array) $options;
@endphp

<div
    x-data="{
        open: false,
        search: '',
        value: @js($selectedValue),
        options: @js($optionsJson),
        get filtered() {
            if (!this.search) return this.options;
            const s = this.search.toLowerCase();
            return this.options.filter(o => o.text.toLowerCase().includes(s));
        },
        get selectedText() {
            const found = this.options.find(o => String(o.id) === String(this.value));
            return found ? found.text : '';
        },
        select(id) {
            this.value = id;
            this.search = '';
            this.open = false;
            this.$nextTick(() => {
                this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            });
        },
        clear() {
            this.value = '';
            this.search = '';
            this.$nextTick(() => {
                this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
    }"
    x-on:click.outside="open = false; search = '';"
    x-on:keydown.escape.window="open = false; search = '';"
    class="relative {{ $class }}"
>
    <input type="hidden"
           x-ref="hiddenInput"
           {{ $inputName ? "name={$inputName}" : '' }}
           x-model="value"
           {{ $required ? 'required' : '' }}
           {{ $attributes->whereStartsWith('wire:model') }}>

    <button type="button"
            x-on:click="open = !open; $nextTick(() => { if(open) $refs.searchInput.focus(); })"
            class="w-full flex items-center justify-between border border-gray-300 rounded-xl px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-left cursor-pointer transition-colors hover:border-gray-400"
            :class="{ 'ring-2 ring-blue-500 border-blue-500': open }">
        <span x-show="value" x-text="selectedText" class="text-gray-900 truncate"></span>
        <span x-show="!value" class="text-gray-400">{{ $placeholder }}</span>
        <div class="flex items-center gap-1 flex-shrink-0 ml-2">
            <span x-show="value" x-on:click.stop="clear()" class="text-gray-400 hover:text-red-500 p-0.5 rounded-full hover:bg-gray-100 transition-colors cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </span>
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </button>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-1"
         class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden"
         x-cloak>
        <div class="p-2 border-b border-gray-100">
            <input x-ref="searchInput"
                   x-model="search"
                   type="text"
                   placeholder="Ketik untuk mencari..."
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   x-on:keydown.enter.prevent="if(filtered.length === 1) select(filtered[0].id)">
        </div>
        <ul class="max-h-60 overflow-y-auto py-1">
            <template x-for="opt in filtered" :key="opt.id">
                <li x-on:click="select(opt.id)"
                    class="px-4 py-2.5 text-sm cursor-pointer transition-colors"
                    :class="String(value) === String(opt.id) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50'">
                    <span x-text="opt.text"></span>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-4 py-3 text-sm text-gray-400 text-center">
                Tidak ditemukan
            </li>
        </ul>
    </div>
</div>
