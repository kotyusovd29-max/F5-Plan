export function buildStarterPackagePayload(data) {
    return {
        name: data.name,
        business_sphere: data.business_sphere,
        description: data.description || null,
        sort_order: data.sort_order ?? 0,
        is_active: Boolean(data.is_active),
        funnels: (data.funnels ?? []).map((funnel) => ({
            name: funnel.name,
            description: funnel.description || null,
            stages: (funnel.stages ?? []).map((stage) => ({
                name: stage.name,
                action: stage.action || null,
                comment: stage.comment || null,
                color: stage.color || null,
            })),
        })),
        skk_fields: (data.skk_fields ?? []).map((field) => {
            const needsOptions = ['select', 'multiselect'].includes(field.value_type);

            return {
                entity_type: field.entity_type,
                name: field.name,
                value_type: field.value_type,
                is_required: Boolean(field.is_required),
                show_in_card: Boolean(field.show_in_card),
                show_in_list: Boolean(field.show_in_list),
                show_in_filter: Boolean(field.show_in_filter),
                options: needsOptions
                    ? String(field.options_text ?? '')
                        .split(/\r?\n/)
                        .map((line) => line.trim())
                        .filter(Boolean)
                    : null,
                funnel_name: field.funnel_name || null,
                stage_name: field.stage_name || null,
                comment: field.comment || null,
            };
        }),
        integrations: (data.integrations ?? []).map((item) => ({
            widget_slug: item.widget_slug || null,
            name: item.name || null,
            comment: item.comment || null,
            billing_quantity: item.billing_quantity || null,
        })),
        work_stages: (data.work_stages ?? []).map((stage) => ({
            name: stage.name,
            composition: stage.composition || null,
            cost: stage.cost === '' || stage.cost === null || stage.cost === undefined
                ? null
                : stage.cost,
            duration_days: stage.duration_days === '' || stage.duration_days === null || stage.duration_days === undefined
                ? null
                : stage.duration_days,
        })),
    };
}
