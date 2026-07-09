const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function nl2br(value) {
    return escapeHtml(value).replace(/\n/g, '<br>');
}

function setStatus(root, message, type = '') {
    const status = root.querySelector('[data-ai-chat-status]');

    if (!status) {
        return;
    }

    status.textContent = message;
    status.classList.remove('is-hidden', 'ai-chat__status--error');

    if (!message) {
        status.classList.add('is-hidden');
        return;
    }

    if (type === 'error') {
        status.classList.add('ai-chat__status--error');
    }
}

function renderChangesPreview(changes) {
    if (!changes || typeof changes !== 'object') {
        return '';
    }

    const blocks = [];
    const entityLabels = { deals: 'Сделки', contacts: 'Контакты', companies: 'Компании' };

    if (changes.project?.fields) {
        const items = Object.entries(changes.project.fields).map(([key, value]) => `<li>${escapeHtml(key)}: ${escapeHtml(value)}</li>`).join('');
        blocks.push(`<div class="ai-changes-preview__block"><h4 class="ai-changes-preview__title">Основная информация</h4><ul class="ai-changes-preview__list">${items}</ul></div>`);
    }

    const sectionTitles = {
        skk_fields: 'Поля',
        integrations: 'Интеграции',
        funnels: 'Воронки',
        stages: 'Этапы',
    };

    Object.entries(sectionTitles).forEach(([key, title]) => {
        const section = changes[key];

        if (!section) {
            return;
        }

        const items = [];

        ['create', 'update', 'delete'].forEach((action) => {
            const rows = section[action] ?? [];
            const label = action === 'create' ? 'Создать' : action === 'update' ? 'Изменить' : 'Удалить';

            if (action === 'delete') {
                rows.forEach((id) => items.push(`<li>${label}: #${escapeHtml(id)}</li>`));
                return;
            }

            rows.forEach((row) => {
                let text = label;

                if (row.id) {
                    text += ` #${row.id}`;
                }

                if (key === 'skk_fields') {
                    text += ` (${entityLabels[row.entity_type] || row.entity_type || ''})`;
                }

                if (key === 'stages' && row.funnel_id) {
                    text += ` воронка #${row.funnel_id}`;
                }

                if (key === 'funnels' && Array.isArray(row.stages) && row.stages.length > 0) {
                    text += `: ${row.name || ''} (${row.stages.length} этапов)`;
                } else if (row.name) {
                    text += `: ${row.name}`;
                }

                items.push(`<li>${escapeHtml(text)}</li>`);
            });
        });

        if (items.length > 0) {
            blocks.push(`<div class="ai-changes-preview__block"><h4 class="ai-changes-preview__title">${title}</h4><ul class="ai-changes-preview__list">${items.join('')}</ul></div>`);
        }
    });

    if (blocks.length === 0) {
        return '';
    }

    return `<div class="ai-changes-preview">${blocks.join('')}</div>`;
}

function hasPendingChanges(changes) {
    if (!changes) {
        return false;
    }

    if (changes.project?.fields && Object.keys(changes.project.fields).length > 0) {
        return true;
    }

    return ['skk_fields', 'integrations', 'funnels', 'stages'].some((key) => {
        const section = changes[key];

        if (!section) {
            return false;
        }

        return ['create', 'update', 'delete'].some((action) => (section[action] ?? []).length > 0);
    });
}

function renderMessage(message, applyUrlTemplate) {
    const isUser = message.role === 'user';
    const roleLabel = isUser ? 'Вы' : 'ИИ';
    let changesHtml = '';

    if (!isUser && message.changes) {
        const preview = renderChangesPreview(message.changes);

        if (preview) {
            changesHtml += `<div class="ai-chat-message__changes">${preview}`;

            if (message.has_pending_changes || hasPendingChanges(message.changes)) {
                const applyUrl = applyUrlTemplate.replace('__MESSAGE__', message.id);
                changesHtml += `<button type="button" class="btn btn--primary btn--sm ai-chat-message__apply" data-ai-apply-message="${message.id}" data-apply-url="${escapeHtml(applyUrl)}">Применить изменения</button>`;
            }

            changesHtml += '</div>';
        }
    }

    return `
        <article class="ai-chat-message ai-chat-message--${message.role}" data-ai-message-id="${message.id}">
            <header class="ai-chat-message__meta">
                <span class="ai-chat-message__role">${roleLabel}</span>
                <time class="ai-chat-message__time">${escapeHtml(message.created_at || '')}</time>
            </header>
            <div class="ai-chat-message__content">${nl2br(message.content)}</div>
            ${changesHtml}
        </article>
    `;
}

function scrollToBottom(container) {
    container.scrollTop = container.scrollHeight;
}

function updateApplyBar(root, message, applyUrlTemplate) {
    let bar = root.querySelector('[data-ai-apply-bar]');

    if (!message || (!message.has_pending_changes && !hasPendingChanges(message.changes))) {
        bar?.remove();
        return;
    }

    const applyUrl = applyUrlTemplate.replace('__MESSAGE__', message.id);

    if (!bar) {
        root.insertAdjacentHTML('beforeend', `
            <div class="ai-chat__apply-bar" data-ai-apply-bar>
                <p class="ai-chat__apply-bar-text">Настройки проекта готовы к загрузке</p>
                <button
                    type="button"
                    class="btn btn--primary ai-chat__apply-bar-btn"
                    data-ai-apply-message="${message.id}"
                    data-apply-url="${escapeHtml(applyUrl)}"
                >Применить изменения</button>
            </div>
        `);
        return;
    }

    const button = bar.querySelector('[data-ai-apply-message]');
    button?.setAttribute('data-ai-apply-message', String(message.id));
    button?.setAttribute('data-apply-url', applyUrl);
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('project-ai-chat');

    if (!root) {
        return;
    }

    const messagesContainer = root.querySelector('[data-ai-chat-messages]');
    const form = root.querySelector('[data-ai-chat-form]');
    const sendButton = root.querySelector('[data-ai-chat-send]');
    const loadingPanel = root.querySelector('[data-ai-chat-loading]');
    const loadingText = root.querySelector('[data-ai-chat-loading-text]');
    const applyUrlTemplate = root.dataset.applyUrlTemplate || '';

    function setLoading(isLoading, text = 'ИИ обрабатывает запрос…') {
        form?.classList.toggle('is-hidden', isLoading);
        loadingPanel?.classList.toggle('is-hidden', !isLoading);

        if (loadingText) {
            loadingText.textContent = text;
        }

        if (sendButton) {
            sendButton.disabled = isLoading;
        }
    }

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const textarea = form.querySelector('textarea[name="message"]');
        const message = textarea?.value.trim() || '';

        if (message.length < 2) {
            setStatus(root, 'Введите сообщение для ИИ.', 'error');
            return;
        }

        const isLargeBrief = message.length >= 1500;
        setLoading(true, isLargeBrief
            ? 'Готовлю кейс внедрения и настройки проекта (этап 1/2)…'
            : 'ИИ обрабатывает запрос…');
        setStatus(root, '');

        try {
            const response = await fetch(root.dataset.sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ message }),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Не удалось отправить сообщение.');
            }

            root.querySelector('[data-ai-chat-empty]')?.remove();

            data.messages.forEach((item) => {
                messagesContainer.insertAdjacentHTML('beforeend', renderMessage(item, applyUrlTemplate));
            });

            if (data.messages.some((item) => item.has_pending_changes || hasPendingChanges(item.changes))) {
                const assistant = [...data.messages].reverse().find((item) => item.role === 'assistant');
                if (assistant) {
                    updateApplyBar(root, assistant, applyUrlTemplate);
                }
            }

            textarea.value = '';
            scrollToBottom(messagesContainer);
        } catch (error) {
            setStatus(root, error.message || 'Ошибка при отправке.', 'error');
        } finally {
            setLoading(false);
            textarea?.focus();
        }
    });

    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-ai-apply-message]');

        if (!button) {
            return;
        }

        const messageId = button.getAttribute('data-ai-apply-message');
        const applyUrl = button.getAttribute('data-apply-url')
            || applyUrlTemplate.replace('__MESSAGE__', messageId);

        button.disabled = true;
        setStatus(root, '');

        try {
            const response = await fetch(applyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({}),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Не удалось применить изменения.');
            }

            window.location.href = data.redirect || window.location.href;
        } catch (error) {
            setStatus(root, error.message || 'Ошибка при применении.', 'error');
            button.disabled = false;
        }
    });
});
