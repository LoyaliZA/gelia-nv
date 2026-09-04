import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { MapPin, Loader2 } from 'lucide-react';
import { THEME_SELECT } from './resguardosStyles';

export default function SelectorSucursalActivaPdv({
    sucursalActiva = null,
    sucursalesAsignadas = [],
}) {
    const [cambiando, setCambiando] = useState(false);
    const [error, setError] = useState(null);

    const multiples = sucursalesAsignadas.length > 1;
    const activaId = sucursalActiva?.id ? String(sucursalActiva.id) : '';

    const onCambiar = async (event) => {
        const sucursalId = event.target.value;
        if (!sucursalId || sucursalId === activaId) return;

        setCambiando(true);
        setError(null);

        try {
            await axios.put(route('punto_venta.sucursal_activa.establecer'), {
                sucursal_id: Number(sucursalId),
            });
            router.reload({ only: ['sucursal_activa', 'resguardos', 'metricas', 'filtros', 'bandeja'] });
        } catch (err) {
            const status = err?.response?.status;
            if (status === 403 || status === 422) {
                setError('No se pudo cambiar la sucursal activa.');
            } else {
                setError('Ocurrió un error al cambiar la sucursal. Intenta de nuevo.');
            }
        } finally {
            setCambiando(false);
        }
    };

    if (!sucursalActiva?.nombre && sucursalesAsignadas.length === 0) {
        return null;
    }

    return (
        <div className="space-y-1">
            <p className="text-[10px] md:text-[11px] font-bold theme-text-muted uppercase tracking-widest m-0 flex items-center gap-1.5 flex-wrap">
                <MapPin className="w-3.5 h-3.5 shrink-0" style={{ color: 'var(--color-primario)' }} aria-hidden />
                {multiples ? (
                    <label className="flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2 w-full sm:w-auto">
                        <span className="shrink-0">Sucursal activa</span>
                        <span className="relative flex-1 sm:flex-initial sm:min-w-[12rem]">
                            <select
                                value={activaId}
                                onChange={onCambiar}
                                disabled={cambiando}
                                className={`${THEME_SELECT} !py-2 !px-3 text-xs font-bold min-h-[44px] w-full`}
                                aria-label="Seleccionar sucursal activa"
                            >
                                {sucursalesAsignadas.map(({ id, nombre }) => (
                                    <option key={id} value={String(id)}>{nombre}</option>
                                ))}
                            </select>
                            {cambiando && (
                                <Loader2
                                    className="w-4 h-4 animate-spin absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                                    style={{ color: 'var(--color-primario)' }}
                                    aria-hidden
                                />
                            )}
                        </span>
                    </label>
                ) : (
                    <span>
                        Sucursal:{' '}
                        <span className="theme-text-main">{sucursalActiva?.nombre}</span>
                    </span>
                )}
            </p>
            {error && (
                <p className="text-[10px] font-semibold text-red-600 dark:text-red-300 m-0 pl-5">{error}</p>
            )}
        </div>
    );
}
