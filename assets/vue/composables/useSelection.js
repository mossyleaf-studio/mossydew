import { computed, reactive } from 'vue';
import { rangeBetween } from '../tasks/selectionRange.js';

const selected = reactive(new Map());
const shown = new Map();
let anchor = null;

function toggle(task) {
    anchor = task.id;
    if (selected.has(task.id)) selected.delete(task.id);
    else selected.set(task.id, task);
}

function extend(task) {
    const order = [...document.querySelectorAll('[data-task]')].map((row) => row.dataset.task);
    const ids = rangeBetween(order, anchor, task.id);
    if (ids === null) {
        toggle(task);
        return;
    }
    ids.map((id) => shown.get(id)?.()).filter((each) => each && !each.done).forEach((each) => selected.set(each.id, each));
    window.getSelection()?.removeAllRanges();
}

export function useSelection() {
    return {
        tasks: computed(() => [...selected.values()]),
        count: computed(() => selected.size),
        has: (task) => selected.has(task.id),
        toggle,
        extend,
        show: (id, task) => shown.set(id, task),
        hide: (id) => shown.delete(id),
        clear: () => {
            selected.clear();
            anchor = null;
        },
    };
}
