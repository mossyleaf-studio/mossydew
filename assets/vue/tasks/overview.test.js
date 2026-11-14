import { describe, expect, it } from 'vitest';
import { PEEK, overview, peek, splitColumns } from './overview.js';

const task = (overrides) => ({
    id: overrides.id,
    title: overrides.id,
    projectId: null,
    parentId: null,
    done: false,
    quadrant: null,
    rank: 0,
    dueOn: null,
    plannedOn: null,
    subtaskCount: 0,
    subtasksDone: 0,
    createdAt: '2026-10-01T10:00:00+00:00',
    ...overrides,
});

const garden = { id: 'garden', name: 'Garden', color: 'moss' };
const attic = { id: 'attic', name: 'Attic', color: 'honey' };
const shed = { id: 'shed', name: 'Shed', color: 'rust' };

const ids = (list) => list.map((each) => each.id);
const groupIds = (view) => view.groups.map((group) => group.id);
const lines = (group) => group.entries.map((entry) => entry.task.id);

describe('overview groups', () => {
    it('puts the inbox first, then the projects in their order', () => {
        const view = overview([
            task({ id: 'rake', projectId: 'garden' }),
            task({ id: 'post' }),
            task({ id: 'boxes', projectId: 'attic' }),
        ], [attic, garden]);

        expect(groupIds(view)).toEqual([null, 'attic', 'garden']);
        expect(view.groups[0].project).toBeNull();
        expect(view.groups[1].project).toBe(attic);
    });

    it('drops the inbox and the projects without open tasks', () => {
        const view = overview([task({ id: 'rake', projectId: 'garden' })], [attic, garden, shed]);

        expect(groupIds(view)).toEqual(['garden']);
    });

    it('keeps tasks whose project is not listed, after the known projects', () => {
        const view = overview([
            task({ id: 'ghost', projectId: 'lost' }),
            task({ id: 'rake', projectId: 'garden' }),
        ], [garden]);

        expect(groupIds(view)).toEqual(['garden', 'lost']);
        expect(view.groups[1].project).toBeNull();
        expect(lines(view.groups[1])).toEqual(['ghost']);
    });

    it('lists only top level tasks, in list order', () => {
        const view = overview([
            task({ id: 'compost', projectId: 'garden', quadrant: 'eliminate' }),
            task({ id: 'water', projectId: 'garden', quadrant: 'do_first' }),
            task({ id: 'seed', projectId: 'garden', parentId: 'water', quadrant: 'do_first' }),
            task({ id: 'weed', projectId: 'garden' }),
        ], [garden]);

        expect(lines(view.groups[0])).toEqual(['water', 'compost', 'weed']);
    });

    it('keeps a subtask whose parent is not listed as a line of its own', () => {
        const view = overview([task({ id: 'seed', projectId: 'garden', parentId: 'gone' })], [garden]);

        expect(lines(view.groups[0])).toEqual(['seed']);
    });
});

describe('overview folders', () => {
    const parent = task({ id: 'video', projectId: 'garden', subtaskCount: 4 });
    const subtasks = [
        task({ id: 'cut', projectId: 'garden', parentId: 'video', createdAt: '2026-10-01T10:01:00+00:00' }),
        task({ id: 'shoot', projectId: 'garden', parentId: 'video', dueOn: '2026-11-20', createdAt: '2026-10-01T10:02:00+00:00' }),
        task({ id: 'script', projectId: 'garden', parentId: 'video', dueOn: '2026-11-16', createdAt: '2026-10-01T10:03:00+00:00' }),
        task({ id: 'post', projectId: 'garden', parentId: 'video', createdAt: '2026-10-01T10:00:00+00:00' }),
    ];

    it('folds the open subtasks in their task, by deadline (none last) then creation', () => {
        const [entry] = overview([parent, ...subtasks], [garden]).groups[0].entries;

        expect(ids(entry.subtasks)).toEqual(['script', 'shoot', 'post', 'cut']);
    });

    it('gives a folder the nearest deadline of its subtasks', () => {
        const [entry] = overview([parent, ...subtasks], [garden]).groups[0].entries;

        expect(entry.nextDue).toBe('2026-11-16');
    });

    it('lets the parent keep its own deadline', () => {
        const [entry] = overview([{ ...parent, dueOn: '2026-12-01' }, ...subtasks], [garden]).groups[0].entries;

        expect(entry.nextDue).toBe('2026-12-01');
    });

    it('has no deadline when nothing in the folder is due', () => {
        const [entry] = overview([parent, subtasks[0], subtasks[3]], [garden]).groups[0].entries;
        const [plain] = overview([task({ id: 'rake', projectId: 'garden' })], [garden]).groups[0].entries;

        expect(entry.nextDue).toBeNull();
        expect(plain.subtasks).toEqual([]);
        expect(plain.nextDue).toBeNull();
    });
});

describe('peek', () => {
    const many = Array.from({ length: 8 }, (_, index) => task({ id: `s${index}` }));

    it('shows the first five, then all of them', () => {
        expect(PEEK).toBe(5);
        expect(ids(peek(many, false))).toEqual(['s0', 's1', 's2', 's3', 's4']);
        expect(peek(many, true)).toHaveLength(8);
        expect(peek(many.slice(0, 3), false)).toHaveLength(3);
    });
});

describe('overview counts', () => {
    it('counts the tasks and the subtasks of each group and in all', () => {
        const view = overview([
            task({ id: 'post' }),
            task({ id: 'video', projectId: 'garden' }),
            task({ id: 'cut', projectId: 'garden', parentId: 'video' }),
            task({ id: 'shoot', projectId: 'garden', parentId: 'video' }),
            task({ id: 'rake', projectId: 'garden' }),
        ], [garden]);

        expect(view.groups.map((group) => [group.tasks, group.subtasks])).toEqual([[1, 0], [2, 2]]);
        expect([view.tasks, view.subtasks]).toEqual([3, 2]);
    });

    it('is empty without open tasks', () => {
        expect(overview([], [garden])).toEqual({ groups: [], tasks: 0, subtasks: 0 });
    });
});

describe('splitColumns', () => {
    const group = (id, count, subtasks = 0) => ({ id, tasks: count, subtasks });

    it('keeps the order and balances the rows', () => {
        const groups = [group('inbox', 6), group('a', 8), group('b', 9), group('c', 3), group('d', 10, 60), group('e', 1), group('f', 1), group('g', 2)];

        expect(splitColumns(groups).map(ids)).toEqual([['inbox', 'a', 'b'], ['c', 'd', 'e', 'f', 'g']]);
    });

    it('weighs folded lines only, so opening a folder never moves a group', () => {
        expect(splitColumns([group('a', 2, 40), group('b', 2)]).map(ids)).toEqual([['a'], ['b']]);
        expect(splitColumns([group('a', 2), group('b', 1), group('c', 1)]).map(ids)).toEqual([['a'], ['b', 'c']]);
    });

    it('leaves a single group alone on the left', () => {
        expect(splitColumns([group('a', 3)]).map(ids)).toEqual([['a'], []]);
        expect(splitColumns([]).map(ids)).toEqual([[], []]);
    });
});
