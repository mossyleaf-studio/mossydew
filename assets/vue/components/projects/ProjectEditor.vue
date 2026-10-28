<script setup>
import { reactive, ref, watch } from 'vue';
import { Check } from '../../icons.js';
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';
import BaseModal from '../ui/BaseModal.vue';
import FormField from '../ui/FormField.vue';
import { ApiError } from '../../composables/useApi.js';
import { useProjects } from '../../composables/useProjects.js';
import { useToast } from '../../composables/useToast.js';

const COLORS = ['moss', 'lichen', 'fjord', 'heather', 'blossom', 'berry', 'rust', 'honey'];

const props = defineProps({
    project: { type: Object, default: null },
});
const emit = defineEmits(['saved']);
const open = defineModel('open', { type: Boolean, required: true });

const { t } = useI18n();
const toast = useToast();
const projects = useProjects();
const form = reactive({ name: '', color: 'moss' });
const errors = ref({});
const saving = ref(false);

watch(open, (value) => {
    if (!value) return;
    Object.assign(form, { name: props.project?.name ?? '', color: props.project?.color ?? COLORS[Math.floor(Math.random() * COLORS.length)] });
    errors.value = {};
}, { immediate: true });

async function save() {
    saving.value = true;
    try {
        const saved = props.project ? await projects.edit(props.project.id, { ...form }) : await projects.create({ ...form });
        open.value = false;
        emit('saved', saved);
    } catch (error) {
        if (error instanceof ApiError && error.violations.length) errors.value = error.fieldErrors;
        else errors.value = { name: error.message };
    } finally {
        saving.value = false;
    }
    if (errors.value.form) toast.error(errors.value.form);
}
</script>

<template>
    <BaseModal v-model:open="open" :title="project ? t('projects.editor.editTitle') : t('projects.editor.newTitle')">
        <form class="project-editor" @submit.prevent="save">
            <FormField :label="t('projects.editor.name')" :error="errors.name">
                <input v-model="form.name" type="text" maxlength="60" required>
            </FormField>
            <FormField :label="t('projects.editor.color')" as="div">
                <RadioGroupRoot v-model="form.color" class="project-editor__colors" orientation="horizontal" :aria-label="t('projects.editor.color')">
                    <RadioGroupItem
                        v-for="color in COLORS"
                        :key="color"
                        :value="color"
                        class="project-editor__swatch"
                        :style="{ '--swatch': `var(--project-${color})` }"
                        :aria-label="t(`projects.colors.${color}`)"
                    >
                        <Check v-if="form.color === color" size="1rem" :stroke-width="3" aria-hidden="true" />
                    </RadioGroupItem>
                </RadioGroupRoot>
            </FormField>
            <div class="actions-row">
                <BaseButton variant="ghost" @click="open = false">{{ t('common.cancel') }}</BaseButton>
                <BaseButton type="submit" :loading="saving">{{ project ? t('common.save') : t('projects.editor.create') }}</BaseButton>
            </div>
        </form>
    </BaseModal>
</template>

<style scoped>
.project-editor { display: flex; flex-direction: column; gap: var(--space-4); }
.project-editor__colors { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.project-editor__swatch { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border: none; border-radius: 50%; background: var(--swatch); color: #ffffff; cursor: pointer; transition: box-shadow var(--transition); }
.project-editor__swatch:hover { box-shadow: 0 0 0 0.1875rem var(--color-border-strong); }
.project-editor__swatch[data-state="checked"] { box-shadow: 0 0 0 0.125rem var(--color-surface), 0 0 0 0.25rem var(--swatch); }
</style>
