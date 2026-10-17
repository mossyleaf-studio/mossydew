import { reactive } from 'vue';
import { parentsMoving } from '../tasks/subtasks.js';

const state = reactive({ parents: [], resolve: null });

function answer(withSubtasks) {
    state.resolve?.(withSubtasks);
    state.parents = [];
    state.resolve = null;
}

function ask(tasks, quadrant) {
    const parents = parentsMoving(tasks, quadrant);
    if (parents.length === 0) return Promise.resolve(false);
    answer(null);
    return new Promise((resolve) => {
        state.parents = parents;
        state.resolve = resolve;
    });
}

export function useSubtaskQuestion() {
    return { state, ask, answer };
}
