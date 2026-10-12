<script setup>
import { computed, watch } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    isEdit: {
        type: Boolean,
        default: false,
    },
    isSelf: {
        type: Boolean,
        default: false,
    },
    roleOptions: {
        type: Array,
        default: () => [],
    },
    ropOptions: {
        type: Array,
        default: () => [],
    },
});

const needsRop = computed(() => props.form.role === 'employee');

watch(
    () => props.form.role,
    (role) => {
        if (role !== 'employee') {
            props.form.rop_id = null;
        }
    },
);
</script>

<template>
    <div class="space-y-5">
        <div>
            <label for="name" class="f5-label">Имя</label>
            <input
                id="name"
                v-model="form.name"
                type="text"
                class="f5-input"
                :class="{ 'f5-input--error': form.errors.name }"
                required
            >
            <p v-if="form.errors.name" class="f5-error">{{ form.errors.name }}</p>
        </div>

        <div>
            <label for="email" class="f5-label">Email</label>
            <input
                id="email"
                v-model="form.email"
                type="email"
                class="f5-input"
                :class="{ 'f5-input--error': form.errors.email }"
                required
            >
            <p v-if="form.errors.email" class="f5-error">{{ form.errors.email }}</p>
        </div>

        <div>
            <label for="password" class="f5-label">
                Пароль
                <span v-if="isEdit" class="font-normal text-f5-text-muted dark:text-white/40">
                    (оставьте пустым, если не меняете)
                </span>
            </label>
            <input
                id="password"
                v-model="form.password"
                type="password"
                class="f5-input"
                :class="{ 'f5-input--error': form.errors.password }"
                :required="!isEdit"
                autocomplete="new-password"
            >
            <p v-if="form.errors.password" class="f5-error">{{ form.errors.password }}</p>
        </div>

        <div>
            <label for="password_confirmation" class="f5-label">Подтверждение пароля</label>
            <input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                class="f5-input"
                :required="!isEdit"
                autocomplete="new-password"
            >
        </div>

        <template v-if="!isSelf">
            <div>
                <label for="role" class="f5-label">Роль</label>
                <select
                    id="role"
                    v-model="form.role"
                    class="f5-input"
                    :class="{ 'f5-input--error': form.errors.role }"
                    required
                >
                    <option
                        v-for="option in roleOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <p v-if="form.errors.role" class="f5-error">{{ form.errors.role }}</p>
            </div>

            <div v-if="needsRop">
                <label for="rop_id" class="f5-label">РОП</label>
                <select
                    id="rop_id"
                    v-model="form.rop_id"
                    class="f5-input"
                    :class="{ 'f5-input--error': form.errors.rop_id }"
                    required
                >
                    <option :value="null" disabled>
                        Выберите РОПа
                    </option>
                    <option
                        v-for="rop in ropOptions"
                        :key="rop.id"
                        :value="rop.id"
                    >
                        {{ rop.name }}
                    </option>
                </select>
                <p v-if="form.errors.rop_id" class="f5-error">{{ form.errors.rop_id }}</p>
                <p
                    v-else-if="ropOptions.length === 0"
                    class="mt-1 text-xs text-f5-text-muted dark:text-white/50"
                >
                    Сначала создайте пользователя с ролью РОП.
                </p>
            </div>
        </template>
    </div>
</template>
