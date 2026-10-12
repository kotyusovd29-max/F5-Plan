<script setup>
import { computed } from 'vue';

const STAGE_W = 200;

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
    /** board — панель вопросов к воронке; stages — заметки у этапов */
    mode: {
        type: String,
        default: 'all',
        validator: (value) => ['all', 'board', 'stages'].includes(value),
    },
});

const emit = defineEmits(['select-stage', 'select-board']);

const stageById = computed(() => {
    const map = new Map();
    (props.stages ?? []).forEach((stage) => map.set(Number(stage.id), stage));
    return map;
});

const showBoard = computed(() => props.mode === 'all' || props.mode === 'board');
const showStages = computed(() => props.mode === 'all' || props.mode === 'stages');

const boardNotes = computed(() => (
    (props.comments ?? [])
        .filter((row) => row.funnel_stage_id == null)
        .slice(0, 8)
));

const stageNotes = computed(() => {
    if (!showStages.value) return [];

    const groups = new Map();

    (props.comments ?? [])
        .filter((row) => row.funnel_stage_id != null)
        .forEach((comment) => {
            const stageId = Number(comment.funnel_stage_id);
            if (!groups.has(stageId)) {
                groups.set(stageId, []);
            }
            const list = groups.get(stageId);
            if (list.length < 3) {
                list.push(comment);
            }
        });

    return [...groups.entries()]
        .map(([stageId, items]) => {
            const stage = stageById.value.get(stageId);
            if (!stage) return null;

            return {
                stageId,
                stage,
                items,
                left: Number(stage.canvas_pos_x) + STAGE_W + 10,
                top: Number(stage.canvas_pos_y),
                isActive: props.activeStageId != null && Number(props.activeStageId) === stageId,
            };
        })
        .filter(Boolean);
});

function truncate(text, max = 90) {
    const value = String(text || '').trim();
    if (value.length <= max) return value;
    return `${value.slice(0, max - 1)}…`;
}
</script>

<template>
    <aside
        v-if="showBoard && boardNotes.length"
        class="funnel-board-questions"
        :class="{ 'is-active': activeStageId == null }"
        aria-label="Вопросы к воронке"
    >
        <button
            type="button"
            class="funnel-board-questions__header"
            @mousedown.stop
            @click.stop="emit('select-board')"
        >
            <span class="funnel-board-questions__title">Вопросы к воронке</span>
            <span class="funnel-board-questions__count">{{ boardNotes.length }}</span>
        </button>
        <div class="funnel-board-questions__list">
            <article
                v-for="comment in boardNotes"
                :key="`board-${comment.id}`"
                class="funnel-comment-note"
                @mousedown.stop
                @click.stop="emit('select-board')"
            >
                <header class="funnel-comment-note__meta">
                    <strong>{{ comment.author_name }}</strong>
                    <span>{{ comment.created_at }}</span>
                </header>
                <p class="funnel-comment-note__body">{{ truncate(comment.body) }}</p>
            </article>
        </div>
    </aside>

    <div
        v-if="showStages && stageNotes.length"
        class="funnel-comment-markers"
        aria-label="Комментарии к этапам"
    >
        <div
            v-for="group in stageNotes"
            :key="`stage-notes-${group.stageId}`"
            class="funnel-comment-stack funnel-comment-stack--stage"
            :class="{ 'is-active': group.isActive }"
            :style="{ left: `${group.left}px`, top: `${group.top}px` }"
        >
            <button
                type="button"
                class="funnel-comment-stack__label"
                @mousedown.stop
                @click.stop="emit('select-stage', group.stageId)"
            >
                Этап {{ group.stage.number }} · {{ group.items.length }}
            </button>
            <article
                v-for="comment in group.items"
                :key="`stage-${comment.id}`"
                class="funnel-comment-note"
                @mousedown.stop
                @click.stop="emit('select-stage', group.stageId)"
            >
                <header class="funnel-comment-note__meta">
                    <strong>{{ comment.author_name }}</strong>
                    <span>{{ comment.created_at }}</span>
                </header>
                <p class="funnel-comment-note__body">{{ truncate(comment.body) }}</p>
            </article>
        </div>
    </div>
</template>
