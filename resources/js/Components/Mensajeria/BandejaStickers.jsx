import React, { useEffect, useRef, useState } from 'react';
import { Plus, Sticker, Trash2 } from 'lucide-react';
import MensajeriaService from '@/Services/MensajeriaService';
import { animarEntrada } from '@/utils/animarMensajeria';
import BotonIconoMensajeria from './BotonIconoMensajeria';
import CreadorSticker from './CreadorSticker';

export default function BandejaStickers({ abierto, onCerrar, onEnviar, enviando = false }) {
    const raiz = useRef(null);
    const [stickers, setStickers] = useState([]);
    const [cargando, setCargando] = useState(false);
    const [creando, setCreando] = useState(false);
    const [error, setError] = useState('');
    const [intento, setIntento] = useState(0);

    useEffect(() => {
        if (!abierto) return undefined;
        animarEntrada(raiz.current, { eje: 'y', distancia: 12 });
        return undefined;
    }, [abierto, creando]);

    useEffect(() => {
        if (!abierto) return undefined;
        let vivo = true;
        setCargando(true);
        setError('');
        MensajeriaService.listarStickers()
            .then((lista) => {
                if (vivo) setStickers(lista);
            })
            .catch(() => {
                if (vivo) setError('No se pudieron cargar los stickers.');
            })
            .finally(() => {
                if (vivo) setCargando(false);
            });
        return () => {
            vivo = false;
        };
    }, [abierto, intento]);

    if (!abierto) return null;

    const guardar = async (archivo, enviar) => {
        setError('');
        const sticker = await MensajeriaService.crearSticker(archivo);
        setStickers((prev) => [sticker, ...prev.filter((item) => item.id !== sticker.id)]);
        setCreando(false);
        if (enviar) {
            await onEnviar?.(sticker.id);
            onCerrar?.();
        }
    };

    const borrar = async (sticker) => {
        setStickers((prev) => prev.filter((item) => item.id !== sticker.id));
        try {
            await MensajeriaService.eliminarSticker(sticker.id);
        } catch {
            setStickers((prev) => [sticker, ...prev]);
            setError('No se pudo borrar el sticker.');
        }
    };

    return (
        <div ref={raiz} className="gelia-sticker-bandeja">
            <div className="flex items-center justify-between gap-2 mb-2">
                <p className="m-0 text-sm font-semibold theme-text-main inline-flex items-center gap-2">
                    <Sticker className="w-4 h-4" aria-hidden />
                    Stickers
                </p>
                <div className="flex items-center gap-1">
                    <BotonIconoMensajeria
                        icon={Plus}
                        label="Crear sticker"
                        onClick={() => setCreando((v) => !v)}
                        pressed={creando}
                        className="rounded-full theme-element border theme-border"
                        size={16}
                    />
                    <button type="button" className="text-xs theme-text-muted px-2" onClick={onCerrar}>
                        Cerrar
                    </button>
                </div>
            </div>

            {creando && (
                <CreadorSticker
                    enviando={enviando}
                    onCancelar={() => setCreando(false)}
                    onGuardar={guardar}
                />
            )}

            {cargando && <p className="m-0 text-xs theme-text-muted">Cargando stickers…</p>}
            {error && <div role="alert" className="gelia-mensajeria-error"><p className="m-0">{error}</p><button type="button" className="gelia-sticker-accion mt-2" onClick={() => setIntento((v) => v + 1)}>Volver a cargar</button></div>}

            {!creando && !cargando && !error && stickers.length === 0 && (
                <p className="m-0 text-xs theme-text-muted">Todavía no hay stickers. Crea uno aquí.</p>
            )}

            <div className="gelia-sticker-grid">
                {stickers.map((sticker) => (
                    <div key={sticker.id} className="gelia-sticker-item">
                        <button
                            type="button"
                            className="gelia-sticker-enviar"
                            disabled={enviando}
                            onClick={async () => { try { await onEnviar?.(sticker.id); } catch { setError('No se pudo enviar el sticker. Inténtalo de nuevo.'); } }}
                            aria-label="Enviar sticker"
                        >
                            <img src={sticker.url} alt="" />
                        </button>
                        <button
                            type="button"
                            className="gelia-sticker-borrar"
                            onClick={() => borrar(sticker)}
                            aria-label="Borrar sticker"
                        >
                            <Trash2 className="w-3.5 h-3.5" aria-hidden />
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );
}
