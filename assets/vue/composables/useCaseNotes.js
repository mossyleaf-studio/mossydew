import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useApi } from './useApi.js';
import { useToast } from './useToast.js';
import { clearCrosses, copyNotes, tap } from '../mysteries/notes.js';
import { createNotesSync } from '../mysteries/notesSync.js';

export function useCaseNotes(investigation) {
    const api = useApi();
    const toast = useToast();
    const notes = ref(null);
    const sync = createNotesSync({
        save: ({ id, notes: snapshot }) => toast.attempt(() => api.put(`/api/mystery/investigations/${id}/notes`, snapshot)),
    });

    watch(investigation, (next, previous) => {
        const open = next !== null && !next.solved;
        const sameCase = open && next.id === previous?.id;
        if (!sameCase) sync.reset();
        if (!open) notes.value = null;
        else if (!sameCase || !sync.dirty) notes.value = copyNotes(next.notes);
    }, { immediate: true });

    function change(next) {
        notes.value = next;
        sync.edit({ id: investigation.value.id, notes: next });
    }

    const flushWhenHidden = () => {
        if (document.visibilityState === 'hidden') sync.flush();
    };

    onMounted(() => document.addEventListener('visibilitychange', flushWhenHidden));
    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', flushWhenHidden);
        sync.flush();
    });

    return {
        notes,
        mark: (cell, person) => change(tap(notes.value, cell, person)),
        clearCrosses: () => change(clearCrosses(notes.value)),
        settle: sync.flush,
    };
}
