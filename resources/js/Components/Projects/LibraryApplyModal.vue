<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Из библиотеки',
    },
    items: {
        type: Array,
        default: () => [],
    },
    emptyText: {
        type: String,
        default: 'В библиотеке пока нет шаблонов.',
    },
});

const emit = defineEmits(['close']);

const query = ref('');
const applyingId = ref(null);

watch(
    () => props.open,
    (value) => {
        if (value) {
            query.value = '';
            applyingId.value = null;
        }
    },
);

const filteredItems = computed(() => {
    const needle = query.value.trim().toLowerCase();

    if (!needle) {
        return props.items;
    }

    return props.items.filter((item) => {
        const haystack = [
            item.name,
            item.description,
            item.entity_label,
            item.value_type_label,
            item.stage_name,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(needle);
    });
});

function close() {
    emit('close');
}

function apply(item) {
    if (!item.apply_url || applyingId.value) {
        return;
    }

    applyingId.value = item.id;

    router.post(item.apply_url, {}, {
        preserveScroll: true,
        onFinish: () => {
            applyingId.value = null;
            close();
        },
    });
}
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="close"
    >
        <div class="flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-f5-border bg-f5-surface shadow-xl dark:border-white/10 dark:bg-f5-black">
            <div class="flex items-start justify-between gap-3 border-b border-f5-border px-5 py-4 dark:border-white/10">
                <div>
                    <h2 class="text-base font-semibold text-f5-text dark:text-white">{{ title }}</h2>
                    <p class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                        Шаблон будет скопирован в текущий проект
                    </p>
                </div>
                <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="close">
                    Закрыть
                </button>
            </div>

            <div class="border-b border-f5-border px-5 py-3 dark:border-white/10">
                <input
                    v-model="query"
                    type="search"
                    class="f5-input"
                    placeholder="Поиск…"
                >
            </div>

            <div class="flex-1 overflow-y-auto p-3">
                <p v-if="filteredItems.length === 0" class="px-2 py-8 text-center text-sm text-f5-text-muted dark:text-white/50">
                    {{ emptyText }}
                </p>

                <ul v-else class="space-y-2">
                    <li
                        v-for="item in filteredItems"
                        :key="item.id"
                        class="rounded-xl border border-f5-border p-3 dark:border-white/10"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-medium text-f5-text dark:text-white">{{ item.name }}</div>
                                <p v-if="item.description" class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                                    {{ item.description }}
                                </p>
                                <p v-else-if="item.entity_label" class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                                    {{ item.entity_label }}
                                    <span v-if="item.value_type_label"> · {{ item.value_type_label }}</span>
                                    <span v-if="item.stage_name"> · этап «{{ item.stage_name }}»</span>
                                </p>
                                <p v-else-if="item.stages_count != null" class="mt-0.5 text-xs text-f5-text-muted dark:text-white/50">
                                    {{ item.stages_count }} этап(ов)
                                </p>
                            </div>
                            <button
                                type="button"
                                class="f5-btn f5-btn--primary f5-btn--sm shrink-0"
                                :disabled="applyingId === item.id"
                                @click="apply(item)"
                            >
                                {{ applyingId === item.id ? '…' : 'Добавить' }}
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
