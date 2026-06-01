import React, { useEffect, useRef, useState } from 'react';
import { Calendar } from 'lucide-react';
import { normalizarFechaAlConfirmar } from '@/utils/fechaFiltro';

/**
 * Rango de fechas con estado local. Mientras el usuario escribe, se guarda el valor crudo del input.
 * La validación ISO ocurre en onBlur o Enter, no en cada tecla.
 */
export default function RangoFechasPersonalizado({
    fechaInicio = '',
    fechaFin = '',
    onAplicar,
    onCambio,
    mostrarBotonAplicar = true,
    idPrefix = 'filtro-fecha',
    textoAyuda = mostrarBotonAplicar
        ? 'Elige las fechas en el calendario y pulsa «Aplicar rango» para consultar (cambiar de mes no dispara la búsqueda).'
        : 'Elige las fechas; la consulta se hará al pulsar «Buscar» (cambiar de mes no dispara la búsqueda).',
}) {
    const [inicioLocal, setInicioLocal] = useState(fechaInicio);
    const [finLocal, setFinLocal] = useState(fechaFin);
    const inicioEnFoco = useRef(false);
    const finEnFoco = useRef(false);

    useEffect(() => {
        if (!inicioEnFoco.current) {
            setInicioLocal(fechaInicio);
        }
    }, [fechaInicio]);

    useEffect(() => {
        if (!finEnFoco.current) {
            setFinLocal(fechaFin);
        }
    }, [fechaFin]);

    const notificarCambio = (inicio, fin) => {
        onCambio?.({ fecha_inicio: inicio, fecha_fin: fin });
    };

    const confirmarInicio = (valorCrudo, valorRespaldo = fechaInicio) => {
        const { ok, valor } = normalizarFechaAlConfirmar(valorCrudo);
        if (ok) {
            setInicioLocal(valor);
            notificarCambio(valor, finLocal);
            return true;
        }
        setInicioLocal(valorRespaldo);
        return false;
    };

    const confirmarFin = (valorCrudo, valorRespaldo = fechaFin) => {
        const { ok, valor } = normalizarFechaAlConfirmar(valorCrudo);
        if (ok) {
            setFinLocal(valor);
            notificarCambio(inicioLocal, valor);
            return true;
        }
        setFinLocal(valorRespaldo);
        return false;
    };

    const aplicar = () => {
        const inicio = normalizarFechaAlConfirmar(inicioLocal);
        const fin = normalizarFechaAlConfirmar(finLocal);
        if (!inicio.ok) {
            setInicioLocal(fechaInicio);
            return;
        }
        if (!fin.ok) {
            setFinLocal(fechaFin);
            return;
        }
        setInicioLocal(inicio.valor);
        setFinLocal(fin.valor);
        notificarCambio(inicio.valor, fin.valor);
        onAplicar?.({
            fecha_inicio: inicio.valor,
            fecha_fin: fin.valor,
        });
    };

    return (
        <div className="theme-surface rounded-2xl border theme-border p-4 flex flex-col gap-3 shadow-sm">
            <p className="text-[10px] font-bold theme-text-muted m-0">{textoAyuda}</p>
            <div className="flex flex-col sm:flex-row gap-3 items-end">
                <div className="flex-1 w-full flex flex-col gap-1.5">
                    <label htmlFor={`${idPrefix}-inicio`} className="text-[10px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-1">
                        <Calendar className="w-3 h-3" /> Desde
                    </label>
                    <input
                        id={`${idPrefix}-inicio`}
                        type="date"
                        value={inicioLocal}
                        onFocus={() => { inicioEnFoco.current = true; }}
                        onChange={(e) => {
                            const v = e.target.value;
                            setInicioLocal(v);
                            onCambio?.({ fecha_inicio: v, fecha_fin: finLocal });
                        }}
                        onBlur={(e) => {
                            inicioEnFoco.current = false;
                            confirmarInicio(e.target.value, fechaInicio);
                        }}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                confirmarInicio(e.currentTarget.value, fechaInicio);
                                if (mostrarBotonAplicar) aplicar();
                            }
                        }}
                        className="w-full px-4 py-2.5 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2"
                    />
                </div>
                <div className="flex-1 w-full flex flex-col gap-1.5">
                    <label htmlFor={`${idPrefix}-fin`} className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                        Hasta
                    </label>
                    <input
                        id={`${idPrefix}-fin`}
                        type="date"
                        value={finLocal}
                        onFocus={() => { finEnFoco.current = true; }}
                        onChange={(e) => {
                            const v = e.target.value;
                            setFinLocal(v);
                            onCambio?.({ fecha_inicio: inicioLocal, fecha_fin: v });
                        }}
                        onBlur={(e) => {
                            finEnFoco.current = false;
                            confirmarFin(e.target.value, fechaFin);
                        }}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                confirmarFin(e.currentTarget.value, fechaFin);
                                if (mostrarBotonAplicar) aplicar();
                            }
                        }}
                        className="w-full px-4 py-2.5 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2"
                    />
                </div>
                {mostrarBotonAplicar && (
                    <button
                        type="button"
                        onClick={aplicar}
                        disabled={!inicioLocal && !finLocal}
                        className="w-full sm:w-auto px-6 py-2.5 rounded-xl font-black uppercase text-[10px] tracking-widest text-white hover:scale-105 transition-all shadow-md disabled:opacity-40 disabled:hover:scale-100"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        Aplicar rango
                    </button>
                )}
            </div>
        </div>
    );
}
