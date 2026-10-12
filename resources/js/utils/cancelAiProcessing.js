import { postJson } from './fetchJson';

/**
 * @param {object} project
 * @param {number} assistantMessageId
 */
export async function cancelAiProcessingMessage(project, assistantMessageId) {
    const template = project?.urls?.ai_cancel_template;

    if (!template || !assistantMessageId) {
        throw new Error('Не удалось отменить запрос.');
    }

    const url = template.replace('__MESSAGE__', String(assistantMessageId));
    const { response, data } = await postJson(url);

    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Не удалось отменить запрос.');
    }

    return data;
}
