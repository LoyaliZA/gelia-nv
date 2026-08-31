import React, { useEffect, useMemo, useState } from 'react';
import { Calendar, Filter, Search, SlidersHorizontal, X } from 'lucide-react';
import RangoFechasPersonalizado from '@/Components/Filtros/RangoFechasPersonalizado';
import { diaCalendarioLocal } from '@/utils/fechasPagoReporte';
import { chipClass } from './pagosPedidosStyles';

const PRESETS = [
    { id: 'TODAS', label: 'Histórico completo' },
    { id: 'HOY', label: 'Hoy' },
    { id: 'AYER', label: 'Ayer' },
    { id: 'SEMANA', label: 'Esta semana' },
    { id: 'MES', label: 'Este mes' },
    { id: 'PERSONALIZADO', label: 'Rango personalizado' },
];

const ESTADOS_VALIDACION = [
    { value: '', label: 'Todos' },
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'verificado', label: 'Validado' },
    { value: 'rechazado', label: 'Rechazado' },
    { value: 'con_observaciones', label: 'Con observaciones' },
];

function fmt(d) {
    return diaCalendarioLocal(d);
}

function inferirPreset(desde, hasta) {
    if (!desde && !hasta) return 'TODAS';
    const hoy = fmt(new Date());
    if (desde === hoy && hasta === hoy) return 'HOY';
    const ayer = new Date();
    ayer.setDate(ayer.getDate() - 1);
    if (desde === fmt(ayer) && hasta === fmt(ayer)) return 'AYER';
    return 'PERSONALIZADO';
}

function rangoPreset(preset) {
    const hoy = new Date();
    const hoyStr = fmt(hoy);
    if (preset === 'TODAS') return { fecha_pago_desde: null, fecha_pago_hasta: null };
    if (preset === 'HOY') return { fecha_pago_desde: hoyStr, fecha_pago_hasta: hoyStr };
    if (preset === 'AYER') {
        const a = new Date();
        a.setDate(a.getDate() - 1);
        const s = fmt(a);
        return { fecha_pago_desde: s, fecha_pago_hasta: s };
    }
    if (preset === 'SEMANA') {
        const d = new Date();
        const day = d.getDay() || 7;
        d.setDate(d.getDate() - day + 1);
        return { fecha_pago_desde: fmt(d), fecha_pago_hasta: hoyStr };
    }
    if (preset === 'MES') {
        const d = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        return { fecha_pago_desde: fmt(d), fecha_pago_hasta: hoyStr };
    }
    return null;
}

const CAMPOS_EXTRA = [
    'forma_pago', 'estado_exhibicion', 'banco_id', 'capturado_por_id', 'validado_por_id',
    'reportado_posteriormente', 'posible_duplicado', 'con_saf_relacionado', 'con_observaciones',
    'con_evidencia', 'monto_desde', 'monto_hasta', 'folio_pedido', 'folio_remision',
    'fecha_reportada_desde', 'fecha_reportada_hasta', 'fecha_validacion_desde', 'fecha_validacion_hasta',
];

function contarFiltros(filtros) {
    let n = 0;
    for (const k of CAMPOS_EXTRA) {
        if (filtros[k] && filtros[k] !== false) n++;
    }
    return n;
}

export default function BarraConsultaVouchersValidados({
    filtros,
    formasPago = [],
    bancos = [],
    capturadores = [],
    validadores = [],
    onAplicar,
    onLimpiarTodo,
}) {
    const [busquedaLocal, setBusquedaLocal] = useState(filtros.busqueda || '');
    const [mostrarFiltros, setMostrarFiltros] = useState(contarFiltros(filtros) > 0);
    const [preset, setPreset] = useState(() => inferirPreset(filtros.fecha_pago_desde, filtros.fecha_pago_hasta));

    const [local, setLocal] = useState({
        fecha_pago_desde: filtros.fecha_pago_desde || '',
        fecha_pago_hasta: filtros.fecha_pago_hasta || '',
        fecha_reportada_desde: filtros.fecha_reportada_desde || '',
        fecha_reportada_hasta: filtros.fecha_reportada_hasta || '',
        fecha_validacion_desde: filtros.fecha_validacion_desde || '',
        fecha_validacion_hasta: filtros.fecha_validacion_hasta || '',
        forma_pago: filtros.forma_pago || '',
        estado_exhibicion: filtros.estado_exhibicion || '',
        banco_id: filtros.banco_id || '',
        capturado_por_id: filtros.capturado_por_id || '',
        validado_por_id: filtros.validado_por_id || '',
        monto_desde: filtros.monto_desde || '',
        monto_hasta: filtros.monto_hasta || '',
        folio_pedido: filtros.folio_pedido || '',
        folio_remision: filtros.folio_remision || '',
        con_evidencia: filtros.con_evidencia ?? '',
        reportado_posteriormente: filtros.reportado_posteriormente ? '1' : '',
        posible_duplicado: filtros.posible_duplicado ? '1' : '',
        con_saf_relacionado: filtros.con_saf_relacionado ? '1' : '',
        con_observaciones: filtros.con_observaciones ? '1' : '',
    });

    useEffect(() => {
        setBusquedaLocal(filtros.busqueda || '');
        setLocal({
            fecha_pago_desde: filtros.fecha_pago_desde || '',
            fecha_pago_hasta: filtros.fecha_pago_hasta || '',
            fecha_reportada_desde: filtros.fecha_reportada_desde || '',
            fecha_reportada_hasta: filtros.fecha_reportada_hasta || '',
            fecha_validacion_desde: filtros.fecha_validacion_desde || '',
            fecha_validacion_hasta: filtros.fecha_validacion_hasta || '',
            forma_pago: filtros.forma_pago || '',
            estado_exhibicion: filtros.estado_exhibicion || '',
            banco_id: filtros.banco_id || '',
            capturado_por_id: filtros.capturado_por_id || '',
            validado_por_id: filtros.validado_por_id || '',
            monto_desde: filtros.monto_desde || '',
            monto_hasta: filtros.monto_hasta || '',
            folio_pedido: filtros.folio_pedido || '',
            folio_remision: filtros.folio_remision || '',
            con_evidencia: filtros.con_evidencia ?? '',
            reportado_posteriormente: filtros.reportado_posteriormente ? '1' : '',
            posible_duplicado: filtros.posible_duplicado ? '1' : '',
            con_saf_relacionado: filtros.con_saf_relacionado ? '1' : '',
            con_observaciones: filtros.con_observaciones ? '1' : '',
        });
        setPreset(inferirPreset(filtros.fecha_pago_desde, filtros.fecha_pago_hasta));
    }, [filtros]);

    const filtrosActivos = useMemo(() => contarFiltros(filtros), [filtros]);

    const boolParam = (v) => v === '1' ? true : null;

    const aplicarBusqueda = () => onAplicar({ busqueda: busquedaLocal.trim() || null });

    const aplicarFiltrosPanel = () => {
        onAplicar({
            ...local,
            fecha_pago_desde: local.fecha_pago_desde || null,
            fecha_pago_hasta: local.fecha_pago_hasta || null,
            fecha_reportada_desde: local.fecha_reportada_desde || null,
            fecha_reportada_hasta: local.fecha_reportada_hasta || null,
            fecha_validacion_desde: local.fecha_validacion_desde || null,
            fecha_validacion_hasta: local.fecha_validacion_hasta || null,
            forma_pago: local.forma_pago || null,
            estado_exhibicion: local.estado_exhibicion || null,
            banco_id: local.banco_id || null,
            capturado_por_id: local.capturado_por_id || null,
            validado_por_id: local.validado_por_id || null,
            monto_desde: local.monto_desde || null,
            monto_hasta: local.monto_hasta || null,
            folio_pedido: local.folio_pedido || null,
            folio_remision: local.folio_remision || null,
            con_evidencia: local.con_evidencia || null,
            reportado_posteriormente: boolParam(local.reportado_posteriormente),
            posible_duplicado: boolParam(local.posible_duplicado),
            con_saf_relacionado: boolParam(local.con_saf_relacionado),
            con_observaciones: boolParam(local.con_observaciones),
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
            setLocal((s) => ({
                ...s,
                fecha_pago_desde: rango.fecha_pago_desde || '',
                fecha_pago_hasta: rango.fecha_pago_hasta || '',
            }));
            onAplicar({ ...rango, busqueda: busquedaLocal.trim() || null });
        }
    };

    const hayActivos = filtrosActivos > 0 || (filtros.busqueda && filtros.busqueda.trim());

    const chips = [];
    if (filtros.forma_pago) chips.push({ key: 'forma_pago', label: `Forma: ${filtros.forma_pago}` });
    if (filtros.estado_exhibicion) chips.push({ key: 'estado_exhibicion', label: `Estado: ${filtros.estado_exhibicion}` });
    if (filtros.reportado_posteriormente) chips.push({ key: 'reportado_posteriormente', label: 'Reportado posteriormente' });
    if (filtros.posible_duplicado) chips.push({ key: 'posible_duplicado', label: 'Posible duplicado' });

    const quitarChip = (key) => {
        const patch = { [key]: null };
        if (key === 'reportado_posteriormente' || key === 'posible_duplicado' || key === 'con_saf_relacionado' || key === 'con_observaciones') {
            patch[key] = false;
        }
        onAplicar(patch);
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-end gap-3">
                <label className="flex flex-col gap-1 min-w-[12rem]">
                    <span className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted">Revisión administrativa</span>
                    <select
                        className="rounded-xl border theme-border theme-element theme-text-main text-sm px-3 py-2.5"
                        value={filtros.estado_admin || 'pendiente'}
                        onChange={(e) => onAplicar({ estado_admin: e.target.value })}
                    >
                        <option value="pendiente">Pendientes de revisión</option>
                        <option value="confirmado">Confirmados</option>
                        <option value="con_error">Con error reportado</option>
                        <option value="todos">Todos</option>
                    </select>
                </label>
            </div>
            <div className="flex flex-col gap-1.5">
                <label className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted flex items-center gap-1.5 m-0">
                    <Calendar className="w-3.5 h-3.5" style={{ color: 'var(--color-primario)' }} />
                    Fecha del movimiento
                </label>
                <select
                    value={preset}
                    onChange={(e) => cambiarPreset(e.target.value)}
                    className="theme-select w-full sm:max-w-xs"
                >
                    {PRESETS.map((p) => (
                        <option key={p.id} value={p.id}>{p.label}</option>
                    ))}
                </select>
            </div>

            <div className="flex flex-col sm:flex-row gap-3">
                <div className="flex-1 flex gap-2 min-w-0">
                    <div className="relative flex-1 min-w-0">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                        <input
                            type="search"
                            value={busquedaLocal}
                            onChange={(e) => setBusquedaLocal(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && aplicarBusqueda()}
                            placeholder="Folio, remisión, cliente, referencia, banco o monto…"
                            className="theme-input w-full pl-9"
                        />
                    </div>
                    <button type="button" onClick={aplicarBusqueda} className="theme-btn-primary theme-btn-primary--compact shrink-0">
                        Buscar
                    </button>
                </div>
                <button
                    type="button"
                    onClick={() => setMostrarFiltros((v) => !v)}
                    className="theme-btn-secondary theme-btn-secondary--compact inline-flex items-center gap-2 shrink-0"
                >
                    <SlidersHorizontal className="w-4 h-4" />
                    Filtros
                    {filtrosActivos > 0 && (
                        <span className="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-[var(--color-primario)] text-white">
                            {filtrosActivos}
                        </span>
                    )}
                </button>
            </div>

            {chips.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {chips.map((c) => (
                        <button key={c.key} type="button" className={chipClass} onClick={() => quitarChip(c.key)}>
                            {c.label}
                            <X className="w-3 h-3" />
                        </button>
                    ))}
                </div>
            )}

            {mostrarFiltros && (
                <div className="rounded-xl border theme-border p-4 md:p-5 space-y-4 theme-element">
                    <div className="flex items-center gap-2">
                        <Filter className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                        <p className="text-sm font-semibold theme-text-main m-0">Filtros de vouchers</p>
                    </div>

                    <RangoFechasPersonalizado
                        etiqueta="Fecha del movimiento"
                        fechaInicio={local.fecha_pago_desde}
                        fechaFin={local.fecha_pago_hasta}
                        onChange={({ fecha_inicio, fecha_fin }) => setLocal((s) => ({
                            ...s,
                            fecha_pago_desde: fecha_inicio ?? s.fecha_pago_desde,
                            fecha_pago_hasta: fecha_fin ?? s.fecha_pago_hasta,
                        }))}
                    />
                    <RangoFechasPersonalizado
                        etiqueta="Fecha de reporte"
                        fechaInicio={local.fecha_reportada_desde}
                        fechaFin={local.fecha_reportada_hasta}
                        onChange={({ fecha_inicio, fecha_fin }) => setLocal((s) => ({
                            ...s,
                            fecha_reportada_desde: fecha_inicio ?? s.fecha_reportada_desde,
                            fecha_reportada_hasta: fecha_fin ?? s.fecha_reportada_hasta,
                        }))}
                    />
                    <RangoFechasPersonalizado
                        etiqueta="Fecha de validación"
                        fechaInicio={local.fecha_validacion_desde}
                        fechaFin={local.fecha_validacion_hasta}
                        onChange={({ fecha_inicio, fecha_fin }) => setLocal((s) => ({
                            ...s,
                            fecha_validacion_desde: fecha_inicio ?? s.fecha_validacion_desde,
                            fecha_validacion_hasta: fecha_fin ?? s.fecha_validacion_hasta,
                        }))}
                    />

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <label className="theme-label block">
                            Banco
                            <select value={local.banco_id} onChange={(e) => setLocal((s) => ({ ...s, banco_id: e.target.value }))} className="theme-select block mt-1.5 w-full">
                                <option value="">Todos</option>
                                {bancos.map((b) => (
                                    <option key={b.id} value={b.id}>{b.nombre}</option>
                                ))}
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
                            Estado de validación
                            <select value={local.estado_exhibicion} onChange={(e) => setLocal((s) => ({ ...s, estado_exhibicion: e.target.value }))} className="theme-select block mt-1.5 w-full">
                                {ESTADOS_VALIDACION.map((o) => (
                                    <option key={o.value || 'todos'} value={o.value}>{o.label}</option>
                                ))}
                            </select>
                        </label>
                        <label className="theme-label block">
                            Reportado por
                            <select value={local.capturado_por_id} onChange={(e) => setLocal((s) => ({ ...s, capturado_por_id: e.target.value }))} className="theme-select block mt-1.5 w-full">
                                <option value="">Todos</option>
                                {capturadores.map((u) => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                        </label>
                        <label className="theme-label block">
                            Validado por
                            <select value={local.validado_por_id} onChange={(e) => setLocal((s) => ({ ...s, validado_por_id: e.target.value }))} className="theme-select block mt-1.5 w-full">
                                <option value="">Todos</option>
                                {validadores.map((u) => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                        </label>
                        <label className="theme-label block">
                            Voucher
                            <select value={local.con_evidencia} onChange={(e) => setLocal((s) => ({ ...s, con_evidencia: e.target.value }))} className="theme-select block mt-1.5 w-full">
                                <option value="">Todos</option>
                                <option value="1">Con voucher</option>
                                <option value="0">Sin voucher</option>
                            </select>
                        </label>
                        <label className="theme-label block">
                            Folio pedido
                            <input type="text" value={local.folio_pedido} onChange={(e) => setLocal((s) => ({ ...s, folio_pedido: e.target.value }))} className="theme-input block mt-1.5 w-full" />
                        </label>
                        <label className="theme-label block">
                            Número remisión
                            <input type="text" value={local.folio_remision} onChange={(e) => setLocal((s) => ({ ...s, folio_remision: e.target.value }))} className="theme-input block mt-1.5 w-full" />
                        </label>
                        <label className="theme-label block">
                            Monto desde
                            <input type="number" min="0" step="0.01" value={local.monto_desde} onChange={(e) => setLocal((s) => ({ ...s, monto_desde: e.target.value }))} className="theme-input block mt-1.5 w-full" />
                        </label>
                        <label className="theme-label block">
                            Monto hasta
                            <input type="number" min="0" step="0.01" value={local.monto_hasta} onChange={(e) => setLocal((s) => ({ ...s, monto_hasta: e.target.value }))} className="theme-input block mt-1.5 w-full" />
                        </label>
                    </div>

                    <div className="flex flex-wrap gap-4">
                        {[
                            ['reportado_posteriormente', 'Reportado posteriormente'],
                            ['posible_duplicado', 'Posible duplicado'],
                            ['con_saf_relacionado', 'Con SAF relacionado'],
                            ['con_observaciones', 'Con observaciones'],
                        ].map(([key, label]) => (
                            <label key={key} className="flex items-center gap-2 text-xs font-medium theme-text-main cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={local[key] === '1'}
                                    onChange={(e) => setLocal((s) => ({ ...s, [key]: e.target.checked ? '1' : '' }))}
                                    className="rounded border theme-border"
                                />
                                {label}
                            </label>
                        ))}
                    </div>

                    <div className="flex flex-wrap gap-2 pt-2">
                        <button type="button" onClick={aplicarFiltrosPanel} className="theme-btn-primary theme-btn-primary--compact">
                            Aplicar filtros
                        </button>
                        {hayActivos && (
                            <button type="button" onClick={onLimpiarTodo} className="theme-btn-secondary theme-btn-secondary--compact">
                                Limpiar filtros
                            </button>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
