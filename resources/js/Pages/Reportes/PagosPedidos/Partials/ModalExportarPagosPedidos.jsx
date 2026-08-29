import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { FileSpreadsheet, FileText, Loader2, X } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';
import VistaGeneracionPagosPedidos from './VistaGeneracionPagosPedidos';
import VistaCompletadoPagosPedidos from './VistaCompletadoPagosPedidos';
import {
    clearPagosPedidosReporteTracking,
    cancelarPagosPedidosPdf,
    fetchEstadoPagosPedidosPdf,
    solicitarPagosPedidosExportacion,
    startPagosPedidosReporteTracking,
    urlDescargaPagosPedidos,
} from '../../../../utils/pagosPedidosReporteTracker';
import {
    AGRUPACIONES,
    ESTADOS_COBERTURA,
    ESTADOS_EXHIBICION,
    FORMATOS_EXPORT,
    PRESETS_RAPIDOS,
    TIPOS_FECHA,
    estadoInicialExport,
    payloadExport,
    queryStringExport,
    rangoPresetExport,
} from './exportarPagosPedidosUtils';

const LABEL = 'block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2';
const INPUT = 'w-full rounded-xl border theme-border bg-transparent px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-primario)] theme-text-main';
const CHECK = 'flex items-center gap-2 text-sm theme-text-main cursor-pointer';
const SECCION = 'text-[11px] font-semibold uppercase tracking-wide theme-text-muted m-0 pb-1 border-b theme-border';

function toggleLista(lista, valor) {
    return lista.includes(valor) ? lista.filter((v) => v !== valor) : [...lista, valor];
}

function etiquetaEstimacion(est) {
    if (!est) return null;
    const pedidos = (est.pedidos ?? 0).toLocaleString('es-MX');
    const exhibiciones = (est.exhibiciones ?? 0).toLocaleString('es-MX');
    const vouchers = (est.vouchers ?? 0).toLocaleString('es-MX');
    const tamano = est.tamano_etiqueta || '—';
    return `${pedidos} pedidos · ${exhibiciones} exhibiciones · ${vouchers} vouchers · Tamaño estimado: ${tamano}`;
}

export default function ModalExportarPagosPedidos({
    abierto,
    onCerrar,
    filtrosConsulta,
    jobIdSeguimiento = null,
    bancos = [],
    formasPago = [],
    departamentos = [],
    vendedores = [],
    almacenes = [],
    origenesPedido = [],
    puedeCsv,
    puedePdf,
}) {
    const [vista, setVista] = useState('config');
    const [estado, setEstado] = useState(() => estadoInicialExport(filtrosConsulta));
    const [estimacion, setEstimacion] = useState(null);
    const [estimacionCargando, setEstimacionCargando] = useState(false);
    const [jobId, setJobId] = useState(null);
    const [progresoPdf, setProgresoPdf] = useState(null);
    const [resultado, setResultado] = useState(null);
    const [cancelando, setCancelando] = useState(false);
    const debounceRef = useRef(null);
    const abortRef = useRef(null);
    const pollRef = useRef(null);

    useEffect(() => {
        if (!abierto) {
            if (pollRef.current) clearInterval(pollRef.current);
            return;
        }
        if (jobIdSeguimiento) {
            setJobId(jobIdSeguimiento);
            setVista('generating');
            setProgresoPdf({ progress: 0, status: 'processing', etapa_label: 'Preparando datos' });
            return;
        }
        setEstado(estadoInicialExport(filtrosConsulta));
        setEstimacion(null);
        setVista('config');
        setJobId(null);
        setProgresoPdf(null);
        setResultado(null);
        setCancelando(false);
    }, [abierto, filtrosConsulta, jobIdSeguimiento]);

    const formatosDisponibles = FORMATOS_EXPORT.filter((f) => {
        if (f.value === 'pdf') return puedePdf;
        return puedeCsv;
    });

    const formatoActual = formatosDisponibles.some((f) => f.value === estado.formato)
        ? estado.formato
        : (formatosDisponibles[0]?.value || 'pdf');

    useEffect(() => {
        if (!abierto || vista !== 'config') return undefined;

        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(async () => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            setEstimacionCargando(true);
            try {
                const payload = payloadExport({ ...estado, formato: formatoActual });
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                const res = await fetch(route('reportes.pagos_pedidos.exportar.estimacion'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                if (res.ok) {
                    setEstimacion(await res.json());
                }
            } catch (err) {
                if (err.name !== 'AbortError') setEstimacion(null);
            } finally {
                if (!controller.signal.aborted) setEstimacionCargando(false);
            }
        }, 400);

        return () => {
            clearTimeout(debounceRef.current);
            abortRef.current?.abort();
        };
    }, [abierto, vista, estado, formatoActual]);

    useEffect(() => {
        if (!abierto || !jobId || (vista !== 'generating' && vista !== 'completed')) return undefined;

        const poll = async () => {
            try {
                const st = await fetchEstadoPagosPedidosPdf(jobId);
                setProgresoPdf(st);
                if (st.status === 'completed') {
                    if (pollRef.current) clearInterval(pollRef.current);
                    setResultado(st);
                    setVista('completed');
                }
                if (st.status === 'failed' || st.status === 'cancelled') {
                    if (pollRef.current) clearInterval(pollRef.current);
                    setVista('generating');
                }
            } catch {
                /* polling silencioso */
            }
        };

        poll();
        pollRef.current = setInterval(poll, 1500);

        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, [abierto, jobId, vista]);

    if (!abierto) return null;

    const aplicarPreset = (id) => {
        const rango = rangoPresetExport(id);
        if (rango) setEstado((s) => ({ ...s, ...rango }));
    };

    const generar = async () => {
        const payload = payloadExport({ ...estado, formato: formatoActual });
        try {
            const data = await solicitarPagosPedidosExportacion(payload);
            setJobId(data.job_id);
            setProgresoPdf({
                progress: 0,
                status: 'processing',
                etapa_label: 'Preparando datos',
                started_at: new Date().toISOString(),
                cancelable: true,
            });
            setVista('generating');
            startPagosPedidosReporteTracking(data.job_id);
        } catch (err) {
            setProgresoPdf({ status: 'failed', error: err.message || 'No se pudo iniciar la generación' });
            setVista('generating');
        }
    };

    const continuarSegundoPlano = () => {
        onCerrar({ enGeneracion: true });
    };

    const cancelarGeneracion = async () => {
        if (!jobId || !progresoPdf?.cancelable) return;
        setCancelando(true);
        try {
            await cancelarPagosPedidosPdf(jobId);
        } catch (err) {
            setProgresoPdf((p) => ({ ...p, status: 'failed', error: err.message }));
        } finally {
            setCancelando(false);
        }
    };

    const cerrarModal = () => {
        if (pollRef.current) clearInterval(pollRef.current);
        const enGeneracion = vista === 'generating' && progresoPdf?.status === 'processing';
        onCerrar({ enGeneracion });
    };

    const descargarArchivo = () => {
        if (!jobId) return;
        clearPagosPedidosReporteTracking();
        window.location.href = urlDescargaPagosPedidos(jobId);
    };

    const generarOtro = () => {
        setEstado(estadoInicialExport(filtrosConsulta));
        setVista('config');
        setJobId(null);
        setProgresoPdf(null);
        setResultado(null);
    };

    const generando = vista === 'generating';
    const completado = vista === 'completed';
    const pdfFallo = progresoPdf?.status === 'failed' || progresoPdf?.status === 'cancelled';

    const textoEstimacion = estimacionCargando
        ? 'Calculando alcance del reporte…'
        : (etiquetaEstimacion(estimacion) || 'Sin datos para estimar con los filtros actuales');

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} role="dialog" aria-modal="true" aria-labelledby="export-pagos-titulo">
            <div className={`${THEME_MODAL_SHELL} max-w-3xl max-h-[90vh] flex flex-col`}>
                <div className="flex justify-between items-start gap-4 p-6 border-b theme-border shrink-0">
                    <div>
                        <h2 id="export-pagos-titulo" className="text-xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            {completado ? 'Reporte listo' : generando ? 'Generando reporte' : 'Generar reporte de pagos'}
                        </h2>
                        <p className="text-xs theme-text-muted mt-1 m-0">
                            {completado
                                ? 'Descarga el archivo o genera otro con distintos filtros.'
                                : generando
                                    ? 'El archivo se construye con los filtros seleccionados.'
                                    : 'Configura qué información necesita Administración.'}
                        </p>
                    </div>
                    <button type="button" onClick={cerrarModal} className="p-2 rounded-xl hover:bg-black/5 transition-colors" aria-label="Cerrar">
                        <X className="w-5 h-5 theme-text-muted" />
                    </button>
                </div>

                {completado ? (
                    <VistaCompletadoPagosPedidos
                        resultado={resultado}
                        onDescargar={descargarArchivo}
                        onGenerarOtro={generarOtro}
                        onCerrar={cerrarModal}
                    />
                ) : generando ? (
                    <>
                        <VistaGeneracionPagosPedidos
                            progreso={progresoPdf}
                            onSegundoPlano={continuarSegundoPlano}
                            onCancelar={cancelarGeneracion}
                            cancelando={cancelando}
                        />
                        {pdfFallo && (
                            <div className="p-6 border-t theme-border flex flex-wrap gap-3 justify-end shrink-0">
                                <button type="button" onClick={generarOtro} className="px-5 py-2.5 rounded-xl border theme-border text-sm font-semibold theme-text-main">
                                    Generar otro
                                </button>
                                <button type="button" onClick={cerrarModal} className="px-5 py-2.5 rounded-xl border theme-border text-sm font-semibold theme-text-muted hover:theme-text-main transition-colors">
                                    Cerrar
                                </button>
                            </div>
                        )}
                    </>
                ) : (
                <>
                <div className="p-6 space-y-8 overflow-y-auto flex-1">
                    {/* ── Periodo ── */}
                    <section className="space-y-4">
                        <p className={SECCION}>Periodo</p>

                        <div>
                            <span className={LABEL}>Tipo de fecha</span>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                {TIPOS_FECHA.map((t) => (
                                    <label key={t.value} className={`${CHECK} p-3 rounded-xl border theme-border ${estado.tipo_fecha === t.value ? 'border-[var(--color-primario)] bg-[color-mix(in_srgb,var(--color-primario)_8%,transparent)]' : ''}`}>
                                        <input
                                            type="radio"
                                            name="tipo_fecha"
                                            checked={estado.tipo_fecha === t.value}
                                            onChange={() => setEstado((s) => ({ ...s, tipo_fecha: t.value }))}
                                            className="accent-[var(--color-primario)]"
                                        />
                                        <span>
                                            <span className="font-semibold block">{t.label}</span>
                                            <span className="text-[11px] theme-text-muted">{t.hint}</span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label className="block">
                                <span className={LABEL}>Fecha inicial</span>
                                <input type="date" className={INPUT} value={estado.fecha_desde} onChange={(e) => setEstado((s) => ({ ...s, fecha_desde: e.target.value }))} />
                            </label>
                            <label className="block">
                                <span className={LABEL}>Fecha final</span>
                                <input type="date" className={INPUT} value={estado.fecha_hasta} onChange={(e) => setEstado((s) => ({ ...s, fecha_hasta: e.target.value }))} />
                            </label>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {PRESETS_RAPIDOS.map((p) => (
                                <button
                                    key={p.id}
                                    type="button"
                                    onClick={() => aplicarPreset(p.id)}
                                    className="px-3 py-1.5 rounded-lg border theme-border text-xs font-semibold theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-colors"
                                >
                                    {p.label}
                                </button>
                            ))}
                        </div>
                    </section>

                    {/* ── Banco y forma de pago ── */}
                    <section className="space-y-4">
                        <p className={SECCION}>Banco y forma de pago</p>

                        <div>
                            <span className={LABEL}>Banco</span>
                            <div className="max-h-36 overflow-y-auto rounded-xl border theme-border p-3 space-y-2">
                                <label className={CHECK}>
                                    <input
                                        type="checkbox"
                                        checked={estado.sin_banco}
                                        onChange={(e) => setEstado((s) => ({ ...s, sin_banco: e.target.checked }))}
                                        className="rounded accent-[var(--color-primario)]"
                                    />
                                    Sin banco (efectivo u otros)
                                </label>
                                {bancos.map((b) => (
                                    <label key={b.id} className={CHECK}>
                                        <input
                                            type="checkbox"
                                            checked={estado.banco_ids.includes(String(b.id))}
                                            onChange={() => setEstado((s) => ({
                                                ...s,
                                                banco_ids: toggleLista(s.banco_ids, String(b.id)),
                                            }))}
                                            className="rounded accent-[var(--color-primario)]"
                                        />
                                        {b.nombre}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div>
                            <span className={LABEL}>Forma de pago</span>
                            <div className="flex flex-wrap gap-3">
                                {formasPago.map((f) => (
                                    <label key={f.codigo} className={CHECK}>
                                        <input
                                            type="checkbox"
                                            checked={estado.formas_pago.includes(f.codigo)}
                                            onChange={() => setEstado((s) => ({
                                                ...s,
                                                formas_pago: toggleLista(s.formas_pago, f.codigo),
                                            }))}
                                            className="rounded accent-[var(--color-primario)]"
                                        />
                                        {f.label}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div>
                            <span className={LABEL}>Estado de exhibición</span>
                            <div className="flex flex-wrap gap-3">
                                {ESTADOS_EXHIBICION.map((e) => (
                                    <label key={e.value} className={CHECK}>
                                        <input
                                            type="checkbox"
                                            checked={estado.estados_exhibicion.includes(e.value)}
                                            onChange={() => setEstado((s) => ({
                                                ...s,
                                                estados_exhibicion: toggleLista(s.estados_exhibicion, e.value),
                                            }))}
                                            className="rounded accent-[var(--color-primario)]"
                                        />
                                        {e.label}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div>
                            <span className={LABEL}>Cobertura</span>
                            <div className="flex flex-wrap gap-3">
                                {ESTADOS_COBERTURA.map((e) => (
                                    <label key={e.value} className={CHECK}>
                                        <input
                                            type="checkbox"
                                            checked={estado.estados_cobertura.includes(e.value)}
                                            onChange={() => setEstado((s) => ({
                                                ...s,
                                                estados_cobertura: toggleLista(s.estados_cobertura, e.value),
                                            }))}
                                            className="rounded accent-[var(--color-primario)]"
                                        />
                                        {e.label}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <label className="block">
                            <span className={LABEL}>Referencia bancaria</span>
                            <input
                                type="text"
                                className={INPUT}
                                placeholder="Contiene…"
                                value={estado.referencia_bancaria}
                                onChange={(e) => setEstado((s) => ({ ...s, referencia_bancaria: e.target.value }))}
                            />
                        </label>
                    </section>

                    {/* ── Alcance ── */}
                    <section className="space-y-4">
                        <p className={SECCION}>Alcance</p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label className="block">
                                <span className={LABEL}>Departamento</span>
                                <select className={INPUT} value={estado.departamento_id} onChange={(e) => setEstado((s) => ({ ...s, departamento_id: e.target.value }))}>
                                    <option value="">Todos</option>
                                    {departamentos.map((d) => <option key={d.id} value={d.id}>{d.nombre}</option>)}
                                </select>
                            </label>
                            <label className="block">
                                <span className={LABEL}>Vendedora</span>
                                <select className={INPUT} value={estado.vendedor_id} onChange={(e) => setEstado((s) => ({ ...s, vendedor_id: e.target.value }))}>
                                    <option value="">Todas</option>
                                    {vendedores.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                                </select>
                            </label>
                            <label className="block">
                                <span className={LABEL}>Almacén / sucursal</span>
                                <select className={INPUT} value={estado.almacen_id} onChange={(e) => setEstado((s) => ({ ...s, almacen_id: e.target.value }))}>
                                    <option value="">Todos</option>
                                    {almacenes.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                                </select>
                            </label>
                            <label className="block">
                                <span className={LABEL}>Origen / modalidad</span>
                                <select className={INPUT} value={estado.origen_pedido} onChange={(e) => setEstado((s) => ({ ...s, origen_pedido: e.target.value }))}>
                                    <option value="">Todos</option>
                                    {origenesPedido.map((o) => <option key={o} value={o}>{o}</option>)}
                                </select>
                            </label>
                            <label className="block sm:col-span-2">
                                <span className={LABEL}>Cliente o número de cliente</span>
                                <input
                                    type="text"
                                    className={INPUT}
                                    placeholder="Nombre o número…"
                                    value={estado.cliente_busqueda}
                                    onChange={(e) => setEstado((s) => ({ ...s, cliente_busqueda: e.target.value }))}
                                />
                            </label>
                            <label className="block">
                                <span className={LABEL}>Remisión</span>
                                <select className={INPUT} value={estado.con_remision} onChange={(e) => setEstado((s) => ({ ...s, con_remision: e.target.value }))}>
                                    <option value="">Todas</option>
                                    <option value="1">Con remisión</option>
                                    <option value="0">Sin remisión</option>
                                </select>
                            </label>
                            <label className="block">
                                <span className={LABEL}>Voucher</span>
                                <select className={INPUT} value={estado.con_evidencia} onChange={(e) => setEstado((s) => ({ ...s, con_evidencia: e.target.value }))}>
                                    <option value="">Todos</option>
                                    <option value="1">Con voucher</option>
                                    <option value="0">Sin voucher</option>
                                </select>
                            </label>
                            <label className="block sm:col-span-2">
                                <span className={LABEL}>Cierre</span>
                                <select className={INPUT} value={estado.estado_cierre} onChange={(e) => setEstado((s) => ({ ...s, estado_cierre: e.target.value }))}>
                                    <option value="vigente">Cierre vigente</option>
                                    <option value="revocado">Histórico revocado</option>
                                    <option value="reconstruido">Reconstruido (backfill)</option>
                                    <option value="todos">Todos</option>
                                </select>
                            </label>
                        </div>
                    </section>

                    {/* ── Contenido y formato ── */}
                    <section className="space-y-4">
                        <p className={SECCION}>Contenido y formato</p>

                        <div>
                            <span className={LABEL}>Formato</span>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                {formatosDisponibles.map((f) => (
                                    <label
                                        key={f.value}
                                        className={`${CHECK} p-3 rounded-xl border theme-border justify-center ${formatoActual === f.value ? 'border-[var(--color-primario)] bg-[color-mix(in_srgb,var(--color-primario)_8%,transparent)]' : ''}`}
                                    >
                                        <input
                                            type="radio"
                                            name="formato"
                                            checked={formatoActual === f.value}
                                            onChange={() => setEstado((s) => ({ ...s, formato: f.value }))}
                                            className="accent-[var(--color-primario)]"
                                        />
                                        {f.value.startsWith('csv') ? <FileSpreadsheet className="w-4 h-4" /> : <FileText className="w-4 h-4" />}
                                        {f.label}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            {[
                                ['incluir_vouchers', 'Incluir vouchers'],
                                ['incluir_evidencias_rechazadas_sustituidas', 'Incluir evidencias rechazadas o sustituidas'],
                                ['incluir_referencias_remision', 'Incluir referencias de remisión'],
                                ['incluir_observaciones_historial', 'Incluir observaciones e historial'],
                            ].map(([key, label]) => (
                                <label key={key} className={CHECK}>
                                    <input
                                        type="checkbox"
                                        checked={estado[key]}
                                        onChange={(e) => setEstado((s) => ({ ...s, [key]: e.target.checked }))}
                                        className="rounded accent-[var(--color-primario)]"
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label className="block">
                                <span className={LABEL}>Orden</span>
                                <select className={INPUT} value={estado.orden} onChange={(e) => setEstado((s) => ({ ...s, orden: e.target.value }))}>
                                    <option value="desc">Descendente</option>
                                    <option value="asc">Ascendente</option>
                                </select>
                            </label>
                            <label className="block">
                                <span className={LABEL}>Agrupar por</span>
                                <select className={INPUT} value={estado.agrupar_por} onChange={(e) => setEstado((s) => ({ ...s, agrupar_por: e.target.value }))}>
                                    {AGRUPACIONES.map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
                                </select>
                            </label>
                        </div>
                    </section>
                </div>

                <div className="p-6 border-t theme-border shrink-0 space-y-4">
                    <div
                        className="rounded-xl border theme-border px-4 py-3 text-sm theme-text-main flex items-center gap-2"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 6%, transparent)' }}
                        aria-live="polite"
                    >
                        {estimacionCargando && <Loader2 className="w-4 h-4 shrink-0 animate-spin" style={{ color: 'var(--color-primario)' }} />}
                        <span className={estimacionCargando ? 'theme-text-muted' : ''}>{textoEstimacion}</span>
                    </div>

                    <div className="flex flex-wrap gap-3 justify-end">
                        <button type="button" onClick={cerrarModal} className="px-5 py-2.5 rounded-xl border theme-border text-sm font-semibold theme-text-muted hover:theme-text-main transition-colors">
                            Cancelar
                        </button>
                        <button
                            type="button"
                            onClick={generar}
                            disabled={formatosDisponibles.length === 0}
                            className="px-6 py-2.5 rounded-xl text-sm font-bold text-white transition-colors disabled:opacity-50"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            Generar reporte
                        </button>
                    </div>
                </div>
                </>
                )}
            </div>
        </div>,
        document.body
    );
}

export { payloadExport, queryStringExport };
