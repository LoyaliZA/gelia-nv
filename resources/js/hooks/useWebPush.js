import { useCallback, useEffect, useRef, useState } from 'react';
import WebPushService from '@/Services/WebPushService';
import NotificationBrowserService from '@/Services/NotificationBrowserService';

async function activarPush() {
    await NotificationBrowserService.requestDesktopPermissions();
    const result = await WebPushService.ensureSubscribed();
    NotificationBrowserService.setServiceWorkerPushActive(result?.ok === true);
    return result;
}

/**
 * Al entrar a la app: intenta permiso + Web Push.
 * Si el navegador bloquea el prompt sin gesto (Brave/Chrome), expone
 * `needsPrompt` para mostrar un CTA de un toque.
 */
export default function useWebPush(auth) {
    const intentadoRef = useRef(false);
    const [needsPrompt, setNeedsPrompt] = useState(false);

    const activar = useCallback(async () => {
        if (!WebPushService.isSupported()) return { ok: false };
        try {
            const result = await activarPush();
            setNeedsPrompt(
                typeof Notification !== 'undefined'
                && Notification.permission === 'default',
            );
            return result;
        } catch {
            NotificationBrowserService.setServiceWorkerPushActive(false);
            setNeedsPrompt(
                typeof Notification !== 'undefined'
                && Notification.permission === 'default',
            );
            return { ok: false };
        }
    }, []);

    useEffect(() => {
        if (!auth?.user?.id || intentadoRef.current) return;
        if (!WebPushService.isSupported()) return;

        const webpush = auth?.webpush;
        if (!webpush?.enabled || !webpush?.public_key) return;

        intentadoRef.current = true;
        WebPushService.publicKey = webpush.public_key;

        activar().then((result) => {
            if (!result?.ok) {
                intentadoRef.current = false;
            }
        });
    }, [auth?.user?.id, auth?.webpush?.enabled, auth?.webpush?.public_key, activar]);

    return { needsPrompt, activarNotificaciones: activar };
}
