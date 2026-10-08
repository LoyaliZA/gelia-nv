import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Check, RotateCw, Smartphone, X } from 'lucide-react';
import FirmaCanvas from '../../../../Components/Rh/FirmaCanvas';
import { esDispositivoCampo } from '../../../Activos/Partials/useDispositivoCampo';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import { BTN_SECONDARY } from './resguardosStyles';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import { normalizarDataUrlFirma } from './entregaResguardoUtils';

async function bloquearOrientacionHorizontal() {
    if (!esDispositivoCampo()) return;
    try {
        await screen.orientation?.lock?.('landscape');
    } catch {
        // ponytail: en iOS/Safari el lock puede requerir gesto o pantalla completa nativa
    }
}

function liberarOrientacion() {
    try {
        screen.orientation?.unlock?.();
    } catch {
        // sin efecto en navegadores que no soportan unlock
    }
}

export default function OverlayFirmaEntregaPantallaCompleta({
    abierto,
    dataUrlInicial = null,
    deshabilitado = false,
    onGuardar,
    onCerrar,
}) {
    const firmaRef = useRef(null);
    const [esVertical, setEsVertical] = useState(false);
    const [guardando, setGuardando] = useState(false);
    const [tieneTrazo, setTieneTrazo] = useState(false);
    const [confirmarAlSalir, setConfirmarAlSalir] = useState(false);
    const requiereHorizontal = esDispositivoCampo();

    const actualizarOrientacion = useCallback(() => {
        setEsVertical(window.matchMedia?.('(orientation: portrait)')?.matches ?? false);
    }, []);

    useEffect(() => {
        if (!abierto) return undefined;

        setConfirmarAlSalir(false);
        setTieneTrazo(Boolean(dataUrlInicial));
        actualizarOrientacion();
        document.body.style.overflow = 'hidden';
        bloquearOrientacionHorizontal();

        window.addEventListener('resize', actualizarOrientacion);
        const orientacion = window.matchMedia?.('(orientation: portrait)');
        orientacion?.addEventListener?.('change', actualizarOrientacion);

        return () => {
            document.body.style.overflow = '';
            liberarOrientacion();
            window.removeEventListener('resize', actualizarOrientacion);
            orientacion?.removeEventListener?.('change', actualizarOrientacion);
        };
    }, [abierto, actualizarOrientacion, dataUrlInicial]);

    useEffect(() => {
        if (!abierto) return;
        const timer = window.setTimeout(() => {
            if (dataUrlInicial) {
                firmaRef.current?.loadDataUrl?.(dataUrlInicial);
            }
        }, 50);
        return () => window.clearTimeout(timer);
    }, [abierto, dataUrlInicial]);

    const cerrarSinGuardar = useCallback(() => {
        if (guardando || deshabilitado) return;
        setConfirmarAlSalir(false);
        onCerrar?.();
    }, [deshabilitado, guardando, onCerrar]);

    const solicitarCerrar = useCallback(() => {
        if (guardando || deshabilitado) return;
        const hayTrazo = tieneTrazo || firmaRef.current?.hasStroke?.();
        if (hayTrazo) {
            setConfirmarAlSalir(true);
            return;
        }
        cerrarSinGuardar();
    }, [cerrarSinGuardar, deshabilitado, guardando, tieneTrazo]);

    const guardarFirma = useCallback(async () => {
        if (deshabilitado || guardando) return;
        if (requiereHorizontal && esVertical) return;
        if (!firmaRef.current?.hasStroke?.()) return;

        setGuardando(true);
        try {
            const captura = firmaRef.current.getDataUrl();
            const normalizada = await normalizarDataUrlFirma(captura);
            await onGuardar?.(normalizada);
            setConfirmarAlSalir(false);
            onCerrar?.();
        } finally {
            setGuardando(false);
        }
    }, [deshabilitado, esVertical, guardando, onCerrar, onGuardar, requiereHorizontal]);

    useEffect(() => {
        if (!abierto) return undefined;

        const onTecla = (evento) => {
            if (evento.key === 'Escape') {
                evento.preventDefault();
                solicitarCerrar();
            }
        };

        window.addEventListener('keydown', onTecla);
        return () => window.removeEventListener('keydown', onTecla);
    }, [abierto, solicitarCerrar]);

    if (!abierto) return null;

    const bloqueadoPorOrientacion = requiereHorizontal && esVertical;
    const puedeGuardar = tieneTrazo && !bloqueadoPorOrientacion && !deshabilitado && !guardando;

    return createPortal(
        <div
            className="fixed inset-0 flex flex-col h-dvh max-h-dvh overflow-hidden bg-[var(--gelia-surface,#f8fafc)] dark:bg-slate-950"
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 30)' }}
            role="dialog"
            aria-modal="true"
            aria-label="Firma en pantalla completa"
        >
            <header className="shrink-0 flex items-center gap-2 px-3 py-2 border-b theme-border safe-area-inset-top">
                <p className="flex-1 min-w-0 text-xs sm:text-sm font-black uppercase tracking-widest theme-text-main m-0 truncate">
                    Firma del receptor
                </p>
                <div className="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        onClick={guardarFirma}
                        disabled={!puedeGuardar}
                        className={`${THEME_BTN_PRIMARY} min-h-[44px] min-w-[44px] px-3 disabled:opacity-40`}
                        aria-label="Aceptar y guardar firma"
                        title="Guardar firma"
                    >
                        {guardando ? (
                            <span className="text-[10px] font-black uppercase">…</span>
                        ) : (
                            <Check className="w-5 h-5" />
                        )}
                    </button>
                    <button
                        type="button"
                        onClick={solicitarCerrar}
                        disabled={deshabilitado || guardando}
                        className={`${BTN_SECONDARY} min-h-[44px] min-w-[44px] px-3`}
                        aria-label="Cancelar firma"
                        title="Cancelar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>
            </header>

            <div className="relative flex-1 min-h-0 overflow-hidden px-2 py-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] flex flex-col">
                {bloqueadoPorOrientacion ? (
                    <div className="absolute inset-2 flex flex-col items-center justify-center text-center gap-4 rounded-2xl border-2 border-dashed theme-border bg-black/[0.03] dark:bg-white/[0.03] p-6">
                        <Smartphone className="w-12 h-12 text-[var(--color-primario)] rotate-90" />
                        <p className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                            Gira el dispositivo en horizontal
                        </p>
                        <p className="text-sm theme-text-muted m-0 max-w-md">
                            En teléfono o tablet la firma se captura en orientación horizontal para aprovechar el ancho de la pantalla.
                        </p>
                        <RotateCw className="w-8 h-8 theme-text-muted animate-pulse" />
                    </div>
                ) : (
                    <FirmaCanvas
                        ref={firmaRef}
                        label="Dibuja la firma"
                        adaptarAltura
                        height={200}
                        onTrazoChange={setTieneTrazo}
                    />
                )}
            </div>

            <ModalConfirmarAccion
                abierto={confirmarAlSalir}
                titulo="¿Guardar la firma?"
                mensaje="Hay un trazo capturado que aún no se ha guardado. Puedes conservarlo en el formulario o salir sin guardar."
                etiquetaConfirmar="Guardar firma"
                variante="primary"
                etiquetaAlternativa="Salir sin guardar"
                onClose={() => setConfirmarAlSalir(false)}
                onConfirm={() => {
                    setConfirmarAlSalir(false);
                    guardarFirma();
                }}
                onAlternativa={cerrarSinGuardar}
            />
        </div>,
        document.body,
    );
}
