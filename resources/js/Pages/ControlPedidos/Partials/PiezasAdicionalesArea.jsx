import React, { useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { PackagePlus } from 'lucide-react';
import { BTN_PRIMARY } from './pedidosBmaStyles';
import { mensajeDeError } from './ModalPiezasAdicionales';

const dinero = (valor) => Number(valor || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

/**
 * Piezas adicionales de un envío municipal vistas por el área que tiene el paquete (CEDIS o sucursal).
 * Solo confirma la integración física quien el servidor autoriza (`puede_integrar`); Ventas no confirma hechos físicos.
 */
export default function PiezasAdicionalesArea({ pedidoId, items = [], onActualizado }) {
    const [procesando, setProcesando] = useState(null);
    const [error, setError] = useState('');
    if (!items.length) return null;

    const integrar = async (complemento) => {
        setError('');
        setProcesando(complemento.id);
        try {
            await axios.post(route('control_pedidos.municipal.complementos.integrar', [pedidoId, complemento.id]), {}, { headers: { Accept: 'application/json' } });
            if (onActualizado) onActualizado();
            else router.reload({ preserveScroll: true });
        } catch (err) {
            setError(mensajeDeError(err));
        } finally {
            setProcesando(null);
        }
    };

    return (
        <section className="rounded-xl border theme-border theme-element p-2.5 space-y-2" aria-label="Piezas adicionales del envío">
            <p className="text-xs font-semibold theme-text-info m-0 inline-flex items-center gap-2">
                <PackagePlus className="w-4 h-4" aria-hidden="true" /> Piezas adicionales
            </p>
            <ul className="m-0 p-0 space-y-2">
                {items.map((c) => (
                    <li key={c.id} className="list-none flex flex-wrap items-center justify-between gap-2">
                        <div className="min-w-0">
                            <p className="text-xs font-bold theme-text-main m-0">{c.folio} · {dinero(c.total_mercancia)}</p>
                            <p className="text-[11px] theme-text-muted font-bold m-0">
                                {c.etiqueta}{c.piezas?.length ? ` · ${c.piezas.map((p) => `${p.cantidad} × ${p.descripcion}`).join(', ')}` : ''}
                            </p>
                        </div>
                        {c.puede_integrar && (
                            <button type="button" className={BTN_PRIMARY} onClick={() => integrar(c)} disabled={procesando === c.id}>
                                {procesando === c.id ? 'Confirmando...' : 'Confirmar integración'}
                            </button>
                        )}
                    </li>
                ))}
            </ul>
            {error && <p role="alert" className="text-[11px] text-red-500 font-bold m-0">{error}</p>}
        </section>
    );
}
