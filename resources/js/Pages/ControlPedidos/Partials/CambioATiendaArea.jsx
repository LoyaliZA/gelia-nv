import React, { useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { Store } from 'lucide-react';
import { BTN_PRIMARY } from './pedidosBmaStyles';
import { mensajeDeError } from './ModalPiezasAdicionales';

/**
 * Estado del cambio a recolección en sucursal visto por el área que tiene el paquete (CEDIS o sucursal).
 * Solo verifica quien el servidor autoriza (`puede_aplicar`); la vendedora no confirma hechos físicos.
 */
export default function CambioATiendaArea({ pedidoId, cambio, onActualizado }) {
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');
    if (!cambio?.estado) return null;

    const aplicar = async () => {
        setError('');
        setProcesando(true);
        try {
            await axios.post(route('control_pedidos.municipal.cambio_a_tienda.aplicar', pedidoId), {}, { headers: { Accept: 'application/json' } });
            if (onActualizado) onActualizado();
            else router.reload({ preserveScroll: true });
        } catch (err) {
            setError(mensajeDeError(err));
        } finally {
            setProcesando(false);
        }
    };

    return (
        <section className="rounded-xl border theme-border theme-element p-2.5 space-y-2" aria-label="Recolección en sucursal">
            <p className="text-xs font-semibold theme-text-info m-0 inline-flex items-center gap-2">
                <Store className="w-4 h-4" aria-hidden="true" /> Recolección en sucursal
            </p>
            <p className="text-xs font-bold theme-text-main m-0">{cambio.etiqueta}</p>
            {cambio.motivo && <p className="text-[11px] theme-text-muted font-bold m-0">Motivo: {cambio.motivo}</p>}
            {cambio.puede_aplicar && (
                <div className="flex justify-end">
                    <button type="button" className={BTN_PRIMARY} onClick={aplicar} disabled={procesando}>
                        {procesando ? 'Verificando...' : 'Verificar cambio'}
                    </button>
                </div>
            )}
            {error && <p role="alert" className="text-[11px] text-red-500 font-bold m-0">{error}</p>}
        </section>
    );
}
