import { describe, expect, it } from 'vitest';
import { moveBy } from './moveBy.js';

describe('move by', () => {
    const order = ['a', 'b', 'c'];

    it('moves an item one place up or down, leaving the list untouched', () => {
        expect(moveBy(order, 1, -1)).toEqual(['b', 'a', 'c']);
        expect(moveBy(order, 1, 1)).toEqual(['a', 'c', 'b']);
        expect(order).toEqual(['a', 'b', 'c']);
    });

    it('does not move past either end', () => {
        expect(moveBy(order, 0, -1)).toBeNull();
        expect(moveBy(order, 2, 1)).toBeNull();
    });
});
