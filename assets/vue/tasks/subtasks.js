const byCreation = (a, b) => (a.createdAt === b.createdAt ? a.id.localeCompare(b.id) : a.createdAt.localeCompare(b.createdAt));

export function hasOpenSubtasks(task) {
    return !task.done && task.subtasksDone < task.subtaskCount;
}

export function nestSubtasks(tasks) {
    const listed = new Set(tasks.map((task) => task.id));
    const isNested = (task) => task.parentId !== null && task.parentId !== undefined && listed.has(task.parentId);
    const children = new Map();
    tasks.filter(isNested).forEach((task) => {
        children.set(task.parentId, [...(children.get(task.parentId) ?? []), task]);
    });

    return tasks
        .filter((task) => !isNested(task))
        .map((task) => ({ task, subtasks: (children.get(task.id) ?? []).sort(byCreation) }));
}

export function canNest(dragged, target) {
    if (!dragged || !target || dragged.id === target.id) return false;
    if (dragged.parentId === target.id) return false;
    return !target.parentId && !dragged.subtaskCount && !dragged.recurrence;
}

export function parentsMoving(tasks, quadrant) {
    return tasks.filter((task) => task.quadrant !== quadrant && hasOpenSubtasks(task));
}
