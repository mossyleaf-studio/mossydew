export function moveBy(items, index, offset) {
    const target = index + offset;
    if (target < 0 || target >= items.length) return null;
    const moved = [...items];
    moved.splice(target, 0, ...moved.splice(index, 1));
    return moved;
}
