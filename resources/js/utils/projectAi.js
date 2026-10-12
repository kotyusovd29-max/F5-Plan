const entityLabels = {
    deals: 'Сделки',
    contacts: 'Контакты',
    companies: 'Компании',
};

const valueTypeLabels = {
    text: 'Текст',
    number: 'Число',
    date: 'Дата',
    select: 'Список',
    multiselect: 'Мультисписок',
    toggle: 'Переключатель',
};

export function pluralize(count, one, few, many) {
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

export function buildGenerateSummary(summary, sections) {
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

    return parts;
}

export function buildGeneratePreviewSections(proposal, sections) {
    const blocks = [];

    if (sections.includes('skk_fields')) {
        Object.entries(entityLabels).forEach(([type, label]) => {
            const fields = proposal.skk_fields?.[type] ?? [];

            if (fields.length === 0) {
                return;
            }

            blocks.push({
                title: label,
                items: fields.map((field) => ({
                    name: field.name,
                    meta: [
                        valueTypeLabels[field.value_type] || field.value_type,
                        field.comment,
                    ].filter(Boolean).join(' · '),
                })),
            });
        });
    }

    if (sections.includes('integrations')) {
        const integrations = proposal.integrations ?? [];

        if (integrations.length > 0) {
            blocks.push({
                title: 'Интеграции',
                items: integrations.map((item) => ({
                    name: item.name,
                    meta: item.comment || '',
                })),
            });
        }
    }

    if (sections.includes('funnels')) {
        (proposal.funnels ?? []).forEach((funnel) => {
            blocks.push({
                title: `Воронка: ${funnel.name}`,
                description: funnel.description || '',
                items: (funnel.stages ?? []).map((stage) => ({
                    name: stage.name,
                    meta: stage.action || '',
                })),
            });
        });
    }

    return blocks;
}

export function chatUrlWithPrompt(baseUrl, { prompt, mode, autostart = false } = {}) {
    const params = new URLSearchParams();

    if (prompt) {
        params.set('prompt', prompt);
    }

    if (mode) {
        params.set('mode', mode);
    }

    if (autostart) {
        params.set('autostart', '1');
    }

    const query = params.toString();

    return query ? `${baseUrl}?${query}` : baseUrl;
}
