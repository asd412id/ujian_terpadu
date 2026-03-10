{{--
    Flash Notification Toast — Floating kanan atas, global, auto-dismiss 4 detik
    Gunakan: session('success'), session('error'), session('warning'), session('info')
--}}
@if(session('success') || session('error') || session('warning') || session('info') || $errors->any())
<div
    x-data="{
        show: true,
        type: '{{ session('success') ? 'success' : (session('error') || ($errors->any() && !session('success')) ? 'error' : (session('warning') ? 'warning' : 'info')) }}',
        init() {
            setTimeout(() => { this.show = false }, 5000)
        }
    }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-x-4"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 translate-x-4"
    class="fixed top-5 right-5 z-[99999] w-full max-w-sm shadow-xl rounded-xl border pointer-events-auto"
    :class="{
        'bg-green-50 border-green-200': type === 'success',
        'bg-red-50 border-red-200': type === 'error',
        'bg-amber-50 border-amber-200': type === 'warning',
        'bg-blue-50 border-blue-200': type === 'info',
    }"
>
    <div class="flex items-start gap-3 px-4 py-3.5">
        {{-- Icon --}}
        <div class="shrink-0 mt-0.5">
            @if(session('success'))
            <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            @elseif(session('warning'))
            <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            @elseif(session('info'))
            <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            @else
            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            @endif
        </div>

        {{-- Message --}}
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium leading-snug"
               :class="{
                   'text-green-800': type === 'success',
                   'text-red-800': type === 'error',
                   'text-amber-800': type === 'warning',
                   'text-blue-800': type === 'info',
               }">
                @if(session('success'))
                    {{ session('success') }}
                @elseif(session('error'))
                    {{ session('error') }}
                @elseif(session('warning'))
                    {{ session('warning') }}
                @elseif(session('info'))
                    {{ session('info') }}
                @elseif($errors->any())
                    {{ $errors->first() }}
                @endif
            </p>
        </div>

        {{-- Close button --}}
        <button @click="show = false"
                class="shrink-0 ml-1 rounded-md p-0.5 transition"
                :class="{
                    'text-green-400 hover:text-green-600 hover:bg-green-100': type === 'success',
                    'text-red-400 hover:text-red-600 hover:bg-red-100': type === 'error',
                    'text-amber-400 hover:text-amber-600 hover:bg-amber-100': type === 'warning',
                    'text-blue-400 hover:text-blue-600 hover:bg-blue-100': type === 'info',
                }">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Progress bar auto-dismiss --}}
    <div class="h-0.5 rounded-b-xl overflow-hidden"
         :class="{
             'bg-green-100': type === 'success',
             'bg-red-100': type === 'error',
             'bg-amber-100': type === 'warning',
             'bg-blue-100': type === 'info',
         }">
        <div class="h-full animate-[shrink_5s_linear_forwards]"
             :class="{
                 'bg-green-400': type === 'success',
                 'bg-red-400': type === 'error',
                 'bg-amber-400': type === 'warning',
                 'bg-blue-400': type === 'info',
             }">
        </div>
    </div>
</div>
@endif
