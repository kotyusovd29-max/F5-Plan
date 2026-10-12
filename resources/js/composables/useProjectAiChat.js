import { computed, nextTick, ref, unref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { hasPendingChanges } from '../utils/aiChat';
import { cancelAiProcessingMessage } from '../utils/cancelAiProcessing';
import { confirmDialog } from '../utils/confirmDialog';
import { getJson, postFormData, postJson } from '../utils/fetchJson';

export function useProjectAiChat(projectSource, options = {}) {
    const {
        initialMessages = [],
        thread = 'project',
        onApplied,
    } = options;

    const project = computed(() => unref(projectSource));
    const chatThread = ref(thread);

    const messages = ref([...initialMessages]);
    const draft = ref('');
    const sendMode = ref(null);
    const loading = ref(false);
    const loadingText = ref('ИИ обрабатывает запрос…');
    const status = ref('');
    const statusType = ref('');
    const applyingId = ref(null);
    const rollingBackId = ref(null);
    const cancelling = ref(false);
    const messagesEl = ref(null);
    const messagesLoaded = ref(initialMessages.length > 0);
    const pollingMessageId = ref(null);
    const pollGeneration = ref(0);
    const pendingAttachments = ref([]);
    const attaching = ref(false);

    const MAX_ATTACHMENTS = 5;

    const isMeetingThread = computed(() => chatThread.value === 'meeting');
    const isAuditThread = computed(() => chatThread.value === 'audit_recommendations');
    const isKpThread = computed(() => chatThread.value === 'kp_generation');
    const isConsultationThread = computed(() => isMeetingThread.value || isAuditThread.value || isKpThread.value);
    const canSendMessage = computed(() => (
        (draft.value.trim().length >= 2 || pendingAttachments.value.length > 0) && !loading.value && !attaching.value
    ));

    const processingMessage = computed(() => messages.value.find(
        (item) => item.role === 'assistant' && item.status === 'processing',
    ));

    const pendingMessage = computed(() => {
        if (isConsultationThread.value) {
            return null;
        }

        return [...messages.value].reverse().find((message) => message.role === 'assistant'
            && (message.has_pending_changes || hasPendingChanges(message.changes)));
    });

    function resetConversation() {
        messages.value = [];
        draft.value = '';
        sendMode.value = null;
        status.value = '';
        statusType.value = '';
        applyingId.value = null;
        rollingBackId.value = null;
        cancelling.value = false;
        messagesLoaded.value = false;
        pollingMessageId.value = null;
        pollGeneration.value += 1;
        pendingAttachments.value = [];
        attaching.value = false;
    }

    function clearAttachment() {
        pendingAttachments.value = [];
    }

    function removeAttachment(index) {
        pendingAttachments.value = pendingAttachments.value.filter((_, i) => i !== index);
    }

    async function attachFile(file) {
        const currentProject = project.value;

        if (!currentProject?.urls?.brief_documents_store) {
            status.value = 'Загрузка файлов недоступна для этого проекта.';
            statusType.value = 'error';
            return;
        }

        if (!file) {
            return;
        }

        if (pendingAttachments.value.length >= MAX_ATTACHMENTS) {
            status.value = `Можно прикрепить не больше ${MAX_ATTACHMENTS} файлов.`;
            statusType.value = 'error';
            return;
        }

        attaching.value = true;
        status.value = '';
        statusType.value = '';

        try {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('kind', 'chat');

            const { response, data } = await postFormData(
                currentProject.urls.brief_documents_store,
                formData,
            );

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Не удалось загрузить файл.');
            }

            pendingAttachments.value = [...pendingAttachments.value, {
                id: data.document.id,
                original_name: data.document.original_name,
                size_label: data.document.size_label,
                is_image: !!data.document.is_image,
                mime_type: data.document.mime_type ?? null,
            }];

            if (data.message) {
                status.value = data.message;
                statusType.value = 'success';
            }
        } catch (error) {
            status.value = error.message || 'Не удалось загрузить файл.';
            statusType.value = 'error';
        } finally {
            attaching.value = false;
        }
    }

    watch(() => project.value?.id, (id, previousId) => {
        if (id === previousId) {
            return;
        }

        resetConversation();
    });

    function messagesUrl() {
        const currentProject = project.value;

        if (!currentProject?.urls?.ai_messages) {
            return '';
        }

        const url = new URL(currentProject.urls.ai_messages, window.location.origin);
        url.searchParams.set('thread', chatThread.value);

        return `${url.pathname}${url.search}`;
    }

    function applyUrl(message) {
        const currentProject = project.value;

        if (!currentProject) {
            return '';
        }

        return message.apply_url
            ?? currentProject.urls.ai_apply_template.replace('__MESSAGE__', String(message.id));
    }

    function sendUrl() {
        const currentProject = project.value;

        if (!currentProject) {
            return '';
        }

        return currentProject.urls.ai_send ?? currentProject.urls.send;
    }

    function scrollToBottom() {
        nextTick(() => {
            if (messagesEl.value) {
                messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
            }
        });
    }

    function stopPolling() {
        pollGeneration.value += 1;
        pollingMessageId.value = null;
    }

    function updateAssistantMessage(assistantMessage) {
        if (!assistantMessage?.id) {
            return;
        }

        const index = messages.value.findIndex((item) => item.id === assistantMessage.id);

        if (index === -1) {
            return;
        }

        messages.value[index] = {
            ...messages.value[index],
            ...assistantMessage,
        };
    }

    async function loadMessages(force = false) {
        const url = messagesUrl();

        if (!url) {
            scrollToBottom();
            return false;
        }

        if (!force && messagesLoaded.value) {
            scrollToBottom();
            return true;
        }

        try {
            const { response, data } = await getJson(url);

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Не удалось загрузить историю чата.');
            }

            messages.value = data.messages ?? [];
            messagesLoaded.value = true;
            scrollToBottom();

            const activeProcessingMessage = messages.value.find(
                (item) => item.role === 'assistant' && item.status === 'processing',
            );

            if (activeProcessingMessage && pollingMessageId.value !== activeProcessingMessage.id) {
                void pollForAssistantResponse(activeProcessingMessage.id);
            }

            return true;
        } catch (error) {
            status.value = error.message || 'Ошибка загрузки чата.';
            statusType.value = 'error';

            return false;
        }
    }

    async function pollForAssistantResponse(assistantMessageId) {
        if (pollingMessageId.value === assistantMessageId) {
            return;
        }

        const generation = pollGeneration.value + 1;
        pollGeneration.value = generation;
        pollingMessageId.value = assistantMessageId;
        const maxAttempts = 600;

        try {
            for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
                if (pollGeneration.value !== generation) {
                    return;
                }

                await new Promise((resolve) => setTimeout(resolve, 3000));

                if (pollGeneration.value !== generation) {
                    return;
                }

                const loaded = await loadMessages(true);

                if (!loaded || pollGeneration.value !== generation) {
                    return;
                }

                const assistantMessage = messages.value.find((item) => item.id === assistantMessageId);

                if (!assistantMessage || assistantMessage.status !== 'processing') {
                    if (assistantMessage?.status === 'failed') {
                        status.value = assistantMessage.error_message || 'ИИ не смог обработать запрос.';
                        statusType.value = 'error';
                    } else if (assistantMessage?.status === 'cancelled') {
                        status.value = assistantMessage.error_message || 'Запрос отменён.';
                        statusType.value = '';
                    }

                    return;
                }
            }

            status.value = 'ИИ всё ещё обрабатывает запрос. Обновите страницу через минуту.';
            statusType.value = 'error';
        } finally {
            if (pollGeneration.value === generation) {
                pollingMessageId.value = null;
            }
        }
    }

    async function cancelCurrentRequest() {
        const currentProject = project.value;
        const message = processingMessage.value;

        if (!currentProject || !message?.id || cancelling.value) {
            return;
        }

        cancelling.value = true;
        stopPolling();
        loading.value = false;

        try {
            const data = await cancelAiProcessingMessage(currentProject, message.id);

            if (data.assistant_message) {
                updateAssistantMessage(data.assistant_message);
            } else {
                await loadMessages(true);
            }

            status.value = data.message || 'Запрос отменён.';
            statusType.value = '';
            scrollToBottom();
        } catch (error) {
            status.value = error.message || 'Не удалось отменить запрос.';
            statusType.value = 'error';
        } finally {
            cancelling.value = false;
        }
    }

    function useSuggestion(item) {
        draft.value = item.message;
        sendMode.value = null;
    }

    function useAction(item) {
        draft.value = item.message;
        sendMode.value = item.mode ?? null;
    }

    function setBootstrap({ prompt, mode, autostart = false } = {}) {
        if (prompt) {
            draft.value = prompt;
        }

        if (mode) {
            sendMode.value = mode;
        }

        if (autostart && draft.value.trim().length >= 2) {
            return sendMessage();
        }

        return Promise.resolve();
    }

    async function sendMessage() {
        const currentProject = project.value;

        if (!currentProject) {
            status.value = 'Сначала выберите проект.';
            statusType.value = 'error';
            return;
        }

        const message = draft.value.trim();
        const attachmentIds = pendingAttachments.value.map((item) => item.id);

        if (message.length < 2 && attachmentIds.length === 0) {
            status.value = 'Введите сообщение или прикрепите файл.';
            statusType.value = 'error';
            return;
        }

        const mode = isConsultationThread.value ? null : sendMode.value;
        const isLargeBrief = !isConsultationThread.value && !mode && message.length >= 1500;
        loading.value = true;
        loadingText.value = isKpThread.value
            ? 'Собираю презентацию КП в Google Slides…'
            : isMeetingThread.value
                ? 'Готовлю структуру встречи…'
            : isAuditThread.value
                ? 'Готовлю рекомендации по аудиту…'
                : mode === 'audit'
                    ? 'Провожу аудит плана…'
                    : mode === 'benchmark_b2b'
                        ? 'Сравниваю с эталоном B2B…'
                        : isLargeBrief
                            ? 'Готовлю кейс внедрения и настройки проекта (этап 1/2)…'
                            : attachmentIds.length > 0
                                ? 'Читаю документы и готовлю ответ…'
                                : 'ИИ обрабатывает запрос…';
        status.value = '';
        statusType.value = '';

        try {
            const payload = {
                message: message.length >= 2
                    ? message
                    : 'Проанализируй прикреплённый документ и ответь по делу.',
                mode,
                thread: chatThread.value,
            };

            if (attachmentIds.length > 0) {
                payload.brief_document_ids = attachmentIds;
            }

            const { response, data } = await postJson(sendUrl(), payload);

            if (response.status === 409 && data?.needs_google_connect) {
                const goConnect = await confirmDialog({
                    title: 'Нужен Google-аккаунт',
                    message: data.message
                        || 'Подключите Google в личном кабинете, чтобы ИИ собирал презентации в Google Slides.',
                    confirmLabel: 'Подключить Google',
                    cancelLabel: 'Отмена',
                    variant: 'primary',
                });

                if (goConnect) {
                    window.location.href = data.connect_url || '/profile';
                }

                return;
            }

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Не удалось отправить сообщение.');
            }

            messages.value.push(...data.messages);
            draft.value = '';
            sendMode.value = null;
            pendingAttachments.value = [];
            messagesLoaded.value = true;
            scrollToBottom();

            if (data.processing) {
                const assistantMessage = data.messages?.find((item) => item.role === 'assistant' && item.status === 'processing')
                    ?? data.messages?.[data.messages.length - 1];

                if (assistantMessage?.id) {
                    loading.value = false;
                    await pollForAssistantResponse(assistantMessage.id);
                }
            }
        } catch (error) {
            status.value = error.message || 'Ошибка при отправке.';
            statusType.value = 'error';
        } finally {
            loading.value = false;
        }
    }

    function rollbackUrl(message) {
        if (message?.rollback_url) {
            return message.rollback_url;
        }

        const template = project.value?.urls?.ai_rollback_template;

        if (!template || !message?.id) {
            return '';
        }

        return template.replace('__MESSAGE__', String(message.id));
    }

    async function applyChanges(message) {
        const currentProject = project.value;

        if (!currentProject) {
            return;
        }

        applyingId.value = message.id;
        status.value = '';
        statusType.value = '';

        try {
            const { response, data } = await postJson(applyUrl(message));

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Не удалось применить изменения.');
            }

            if (data.assistant_message) {
                updateAssistantMessage(data.assistant_message);
            }

            status.value = data.message || 'Изменения применены.';
            statusType.value = 'success';

            if (typeof onApplied === 'function') {
                await onApplied(data);
            } else {
                router.visit(data.redirect || currentProject.urls.ai_chat, {
                    preserveScroll: true,
                });
            }
        } catch (error) {
            status.value = error.message || 'Ошибка при применении.';
            statusType.value = 'error';
        } finally {
            applyingId.value = null;
        }
    }

    async function rollbackChanges(message) {
        const currentProject = project.value;
        const url = rollbackUrl(message);

        if (!currentProject || !url) {
            return;
        }

        rollingBackId.value = message.id;
        status.value = '';
        statusType.value = '';

        try {
            const { response, data } = await postJson(url);

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Не удалось откатить изменения.');
            }

            if (data.assistant_message) {
                updateAssistantMessage(data.assistant_message);
            }

            status.value = data.message || 'Изменения откатены.';
            statusType.value = 'success';

            if (typeof onApplied === 'function') {
                await onApplied(data);
            } else {
                router.visit(data.redirect || currentProject.urls.ai_chat, {
                    preserveScroll: true,
                });
            }
        } catch (error) {
            status.value = error.message || 'Ошибка при откате.';
            statusType.value = 'error';
        } finally {
            rollingBackId.value = null;
        }
    }

    return {
        thread: chatThread,
        messages,
        draft,
        sendMode,
        loading,
        loadingText,
        status,
        statusType,
        applyingId,
        rollingBackId,
        cancelling,
        processingMessage,
        messagesEl,
        pendingMessage,
        pendingAttachments,
        attaching,
        canSendMessage,
        isMeetingThread,
        isAuditThread,
        isKpThread,
        isConsultationThread,
        applyUrl,
        rollbackUrl,
        loadMessages,
        useSuggestion,
        useAction,
        setBootstrap,
        attachFile,
        clearAttachment,
        removeAttachment,
        sendMessage,
        applyChanges,
        rollbackChanges,
        cancelCurrentRequest,
        scrollToBottom,
        resetConversation,
        pollForAssistantResponse,
    };
}
