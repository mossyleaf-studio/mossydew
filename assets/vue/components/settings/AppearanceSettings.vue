<script setup>
import { computed, ref } from 'vue';
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import { Leaf as LucideLeaf } from '@lucide/vue';
import { PhLeaf } from '@phosphor-icons/vue';
import { useToast } from '../../composables/useToast.js';
import { DESIGNS, THEMES, applyDesign, applyTheme, presetOf, themeNamed, useTheme } from '../../composables/useTheme.js';
import { ICON_SETS, iconSet } from '../../icons.js';

const { t } = useI18n();
const toast = useToast();
const { saved, savedDesign, choose, chooseDesign, chooseIcons } = useTheme();

const kept = ref({ ...saved });
const preset = computed(() => presetOf(kept.value));
const design = ref(savedDesign);
const samples = { lucide: LucideLeaf, phosphor: PhLeaf };

async function pick(name) {
    const next = themeNamed(name);
    if (!next || preset.value === name) return;
    const previous = { ...kept.value };
    kept.value = { background: next.background, accent: next.accent };
    if (!await toast.attempt(() => choose(next), t('settings.appearance.saved'))) {
        kept.value = previous;
        applyTheme(previous);
    }
}

async function pickDesign(next) {
    const previous = design.value;
    design.value = next;
    if (!await toast.attempt(() => chooseDesign(next), t('settings.appearance.saved'))) {
        design.value = previous;
        applyDesign(previous);
    }
}

async function pickIcons(next) {
    const previous = iconSet.value;
    if (!await toast.attempt(() => chooseIcons(next), t('settings.appearance.saved'))) iconSet.value = previous;
}
</script>

<template>
    <div class="appearance">
        <RadioGroupRoot :model-value="preset" class="appearance__options" :aria-label="t('settings.appearance.themesLabel')" @update:model-value="pick">
            <RadioGroupItem v-for="option in THEMES" :key="option.name" :value="option.name" class="appearance__option">
                <span class="appearance__sample" :style="{ background: option.background }" aria-hidden="true">
                    <span class="appearance__sample-accent" :style="{ background: option.accent }" />
                </span>
                <span class="appearance__name">{{ t(`settings.appearance.themes.${option.name}`) }}</span>
            </RadioGroupItem>
        </RadioGroupRoot>
        <p class="appearance__label">{{ t('settings.appearance.designLabel') }}</p>
        <RadioGroupRoot :model-value="design" class="appearance__options" :aria-label="t('settings.appearance.designLabel')" @update:model-value="pickDesign">
            <RadioGroupItem v-for="option in DESIGNS" :key="option" :value="option" class="appearance__option">
                <span :class="['appearance__sample', 'appearance__glyph', `appearance__glyph--${option}`]" aria-hidden="true">Aa</span>
                <span class="appearance__name">{{ t(`settings.appearance.designs.${option}`) }}</span>
            </RadioGroupItem>
        </RadioGroupRoot>
        <p class="appearance__label">{{ t('settings.appearance.iconsLabel') }}</p>
        <RadioGroupRoot :model-value="iconSet" class="appearance__options" :aria-label="t('settings.appearance.iconsLabel')" @update:model-value="pickIcons">
            <RadioGroupItem v-for="option in ICON_SETS" :key="option" :value="option" class="appearance__option">
                <span class="appearance__sample appearance__glyph" aria-hidden="true"><component :is="samples[option]" size="1.5rem" weight="duotone" /></span>
                <span class="appearance__name">{{ t(`settings.appearance.icons.${option}`) }}</span>
            </RadioGroupItem>
        </RadioGroupRoot>
    </div>
</template>

<style scoped>
.appearance { display: flex; flex-direction: column; gap: var(--space-3); }
.appearance__options { display: grid; grid-template-columns: repeat(auto-fill, minmax(7.5rem, 1fr)); gap: var(--space-3); }
.appearance__label { margin-top: var(--space-2); color: var(--color-ink); font-size: var(--font-size-md); font-weight: 600; }

.appearance__option {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
    padding: var(--space-2);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius);
    background: var(--color-surface);
    color: inherit;
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition: border-color var(--transition), background var(--transition);
}

.appearance__option:hover { border-color: var(--color-border-strong); }
.appearance__option[data-state='checked'] { border-color: var(--color-accent); background: var(--color-accent-soft); }

.appearance__sample { display: flex; align-items: flex-end; height: 3rem; padding: var(--space-2); border: var(--border-width) solid var(--color-border); border-radius: var(--radius-inner); }
.appearance__sample-accent { width: 40%; height: 0.5rem; border-radius: var(--radius-sm); }
.appearance__glyph { align-items: center; justify-content: center; background: var(--color-bg); color: var(--color-accent-strong); font-size: 1.5rem; line-height: 1; }
.appearance__glyph--classic { font-family: "Patua One", Rockwell, Georgia, serif; }
.appearance__glyph--cozy { font-family: "Fredoka Variable", system-ui, sans-serif; font-weight: 600; }
.appearance__name { color: var(--color-ink); font-size: var(--font-size-md); font-weight: 500; }
</style>
