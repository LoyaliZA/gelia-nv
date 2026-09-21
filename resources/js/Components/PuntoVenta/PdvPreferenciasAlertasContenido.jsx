import React from 'react';
import {
    BellOff,
    Loader2,
    Mic,
    Smartphone,
    Volume2,
    VolumeX,
} from 'lucide-react';
import { GELIA_BTN_OUTLINE, THEME_SELECT } from '@/utils/geliaTheme';
import {
    mensajeFallbackWebPushPdv,
    PDV_PUSH_ESTADO,
    reproducirTonoPdv,
} from '@/utils/pdvAlertasPrefs';
import { conexionDegradadaPdv } from '@/utils/pdvAlertQueueUtils';
import { PDV_TTS_ESTADO } from '@/utils/pdvSpeechUtils';

function FilaPreferencia({ icono: Icono, titulo, subtitulo, children }) {
    return (
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 py-3 border-b last:border-b-0 theme-border">
            <div className="flex items-start gap-3 min-w-0">
                <Icono className="w-4 h-4 mt-0.5 shrink-0 theme-text-muted" aria-hidden />
                <div className="min-w-0">
                    <p className="text-sm font-semibold theme-text-main m-0">{titulo}</p>
                    {subtitulo && <p className="text-xs theme-text-muted m-0 mt-0.5">{subtitulo}</p>}
                </div>
            </div>
            <div className="shrink-0 self-end sm:self-center">{children}</div>
        </div>
    );
}

function SwitchPreferencia({ activo, onToggle, deshabilitado = false, etiqueta }) {
    return (
        <button
            type="button"
            className="gelia-switch shrink-0 scale-110 shadow-sm disabled:opacity-40"
            data-active={activo}
            disabled={deshabilitado}
            onClick={onToggle}
            aria-label={etiqueta}
            aria-pressed={activo}
        >
            <div className="gelia-switch-thumb shadow-md" />
        </button>
    );
}

function IndicadorEstado({ etiqueta, estado, detalle }) {
    return (
        <div className="flex items-start justify-between gap-3 text-xs py-2">
            <span className="font-semibold uppercase tracking-wide theme-text-muted">{etiqueta}</span>
            <span className="text-right theme-text-main">{detalle || estado}</span>
        </div>
    );
}

export default function PdvPreferenciasAlertasContenido({
    prefs,
    tonosAlertas = [],
    estadoConexion,
    estadoTts,
    onActivarPush = null,
    activandoPush = false,
}) {
    if (!prefs) return null;

    const {
        prefsUsuario,
        silencioTerminal,
        guardando,
        error,
        actualizarCanal,
        actualizarTono,
        alternarSilencioTerminal,
        estadoPush,
    } = prefs;

    const mensajePush = mensajeFallbackWebPushPdv(estadoPush);
    const mostrarActivarPush = estadoPush === PDV_PUSH_ESTADO.pendiente && typeof onActivarPush === 'function';

    const etiquetaConexion = conexionDegradadaPdv(estadoConexion) ? 'Degradada' : 'Conectada';
    const etiquetaTts = ({
        [PDV_TTS_ESTADO.listo]: 'Lista',
        [PDV_TTS_ESTADO.bloqueado]: 'Bloqueada (interactúa con la página)',
        [PDV_TTS_ESTADO.silenciado]: 'Silenciada en este terminal',
        [PDV_TTS_ESTADO.no_soportado]: 'No disponible',
    })[estadoTts] || estadoTts;

    const etiquetaPush = ({
        [PDV_PUSH_ESTADO.activo]: 'Activa',
        [PDV_PUSH_ESTADO.pendiente]: 'Pendiente de permiso',
        [PDV_PUSH_ESTADO.denegado]: 'Bloqueada',
        [PDV_PUSH_ESTADO.deshabilitado_usuario]: 'Desactivada por preferencia',
        [PDV_PUSH_ESTADO.servidor_deshabilitado]: 'No configurada en servidor',
        [PDV_PUSH_ESTADO.no_soportado]: 'No disponible',
    })[estadoPush] || estadoPush;

    return (
        <section data-pdv-preferencias-alertas-contenido>
            <p className="text-[11px] theme-text-muted m-0">
                Las preferencias de usuario se guardan en tu cuenta. El silencio de terminal solo afecta este dispositivo.
            </p>

            <div className="mt-4">
                <FilaPreferencia
                    icono={Volume2}
                    titulo="Timbre de asignación"
                    subtitulo="Tono al asignar, reatender o transferir turno"
                >
                    <SwitchPreferencia
                        activo={prefsUsuario.canales.sonido}
                        deshabilitado={guardando}
                        etiqueta="Activar timbre de asignación"
                        onToggle={() => actualizarCanal('sonido', !prefsUsuario.canales.sonido)}
                    />
                </FilaPreferencia>

                <FilaPreferencia
                    icono={Mic}
                    titulo="Anuncios por voz"
                    subtitulo="TTS de turnos en este terminal (independiente de Mensajería)"
                >
                    <SwitchPreferencia
                        activo={prefsUsuario.canales.voz}
                        deshabilitado={guardando}
                        etiqueta="Activar anuncios por voz"
                        onToggle={() => actualizarCanal('voz', !prefsUsuario.canales.voz)}
                    />
                </FilaPreferencia>

                <FilaPreferencia
                    icono={Smartphone}
                    titulo="Notificaciones push"
                    subtitulo="Avisos con la pestaña en segundo plano"
                >
                    <SwitchPreferencia
                        activo={prefsUsuario.canales.web_push}
                        deshabilitado={guardando}
                        etiqueta="Activar notificaciones push"
                        onToggle={() => actualizarCanal('web_push', !prefsUsuario.canales.web_push)}
                    />
                </FilaPreferencia>

                <FilaPreferencia
                    icono={silencioTerminal ? VolumeX : Volume2}
                    titulo="Silenciar este terminal"
                    subtitulo="Silencia timbre y voz solo en este equipo"
                >
                    <SwitchPreferencia
                        activo={silencioTerminal}
                        etiqueta="Silenciar terminal"
                        onToggle={() => alternarSilencioTerminal()}
                    />
                </FilaPreferencia>
            </div>

            <div className="flex flex-col sm:flex-row gap-2 mt-4">
                <label className="text-xs font-semibold uppercase tracking-wide theme-text-muted sm:self-center">
                    Tono
                </label>
                <select
                    className={`flex-1 min-h-[44px] ${THEME_SELECT}`}
                    value={prefsUsuario.tono_id}
                    disabled={guardando}
                    onChange={(e) => actualizarTono(e.target.value)}
                >
                    {(tonosAlertas.length > 0 ? tonosAlertas : [{ id: 'default', nombre: 'Campana clásica' }]).map((tono) => (
                        <option key={tono.id} value={tono.id}>{tono.nombre}</option>
                    ))}
                </select>
                <button
                    type="button"
                    className={`${GELIA_BTN_OUTLINE} min-h-[44px]`}
                    disabled={guardando || !prefsUsuario.canales.sonido || silencioTerminal}
                    onClick={() => reproducirTonoPdv(prefsUsuario.tono_id, tonosAlertas)}
                >
                    Probar
                </button>
            </div>

            {guardando && (
                <p className="text-xs flex items-center gap-2 m-0 mt-3" role="status">
                    <Loader2 className="w-3.5 h-3.5 animate-spin" aria-hidden />
                    Guardando preferencias…
                </p>
            )}

            {error && (
                <p className="text-xs theme-text-peligro m-0 mt-3" role="alert">{error}</p>
            )}

            <div className="gelia-panel-suave px-3 py-2 mt-4">
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-2">Estado de canales</p>
                <IndicadorEstado etiqueta="Tiempo real" estado={estadoConexion} detalle={etiquetaConexion} />
                <IndicadorEstado etiqueta="Voz" estado={estadoTts} detalle={etiquetaTts} />
                <IndicadorEstado etiqueta="Push" estado={estadoPush} detalle={etiquetaPush} />
            </div>

            {mensajePush && (
                <div
                    className="gelia-callout-aviso px-4 py-3 text-xs flex items-start gap-3 mt-4"
                    role="status"
                    data-pdv-push-fallback
                >
                    <BellOff className="w-4 h-4 shrink-0 mt-0.5 theme-text-aviso" aria-hidden />
                    <div className="space-y-2">
                        <p className="m-0 leading-snug theme-text-main">{mensajePush}</p>
                        {mostrarActivarPush && (
                            <button
                                type="button"
                                className="text-[10px] font-black uppercase tracking-widest underline theme-text-primario"
                                disabled={activandoPush}
                                onClick={onActivarPush}
                            >
                                {activandoPush ? 'Solicitando permiso…' : 'Activar notificaciones'}
                            </button>
                        )}
                    </div>
                </div>
            )}
        </section>
    );
}
