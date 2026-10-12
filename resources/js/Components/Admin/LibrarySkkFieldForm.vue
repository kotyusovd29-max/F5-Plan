<script setup>
import { computed } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    entityOptions: { type: Array, default: () => [] },
    valueTypeOptions: { type: Array, default: () => [] },
});

const needsOptions = computed(() => ['select', 'multiselect'].includes(props.form.value_type));
</script>

<template>
    <div class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="f5-label">Сущность</label>
                <select v-model="form.entity_type" class="f5-input" required>
                    <option v-for="option in entityOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>
            <div>
                <label class="f5-label">Тип значения</label>
                <select v-model="form.value_type" class="f5-input" required>
                    <option v-for="option in valueTypeOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>
        </div>

        <div>
            <label class="f5-label">Название</label>
            <input v-model="form.name" type="text" class="f5-input" required>
            <p v-if="form.errors.name" class="f5-error">{{ form.errors.name }}</p>
        </div>

        <div v-if="needsOptions">
            <label class="f5-label">Варианты (по одному на строку)</label>
            <textarea v-model="form.options_text" class="f5-input" rows="4" />
            <p v-if="form.errors.options" class="f5-error">{{ form.errors.options }}</p>
        </div>

        <div>
            <label class="f5-label">Этап воронки (по имени, опционально)</label>
            <input v-model="form.stage_name" type="text" class="f5-input" placeholder="Например: Квалификация">
            <p class="mt-1 text-xs text-f5-text-muted dark:text-white/40">
                При применении к проекту этап ищется по названию среди воронок проекта.
            </p>
        </div>

        <div>
            <label class="f5-label">Комментарий</label>
            <textarea v-model="form.comment" class="f5-input" rows="2" />
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_required" type="checkbox" class="rounded border-f5-border">
                Обязательное
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.show_in_card" type="checkbox" class="rounded border-f5-border">
                В карточке
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.show_in_list" type="checkbox" class="rounded border-f5-border">
                В списке
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.show_in_filter" type="checkbox" class="rounded border-f5-border">
                В фильтре
            </label>
        </div>

        <div>
            <label class="f5-label">Порядок</label>
            <input v-model.number="form.sort_order" type="number" min="0" class="f5-input max-w-xs">
        </div>
    </div>
</template>
