export const MIN_BILLING_USERS = 5;

/** Годовой период в UI: оплата за 10 мес. + 3 мес. бесплатно. */
export const ANNUAL_PERIOD_KEY = 12;
export const ANNUAL_PAID_MONTHS = 10;
export const ANNUAL_FREE_MONTHS = 3;

/**
 * Сколько месяцев реально оплачивается для помесячных виджетов.
 * @param {number} periodMonths
 * @returns {number}
 */
export function resolvePaidMonths(periodMonths) {
    const months = Math.max(1, Number(periodMonths) || 1);

    if (months === ANNUAL_PERIOD_KEY) {
        return ANNUAL_PAID_MONTHS;
    }

    return months;
}

/**
 * @param {number} periodMonths
 * @returns {{ key: number, paidMonths: number, freeMonths: number, isAnnualBonus: boolean, label: string, shortLabel: string }}
 */
export function describeBillingPeriod(periodMonths) {
    const key = Math.max(1, Number(periodMonths) || 1);
    const paidMonths = resolvePaidMonths(key);
    const isAnnualBonus = key === ANNUAL_PERIOD_KEY;

    return {
        key,
        paidMonths,
        freeMonths: isAnnualBonus ? ANNUAL_FREE_MONTHS : 0,
        isAnnualBonus,
        label: isAnnualBonus
            ? `за ${ANNUAL_PAID_MONTHS}+${ANNUAL_FREE_MONTHS} мес.`
            : (key === 1 ? 'в месяц' : `за ${key} мес.`),
        shortLabel: isAnnualBonus
            ? `${ANNUAL_PAID_MONTHS}+${ANNUAL_FREE_MONTHS}`
            : (key === 1 ? 'В месяц' : `За ${key} мес.`),
    };
}

/**
 * @returns {number|null} Monthly price per user in RUB, or null if not calculable.
 */
export function parseWidgetMonthlyPrice(priceStr) {
    if (!priceStr || priceStr.trim() === '—') {
        return null;
    }

    const normalized = priceStr.trim().toLowerCase();

    if (
        normalized.includes('бонусный')
        || normalized.includes('токен')
        || normalized.includes('/ час')
        || normalized.includes('/ мин')
        || normalized.includes('расчет по')
        || normalized.includes('канал')
        || normalized.includes('/ 6 мес')
        || normalized.includes('/ 12 мес')
    ) {
        return null;
    }

    const yearlyMatch = normalized.match(/([\d\s.,]+)\s*₽?\s*в\s*год/u);

    if (yearlyMatch) {
        const yearly = parseRussianAmount(yearlyMatch[1]);

        return yearly !== null ? yearly / 12 : null;
    }

    const monthlyMatch = normalized.match(/([\d\s.,]+)\s*₽?/u);

    if (monthlyMatch) {
        return parseRussianAmount(monthlyMatch[1]);
    }

    return null;
}

/**
 * @param {string} value
 * @returns {number|null}
 */
function parseRussianAmount(value) {
    const cleaned = value.replace(/\s/g, '').replace(',', '.');
    const amount = Number.parseFloat(cleaned);

    return Number.isFinite(amount) ? amount : null;
}

/**
 * @param {number|string|null|undefined} usersCount
 * @returns {number}
 */
export function resolveBillingUsers(usersCount) {
    const parsed = Math.max(0, Number(usersCount) || 0);

    return Math.max(MIN_BILLING_USERS, parsed || MIN_BILLING_USERS);
}

/**
 * @returns {string}
 */
export function formatRub(amount) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: amount % 1 === 0 ? 0 : 2,
    }).format(amount);
}

/**
 * @param {Record<string, any>|null|undefined} pricing
 * @param {number|string|null|undefined} quantity
 * @returns {number}
 */
export function resolveChannelQuantity(pricing, quantity) {
    const min = Math.max(1, Number(pricing?.min_quantity) || 1);
    const fallback = Math.max(min, Number(pricing?.default_quantity) || min);
    const parsed = Number.parseInt(String(quantity ?? ''), 10);

    if (!Number.isFinite(parsed) || parsed < 1) {
        return fallback;
    }

    return Math.max(min, parsed);
}

/**
 * Fixed-period packages: exact price for 6/12 months; for 1 month use yearly package / 12.
 *
 * @param {Record<string, number>|undefined} packages
 * @param {number} months
 * @returns {{ periodTotal: number, monthlyTotal: number }|null}
 */
export function resolveFixedPeriodCost(packages, months) {
    if (!packages || typeof packages !== 'object') {
        return null;
    }

    const periodMonths = Math.max(1, Number(months) || 1);
    const six = Number(packages[6]);
    const twelve = Number(packages[12]);

    if (periodMonths === 6 && Number.isFinite(six)) {
        return {
            periodTotal: six,
            monthlyTotal: six / 6,
        };
    }

    if (periodMonths === 12 && Number.isFinite(twelve)) {
        return {
            periodTotal: twelve,
            monthlyTotal: twelve / 12,
        };
    }

    if (periodMonths === 1 && Number.isFinite(twelve)) {
        return {
            periodTotal: twelve / 12,
            monthlyTotal: twelve / 12,
        };
    }

    if (Number.isFinite(twelve)) {
        const monthly = twelve / 12;

        return {
            periodTotal: monthly * periodMonths,
            monthlyTotal: monthly,
        };
    }

    if (Number.isFinite(six)) {
        const monthly = six / 6;

        return {
            periodTotal: monthly * periodMonths,
            monthlyTotal: monthly,
        };
    }

    return null;
}

/**
 * @param {{
 *   integrations: Array<{
 *     name: string,
 *     widget_slug?: string|null,
 *     is_custom?: boolean,
 *     billing_quantity?: number|null,
 *     payment_type?: string|null,
 *     cost?: number|string|null,
 *   }>,
 *   catalogWidgets: Array<{ slug: string, name: string, price?: string, pricing?: Record<string, any> }>,
 *   usersCount: number,
 *   months: number,
 * }} params
 */
export function calculateIntegrationsCost({
    integrations,
    catalogWidgets,
    usersCount,
    months,
}) {
    const widgetsBySlug = Object.fromEntries(
        (catalogWidgets ?? []).map((widget) => [widget.slug, widget]),
    );
    const usersInput = Math.max(0, Number(usersCount) || 0);
    const billingUsers = resolveBillingUsers(usersCount);
    const periodMonths = Math.max(1, Number(months) || 1);
    const paidMonths = resolvePaidMonths(periodMonths);
    const periodMeta = describeBillingPeriod(periodMonths);

    /** @type {Array<{
     *   name: string,
     *   priceLabel: string,
     *   model: string,
     *   calcLabel: string,
     *   lineMonthly: number,
     *   linePeriod: number,
     *   dependsOnUsers: boolean,
     * }>} */
    const lines = [];
    /** @type {Array<{ name: string, reason: string }>} */
    const skipped = [];

    let monthlyTotal = 0;
    let periodTotal = 0;
    let hasUserBasedLines = false;

    for (const integration of integrations ?? []) {
        if (!integration.widget_slug || integration.is_custom) {
            const customLine = resolveCustomIntegrationLine(integration, paidMonths, periodMeta);

            if (!customLine) {
                skipped.push({
                    name: integration.name,
                    reason: 'Своя интеграция',
                });
                continue;
            }

            monthlyTotal += customLine.lineMonthly;
            periodTotal += customLine.linePeriod;
            lines.push(customLine);
            continue;
        }

        const widget = widgetsBySlug[integration.widget_slug];

        if (!widget) {
            skipped.push({
                name: integration.name,
                reason: 'Нет в каталоге',
            });
            continue;
        }

        const pricing = widget.pricing ?? null;
        const model = pricing?.model ?? 'per_user_monthly';

        if (model === 'per_channel_monthly') {
            const unitMonthly = Number(pricing.unit_monthly);
            const quantity = resolveChannelQuantity(pricing, integration.billing_quantity);
            const quantityLabel = pricing.quantity_label || 'каналов';

            if (!Number.isFinite(unitMonthly) || unitMonthly <= 0) {
                skipped.push({
                    name: integration.name,
                    reason: widget.price || 'Цена не фиксированная',
                });
                continue;
            }

            const lineMonthly = unitMonthly * quantity;
            const linePeriod = lineMonthly * paidMonths;
            monthlyTotal += lineMonthly;
            periodTotal += linePeriod;

            const channelCalc = `${formatRub(unitMonthly)} × ${quantity} ${quantityLabel}`;

            lines.push({
                name: integration.name,
                priceLabel: widget.price ?? `${formatRub(unitMonthly)} / канал · мес`,
                model,
                calcLabel: paidMonths > 1
                    ? `${channelCalc} × ${paidMonths} мес.${periodMeta.isAnnualBonus ? ` (+${periodMeta.freeMonths} бесплатно)` : ''}`
                    : channelCalc,
                lineMonthly,
                linePeriod,
                dependsOnUsers: false,
            });
            continue;
        }

        if (model === 'fixed_period') {
            const fixed = resolveFixedPeriodCost(pricing.packages, periodMonths);

            if (!fixed) {
                skipped.push({
                    name: integration.name,
                    reason: 'Нет пакета на выбранный период',
                });
                continue;
            }

            monthlyTotal += fixed.monthlyTotal;
            periodTotal += fixed.periodTotal;

            const packageHint = periodMeta.isAnnualBonus
                ? `пакет на ${ANNUAL_PAID_MONTHS}+${ANNUAL_FREE_MONTHS}`
                : (periodMonths === 6 || periodMonths === 12
                    ? `пакет на ${periodMonths} мес.`
                    : 'из годового пакета');

            lines.push({
                name: integration.name,
                priceLabel: widget.price ?? '',
                model,
                calcLabel: packageHint,
                lineMonthly: fixed.monthlyTotal,
                linePeriod: fixed.periodTotal,
                dependsOnUsers: false,
            });
            continue;
        }

        const perUserMonthly = parseWidgetMonthlyPrice(widget.price);

        if (perUserMonthly === null) {
            skipped.push({
                name: integration.name,
                reason: widget.price || 'Цена не фиксированная',
            });
            continue;
        }

        const lineMonthly = perUserMonthly * billingUsers;
        const linePeriod = lineMonthly * paidMonths;
        monthlyTotal += lineMonthly;
        periodTotal += linePeriod;
        hasUserBasedLines = true;

        const userCalc = `${formatRub(perUserMonthly)} × ${billingUsers}`;

        lines.push({
            name: integration.name,
            priceLabel: widget.price ?? '',
            model: 'per_user_monthly',
            calcLabel: paidMonths > 1
                ? `${userCalc} × ${paidMonths} мес.${periodMeta.isAnnualBonus ? ` (+${periodMeta.freeMonths} бесплатно)` : ''}`
                : userCalc,
            lineMonthly,
            linePeriod,
            dependsOnUsers: true,
        });
    }

    return {
        usersInput,
        billingUsers,
        months: periodMonths,
        paidMonths,
        freeMonths: periodMeta.freeMonths,
        isAnnualBonus: periodMeta.isAnnualBonus,
        periodLabel: periodMeta.label,
        periodShortLabel: periodMeta.shortLabel,
        monthlyTotal,
        periodTotal,
        lines,
        skipped,
        hasCalculable: lines.length > 0,
        hasUserBasedLines,
        usesMinimumUsers: hasUserBasedLines && (billingUsers > usersInput || usersInput < MIN_BILLING_USERS),
    };
}

/**
 * @param {{
 *   name: string,
 *   payment_type?: string|null,
 *   cost?: number|string|null,
 * }} integration
 * @param {number} paidMonths
 * @param {{ isAnnualBonus: boolean, freeMonths: number }} periodMeta
 */
function resolveCustomIntegrationLine(integration, paidMonths, periodMeta) {
    const rawCost = integration.cost;

    if (rawCost === null || rawCost === undefined || rawCost === '') {
        return null;
    }

    const cost = Number(rawCost);

    if (!Number.isFinite(cost) || cost < 0) {
        return null;
    }

    const paymentType = integration.payment_type || 'subscription';

    if (paymentType === 'one_time') {
        return {
            name: integration.name,
            priceLabel: 'Единовременно',
            model: 'custom_one_time',
            calcLabel: 'разовая сумма',
            lineMonthly: 0,
            linePeriod: cost,
            dependsOnUsers: false,
        };
    }

    const lineMonthly = cost;
    const linePeriod = lineMonthly * paidMonths;

    return {
        name: integration.name,
        priceLabel: `${formatRub(lineMonthly)} / мес`,
        model: 'custom_subscription',
        calcLabel: paidMonths > 1
            ? `${formatRub(lineMonthly)} × ${paidMonths} мес.${periodMeta.isAnnualBonus ? ` (+${periodMeta.freeMonths} бесплатно)` : ''}`
            : formatRub(lineMonthly),
        lineMonthly,
        linePeriod,
        dependsOnUsers: false,
    };
}
