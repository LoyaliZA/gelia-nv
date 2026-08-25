import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { X, PackageOpen } from 'lucide-react';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from '../Partials/pedidosBmaStyles';
import { THEME_INPUT, THEME_TEXTAREA } from '../../../utils/geliaTheme';

const SECCION = `${THEME_LABEL} mb-2 block`;

function tiempoDesde(iso) {
    if (!iso) return '—';
    const ms = Date.now() - new Date(iso).getTime();
    if (Number.isNaN(ms) || ms < 0) return 'recién';
    const h = Math.floor(ms / 3600000);
    const d = Math.floor(h / 24);
    if (d > 0) return `${d} día(s)`;
    if (h > 0) return `${h} hora(s)`;
    return `${Math.max(1, Math.floor(ms / 60000))} min`;
}

/**
 * Modal de liberación física (Tienda / CEDIS). No usa botón genérico «Aceptar».
 */
export default function ModalLiberarMercancia({
    abierto,
    onClose,
    tarea,
    routeName = 'control_pedidos.tienda.liberar',
}) {
    const [cantidad, setCantidad] = useState('');
    const [incidencia, setIncidencia] = useState('');
    const [confirmado, setConfirmado] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');

    const productos = tarea?.productos || [];
    const sugerida = useMemo(() => {
        const sum = productos.reduce((acc, p) => acc + (Number(p.cantidad_encontrada ?? p.cantidad_solicitada) || 0), 0);
        return sum > 0 ? String(sum) : '';
    }, [productos]);

    useEffect(() => {
        if (!abierto) return;
        setCantidad(sugerida);
        setIncidencia('');
        setConfirmado(false);
        setError('');
    }, [abierto, sugerida, tarea?.id]);

    if (!abierto || !tarea) return null;

    const folio = tarea.pedido?.folio_remision || tarea.pedido?.folio || `#${tarea.id}`;

    const confirmar = () => {
        if (!confirmado) {
            setError('Debe confirmar que ya devolvió las piezas a disponibilidad.');
            return;
        }
        setProcesando(true);
        setError('');
        router.post(route(routeName, tarea.id), {
            version: tarea.version,
            cantidad_liberada: cantidad === '' ? null : Number(cantidad),
            incidencia: incidencia || null,
            confirmacion: true,
            motivo: 'Ya devolví estas piezas a disponibilidad',
        }, {
            preserveScroll: true,
            onFinish: () => setProcesando(false),
            onSuccess: (page) => {
                if (page?.props?.flash?.error) {
                    setError(page.props.flash.error);
                    return;
                }
                onClose();
            },
            onError: (errs) => {
                const msg = Object.values(errs || {})[0];
                setError(typeof msg === 'string' ? msg : 'No se pudo liberar. Si el pedido fue reactivado, actualice la página.');
            },
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4`} style={{ zIndex: 'calc(var(--gelia-z-modal) + 10)' }} onClick={onClose}>
            <div className={`${THEME_MODAL_SHELL} max-w-lg w-full flex flex-col`} style={{ maxHeight: 'calc(100dvh - 2rem)' }} onClick={(e) => e.stopPropagation()}>
                <div className="p-5 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1">Liberar mercancía</p>
                        <h3 className="text-lg font-black theme-text-main m-0">{folio}</h3>
                        <p className="text-xs font-bold theme-text-muted m-0 mt-1">
                            {tarea.almacen?.nombre || '—'} · desde solicitud: {tiempoDesde(tarea.updated_at || tarea.solicitada_at)}
                        </p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2" aria-label="Cerrar"><X className="w-5 h-5" /></button>
                </div>
                <div className="gelia-modal-body p-5 space-y-4">
                    <p className="text-sm font-bold text-amber-800 m-0 p-3 rounded-xl border border-amber-300 bg-amber-50">
                        Confirme solo si devolvió físicamente estas piezas a disponibilidad en el almacén.
                    </p>
                    <div className="space-y-1 text-xs font-bold theme-text-muted">
                        {(productos.length ? productos : [{ descripcion_snapshot: 'Sin detalle de productos', cantidad_solicitada: '—' }]).map((p, i) => (
                            <p key={i} className="m-0">
                                · {p.descripcion_snapshot || p.sku || 'Producto'} — sol. {p.cantidad_solicitada ?? '—'}
                                {p.cantidad_encontrada != null ? ` / enc. ${p.cantidad_encontrada}` : ''}
                            </p>
                        ))}
                    </div>
                    <div>
                        <label className={SECCION}>Cantidad a liberar</label>
                        <input type="number" min="0" className={`${THEME_INPUT} w-full py-3`} value={cantidad} onChange={(e) => setCantidad(e.target.value)} />
                    </div>
                    <div>
                        <label className={SECCION}>Incidencia (faltante/daño, opcional)</label>
                        <textarea className={`${THEME_TEXTAREA} w-full py-3 min-h-[72px]`} value={incidencia} onChange={(e) => setIncidencia(e.target.value)} placeholder="Si aplica…" />
                    </div>
                    <label className="flex items-start gap-3 text-sm font-bold theme-text-main cursor-pointer">
                        <input type="checkbox" className="mt-1" checked={confirmado} onChange={(e) => setConfirmado(e.target.checked)} />
                        <span>Ya devolví estas piezas a disponibilidad</span>
                    </label>
                    {error && <p className="text-xs font-bold text-red-600 m-0">{error}</p>}
                </div>
                <div className="gelia-modal-footer p-5 border-t theme-border flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                    <button type="button" className={`${BTN_SECONDARY} min-h-[44px]`} onClick={onClose} disabled={procesando}>Cerrar</button>
                    <button type="button" className={`${BTN_PRIMARY} min-h-[44px] inline-flex items-center gap-2`} onClick={confirmar} disabled={procesando || !confirmado}>
                        <PackageOpen className="w-4 h-4" />
                        {procesando ? 'Registrando…' : 'Liberar mercancía'}
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
