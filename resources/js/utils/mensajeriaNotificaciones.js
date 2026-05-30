import NotificationService from '@/Services/NotificationBrowserService';
import {
    resolveAlertasPrefs,
    shouldTriggerChannel,
    isTipoAlertaEnabled,
    MENSAJERIA_TIPO_ALERTA,
    construirMensajeVozMensajeria,
    debeUsarVozMensajeria,
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
    const voz = isTipoAlertaEnabled(prefs, tipo) && debeUsarVozMensajeria(prefs);

    const nombre = mensaje.user?.name || 'Un contacto';
    const cuerpo = mensaje.contenido?.trim() || previewTipo(mensaje.tipo);
    const mensajeVoz = construirMensajeVozMensajeria(mensaje, prefs, auth?.user?.name);

    if (sonido || voz || escritorio) {
        NotificationService.triggerFullAlert(
            `Mensaje de ${nombre}`,
            cuerpo,
            voz ? mensajeVoz : null,
            { sonido, voz, escritorio }
        );
    }
}
