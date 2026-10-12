import { computed, reactive } from 'vue';

const selected = reactive(new Map());

export function useSelection() {
    return {
        tasks: computed(() => [...selected.values()]),
        count: computed(() => selected.size),
        has: (task) => selected.has(task.id),
        toggle: (task) => (selected.has(task.id) ? selected.delete(task.id) : selected.set(task.id, task)),
        clear: () => selected.clear(),
    };
}
