const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function request(method, url, body, isForm = false) {
    const headers = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (body !== undefined && !isForm) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
    });

    const isJson = response.headers.get('content-type')?.includes('application/json');
    const payload = response.status === 204 ? null : isJson ? await response.json() : await response.text();

    if (!response.ok) {
        throw Object.assign(new Error(payload?.message || response.statusText), { status: response.status, payload });
    }

    return payload;
}

export const api = {
    get: (url) => request('GET', url),
    post: (url, body = {}) => request('POST', url, body),
    put: (url, body) => request('PUT', url, body),
    delete: (url) => request('DELETE', url),
    upload: (url, form) => request('POST', url, form, true),
};
