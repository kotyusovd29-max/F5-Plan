<script setup>
import { computed } from 'vue';
import { skkFieldNeedsOptions, valueTypeOptions } from '../../utils/skkField';

const model = defineModel({
    type: Object,
    required: true,
});

defineProps({
    funnelStages: {
        type: Array,
        default: () => [],
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    /** Узкая колонка СКК: поля в один столбец, без горизонтального переполнения. */
    compact: {
        type: Boolean,
        default: false,
    },
});

const showOptions = computed(() => skkFieldNeedsOptions(model.value.value_type));
</script>

<template>
    <div class="min-w-0 space-y-3.5">
        <div class="min-w-0">
            <label class="f5-label">Название поля</label>
            <input
                v-model="model.name"
                type="text"
                class="f5-input"
                :class="{ 'f5-input--error': errors.name }"
                placeholder="Например: Бюджет, Источник"
                required
            >
            <p v-if="errors.name" class="f5-error">{{ errors.name }}</p>
        </div>

        <div
            class="grid min-w-0 gap-3.5"
            :class="compact ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2'"
        >
            <div class="min-w-0">
                <label class="f5-label">Тип поля</label>
                <select v-model="model.value_type" class="f5-input">
                    <option
                        v-for="option in valueTypeOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div class="min-w-0">
                <label class="f5-label">Обязательное</label>
                <select v-model="model.is_required" class="f5-input">
                    <option :value="false">Нет</option>
                    <option :value="true">Да</option>
                </select>
            </div>

            <div
                v-if="funnelStages.length > 0"
                class="min-w-0"
                :class="compact ? '' : 'sm:col-span-2'"
            >
                <label class="f5-label">{{ compact ? 'Этап заполнения' : 'Заполняется на этапе' }}</label>
                <select v-model="model.funnel_stage_id" class="f5-input">
                    <option value="">Не привязано</option>
                    <option
                        v-for="stage in funnelStages"
                        :key="stage.id"
                        :value="String(stage.id)"
                    >
                        {{ stage.label }}
                    </option>
                </select>
                <p v-if="errors.funnel_stage_id" class="f5-error">{{ errors.funnel_stage_id }}</p>
            </div>
        </div>

        <div class="min-w-0">
            <span class="f5-label">Где показывать</span>
            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                <label
                    class="inline-flex min-w-0 items-center gap-2 rounded-lg border border-f5-border bg-f5-surface px-3 py-2 text-sm text-f5-text dark:border-white/15 dark:bg-f5-black dark:text-white/80"
                >
                    <input
                        v-model="model.show_in_card"
                        type="checkbox"
                        class="rounded border-f5-border dark:border-white/20"
                    >
                    Карточка
                </label>
                <label
                    class="inline-flex min-w-0 items-center gap-2 rounded-lg border border-f5-border bg-f5-surface px-3 py-2 text-sm text-f5-text dark:border-white/15 dark:bg-f5-black dark:text-white/80"
                >
                    <input
                        v-model="model.show_in_list"
                        type="checkbox"
                        class="rounded border-f5-border dark:border-white/20"
                    >
                    Список
                </label>
                <label
                    class="inline-flex min-w-0 items-center gap-2 rounded-lg border border-f5-border bg-f5-surface px-3 py-2 text-sm text-f5-text dark:border-white/15 dark:bg-f5-black dark:text-white/80"
                >
                    <input
                        v-model="model.show_in_filter"
                        type="checkbox"
                        class="rounded border-f5-border dark:border-white/20"
                    >
                    Фильтр
                </label>
            </div>
        </div>

        <div v-if="showOptions" class="min-w-0">
            <label class="f5-label">Варианты значений</label>
            <textarea
                v-model="model.options_text"
                rows="4"
                class="f5-input resize-y"
                :class="{ 'f5-input--error': errors.options }"
                placeholder="Каждый вариант с новой строки"
            />
            <p v-if="errors.options" class="f5-error">{{ errors.options }}</p>
        </div>

        <div class="min-w-0">
            <label class="f5-label">Комментарий</label>
            <textarea
                v-model="model.comment"
                :rows="compact ? 2 : 3"
                class="f5-input resize-y"
                placeholder="Пояснения для настройщика"
            />
        </div>
    </div>
</template>
