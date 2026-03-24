import { describe, expect, it } from 'vitest';

describe('vitest frontend setup', () => {
    it('provides browser-like storage and indexedDB mocks', () => {
        localStorage.setItem('foo', 'bar');
        sessionStorage.setItem('baz', 'qux');

        expect(localStorage.getItem('foo')).toBe('bar');
        expect(sessionStorage.getItem('baz')).toBe('qux');
        expect(indexedDB).toBeDefined();
    });
});
