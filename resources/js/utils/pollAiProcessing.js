import { getJson } from './fetchJson';

const DEFAULT_INTERVAL_MS = 3000;
const DEFAULT_MAX_ATTEMPTS = 600;

/**
 * @param {object} project
 * @param {number} assistantMessageId
 * @param {{
 *   intervalMs?: number,
 *   maxAttempts?: number,
 *   onProgress?: (message: object|null, attempt: number) => void,
 *   shouldContinue?: () => boolean,
 * }} [options]
 */
export async function pollAiProcessingMessage(project, assistantMessageId, options = {}) {
    const {
        intervalMs = DEFAULT_INTERVAL_MS,
        maxAttempts = DEFAULT_MAX_ATTEMPTS,
        onProgress,
        shouldContinue = () => true,
    } = options;

    const messagesUrl = project?.urls?.ai_messages;

    if (!messagesUrl || !assistantMessageId) {
        return { status: 'error', message: 'Не удалось отследить статус обработки.' };
    }

    for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
        if (!shouldContinue()) {
            return { status: 'cancelled', message: 'Запрос отменён.' };
        }

        if (attempt > 0) {
            await new Promise((resolve) => setTimeout(resolve, intervalMs));
        }

        if (!shouldContinue()) {
            return { status: 'cancelled', message: 'Запрос отменён.' };
        }

        const { response, data } = await getJson(messagesUrl);

        if (!response.ok || !data.success) {
            return {
                status: 'error',
                message: data.message || 'Не удалось проверить статус обработки.',
            };
        }

        const assistantMessage = (data.messages ?? []).find((item) => item.id === assistantMessageId);

        if (typeof onProgress === 'function') {
            onProgress(assistantMessage ?? null, attempt + 1);
        }

        if (!assistantMessage) {
            continue;
        }

        if (assistantMessage.status === 'completed') {
            return { status: 'completed', message: assistantMessage };
        }

        if (assistantMessage.status === 'cancelled') {
            return {
                status: 'cancelled',
                message: assistantMessage.error_message || 'Запрос отменён.',
                assistantMessage,
            };
        }

        if (assistantMessage.status === 'failed') {
            return {
                status: 'failed',
                message: assistantMessage.error_message || 'ИИ не смог обработать запрос.',
                assistantMessage,
            };
        }
    }

    return {
        status: 'timeout',
        message: 'ИИ всё ещё обрабатывает запрос. Откройте чат и проверьте результат позже.',
    };
}
