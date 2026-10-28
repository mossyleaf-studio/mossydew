<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';
import BaseDatePicker from '../ui/BaseDatePicker.vue';
import BaseModal from '../ui/BaseModal.vue';
import FormField from '../ui/FormField.vue';
import { ApiError, useApi } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { addDays, isoDay } from '../../tasks/days.js';

const props = defineProps({ friends: { type: Array, required: true } });
const open = defineModel('open', { type: Boolean, required: true });

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const saving = ref(false);
const errors = ref({});
const form = reactive({ title: '', target: '20', endsOn: null, friendIds: [] });

watch(open, (opened) => {
    if (!opened) return;
    Object.assign(form, { title: '', target: '20', endsOn: addDays(isoDay(new Date()), 14), friendIds: props.friends.map((friend) => friend.id) });
    errors.value = {};
});

function toggle(id) {
    form.friendIds = form.friendIds.includes(id) ? form.friendIds.filter((other) => other !== id) : [...form.friendIds, id];
}

async function save() {
    saving.value = true;
    try {
        await api.post('/api/goals', { ...form, target: Number.parseInt(form.target, 10) || 0 });
        toast.success(t('friends.goals.created', { title: form.title.trim() }));
        open.value = false;
    } catch (error) {
        if (error instanceof ApiError && error.violations.length) errors.value = error.fieldErrors;
        else toast.error(error.message);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BaseModal v-model:open="open" :title="t('friends.goals.new')">
        <form class="goal-dialog" @submit.prevent="save">
            <FormField :label="t('friends.goals.name')" :error="errors.title">
                <input v-model="form.title" type="text" maxlength="80" required>
            </FormField>
            <div class="goal-dialog__pair">
                <FormField :label="t('friends.goals.target')" :error="errors.target">
                    <input v-model="form.target" type="text" inputmode="numeric" maxlength="4" class="control goal-dialog__target" required>
                </FormField>
                <FormField :label="t('friends.goals.endsOn')" as="div" :error="errors.endsOn">
                    <BaseDatePicker v-model="form.endsOn" />
                </FormField>
            </div>
            <FormField :label="t('friends.goals.with')" as="div" :error="errors.friendIds">
                <div class="goal-dialog__friends">
                    <button v-for="friend in friends" :key="friend.id" type="button" class="goal-dialog__friend" :aria-pressed="form.friendIds.includes(friend.id)" @click="toggle(friend.id)">{{ friend.displayName }}</button>
                </div>
            </FormField>
            <div class="goal-dialog__actions">
                <BaseButton variant="ghost" @click="open = false">{{ t('common.cancel') }}</BaseButton>
                <BaseButton type="submit" :loading="saving">{{ t('friends.goals.create') }}</BaseButton>
            </div>
        </form>
    </BaseModal>
</template>

<style scoped>
.goal-dialog { display: flex; flex-direction: column; gap: var(--space-4); }
.goal-dialog__pair { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: var(--space-4); }
.goal-dialog__target { width: 6rem; font-variant-numeric: tabular-nums; }
.goal-dialog__friends { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.goal-dialog__friend { padding: var(--space-1) var(--space-3); border: var(--border-width) solid var(--color-border); border-radius: var(--radius-pill); background: var(--color-surface); color: var(--color-muted); cursor: pointer; }
.goal-dialog__friend[aria-pressed="true"] { border-color: var(--color-accent); background: var(--color-accent-soft); color: var(--color-ink); }
.goal-dialog__actions { display: flex; justify-content: flex-end; gap: var(--space-2); }
</style>
