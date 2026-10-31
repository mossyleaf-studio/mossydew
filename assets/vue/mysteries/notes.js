export const emptyNotes = (size) => ({ placements: Array(size).fill(null), crosses: [] });

export const copyNotes = (notes) => ({ placements: [...notes.placements], crosses: [...notes.crosses] });

export function occupantOf(placements, cell) {
    const person = placements.indexOf(cell);

    return person === -1 ? null : person;
}

export function place(notes, person, cell) {
    return {
        placements: notes.placements.map((current, index) => {
            if (index === person) return cell;
            return current === cell ? null : current;
        }),
        crosses: notes.crosses.filter((crossed) => crossed !== cell),
    };
}

export function remove(notes, person) {
    return {
        placements: notes.placements.map((current, index) => (index === person ? null : current)),
        crosses: notes.crosses,
    };
}

export function toggleCross(notes, cell) {
    const crosses = notes.crosses.includes(cell)
        ? notes.crosses.filter((crossed) => crossed !== cell)
        : [...notes.crosses, cell].sort((first, second) => first - second);

    return { placements: notes.placements, crosses };
}

export const clearCrosses = (notes) => ({ placements: notes.placements, crosses: [] });

export function tap(notes, cell, person) {
    if (person !== null) return place(notes, person, cell);
    const occupant = occupantOf(notes.placements, cell);

    return occupant === null ? toggleCross(notes, cell) : remove(notes, occupant);
}

export const everyonePlaced = (notes) => notes.placements.every((cell) => cell !== null);
