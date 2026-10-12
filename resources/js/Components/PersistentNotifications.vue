<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { getJson, postJson } from '../utils/fetchJson';

const page = usePage();
const items = ref([]);

const isPublicBoard = computed(() => (
    String(page.component || '').startsWith('Funnels/PublicBoard')
));

const canShowNotifications = computed(() => (
    !!page.props.auth?.user && !isPublicBoard.value
));

function replaceWithServerList(list) {
    items.value = Array.isArray(list) ? [...list] : [];
}

watch(
    () => [page.props.notifications, canShowNotifications.value],
    () => {
        if (!canShowNotifications.value) {
            items.value = [];
            return;
        }

        replaceWithServerList(page.props.notifications);
    },
    { deep: true, immediate: true },
);

async function poll() {
    if (!canShowNotifications.value) {
        items.value = [];
        return;
    }

    try {
        const { response, data } = await getJson('/notifications');

        if (response.ok && Array.isArray(data?.notifications)) {
            replaceWithServerList(data.notifications);
        }
    } catch {
        // ignore
    }
}

async function dismiss(item) {
    items.value = items.value.filter((entry) => entry.id !== item.id);

    try {
        await postJson(item.dismiss_url || `/notifications/${item.id}/dismiss`, {});
    } catch {
        // keep closed locally
    }
}

let timer = null;

onMounted(() => {
    timer = window.setInterval(poll, 15000);
});

onUnmounted(() => {
    if (timer) {
        window.clearInterval(timer);
    }
});
</script>

<template>
    <div
        class="pointer-events-none fixed top-5 right-5 z-[210] flex w-full max-w-sm flex-col gap-3"
        aria-live="polite"
    >
        <TransitionGroup name="f5-toast">
            <div
                v-for="item in items"
                :key="item.id"
                class="f5-toast pointer-events-auto"
                :class="{
                    'f5-toast--error': item.type === 'ai_response_failed',
                    'f5-toast--comment': item.type === 'board_comment',
                    'f5-toast--success': item.type !== 'ai_response_failed' && item.type !== 'board_comment',
                }"
                role="status"
            >
                <span class="f5-toast__icon" aria-hidden="true">
                    <svg
                        v-if="item.type === 'ai_response_failed'"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg
                        v-else-if="item.type === 'board_comment'"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8M8 14h5m9-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg
                        v-else
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </span>

                <div class="f5-toast__message min-w-0">
                    <p class="font-semibold">{{ item.title }}</p>
                    <p v-if="item.body" class="mt-0.5 opacity-80">
                        {{ item.body }}
                    </p>
                    <a
                        v-if="item.url"
                        :href="item.url"
                        class="mt-2 inline-block text-xs font-semibold text-f5-accent no-underline hover:underline"
                    >
                        Открыть
                    </a>
                </div>

                <button
                    type="button"
                    class="f5-toast__close"
                    aria-label="Закрыть"
                    @click="dismiss(item)"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
