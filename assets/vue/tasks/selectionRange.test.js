import { describe, expect, it } from 'vitest';
import { rangeBetween } from './selectionRange.js';

describe('selection range', () => {
    const order = ['a', 'b', 'c', 'd'];

    it('takes every task between the anchor and the clicked one, both included, in either direction', () => {
        expect(rangeBetween(order, 'b', 'd')).toEqual(['b', 'c', 'd']);
        expect(rangeBetween(order, 'd', 'b')).toEqual(['b', 'c', 'd']);
        expect(rangeBetween(order, 'c', 'c')).toEqual(['c']);
    });

    it('has no range without an anchor still on the page', () => {
        expect(rangeBetween(order, null, 'b')).toBeNull();
        expect(rangeBetween(order, 'z', 'b')).toBeNull();
    });
});
