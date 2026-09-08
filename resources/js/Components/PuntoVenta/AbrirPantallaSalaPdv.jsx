import React, { useCallback, useEffect, useState } from 'react';
import { ExternalLink, Loader2, Monitor, RefreshCw, ShieldOff } from 'lucide-react';
import ModalConfirmarAccion from '@/Pages/ControlPedidos/Partials/ModalConfirmarAccion';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '@/utils/geliaTheme';
import useEnlacePantallaSalaPdv, { leerUrlPantallaSalaLocal } from '@/hooks/useEnlacePantallaSalaPdv';

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
        revocarEnlace,
        abrirEnNuevaPestana,
    } = useEnlacePantallaSalaPdv();

    const [modalConfirmar, setModalConfirmar] = useState(false);
    const [modalRegenerar, setModalRegenerar] = useState(false);
    const [mensaje, setMensaje] = useState(null);

    useEffect(() => {
        if (!sucursalId) return;
        consultarEstado(sucursalId);
    }, [sucursalId, consultarEstado]);

    const intentarAbrir = useCallback(async (regenerar = false) => {
        if (!sucursalId) return;

        setMensaje(null);

        if (!regenerar) {
            const urlLocal = leerUrlPantallaSalaLocal(sucursalId);
            if (urlLocal) {
                abrirEnNuevaPestana(urlLocal);
                onAbierta?.(urlLocal);
                return;
            }
        }

        try {
            const resultado = await obtenerEnlace(sucursalId, { regenerar });
            if (resultado?.url) {
                abrirEnNuevaPestana(resultado.url);
                onAbierta?.(resultado.url);
            }
        } catch (err) {
            if (err?.response?.status === 422) {
                setModalRegenerar(true);
            }
        }
    }, [sucursalId, obtenerEnlace, abrirEnNuevaPestana, onAbierta]);

    const confirmarApertura = () => {
        setModalConfirmar(false);
        intentarAbrir(false);
    };

    const confirmarRegeneracion = async () => {
        setModalRegenerar(false);
        await intentarAbrir(true);
    };

    const manejarRevocar = async () => {
        if (!sucursalId) return;
        const resultado = await revocarEnlace(sucursalId);
        if (resultado?.revocado) {
            setMensaje('Enlace revocado. La TV dejará de cargar en la siguiente visita.');
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
            {enlaceActivo && (
                <>
                    <button
                        type="button"
                        className={`${THEME_BTN_SECONDARY} inline-flex items-center justify-center gap-2`}
                        onClick={() => setModalRegenerar(true)}
                        disabled={cargando}
                        data-pdv-pantalla-sala-regenerar
                    >
                        <RefreshCw className="w-4 h-4" aria-hidden />
                        Regenerar enlace
                    </button>
                    <button
                        type="button"
                        className={`${THEME_BTN_SECONDARY} inline-flex items-center justify-center gap-2`}
                        onClick={manejarRevocar}
                        disabled={cargando}
                        data-pdv-pantalla-sala-revocar
                    >
                        <ShieldOff className="w-4 h-4" aria-hidden />
                        Revocar enlace
                    </button>
                </>
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
                    abierto={modalRegenerar}
                    titulo="Regenerar enlace"
                    mensaje="Ya existe un enlace activo. Regenerar invalidará el acceso actual de la TV y creará uno nuevo."
                    etiquetaConfirmar="Regenerar y abrir"
                    variante="danger"
                    onClose={() => setModalRegenerar(false)}
                    onConfirm={confirmarRegeneracion}
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
                        Abre la vista pública de turnos para <strong>{nombreSucursal}</strong> en una TV sin sesión de usuario.
                    </p>
                </div>
            </div>

            {sucursalesAsignadas.length > 1 && (
                <p className="text-xs theme-text-muted m-0">
                    Si necesitas otra sucursal, cámbiala con el selector de sucursal activa antes de abrir.
                </p>
            )}

            {enlaceActivo && (
                <p className="text-xs font-semibold m-0" style={{ color: 'var(--color-exito)' }}>
                    Hay un enlace activo para esta sucursal.
                </p>
            )}

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
                abierto={modalRegenerar}
                titulo="Regenerar enlace"
                mensaje="Ya existe un enlace activo. Regenerar invalidará el acceso actual de la TV y creará uno nuevo."
                etiquetaConfirmar="Regenerar y abrir"
                variante="danger"
                onClose={() => setModalRegenerar(false)}
                onConfirm={confirmarRegeneracion}
            />
        </section>
    );
}
