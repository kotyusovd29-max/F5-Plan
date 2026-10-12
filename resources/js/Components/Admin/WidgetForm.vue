<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    slugLocked: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="widget_name" class="f5-label">Название</label>
                <input id="widget_name" v-model="form.name" type="text" class="f5-input" required>
                <p v-if="form.errors.name" class="f5-error">{{ form.errors.name }}</p>
            </div>
            <div>
                <label for="widget_slug" class="f5-label">Slug</label>
                <input
                    id="widget_slug"
                    v-model="form.slug"
                    type="text"
                    class="f5-input"
                    :readonly="slugLocked"
                    required
                >
                <p v-if="form.errors.slug" class="f5-error">{{ form.errors.slug }}</p>
            </div>
        </div>

        <div>
            <label for="widget_description" class="f5-label">Описание</label>
            <textarea id="widget_description" v-model="form.description" class="f5-input" rows="3" />
            <p v-if="form.errors.description" class="f5-error">{{ form.errors.description }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="widget_category" class="f5-label">Категория</label>
                <select id="widget_category" v-model="form.category" class="f5-input" required>
                    <option v-for="option in categories" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
                <p v-if="form.errors.category" class="f5-error">{{ form.errors.category }}</p>
            </div>
            <div>
                <label for="widget_price" class="f5-label">Цена (подпись)</label>
                <input id="widget_price" v-model="form.price" type="text" class="f5-input" placeholder="например: 399 ₽">
                <p v-if="form.errors.price" class="f5-error">{{ form.errors.price }}</p>
            </div>
        </div>

        <div>
            <label for="widget_url" class="f5-label">URL</label>
            <input id="widget_url" v-model="form.url" type="url" class="f5-input" placeholder="https://">
            <p v-if="form.errors.url" class="f5-error">{{ form.errors.url }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="widget_sort_order" class="f5-label">Порядок</label>
                <input id="widget_sort_order" v-model.number="form.sort_order" type="number" min="0" class="f5-input">
                <p v-if="form.errors.sort_order" class="f5-error">{{ form.errors.sort_order }}</p>
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-f5-text dark:text-white">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-f5-border">
                    Активен в каталоге
                </label>
            </div>
        </div>

        <fieldset class="space-y-4 rounded-xl border border-f5-border p-4 dark:border-white/10">
            <legend class="px-1 text-sm font-semibold text-f5-text dark:text-white">Тарификация (опционально)</legend>

            <div>
                <label for="pricing_model" class="f5-label">Модель</label>
                <select id="pricing_model" v-model="form.pricing_model" class="f5-input">
                    <option value="">Без расчёта (только подпись цены)</option>
                    <option value="per_channel_monthly">За канал / месяц</option>
                    <option value="fixed_period">Пакеты на период</option>
                </select>
            </div>

            <div v-if="form.pricing_model === 'per_channel_monthly'" class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="f5-label">Цена за канал</label>
                    <input v-model.number="form.pricing_unit_monthly" type="number" min="0" step="1" class="f5-input">
                    <p v-if="form.errors.pricing_unit_monthly" class="f5-error">{{ form.errors.pricing_unit_monthly }}</p>
                </div>
                <div>
                    <label class="f5-label">Подпись количества</label>
                    <input v-model="form.pricing_quantity_label" type="text" class="f5-input">
                </div>
                <div>
                    <label class="f5-label">Мин. количество</label>
                    <input v-model.number="form.pricing_min_quantity" type="number" min="1" class="f5-input">
                </div>
                <div>
                    <label class="f5-label">По умолчанию</label>
                    <input v-model.number="form.pricing_default_quantity" type="number" min="1" class="f5-input">
                </div>
            </div>

            <div v-if="form.pricing_model === 'fixed_period'" class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="f5-label">Пакет 6 мес</label>
                    <input v-model.number="form.pricing_package_6" type="number" min="0" class="f5-input">
                    <p v-if="form.errors.pricing_package_6" class="f5-error">{{ form.errors.pricing_package_6 }}</p>
                </div>
                <div>
                    <label class="f5-label">Пакет 12 мес</label>
                    <input v-model.number="form.pricing_package_12" type="number" min="0" class="f5-input">
                </div>
            </div>
        </fieldset>
    </div>
</template>
