<script setup>
import { computed, ref, watch } from 'vue';
import {
    CUSTOM_WIDGET_SLUG,
    categoryLabel,
    isChannelPricedWidget,
} from '../../utils/integrationWidget';

const model = defineModel({
    type: Object,
    required: true,
});

const props = defineProps({
    catalog: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const search = ref('');
const category = ref('');

function resolveSourceMode() {
    if (model.value.widget_slug && model.value.widget_slug !== CUSTOM_WIDGET_SLUG) {
        return 'f5';
    }

    if (model.value.widget_slug === CUSTOM_WIDGET_SLUG || (model.value.name ?? '').trim() !== '') {
        return 'custom';
    }

    return 'f5';
}

const sourceMode = ref(resolveSourceMode());

watch(
    () => model.value.widget_slug,
    () => {
        sourceMode.value = resolveSourceMode();
    },
);

const categories = computed(() => props.catalog.categories ?? {});
const isCustom = computed(() => sourceMode.value === 'custom');

const selectedWidget = computed(() => {
    if (isCustom.value || !model.value.widget_slug || model.value.widget_slug === CUSTOM_WIDGET_SLUG) {
        return null;
    }

    return (props.catalog.widgets ?? []).find((widget) => widget.slug === model.value.widget_slug) ?? null;
});

const showChannelQuantity = computed(() => isChannelPricedWidget(selectedWidget.value));

const channelQuantityLabel = computed(() => (
    selectedWidget.value?.pricing?.quantity_label
        ? `Количество ${selectedWidget.value.pricing.quantity_label}`
        : 'Количество каналов'
));

const filteredWidgets = computed(() => {
    const query = search.value.trim().toLowerCase();

    return (props.catalog.widgets ?? []).filter((widget) => {
        if (category.value && widget.category !== category.value) {
            return false;
        }

        if (!query) {
            return true;
        }

        return widget.name.toLowerCase().includes(query)
            || (widget.description ?? '').toLowerCase().includes(query)
            || widget.slug.toLowerCase().includes(query);
    });
});

function setSourceMode(mode) {
    sourceMode.value = mode;

    if (mode === 'custom') {
        model.value.widget_slug = CUSTOM_WIDGET_SLUG;
        model.value.widget_url = null;
        model.value.billing_quantity = null;
        model.value.payment_type = model.value.payment_type || 'subscription';
        model.value.cost = model.value.cost ?? '';
        return;
    }

    if (model.value.widget_slug === CUSTOM_WIDGET_SLUG) {
        model.value.widget_slug = '';
        model.value.name = '';
        model.value.widget_url = null;
        model.value.billing_quantity = null;
        model.value.payment_type = null;
        model.value.cost = null;
    }
}

function selectWidget(widget) {
    model.value.widget_slug = widget.slug;
    model.value.name = widget.name;
    model.value.widget_url = widget.url ?? null;
    model.value.payment_type = null;
    model.value.cost = null;
    sourceMode.value = 'f5';

    if (isChannelPricedWidget(widget)) {
        const min = Math.max(1, Number(widget.pricing?.min_quantity) || 1);
        const fallback = Math.max(min, Number(widget.pricing?.default_quantity) || min);
        model.value.billing_quantity = Math.max(
            min,
            Number(model.value.billing_quantity) || fallback,
        );
    } else {
        model.value.billing_quantity = null;
    }
}

function clearF5Selection() {
    model.value.widget_slug = '';
    model.value.name = '';
    model.value.widget_url = null;
    model.value.billing_quantity = null;
}
</script>

<template>
    <div class="space-y-4" :class="{ 'space-y-3': compact }">
        <div>
            <p class="f5-label">Источник виджета</p>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="sourceMode === 'f5'
                        ? 'bg-f5-black text-white dark:bg-f5-green dark:text-f5-black'
                        : 'border border-f5-border bg-f5-surface text-f5-text-muted hover:text-f5-text dark:border-white/15 dark:bg-f5-black dark:text-white/60 dark:hover:text-white'"
                    @click="setSourceMode('f5')"
                >
                    Виджеты от Ф5
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="sourceMode === 'custom'
                        ? 'bg-f5-black text-white dark:bg-f5-green dark:text-f5-black'
                        : 'border border-f5-border bg-f5-surface text-f5-text-muted hover:text-f5-text dark:border-white/15 dark:bg-f5-black dark:text-white/60 dark:hover:text-white'"
                    @click="setSourceMode('custom')"
                >
                    Свой вариант
                </button>
            </div>
            <p v-if="errors.widget_slug" class="f5-error">{{ errors.widget_slug }}</p>
        </div>

        <div v-if="sourceMode === 'f5'" class="space-y-3">
            <div
                v-if="selectedWidget"
                class="flex items-start gap-3 rounded-xl border border-f5-border bg-f5-surface-muted/60 p-3 dark:border-white/10 dark:bg-white/5"
            >
                <img
                    v-if="selectedWidget.icon"
                    :src="selectedWidget.icon"
                    :alt="selectedWidget.name"
                    class="h-12 w-12 shrink-0 rounded-lg object-contain bg-white dark:bg-f5-black"
                    loading="lazy"
                    referrerpolicy="no-referrer"
                >
                <div
                    v-else
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-f5-green/20 text-xs font-bold text-f5-text"
                    aria-hidden="true"
                >
                    F5
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-f5-text dark:text-white">{{ selectedWidget.name }}</p>
                    <p v-if="selectedWidget.description" class="mt-0.5 text-sm text-f5-text-muted dark:text-white/60">
                        {{ selectedWidget.description }}
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <a
                            v-if="selectedWidget.url"
                            :href="selectedWidget.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-xs text-f5-accent hover:underline"
                        >
                            Страница на cmdf5.ru
                        </a>
                        <button
                            type="button"
                            class="text-xs font-medium text-f5-text-muted underline-offset-2 hover:underline dark:text-white/50"
                            @click="clearF5Selection"
                        >
                            Выбрать другой
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="overflow-hidden rounded-xl border border-f5-border dark:border-white/10"
            >
                <div class="space-y-2 border-b border-f5-border p-3 dark:border-white/10">
                    <input
                        v-model="search"
                        type="search"
                        class="f5-input"
                        placeholder="Поиск по названию…"
                    >
                    <select v-model="category" class="f5-input">
                        <option value="">Все категории</option>
                        <option
                            v-for="(label, key) in categories"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>

                <ul class="integration-widget-grid max-h-80 overflow-y-auto p-2">
                    <li
                        v-for="widget in filteredWidgets"
                        :key="widget.slug"
                    >
                        <button
                            type="button"
                            class="integration-widget-card"
                            @click="selectWidget(widget)"
                        >
                            <img
                                v-if="widget.icon"
                                :src="widget.icon"
                                :alt="widget.name"
                                class="integration-widget-card__icon"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                            >
                            <span
                                v-else
                                class="integration-widget-card__icon integration-widget-card__icon--fallback"
                                aria-hidden="true"
                            >
                                F5
                            </span>
                            <span class="integration-widget-card__body">
                                <span class="integration-widget-card__name">{{ widget.name }}</span>
                                <span class="integration-widget-card__meta">
                                    {{ categoryLabel(categories, widget.category) }}
                                    <template v-if="widget.price"> · {{ widget.price }}</template>
                                </span>
                            </span>
                        </button>
                    </li>
                    <li
                        v-if="filteredWidgets.length === 0"
                        class="col-span-full px-3 py-6 text-center text-sm text-f5-text-muted dark:text-white/50"
                    >
                        Ничего не найдено
                    </li>
                </ul>
            </div>
        </div>

        <div v-else class="space-y-3">
            <div>
                <label class="f5-label">Название</label>
                <input
                    v-model="model.name"
                    type="text"
                    class="f5-input"
                    :class="{ 'f5-input--error': errors.name }"
                    placeholder="Например: Телефония, WhatsApp"
                    required
                >
                <p v-if="errors.name" class="f5-error">{{ errors.name }}</p>
            </div>

            <div>
                <p class="f5-label">Тип оплаты</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                        :class="model.payment_type === 'subscription'
                            ? 'bg-f5-black text-white dark:bg-f5-green dark:text-f5-black'
                            : 'border border-f5-border bg-f5-surface text-f5-text-muted hover:text-f5-text dark:border-white/15 dark:bg-f5-black dark:text-white/60 dark:hover:text-white'"
                        @click="model.payment_type = 'subscription'"
                    >
                        Подписка
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                        :class="model.payment_type === 'one_time'
                            ? 'bg-f5-black text-white dark:bg-f5-green dark:text-f5-black'
                            : 'border border-f5-border bg-f5-surface text-f5-text-muted hover:text-f5-text dark:border-white/15 dark:bg-f5-black dark:text-white/60 dark:hover:text-white'"
                        @click="model.payment_type = 'one_time'"
                    >
                        Единовременная
                    </button>
                </div>
                <p v-if="errors.payment_type" class="f5-error">{{ errors.payment_type }}</p>
            </div>

            <div>
                <label class="f5-label">
                    {{ model.payment_type === 'one_time' ? 'Сумма, ₽' : 'Стоимость в месяц, ₽' }}
                </label>
                <input
                    v-model="model.cost"
                    type="number"
                    min="0"
                    step="0.01"
                    class="f5-input max-w-xs"
                    :class="{ 'f5-input--error': errors.cost }"
                    :placeholder="model.payment_type === 'one_time' ? 'Например: 50000' : 'Например: 5000'"
                    :required="model.payment_type === 'one_time'"
                >
                <p class="mt-1 text-xs text-f5-text-muted dark:text-white/50">
                    <template v-if="model.payment_type === 'one_time'">
                        Разовая сумма без привязки к периоду подписки.
                    </template>
                    <template v-else>
                        Считается как у виджетов: × оплачиваемые месяцы (6+1 / 10+3).
                    </template>
                </p>
                <p v-if="errors.cost" class="f5-error">{{ errors.cost }}</p>
            </div>
        </div>

        <div v-if="showChannelQuantity">
            <label class="f5-label" for="billing_quantity">{{ channelQuantityLabel }}</label>
            <input
                id="billing_quantity"
                v-model.number="model.billing_quantity"
                type="number"
                min="1"
                max="100"
                class="f5-input"
                :class="{ 'f5-input--error': errors.billing_quantity }"
            >
            <p class="mt-1 text-xs text-f5-text-muted dark:text-white/50">
                3 500 ₽ в месяц за каждый канал, независимо от числа пользователей
            </p>
            <p v-if="errors.billing_quantity" class="f5-error">{{ errors.billing_quantity }}</p>
        </div>

        <div>
            <label class="f5-label">Комментарий</label>
            <textarea
                v-model="model.comment"
                rows="4"
                class="f5-input resize-y"
                :class="{ 'min-h-[64px]': compact }"
                placeholder="Для чего используется, настройки..."
            />
            <p v-if="errors.comment" class="f5-error">{{ errors.comment }}</p>
        </div>
    </div>
</template>
