import { describe, expect, it } from 'vitest';
import { canNest, hasOpenSubtasks, nestSubtasks, parentsMoving } from './subtasks.js';

const task = (overrides) => ({
    id: overrides.id,
    parentId: null,
    done: false,
    subtaskCount: 0,
    subtasksDone: 0,
    createdAt: '2026-10-01T10:00:00+00:00',
    ...overrides,
});

const shape = (nested) => nested.map(({ task: parent, subtasks }) => [parent.id, subtasks.map((subtask) => subtask.id)]);

describe('nestSubtasks', () => {
    it('puts subtasks under their parent when both are listed, in the order they were added', () => {
        const tasks = [
            task({ id: 'render', parentId: 'drawing', createdAt: '2026-10-01T10:03:00+00:00' }),
            task({ id: 'drawing' }),
            task({ id: 'vet' }),
            task({ id: 'sketch', parentId: 'drawing', createdAt: '2026-10-01T10:01:00+00:00' }),
        ];

        expect(shape(nestSubtasks(tasks))).toEqual([['drawing', ['sketch', 'render']], ['vet', []]]);
    });

    it('keeps a subtask at the top level when its parent is not listed', () => {
        const tasks = [task({ id: 'sketch', parentId: 'drawing' }), task({ id: 'vet' })];

        expect(shape(nestSubtasks(tasks))).toEqual([['sketch', []], ['vet', []]]);
    });

    it('keeps the order of the top level', () => {
        const tasks = [task({ id: 'b' }), task({ id: 'a' })];

        expect(shape(nestSubtasks(tasks))).toEqual([['b', []], ['a', []]]);
    });

    it('breaks creation ties by id', () => {
        const tasks = [task({ id: 'drawing' }), task({ id: 'b', parentId: 'drawing' }), task({ id: 'a', parentId: 'drawing' })];

        expect(shape(nestSubtasks(tasks))).toEqual([['drawing', ['a', 'b']]]);
    });
});

describe('hasOpenSubtasks', () => {
    it('is true for an open task with subtasks left to do', () => {
        expect(hasOpenSubtasks(task({ id: 'drawing', subtaskCount: 3, subtasksDone: 2 }))).toBe(true);
        expect(hasOpenSubtasks(task({ id: 'drawing', subtaskCount: 3, subtasksDone: 3 }))).toBe(false);
        expect(hasOpenSubtasks(task({ id: 'drawing', done: true, subtaskCount: 3, subtasksDone: 3 }))).toBe(false);
        expect(hasOpenSubtasks(task({ id: 'vet' }))).toBe(false);
    });
});

describe('canNest', () => {
    it('lets a plain task become a subtask of a top level task', () => {
        expect(canNest(task({ id: 'sketch' }), task({ id: 'drawing' }))).toBe(true);
        expect(canNest(task({ id: 'sketch', parentId: 'owl' }), task({ id: 'drawing' }))).toBe(true);
    });

    it('refuses what the server would refuse', () => {
        expect(canNest(task({ id: 'drawing' }), task({ id: 'drawing' }))).toBe(false);
        expect(canNest(task({ id: 'sketch', parentId: 'drawing' }), task({ id: 'drawing' }))).toBe(false);
        expect(canNest(task({ id: 'pencils' }), task({ id: 'sketch', parentId: 'drawing' }))).toBe(false);
        expect(canNest(task({ id: 'drawing', subtaskCount: 2 }), task({ id: 'sketchbook' }))).toBe(false);
        expect(canNest(task({ id: 'ferns', recurrence: { interval: 1, unit: 'week' } }), task({ id: 'garden' }))).toBe(false);
        expect(canNest(null, task({ id: 'garden' }))).toBe(false);
    });

    it('asks about subtasks only for parents with open subtasks that change quadrant', () => {
        const parent = task({ id: 'fox', quadrant: 'schedule', subtaskCount: 2, subtasksDone: 1 });
        const finished = task({ id: 'ink', quadrant: 'schedule', subtaskCount: 1, subtasksDone: 1 });
        const plain = task({ id: 'rake', quadrant: null });

        expect(parentsMoving([parent, finished, plain], 'do_first')).toEqual([parent]);
        expect(parentsMoving([parent], 'schedule')).toEqual([]);
        expect(parentsMoving([parent], null)).toEqual([parent]);
    });
});
