import { describe, expect, it } from 'vitest';
import { clueMessage } from './clues.js';

const plan = { people: ['Aster', 'Basil', 'Clover'], kinds: ['kitchen', 'library'] };

describe('clueMessage', () => {
    it('names the person and points at the room phrase', () => {
        expect(clueMessage({ kind: 'in_room', person: 2, room: 1 }, plan)).toEqual({
            key: 'mysteries.sentence.in_room',
            params: { person: 'Clover' },
            phrases: { room: 'mysteries.rooms.library.in' },
        });
    });

    it('points at the seat phrase for a person on furniture', () => {
        expect(clueMessage({ kind: 'on_furniture', person: 0, furniture: 'armchair' }, plan)).toEqual({
            key: 'mysteries.sentence.on_furniture',
            params: { person: 'Aster' },
            phrases: { furniture: 'mysteries.furniture.armchair.on' },
        });
    });

    it('points at the beside phrase for a person next to furniture', () => {
        expect(clueMessage({ kind: 'beside_furniture', person: 1, furniture: 'plant' }, plan)).toEqual({
            key: 'mysteries.sentence.beside_furniture',
            params: { person: 'Basil' },
            phrases: { furniture: 'mysteries.furniture.plant.beside' },
        });
    });

    it('counts rows from one and names columns by letter', () => {
        expect(clueMessage({ kind: 'in_row', person: 0, number: 0 }, plan).params).toEqual({ person: 'Aster', row: 1 });
        expect(clueMessage({ kind: 'in_column', person: 0, number: 2 }, plan).params).toEqual({ person: 'Aster', column: 'C' });
    });

    it('names both people in comparisons', () => {
        for (const kind of ['same_room', 'north_of', 'west_of']) {
            expect(clueMessage({ kind, person: 0, other: 1 }, plan)).toEqual({
                key: `mysteries.sentence.${kind}`,
                params: { person: 'Aster', other: 'Basil' },
                phrases: {},
            });
        }
    });

    it('needs no person to say a room is empty', () => {
        expect(clueMessage({ kind: 'empty_room', room: 0 }, plan)).toEqual({
            key: 'mysteries.sentence.empty_room',
            params: {},
            phrases: { room: 'mysteries.rooms.kitchen.in' },
        });
    });

    it('picks the plural form from the head count of a room', () => {
        expect(clueMessage({ kind: 'room_holds', room: 1, number: 3 }, plan)).toEqual({
            key: 'mysteries.sentence.room_holds',
            params: {},
            phrases: { room: 'mysteries.rooms.library.in' },
            plural: 3,
        });
    });
});
