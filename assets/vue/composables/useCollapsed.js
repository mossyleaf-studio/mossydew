import { reactive } from 'vue';

const KEY = 'mossydew.collapsed';

function stored() {
    try {
        const ids = JSON.parse(window.localStorage.getItem(KEY) ?? '[]');
        return Array.isArray(ids) ? ids : [];
    } catch {
        return [];
    }
}

const collapsed = reactive(new Set(stored()));

function save() {
    try {
        window.localStorage.setItem(KEY, JSON.stringify([...collapsed]));
    } catch {
        return;
    }
}

export function useCollapsed() {
    return {
        isCollapsed: (task) => collapsed.has(task.id),
        toggle(task, value = !collapsed.has(task.id)) {
            if (value) collapsed.add(task.id);
            else collapsed.delete(task.id);
            save();
        },
    };
}
