import { cpSync, existsSync, mkdirSync } from 'fs';
import { resolve } from 'path';

/**
 * Vite plugin that copies MathJax runtime files from node_modules
 * into the build output directory. MathJax can't be bundled by Vite
 * because it dynamically loads sub-modules (fonts, extensions) relative
 * to its own script URL at runtime.
 *
 * Output: public/build/mathjax/ -> served at /build/mathjax/
 *
 * Only copies the files needed for tex-mml-chtml (TeX input + CHTML output):
 * - tex-mml-chtml.js  (combined bundle, ~1.2MB)
 * - output/chtml/      (renderer + font data + woff fonts)
 * - input/tex/         (autoloaded extensions)
 * - input/mml/         (MathML input)
 * - startup.js, core.js, loader.js (dynamic loading support)
 */
export function copyMathJax() {
    const src = resolve('node_modules/mathjax/es5');
    const dest = resolve('public/build/mathjax');

    function copyFiles() {
        if (!existsSync(src)) {
            console.warn('[mathjax] node_modules/mathjax not found — run npm install');
            return;
        }

        mkdirSync(dest, { recursive: true });

        // Combined bundle
        cpSync(resolve(src, 'tex-mml-chtml.js'), resolve(dest, 'tex-mml-chtml.js'));

        // Dynamic loaders (MathJax loads these at runtime)
        for (const f of ['startup.js', 'core.js', 'loader.js']) {
            if (existsSync(resolve(src, f))) {
                cpSync(resolve(src, f), resolve(dest, f));
            }
        }

        // CHTML output renderer + fonts
        cpSync(resolve(src, 'output'), resolve(dest, 'output'), { recursive: true });

        // TeX input extensions (autoloaded on demand)
        cpSync(resolve(src, 'input'), resolve(dest, 'input'), { recursive: true });

        // Adaptors (needed by CHTML for browser rendering)
        if (existsSync(resolve(src, 'adaptors'))) {
            cpSync(resolve(src, 'adaptors'), resolve(dest, 'adaptors'), { recursive: true });
        }

        console.log('[mathjax] Copied to public/build/mathjax/');
    }

    return {
        name: 'copy-mathjax',
        // Copy AFTER Vite writes output (closeBundle runs after emptyOutDir)
        closeBundle() {
            copyFiles();
        },
        // Copy when dev server starts
        configureServer() {
            copyFiles();
        },
    };
}
