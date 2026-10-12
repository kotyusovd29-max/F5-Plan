<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    comments: {
        type: Array,
        default: () => [],
    },
    stages: {
        type: Array,
        default: () => [],
    },
    activeStageId: {
        type: [Number, String, null],
        default: null,
    },
    storeUrl: {
        type: String,
        default: null,
    },
    destroyBaseUrl: {
        type: String,
        default: null,
    },
    canPost: {
        type: Boolean,
        default: false,
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    boardMarkersVisible: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:boardMarkersVisible']);

const scope = ref(props.activeStageId != null ? 'stage' : 'board');
const deletingId = ref(null);

watch(
    () => props.activeStageId,
    (stageId) => {
        if (stageId != null && scope.value === 'stage') {
            return;
        }
        if (stageId == null) {
            scope.value = 'board';
        }
    },
);

const form = useForm({
    author_name: '',
    body: '',
    scope: 'board',
    funnel_stage_id: null,
});

const stageById = computed(() => {
    const map = new Map();
    (props.stages ?? []).forEach((stage) => map.set(Number(stage.id), stage));
    return map;
});

const activeStage = computed(() => (
    props.activeStageId != null
        ? stageById.value.get(Number(props.activeStageId)) ?? null
        : null
));

const visibleComments = computed(() => {
    const list = [...(props.comments ?? [])];

    if (scope.value === 'stage' && props.activeStageId != null) {
        return list.filter((row) => Number(row.funnel_stage_id) === Number(props.activeStageId));
    }

    if (scope.value === 'board') {
        return list.filter((row) => row.funnel_stage_id == null);
    }

    return list;
});

const boardCount = computed(() => (props.comments ?? []).filter((row) => row.funnel_stage_id == null).length);

const stageCount = computed(() => {
    if (props.activeStageId == null) return 0;
    return (props.comments ?? []).filter((row) => Number(row.funnel_stage_id) === Number(props.activeStageId)).length;
});

function submit() {
    if (!props.canPost || !props.storeUrl) return;

    form.scope = scope.value;
    form.funnel_stage_id = scope.value === 'stage' && props.activeStageId != null
        ? Number(props.activeStageId)
        : null;

    form.post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('body');
        },
    });
}

function destroyComment(comment) {
    if (!props.canManage || !props.destroyBaseUrl) return;
    if (!window.confirm('Удалить этот комментарий?')) return;

    deletingId.value = comment.id;
    router.delete(`${props.destroyBaseUrl}/${comment.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}

function toggleBoardMarkers() {
    emit('update:boardMarkersVisible', !props.boardMarkersVisible);
}
</script>

<template>
    <section class="funnel-board-comments">
        <div class="funnel-board-comments__head">
            <h3 class="funnel-board-comments__heading">Комментарии</h3>
            <button
                v-if="canManage"
                type="button"
                class="f5-btn f5-btn--ghost f5-btn--sm"
                @click="toggleBoardMarkers"
            >
                {{ boardMarkersVisible ? 'Скрыть на доске' : 'Показать на доске' }}
            </button>
        </div>

        <div class="funnel-board-comments__tabs">
            <button
                type="button"
                class="funnel-board-comments__tab"
                :class="{ 'is-active': scope === 'board' }"
                @click="scope = 'board'"
            >
                К доске
                <span v-if="boardCount" class="funnel-board-comments__count">{{ boardCount }}</span>
            </button>
            <button
                type="button"
                class="funnel-board-comments__tab"
                :class="{ 'is-active': scope === 'stage' }"
                :disabled="!activeStage"
                @click="activeStage && (scope = 'stage')"
            >
                {{ activeStage ? `К этапу ${activeStage.number}` : 'К этапу' }}
                <span v-if="stageCount" class="funnel-board-comments__count">{{ stageCount }}</span>
            </button>
        </div>

        <p v-if="scope === 'stage' && !activeStage" class="funnel-canvas-hint">
            Выберите этап на доске, чтобы оставить комментарий к нему.
        </p>

        <ul v-if="visibleComments.length" class="funnel-board-comments__list">
            <li
                v-for="comment in visibleComments"
                :key="comment.id"
                class="funnel-board-comments__item"
            >
                <div class="funnel-board-comments__meta">
                    <strong>{{ comment.author_name }}</strong>
                    <span>{{ comment.created_at }}</span>
                </div>
                <p class="funnel-board-comments__body">{{ comment.body }}</p>
                <div v-if="canManage && destroyBaseUrl" class="funnel-board-comments__actions">
                    <button
                        type="button"
                        class="funnel-board-comments__delete"
                        :disabled="deletingId === comment.id"
                        @click="destroyComment(comment)"
                    >
                        {{ deletingId === comment.id ? 'Удаление…' : 'Удалить' }}
                    </button>
                </div>
            </li>
        </ul>
        <p v-else class="funnel-canvas-hint">
            {{ scope === 'board' ? 'Комментариев к доске пока нет.' : 'Комментариев к этому этапу пока нет.' }}
        </p>

        <form
            v-if="canPost && storeUrl && (scope === 'board' || activeStage)"
            class="funnel-board-comments__form"
            @submit.prevent="submit"
        >
            <p class="funnel-board-comments__form-title">
                {{ scope === 'board' ? 'Комментарий к доске' : `Комментарий к этапу «${activeStage.name}»` }}
            </p>
            <div>
                <label class="f5-label" for="board-comment-name">Имя</label>
                <input
                    id="board-comment-name"
                    v-model="form.author_name"
                    type="text"
                    class="f5-input"
                    :class="{ 'f5-input--error': form.errors.author_name }"
                    maxlength="120"
                    required
                    placeholder="Как к вам обращаться"
                >
                <p v-if="form.errors.author_name" class="f5-error">{{ form.errors.author_name }}</p>
            </div>
            <div>
                <label class="f5-label" for="board-comment-body">Комментарий</label>
                <textarea
                    id="board-comment-body"
                    v-model="form.body"
                    class="f5-input min-h-[88px] resize-y"
                    :class="{ 'f5-input--error': form.errors.body }"
                    rows="3"
                    maxlength="3000"
                    required
                    placeholder="Вопрос или замечание по схеме…"
                />
                <p v-if="form.errors.body" class="f5-error">{{ form.errors.body }}</p>
            </div>
            <button
                type="submit"
                class="f5-btn f5-btn--primary f5-btn--sm w-full"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Отправка…' : 'Отправить' }}
            </button>
        </form>
    </section>
</template>
