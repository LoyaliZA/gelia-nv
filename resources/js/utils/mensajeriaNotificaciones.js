import NotificationService from '@/Services/NotificationBrowserService';
import { router } from '@inertiajs/react';
import {
    resolveAlertasPrefs,
    shouldTriggerChannel,
    isTipoAlertaEnabled,
    MENSAJERIA_TIPO_ALERTA,
    construirMensajeVozMensajeria,
    shouldTriggerMensajeriaVoz,
} from '@/utils/alertasPrefs';

const previewTipo = (tipo) => ({
    imagen: 'Te envió una imagen',
    video: 'Te envió un video',
    audio: 'Te envió un audio',
    archivo: 'Te envió un archivo',
}[tipo] || 'Nuevo mensaje');

export function setConversacionActivaMensajeria(conversacionId) {
    if (typeof window === 'undefined') return;
    window.__mensajeriaConversacionActivaId = conversacionId ?? null;
}

export function setMensajeriaWidgetAbierto(abierto) {
    if (typeof window === 'undefined') return;
    window.__mensajeriaWidgetAbierto = abierto;
}

export function abrirConversacionDesdeNotificacion(conversacionId) {
    if (!conversacionId || typeof window === 'undefined') return;

    const enMensajeria = window.location.pathname.startsWith('/mensajeria');

    if (enMensajeria) {
        window.dispatchEvent(new CustomEvent('mensajeria-abrir-conversacion', {
            detail: { conversacionId: Number(conversacionId), scrollToBottom: true },
        }));
    } else {
        router.visit(`/mensajeria?conversacion=${conversacionId}`);
    }
}

export function notificarMensajeLeido(mensaje, auth) {
    if (!mensaje || !auth?.user?.id) return;
    if (Number(mensaje.user?.id) !== Number(auth.user.id)) return;

    window.dispatchEvent(new CustomEvent('mensajeria-mensaje-leido', { detail: mensaje }));
}

export function notificarMensajeNuevo(mensaje, auth) {
    if (!mensaje || mensaje.user?.id === auth?.user?.id) return;

    window.dispatchEvent(new CustomEvent('mensajeria-mensaje-recibido', { detail: mensaje }));

    if (window.__mensajeriaConversacionActivaId === mensaje.conversacion_id) {
        return;
    }

    const prefs = resolveAlertasPrefs(auth);
    const tipo = MENSAJERIA_TIPO_ALERTA;
    const sonido = shouldTriggerChannel(prefs, tipo, 'sonido');
    const escritorio = shouldTriggerChannel(prefs, tipo, 'escritorio');
    const voz = shouldTriggerMensajeriaVoz(prefs);

    const nombre = mensaje.user?.name || 'Un contacto';
    const cuerpo = mensaje.contenido?.trim() || previewTipo(mensaje.tipo);
    const mensajeVoz = construirMensajeVozMensajeria(mensaje, prefs, auth?.user?.name);

    if (sonido || voz || escritorio) {
        NotificationService.triggerFullAlert(
            `Mensaje de ${nombre}`,
            cuerpo,
            voz ? mensajeVoz : null,
            {
                sonido,
                voz,
                escritorio,
                conversacionId: mensaje.conversacion_id,
                onClick: () => abrirConversacionDesdeNotificacion(mensaje.conversacion_id),
            }
        );
    }
}
