<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { putJson } from '../../utils/fetchJson';

const props = defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const selectId = `ai-model-select-${Math.random().toString(36).slice(2, 9)}`;
const page = usePage();

const models = computed(() => page.props.aiModels ?? page.props.aiChat?.models ?? null);

const selected = ref(models.value?.selected || '');
const saving = ref(false);
const error = ref('');

watch(models, (value) => {
    if (!saving.value) {
        selected.value = value?.selected || '';
    }
}, { deep: true });

const options = computed(() => models.value?.options ?? []);

const defaultLabel = computed(() => models.value?.default_label || 'серверная');

const selectClass = computed(() => (
    props.compact
        ? 'h-8 w-full truncate rounded-lg border border-f5-border bg-f5-surface px-2 text-xs font-medium text-f5-text outline-none transition hover:border-f5-green/50 focus:border-f5-green/60 disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-white/85'
        : 'h-9 w-full rounded-xl border border-f5-border bg-f5-surface px-3 text-sm text-f5-text outline-none transition hover:border-f5-green/50 focus:border-f5-green/60 disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-white'
));

async function onChange() {
    const url = models.value?.update_url;

    if (!url || saving.value) {
        return;
    }

    const previous = models.value?.selected || '';
    const next = selected.value || null;

    saving.value = true;
    error.value = '';

    try {
        const { response, data } = await putJson(url, { ai_model: next });

        if (!response.ok || !data?.success) {
            selected.value = previous;
            error.value = data?.message || data?.errors?.ai_model?.[0] || 'Не удалось сохранить модель';
            return;
        }

        if (page.props.auth?.user) {
            page.props.auth.user.ai_model = data.ai_model ?? null;
        }

        if (page.props.aiModels) {
            page.props.aiModels.selected = data.ai_model ?? null;
            page.props.aiModels.selected_label = data.ai_model_label ?? null;
        }

        if (page.props.aiChat?.models) {
            page.props.aiChat.models.selected = data.ai_model ?? null;
            page.props.aiChat.models.selected_label = data.ai_model_label ?? null;
        }
    } catch (exception) {
        selected.value = previous;
        error.value = exception?.message || 'Не удалось сохранить модель';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="min-w-0 w-full">
        <select
            v-if="models"
            :id="selectId"
            v-model="selected"
            :class="selectClass"
            :disabled="disabled || saving"
            :title="saving ? 'Сохранение…' : 'Модель ИИ для ваших запросов'"
            :aria-label="'Модель ИИ'"
            @change="onChange"
        >
            <option value="">
                По умолчанию ({{ defaultLabel }})
            </option>
            <option
                v-for="model in options"
                :key="model.id"
                :value="model.id"
            >
                {{ model.label }}{{ model.vendor ? ` · ${model.vendor}` : '' }}
            </option>
        </select>
        <p
            v-else
            class="text-[11px] text-f5-text-muted dark:text-white/45"
        >
            Список моделей недоступен — обновите страницу
        </p>
        <p
            v-if="error"
            class="mt-1 text-[11px] text-red-600 dark:text-red-400"
        >
            {{ error }}
        </p>
    </div>
</template>
