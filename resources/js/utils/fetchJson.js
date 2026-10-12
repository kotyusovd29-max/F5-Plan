export async function readJsonResponse(response) {
    const contentType = response.headers.get('content-type') ?? '';

    if (!contentType.includes('application/json')) {
        const text = await response.text();
        const titleMatch = text.match(/<title>([^<]+)<\/title>/i);
        const title = titleMatch?.[1]?.trim();

        throw new Error(
            title
                ? `Сервер вернул ошибку (${response.status}): ${title}`
                : response.status === 502
                    ? 'Сервер не успел ответить (502). Обновите страницу — ответ ИИ мог сохраниться в фоне.'
                    : `Сервер вернул не-JSON ответ (${response.status}). Обновите страницу и попробуйте снова.`,
        );
    }

    return response.json();
}

export function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export async function postJson(url, payload = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    const data = await readJsonResponse(response);

    return { response, data };
}

export async function putJson(url, payload = {}) {
    const response = await fetch(url, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    const data = await readJsonResponse(response);

    return { response, data };
}

export async function getJson(url) {
    const response = await fetch(url, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    const data = await readJsonResponse(response);

    return { response, data };
}

export async function postFormData(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: formData,
    });

    const data = await readJsonResponse(response);

    return { response, data };
}

export async function deleteJson(url) {
    const response = await fetch(url, {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
    });

    const data = await readJsonResponse(response);

    return { response, data };
}
