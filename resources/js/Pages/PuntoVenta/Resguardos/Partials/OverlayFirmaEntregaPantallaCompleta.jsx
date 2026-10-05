import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Check, RotateCw, Smartphone, X } from 'lucide-react';
import FirmaCanvas from '../../../../Components/Rh/FirmaCanvas';
import { esDispositivoCampo } from '../../../Activos/Partials/useDispositivoCampo';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import { BTN_SECONDARY } from './resguardosStyles';
import { normalizarDataUrlFirma } from './entregaResguardoUtils';

const ALTURA_BARRA = 120;

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
    const [alturaLienzo, setAlturaLienzo] = useState(280);
    const [esVertical, setEsVertical] = useState(false);
    const [guardando, setGuardando] = useState(false);
    const requiereHorizontal = esDispositivoCampo();

    const actualizarLayout = useCallback(() => {
        setEsVertical(window.matchMedia?.('(orientation: portrait)')?.matches ?? false);
        const disponible = Math.max(200, window.innerHeight - ALTURA_BARRA);
        setAlturaLienzo(disponible);
    }, []);

    useEffect(() => {
        if (!abierto) return undefined;

        actualizarLayout();
        document.body.style.overflow = 'hidden';
        bloquearOrientacionHorizontal();

        window.addEventListener('resize', actualizarLayout);
        const orientacion = window.matchMedia?.('(orientation: portrait)');
        orientacion?.addEventListener?.('change', actualizarLayout);

        return () => {
            document.body.style.overflow = '';
            liberarOrientacion();
            window.removeEventListener('resize', actualizarLayout);
            orientacion?.removeEventListener?.('change', actualizarLayout);
        };
    }, [abierto, actualizarLayout]);

    useEffect(() => {
        if (!abierto) return;
        const timer = window.setTimeout(() => {
            if (dataUrlInicial) {
                firmaRef.current?.loadDataUrl?.(dataUrlInicial);
            }
        }, 50);
        return () => window.clearTimeout(timer);
    }, [abierto, dataUrlInicial]);

    const cerrar = () => {
        if (guardando || deshabilitado) return;
        onCerrar?.();
    };

    const guardarFirma = async () => {
        if (deshabilitado || guardando) return;
        if (requiereHorizontal && esVertical) return;
        if (!firmaRef.current?.hasStroke?.()) return;

        setGuardando(true);
        try {
            const captura = firmaRef.current.getDataUrl();
            const normalizada = await normalizarDataUrlFirma(captura);
            await onGuardar?.(normalizada);
            onCerrar?.();
        } finally {
            setGuardando(false);
        }
    };

    if (!abierto) return null;

    const bloqueadoPorOrientacion = requiereHorizontal && esVertical;

    return createPortal(
        <div
            className="fixed inset-0 z-[200] flex flex-col bg-[var(--gelia-surface,#f8fafc)] dark:bg-slate-950"
            role="dialog"
            aria-modal="true"
            aria-label="Firma en pantalla completa"
        >
            <header className="shrink-0 flex items-center justify-between gap-3 px-4 py-3 border-b theme-border safe-area-inset-top">
                <div className="min-w-0">
                    <p className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        Firma del receptor
                    </p>
                    <p className="text-xs theme-text-muted m-0 mt-0.5">
                        Usa todo el ancho de la pantalla. Al guardar se ajustará al recuadro del formulario.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={cerrar}
                    disabled={deshabilitado || guardando}
                    className={`${BTN_SECONDARY} shrink-0 min-h-[44px] min-w-[44px] px-3`}
                    aria-label="Cerrar"
                >
                    <X className="w-5 h-5" />
                </button>
            </header>

            <div className="relative flex-1 min-h-0 px-4 py-3">
                {bloqueadoPorOrientacion ? (
                    <div className="absolute inset-4 flex flex-col items-center justify-center text-center gap-4 rounded-2xl border-2 border-dashed theme-border bg-black/[0.03] dark:bg-white/[0.03] p-6">
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
                        height={alturaLienzo}
                        className="h-full"
                    />
                )}
            </div>

            <footer className="shrink-0 flex flex-col sm:flex-row gap-2 px-4 py-3 border-t theme-border safe-area-inset-bottom">
                <button
                    type="button"
                    onClick={cerrar}
                    disabled={deshabilitado || guardando}
                    className={`${BTN_SECONDARY} min-h-[48px] flex-1`}
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    onClick={guardarFirma}
                    disabled={deshabilitado || guardando || bloqueadoPorOrientacion}
                    className={`${THEME_BTN_PRIMARY} min-h-[48px] flex-1 text-[10px] font-black uppercase tracking-widest disabled:opacity-50`}
                >
                    <Check className="w-4 h-4 inline mr-2" />
                    {guardando ? 'Guardando…' : 'Guardar firma'}
                </button>
            </footer>
        </div>,
        document.body,
    );
}
