const valueTypeLabels = {
    text: 'Текст',
    number: 'Число',
    date: 'Дата',
    select: 'Список',
    multiselect: 'Мультисписок',
    toggle: 'Переключатель',
};

const entityLabels = {
    deals: 'Сделки',
    contacts: 'Контакты',
    companies: 'Компании',
};

const fieldLabels = {
    company_name: 'Название компании',
    website: 'Сайт',
    business_sphere: 'Сфера бизнеса',
    current_services: 'Сервисы сейчас',
    employees_count: 'Сотрудников',
    client_tasks: 'Задачи клиента',
};

const sectionTitles = {
    skk_fields: 'Поля',
    integrations: 'Интеграции',
    funnels: 'Воронки',
    stages: 'Этапы воронок',
    work_stages: 'Этапы работ',
};

export function hasPendingChanges(changes) {
    if (!changes) {
        return false;
    }

    if (changes.project?.fields && Object.keys(changes.project.fields).length > 0) {
        return true;
    }

    return ['skk_fields', 'integrations', 'funnels', 'stages', 'work_stages'].some((key) => {
        const section = changes[key];

        if (!section) {
            return false;
        }

        return ['create', 'update', 'delete'].some((action) => (section[action] ?? []).length > 0);
    });
}

export function buildChangesSections(changes) {
    if (!changes || typeof changes !== 'object') {
        return [];
    }

    const sections = [];

    if (changes.project?.fields) {
        sections.push({
            title: 'Основная информация',
            items: Object.entries(changes.project.fields).map(([key, value]) => {
                const label = fieldLabels[key] || key;
                return `${label}: ${value}`;
            }),
        });
    }

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
                rows.forEach((id) => items.push(`${label}: #${id}`));
                return;
            }

            rows.forEach((row) => {
                let text = label;

                if (row.id) {
                    text += ` #${row.id}`;
                }

                if (key === 'skk_fields') {
                    text += ` (${entityLabels[row.entity_type] || row.entity_type || ''})`;

                    if (row.value_type) {
                        text += `, ${valueTypeLabels[row.value_type] || row.value_type}`;
                    }

                    if (row.is_required) {
                        text += ', обяз.';
                    }
                }

                if (key === 'stages' && row.funnel_id) {
                    text += ` воронка #${row.funnel_id}`;
                }

                if (key === 'funnels' && Array.isArray(row.stages) && row.stages.length > 0) {
                    const stageNames = row.stages
                        .map((stage) => stage?.name)
                        .filter(Boolean)
                        .slice(0, 8)
                        .join(' → ');
                    const more = row.stages.length > 8 ? '…' : '';
                    text += `: ${row.name || ''} (${row.stages.length} этап.) — ${stageNames}${more}`;
                } else if (row.name) {
                    text += `: ${row.name}`;
                }

                if (key === 'stages') {
                    if (row.action) {
                        text += ` — автозадача: ${row.action}`;
                    }

                    if (row.deadline) {
                        text += ` · SLA: ${row.deadline}`;
                    }

                    if (row.stage_type) {
                        text += ` · тип: ${row.stage_type}`;
                    }

                    if (Array.isArray(row.outcomes) && row.outcomes.length > 0) {
                        text += ` · исходов: ${row.outcomes.length}`;
                    }
                }

                if (key === 'work_stages') {
                    if (row.duration_days !== null && row.duration_days !== undefined && row.duration_days !== '') {
                        const days = Number(row.duration_days);
                        if (Number.isFinite(days) && days > 0) {
                            text += ` — ${days} раб. дн.`;
                        }
                    }

                    if (row.cost !== null && row.cost !== undefined && row.cost !== '') {
                        const cost = Number(row.cost);
                        if (Number.isFinite(cost)) {
                            text += ` — ${new Intl.NumberFormat('ru-RU').format(cost)} ₽`;
                        }
                    }

                    const composition = String(row.composition ?? '').trim();

                    if (composition) {
                        const points = composition
                            .split(/\n+/)
                            .map((line) => line.replace(/^(?:\d+[.)]\s*|[-–—*•]\s+)/u, '').trim())
                            .filter(Boolean);

                        if (points.length > 0) {
                            text += ` · ${points.map((point, index) => `${index + 1}) ${point}`).join('; ')}`;
                        }
                    }
                }

                items.push(text);
            });
        });

        if (items.length > 0) {
            sections.push({ title, items });
        }
    });

    return sections;
}
