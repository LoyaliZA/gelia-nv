import React, { useEffect, useMemo, useState } from 'react';
import { Clock } from 'lucide-react';
import { formatearCronometro } from '../../Turnos/Partials/tableroVentasUtils';

function calcularDisplay(modo, referenciaAt, servidorAt) {
    if (!referenciaAt || !servidorAt) return null;
    const ref = new Date(referenciaAt).getTime();
    const ahora = new Date(servidorAt).getTime();
    if (!Number.isFinite(ref) || !Number.isFinite(ahora)) return null;

    if (modo === 'transcurrido') {
        return Math.max(0, ahora - ref);
    }

    return Math.max(0, ref - ahora);
}

export default function CronometroVisualOperacion({
    etiqueta,
    referenciaAt,
    servidorAt,
    modo = 'transcurrido',
    compacto = false,
    alerta = false,
}) {
    const [, setTick] = useState(0);
    const reloj = useMemo(() => ({
        servidor: new Date(servidorAt).getTime(),
        cliente: Date.now(),
    }), [servidorAt]);

    useEffect(() => {
        const intervalo = setInterval(() => setTick((tick) => tick + 1), 1000);
        return () => clearInterval(intervalo);
    }, []);

    const ahora = reloj.servidor + Math.max(0, Date.now() - reloj.cliente);
    const milisegundos = Number.isFinite(ahora)
        ? calcularDisplay(modo, referenciaAt, ahora)
        : null;
    // La espera solo debe mostrar cero cuando ya se cumplió el plazo completo.
    const display = modo === 'restante' && milisegundos !== null
        ? Math.ceil(milisegundos / 1000) * 1000
        : milisegundos;

    if (!referenciaAt) return null;

    return (
        <div className={`flex items-center gap-2 rounded-xl ${compacto ? 'px-2 py-1.5' : 'px-3 py-2'} ${alerta ? 'bg-amber-500/15' : 'bg-black/5 dark:bg-white/5'}`}>
            <Clock className={`${compacto ? 'w-3.5 h-3.5' : 'w-4 h-4'} shrink-0 ${alerta ? 'text-amber-600 dark:text-amber-400' : 'theme-text-muted'}`} aria-hidden />
            <div className="min-w-0">
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">{etiqueta}</p>
                <p
                    className={`${compacto ? 'text-sm' : 'text-lg'} font-black tabular-nums m-0 ${alerta ? 'text-amber-700 dark:text-amber-300' : 'theme-text-main'}`}
                    aria-live="off"
                >
                    {formatearCronometro(display)}
                </p>
            </div>
        </div>
    );
}
