import { inject } from 'vue';

export const GLOBAL_AI_CHAT_KEY = Symbol('globalAiChat');

export function useGlobalAiChat() {
    return inject(GLOBAL_AI_CHAT_KEY, null);
}
