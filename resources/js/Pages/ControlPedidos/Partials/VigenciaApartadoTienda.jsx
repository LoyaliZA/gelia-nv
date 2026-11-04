import React, { useEffect, useState } from 'react';
import { Clock } from 'lucide-react';

export default function VigenciaApartadoTienda({ venceAt, finalizado = false }) {
    const [ahora, setAhora] = useState(Date.now);
    useEffect(() => {
        if (!venceAt || finalizado) return undefined;
        const timer = setInterval(() => setAhora(Date.now()), 1000);
        return () => clearInterval(timer);
    }, [venceAt, finalizado]);
    if (!venceAt || finalizado || !Number.isFinite(Date.parse(venceAt))) return null;
    const segundos = Math.max(0, Math.floor((Date.parse(venceAt) - ahora) / 1000));
    const hh = Math.floor(segundos / 3600);
    const mm = Math.floor(segundos % 3600 / 60);
    const ss = segundos % 60;
    return <div className="flex flex-wrap items-center gap-2 text-sm theme-text-main">
        <Clock size={16} aria-hidden="true" />
        <span>{segundos ? 'Vigencia del apartado' : 'Vigencia terminada · pendiente de liberar en tienda'}</span>
        {segundos > 0 && <strong className="tabular-nums" role="timer" aria-label="Tiempo restante del apartado">{[hh, mm, ss].map(n => String(n).padStart(2, '0')).join(':')}</strong>}
        <span className="theme-text-muted">Hasta {new Date(venceAt).toLocaleString('es-MX', { timeZone: 'America/Mexico_City', hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' })}</span>
    </div>;
}
