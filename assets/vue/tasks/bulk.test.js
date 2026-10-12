import { describe, expect, it } from 'vitest';
import { movePayload, runEach, subtasksFirst } from './bulk.js';

describe('bulk actions', () => {
    it('moves a task with the whole edit payload, so nothing else is cleared', () => {
        const task = { title: 'Mix', notes: 'Bus', projectId: 'a', dueOn: '2026-10-20', recurrence: { interval: 2, unit: 'week' } };

        expect(movePayload(task, 'b')).toEqual({ title: 'Mix', notes: 'Bus', projectId: 'b', dueOn: '2026-10-20', recurrence: { interval: 2, unit: 'week' } });
        expect(movePayload({ ...task, recurrence: null }, null).recurrence).toBeNull();
    });

    it('completes subtasks before their parents', () => {
        const order = subtasksFirst([{ id: 'parent', parentId: null }, { id: 'loose' }, { id: 'child', parentId: 'parent' }]).map((task) => task.id);

        expect(order).toEqual(['child', 'parent', 'loose']);
    });

    it('runs every task in turn and reports the refused ones', async () => {
        const seen = [];
        const report = await runEach([{ id: 1 }, { id: 2 }, { id: 3 }], async (task) => {
            seen.push(task.id);
            if (task.id === 2) throw new Error('Refused');
        });

        expect(seen).toEqual([1, 2, 3]);
        expect(report).toEqual({ done: 2, failed: 1, error: 'Refused' });
    });
});
