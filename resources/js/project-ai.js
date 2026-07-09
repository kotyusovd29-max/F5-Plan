const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const entityLabels = {
    deals: 'Сделки',
    contacts: 'Контакты',
    companies: 'Компании',
};

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function pluralize(count, one, few, many) {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return one;
    }

    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
        return few;
    }

    return many;
}

function getSelectedSections(form) {
    return [...form.querySelectorAll('input[name="sections[]"]:checked')].map((input) => input.value);
}

function showStep(panel, step) {
    panel.querySelectorAll('[data-ai-step]').forEach((section) => {
        section.classList.toggle('is-hidden', section.dataset.aiStep !== step);
    });
}

function setStatus(panel, message, type = '') {
    const status = panel.querySelector('[data-ai-status]');

    if (!status) {
        return;
    }

    status.textContent = message;
    status.classList.remove('is-hidden', 'ai-panel__status--error', 'ai-panel__status--success');

    if (message) {
        status.classList.add(type === 'error' ? 'ai-panel__status--error' : 'ai-panel__status--success');
    } else {
        status.classList.add('is-hidden');
    }
}

function renderSummary(summary, sections) {
    const parts = [];

    if (sections.includes('skk_fields') && summary.skk_fields) {
        parts.push(`${summary.skk_fields} ${pluralize(summary.skk_fields, 'поле', 'поля', 'полей')}`);
    }

    if (sections.includes('integrations') && summary.integrations) {
        parts.push(`${summary.integrations} ${pluralize(summary.integrations, 'интеграция', 'интеграции', 'интеграций')}`);
    }

    if (sections.includes('funnels') && summary.funnels) {
        let text = `${summary.funnels} ${pluralize(summary.funnels, 'воронка', 'воронки', 'воронок')}`;

        if (summary.stages) {
            text += `, ${summary.stages} ${pluralize(summary.stages, 'этап', 'этапа', 'этапов')}`;
        }

        parts.push(text);
    }

    if (parts.length === 0) {
        return '<p class="ai-panel__empty">ИИ не предложил элементов для выбранных разделов.</p>';
    }

    return `<p class="ai-panel__summary-text">Будет добавлено: <strong>${escapeHtml(parts.join(', '))}</strong></p>`;
}

function renderPreview(proposal, sections) {
    const blocks = [];

    if (sections.includes('skk_fields')) {
        Object.entries(entityLabels).forEach(([type, label]) => {
            const fields = proposal.skk_fields?.[type] ?? [];

            if (fields.length === 0) {
                return;
            }

            const items = fields.map((field) => `
                <li>
                    <strong>${escapeHtml(field.name)}</strong>
                    ${field.comment ? `<span>${escapeHtml(field.comment)}</span>` : ''}
                </li>
            `).join('');

            blocks.push(`
                <div class="ai-preview-block">
                    <h4 class="ai-preview-block__title">${label}</h4>
                    <ul class="ai-preview-block__list">${items}</ul>
                </div>
            `);
        });
    }

    if (sections.includes('integrations')) {
        const integrations = proposal.integrations ?? [];

        if (integrations.length > 0) {
            const items = integrations.map((item) => `
                <li>
                    <strong>${escapeHtml(item.name)}</strong>
                    ${item.comment ? `<span>${escapeHtml(item.comment)}</span>` : ''}
                </li>
            `).join('');

            blocks.push(`
                <div class="ai-preview-block">
                    <h4 class="ai-preview-block__title">Интеграции</h4>
                    <ul class="ai-preview-block__list">${items}</ul>
                </div>
            `);
        }
    }

    if (sections.includes('funnels')) {
        const funnels = proposal.funnels ?? [];

        funnels.forEach((funnel) => {
            const stages = (funnel.stages ?? []).map((stage) => `
                <li>
                    <strong>${escapeHtml(stage.name)}</strong>
                    ${stage.action ? `<span>${escapeHtml(stage.action)}</span>` : ''}
                </li>
            `).join('');

            blocks.push(`
                <div class="ai-preview-block">
                    <h4 class="ai-preview-block__title">Воронка: ${escapeHtml(funnel.name)}</h4>
                    ${funnel.description ? `<p class="ai-preview-block__desc">${escapeHtml(funnel.description)}</p>` : ''}
                    <ul class="ai-preview-block__list">${stages}</ul>
                </div>
            `);
        });
    }

    if (blocks.length === 0) {
        return '<p class="ai-panel__empty">Нет данных для предпросмотра.</p>';
    }

    return blocks.join('');
}

function openPanel(panel) {
    panel.classList.remove('is-hidden');
    panel.setAttribute('aria-hidden', 'false');
    document.body.classList.add('ai-panel-open');
    setStatus(panel, '');
    showStep(panel, 'form');
}

function closePanel(panel) {
    panel.classList.add('is-hidden');
    panel.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('ai-panel-open');
}

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('project-ai-panel');
    const openButton = document.querySelector('[data-open-ai-panel]');

    if (!panel || !openButton) {
        return;
    }

    const form = panel.querySelector('#project-ai-form');
    const generateButton = panel.querySelector('[data-ai-generate]');
    const applyButton = panel.querySelector('[data-ai-apply]');
    const summaryNode = panel.querySelector('[data-ai-summary]');
    const previewNode = panel.querySelector('[data-ai-preview]');

    let currentProposal = null;
    let currentSections = [];

    openButton.addEventListener('click', () => openPanel(panel));

    panel.querySelectorAll('[data-ai-close]').forEach((trigger) => {
        trigger.addEventListener('click', () => closePanel(panel));
    });

    panel.querySelector('[data-ai-back]')?.addEventListener('click', () => {
        setStatus(panel, '');
        showStep(panel, 'form');
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        setStatus(panel, '');

        const sections = getSelectedSections(form);

        if (sections.length === 0) {
            setStatus(panel, 'Выберите хотя бы один раздел для генерации.', 'error');
            return;
        }

        const payload = {
            company_name: form.company_name.value,
            employees_count: form.employees_count.value ? Number(form.employees_count.value) : null,
            client_tasks: form.client_tasks.value,
            extra_instructions: form.extra_instructions.value,
            sections,
        };

        generateButton.disabled = true;
        showStep(panel, 'loading');

        try {
            const response = await fetch(panel.dataset.generateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Не удалось получить ответ от ИИ.');
            }

            currentProposal = data.proposal;
            currentSections = sections;
            summaryNode.innerHTML = renderSummary(data.summary ?? {}, sections);
            previewNode.innerHTML = renderPreview(data.proposal, sections);
            showStep(panel, 'preview');
        } catch (error) {
            showStep(panel, 'form');
            setStatus(panel, error.message || 'Ошибка при обращении к ИИ.', 'error');
        } finally {
            generateButton.disabled = false;
        }
    });

    applyButton?.addEventListener('click', async () => {
        if (!currentProposal) {
            setStatus(panel, 'Сначала сгенерируйте предложение.', 'error');
            return;
        }

        applyButton.disabled = true;
        setStatus(panel, '');

        try {
            const response = await fetch(panel.dataset.applyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    proposal: currentProposal,
                    sections: currentSections,
                }),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Не удалось применить предложение.');
            }

            window.location.href = data.redirect || window.location.href;
        } catch (error) {
            setStatus(panel, error.message || 'Ошибка при применении.', 'error');
            applyButton.disabled = false;
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.classList.contains('is-hidden')) {
            closePanel(panel);
        }
    });
});
