import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Loader2 } from 'lucide-react';

export default function ModalProgreso({ logId, onClose }) {
    const [log, setLog] = useState(null);

    useEffect(() => {
        const interval = setInterval(async () => {
            try {
                const res = await fetch(route('woocommerce.progreso', logId), { headers: { Accept: 'application/json' } });
                const data = await res.json();
                setLog(data);
                if (['completado', 'error', 'cancelado'].includes(data.estado)) {
                    clearInterval(interval);
                }
            } catch (e) {
                console.error(e);
            }
        }, 2000);
        return () => clearInterval(interval);
    }, [logId]);

    const pct = log ? Math.round((log.procesados / Math.max(log.total_productos, 1)) * 100) : 0;
    const terminado = log && ['completado', 'error', 'cancelado'].includes(log.estado);

    const modal = (
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md">
            <div className="w-full max-w-md theme-surface border theme-border rounded-[2.5rem] p-8 text-center">
                <div className="flex justify-end"><button onClick={onClose} className="theme-text-muted"><X className="w-5 h-5" /></button></div>
                {!terminado && <Loader2 className="w-10 h-10 mx-auto animate-spin mb-4" style={{ color: 'var(--color-primario)' }} />}
                <h2 className="text-lg font-black uppercase theme-text-main mb-2">
                    {terminado ? `Proceso ${log.estado}` : 'Sincronizando...'}
                </h2>
                <p className="text-xs theme-text-muted mb-4">Proceso #{logId}</p>
                <div className="w-full h-3 rounded-full theme-element overflow-hidden mb-2">
                    <div className="h-full transition-all duration-500 rounded-full" style={{ width: `${pct}%`, backgroundColor: 'var(--color-primario)' }} />
                </div>
                <p className="text-[10px] font-black uppercase theme-text-muted">{log?.procesados ?? 0} / {log?.total_productos ?? '—'} ({pct}%)</p>
                {log?.mensaje_error && <p className="text-xs text-red-500 mt-4 font-bold">{log.mensaje_error}</p>}
            </div>
        </div>
    );
    return typeof window === 'undefined' ? modal : createPortal(modal, document.body);
}
