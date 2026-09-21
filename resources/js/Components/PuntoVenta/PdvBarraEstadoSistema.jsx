import React, { useCallback, useState } from 'react';
import { createPortal } from 'react-dom';
import {
    Bell,
    BellOff,
    MicOff,
    Radio,
    RadioTower,
    ShieldAlert,
    Volume2,
    VolumeX,
    Wifi,
    WifiOff,
} from 'lucide-react';
import { usePdvAlertContext } from '@/Components/PuntoVenta/PdvAlertProvider';
import PdvIndicadorEstadoVivo from '@/Components/PuntoVenta/PdvIndicadorEstadoVivo';
import {
    conexionDegradadaPdv,
    mensajeConexionDegradadaPdv,
    PDV_ESTADO_CONEXION,
} from '@/utils/pdvAlertQueueUtils';
import { PDV_PUSH_ESTADO, reproducirTonoPdv } from '@/utils/pdvAlertasPrefs';
import { etiquetaAudioIndicadorPdv, PDV_TTS_ESTADO } from '@/utils/pdvSpeechUtils';
import {
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    deferModalAction,
} from '@/utils/geliaTheme';

function ModalAccionEstado({ abierto, titulo, mensaje, etiquetaAccion, onAccion, onCerrar, cargando = false }) {
    if (!abierto) return null;

    const cerrar = (e) => {
        e?.stopPropagation?.();
        deferModalAction(onCerrar);
    };

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-center py-4`}
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 10)' }}
            onClick={cerrar}
        >
            <div
                className={`${THEME_MODAL_SHELL} max-w-sm w-full p-6 md:p-8 space-y-4 modal-pop text-left`}
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="pdv-estado-accion-titulo"
            >
                <div>
                    <h3 id="pdv-estado-accion-titulo" className="text-sm font-black uppercase theme-text-main m-0">
                        {titulo}
                    </h3>
                    <p className="text-sm theme-text-muted mt-2 m-0 leading-relaxed">{mensaje}</p>
                </div>
                <div className="flex flex-col gap-2">
                    {etiquetaAccion && onAccion && (
                        <button
                            type="button"
                            className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact w-full min-h-[44px]`}
                            disabled={cargando}
                            onClick={(e) => {
                                e.stopPropagation();
                                onAccion();
                            }}
                        >
                            {cargando ? 'Procesando…' : etiquetaAccion}
                        </button>
                    )}
                    <button
                        type="button"
                        className={`${THEME_BTN_SECONDARY} w-full min-h-[44px] rounded-xl border theme-border theme-text-main`}
                        onClick={cerrar}
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}

function resolverIndicadorConexion(estadoConexion) {
    if (estadoConexion === PDV_ESTADO_CONEXION.conectado) {
        return {
            icono: Wifi,
            tono: 'exito',
            pulsando: false,
            etiqueta: 'Tiempo real conectado',
            titulo: 'En línea',
            clickeable: false,
        };
    }

    const degradada = conexionDegradadaPdv(estadoConexion);
    const reconectando = estadoConexion === PDV_ESTADO_CONEXION.conectando;

    return {
        icono: WifiOff,
        tono: reconectando ? 'aviso' : 'error',
        pulsando: degradada,
        etiqueta: degradada ? mensajeConexionDegradadaPdv(estadoConexion) : 'Sin conexión',
        titulo: degradada ? mensajeConexionDegradadaPdv(estadoConexion) : 'Sin conexión',
        clickeable: degradada,
    };
}

function resolverIndicadorAudio({ estadoTts, silenciado, vozHabilitada, sonidoHabilitado }) {
    const canalesActivos = vozHabilitada || sonidoHabilitado;
    const { titulo, etiqueta } = etiquetaAudioIndicadorPdv(estadoTts, {
        silenciado,
        canalesActivos,
    });

    if (!canalesActivos) {
        return {
            icono: VolumeX,
            tono: 'neutro',
            pulsando: false,
            etiqueta,
            titulo,
            clickeable: false,
            deshabilitado: true,
        };
    }

    if (silenciado) {
        return {
            icono: VolumeX,
            tono: 'neutro',
            pulsando: false,
            etiqueta,
            titulo,
            clickeable: true,
            accion: 'silenciado',
        };
    }

    if (estadoTts === PDV_TTS_ESTADO.listo) {
        return {
            icono: Volume2,
            tono: 'exito',
            pulsando: false,
            etiqueta,
            titulo,
            clickeable: false,
        };
    }

    if (estadoTts === PDV_TTS_ESTADO.bloqueado) {
        return {
            icono: Volume2,
            tono: 'aviso',
            pulsando: true,
            etiqueta,
            titulo,
            clickeable: true,
            accion: 'desbloquear',
        };
    }

    if (estadoTts === PDV_TTS_ESTADO.no_soportado) {
        return {
            icono: MicOff,
            tono: 'neutro',
            pulsando: false,
            etiqueta,
            titulo,
            clickeable: true,
            accion: 'no_soportado',
        };
    }

    return {
        icono: Volume2,
        tono: 'neutro',
        pulsando: false,
        etiqueta,
        titulo,
        clickeable: false,
    };
}

function resolverIndicadorPush(estadoPush) {
    if (estadoPush === PDV_PUSH_ESTADO.activo) {
        return {
            icono: Bell,
            tono: 'exito',
            pulsando: false,
            etiqueta: 'Notificaciones push activas',
            titulo: 'Push activo',
            clickeable: false,
        };
    }

    if (estadoPush === PDV_PUSH_ESTADO.pendiente) {
        return {
            icono: BellOff,
            tono: 'aviso',
            pulsando: true,
            etiqueta: 'Notificaciones push pendientes de permiso',
            titulo: 'Toca para activar notificaciones',
            clickeable: true,
            accion: 'activar_push',
        };
    }

    if (estadoPush === PDV_PUSH_ESTADO.denegado) {
        return {
            icono: BellOff,
            tono: 'error',
            pulsando: false,
            etiqueta: 'Notificaciones push bloqueadas por el navegador',
            titulo: 'Push bloqueado',
            clickeable: true,
            accion: 'push_denegado',
        };
    }

    return {
        icono: BellOff,
        tono: 'neutro',
        pulsando: false,
        etiqueta: 'Notificaciones push no activas',
        titulo: 'Push inactivo',
        clickeable: false,
        deshabilitado: true,
    };
}

function resolverIndicadorTerminal(terminal) {
    if (!terminal) return null;

    const { estado, terminalActiva } = terminal;
    const mapa = {
        no_autorizada: { icono: ShieldAlert, tono: 'neutro', pulsando: false },
        disponible: { icono: Radio, tono: 'neutro', pulsando: false },
        terminal_activa: { icono: RadioTower, tono: 'exito', pulsando: false },
        otra_terminal_activa: { icono: Radio, tono: 'aviso', pulsando: false },
        conexion_perdida: { icono: WifiOff, tono: 'error', pulsando: true },
    };
    const base = mapa[estado] || mapa.disponible;

    const titulos = {
        no_autorizada: 'Terminal no autorizada',
        disponible: 'Terminal disponible',
        terminal_activa: 'Terminal de alertas activa',
        otra_terminal_activa: 'Otra terminal activa',
        conexion_perdida: 'Conexión de terminal perdida',
    };

    return {
        ...base,
        etiqueta: titulos[estado] || 'Terminal de alertas',
        titulo: titulos[estado] || 'Terminal de alertas',
        clickeable: true,
        accion: 'terminal',
        terminalActiva,
    };
}

export default function PdvBarraEstadoSistema({
    onAbrirConfiguracion = null,
    seccionInicialModal = null,
    className = '',
}) {
    const ctx = usePdvAlertContext();
    const [modalAccion, setModalAccion] = useState(null);
    const [reintentando, setReintentando] = useState(false);

    if (!ctx) return null;

    const {
        estadoConexion,
        estadoTts,
        silenciado,
        desbloquearAudio,
        activarPush,
        activandoPush,
        resincronizar,
        prefs,
        estadoPush,
        terminal,
        capacidades,
        tonosAlertas = [],
        alternarSilencioTts,
    } = ctx;

    const vozHabilitada = prefs?.prefsUsuario?.canales?.voz;
    const sonidoHabilitado = prefs?.prefsUsuario?.canales?.sonido;
    const mostrarTerminal = Boolean(capacidades?.alertas_sucursal);

    const conexion = resolverIndicadorConexion(estadoConexion);
    const audio = resolverIndicadorAudio({
        estadoTts,
        silenciado,
        vozHabilitada,
        sonidoHabilitado,
    });
    const push = resolverIndicadorPush(estadoPush);
    const terminalInfo = mostrarTerminal ? resolverIndicadorTerminal(terminal) : null;

    const cerrarModal = useCallback(() => setModalAccion(null), []);

    const manejarConexion = useCallback(() => {
        if (!conexionDegradadaPdv(estadoConexion)) return;
        setModalAccion({
            titulo: 'Conexión en tiempo real',
            mensaje: mensajeConexionDegradadaPdv(estadoConexion),
            etiquetaAccion: 'Reintentar',
            onAccion: async () => {
                setReintentando(true);
                try {
                    await resincronizar?.();
                } finally {
                    setReintentando(false);
                    cerrarModal();
                }
            },
        });
    }, [estadoConexion, resincronizar, cerrarModal]);

    const manejarAudio = useCallback(() => {
        if (audio.accion === 'desbloquear') {
            desbloquearAudio?.();
            if (sonidoHabilitado && !silenciado) {
                reproducirTonoPdv(prefs?.prefsUsuario?.tono_id, tonosAlertas);
            }
            return;
        }

        if (audio.accion === 'silenciado') {
            alternarSilencioTts?.();
            return;
        }

        if (audio.accion === 'no_soportado') {
            setModalAccion({
                titulo: 'Audio no disponible',
                mensaje: 'Este navegador no reproduce anuncios por voz. Las alertas visuales continúan activas.',
            });
        }
    }, [
        audio.accion,
        desbloquearAudio,
        sonidoHabilitado,
        silenciado,
        prefs,
        tonosAlertas,
        alternarSilencioTts,
    ]);

    const manejarPush = useCallback(() => {
        if (push.accion === 'activar_push') {
            activarPush?.();
            return;
        }

        if (push.accion === 'push_denegado') {
            setModalAccion({
                titulo: 'Notificaciones bloqueadas',
                mensaje: 'El navegador bloqueó las notificaciones push. Revísalo en la configuración del sitio.',
            });
        }
    }, [push.accion, activarPush]);

    const manejarTerminal = useCallback(() => {
        if (typeof onAbrirConfiguracion === 'function') {
            onAbrirConfiguracion(seccionInicialModal || 'terminal');
        }
    }, [onAbrirConfiguracion, seccionInicialModal]);

    return (
        <>
            <div
                className={`inline-flex items-center gap-1.5 ${className}`}
                role="group"
                aria-label="Estado del sistema de alertas"
                data-pdv-barra-estado
            >
                <PdvIndicadorEstadoVivo
                    icono={conexion.icono}
                    etiqueta={conexion.etiqueta}
                    titulo={conexion.titulo}
                    tono={conexion.tono}
                    pulsando={conexion.pulsando}
                    clickeable={conexion.clickeable}
                    onClick={manejarConexion}
                    dataAtributo={`conexion-${estadoConexion}`}
                />
                <PdvIndicadorEstadoVivo
                    icono={audio.icono}
                    etiqueta={audio.etiqueta}
                    titulo={audio.titulo}
                    tono={audio.tono}
                    pulsando={audio.pulsando}
                    clickeable={audio.clickeable}
                    deshabilitado={audio.deshabilitado}
                    onClick={manejarAudio}
                    dataAtributo={`audio-${estadoTts}`}
                />
                {(vozHabilitada || sonidoHabilitado || prefs?.prefsUsuario?.canales?.web_push) && (
                    <PdvIndicadorEstadoVivo
                        icono={push.icono}
                        etiqueta={push.etiqueta}
                        titulo={push.titulo}
                        tono={push.tono}
                        pulsando={push.pulsando}
                        clickeable={push.clickeable}
                        deshabilitado={push.deshabilitado}
                        onClick={push.accion === 'activar_push' ? manejarPush : (
                            push.clickeable ? manejarPush : undefined
                        )}
                        dataAtributo={`push-${estadoPush}`}
                    />
                )}
                {terminalInfo && (
                    <PdvIndicadorEstadoVivo
                        icono={terminalInfo.icono}
                        etiqueta={terminalInfo.etiqueta}
                        titulo={terminalInfo.titulo}
                        tono={terminalInfo.tono}
                        pulsando={terminalInfo.pulsando}
                        clickeable={terminalInfo.clickeable}
                        onClick={manejarTerminal}
                        dataAtributo={`terminal-${terminal?.estado}`}
                    />
                )}
            </div>

            <ModalAccionEstado
                abierto={Boolean(modalAccion)}
                titulo={modalAccion?.titulo}
                mensaje={modalAccion?.mensaje}
                etiquetaAccion={modalAccion?.etiquetaAccion}
                onAccion={modalAccion?.onAccion}
                onCerrar={cerrarModal}
                cargando={reintentando || activandoPush}
            />
        </>
    );
}
