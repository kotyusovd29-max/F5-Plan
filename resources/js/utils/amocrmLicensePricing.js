import {
    ANNUAL_FREE_MONTHS,
    ANNUAL_PAID_MONTHS,
    ANNUAL_PERIOD_KEY,
    describeBillingPeriod,
    formatRub,
    resolvePaidMonths,
} from './widgetPricing';

export const AMOCRM_LICENSE_SOURCE_URL = 'https://www.amocrm.ru/buy/';

/** Лицензии amoCRM считаются от 1 пользователя. */
export const MIN_AMOCRM_LICENSE_USERS = 1;

/** Fallback if backend config is missing — prices from amocrm.ru/buy */
export const DEFAULT_AMOCRM_LICENSE_TARIFFS = [
    { slug: 'base', name: 'Базовый', price_per_user: 599 },
    { slug: 'advanced', name: 'Расширенный', price_per_user: 1199 },
    { slug: 'professional', name: 'Профессиональный', price_per_user: 1699 },
];

/**
 * @param {number|string|null|undefined} usersCount
 * @returns {number}
 */
export function resolveAmocrmLicenseUsers(usersCount) {
    const parsed = Math.max(0, Number.parseInt(String(usersCount ?? ''), 10) || 0);

    return Math.max(MIN_AMOCRM_LICENSE_USERS, parsed > 0 ? parsed : MIN_AMOCRM_LICENSE_USERS);
}

/**
 * @param {{
 *   tariffs?: Array<{ slug?: string, name?: string, price_per_user?: number }>,
 *   usersCount?: number|string|null,
 *   months?: number,
 *   sourceUrl?: string,
 *   sourceNote?: string,
 * }} options
 */
export function calculateAmocrmLicenses(options = {}) {
    const tariffsConfig = (options.tariffs?.length
        ? options.tariffs
        : DEFAULT_AMOCRM_LICENSE_TARIFFS);
    const usersInput = Math.max(0, Number.parseInt(String(options.usersCount ?? ''), 10) || 0);
    const billingUsers = resolveAmocrmLicenseUsers(options.usersCount);
    const periodMonths = Math.max(1, Number(options.months) || ANNUAL_PERIOD_KEY);
    const paidMonths = resolvePaidMonths(periodMonths);
    const periodMeta = describeBillingPeriod(periodMonths);
    const freeMonths = periodMeta.isAnnualBonus
        ? ANNUAL_FREE_MONTHS
        : (periodMonths === 6 ? 1 : 0);

    const tariffs = tariffsConfig
        .map((tariff) => {
            const price = Number(tariff.price_per_user);
            const slug = String(tariff.slug ?? '');
            const name = String(tariff.name ?? '');

            if (!slug || !name || !Number.isFinite(price) || price <= 0) {
                return null;
            }

            const lineMonthly = price * billingUsers;
            const linePeriod = lineMonthly * paidMonths;
            const userCalc = `${formatRub(price)} × ${billingUsers}`;
            const calcLabel = paidMonths > 1
                ? `${userCalc} × ${paidMonths} мес.${freeMonths > 0 ? ` (+${freeMonths} в подарок)` : ''}`
                : userCalc;

            return {
                slug,
                name,
                price_per_user: price,
                lineMonthly,
                linePeriod,
                calcLabel,
            };
        })
        .filter(Boolean);

    return {
        usersInput,
        billingUsers,
        months: periodMonths,
        paidMonths,
        freeMonths,
        isAnnualBonus: periodMeta.isAnnualBonus,
        periodLabel: periodMeta.label,
        periodShortLabel: periodMeta.shortLabel,
        sourceUrl: options.sourceUrl || AMOCRM_LICENSE_SOURCE_URL,
        sourceNote: options.sourceNote || '',
        tariffs,
        usesMinimumUsers: false,
    };
}
