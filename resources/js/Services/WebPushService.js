import axios from 'axios';

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i += 1) {
        output[i] = raw.charCodeAt(i);
    }
    return output;
}

function urlPushSubscribe() {
    try {
        if (typeof route === 'function' && route().has?.('push.subscribe')) {
            return route('push.subscribe');
        }
    } catch {
        /* ziggy */
    }
    return '/push/subscribe';
}

function urlPushUnsubscribe() {
    try {
        if (typeof route === 'function' && route().has?.('push.unsubscribe')) {
            return route('push.unsubscribe');
        }
    } catch {
        /* ziggy */
    }
    return '/push/unsubscribe';
}

function urlVapidKey() {
    try {
        if (typeof route === 'function' && route().has?.('push.vapid')) {
            return route('push.vapid');
        }
    } catch {
        /* ziggy */
    }
    return '/push/vapid-public-key';
}

class WebPushService {
    constructor() {
        this.registration = null;
        this.publicKey = null;
        this.soportado = typeof window !== 'undefined'
            && 'serviceWorker' in navigator
            && 'PushManager' in window
            && 'Notification' in window;
    }

    isSupported() {
        return this.soportado;
    }

    async fetchVapidPublicKey() {
        const { data } = await axios.get(urlVapidKey());
        this.publicKey = data?.public_key || null;
        return data;
    }

    async registerServiceWorker() {
        if (!this.soportado) return null;

        this.registration = await navigator.serviceWorker.register('/sw.js', {
            scope: '/',
        });

        await navigator.serviceWorker.ready;
        return this.registration;
    }

    async subscribe() {
        if (!this.soportado) {
            return { ok: false, reason: 'unsupported' };
        }

        const permiso = await Notification.requestPermission();
        if (permiso !== 'granted') {
            return { ok: false, reason: 'permission_denied' };
        }

        const vapid = await this.fetchVapidPublicKey();
        if (!vapid?.enabled || !vapid?.public_key) {
            return { ok: false, reason: 'vapid_not_configured' };
        }

        await this.registerServiceWorker();

        const existente = await this.registration.pushManager.getSubscription();
        if (existente) {
            await this.syncSubscription(existente);
            return { ok: true, subscription: existente };
        }

        const subscription = await this.registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapid.public_key),
        });

        await this.syncSubscription(subscription);

        return { ok: true, subscription };
    }

    async syncSubscription(subscription) {
        const json = subscription.toJSON();
        await axios.post(urlPushSubscribe(), {
            endpoint: json.endpoint,
            keys: json.keys,
            content_encoding: 'aesgcm',
        });
    }

    async unsubscribe() {
        if (!this.registration) {
            await this.registerServiceWorker().catch(() => null);
        }
        if (!this.registration) return;

        const sub = await this.registration.pushManager.getSubscription();
        if (!sub) return;

        const endpoint = sub.endpoint;
        await sub.unsubscribe();
        await axios.delete(urlPushUnsubscribe(), { data: { endpoint } });
    }

    async ensureSubscribed() {
        if (!this.soportado || Notification.permission !== 'granted') {
            return { ok: false };
        }

        try {
            return await this.subscribe();
        } catch (err) {
            console.warn('[WebPush] No se pudo suscribir:', err);
            return { ok: false, error: err };
        }
    }
}

export default new WebPushService();
