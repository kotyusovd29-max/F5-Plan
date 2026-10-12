<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ProjectCard from '../../Components/ProjectCard.vue';
import RopTeamDashboard from '../../Components/Home/RopTeamDashboard.vue';
import SectionEmptyState from '../../Components/Projects/SectionEmptyState.vue';

const props = defineProps({
    view: {
        type: String,
        default: 'mine',
    },
    filters: {
        type: Object,
        default: () => ({
            q: '',
            status: 'active',
            sphere: '',
            date_from: '',
            date_to: '',
        }),
    },
    statusFilters: {
        type: Array,
        default: () => [],
    },
    sphereOptions: {
        type: Array,
        default: () => [],
    },
    dashboard: {
        type: Object,
        default: () => ({
            drafts_count: 0,
            pending_approval_count: 0,
            drafts_url: '/',
            pending_approval_url: '/',
            recent_ai: [],
        }),
    },
    teamDashboard: {
        type: Object,
        default: null,
    },
    canViewAll: {
        type: Boolean,
        default: false,
    },
    canViewTeam: {
        type: Boolean,
        default: false,
    },
    sharedProjectsCount: {
        type: Number,
        default: 0,
    },
    projects: {
        type: Array,
        default: () => [],
    },
    sharedProjects: {
        type: Array,
        default: () => [],
    },
    employees: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    selectedUser: {
        type: Object,
        default: null,
    },
});

const localFilters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? 'active',
    sphere: props.filters.sphere ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

watch(
    () => props.filters,
    (filters) => {
        localFilters.q = filters.q ?? '';
        localFilters.status = filters.status ?? 'active';
        localFilters.sphere = filters.sphere ?? '';
        localFilters.date_from = filters.date_from ?? '';
        localFilters.date_to = filters.date_to ?? '';
    },
    { deep: true },
);

const isTeamLikeView = computed(() => props.view === 'all' || props.view === 'team');
const showSharedTab = computed(() => props.sharedProjectsCount > 0 || props.view === 'shared');
const showDashboard = computed(() => props.view === 'mine');
const showTeamDashboard = computed(() => props.view === 'team' && Boolean(props.teamDashboard));
const isEmptyMine = computed(() => props.view === 'mine' && props.projects.length === 0);
const isEmptyShared = computed(() => props.view === 'shared' && props.sharedProjects.length === 0);
const isEmptyTeamNoUser = computed(() => props.view === 'all' && !props.selectedUser);
const isEmptyTeamUser = computed(() => isTeamLikeView.value && props.selectedUser && props.projects.length === 0);
const isEmptyTeamOverview = computed(() => props.view === 'team' && !props.selectedUser && props.projects.length === 0);

const openDepartmentKey = ref(null);

function departmentForUser(userId) {
    if (!userId) {
        return null;
    }

    return props.departments.find((department) => (
        (department.employees ?? []).some((employee) => employee.id === userId)
    )) ?? null;
}

watch(
    [() => props.view, () => props.selectedUser?.id, () => props.departments],
    () => {
        if (props.view !== 'all') {
            openDepartmentKey.value = null;
            return;
        }

        const current = departmentForUser(props.selectedUser?.id);
        if (current) {
            openDepartmentKey.value = current.key;
        }
    },
    { immediate: true, deep: true },
);

const openDepartment = computed(() => (
    props.departments.find((item) => item.key === openDepartmentKey.value) ?? null
));

function toggleDepartment(key) {
    openDepartmentKey.value = openDepartmentKey.value === key ? null : key;
}

function departmentProjectsCount(department) {
    return (department.employees ?? []).reduce(
        (sum, employee) => sum + (Number(employee.projects_count) || 0),
        0,
    );
}

function departmentEmployeesLabel(count) {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return 'сотрудник';
    }

    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
        return 'сотрудника';
    }

    return 'сотрудников';
}
const hasActiveSearch = computed(() => (
    Boolean(localFilters.q)
    || Boolean(localFilters.sphere)
    || Boolean(localFilters.date_from)
    || Boolean(localFilters.date_to)
    || (localFilters.status && localFilters.status !== 'active')
));

function homeQuery(overrides = {}) {
    const query = {
        view: props.view,
        status: localFilters.status,
        q: localFilters.q || undefined,
        sphere: localFilters.sphere || undefined,
        date_from: localFilters.date_from || undefined,
        date_to: localFilters.date_to || undefined,
        ...overrides,
    };

    if (props.selectedUser?.id && (query.view === 'all' || query.view === 'team')) {
        query.user = props.selectedUser.id;
    }

    Object.keys(query).forEach((key) => {
        if (query[key] === undefined || query[key] === null || query[key] === '') {
            delete query[key];
        }
    });

    return query;
}

function navigate(overrides = {}) {
    router.get('/', homeQuery(overrides), {
        preserveState: true,
        preserveScroll: true,
    });
}

function switchView(nextView) {
    navigate({
        view: nextView,
        status: nextView === 'shared' && localFilters.status === 'deleted'
            ? 'active'
            : localFilters.status,
    });
}

function switchStatus(nextStatus) {
    localFilters.status = nextStatus;
    navigate({ status: nextStatus });
}

function applyFilters() {
    navigate();
}

function resetFilters() {
    localFilters.q = '';
    localFilters.status = 'active';
    localFilters.sphere = '';
    localFilters.date_from = '';
    localFilters.date_to = '';
    navigate({
        q: undefined,
        status: 'active',
        sphere: undefined,
        date_from: undefined,
        date_to: undefined,
    });
}

function selectEmployee(userId) {
    router.get('/', {
        ...homeQuery({
            view: props.view === 'team' ? 'team' : 'all',
        }),
        user: userId,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function projectsLabel(count) {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return 'проект';
    }

    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
        return 'проекта';
    }

    return 'проектов';
}
</script>

<template>
    <Head title="Главная" />

    <AppLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-f5-text dark:text-white">
                    Мои проекты
                </h1>
                <p class="text-sm text-f5-text-muted dark:text-white/50">
                    Планы внедрения amoCRM
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-6xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="inline-flex flex-wrap rounded-xl border border-f5-border bg-f5-surface p-1 dark:border-white/10 dark:bg-f5-black">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        :class="view === 'mine'
                            ? 'bg-f5-green text-f5-black'
                            : 'text-f5-text-muted hover:text-f5-text dark:text-white/50 dark:hover:text-white'"
                        @click="switchView('mine')"
                    >
                        Мои проекты
                    </button>
                    <button
                        v-if="showSharedTab"
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        :class="view === 'shared'
                            ? 'bg-f5-green text-f5-black'
                            : 'text-f5-text-muted hover:text-f5-text dark:text-white/50 dark:hover:text-white'"
                        @click="switchView('shared')"
                    >
                        Доступные мне
                        <span
                            v-if="sharedProjectsCount > 0"
                            class="ml-1 text-xs opacity-70"
                        >({{ sharedProjectsCount }})</span>
                    </button>
                    <button
                        v-if="canViewTeam"
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        :class="view === 'team'
                            ? 'bg-f5-yellow text-f5-black'
                            : 'text-f5-text-muted hover:text-f5-text dark:text-white/50 dark:hover:text-white'"
                        @click="switchView('team')"
                    >
                        Команда
                    </button>
                    <button
                        v-if="canViewAll"
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        :class="view === 'all'
                            ? 'bg-f5-yellow text-f5-black'
                            : 'text-f5-text-muted hover:text-f5-text dark:text-white/50 dark:hover:text-white'"
                        @click="switchView('all')"
                    >
                        Все проекты
                    </button>
                </div>

                <Link href="/projects/create" class="f5-btn f5-btn--primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Создать проект
                </Link>
            </div>

            <section
                v-if="showDashboard"
                class="f5-stagger mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Link
                    :href="dashboard.drafts_url"
                    class="f5-stagger-item f5-card-lift rounded-2xl border border-f5-border bg-f5-surface p-5 no-underline hover:border-f5-green dark:border-white/10 dark:bg-f5-black dark:hover:border-f5-green"
                    :style="{ '--stagger-index': 0 }"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                        Мои черновики
                    </p>
                    <p class="mt-2 text-3xl font-bold text-f5-text dark:text-white">
                        {{ dashboard.drafts_count }}
                    </p>
                    <p class="mt-1 text-sm text-f5-text-muted dark:text-white/50">
                        Планы в работе
                    </p>
                </Link>

                <Link
                    :href="dashboard.pending_approval_url"
                    class="f5-stagger-item f5-card-lift rounded-2xl border border-f5-border bg-f5-surface p-5 no-underline hover:border-f5-yellow dark:border-white/10 dark:bg-f5-black dark:hover:border-f5-yellow"
                    :style="{ '--stagger-index': 1 }"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                        Ждут согласования
                    </p>
                    <p class="mt-2 text-3xl font-bold text-f5-text dark:text-white">
                        {{ dashboard.pending_approval_count }}
                    </p>
                    <p class="mt-1 text-sm text-f5-text-muted dark:text-white/50">
                        На проверке у команды
                    </p>
                </Link>

                <div
                    class="f5-stagger-item rounded-2xl border border-f5-border bg-f5-surface p-5 dark:border-white/10 dark:bg-f5-black sm:col-span-2 lg:col-span-1"
                    :style="{ '--stagger-index': 2 }"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                        Недавние AI-сессии
                    </p>
                    <ul v-if="dashboard.recent_ai?.length" class="mt-3 space-y-2">
                        <li
                            v-for="item in dashboard.recent_ai"
                            :key="item.id"
                        >
                            <a
                                :href="item.url"
                                class="block rounded-lg px-2 py-1.5 no-underline transition-colors hover:bg-f5-surface-2 dark:hover:bg-white/5"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-medium text-f5-text dark:text-white">
                                        {{ item.project_name }}
                                    </span>
                                    <span class="shrink-0 text-[10px] uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                                        {{ item.thread_label }}
                                    </span>
                                </div>
                                <p class="mt-0.5 line-clamp-1 text-xs text-f5-text-muted dark:text-white/50">
                                    {{ item.preview }}
                                </p>
                            </a>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-f5-text-muted dark:text-white/50">
                        Пока нет ответов ИИ по вашим проектам.
                    </p>
                </div>
            </section>

            <RopTeamDashboard
                v-if="showTeamDashboard"
                :dashboard="teamDashboard"
            />

            <form
                class="mb-6 grid gap-3 rounded-2xl border border-f5-border bg-f5-surface p-4 dark:border-white/10 dark:bg-f5-black sm:grid-cols-2 lg:grid-cols-6"
                @submit.prevent="applyFilters"
            >
                <div class="sm:col-span-2 lg:col-span-2">
                    <label for="home_q" class="f5-label">Поиск</label>
                    <input
                        id="home_q"
                        v-model="localFilters.q"
                        type="search"
                        class="f5-input"
                        placeholder="Компания, сфера, задачи…"
                    >
                </div>
                <div>
                    <label for="home_sphere" class="f5-label">Сфера</label>
                    <select id="home_sphere" v-model="localFilters.sphere" class="f5-input">
                        <option value="">Все сферы</option>
                        <option
                            v-for="sphere in sphereOptions"
                            :key="sphere"
                            :value="sphere"
                        >
                            {{ sphere }}
                        </option>
                    </select>
                </div>
                <div>
                    <label for="home_date_from" class="f5-label">Дата с</label>
                    <input
                        id="home_date_from"
                        v-model="localFilters.date_from"
                        type="date"
                        class="f5-input"
                    >
                </div>
                <div>
                    <label for="home_date_to" class="f5-label">Дата по</label>
                    <input
                        id="home_date_to"
                        v-model="localFilters.date_to"
                        type="date"
                        class="f5-input"
                    >
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="f5-btn f5-btn--primary w-full">
                        Найти
                    </button>
                    <button
                        v-if="hasActiveSearch"
                        type="button"
                        class="f5-btn f5-btn--ghost shrink-0"
                        @click="resetFilters"
                    >
                        Сброс
                    </button>
                </div>
            </form>

            <div class="mb-6 flex flex-wrap gap-2">
                <button
                    v-for="filter in statusFilters"
                    :key="filter.value"
                    type="button"
                    class="rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="localFilters.status === filter.value
                        ? 'border-f5-green bg-f5-green/15 text-f5-text dark:bg-f5-green/20 dark:text-white'
                        : 'border-f5-border text-f5-text-muted hover:border-f5-green/50 hover:text-f5-text dark:border-white/10 dark:text-white/50 dark:hover:text-white'"
                    @click="switchStatus(filter.value)"
                >
                    {{ filter.label }}
                </button>
            </div>

            <section v-if="view === 'all'" class="mb-8">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                    Отделы
                </h2>

                <p v-if="departments.length === 0" class="text-sm text-f5-text-muted dark:text-white/50">
                    Проектов пока нет.
                </p>

                <div v-else class="space-y-4">
                    <div class="flex flex-wrap gap-3">
                        <button
                            v-for="department in departments"
                            :key="department.key"
                            type="button"
                            class="rounded-xl border px-4 py-3 text-left transition-colors"
                            :class="openDepartmentKey === department.key
                                ? 'border-f5-green bg-f5-green/15 dark:bg-f5-green/25'
                                : 'border-f5-border bg-f5-surface hover:border-f5-yellow/60 dark:border-white/10 dark:bg-f5-black dark:hover:border-f5-yellow/50'"
                            @click="toggleDepartment(department.key)"
                        >
                            <span class="block text-sm font-semibold text-f5-text dark:text-white">
                                {{ department.title }}
                            </span>
                            <span class="mt-0.5 block text-xs text-f5-text-muted dark:text-white/50">
                                {{ department.employees.length }}
                                {{ departmentEmployeesLabel(department.employees.length) }}
                                ·
                                {{ departmentProjectsCount(department) }}
                                {{ projectsLabel(departmentProjectsCount(department)) }}
                            </span>
                        </button>
                    </div>

                    <div
                        v-if="openDepartment"
                        class="rounded-2xl border border-f5-border bg-f5-surface-2/70 p-4 dark:border-white/10 dark:bg-white/5"
                    >
                        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                            Сотрудники · {{ openDepartment.title }}
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <button
                                v-for="employee in openDepartment.employees"
                                :key="employee.id"
                                type="button"
                                class="rounded-xl border px-4 py-3 text-left transition-colors"
                                :class="selectedUser?.id === employee.id
                                    ? 'border-f5-green bg-f5-green/15 dark:bg-f5-green/25'
                                    : 'border-f5-border bg-f5-surface hover:border-f5-yellow/60 dark:border-white/10 dark:bg-f5-black dark:hover:border-f5-yellow/50'"
                                @click="selectEmployee(employee.id)"
                            >
                                <span class="block text-sm font-semibold text-f5-text dark:text-white">
                                    {{ employee.name }}
                                </span>
                                <span class="mt-0.5 block text-xs text-f5-text-muted dark:text-white/50">
                                    {{ employee.projects_count }} {{ projectsLabel(employee.projects_count) }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section v-else-if="view === 'team'" class="mb-8">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-f5-text-muted dark:text-white/40">
                    Мои сотрудники
                </h2>

                <p v-if="employees.length === 0" class="text-sm text-f5-text-muted dark:text-white/50">
                    К вам пока не привязаны сотрудники.
                </p>

                <div v-else class="flex flex-wrap gap-3">
                    <button
                        v-for="employee in employees"
                        :key="employee.id"
                        type="button"
                        class="rounded-xl border px-4 py-3 text-left transition-colors"
                        :class="selectedUser?.id === employee.id
                            ? 'border-f5-green bg-f5-green/15 dark:bg-f5-green/25'
                            : 'border-f5-border bg-f5-surface hover:border-f5-yellow/60 dark:border-white/10 dark:bg-f5-black dark:hover:border-f5-yellow/50'"
                        @click="selectEmployee(employee.id)"
                    >
                        <span class="block text-sm font-semibold text-f5-text dark:text-white">
                            {{ employee.name }}
                        </span>
                        <span class="mt-0.5 block text-xs text-f5-text-muted dark:text-white/50">
                            {{ employee.projects_count }} {{ projectsLabel(employee.projects_count) }}
                        </span>
                    </button>
                </div>
            </section>

            <h2
                v-if="isTeamLikeView && selectedUser"
                class="mb-4 text-lg font-semibold text-f5-text dark:text-white"
            >
                Проекты: {{ selectedUser.name }}
            </h2>

            <h2
                v-else-if="view === 'team' && !selectedUser"
                class="mb-4 text-lg font-semibold text-f5-text dark:text-white"
            >
                Проекты команды
            </h2>

            <h2
                v-else-if="view === 'shared'"
                class="mb-4 text-lg font-semibold text-f5-text dark:text-white"
            >
                Проекты с доступом
            </h2>

            <div
                v-if="isEmptyMine"
                class="overflow-hidden rounded-2xl border border-f5-border bg-f5-surface dark:border-white/10 dark:bg-f5-black"
            >
                <SectionEmptyState
                    v-if="localFilters.status === 'deleted'"
                    title="Удалённых проектов нет"
                    description="Здесь появятся проекты после мягкого удаления."
                />
                <SectionEmptyState
                    v-else-if="localFilters.status === 'archived'"
                    title="В архиве пока пусто"
                    description="Архивные проекты будут отображаться в этом разделе."
                />
                <SectionEmptyState
                    v-else-if="hasActiveSearch"
                    title="Ничего не найдено"
                    description="Измените фильтры или сбросьте поиск."
                />
                <SectionEmptyState
                    v-else
                    title="У вас пока нет проектов"
                    description="Создайте первый план внедрения — воронки, поля, виджеты и этапы в одном месте."
                    action-label="Создать проект"
                    @action="router.visit('/projects/create')"
                />
            </div>

            <div
                v-else-if="isEmptyShared"
                class="overflow-hidden rounded-2xl border border-f5-border bg-f5-surface dark:border-white/10 dark:bg-f5-black"
            >
                <SectionEmptyState
                    title="Нет доступных проектов"
                    description="Когда коллеги откроют вам доступ, проекты появятся здесь."
                />
            </div>

            <div
                v-else-if="isEmptyTeamNoUser"
                class="overflow-hidden rounded-2xl border border-f5-border bg-f5-surface dark:border-white/10 dark:bg-f5-black"
            >
                <SectionEmptyState
                    title="Выберите сотрудника"
                    description="Откройте отдел и сотрудника, чтобы увидеть его проекты."
                />
            </div>

            <div
                v-else-if="isEmptyTeamOverview"
                class="overflow-hidden rounded-2xl border border-f5-border bg-f5-surface dark:border-white/10 dark:bg-f5-black"
            >
                <SectionEmptyState
                    title="У команды пока нет проектов"
                    description="Проекты сотрудников появятся здесь, как только будут созданы."
                />
            </div>

            <div
                v-else-if="isEmptyTeamUser"
                class="overflow-hidden rounded-2xl border border-f5-border bg-f5-surface dark:border-white/10 dark:bg-f5-black"
            >
                <SectionEmptyState
                    title="У сотрудника пока нет проектов"
                    description="Попробуйте другой статус или сбросьте фильтры."
                />
            </div>

            <div
                v-else-if="view === 'shared'"
                class="f5-stagger grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <ProjectCard
                    v-for="(project, index) in sharedProjects"
                    :key="project.id"
                    :project="project"
                    :stagger-index="index"
                />
            </div>

            <div
                v-else
                class="f5-stagger grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <ProjectCard
                    v-for="(project, index) in projects"
                    :key="project.id"
                    :project="project"
                    :stagger-index="index"
                />
            </div>

            <section
                v-if="view === 'mine' && sharedProjects.length > 0"
                class="mt-10"
            >
                <div class="mb-4 flex items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-f5-text dark:text-white">
                            Доступные мне
                        </h2>
                        <p class="mt-0.5 text-sm text-f5-text-muted dark:text-white/50">
                            Проекты других пользователей, к которым вам открыли доступ
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-sm font-medium text-f5-green hover:underline"
                        @click="switchView('shared')"
                    >
                        Все {{ sharedProjectsCount }}
                    </button>
                </div>

                <div class="f5-stagger grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <ProjectCard
                        v-for="(project, index) in sharedProjects.slice(0, 6)"
                        :key="`shared-${project.id}`"
                        :project="project"
                        :stagger-index="index"
                    />
                </div>
            </section>

            <section
                v-if="$page.props.training"
                class="mt-10 rounded-2xl border border-f5-border bg-f5-surface p-5 dark:border-white/10 dark:bg-f5-black"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-f5-text dark:text-white">
                            Учебный проект
                        </p>
                        <p class="mt-0.5 text-sm text-f5-text-muted dark:text-white/55">
                            {{ $page.props.training.company_name }} — доступен всем. Редактирует только админ.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="$page.props.training.start_url"
                            class="f5-btn f5-btn--primary f5-btn--sm"
                        >
                            Пройти обучение
                        </Link>
                        <Link
                            :href="$page.props.training.show_url"
                            class="f5-btn f5-btn--ghost f5-btn--sm"
                        >
                            Открыть проект
                        </Link>
                        <Link
                            href="/knowledge"
                            class="f5-btn f5-btn--ghost f5-btn--sm"
                        >
                            База знаний
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
