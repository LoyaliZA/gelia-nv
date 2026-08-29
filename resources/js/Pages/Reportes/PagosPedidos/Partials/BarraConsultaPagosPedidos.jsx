import React, { useEffect, useMemo, useState } from 'react';
import { Calendar, Filter, Search, SlidersHorizontal, X } from 'lucide-react';
import RangoFechasPersonalizado from '@/Components/Filtros/RangoFechasPersonalizado';

const PRESETS = [
    { id: 'TODAS', label: 'Histórico completo' },
    { id: 'HOY', label: 'Hoy' },
    { id: 'AYER', label: 'Ayer' },
    { id: 'SEMANA', label: 'Esta semana' },
    { id: 'MES', label: 'Este mes' },
    { id: 'PERSONALIZADO', label: 'Rango personalizado' },
];

function fmt(d) {
    return d.toISOString().slice(0, 10);
}

function inferirPreset(desde, hasta) {
    if (!desde && !hasta) return 'TODAS';
    const hoy = fmt(new Date());
    if (desde === hoy && hasta === hoy) return 'HOY';
    const ayer = new Date();
    ayer.setDate(ayer.getDate() - 1);
    const ayerStr = fmt(ayer);
    if (desde === ayerStr && hasta === ayerStr) return 'AYER';
    if (desde && hasta && desde !== hasta) return 'PERSONALIZADO';
    return 'PERSONALIZADO';
}

function rangoPreset(preset) {
    const hoy = new Date();
    const hoyStr = fmt(hoy);
    if (preset === 'TODAS') return { fecha_validacion_desde: null, fecha_validacion_hasta: null };
    if (preset === 'HOY') return { fecha_validacion_desde: hoyStr, fecha_validacion_hasta: hoyStr };
    if (preset === 'AYER') {
        const a = new Date();
        a.setDate(a.getDate() - 1);
        const s = fmt(a);
        return { fecha_validacion_desde: s, fecha_validacion_hasta: s };
    }
    if (preset === 'SEMANA') {
        const d = new Date();
        const day = d.getDay() || 7;
        d.setDate(d.getDate() - day + 1);
        return { fecha_validacion_desde: fmt(d), fecha_validacion_hasta: hoyStr };
    }
    if (preset === 'MES') {
        const d = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        return { fecha_validacion_desde: fmt(d), fecha_validacion_hasta: hoyStr };
    }
    return null;
}

const CAMPOS_FILTRO = [
    'estado_cierre', 'estado_cobertura', 'forma_pago', 'con_remision', 'con_evidencia',
    'departamento_id', 'vendedor_id', 'cliente_id', 'banco_id', 'almacen_id',
];

function contarFiltrosAdicionales(filtros) {
    let n = 0;
    for (const k of CAMPOS_FILTRO) {
        if (filtros[k] && filtros[k] !== 'vigente') n++;
    }
    if (filtros.fecha_validacion_desde || filtros.fecha_validacion_hasta) n++;
    return n;
}

export default function BarraConsultaPagosPedidos({ filtros, formasPago, onAplicar, onLimpiarTodo }) {
    const [busquedaLocal, setBusquedaLocal] = useState(filtros.busqueda || '');
    const [mostrarFiltros, setMostrarFiltros] = useState(contarFiltrosAdicionales(filtros) > 0);
    const [preset, setPreset] = useState(() => inferirPreset(filtros.fecha_validacion_desde, filtros.fecha_validacion_hasta));

    const [local, setLocal] = useState({
        fecha_validacion_desde: filtros.fecha_validacion_desde || '',
        fecha_validacion_hasta: filtros.fecha_validacion_hasta || '',
        estado_cierre: filtros.estado_cierre || 'vigente',
        estado_cobertura: filtros.estado_cobertura || '',
        forma_pago: filtros.forma_pago || '',
        con_remision: filtros.con_remision ?? '',
        con_evidencia: filtros.con_evidencia ?? '',
    });

    useEffect(() => {
        setBusquedaLocal(filtros.busqueda || '');
        setLocal({
            fecha_validacion_desde: filtros.fecha_validacion_desde || '',
            fecha_validacion_hasta: filtros.fecha_validacion_hasta || '',
            estado_cierre: filtros.estado_cierre || 'vigente',
            estado_cobertura: filtros.estado_cobertura || '',
            forma_pago: filtros.forma_pago || '',
            con_remision: filtros.con_remision ?? '',
            con_evidencia: filtros.con_evidencia ?? '',
        });
        setPreset(inferirPreset(filtros.fecha_validacion_desde, filtros.fecha_validacion_hasta));
    }, [filtros]);

    const filtrosActivos = useMemo(() => contarFiltrosAdicionales(filtros), [filtros]);

    const aplicarBusqueda = () => {
        onAplicar({ busqueda: busquedaLocal.trim() || null });
    };

    const aplicarFiltrosPanel = () => {
        onAplicar({
            ...local,
            fecha_validacion_desde: local.fecha_validacion_desde || null,
            fecha_validacion_hasta: local.fecha_validacion_hasta || null,
            estado_cobertura: local.estado_cobertura || null,
            forma_pago: local.forma_pago || null,
            con_remision: local.con_remision || null,
            con_evidencia: local.con_evidencia || null,
        });
    };

    const cambiarPreset = (id) => {
        setPreset(id);
        if (id === 'PERSONALIZADO') {
            setMostrarFiltros(true);
            return;
        }
        const rango = rangoPreset(id);
        if (rango) {
            setLocal((s) => ({ ...s, ...rango, fecha_validacion_desde: rango.fecha_validacion_desde || '', fecha_validacion_hasta: rango.fecha_validacion_hasta || '' }));
            onAplicar({ ...rango, busqueda: busquedaLocal.trim() || null });
        }
    };

    const limpiarFiltros = () => {
        setPreset('TODAS');
        setLocal({
            fecha_validacion_desde: '',
            fecha_validacion_hasta: '',
            estado_cierre: 'vigente',
            estado_cobertura: '',
            forma_pago: '',
            con_remision: '',
            con_evidencia: '',
        });
        onLimpiarTodo();
    };

    const hayFiltrosActivos = filtrosActivos > 0 || (filtros.busqueda && filtros.busqueda.trim());

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-1.5">
                <label htmlFor="pagos-pedidos-periodo" className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted flex items-center gap-1.5 m-0">
                    <Calendar className="w-3.5 h-3.5" style={{ color: 'var(--color-primario)' }} />
                    Periodo de validación
                </label>
                <select
                    id="pagos-pedidos-periodo"
                    value={preset}
                    onChange={(e) => cambiarPreset(e.target.value)}
                    className="w-full sm:max-w-xs px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 cursor-pointer shadow-sm"
                >
                    {PRESETS.map((p) => (
                        <option key={p.id} value={p.id}>{p.label}</option>
                    ))}
                </select>
            </div>

            <div className="flex flex-col sm:flex-row gap-3">
                <div className="flex flex-col sm:flex-row gap-2 flex-1 min-w-0">
                    <div className="relative flex-1 min-w-0">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted pointer-events-none" />
                        <input
                            type="text"
                            placeholder="Buscar folio, remisión, cliente, quien atendió, referencia…"
                            value={busquedaLocal}
                            onChange={(e) => setBusquedaLocal(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    aplicarBusqueda();
                                }
                            }}
                            enterKeyHint="search"
                            autoComplete="off"
                            className="w-full pl-12 pr-4 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm"
                        />
                    </div>
                    <button
                        type="button"
                        onClick={aplicarBusqueda}
                        className="w-full sm:w-auto shrink-0 px-6 py-4 rounded-xl font-semibold text-xs tracking-wide text-white hover:scale-[1.02] transition-all shadow-md flex items-center justify-center gap-2"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Search className="w-4 h-4 shrink-0" />
                        Buscar
                    </button>
                </div>

                <div className="flex flex-wrap gap-2 shrink-0">
                    <button
                        type="button"
                        onClick={() => setMostrarFiltros((v) => !v)}
                        aria-expanded={mostrarFiltros}
                        className={`flex items-center justify-center gap-2 px-5 py-4 rounded-xl border text-xs font-semibold transition-all ${
                            mostrarFiltros || filtrosActivos > 0
                                ? 'border-[var(--color-primario)] text-[var(--color-primario)] bg-[color-mix(in_srgb,var(--color-primario)_10%,transparent)]'
                                : 'theme-border theme-element theme-text-muted hover:border-[var(--color-primario)]'
                        }`}
                    >
                        <SlidersHorizontal className="w-4 h-4 shrink-0" />
                        Más filtros
                        {filtrosActivos > 0 && (
                            <span className="w-5 h-5 rounded-full text-white text-[9px] flex items-center justify-center" style={{ backgroundColor: 'var(--color-primario)' }}>
                                {filtrosActivos}
                            </span>
                        )}
                    </button>

                    {hayFiltrosActivos && (
                        <button
                            type="button"
                            onClick={limpiarFiltros}
                            className="flex items-center justify-center gap-2 px-5 py-4 rounded-xl border theme-border theme-element text-xs font-semibold theme-text-muted hover:text-red-500 hover:border-red-400/50 transition-all"
                        >
                            <X className="w-4 h-4 shrink-0" />
                            Borrar filtros
                        </button>
                    )}
                </div>
            </div>

            {mostrarFiltros && (
                <div className="theme-surface rounded-2xl border theme-border p-5 md:p-6 shadow-sm space-y-5 animate-page-reveal">
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted flex items-center gap-2 m-0">
                            <Filter className="w-3.5 h-3.5" /> Filtros adicionales
                        </p>
                        <button
                            type="button"
                            onClick={limpiarFiltros}
                            className="text-xs font-medium theme-text-muted hover:text-red-500 flex items-center gap-1 transition-colors"
                        >
                            <X className="w-3 h-3" /> Limpiar
                        </button>
                    </div>

                    {preset === 'PERSONALIZADO' && (
                        <RangoFechasPersonalizado
                            idPrefix="pagos-pedidos-validacion"
                            fechaInicio={local.fecha_validacion_desde}
                            fechaFin={local.fecha_validacion_hasta}
                            mostrarBotonAplicar={false}
                            onCambio={({ fecha_inicio, fecha_fin }) => {
                                setLocal((s) => ({
                                    ...s,
                                    fecha_validacion_desde: fecha_inicio ?? s.fecha_validacion_desde,
                                    fecha_validacion_hasta: fecha_fin ?? s.fecha_validacion_hasta,
                                }));
                            }}
                        />
                    )}

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <label className="theme-label block">
                            Estado cierre
                            <select value={local.estado_cierre} onChange={(e) => setLocal((s) => ({ ...s, estado_cierre: e.target.value }))} className="theme-select block mt-1.5 w-full">
                                <option value="vigente">Vigente</option>
                                <option value="revocado">Revocado</option>
                                <option value="todos">Todos</option>
                            </select>
                        </label>
                        <label className="theme-label block">
                            Cobertura
                            <select value={local.estado_cobertura} onChange={(e) => setLocal((s) => ({ ...s, estado_cobertura: e.target.value }))} className="theme-select block mt-1.5 w-full">
                                <option value="">Todas</option>
                                <option value="cubierto">Cubierto</option>
                                <option value="parcial">Parcial</option>
                                <option value="con_excedente">Excedente</option>
                                <option value="sin_pago">Sin pago</option>
                            </select>
                        </label>
                        <label className="theme-label block">
                            Forma de pago
                            <select value={local.forma_pago} onChange={(e) => setLocal((s) => ({ ...s, forma_pago: e.target.value }))} className="theme-select block mt-1.5 w-full">
                                <option value="">Todas</option>
                                {formasPago.map((f) => (
                                    <option key={f.codigo} value={f.codigo}>{f.label}</option>
                                ))}
                            </select>
                        </label>
                        <label className="theme-label block">
                            Remisión
                            <select value={local.con_remision} onChange={(e) => setLocal((s) => ({ ...s, con_remision: e.target.value }))} className="theme-select block mt-1.5 w-full">
                                <option value="">Todas</option>
                                <option value="1">Con remisión</option>
                                <option value="0">Sin remisión</option>
                            </select>
                        </label>
                        <label className="theme-label block">
                            Evidencia
                            <select value={local.con_evidencia} onChange={(e) => setLocal((s) => ({ ...s, con_evidencia: e.target.value }))} className="theme-select block mt-1.5 w-full">
                                <option value="">Todas</option>
                                <option value="1">Con evidencia</option>
                                <option value="0">Sin evidencia</option>
                            </select>
                        </label>
                    </div>

                    <div className="flex justify-end">
                        <button type="button" onClick={aplicarFiltrosPanel} className="theme-btn-primary theme-btn-primary--compact">
                            Aplicar filtros
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
