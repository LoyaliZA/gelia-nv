import React, { useEffect, useState } from 'react';
import { Wifi, WifiOff } from 'lucide-react';
import DashboardWidgetStatus from './DashboardWidgetStatus';

function formatearReloj(fecha) {
    return {
        hora: fecha.toLocaleTimeString('es-MX', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        }),
        dia: fecha.toLocaleDateString('es-MX', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        }),
        iso: fecha.toISOString(),
    };
}

export default function RelojConexion() {
    const [ahora, setAhora] = useState(() => new Date());
    const [enLinea, setEnLinea] = useState(() => (
        typeof navigator === 'undefined' ? true : navigator.onLine
    ));

    useEffect(() => {
        const id = window.setInterval(() => setAhora(new Date()), 1000);
        return () => window.clearInterval(id);
    }, []);

    useEffect(() => {
        const marcar = () => setEnLinea(navigator.onLine);
        window.addEventListener('online', marcar);
        window.addEventListener('offline', marcar);
        return () => {
            window.removeEventListener('online', marcar);
            window.removeEventListener('offline', marcar);
        };
    }, []);

    const reloj = formatearReloj(ahora);

    return (
        <div className="flex flex-wrap items-center gap-3 shrink-0">
            <time dateTime={reloj.iso} className="min-w-0 text-left sm:text-right">
                <span className="block text-xl font-semibold tabular-nums theme-text-main leading-none">{reloj.hora}</span>
                <span className="mt-1 block text-xs theme-text-muted">{reloj.dia}</span>
            </time>
            <DashboardWidgetStatus tono={enLinea ? 'exito' : 'error'} icon={enLinea ? Wifi : WifiOff}>
                <span role="status">{enLinea ? 'En línea' : 'Sin conexión'}</span>
            </DashboardWidgetStatus>
        </div>
    );
}
