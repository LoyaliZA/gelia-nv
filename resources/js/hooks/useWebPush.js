import { useEffect, useRef } from 'react';
import WebPushService from '@/Services/WebPushService';
import { resolveAlertasPrefs, shouldTriggerChannel, MENSAJERIA_TIPO_ALERTA } from '@/utils/alertasPrefs';

/**
 * Registra el Service Worker y la suscripción Web Push cuando el usuario
 * tiene activadas las alertas de escritorio.
 */
export default function useWebPush(auth, tonosAlertas = []) {
    const intentadoRef = useRef(false);

    useEffect(() => {
        if (!auth?.user?.id || intentadoRef.current) return;
        if (!WebPushService.isSupported()) return;

        const webpush = auth?.webpush;
        if (!webpush?.enabled || !webpush?.public_key) return;

        const prefs = resolveAlertasPrefs(auth);
        const escritorioActivo = shouldTriggerChannel(prefs, 'solicitudes', 'escritorio')
            || shouldTriggerChannel(prefs, MENSAJERIA_TIPO_ALERTA, 'escritorio');

        if (!escritorioActivo) return;

        intentadoRef.current = true;

        WebPushService.publicKey = webpush.public_key;

        if (Notification.permission === 'granted') {
            WebPushService.ensureSubscribed();
            return;
        }

        if (Notification.permission === 'default') {
            WebPushService.ensureSubscribed();
        }
    }, [auth?.user?.id, auth?.webpush?.enabled, auth?.webpush?.public_key, tonosAlertas]);
}
