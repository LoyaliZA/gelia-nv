import React, { useEffect, useState } from 'react';
import { MapPin } from 'lucide-react';
import GeliaLogo from '@/Components/GeliaLogo';
import { PDV_ESTADO_CONEXION } from '@/utils/pdvAlertQueueUtils';

function formatearFecha(fecha) {
    return new Intl.DateTimeFormat('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(fecha);
}

function formatearHora(fecha) {
    return new Intl.DateTimeFormat('es-MX', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(fecha);
}

export default function DisplayHeader({ sucursalNombre, estadoConexion }) {
    const [ahora, setAhora] = useState(() => new Date());

    useEffect(() => {
        const id = window.setInterval(() => setAhora(new Date()), 15000);
        return () => window.clearInterval(id);
    }, []);

    const enVivo = estadoConexion === PDV_ESTADO_CONEXION.conectado;

    return (
        <header className="flex flex-wrap items-center justify-between gap-2 px-1 sm:gap-4 sm:px-2">
            <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                <GeliaLogo
                    variant="sparkle"
                    className="h-9 w-9 shrink-0 drop-shadow-[0_0_12px_color-mix(in_srgb,var(--color-primario)_60%,transparent)] sm:h-12 sm:w-12 [@media(max-height:760px)]:h-8 [@media(max-height:760px)]:w-8"
                />
                <span
                    className="h-10 w-px shrink-0"
                    style={{ backgroundColor: 'var(--theme-border)' }}
                    aria-hidden
                />
                <div className="flex min-w-0 items-center gap-2">
                    <MapPin className="h-5 w-5 shrink-0 theme-text-primario" aria-hidden />
                    <p className="truncate text-base font-bold theme-text-main sm:text-lg" data-pdv-sala-sucursal>
                        {sucursalNombre || 'Sucursal'}
                    </p>
                </div>
            </div>

            <div className="flex shrink-0 items-center gap-3">
                <span
                    className="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-black uppercase tracking-widest"
                    style={{
                        backgroundColor: enVivo
                            ? 'color-mix(in srgb, #16a34a 18%, white)'
                            : 'color-mix(in srgb, var(--theme-text-muted) 14%, white)',
                        color: enVivo ? '#15803d' : 'var(--theme-text-muted)',
                    }}
                    data-pdv-sala-en-vivo
                >
                    <span className={`h-2 w-2 rounded-full ${enVivo ? 'bg-green-600' : 'bg-zinc-400'}`} aria-hidden />
                    En vivo
                </span>
                <div className="text-right">
                    <p className="text-xl font-black leading-none theme-text-main tabular-nums sm:text-2xl [@media(max-height:760px)]:text-lg" data-pdv-sala-hora>
                        {formatearHora(ahora)}
                    </p>
                    <p className="mt-1 text-xs capitalize theme-text-muted" data-pdv-sala-fecha>
                        {formatearFecha(ahora)}
                    </p>
                </div>
            </div>
        </header>
    );
}
