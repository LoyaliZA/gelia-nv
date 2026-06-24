import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { Settings, X } from 'lucide-react';
import { THEME_INPUT, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { BTN_PRIMARY, BTN_PRIMARY_STYLE } from './contabilidadStyles';
import { contabilidadRoutes } from '../contabilidadRoutes';

export default function ModalConfigComisiones({ abierto, plataformas, onCerrar }) {
    const [tasas, setTasas] = useState(() =>
        Object.fromEntries((plataformas || []).map((p) => [p.id, String(p.tasa_comision_pct ?? '')]))
    );
    const [procesando, setProcesando] = useState(false);

    if (!abierto) return null;

    const guardar = (e) => {
        e.preventDefault();
        setProcesando(true);
        router.put(
            contabilidadRoutes.plataformasComisiones(),
            {
                plataformas: (plataformas || []).map((p) => ({
                    id: p.id,
                    tasa_comision_pct: parseFloat(tasas[p.id] || 0),
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => onCerrar(),
                onFinish: () => setProcesando(false),
            }
        );
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md`} onClick={onCerrar}>
            <div className={`${THEME_MODAL_SHELL} w-full max-w-sm rounded-[2rem] shadow-2xl p-6 md:p-8 relative`} onClick={(e) => e.stopPropagation()}>
                <button type="button" onClick={onCerrar} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-xl outline-none">
                    <X className="w-5 h-5" />
                </button>
                <div className="flex items-center gap-3 mb-6 border-b theme-border pb-4">
                    <Settings className="w-6 h-6 text-[var(--color-primario)]" />
                    <h3 className="text-xl font-black italic theme-text-main uppercase m-0">Comisiones</h3>
                </div>
                <form onSubmit={guardar} className="space-y-3">
                    {(plataformas || []).map((plat) => (
                        <div key={plat.id} className="theme-element border theme-border rounded-xl p-3 flex items-center justify-between gap-3">
                            <span className="text-sm font-bold theme-text-main">{plat.nombre}</span>
                            <div className="flex items-center gap-1">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    className={`${THEME_INPUT} w-20 text-right font-bold`}
                                    value={tasas[plat.id] ?? ''}
                                    onChange={(e) => setTasas((prev) => ({ ...prev, [plat.id]: e.target.value }))}
                                />
                                <span className="text-xs theme-text-muted font-bold">%</span>
                            </div>
                        </div>
                    ))}
                    <button type="submit" disabled={procesando} className={`${BTN_PRIMARY} w-full mt-4`} style={BTN_PRIMARY_STYLE}>
                        {procesando ? 'Guardando...' : 'Guardar configuración'}
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
