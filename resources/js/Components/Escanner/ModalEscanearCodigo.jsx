import React, { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Camera, AlertTriangle, Flashlight, FlashlightOff, X } from 'lucide-react';
import { cargarHtml5Qrcode } from './cargarHtml5Qrcode';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_BTN_SECONDARY } from '@/utils/geliaTheme';

function esperarDom() {
    return new Promise((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(resolve));
    });
}

async function detenerEscaner(escaner) {
    if (!escaner) return;
    try {
        await escaner.stop();
    } catch {
        // cámara ya detenida
    }
    try {
        escaner.clear();
    } catch {
        // contenedor ya limpio
    }
}

async function detectarSoporteFlash(escaner) {
    try {
        const caps = escaner.getRunningTrackCameraCapabilities?.();
        return Boolean(caps?.torch);
    } catch {
        return false;
    }
}

async function aplicarFlash(escaner, encender) {
    if (!escaner) return false;
    const intentos = [
        { advanced: [{ torch: encender }] },
        { torch: encender },
    ];
    for (const constraints of intentos) {
        try {
            await escaner.applyVideoConstraints(constraints);
            return true;
        } catch {
            // probar siguiente formato
        }
    }
    return false;
}

async function aplicarAutofocus(escaner) {
    const intentos = [
        { advanced: [{ focusMode: 'continuous' }] },
        { focusMode: 'continuous' },
    ];
    for (const constraints of intentos) {
        try {
            await escaner.applyVideoConstraints(constraints);
            return true;
        } catch {
            // ignorar
        }
    }
    return false;
}

export default function ModalEscanearCodigo({
    abierto,
    onCerrar,
    onEscaneado,
    titulo = 'Escanear código',
    descripcion = 'Apunta la cámara al código QR o de barras del producto.',
}) {
    const hostRef = useRef(null);
    const escanerRef = useRef(null);
    const onEscaneadoRef = useRef(onEscaneado);
    const scannerId = useId().replace(/:/g, '');
    const [error, setError] = useState(null);
    const [iniciando, setIniciando] = useState(true);
    const [flashActivo, setFlashActivo] = useState(false);
    const [soportaFlash, setSoportaFlash] = useState(false);
    const flashActivoRef = useRef(false);

    onEscaneadoRef.current = onEscaneado;

    useEffect(() => {
        flashActivoRef.current = flashActivo;
    }, [flashActivo]);

    useEffect(() => {
        if (!abierto) return undefined;

        let cancelado = false;

        const iniciar = async () => {
            setError(null);
            setIniciando(true);
            setFlashActivo(false);
            setSoportaFlash(false);

            await esperarDom();
            if (cancelado || !hostRef.current) return;

            const contenedor = document.createElement('div');
            contenedor.id = scannerId;
            contenedor.className = 'w-full min-h-[220px]';
            hostRef.current.replaceChildren(contenedor);

            try {
                const { Html5Qrcode, Html5QrcodeSupportedFormats } = await cargarHtml5Qrcode();
                if (cancelado) return;

                const formatosSoportados = [
                    Html5QrcodeSupportedFormats.QR_CODE,
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.CODE_93,
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.UPC_E,
                    Html5QrcodeSupportedFormats.ITF,
                    Html5QrcodeSupportedFormats.CODABAR,
                ];

                const escaner = new Html5Qrcode(scannerId, {
                    formatsToSupport: formatosSoportados,
                    verbose: false,
                });
                escanerRef.current = escaner;

                await escaner.start(
                    {
                        facingMode: 'environment',
                        advanced: [{ focusMode: 'continuous' }],
                    },
                    {
                        fps: 20,
                        qrbox: (viewfinderWidth, viewfinderHeight) => {
                            const ancho = Math.min(viewfinderWidth * 0.9, 400);
                            const alto = Math.min(viewfinderHeight * 0.25, 120);
                            return { width: Math.max(ancho, 200), height: Math.max(alto, 80) };
                        },
                    },
                    async (texto) => {
                        if (cancelado) return;
                        const valor = texto.trim();
                        if (!valor) return;

                        cancelado = true;
                        if (flashActivoRef.current) {
                            await aplicarFlash(escanerRef.current, false);
                        }
                        await detenerEscaner(escanerRef.current);
                        escanerRef.current = null;
                        onEscaneadoRef.current?.(valor);
                    },
                    () => {},
                );

                await aplicarAutofocus(escaner);

                if (!cancelado) {
                    setSoportaFlash(await detectarSoporteFlash(escaner));
                    setIniciando(false);
                }
            } catch (err) {
                if (cancelado) return;
                setError(err?.message || 'No se pudo acceder a la cámara.');
                setIniciando(false);
            }
        };

        iniciar();

        return () => {
            cancelado = true;
            const escaner = escanerRef.current;
            escanerRef.current = null;
            detenerEscaner(escaner).finally(() => {
                hostRef.current?.replaceChildren();
            });
        };
    }, [abierto, scannerId]);

    const toggleFlash = async () => {
        const escaner = escanerRef.current;
        if (!escaner || !soportaFlash) return;
        const next = !flashActivo;
        const ok = await aplicarFlash(escaner, next);
        if (ok) setFlashActivo(next);
    };

    if (!abierto) return null;

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} onClick={onCerrar}>
            <div className={`${THEME_MODAL_SHELL} max-w-lg w-full modal-pop`} onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between gap-3 p-5 border-b theme-border">
                    <h2 className="text-lg font-black italic uppercase theme-text-main m-0">{titulo}</h2>
                    <button type="button" onClick={onCerrar} className="p-2 hover:bg-black/5 dark:hover:bg-white/5 rounded-full">
                        <X className="w-5 h-5 theme-text-muted" />
                    </button>
                </div>
                <div className="p-5 space-y-4">
                    <p className="text-sm theme-text-muted m-0">{descripcion}</p>
                    <div className="rounded-2xl overflow-hidden border theme-border bg-black/90 min-h-[220px] relative">
                        <div ref={hostRef} className="w-full min-h-[220px]" />
                        {iniciando && (
                            <div className="absolute inset-0 flex items-center justify-center bg-black/60 pointer-events-none">
                                <p className="text-white text-xs font-bold uppercase tracking-widest flex items-center gap-2">
                                    <Camera className="w-4 h-4" /> Iniciando cámara...
                                </p>
                            </div>
                        )}
                    </div>
                    {error && (
                        <div className="flex items-start gap-2 px-4 py-3 rounded-xl border border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-200 text-sm">
                            <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
                            <span>{error}</span>
                        </div>
                    )}
                    <p className="text-[10px] theme-text-muted m-0">
                        Compatible con QR, Code 128/39, EAN y UPC. El autofocus ayuda con códigos pequeños.
                        {soportaFlash ? ' Usa el flash si el entorno está oscuro.' : ''}
                    </p>
                </div>
                <div className="p-5 border-t theme-border flex flex-wrap justify-between gap-2">
                    {soportaFlash ? (
                        <button type="button" onClick={toggleFlash} className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2`}>
                            {flashActivo ? <><FlashlightOff className="w-4 h-4" /> Apagar flash</> : <><Flashlight className="w-4 h-4" /> Encender flash</>}
                        </button>
                    ) : <span />}
                    <button type="button" onClick={onCerrar} className={THEME_BTN_SECONDARY}>Cancelar</button>
                </div>
            </div>
        </div>,
        document.body
    );
}
