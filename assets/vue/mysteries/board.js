const FIRST_LETTER = 65;
const TINT_LEVELS = 3;

export const SEATS = ['armchair', 'sofa', 'bed'];

export const blocksCell = (piece) => piece !== null && !SEATS.includes(piece);

export const columnLetter = (column) => String.fromCharCode(FIRST_LETTER + column);

export const cellName = (cell, size) => `${columnLetter(cell % size)}${Math.floor(cell / size) + 1}`;

export function wallsOf(rooms, size, cell) {
    return {
        right: cell % size < size - 1 && rooms[cell + 1] !== rooms[cell],
        bottom: cell + size < rooms.length && rooms[cell + size] !== rooms[cell],
    };
}

export function roomTints(rooms, size) {
    const touching = Array.from({ length: Math.max(...rooms) + 1 }, () => new Set());
    const touch = (first, second) => {
        touching[first].add(second);
        touching[second].add(first);
    };
    rooms.forEach((room, cell) => {
        const { right, bottom } = wallsOf(rooms, size, cell);
        if (right) touch(room, rooms[cell + 1]);
        if (bottom) touch(room, rooms[cell + size]);
    });

    const tints = [];
    touching.forEach((neighbours, room) => {
        const used = [...neighbours].filter((neighbour) => neighbour < room).map((neighbour) => tints[neighbour]);
        const counts = Array.from({ length: TINT_LEVELS }, (_, tint) => used.filter((candidate) => candidate === tint).length);
        tints[room] = counts.indexOf(Math.min(...counts));
    });

    return tints;
}
