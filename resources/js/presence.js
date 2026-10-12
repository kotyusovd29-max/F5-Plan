import { router } from '@inertiajs/vue3';
import { getCsrfToken } from './utils/fetchJson';

const INTERVAL_MS = 30_000;
const ENDPOINT = '/presence/heartbeat';

let timerId = null;
let started = false;
let inFlight = false;

function hasAuthUser(page) {
    return Boolean(page?.props?.auth?.user?.id);
}

async function sendHeartbeat() {
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
        return;
    }

    if (inFlight) {
        return;
    }

    inFlight = true;

    try {
        await fetch(ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            keepalive: true,
            body: '{}',
        });
    } catch {
        // ignore network blips — следующий тик догонит
    } finally {
        inFlight = false;
    }
}

function startPresenceHeartbeat() {
    if (started) {
        return;
    }

    started = true;
    sendHeartbeat();
    timerId = window.setInterval(sendHeartbeat, INTERVAL_MS);
}

function stopPresenceHeartbeat() {
    started = false;

    if (timerId !== null) {
        window.clearInterval(timerId);
        timerId = null;
    }
}

function syncPresenceFromPage(page) {
    if (hasAuthUser(page)) {
        startPresenceHeartbeat();
    } else {
        stopPresenceHeartbeat();
    }
}

export function initPresenceTracking(initialPage) {
    syncPresenceFromPage(initialPage);

    router.on('navigate', (event) => {
        syncPresenceFromPage(event.detail.page);
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && started) {
            sendHeartbeat();
        }
    });

    window.addEventListener('focus', () => {
        if (started) {
            sendHeartbeat();
        }
    });
}
