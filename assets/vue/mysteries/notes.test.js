import { describe, expect, it } from 'vitest';
import { clearCrosses, copyNotes, emptyNotes, everyonePlaced, occupantOf, place, remove, tap, toggleCross } from './notes.js';

const notes = (placements, crosses = []) => ({ placements, crosses });

describe('emptyNotes', () => {
    it('starts with nobody placed and nothing crossed', () => {
        expect(emptyNotes(3)).toEqual({ placements: [null, null, null], crosses: [] });
    });
});

describe('place', () => {
    it('puts a person on a square', () => {
        expect(place(emptyNotes(3), 1, 7).placements).toEqual([null, 7, null]);
    });

    it('moves a person who was already placed', () => {
        expect(place(notes([4, null, null]), 0, 9).placements).toEqual([9, null, null]);
    });

    it('sends back whoever stood on the square', () => {
        expect(place(notes([4, 9, null]), 2, 4).placements).toEqual([null, 9, 4]);
    });

    it('wipes a cross from the square', () => {
        expect(place(notes([null, null], [3, 5]), 0, 3).crosses).toEqual([5]);
    });

    it('leaves the previous notes untouched', () => {
        const before = notes([4, null], [1]);
        place(before, 1, 4);
        expect(before).toEqual(notes([4, null], [1]));
    });
});

describe('remove', () => {
    it('takes a person off the plan and keeps the crosses', () => {
        expect(remove(notes([4, 9], [1]), 0)).toEqual(notes([null, 9], [1]));
    });
});

describe('toggleCross', () => {
    it('crosses a square, keeping the crosses sorted', () => {
        expect(toggleCross(notes([null], [8, 2]), 5).crosses).toEqual([2, 5, 8]);
    });

    it('uncrosses a crossed square', () => {
        expect(toggleCross(notes([null], [2, 5]), 5).crosses).toEqual([2]);
    });
});

describe('clearCrosses', () => {
    it('removes every cross and keeps the people', () => {
        expect(clearCrosses(notes([4], [1, 2]))).toEqual(notes([4]));
    });
});

describe('tap', () => {
    it('places the picked person', () => {
        expect(tap(emptyNotes(2), 6, 1).placements).toEqual([null, 6]);
    });

    it('crosses an empty square when nobody is picked', () => {
        expect(tap(emptyNotes(2), 6, null).crosses).toEqual([6]);
    });

    it('takes back the person standing on the square when nobody is picked', () => {
        expect(tap(notes([6, null]), 6, null).placements).toEqual([null, null]);
    });
});

describe('occupantOf', () => {
    it('finds who stands on a square', () => {
        expect(occupantOf([4, 9], 9)).toBe(1);
        expect(occupantOf([4, 9], 5)).toBeNull();
    });
});

describe('everyonePlaced', () => {
    it('is true only once nobody is left off the plan', () => {
        expect(everyonePlaced(notes([4, null]))).toBe(false);
        expect(everyonePlaced(notes([4, 9]))).toBe(true);
    });
});

describe('copyNotes', () => {
    it('copies the arrays so edits never reach the payload', () => {
        const payload = notes([4, null], [1]);
        const copy = copyNotes(payload);
        expect(copy).toEqual(payload);
        expect(copy.placements).not.toBe(payload.placements);
        expect(copy.crosses).not.toBe(payload.crosses);
    });
});
