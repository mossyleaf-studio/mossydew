import { computed, ref } from 'vue';
import { useApi } from './useApi.js';
import { useToast } from './useToast.js';

const projects = ref([]);

export function useProjects() {
    const api = useApi();
    const toast = useToast();

    async function reorder(ids) {
        const byId = new Map(projects.value.map((project) => [project.id, project]));
        projects.value = [...ids.map((id) => byId.get(id)), ...projects.value.filter((project) => !ids.includes(project.id))];
        try {
            await api.put('/api/projects/order', { ids });
        } catch (error) {
            toast.error(error.message);
            await api.load('/api/projects', projects).catch(() => {});
        }
    }

    return {
        projects,
        byId: computed(() => new Map(projects.value.map((project) => [project.id, project]))),
        load: () => api.load('/api/projects', projects),
        create: (payload) => api.post('/api/projects', payload),
        edit: (id, payload) => api.patch(`/api/projects/${id}`, payload),
        remove: (id) => api.del(`/api/projects/${id}`),
        reorder,
    };
}
