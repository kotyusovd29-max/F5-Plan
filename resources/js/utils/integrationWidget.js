export const CUSTOM_WIDGET_SLUG = 'custom';

export const PAYMENT_SUBSCRIPTION = 'subscription';
export const PAYMENT_ONE_TIME = 'one_time';

export function emptyIntegration() {
    return {
        widget_slug: '',
        name: '',
        widget_url: null,
        comment: '',
        billing_quantity: 1,
        payment_type: PAYMENT_SUBSCRIPTION,
        cost: '',
    };
}

export function integrationFromApi(item) {
    return {
        widget_slug: item.is_custom === false && item.widget_slug
            ? item.widget_slug
            : CUSTOM_WIDGET_SLUG,
        name: item.name ?? '',
        widget_url: item.widget_url ?? null,
        comment: item.comment ?? '',
        billing_quantity: item.billing_quantity ?? 1,
        payment_type: item.payment_type || PAYMENT_SUBSCRIPTION,
        cost: item.cost ?? '',
    };
}

export function isCustomIntegration(model) {
    return !model.widget_slug || model.widget_slug === CUSTOM_WIDGET_SLUG;
}

export function integrationToPayload(model) {
    if (isCustomIntegration(model)) {
        return {
            widget_slug: CUSTOM_WIDGET_SLUG,
            name: model.name,
            comment: model.comment || null,
            billing_quantity: null,
            payment_type: model.payment_type || PAYMENT_SUBSCRIPTION,
            cost: model.cost === '' || model.cost === null || model.cost === undefined
                ? null
                : model.cost,
        };
    }

    return {
        widget_slug: model.widget_slug,
        comment: model.comment || null,
        billing_quantity: model.billing_quantity ?? null,
        payment_type: null,
        cost: null,
    };
}

export function categoryLabel(categories, category) {
    return categories?.[category] ?? category ?? '';
}

export function widgetPricing(widget) {
    return widget?.pricing ?? null;
}

export function isChannelPricedWidget(widget) {
    return widgetPricing(widget)?.model === 'per_channel_monthly';
}
