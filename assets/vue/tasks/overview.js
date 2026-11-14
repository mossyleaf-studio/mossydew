import { sortTasks } from './compareTasks.js';
import { nestSubtasks } from './subtasks.js';

export const PEEK = 5;

const nullsLast = (a, b) => {
    if (a === b) return 0;
    if (a === null || a === undefined) return 1;
    if (b === null || b === undefined) return -1;
    return a < b ? -1 : 1;
};

const byDeadline = (a, b) => nullsLast(a.dueOn, b.dueOn) || nullsLast(a.createdAt, b.createdAt) || nullsLast(a.id, b.id);

function entry({ task, subtasks }) {
    const sorted = [...subtasks].sort(byDeadline);

    return { task, subtasks: sorted, nextDue: task.dueOn ?? sorted[0]?.dueOn ?? null };
}

export function overview(tasks, projects) {
    const byProject = new Map();
    nestSubtasks(sortTasks(tasks)).map(entry).forEach((each) => {
        const key = each.task.projectId ?? null;
        byProject.set(key, [...(byProject.get(key) ?? []), each]);
    });
    const known = new Map(projects.map((project) => [project.id, project]));
    const keys = [null, ...projects.map((project) => project.id), ...[...byProject.keys()].filter((key) => key !== null && !known.has(key))];
    const groups = keys.filter((key) => byProject.has(key)).map((key) => {
        const entries = byProject.get(key);

        return { id: key, project: known.get(key) ?? null, entries, tasks: entries.length, subtasks: entries.reduce((sum, each) => sum + each.subtasks.length, 0) };
    });

    return {
        groups,
        tasks: groups.reduce((sum, group) => sum + group.tasks, 0),
        subtasks: groups.reduce((sum, group) => sum + group.subtasks, 0),
    };
}

export function peek(subtasks, all) {
    return all ? subtasks : subtasks.slice(0, PEEK);
}

export function splitColumns(groups) {
    const weights = groups.map((group) => 1 + group.tasks);
    const total = weights.reduce((sum, weight) => sum + weight, 0);
    let left = 0;
    const gaps = weights.map((weight) => {
        left += weight;
        return Math.abs(total - 2 * left);
    });
    const best = gaps.indexOf(Math.min(...gaps)) + 1;

    return [groups.slice(0, best), groups.slice(best)];
}
