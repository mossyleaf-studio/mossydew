export function createNotesSync({ save, delay = 800 }) {
    let timer = null;
    let pending = null;
    let running = null;

    async function drain() {
        while (pending !== null) {
            const snapshot = pending;
            if (!await save(snapshot)) return;
            if (pending === snapshot) pending = null;
        }
    }

    function flush() {
        clearTimeout(timer);
        running ??= drain().finally(() => { running = null; });

        return running;
    }

    return {
        get dirty() {
            return pending !== null;
        },
        edit(notes) {
            pending = notes;
            clearTimeout(timer);
            timer = setTimeout(flush, delay);
        },
        flush,
        reset() {
            clearTimeout(timer);
            pending = null;
        },
    };
}
