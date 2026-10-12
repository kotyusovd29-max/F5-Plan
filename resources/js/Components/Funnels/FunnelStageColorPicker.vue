<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    palette: {
        type: Array,
        default: () => [],
    },
    inputId: {
        type: String,
        default: 'stage-color',
    },
});

const emit = defineEmits(['update:modelValue']);

const normalizedValue = computed(() => props.modelValue.toLowerCase());

function selectColor(color) {
    emit('update:modelValue', color);
}

function onCustomInput(event) {
    emit('update:modelValue', event.target.value);
}
</script>

<template>
    <div class="stage-color-picker">
        <span class="f5-label">Цвет полосы этапа</span>
        <div class="stage-color-picker__swatches" role="listbox" aria-label="Выбор цвета этапа">
            <button
                v-for="paletteColor in palette"
                :key="paletteColor"
                type="button"
                class="stage-color-picker__swatch"
                :class="{ 'is-selected': normalizedValue === paletteColor.toLowerCase() }"
                :style="{ backgroundColor: paletteColor }"
                :title="paletteColor"
                :aria-label="`Цвет ${paletteColor}`"
                @click="selectColor(paletteColor)"
            />

            <label class="stage-color-picker__custom" title="Свой цвет">
                <input
                    :id="inputId"
                    type="color"
                    :value="modelValue"
                    @input="onCustomInput"
                >
                <span class="stage-color-picker__custom-icon" aria-hidden="true">+</span>
            </label>
        </div>
    </div>
</template>
