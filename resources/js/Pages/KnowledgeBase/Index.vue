<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { useProductTour } from '../../composables/useProductTour';

const props = defineProps({
    articles: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const training = computed(() => page.props.training ?? null);
const { startFromKnowledgeBase } = useProductTour();

const query = ref('');
const activeId = ref(props.articles[0]?.id ?? null);
const articleEl = ref(null);

const articlesById = computed(() => {
    const map = new Map();

    for (const article of props.articles) {
        map.set(article.id, article);
    }

    return map;
});

const categories = computed(() => {
    const map = new Map();

    for (const article of props.articles) {
        if (!map.has(article.category)) {
            map.set(article.category, []);
        }
        map.get(article.category).push(article);
    }

    return [...map.entries()].map(([name, items]) => ({ name, items }));
});

const filteredArticles = computed(() => {
    const needle = query.value.trim().toLowerCase();

    if (!needle) {
        return props.articles;
    }

    return props.articles.filter((article) => {
        const haystack = [
            article.title,
            article.summary,
            article.category,
            ...(article.tags ?? []),
            ...article.sections.flatMap((section) => [
                section.heading,
                ...section.body,
                ...(section.tips ?? []),
            ]),
        ].join(' ').toLowerCase();

        return haystack.includes(needle);
    });
});

const filteredCategories = computed(() => {
    const ids = new Set(filteredArticles.value.map((item) => item.id));

    return categories.value
        .map((category) => ({
            ...category,
            items: category.items.filter((item) => ids.has(item.id)),
        }))
        .filter((category) => category.items.length > 0);
});

const activeArticle = computed(() => (
    filteredArticles.value.find((item) => item.id === activeId.value)
    ?? filteredArticles.value[0]
    ?? null
));

const relatedArticles = computed(() => {
    if (!activeArticle.value?.related?.length) {
        return [];
    }

    return activeArticle.value.related
        .map((id) => articlesById.value.get(id))
        .filter(Boolean);
});

const readingMinutes = computed(() => {
    if (!activeArticle.value) {
        return 0;
    }

    const text = activeArticle.value.sections
        .flatMap((section) => [...section.body, ...(section.tips ?? [])])
        .join(' ');

    const words = text.trim().split(/\s+/).filter(Boolean).length;

    return Math.max(1, Math.round(words / 180));
});

watch(filteredArticles, (list) => {
    if (!list.length) {
        activeId.value = null;
        return;
    }

    if (!list.some((item) => item.id === activeId.value)) {
        activeId.value = list[0].id;
    }
});

watch(activeId, async () => {
    await nextTick();
    articleEl.value?.scrollTo?.({ top: 0 });
});

function selectArticle(id) {
    activeId.value = id;
}
</script>

<template>
    <Head title="База знаний" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">
                    База знаний
                </h1>
                <p class="text-sm text-f5-text-muted dark:text-white/50">
                    Живая справка консультанта F5 Plan
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-6xl">
            <PageHeader
                title="Как работать в F5 Plan"
                subtitle="От wizard’а и библиотеки до сметы, панели РОПа и пушей. Ищите по слову или листайте слева."
            />

            <section
                v-if="training"
                class="mb-5 overflow-hidden rounded-2xl border border-f5-border bg-gradient-to-br from-f5-green/15 via-f5-surface to-f5-yellow/10 p-5 dark:border-white/10 dark:from-f5-green/10 dark:via-f5-black dark:to-f5-yellow/5"
                data-tour="kb-training"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-widest text-f5-text-muted dark:text-white/45">
                            Интерактивное обучение
                        </p>
                        <h2 class="mt-1 text-lg font-semibold text-f5-text dark:text-white">
                            Пройти тур по учебному проекту
                        </h2>
                        <p class="mt-1 max-w-2xl text-sm text-f5-text-muted dark:text-white/55">
                            Покажем карточку проекта, воронки, поля, интеграции, ИИ-чат и доску.
                            Можно остановить в любой момент — крестик или Escape.
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button
                            type="button"
                            class="f5-btn f5-btn--primary"
                            data-tour="kb-training-start"
                            @click="startFromKnowledgeBase"
                        >
                            Начать обучение
                        </button>
                        <Link
                            :href="training.show_url"
                            class="f5-btn f5-btn--ghost"
                        >
                            Открыть учебный проект
                        </Link>
                    </div>
                </div>
            </section>

            <div class="grid gap-5 lg:grid-cols-[280px_minmax(0,1fr)]">
                <aside class="f5-card flex max-h-[min(70vh,720px)] flex-col overflow-hidden p-3 dark:bg-f5-black lg:sticky lg:top-24">
                    <label class="sr-only" for="kb-search">Поиск по базе знаний</label>
                    <input
                        id="kb-search"
                        v-model="query"
                        type="search"
                        class="f5-input mb-3"
                        placeholder="Поиск: клон, SLA, смета, Google…"
                    >

                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
                        <p
                            v-if="filteredCategories.length === 0"
                            class="px-2 py-3 text-sm text-f5-text-muted dark:text-white/50"
                        >
                            Ничего не найдено. Попробуйте «пакет», «библиотека» или «уведомления».
                        </p>

                        <section
                            v-for="category in filteredCategories"
                            :key="category.name"
                        >
                            <p class="mb-1.5 px-2 text-[11px] font-semibold uppercase tracking-widest text-f5-text-muted dark:text-white/40">
                                {{ category.name }}
                            </p>
                            <ul class="space-y-1">
                                <li
                                    v-for="article in category.items"
                                    :key="article.id"
                                >
                                    <button
                                        type="button"
                                        class="w-full rounded-xl px-3 py-2.5 text-left transition"
                                        :class="activeArticle?.id === article.id
                                            ? 'bg-f5-green/15 text-f5-text dark:bg-f5-green/20 dark:text-white'
                                            : 'text-f5-text-muted hover:bg-f5-surface-2 hover:text-f5-text dark:text-white/60 dark:hover:bg-white/5 dark:hover:text-white'"
                                        @click="selectArticle(article.id)"
                                    >
                                        <span class="block text-sm font-semibold leading-snug">
                                            {{ article.title }}
                                        </span>
                                        <span class="mt-0.5 block text-[11px] leading-snug opacity-70">
                                            {{ article.summary }}
                                        </span>
                                    </button>
                                </li>
                            </ul>
                        </section>
                    </div>
                </aside>

                <article
                    ref="articleEl"
                    class="f5-card min-h-[420px] overflow-y-auto p-5 sm:p-7 dark:bg-f5-black"
                >
                    <template v-if="activeArticle">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-xs font-semibold uppercase tracking-widest text-f5-green">
                                {{ activeArticle.category }}
                            </p>
                            <span class="text-xs text-f5-text-muted dark:text-white/40">
                                · {{ readingMinutes }} мин чтения
                            </span>
                        </div>
                        <h2 class="mt-2 text-2xl font-bold text-f5-text dark:text-white">
                            {{ activeArticle.title }}
                        </h2>
                        <p class="mt-2 text-sm text-f5-text-muted dark:text-white/55">
                            {{ activeArticle.summary }}
                        </p>

                        <div
                            v-if="activeArticle.tags?.length"
                            class="mt-4 flex flex-wrap gap-1.5"
                        >
                            <span
                                v-for="tag in activeArticle.tags"
                                :key="tag"
                                class="rounded-full border border-f5-border px-2.5 py-0.5 text-[11px] text-f5-text-muted dark:border-white/10 dark:text-white/45"
                            >
                                {{ tag }}
                            </span>
                        </div>

                        <div class="mt-8 space-y-8">
                            <section
                                v-for="(section, index) in activeArticle.sections"
                                :key="`${activeArticle.id}-${index}`"
                            >
                                <h3 class="text-base font-semibold text-f5-text dark:text-white">
                                    {{ section.heading }}
                                </h3>
                                <div class="mt-3 space-y-2.5 text-sm leading-relaxed text-f5-text dark:text-white/80">
                                    <p
                                        v-for="(paragraph, paragraphIndex) in section.body"
                                        :key="paragraphIndex"
                                        class="whitespace-pre-wrap"
                                    >
                                        {{ paragraph }}
                                    </p>
                                </div>
                                <div
                                    v-if="section.tips?.length"
                                    class="mt-4 rounded-xl border border-f5-green/30 bg-f5-green/5 px-4 py-3 dark:border-f5-green/20 dark:bg-f5-green/10"
                                >
                                    <p class="text-xs font-semibold uppercase tracking-wide text-f5-text dark:text-white">
                                        На практике
                                    </p>
                                    <ul class="mt-2 space-y-1.5 text-sm text-f5-text dark:text-white/80">
                                        <li
                                            v-for="(tip, tipIndex) in section.tips"
                                            :key="tipIndex"
                                            class="leading-relaxed"
                                        >
                                            {{ tip }}
                                        </li>
                                    </ul>
                                </div>
                            </section>
                        </div>

                        <div
                            v-if="relatedArticles.length"
                            class="mt-10 border-t border-f5-border pt-6 dark:border-white/10"
                        >
                            <p class="text-xs font-semibold uppercase tracking-widest text-f5-text-muted dark:text-white/40">
                                Читайте также
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    v-for="item in relatedArticles"
                                    :key="item.id"
                                    type="button"
                                    class="rounded-xl border border-f5-border bg-f5-surface-2 px-3 py-2 text-left text-sm transition hover:border-f5-green dark:border-white/10 dark:bg-white/5 dark:hover:border-f5-green"
                                    @click="selectArticle(item.id)"
                                >
                                    <span class="block font-medium text-f5-text dark:text-white">
                                        {{ item.title }}
                                    </span>
                                    <span class="mt-0.5 block text-[11px] text-f5-text-muted dark:text-white/50">
                                        {{ item.category }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </template>

                    <p
                        v-else
                        class="text-sm text-f5-text-muted dark:text-white/50"
                    >
                        Выберите статью слева.
                    </p>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
