import { useEffect, useRef } from 'react';
import WebPushService from '@/Services/WebPushService';
import NotificationBrowserService from '@/Services/NotificationBrowserService';
import { resolveAlertasPrefs, shouldTriggerChannel, MENSAJERIA_TIPO_ALERTA } from '@/utils/alertasPrefs';

/**
 * Registra el Service Worker y la suscripción Web Push cuando el usuario
 * tiene activadas las alertas de escritorio.
 *
 * Además señaliza a NotificationBrowserService si hay push activo
 * para evitar notificaciones de escritorio duplicadas.
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
        WebPushService.ensureSubscribed()
            .then((result) => {
                // Si la suscripción push es exitosa, señalizar para evitar
                // notificaciones de escritorio duplicadas (el SW las muestra).
                NotificationBrowserService.setServiceWorkerPushActive(result?.ok === true);
            })
            .catch(() => {
                NotificationBrowserService.setServiceWorkerPushActive(false);
                // Si falla la suscripción, permitir reintentar en el próximo render.
                intentadoRef.current = false;
            });
    }, [auth?.user?.id, auth?.webpush?.enabled, auth?.webpush?.public_key, tonosAlertas]);
}
