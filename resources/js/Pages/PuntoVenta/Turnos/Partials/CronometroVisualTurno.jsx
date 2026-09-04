import React, { useEffect, useState } from 'react';
import { Clock } from 'lucide-react';
import { formatearCronometro } from './tableroVentasUtils';

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

export default function CronometroVisualTurno({
    etiqueta,
    referenciaAt,
    servidorAt,
    modo = 'restante',
    alerta = false,
}) {
    const [milisegundos, setMilisegundos] = useState(() => calcularDisplay(modo, referenciaAt, servidorAt));

    useEffect(() => {
        const actualizar = () => setMilisegundos(calcularDisplay(modo, referenciaAt, servidorAt));
        actualizar();
        const intervalo = setInterval(actualizar, 1000);
        return () => clearInterval(intervalo);
    }, [modo, referenciaAt, servidorAt]);

    return (
        <div className={`flex items-center gap-2 rounded-xl px-3 py-2 ${alerta ? 'bg-amber-500/15' : 'bg-black/5 dark:bg-white/5'}`}>
            <Clock className={`w-4 h-4 shrink-0 ${alerta ? 'text-amber-600 dark:text-amber-400' : 'theme-text-muted'}`} aria-hidden />
            <div className="min-w-0">
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">{etiqueta}</p>
                <p
                    className={`text-lg font-black tabular-nums m-0 ${alerta ? 'text-amber-700 dark:text-amber-300' : 'theme-text-main'}`}
                    aria-live="off"
                >
                    {formatearCronometro(milisegundos)}
                </p>
            </div>
        </div>
    );
}
