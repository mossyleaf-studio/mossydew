import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createNotesSync } from './notesSync.js';

const deferred = () => {
    let resolve;
    const promise = new Promise((done) => { resolve = done; });

    return { promise, resolve };
};

describe('createNotesSync', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('saves only the latest edit once the delay has passed', async () => {
        const save = vi.fn().mockResolvedValue(true);
        const sync = createNotesSync({ save });
        sync.edit('first');
        await vi.advanceTimersByTimeAsync(500);
        sync.edit('second');
        await vi.advanceTimersByTimeAsync(799);
        expect(save).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(1);
        expect(save).toHaveBeenCalledTimes(1);
        expect(save).toHaveBeenCalledWith('second');
    });

    it('stays dirty until the save has gone through', async () => {
        const request = deferred();
        const sync = createNotesSync({ save: () => request.promise });
        expect(sync.dirty).toBe(false);
        sync.edit('notes');
        expect(sync.dirty).toBe(true);
        await vi.advanceTimersByTimeAsync(800);
        expect(sync.dirty).toBe(true);
        request.resolve(true);
        await vi.advanceTimersByTimeAsync(0);
        expect(sync.dirty).toBe(false);
    });

    it('saves what was typed while a save was in flight, once that save ends', async () => {
        const request = deferred();
        const save = vi.fn().mockReturnValueOnce(request.promise).mockResolvedValue(true);
        const sync = createNotesSync({ save });
        sync.edit('a');
        await vi.advanceTimersByTimeAsync(800);
        sync.edit('b');
        await vi.advanceTimersByTimeAsync(800);
        expect(save).toHaveBeenCalledTimes(1);
        request.resolve(true);
        await vi.advanceTimersByTimeAsync(0);
        expect(save).toHaveBeenCalledTimes(2);
        expect(save).toHaveBeenLastCalledWith('b');
        expect(sync.dirty).toBe(false);
    });

    it('stays dirty after a failed save and tries again on the next edit', async () => {
        const save = vi.fn().mockResolvedValueOnce(false).mockResolvedValue(true);
        const sync = createNotesSync({ save });
        sync.edit('a');
        await vi.advanceTimersByTimeAsync(800);
        expect(sync.dirty).toBe(true);
        sync.edit('b');
        await vi.advanceTimersByTimeAsync(800);
        expect(save).toHaveBeenLastCalledWith('b');
        expect(sync.dirty).toBe(false);
    });

    it('flushes at once and resolves when everything is saved', async () => {
        const save = vi.fn().mockResolvedValue(true);
        const sync = createNotesSync({ save });
        sync.edit('a');
        await sync.flush();
        expect(save).toHaveBeenCalledWith('a');
        expect(sync.dirty).toBe(false);
        await vi.advanceTimersByTimeAsync(800);
        expect(save).toHaveBeenCalledTimes(1);
    });

    it('forgets pending edits on reset', async () => {
        const save = vi.fn().mockResolvedValue(true);
        const sync = createNotesSync({ save });
        sync.edit('a');
        sync.reset();
        await vi.advanceTimersByTimeAsync(800);
        expect(save).not.toHaveBeenCalled();
        expect(sync.dirty).toBe(false);
    });
});
