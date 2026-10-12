<script setup>
import { computed, ref } from 'vue';

const widgets = defineModel('widgets', {
    type: Array,
    default: () => [],
});

const props = defineProps({
    catalog: {
        type: Object,
        default: () => ({ widgets: [], categories: {} }),
    },
});

const pickSlug = ref('');

const catalogWidgets = computed(() => props.catalog?.widgets ?? []);

const availableToAdd = computed(() => {
    const used = new Set((widgets.value ?? []).map((row) => row.widget_slug));
    return catalogWidgets.value.filter((widget) => !used.has(widget.slug));
});

function widgetName(slug) {
    return catalogWidgets.value.find((widget) => widget.slug === slug)?.name || slug;
}

function addWidget() {
    const slug = pickSlug.value;

    if (!slug) {
        return;
    }

    if ((widgets.value ?? []).some((row) => row.widget_slug === slug)) {
        pickSlug.value = '';
        return;
    }

    widgets.value = [...(widgets.value ?? []), { widget_slug: slug, comment: '' }];
    pickSlug.value = '';
}

function removeWidget(index) {
    const next = [...(widgets.value ?? [])];
    next.splice(index, 1);
    widgets.value = next;
}

function updateComment(index, comment) {
    widgets.value = (widgets.value ?? []).map((row, rowIndex) => (
        rowIndex === index ? { ...row, comment } : row
    ));
}
</script>

<template>
    <div>
        <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
            <label class="f5-label mb-0">Виджеты F5 на этапе</label>
            <div v-if="availableToAdd.length" class="flex items-center gap-1.5">
                <select v-model="pickSlug" class="f5-input !w-auto max-w-[200px] !py-1.5 text-xs">
                    <option value="">Выберите виджет</option>
                    <option
                        v-for="widget in availableToAdd"
                        :key="widget.slug"
                        :value="widget.slug"
                    >
                        {{ widget.name }}
                    </option>
                </select>
                <button
                    type="button"
                    class="f5-btn f5-btn--ghost f5-btn--sm"
                    :disabled="!pickSlug"
                    @click="addWidget"
                >
                    Добавить
                </button>
            </div>
        </div>

        <p
            v-if="!(widgets ?? []).length"
            class="rounded-xl border border-dashed border-f5-border px-3 py-2 text-xs text-f5-text-muted dark:border-white/15 dark:text-white/45"
        >
            Виджеты этапа — отдельный список (сценарий на этапе). Запуск по результату автозадачи выбирается в блоке «Результаты».
        </p>

        <ul v-else class="space-y-2">
            <li
                v-for="(row, index) in widgets"
                :key="`${row.widget_slug}-${index}`"
                class="rounded-xl border border-f5-border bg-f5-surface-2 p-3 dark:border-white/10 dark:bg-white/[0.03]"
            >
                <div class="mb-2 flex items-start justify-between gap-2">
                    <p class="text-sm font-medium text-f5-text dark:text-white">
                        {{ widgetName(row.widget_slug) }}
                    </p>
                    <button
                        type="button"
                        class="text-xs text-f5-text-muted underline hover:text-f5-text dark:text-white/45 dark:hover:text-white"
                        @click="removeWidget(index)"
                    >
                        Убрать
                    </button>
                </div>
                <label class="sr-only" :for="`stage-widget-comment-${index}`">Комментарий к виджету</label>
                <textarea
                    :id="`stage-widget-comment-${index}`"
                    :value="row.comment ?? ''"
                    rows="2"
                    class="f5-input min-h-[64px] resize-y text-sm"
                    placeholder="Зачем виджет на этом этапе, какой сценарий…"
                    @input="updateComment(index, $event.target.value)"
                />
            </li>
        </ul>
    </div>
</template>
