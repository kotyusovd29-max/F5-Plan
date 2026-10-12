<script setup>
import { ref } from 'vue';
import { postJson } from '../../utils/fetchJson';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    analysis: {
        type: Object,
        default: () => ({
            title: 'Анализ проекта',
            description: 'ИИ изучит поля, воронки и интеграции и предложит улучшения',
        }),
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const loading = ref(false);
const error = ref('');
const result = ref('');
const expanded = ref(false);

function storageKey() {
    return `f5plan:project-analysis:${props.project.id}`;
}

function loadCachedResult() {
    try {
        const cached = sessionStorage.getItem(storageKey());

        if (cached) {
            result.value = cached;
            expanded.value = true;
        }
    } catch {
        // ignore
    }
}

loadCachedResult();

async function runAnalysis() {
    if (!props.project.urls?.ai_analyze) {
        error.value = 'Анализ недоступен для этого проекта.';
        return;
    }

    loading.value = true;
    error.value = '';
    expanded.value = true;

    try {
        const { response, data } = await postJson(props.project.urls.ai_analyze);

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Не удалось выполнить анализ проекта.');
        }

        result.value = data.analysis ?? '';
        sessionStorage.setItem(storageKey(), result.value);
    } catch (exception) {
        error.value = exception.message || 'Не удалось выполнить анализ проекта.';
    } finally {
        loading.value = false;
    }
}

function toggleExpanded() {
    if (!result.value && !loading.value) {
        runAnalysis();
        return;
    }

    expanded.value = !expanded.value;
}
</script>

<template>
    <div
        class="rounded-xl border border-f5-border bg-f5-surface-2 dark:border-white/10 dark:bg-white/5"
        :class="compact ? 'mt-4' : 'mt-4'"
    >
        <div class="flex items-start gap-2 p-3">
            <button
                type="button"
                class="flex min-w-0 flex-1 items-start gap-2 text-left"
                :aria-expanded="expanded"
                @click="toggleExpanded"
            >
                <span
                    class="mt-0.5 shrink-0 text-f5-text-muted transition-transform dark:text-white/40"
                    :class="{ 'rotate-90': expanded && (result || loading) }"
                    aria-hidden="true"
                >▶</span>

                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-f5-text dark:text-white">
                        {{ analysis.title }}
                    </span>
                    <span class="mt-0.5 block text-xs text-f5-text-muted dark:text-white/50">
                        {{ analysis.description }}
                    </span>
                </span>
            </button>

            <button
                type="button"
                class="f5-btn f5-btn--primary f5-btn--sm shrink-0"
                :disabled="loading"
                @click="runAnalysis"
            >
                {{ loading ? 'Анализ…' : result ? 'Обновить' : 'Анализировать' }}
            </button>
        </div>

        <div
            v-if="expanded && (loading || error || result)"
            class="border-t border-f5-border px-3 py-3 dark:border-white/10"
        >
            <div v-if="loading" class="flex items-center gap-2 py-2 text-sm text-f5-text-muted dark:text-white/60">
                <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-f5-green/30 border-t-f5-green" />
                ИИ изучает проект…
            </div>

            <p v-else-if="error" class="text-sm text-red-600 dark:text-red-400">
                {{ error }}
            </p>

            <div
                v-else
                class="max-h-80 overflow-y-auto whitespace-pre-wrap text-sm leading-relaxed text-f5-text dark:text-white/90"
            >
                {{ result }}
            </div>
        </div>
    </div>
</template>
