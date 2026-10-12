<script setup>
import { ref } from 'vue';
import { useSortableList } from '../../composables/useSortableList';

const props = defineProps({
    reorderUrl: {
        type: String,
        required: true,
    },
    entityType: {
        type: String,
        default: '',
    },
    dragDelete: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['deleted']);

const listRef = ref(null);

useSortableList(listRef, {
    reorderUrl: props.reorderUrl,
    entityType: props.entityType || undefined,
    dragDelete: props.dragDelete,
    onDeleted: (id) => emit('deleted', id),
});
</script>

<template>
    <div ref="listRef" class="flex w-full flex-col">
        <slot />
    </div>
</template>
