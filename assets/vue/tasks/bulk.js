export function movePayload(task, projectId) {
    return {
        title: task.title,
        notes: task.notes,
        projectId,
        dueOn: task.dueOn,
        recurrence: task.recurrence ? { interval: task.recurrence.interval, unit: task.recurrence.unit } : null,
    };
}

export function subtasksFirst(tasks) {
    return [...tasks].sort((a, b) => Number(!a.parentId) - Number(!b.parentId));
}

export async function runEach(tasks, action) {
    let done = 0;
    const errors = [];
    for (const task of tasks) {
        try {
            await action(task);
            done += 1;
        } catch (error) {
            errors.push(error.message);
        }
    }
    return { done, failed: errors.length, error: errors[0] ?? null };
}
