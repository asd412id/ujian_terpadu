{{-- Panduan Penulisan Rumus Matematika --}}
<div x-data="{ showGuide: false }">
    <button type="button" @click="showGuide = !showGuide"
            class="flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors">
        <svg class="w-4 h-4" :class="{ 'rotate-90': showGuide }" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="transition: transform 0.2s">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
        </svg>
        Panduan Penulisan Rumus Matematika
    </button>

    <div x-show="showGuide" x-transition.duration.200ms
         class="mt-3 bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-5 space-y-5 text-sm">

        {{-- Intro --}}
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-gray-800">Rumus ditulis dengan format LaTeX langsung di editor.</p>
                <p class="text-gray-600 mt-1">Ketikkan rumus sebagai teks biasa. Sistem akan otomatis merender saat soal ditampilkan ke peserta.</p>
            </div>
        </div>

        {{-- Cara Penulisan --}}
        <div class="space-y-3">
            <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                <span class="w-5 h-5 bg-blue-600 text-white rounded text-xs flex items-center justify-center font-bold">1</span>
                Cara Penulisan Dasar
            </h4>
            <div class="bg-white rounded-lg border border-blue-100 overflow-hidden">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-blue-50">
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">Tujuan</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">Ketik di Editor</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">Hasil</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-2 px-3 text-gray-600">Rumus di dalam kalimat</td>
                            <td class="py-2 px-3"><code class="bg-gray-100 px-1.5 py-0.5 rounded text-red-600 font-mono">$x^2 + y^2 = r^2$</code></td>
                            <td class="py-2 px-3 mathjax-process">\(x^2 + y^2 = r^2\)</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-gray-600">Rumus di baris terpisah</td>
                            <td class="py-2 px-3"><code class="bg-gray-100 px-1.5 py-0.5 rounded text-red-600 font-mono">$$x = \frac{-b \pm \sqrt{b^2-4ac}}{2a}$$</code></td>
                            <td class="py-2 px-3 mathjax-process">\[x = \frac{-b \pm \sqrt{b^2-4ac}}{2a}\]</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-gray-600">Alternatif (inline)</td>
                            <td class="py-2 px-3"><code class="bg-gray-100 px-1.5 py-0.5 rounded text-red-600 font-mono">\(E = mc^2\)</code></td>
                            <td class="py-2 px-3 mathjax-process">\(E = mc^2\)</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-gray-600">Alternatif (display)</td>
                            <td class="py-2 px-3"><code class="bg-gray-100 px-1.5 py-0.5 rounded text-red-600 font-mono">\[a^2 + b^2 = c^2\]</code></td>
                            <td class="py-2 px-3 mathjax-process">\[a^2 + b^2 = c^2\]</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500 italic">
                <strong>Tip:</strong> Gunakan <code class="bg-gray-100 px-1 rounded">$...$</code> untuk rumus di tengah kalimat, dan <code class="bg-gray-100 px-1 rounded">$$...$$</code> untuk rumus yang berdiri sendiri (lebih besar, rata tengah).
            </p>
        </div>

        {{-- Operasi Dasar --}}
        <div class="space-y-3">
            <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                <span class="w-5 h-5 bg-blue-600 text-white rounded text-xs flex items-center justify-center font-bold">2</span>
                Operasi & Simbol Umum
            </h4>
            <div class="bg-white rounded-lg border border-blue-100 overflow-hidden">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-blue-50">
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">Operasi</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">Ketik</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">Hasil</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Pecahan</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\frac{a}{b}</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\frac{a}{b}\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Pangkat</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">x^{2n}</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(x^{2n}\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Indeks bawah</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">a_{n+1}</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(a_{n+1}\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Akar kuadrat</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\sqrt{x+1}</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\sqrt{x+1}\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Akar pangkat n</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\sqrt[3]{27}</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\sqrt[3]{27}\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Pangkat & indeks</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">x_i^{2}</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(x_i^{2}\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Perkalian titik</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">a \cdot b</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(a \cdot b\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Kali silang</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">a \times b</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(a \times b\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Bagi</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">a \div b</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(a \div b\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Plus-minus</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\pm</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\pm\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Tidak sama dengan</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\neq</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\neq\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Kurang dari / sama</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\leq, \geq</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\leq, \geq\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Tak hingga</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\infty</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\infty\)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Fungsi Matematika --}}
        <div class="space-y-3">
            <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                <span class="w-5 h-5 bg-blue-600 text-white rounded text-xs flex items-center justify-center font-bold">3</span>
                Fungsi, Limit, Integral & Sigma
            </h4>
            <div class="bg-white rounded-lg border border-blue-100 overflow-hidden">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-blue-50">
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">Fungsi</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">Ketik</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">Hasil</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Trigonometri</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\sin(\theta), \cos(\alpha), \tan(x)</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\sin(\theta), \cos(\alpha), \tan(x)\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Logaritma</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\log_{2}(8), \ln(e)</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\log_{2}(8), \ln(e)\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Limit</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\lim_{x \to 0} \frac{\sin x}{x}</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\lim_{x \to 0} \frac{\sin x}{x}\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Sigma (jumlah)</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\sum_{i=1}^{n} i^2</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\sum_{i=1}^{n} i^2\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Integral</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\int_{0}^{1} x^2 \, dx</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\int_{0}^{1} x^2 \, dx\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Produk</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\prod_{k=1}^{n} k</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\prod_{k=1}^{n} k\)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Matriks, Vektor, dll --}}
        <div class="space-y-3">
            <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                <span class="w-5 h-5 bg-blue-600 text-white rounded text-xs flex items-center justify-center font-bold">4</span>
                Matriks, Vektor & Kurung
            </h4>
            <div class="bg-white rounded-lg border border-blue-100 overflow-hidden">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-blue-50">
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">Jenis</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">Ketik</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">Hasil</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Matriks</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600 text-[10px]">\begin{pmatrix} a & b \\ c & d \end{pmatrix}</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\begin{pmatrix} a & b \\ c & d \end{pmatrix}\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Matriks siku</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600 text-[10px]">\begin{bmatrix} 1 & 2 \\ 3 & 4 \end{bmatrix}</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\begin{bmatrix} 1 & 2 \\ 3 & 4 \end{bmatrix}\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Vektor</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\vec{v}, \overrightarrow{AB}</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\vec{v}, \overrightarrow{AB}\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Kurung auto-size</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\left(\frac{a}{b}\right)</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\left(\frac{a}{b}\right)\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Kurung kurawal</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\left\{ x \mid x > 0 \right\}</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\left\{ x \mid x > 0 \right\}\)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-3 text-gray-600">Nilai mutlak</td>
                            <td class="py-1.5 px-3"><code class="font-mono text-red-600">\left| x \right|</code></td>
                            <td class="py-1.5 px-3 mathjax-process">\(\left| x \right|\)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Simbol Huruf Yunani --}}
        <div class="space-y-3">
            <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                <span class="w-5 h-5 bg-blue-600 text-white rounded text-xs flex items-center justify-center font-bold">5</span>
                Huruf Yunani & Simbol
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div class="bg-white rounded-lg border border-blue-100 p-3">
                    <p class="font-medium text-gray-700 mb-2 text-xs">Huruf Kecil</p>
                    <div class="grid grid-cols-3 gap-1 text-xs">
                        <span class="mathjax-process"><code class="text-red-600">\alpha</code> \(\alpha\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\beta</code> \(\beta\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\gamma</code> \(\gamma\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\delta</code> \(\delta\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\theta</code> \(\theta\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\lambda</code> \(\lambda\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\mu</code> \(\mu\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\pi</code> \(\pi\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\sigma</code> \(\sigma\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\phi</code> \(\phi\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\omega</code> \(\omega\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\epsilon</code> \(\epsilon\)</span>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-blue-100 p-3">
                    <p class="font-medium text-gray-700 mb-2 text-xs">Huruf Besar</p>
                    <div class="grid grid-cols-3 gap-1 text-xs">
                        <span class="mathjax-process"><code class="text-red-600">\Alpha</code> \(A\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\Delta</code> \(\Delta\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\Gamma</code> \(\Gamma\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\Theta</code> \(\Theta\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\Lambda</code> \(\Lambda\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\Sigma</code> \(\Sigma\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\Pi</code> \(\Pi\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\Phi</code> \(\Phi\)</span>
                        <span class="mathjax-process"><code class="text-red-600">\Omega</code> \(\Omega\)</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Fungsi Bersyarat (Piecewise) --}}
        <div class="space-y-3">
            <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                <span class="w-5 h-5 bg-blue-600 text-white rounded text-xs flex items-center justify-center font-bold">6</span>
                Fungsi Bersyarat & Sistem Persamaan
            </h4>
            <div class="bg-white rounded-lg border border-blue-100 p-3 space-y-3">
                <div>
                    <p class="text-xs font-medium text-gray-700 mb-1">Fungsi bersyarat (piecewise):</p>
                    <code class="block bg-gray-50 rounded p-2 text-[11px] text-red-600 font-mono whitespace-pre">$$f(x) = \begin{cases} x^2 & \text{jika } x \geq 0 \\ -x & \text{jika } x < 0 \end{cases}$$</code>
                    <div class="mt-1.5 mathjax-process text-center">\[f(x) = \begin{cases} x^2 & \text{jika } x \geq 0 \\ -x & \text{jika } x < 0 \end{cases}\]</div>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-700 mb-1">Sistem persamaan:</p>
                    <code class="block bg-gray-50 rounded p-2 text-[11px] text-red-600 font-mono whitespace-pre">$$\begin{cases} 2x + y = 5 \\ x - y = 1 \end{cases}$$</code>
                    <div class="mt-1.5 mathjax-process text-center">\[\begin{cases} 2x + y = 5 \\ x - y = 1 \end{cases}\]</div>
                </div>
            </div>
        </div>

        {{-- Contoh Soal Lengkap --}}
        <div class="space-y-3">
            <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                <span class="w-5 h-5 bg-green-600 text-white rounded text-xs flex items-center justify-center font-bold">&#10003;</span>
                Contoh Penulisan Soal
            </h4>
            <div class="bg-white rounded-lg border border-green-200 p-3 space-y-3">
                <div>
                    <p class="font-medium text-gray-700 text-xs mb-1">Contoh 1 — Rumus inline:</p>
                    <code class="block bg-gray-50 rounded p-2 text-xs font-mono text-gray-700">Tentukan nilai $x$ jika $2x + 3 = 7$</code>
                    <div class="mt-1 text-sm text-gray-800 mathjax-process border-l-2 border-green-300 pl-3">Tentukan nilai \(x\) jika \(2x + 3 = 7\)</div>
                </div>
                <div>
                    <p class="font-medium text-gray-700 text-xs mb-1">Contoh 2 — Rumus display:</p>
                    <code class="block bg-gray-50 rounded p-2 text-xs font-mono text-gray-700">Hitunglah integral berikut: $$\int_{0}^{\pi} \sin(x) \, dx$$</code>
                    <div class="mt-1 text-sm text-gray-800 mathjax-process border-l-2 border-green-300 pl-3">Hitunglah integral berikut: \[\int_{0}^{\pi} \sin(x) \, dx\]</div>
                </div>
                <div>
                    <p class="font-medium text-gray-700 text-xs mb-1">Contoh 3 — Campuran teks dan rumus:</p>
                    <code class="block bg-gray-50 rounded p-2 text-xs font-mono text-gray-700">Diketahui $f(x) = x^2 - 4x + 3$. Tentukan nilai minimum dari $f(x)$ dan titik baliknya.</code>
                    <div class="mt-1 text-sm text-gray-800 mathjax-process border-l-2 border-green-300 pl-3">Diketahui \(f(x) = x^2 - 4x + 3\). Tentukan nilai minimum dari \(f(x)\) dan titik baliknya.</div>
                </div>
            </div>
        </div>

        {{-- Tips --}}
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 space-y-1.5">
            <p class="font-semibold text-amber-800 text-xs flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Tips Penting
            </p>
            <ul class="text-xs text-amber-700 space-y-1 list-disc list-inside">
                <li>Gunakan kurung kurawal <code class="bg-amber-100 px-1 rounded">{}</code> untuk mengelompokkan lebih dari 1 karakter, contoh: <code class="bg-amber-100 px-1 rounded">x^{12}</code> bukan <code class="bg-amber-100 px-1 rounded line-through">x^12</code></li>
                <li>Spasi di LaTeX diabaikan. Untuk spasi eksplisit gunakan: <code class="bg-amber-100 px-1 rounded">\,</code> (kecil), <code class="bg-amber-100 px-1 rounded">\;</code> (sedang), <code class="bg-amber-100 px-1 rounded">\quad</code> (besar)</li>
                <li>Untuk teks biasa di dalam rumus, gunakan <code class="bg-amber-100 px-1 rounded">\text{kata}</code>, contoh: <code class="bg-amber-100 px-1 rounded">$x \text{ cm}$</code></li>
                <li>Rumus akan tampil sebagai teks mentah di editor — ini normal. Peserta akan melihat rumus ter-render saat ujian.</li>
                <li>Gunakan tombol <strong>Karakter Spesial</strong> (&#937;) di toolbar untuk memasukkan simbol tanpa LaTeX.</li>
                <li>Untuk simbol Kimia gunakan subscript: H<sub>2</sub>O bisa ditulis langsung dengan tombol subscript, atau <code class="bg-amber-100 px-1 rounded">$\text{H}_2\text{O}$</code></li>
            </ul>
        </div>
    </div>
</div>
