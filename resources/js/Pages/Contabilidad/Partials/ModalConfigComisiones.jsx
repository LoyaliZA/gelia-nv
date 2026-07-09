import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { Settings, X } from 'lucide-react';
import { THEME_INPUT, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { BTN_PRIMARY, BTN_PRIMARY_STYLE } from './contabilidadStyles';
import { contabilidadRoutes } from '../contabilidadRoutes';

export default function ModalConfigComisiones({ abierto, plataformas, configuracion, onCerrar }) {
    const [tasas, setTasas] = useState(() =>
        Object.fromEntries((plataformas || []).map((p) => [p.id, String(p.tasa_comision_pct ?? '')]))
    );
    const [mapeoPrecios, setMapeoPrecios] = useState(() => ({
        sku: configuracion?.mapeo_precios?.sku || 'SKU',
        precio_base: configuracion?.mapeo_precios?.precio_base || 'Bronce',
        descripcion: configuracion?.mapeo_precios?.descripcion || 'Descripcion',
    }));
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
                onSuccess: () => {
                    router.put(
                        contabilidadRoutes.configuracionUpdate(),
                        { mapeo_precios: mapeoPrecios },
                        {
                            preserveScroll: true,
                            onSuccess: () => onCerrar(),
                            onFinish: () => setProcesando(false),
                        }
                    );
                },
                onError: () => setProcesando(false),
            }
        );
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md`} onClick={onCerrar}>
            <div className={`${THEME_MODAL_SHELL} w-full max-w-md rounded-[2rem] shadow-2xl p-6 md:p-8 relative max-h-[90vh] overflow-y-auto custom-scrollbar`} onClick={(e) => e.stopPropagation()}>
                <button type="button" onClick={onCerrar} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-xl outline-none">
                    <X className="w-5 h-5" />
                </button>
                <div className="flex items-center gap-3 mb-6 border-b theme-border pb-4">
                    <Settings className="w-6 h-6 text-[var(--color-primario)]" />
                    <h3 className="text-xl font-black italic theme-text-main uppercase m-0">Configuración</h3>
                </div>
                <form onSubmit={guardar} className="space-y-5">
                    <div>
                        <h4 className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">Comisiones por plataforma</h4>
                        <div className="space-y-3">
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
                        </div>
                    </div>

                    <div className="border-t theme-border pt-4">
                        <h4 className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">Mapeo de precios (Excel)</h4>
                        <p className="text-[10px] font-bold theme-text-muted mb-3">
                            Nombres de columna predeterminados al importar la lista de resurtido.
                        </p>
                        <div className="space-y-3">
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Columna SKU</label>
                                <input
                                    type="text"
                                    className={`${THEME_INPUT} w-full mt-1`}
                                    value={mapeoPrecios.sku}
                                    onChange={(e) => setMapeoPrecios((prev) => ({ ...prev, sku: e.target.value }))}
                                    placeholder="SKU"
                                />
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Columna descripción</label>
                                <input
                                    type="text"
                                    className={`${THEME_INPUT} w-full mt-1`}
                                    value={mapeoPrecios.descripcion}
                                    onChange={(e) => setMapeoPrecios((prev) => ({ ...prev, descripcion: e.target.value }))}
                                    placeholder="Descripcion"
                                />
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Columna precio base</label>
                                <input
                                    type="text"
                                    className={`${THEME_INPUT} w-full mt-1`}
                                    value={mapeoPrecios.precio_base}
                                    onChange={(e) => setMapeoPrecios((prev) => ({ ...prev, precio_base: e.target.value }))}
                                    placeholder="Bronce"
                                />
                            </div>
                        </div>
                    </div>

                    <button type="submit" disabled={procesando} className={`${BTN_PRIMARY} w-full mt-4`} style={BTN_PRIMARY_STYLE}>
                        {procesando ? 'Guardando...' : 'Guardar configuración'}
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
