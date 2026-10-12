<script setup>
import { computed, ref, watch } from 'vue';
import { categoryLabel } from '../../utils/integrationWidget';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    catalog: {
        type: Object,
        default: () => ({ widgets: [], categories: {} }),
    },
});

const emit = defineEmits(['close', 'select']);

const search = ref('');
const category = ref('');
const brokenIcons = ref({});

const categories = computed(() => props.catalog.categories ?? {});

const widgets = computed(() => (props.catalog.widgets ?? []).filter((w) => w.slug && w.slug !== 'custom'));

const filteredWidgets = computed(() => {
    const query = search.value.trim().toLowerCase();

    return widgets.value.filter((widget) => {
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

watch(
    () => props.open,
    (open) => {
        if (open) {
            search.value = '';
            category.value = '';
            brokenIcons.value = {};
        }
    },
);

function initial(name) {
    const text = String(name || '').trim();
    return text ? text.charAt(0).toUpperCase() : 'W';
}

function hasIcon(widget) {
    return Boolean(widget.icon) && !brokenIcons.value[widget.slug];
}

function markBroken(slug) {
    brokenIcons.value = { ...brokenIcons.value, [slug]: true };
}

function selectWidget(widget) {
    emit('select', widget);
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="funnel-widget-picker"
            role="dialog"
            aria-modal="true"
            aria-labelledby="funnel-widget-picker-title"
            @keydown="onKeydown"
        >
            <button
                type="button"
                class="funnel-widget-picker__backdrop"
                aria-label="Закрыть"
                @click="emit('close')"
            />

            <div class="funnel-widget-picker__panel">
                <header class="funnel-widget-picker__header">
                    <div>
                        <h3 id="funnel-widget-picker-title" class="funnel-widget-picker__title">
                            Виджеты F5
                        </h3>
                        <p class="funnel-widget-picker__subtitle">
                            Выберите виджет, чтобы добавить его на доску
                        </p>
                    </div>
                    <button type="button" class="f5-btn f5-btn--ghost f5-btn--sm" @click="emit('close')">
                        Закрыть
                    </button>
                </header>

                <div class="funnel-widget-picker__filters">
                    <input
                        v-model="search"
                        type="search"
                        class="f5-input"
                        placeholder="Поиск по названию…"
                        autofocus
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

                <div class="funnel-widget-picker__grid">
                    <button
                        v-for="widget in filteredWidgets"
                        :key="widget.slug"
                        type="button"
                        class="funnel-widget-picker__card"
                        @click="selectWidget(widget)"
                    >
                        <span class="funnel-widget-picker__icon" aria-hidden="true">
                            <img
                                v-if="hasIcon(widget)"
                                :src="widget.icon"
                                :alt="widget.name"
                                loading="lazy"
                                @error="markBroken(widget.slug)"
                            >
                            <span v-else class="funnel-widget-picker__letter">{{ initial(widget.name) }}</span>
                        </span>
                        <span class="funnel-widget-picker__meta">
                            <span class="funnel-widget-picker__name">{{ widget.name }}</span>
                            <span class="funnel-widget-picker__category">
                                {{ categoryLabel(categories, widget.category) }}
                            </span>
                        </span>
                    </button>

                    <p
                        v-if="filteredWidgets.length === 0"
                        class="funnel-widget-picker__empty"
                    >
                        Ничего не найдено
                    </p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
