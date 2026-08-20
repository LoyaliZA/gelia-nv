import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Smartphone } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../Partials/pedidosBmaStyles';
import { BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';

export default function ModalSesionEvidenciaCedis({
    abierto,
    sesion,
    conectado = false,
    onCerrar,
    onCancelar,
}) {
    const [restante, setRestante] = useState('');

    useEffect(() => {
        if (!abierto || !sesion?.expira_en) return undefined;
        const tick = () => {
            const ms = new Date(sesion.expira_en).getTime() - Date.now();
            if (ms <= 0) {
                setRestante('Expiró');
                return;
            }
            const m = Math.floor(ms / 60000);
            const s = Math.floor((ms % 60000) / 1000);
            setRestante(`${m}:${String(s).padStart(2, '0')}`);
        };
        tick();
        const t = window.setInterval(tick, 1000);
        return () => window.clearInterval(t);
    }, [abierto, sesion?.expira_en]);

    if (!abierto || !sesion) return null;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center`}>
            <div className={`${THEME_MODAL_SHELL} max-w-sm w-full p-5 space-y-4`} onClick={(e) => e.stopPropagation()}>
                <div className="flex justify-between items-start gap-2">
                    <div>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Celular</p>
                        <h2 className="text-lg font-black theme-text-main m-0 mt-1">Tomar evidencias</h2>
                    </div>
                    <button type="button" onClick={onCerrar} className="p-2 min-h-[44px] min-w-[44px]" aria-label="Cerrar">
                        <X className="w-4 h-4" />
                    </button>
                </div>
                <p className="text-xs theme-text-muted m-0">
                    Escanee este QR con el teléfono. Las fotos aparecen aquí al instante. Quedan {restante || '—'}.
                </p>
                {sesion.qr_data_uri && (
                    <img src={sesion.qr_data_uri} alt="QR de evidencias" className="w-56 h-56 mx-auto bg-white p-2 rounded-xl" />
                )}
                <p className={`text-xs font-black uppercase m-0 ${conectado ? 'text-emerald-600' : 'theme-text-muted'}`}>
                    {conectado ? 'Celular conectado' : 'Esperando escaneo…'}
                </p>
                <div className="flex gap-2">
                    <button type="button" onClick={onCancelar} className={`${BTN_SECONDARY} flex-1 min-h-[44px]`}>
                        Cancelar sesión
                    </button>
                    <button type="button" onClick={onCerrar} className={`${BTN_SECONDARY} flex-1 min-h-[44px] inline-flex items-center justify-center gap-1`}>
                        <Smartphone className="w-4 h-4" /> Listo
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
