export const valueTypeOptions = [
    { value: 'text', label: 'Текст' },
    { value: 'number', label: 'Число' },
    { value: 'date', label: 'Дата' },
    { value: 'select', label: 'Список' },
    { value: 'multiselect', label: 'Мультисписок' },
    { value: 'toggle', label: 'Переключатель' },
];

export function emptySkkField(entityType = 'deals') {
    return {
        entity_type: entityType,
        name: '',
        value_type: 'text',
        is_required: false,
        show_in_card: true,
        show_in_list: false,
        show_in_filter: false,
        options_text: '',
        funnel_stage_id: '',
        comment: '',
    };
}

export function skkFieldFromApi(field) {
    return {
        name: field.name ?? '',
        value_type: field.value_type ?? 'text',
        is_required: Boolean(field.is_required),
        show_in_card: field.show_in_card !== false,
        show_in_list: Boolean(field.show_in_list),
        show_in_filter: Boolean(field.show_in_filter),
        options_text: (field.options ?? []).join('\n'),
        funnel_stage_id: field.funnel_stage_id ? String(field.funnel_stage_id) : '',
        comment: field.comment ?? '',
    };
}

export function skkFieldNeedsOptions(valueType) {
    return valueType === 'select' || valueType === 'multiselect';
}

export function skkFieldToPayload(field) {
    const payload = {
        name: field.name,
        value_type: field.value_type,
        is_required: Boolean(field.is_required),
        show_in_card: Boolean(field.show_in_card),
        show_in_list: Boolean(field.show_in_list),
        show_in_filter: Boolean(field.show_in_filter),
        funnel_stage_id: field.funnel_stage_id ? Number(field.funnel_stage_id) : null,
        comment: field.comment || null,
    };

    if (skkFieldNeedsOptions(field.value_type)) {
        payload.options = field.options_text
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter(Boolean);
    }

    return payload;
}

export function skkFieldSummary(field) {
    if (field.summary) {
        return field.summary;
    }

    const typeLabel = valueTypeOptions.find((option) => option.value === field.value_type)?.label
        ?? field.value_type_label
        ?? field.value_type
        ?? 'Текст';

    const parts = [typeLabel];

    if (field.is_required) {
        parts.push('обяз.');
    }

    if (field.funnel_stage_label) {
        parts.push(field.funnel_stage_label);
    }

    return parts.join(' · ');
}
