import { describe, expect, it } from 'vitest';
import { blocksCell, cellName, columnLetter, roomTints, wallsOf } from './board.js';

const CORNER_PLAN = [0, 0, 1, 0, 2, 1, 2, 2, 1];

describe('cellName', () => {
    it('writes the column letter then the row number', () => {
        expect(columnLetter(0)).toBe('A');
        expect(columnLetter(13)).toBe('N');
        expect(cellName(0, 6)).toBe('A1');
        expect(cellName(8, 6)).toBe('C2');
        expect(cellName(35, 6)).toBe('F6');
    });
});

describe('blocksCell', () => {
    it('lets seats and empty squares hold someone', () => {
        expect(blocksCell(null)).toBe(false);
        expect(['armchair', 'sofa', 'bed'].some(blocksCell)).toBe(false);
    });

    it('blocks every other piece of furniture', () => {
        expect(['plant', 'books', 'bath', 'toilet', 'tv', 'piano', 'lamp', 'stove', 'washer'].every(blocksCell)).toBe(true);
    });
});

describe('wallsOf', () => {
    it('puts a wall on the right and bottom sides that meet another room', () => {
        expect(wallsOf(CORNER_PLAN, 3, 0)).toEqual({ right: false, bottom: false });
        expect(wallsOf(CORNER_PLAN, 3, 1)).toEqual({ right: true, bottom: true });
        expect(wallsOf(CORNER_PLAN, 3, 3)).toEqual({ right: true, bottom: true });
    });

    it('never walls the edge of the plan', () => {
        expect(wallsOf(CORNER_PLAN, 3, 5)).toEqual({ right: false, bottom: false });
        expect(wallsOf(CORNER_PLAN, 3, 8)).toEqual({ right: false, bottom: false });
        expect(wallsOf(CORNER_PLAN, 3, 6)).toEqual({ right: false, bottom: false });
    });
});

describe('roomTints', () => {
    it('gives neighbouring rooms different tints', () => {
        expect(roomTints([0, 0, 1, 1], 2)).toEqual([0, 1]);
        expect(roomTints([0, 1, 2, 3, 0, 1, 2, 3, 0, 1, 2, 3, 0, 1, 2, 3], 4)).toEqual([0, 1, 0, 1]);
    });

    it('uses all three tints for three rooms that all touch', () => {
        expect(new Set(roomTints(CORNER_PLAN, 3)).size).toBe(3);
    });

    it('reuses the least used tint when four rooms all touch', () => {
        const rooms = [0, 0, 0, 1, 0, 3, 3, 1, 2, 3, 3, 1, 2, 2, 1, 1];
        expect(roomTints(rooms, 4)).toEqual([0, 1, 2, 0]);
    });
});
