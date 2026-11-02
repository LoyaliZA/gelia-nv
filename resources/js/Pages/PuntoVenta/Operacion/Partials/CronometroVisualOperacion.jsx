import React, { useEffect, useMemo, useState } from 'react';
import { ClockIcon } from '@animateicons/react/lucide/clock-icon';

function formatearCronometroHms(milisegundos) {
    if (milisegundos === null || milisegundos === undefined) return '—';
    const totalSegundos = Math.max(0, Math.floor(milisegundos / 1000));
    const horas = Math.floor(totalSegundos / 3600);
    const minutos = Math.floor((totalSegundos % 3600) / 60);
    const segundos = totalSegundos % 60;
    const reloj = `${String(minutos).padStart(2, '0')}:${String(segundos).padStart(2, '0')}`;

    if (horas < 1) {
        return reloj;
    }

    return `${String(horas).padStart(2, '0')}:${reloj}`;
}

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
    superficieAlerta = null,
    variante = 'bloque',
    prominente = false,
    integrado = false,
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

    const valor = formatearCronometroHms(display);

    if (variante === 'valor') {
        return <span className="tabular-nums">{valor}</span>;
    }

    const superficie = superficieAlerta === 'prorroga'
        ? 'gelia-estado-equipo--prorroga'
        : superficieAlerta === 'pausa'
            ? 'gelia-estado-equipo--pausa'
            : null;
    const heredaTono = Boolean(superficie);
    const caja = integrado
        ? 'flex items-center gap-2.5'
        : `flex items-center gap-2 rounded-xl border theme-border ${compacto ? 'px-2 py-1.5' : 'px-3 py-2'} ${prominente ? 'h-14' : ''} ${superficie || (alerta ? 'gelia-estado-vivo--aviso' : 'theme-element')}`;
    const icono = prominente ? 16 : (compacto ? 14 : 16);
    const valorClase = prominente
        ? 'text-xl font-bold leading-none'
        : `${compacto ? 'text-sm' : 'text-lg'} font-semibold`;
    const colorIcono = heredaTono ? '' : (alerta ? 'theme-text-aviso' : 'theme-text-muted');
    const colorEtiqueta = heredaTono || alerta ? '' : 'theme-text-muted';
    const colorValor = heredaTono ? '' : (alerta ? 'theme-text-aviso' : 'theme-text-main');

    return (
        <div className={caja}>
            <ClockIcon size={icono} className={`shrink-0 ${colorIcono}`} />
            <div className={`min-w-0 ${prominente ? 'flex flex-col gap-0.5' : ''}`}>
                <p className={`${prominente ? 'text-[11px] font-bold uppercase tracking-wide leading-none' : 'text-xs font-medium'} ${colorEtiqueta} m-0`}>{etiqueta}</p>
                <p
                    className={`${valorClase} tabular-nums m-0 ${colorValor}`}
                    aria-live="off"
                >
                    {valor}
                </p>
            </div>
        </div>
    );
}
