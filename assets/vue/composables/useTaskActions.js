import { useI18n } from 'vue-i18n';
import { useApi } from './useApi.js';
import { useCelebration } from './useCelebration.js';
import { useSubtaskQuestion } from './useSubtaskQuestion.js';
import { useToast } from './useToast.js';

export function useTaskActions() {
    const api = useApi();
    const toast = useToast();
    const celebration = useCelebration();
    const question = useSubtaskQuestion();
    const { t } = useI18n();

    async function attempt(action) {
        try {
            return await action();
        } catch (error) {
            toast.error(error.message);
            return null;
        }
    }

    return {
        create: (payload) => attempt(() => api.post('/api/tasks', payload)),
        edit: (task, payload) => attempt(() => api.patch(`/api/tasks/${task.id}`, payload)),
        plan: (task, when, date = null) => attempt(() => api.post(`/api/tasks/${task.id}/plan`, { when, date })),
        async classify(task, quadrant) {
            const withSubtasks = await question.ask([task], quadrant);
            if (withSubtasks === null) return null;
            return attempt(() => api.post(`/api/tasks/${task.id}/classify`, { quadrant, withSubtasks }));
        },
        reorder: (quadrant, ids, withSubtasks = false) => attempt(() => api.put(`/api/matrix/${quadrant}/order`, { ids, withSubtasks })),
        addSubtask: (parent, title) => attempt(() => api.post(`/api/tasks/${parent.id}/subtasks`, { title })),
        nest: (task, parent) => attempt(() => api.post(`/api/tasks/${task.id}/parent`, { parentId: parent.id })),
        promote: (task) => attempt(() => api.post(`/api/tasks/${task.id}/parent`, { parentId: null })),
        moveOverdueToToday: () => attempt(() => api.post('/api/tasks/overdue/plan-today')),
        async complete(task) {
            task.done = true;
            const completion = await attempt(() => api.post(`/api/tasks/${task.id}/complete`));
            if (completion === null) {
                task.done = false;
                return null;
            }
            celebration.reward(completion);
            (completion.goalsReached ?? []).forEach((title) => toast.success(t('friends.goals.reachedToast', { title })));
            if (completion.cluesRevealed > 0) toast.info(t('mysteries.clueRevealed', completion.cluesRevealed));
            return completion;
        },
        async reopen(task) {
            task.done = false;
            const reopened = await attempt(() => api.post(`/api/tasks/${task.id}/reopen`));
            if (reopened === null) task.done = true;
            return reopened;
        },
        async remove(task) {
            try {
                await api.del(`/api/tasks/${task.id}`);
                toast.success(t('tasks.deleted', { title: task.title }));
            } catch (error) {
                toast.error(error.message);
            }
        },
    };
}
