import React, { useEffect, useState } from 'react';
import { AlertCircle, AlertTriangle, CheckCircle2, Clock, Info, Loader2, XCircle } from 'lucide-react';
import { GELIA_ESTADO_VIVO_TONO } from '@/utils/geliaTheme';
import { AVISO_ESPERA_SEGUNDOS, TRABAJO } from './geliaAiEstados';

const ICONOS = {
    reloj: Clock,
    carga: Loader2,
    ok: CheckCircle2,
    cancelada: XCircle,
    error: AlertCircle,
    aviso: AlertTriangle,
    info: Info,
};

/** Insignia de estado: siempre icono + texto, nunca solo color. */
export function EstadoBadge({ etiqueta, tono = 'neutro', icono = 'info' }) {
    const Icono = ICONOS[icono] || Info;
    return (
        <span className={`gelia-estado-vivo gelia-estado-vivo--compacto gelia-ai-badge ${GELIA_ESTADO_VIVO_TONO[tono] || GELIA_ESTADO_VIVO_TONO.neutro}`}>
            <Icono className={`w-3.5 h-3.5 ${icono === 'carga' ? 'animate-spin motion-reduce:animate-none' : ''}`} aria-hidden />
            {etiqueta}
        </span>
    );
}

/** Aviso dentro de la conversación (error, respuesta incompleta, datos faltantes). */
export function GeliaAiAviso({ tono = 'aviso', titulo, children, accion = null }) {
    const Icono = tono === 'error' ? AlertCircle : AlertTriangle;
    return (
        <div className="gelia-ai-aviso" data-tono={tono} role={tono === 'error' ? 'alert' : undefined}>
            <Icono className="gelia-ai-aviso-icon w-4 h-4" aria-hidden />
            <div className="gelia-ai-aviso-body">
                <p className="gelia-ai-aviso-title">{titulo}</p>
                {children}
                {accion}
            </div>
        </div>
    );
}

/** Trabajo en curso: describe la petición abierta y el tiempo transcurrido, sin inventar pasos. */
export function EstadoTrabajoGelia({ trabajo, limiteSegundos = 90 }) {
    const [segundos, setSegundos] = useState(0);

    useEffect(() => {
        if (!trabajo) return undefined;
        const tick = () => setSegundos(Math.floor((Date.now() - trabajo.desde) / 1000));
        tick();
        const id = window.setInterval(tick, 1000);
        return () => window.clearInterval(id);
    }, [trabajo]);

    if (!trabajo) return null;

    return (
        <div className="gelia-ai-status" role="status">
            <Loader2 className="w-4 h-4 animate-spin motion-reduce:animate-none" aria-hidden />
            <span className="gelia-ai-status-text">
                {TRABAJO[trabajo.tipo] || TRABAJO.consulta}
                {segundos > 0 && <span className="gelia-ai-status-time" aria-hidden> · {segundos} s</span>}
            </span>
            {trabajo.tipo === 'consulta' && segundos >= AVISO_ESPERA_SEGUNDOS && (
                <span className="gelia-ai-status-hint">
                    Las consultas que revisan inventario o archivos pueden tardar hasta {limiteSegundos} s.
                </span>
            )}
        </div>
    );
}
