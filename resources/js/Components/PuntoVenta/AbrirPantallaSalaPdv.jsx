import React, { useCallback, useEffect, useState } from 'react';
import { TvIcon } from '@animateicons/react/lucide/tv-icon';
import {
    Check,
    Copy,
    ExternalLink,
    Images,
    Keyboard,
    Link2,
    Loader2,
    Monitor,
    Power,
    PowerOff,
    RectangleVertical,
} from 'lucide-react';
import ModalConfirmarAccion from '@/Pages/ControlPedidos/Partials/ModalConfirmarAccion';
import {
    GELIA_BTN_OUTLINE,
    GELIA_ESTADO_VIVO_TONO,
    GELIA_ICON_BOX,
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_INPUT,
} from '@/utils/geliaTheme';
import useEnlacePantallaSalaPdv from '@/hooks/useEnlacePantallaSalaPdv';
import useIconoEnControl from '@/hooks/useIconoEnControl';
import { urlKioskoConDisposicion } from '@/utils/rotacionMedioPublicidad';

const COLOR_ENLACES = 'var(--color-primario)';
const COLOR_TURNOS = 'var(--color-info)';
const COLOR_PUBLICIDAD = 'var(--color-aviso)';

function cajaIconoEstilo(color) {
    return {
        color,
        backgroundColor: `color-mix(in srgb, ${color} 10%, transparent)`,
        borderColor: `color-mix(in srgb, ${color} 30%, transparent)`,
    };
}

function EncabezadoSeccion({ icon: Icon, color, titulo, apoyo }) {
    return (
        <div className="flex items-start gap-3">
            <span className={`${GELIA_ICON_BOX} rounded-2xl`} style={cajaIconoEstilo(color)} aria-hidden>
                <Icon className="w-5 h-5" />
            </span>
            <div className="min-w-0 space-y-1">
                <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                    {titulo}
                </h2>
                {apoyo ? (
                    <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest m-0">
                        {apoyo}
                    </p>
                ) : null}
            </div>
        </div>
    );
}

function CampoEnlace({
    etiqueta,
    url,
    copiado,
    onCopiar,
    dataUrl,
    dataCopiar,
    destacado = false,
}) {
    if (!url) return null;

    return (
        <div className="space-y-2">
            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                {etiqueta}
            </p>
            <div className="flex flex-col sm:flex-row gap-2">
                <input
                    type="text"
                    readOnly
                    value={url}
                    className={`${THEME_INPUT} flex-1 min-h-[44px] ${destacado ? 'font-mono text-base tracking-wide' : 'text-sm'}`}
                    data-pdv-pantalla-sala-url={dataUrl}
                />
                <button
                    type="button"
                    className={`${GELIA_BTN_OUTLINE} inline-flex items-center justify-center gap-2 shrink-0 min-h-[44px] px-4 hover:border-[var(--color-primario)]`}
                    onClick={onCopiar}
                    data-pdv-pantalla-sala-copiar={dataCopiar}
                >
                    {copiado ? <Check className="w-4 h-4" aria-hidden /> : <Copy className="w-4 h-4" aria-hidden />}
                    {copiado ? 'Copiado' : 'Copiar'}
                </button>
            </div>
        </div>
    );
}

export default function AbrirPantallaSalaPdv({
    sucursalActiva = null,
    sucursalesAsignadas: _sucursalesAsignadas = [],
    variante = 'card',
    onAbierta = null,
}) {
    const sucursalId = sucursalActiva?.id ?? null;
    const nombreSucursal = sucursalActiva?.nombre ?? 'la sucursal activa';

    const {
        cargando,
        error,
        estadoEnlace,
        consultarEstado,
        obtenerEnlace,
        activarEnlace,
        desactivarEnlace,
        abrirEnNuevaPestana,
    } = useEnlacePantallaSalaPdv();

    const [modalConfirmar, setModalConfirmar] = useState(false);
    const [modalConfirmarKiosko, setModalConfirmarKiosko] = useState(false);
    const [modalDesactivar, setModalDesactivar] = useState(false);
    const [mensaje, setMensaje] = useState(null);
    const [copiadoId, setCopiadoId] = useState(null);
    const [disposicionKiosko, setDisposicionKiosko] = useState('auto');
    const { ref: iconoTvRef, triggerProps: iconoTvHover } = useIconoEnControl();

    useEffect(() => {
        if (!sucursalId) return;
        consultarEstado(sucursalId);
    }, [sucursalId, consultarEstado]);

    const asegurarEnlace = useCallback(async () => {
        if (!sucursalId) return null;

        if (estadoEnlace?.url) {
            return estadoEnlace;
        }

        return obtenerEnlace(sucursalId);
    }, [sucursalId, estadoEnlace, obtenerEnlace]);

    const intentarAbrir = useCallback(async () => {
        if (!sucursalId) return;

        setMensaje(null);

        try {
            const resultado = await asegurarEnlace();
            const url = resultado?.url_corta || resultado?.url;
            if (url) {
                abrirEnNuevaPestana(url);
                onAbierta?.(url);
            }
        } catch {
            // error ya expuesto en hook
        }
    }, [sucursalId, asegurarEnlace, abrirEnNuevaPestana, onAbierta]);

    const intentarAbrirKiosko = useCallback(async () => {
        if (!sucursalId) return;

        setMensaje(null);

        try {
            const resultado = await asegurarEnlace();
            const url = urlKioskoConDisposicion(
                resultado?.url_kiosko_corta || resultado?.url_kiosko,
                disposicionKiosko,
            );
            if (url) {
                abrirEnNuevaPestana(url);
                onAbierta?.(url);
            }
        } catch {
            // error ya expuesto en hook
        }
    }, [sucursalId, asegurarEnlace, abrirEnNuevaPestana, onAbierta, disposicionKiosko]);

    const confirmarApertura = () => {
        setModalConfirmar(false);
        intentarAbrir();
    };

    const confirmarAperturaKiosko = () => {
        setModalConfirmarKiosko(false);
        intentarAbrirKiosko();
    };

    const manejarActivar = async () => {
        if (!sucursalId) return;
        const resultado = await activarEnlace(sucursalId);
        if (resultado?.enlace_activo) {
            setMensaje('Pantallas activadas. La TV de turnos y el kiosko pueden seguir usando los mismos enlaces.');
        }
    };

    const confirmarDesactivacion = async () => {
        setModalDesactivar(false);
        if (!sucursalId) return;
        const resultado = await desactivarEnlace(sucursalId);
        if (resultado && !resultado.enlace_activo) {
            setMensaje('Pantallas desactivadas. Los enlaces permanecen iguales para reactivarlas después.');
        }
    };

    const copiarTexto = async (url, id) => {
        if (!url || typeof navigator === 'undefined' || !navigator.clipboard) return;

        try {
            await navigator.clipboard.writeText(url);
            setCopiadoId(id);
            window.setTimeout(() => {
                setCopiadoId((actual) => (actual === id ? null : actual));
            }, 2000);
        } catch {
            setMensaje('No se pudo copiar al portapapeles.');
        }
    };

    if (!sucursalId) {
        return (
            <p className="text-sm theme-text-muted m-0">
                Selecciona una sucursal activa para abrir la pantalla de sala.
            </p>
        );
    }

    const enlaceActivo = Boolean(estadoEnlace?.enlace_activo);
    const codigoCorto = estadoEnlace?.codigo_corto ?? null;
    const urlTurnos = estadoEnlace?.url ?? null;
    const urlTurnosCorta = estadoEnlace?.url_corta ?? null;
    const urlKioskoLarga = urlKioskoConDisposicion(estadoEnlace?.url_kiosko, disposicionKiosko);
    const urlKioskoCorta = urlKioskoConDisposicion(estadoEnlace?.url_kiosko_corta, disposicionKiosko);

    if (variante === 'compact') {
        return (
            <>
                <button
                    type="button"
                    className="inline-flex items-center gap-2 min-h-[44px] px-4 py-2.5 rounded-xl border text-[10px] font-black uppercase tracking-widest theme-text-main disabled:opacity-60 disabled:cursor-not-allowed"
                    style={{
                        backgroundColor: 'var(--theme-element-bg)',
                        borderColor: 'var(--theme-element-border)',
                    }}
                    {...iconoTvHover}
                    onClick={() => setModalConfirmar(true)}
                    disabled={cargando}
                    data-pdv-pantalla-sala-abrir-compact
                >
                    {cargando ? (
                        <Loader2 className="w-4 h-4 animate-spin" aria-hidden />
                    ) : (
                        <TvIcon ref={iconoTvRef} size={16} duration={1.5} aria-hidden />
                    )}
                    Pantalla de sala
                </button>
                <ModalConfirmarAccion
                    abierto={modalConfirmar}
                    titulo="Abrir pantalla de sala"
                    mensaje={`Se abrirá la TV pública de turnos para ${nombreSucursal} en una pestaña nueva. Confirma que es la sucursal correcta.`}
                    etiquetaConfirmar="Abrir en pestaña nueva"
                    variante="primary"
                    onClose={() => setModalConfirmar(false)}
                    onConfirm={confirmarApertura}
                />
                <ModalConfirmarAccion
                    abierto={modalDesactivar}
                    titulo="Desactivar pantallas"
                    mensaje="La TV de turnos y el kiosko seguirán usando los mismos enlaces, pero mostrarán que la pantalla está desactivada hasta que las vuelvas a encender."
                    etiquetaConfirmar="Desactivar"
                    variante="danger"
                    onClose={() => setModalDesactivar(false)}
                    onConfirm={confirmarDesactivacion}
                />
            </>
        );
    }

    return (
        <div className="space-y-4">
            <section className={`${geliaCardClass()} p-6 md:p-8 space-y-6`} data-pdv-pantalla-sala-acceso>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <EncabezadoSeccion
                        icon={Link2}
                        color={COLOR_ENLACES}
                        titulo="Enlaces_"
                        apoyo="Un código para la TV y un enlace de respaldo. Encender o apagar no cambia las URLs."
                    />
                    {urlTurnos ? (
                        <span
                            className={`gelia-estado-vivo gelia-estado-vivo--compacto inline-flex items-center gap-1.5 self-start ${enlaceActivo ? GELIA_ESTADO_VIVO_TONO.exito : GELIA_ESTADO_VIVO_TONO.aviso}`}
                        >
                            {enlaceActivo ? <Power className="w-3.5 h-3.5" aria-hidden /> : <PowerOff className="w-3.5 h-3.5" aria-hidden />}
                            {enlaceActivo ? 'Activas' : 'Apagadas'}
                        </span>
                    ) : null}
                </div>

                {codigoCorto ? (
                    <div
                        className="rounded-[1.75rem] border theme-border p-5 md:p-6 flex flex-col sm:flex-row sm:items-center gap-4"
                        style={{
                            backgroundColor: 'color-mix(in srgb, var(--color-primario) 6%, transparent)',
                            borderColor: 'color-mix(in srgb, var(--color-primario) 22%, transparent)',
                        }}
                        data-pdv-pantalla-codigo
                    >
                        <span className={`${GELIA_ICON_BOX} rounded-2xl`} style={cajaIconoEstilo(COLOR_ENLACES)} aria-hidden>
                            <Keyboard className="w-6 h-6" />
                        </span>
                        <div className="min-w-0 flex-1 space-y-1">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                Código para teclear
                            </p>
                            <p className="font-mono text-3xl md:text-4xl font-black tracking-[0.28em] theme-text-main m-0 leading-none">
                                {codigoCorto}
                            </p>
                            <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted m-0">
                                Turnos /s/{codigoCorto} · Publicidad /k/{codigoCorto}
                            </p>
                        </div>
                        <button
                            type="button"
                            className={`${GELIA_BTN_OUTLINE} inline-flex items-center justify-center gap-2 min-h-[44px] hover:border-[var(--color-primario)]`}
                            onClick={() => copiarTexto(codigoCorto, 'codigo')}
                            aria-label="Copiar código corto"
                        >
                            {copiadoId === 'codigo' ? <Check className="w-4 h-4" aria-hidden /> : <Copy className="w-4 h-4" aria-hidden />}
                            {copiadoId === 'codigo' ? 'Copiado' : 'Copiar código'}
                        </button>
                    </div>
                ) : null}

                {error && (
                    <p className="text-sm m-0" style={{ color: 'var(--color-peligro)' }} role="alert">
                        {error}
                    </p>
                )}

                {mensaje && (
                    <p className="text-sm theme-text-muted m-0" role="status">
                        {mensaje}
                    </p>
                )}

                <div className="flex flex-col sm:flex-row flex-wrap gap-3">
                    {!urlTurnos && (
                        <button
                            type="button"
                            className={`${GELIA_BTN_OUTLINE} inline-flex items-center justify-center gap-2 min-h-[44px] px-4 hover:border-[var(--color-primario)]`}
                            onClick={() => obtenerEnlace(sucursalId)}
                            disabled={cargando}
                            data-pdv-pantalla-sala-provisionar
                        >
                            {cargando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <Link2 className="w-4 h-4" aria-hidden />}
                            Generar enlace permanente
                        </button>
                    )}
                    {urlTurnos && !enlaceActivo && (
                        <button
                            type="button"
                            className={`${GELIA_BTN_OUTLINE} inline-flex items-center justify-center gap-2 min-h-[44px] px-4 hover:border-[var(--color-primario)]`}
                            onClick={manejarActivar}
                            disabled={cargando}
                            data-pdv-pantalla-sala-activar
                        >
                            <Power className="w-4 h-4" aria-hidden />
                            Activar pantallas
                        </button>
                    )}
                    {urlTurnos && enlaceActivo && (
                        <button
                            type="button"
                            className={`${GELIA_BTN_OUTLINE} inline-flex items-center justify-center gap-2 min-h-[44px] px-4 hover:border-[var(--color-primario)]`}
                            onClick={() => setModalDesactivar(true)}
                            disabled={cargando}
                            data-pdv-pantalla-sala-desactivar
                        >
                            <PowerOff className="w-4 h-4" aria-hidden />
                            Desactivar pantallas
                        </button>
                    )}
                </div>
            </section>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 items-stretch">
                <section className={`${geliaCardClass()} p-6 md:p-8 space-y-6 flex flex-col`} data-pdv-pantalla-sala-turnos>
                    <EncabezadoSeccion
                        icon={Monitor}
                        color={COLOR_TURNOS}
                        titulo="TV de turnos_"
                        apoyo="Publicidad y cola de atención, sin iniciar sesión."
                    />

                    <div className="flex-1 space-y-4">
                        {urlTurnos ? (
                            <>
                                <CampoEnlace
                                    etiqueta="Para teclear en la TV"
                                    url={urlTurnosCorta}
                                    copiado={copiadoId === 'turnos-corta'}
                                    onCopiar={() => copiarTexto(urlTurnosCorta, 'turnos-corta')}
                                    dataUrl="turnos-corta"
                                    dataCopiar="turnos-corta"
                                    destacado
                                />
                                <CampoEnlace
                                    etiqueta="Enlace permanente"
                                    url={urlTurnos}
                                    copiado={copiadoId === 'turnos'}
                                    onCopiar={() => copiarTexto(urlTurnos, 'turnos')}
                                    dataUrl="turnos"
                                    dataCopiar="turnos"
                                />
                            </>
                        ) : (
                            <p className="text-xs theme-text-muted m-0">
                                Genera el enlace permanente para ver las URLs de esta TV.
                            </p>
                        )}
                    </div>

                    <button
                        type="button"
                        className={`${THEME_BTN_PRIMARY} inline-flex items-center justify-center gap-2 min-h-[44px] w-full mt-auto`}
                        onClick={() => setModalConfirmar(true)}
                        disabled={cargando}
                        data-pdv-pantalla-sala-abrir
                    >
                        {cargando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <ExternalLink className="w-4 h-4" aria-hidden />}
                        Abrir pantalla de sala
                    </button>
                </section>

                <section className={`${geliaCardClass()} p-6 md:p-8 space-y-6 flex flex-col`} data-pdv-pantalla-kiosko-acceso>
                    <EncabezadoSeccion
                        icon={Images}
                        color={COLOR_PUBLICIDAD}
                        titulo="Publicidad_"
                        apoyo="Kiosko a pantalla completa solo con la playlist. En vertical llena el 9:16."
                    />

                    <div className="space-y-2">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                            Disposición del kiosko
                        </p>
                        <div
                            className="gelia-segment grid grid-cols-1 sm:grid-cols-2 gap-1 p-1"
                            role="group"
                            aria-label="Disposición del kiosko"
                        >
                            <button
                                type="button"
                                className="gelia-segment-btn min-h-[44px] w-full inline-flex items-center justify-center gap-2"
                                data-active={disposicionKiosko === 'auto'}
                                aria-pressed={disposicionKiosko === 'auto'}
                                onClick={() => setDisposicionKiosko('auto')}
                            >
                                <Monitor className="w-4 h-4" aria-hidden />
                                Según la pantalla
                            </button>
                            <button
                                type="button"
                                className="gelia-segment-btn min-h-[44px] w-full inline-flex items-center justify-center gap-2"
                                data-active={disposicionKiosko === 'vertical'}
                                aria-pressed={disposicionKiosko === 'vertical'}
                                onClick={() => setDisposicionKiosko('vertical')}
                            >
                                <RectangleVertical className="w-4 h-4" aria-hidden />
                                Forzar vertical 9:16
                            </button>
                        </div>
                        <p className="text-xs theme-text-muted m-0">
                            Si la TV está girada y el equipo sigue en horizontal, usa forzar vertical. Si el equipo ya está en vertical, deja según la pantalla.
                        </p>
                    </div>

                    <div className="flex-1 space-y-4">
                        {urlKioskoLarga ? (
                            <>
                                <CampoEnlace
                                    etiqueta="Para teclear en la TV"
                                    url={urlKioskoCorta}
                                    copiado={copiadoId === 'kiosko-corta'}
                                    onCopiar={() => copiarTexto(urlKioskoCorta, 'kiosko-corta')}
                                    dataUrl="kiosko-corta"
                                    dataCopiar="kiosko-corta"
                                    destacado
                                />
                                <CampoEnlace
                                    etiqueta="Enlace permanente"
                                    url={urlKioskoLarga}
                                    copiado={copiadoId === 'kiosko'}
                                    onCopiar={() => copiarTexto(urlKioskoLarga, 'kiosko')}
                                    dataUrl="kiosko"
                                    dataCopiar="kiosko"
                                />
                            </>
                        ) : (
                            <p className="text-xs theme-text-muted m-0">
                                Genera el enlace permanente para ver las URLs de esta pantalla.
                            </p>
                        )}
                    </div>

                    <button
                        type="button"
                        className={`${THEME_BTN_PRIMARY} inline-flex items-center justify-center gap-2 min-h-[44px] w-full mt-auto`}
                        onClick={() => setModalConfirmarKiosko(true)}
                        disabled={cargando}
                        data-pdv-pantalla-kiosko-abrir
                    >
                        {cargando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <ExternalLink className="w-4 h-4" aria-hidden />}
                        Abrir pantalla de publicidad
                    </button>
                </section>
            </div>

            <ModalConfirmarAccion
                abierto={modalConfirmar}
                titulo="Confirmar sucursal"
                mensaje={`¿Abrir la pantalla de sala de ${nombreSucursal} en una pestaña nueva?`}
                etiquetaConfirmar="Abrir pantalla"
                variante="primary"
                onClose={() => setModalConfirmar(false)}
                onConfirm={confirmarApertura}
            />
            <ModalConfirmarAccion
                abierto={modalConfirmarKiosko}
                titulo="Confirmar sucursal"
                mensaje={`¿Abrir la pantalla de publicidad de ${nombreSucursal} en una pestaña nueva?`}
                etiquetaConfirmar="Abrir kiosko"
                variante="primary"
                onClose={() => setModalConfirmarKiosko(false)}
                onConfirm={confirmarAperturaKiosko}
            />
            <ModalConfirmarAccion
                abierto={modalDesactivar}
                titulo="Desactivar pantallas"
                mensaje="La TV de turnos y el kiosko seguirán usando los mismos enlaces, pero mostrarán que la pantalla está desactivada hasta que las vuelvas a encender."
                etiquetaConfirmar="Desactivar"
                variante="danger"
                onClose={() => setModalDesactivar(false)}
                onConfirm={confirmarDesactivacion}
            />
        </div>
    );
}
