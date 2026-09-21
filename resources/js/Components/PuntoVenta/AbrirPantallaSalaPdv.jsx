import React, { useCallback, useEffect, useState } from 'react';
import { Copy, ExternalLink, Loader2, Monitor, Power, PowerOff } from 'lucide-react';
import ModalConfirmarAccion from '@/Pages/ControlPedidos/Partials/ModalConfirmarAccion';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '@/utils/geliaTheme';
import useEnlacePantallaSalaPdv from '@/hooks/useEnlacePantallaSalaPdv';

export default function AbrirPantallaSalaPdv({
    sucursalActiva = null,
    sucursalesAsignadas = [],
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
    const [modalDesactivar, setModalDesactivar] = useState(false);
    const [mensaje, setMensaje] = useState(null);
    const [copiado, setCopiado] = useState(false);

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
            if (resultado?.url) {
                abrirEnNuevaPestana(resultado.url);
                onAbierta?.(resultado.url);
            }
        } catch {
            // error ya expuesto en hook
        }
    }, [sucursalId, asegurarEnlace, abrirEnNuevaPestana, onAbierta]);

    const confirmarApertura = () => {
        setModalConfirmar(false);
        intentarAbrir();
    };

    const manejarActivar = async () => {
        if (!sucursalId) return;
        const resultado = await activarEnlace(sucursalId);
        if (resultado?.enlace_activo) {
            setMensaje('Pantalla activada. La TV puede seguir usando el mismo enlace.');
        }
    };

    const confirmarDesactivacion = async () => {
        setModalDesactivar(false);
        if (!sucursalId) return;
        const resultado = await desactivarEnlace(sucursalId);
        if (resultado && !resultado.enlace_activo) {
            setMensaje('Pantalla desactivada. El enlace permanece igual para reactivarla después.');
        }
    };

    const copiarEnlace = async () => {
        const url = estadoEnlace?.url;
        if (!url || typeof navigator === 'undefined' || !navigator.clipboard) return;

        try {
            await navigator.clipboard.writeText(url);
            setCopiado(true);
            window.setTimeout(() => setCopiado(false), 2000);
        } catch {
            setMensaje('No se pudo copiar el enlace al portapapeles.');
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
    const urlPermanente = estadoEnlace?.url ?? null;

    const bloqueEnlace = urlPermanente && (
        <div className="space-y-2">
            <p className="text-xs font-semibold uppercase tracking-wider theme-text-muted m-0">
                Enlace permanente de la TV
            </p>
            <div className="flex flex-col sm:flex-row gap-2">
                <input
                    type="text"
                    readOnly
                    value={urlPermanente}
                    className="flex-1 rounded-xl border px-3 py-2 text-sm theme-surface theme-text-main"
                    style={{ borderColor: 'color-mix(in srgb, var(--color-texto) 12%, transparent)' }}
                    data-pdv-pantalla-sala-url
                />
                <button
                    type="button"
                    className={`${THEME_BTN_SECONDARY} inline-flex items-center justify-center gap-2 shrink-0`}
                    onClick={copiarEnlace}
                    data-pdv-pantalla-sala-copiar
                >
                    <Copy className="w-4 h-4" aria-hidden />
                    {copiado ? 'Copiado' : 'Copiar'}
                </button>
            </div>
        </div>
    );

    const contenidoAcciones = (
        <div className="flex flex-col sm:flex-row flex-wrap gap-3">
            <button
                type="button"
                className={`${THEME_BTN_PRIMARY} inline-flex items-center justify-center gap-2`}
                onClick={() => setModalConfirmar(true)}
                disabled={cargando}
                data-pdv-pantalla-sala-abrir
            >
                {cargando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <ExternalLink className="w-4 h-4" aria-hidden />}
                Abrir pantalla de sala
            </button>
            {!urlPermanente && (
                <button
                    type="button"
                    className={`${THEME_BTN_SECONDARY} inline-flex items-center justify-center gap-2`}
                    onClick={() => obtenerEnlace(sucursalId)}
                    disabled={cargando}
                    data-pdv-pantalla-sala-provisionar
                >
                    Generar enlace permanente
                </button>
            )}
            {urlPermanente && !enlaceActivo && (
                <button
                    type="button"
                    className={`${THEME_BTN_SECONDARY} inline-flex items-center justify-center gap-2`}
                    onClick={manejarActivar}
                    disabled={cargando}
                    data-pdv-pantalla-sala-activar
                >
                    <Power className="w-4 h-4" aria-hidden />
                    Activar pantalla
                </button>
            )}
            {urlPermanente && enlaceActivo && (
                <button
                    type="button"
                    className={`${THEME_BTN_SECONDARY} inline-flex items-center justify-center gap-2`}
                    onClick={() => setModalDesactivar(true)}
                    disabled={cargando}
                    data-pdv-pantalla-sala-desactivar
                >
                    <PowerOff className="w-4 h-4" aria-hidden />
                    Desactivar pantalla
                </button>
            )}
        </div>
    );

    if (variante === 'compact') {
        return (
            <>
                <button
                    type="button"
                    className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2`}
                    onClick={() => setModalConfirmar(true)}
                    disabled={cargando}
                    data-pdv-pantalla-sala-abrir-compact
                >
                    <Monitor className="w-4 h-4" aria-hidden />
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
                    titulo="Desactivar pantalla"
                    mensaje="La TV seguirá usando el mismo enlace, pero mostrará que la pantalla está desactivada hasta que la vuelvas a encender."
                    etiquetaConfirmar="Desactivar"
                    variante="danger"
                    onClose={() => setModalDesactivar(false)}
                    onConfirm={confirmarDesactivacion}
                />
            </>
        );
    }

    return (
        <section className={`${geliaCardClass()} p-4 space-y-4`} data-pdv-pantalla-sala-acceso>
            <div className="flex items-start gap-3">
                <Monitor className="w-5 h-5 theme-text-muted shrink-0 mt-0.5" aria-hidden />
                <div className="space-y-1">
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        Pantalla de sala
                    </h2>
                    <p className="text-sm theme-text-muted m-0">
                        Enlace permanente por sucursal para la TV de turnos, sin sesión de usuario.
                    </p>
                </div>
            </div>

            {sucursalesAsignadas.length > 1 && (
                <p className="text-xs theme-text-muted m-0">
                    Si necesitas otra sucursal, cámbiala con el selector de sucursal activa antes de abrir.
                </p>
            )}

            {urlPermanente && (
                <p className="text-xs font-semibold m-0" style={{ color: enlaceActivo ? 'var(--color-exito)' : 'var(--color-advertencia, #b45309)' }}>
                    {enlaceActivo
                        ? 'Pantalla activa para esta sucursal.'
                        : 'Pantalla desactivada. El enlace permanece igual.'}
                </p>
            )}

            {bloqueEnlace}

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

            {contenidoAcciones}

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
                abierto={modalDesactivar}
                titulo="Desactivar pantalla"
                mensaje="La TV seguirá usando el mismo enlace, pero mostrará que la pantalla está desactivada hasta que la vuelvas a encender."
                etiquetaConfirmar="Desactivar"
                variante="danger"
                onClose={() => setModalDesactivar(false)}
                onConfirm={confirmarDesactivacion}
            />
        </section>
    );
}
