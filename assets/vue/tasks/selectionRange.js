export function rangeBetween(order, from, to) {
    const start = order.indexOf(from);
    const end = order.indexOf(to);
    if (start === -1 || end === -1) return null;
    return order.slice(Math.min(start, end), Math.max(start, end) + 1);
}
