import axios from 'axios';

function urlBase64ToUint8Array(base64String) {
    base64String = (base64String || '').trim();
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

const PUSH_BLOCK_KEY = 'gelia_webpush_fcm_blocked';

function readPushBlocked() {
    try {
        return sessionStorage.getItem(PUSH_BLOCK_KEY) === '1';
    } catch {
        return false;
    }
}

function writePushBlocked(blocked) {
    try {
        if (blocked) {
            sessionStorage.setItem(PUSH_BLOCK_KEY, '1');
        } else {
            sessionStorage.removeItem(PUSH_BLOCK_KEY);
        }
    } catch {
        /* private mode */
    }
}

class WebPushService {
    constructor() {
        this.registration = null;
        this.publicKey = null;
        this.pushServiceBlocked = typeof window !== 'undefined' ? readPushBlocked() : false;
        this.subscribeInFlight = null;
        this.soportado = typeof window !== 'undefined'
            && 'serviceWorker' in navigator
            && 'PushManager' in window
            && 'Notification' in window;
    }

    isSupported() {
        return this.soportado;
    }

    markPushServiceBlocked(blocked) {
        this.pushServiceBlocked = blocked;
        writePushBlocked(blocked);
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

        const keyBytes = urlBase64ToUint8Array(vapid.public_key);

        await this.registerServiceWorker();

        const existente = await this.registration.pushManager.getSubscription();
        if (existente) {
            let keyBytesEqual = false;
            try {
                const opts = existente.options?.applicationServerKey;
                const existingBytes = opts ? new Uint8Array(opts) : null;
                if (existingBytes && existingBytes.length === keyBytes.length) {
                    keyBytesEqual = existingBytes.every((b, i) => b === keyBytes[i]);
                }
            } catch {
                keyBytesEqual = false;
            }

            // Tras rotar VAPID, reutilizar la sub antigua rompe el envío; renovar.
            if (!keyBytesEqual) {
                await existente.unsubscribe().catch(() => null);
            } else {
                await this.syncSubscription(existente);
                return { ok: true, subscription: existente };
            }
        }

        const subscription = await this.registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: keyBytes,
        });
        await this.syncSubscription(subscription);
        return { ok: true, subscription };
    }

    async syncSubscription(subscription) {
        const json = subscription.toJSON();
        // Chrome/Brave modernos usan aes128gcm; aesgcm hardcodeado rompe el descifrado.
        const contentEncoding = (typeof PushManager !== 'undefined'
            && PushManager.supportedContentEncodings?.[0])
            || 'aes128gcm';

        await axios.post(urlPushSubscribe(), {
            endpoint: json.endpoint,
            keys: json.keys,
            content_encoding: contentEncoding,
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
        if (!this.soportado || Notification.permission === 'denied') {
            return { ok: false };
        }

        if (!this.pushServiceBlocked && readPushBlocked()) {
            this.pushServiceBlocked = true;
        }

        // FCM del navegador ya falló en esta sesión de pestaña: no reintentar.
        if (this.pushServiceBlocked) {
            return { ok: false, reason: 'push_service_error' };
        }

        if (this.subscribeInFlight) {
            return this.subscribeInFlight;
        }

        this.subscribeInFlight = (async () => {
            try {
                const result = await this.subscribe();
                if (result?.ok) {
                    this.markPushServiceBlocked(false);
                }
                return result;
            } catch (err) {
                // AbortError = fallo del servicio push del navegador (FCM), no de GELIA.
                // Campana / sonido / Notification API siguen activos con la pestaña abierta.
                if (err?.name === 'AbortError') {
                    this.markPushServiceBlocked(true);
                    console.warn(
                        '[WebPush] Servicio push del navegador no disponible (FCM). '
                        + 'Las alertas en pestaña abierta siguen activas.',
                        err?.message || err,
                    );
                    return { ok: false, reason: 'push_service_error', error: err };
                }
                console.warn('[WebPush] No se pudo suscribir:', err);
                return { ok: false, error: err };
            } finally {
                this.subscribeInFlight = null;
            }
        })();

        return this.subscribeInFlight;
    }
}

export default new WebPushService();
