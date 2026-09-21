import React from 'react';
import { Head } from '@inertiajs/react';
import { MonitorOff } from 'lucide-react';

export default function SalaInactiva({ sucursal_id: sucursalId = null }) {
    return (
        <>
            <Head title="Pantalla desactivada | Sala de turnos" />
            <div
                className="min-h-screen flex items-center justify-center p-8 theme-bg-main theme-text-main"
                data-pdv-sala-inactiva
                data-sucursal-id={sucursalId ?? undefined}
            >
                <div
                    className="max-w-lg w-full rounded-3xl border px-8 py-12 text-center theme-surface space-y-4"
                    style={{ borderColor: 'color-mix(in srgb, var(--color-texto) 12%, transparent)' }}
                >
                    <MonitorOff
                        className="w-12 h-12 mx-auto opacity-60"
                        aria-hidden
                    />
                    <h1 className="text-2xl font-black m-0">
                        Pantalla desactivada
                    </h1>
                    <p className="text-base opacity-80 m-0">
                        Esta pantalla de sala no está activa en este momento.
                        El enlace permanece igual; un operador con permisos puede activarla desde el panel de punto de venta.
                    </p>
                </div>
            </div>
        </>
    );
}
