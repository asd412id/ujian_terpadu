import 'fake-indexeddb/auto';
import { afterEach, vi } from 'vitest';

if (!globalThis.ResizeObserver) {
    globalThis.ResizeObserver = class {
        observe() {}
        unobserve() {}
        disconnect() {}
    };
}

if (!globalThis.MutationObserver) {
    globalThis.MutationObserver = class {
        observe() {}
        disconnect() {}
        takeRecords() { return []; }
    };
}

afterEach(() => {
    vi.restoreAllMocks();
    localStorage.clear();
    sessionStorage.clear();
});
