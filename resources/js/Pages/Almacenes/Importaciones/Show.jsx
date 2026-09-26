import React, { useState, useMemo, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { startImportacionAlmacenTracking } from '@/utils/importacionAlmacenTracker';
import AppLayout from '@/Layouts/AppLayout';
import ResumenSimulacionImportacion from '@/Components/Almacenes/ResumenSimulacionImportacion';
import { geliaCardClass, THEME_BTN_PRIMARY } from '@/utils/geliaTheme';
import { MAPPING_HUB_DEFAULT, MAPPING_HUB_FICHA_REQUIRED, MAPPING_HUB_LABELS, autoMapearHub } from '@/config/importacionHubAlmacenes';

function mensajeErrorImportacion(err, fallback) {
    const data = err?.response?.data;
    if (data?.errors) {
        const lineas = Object.values(data.errors).flat().filter(Boolean);
        if (lineas.length) return lineas.join(' ');
    }
    return data?.message || fallback;
}

export default function Show({ auth, lote, operacionesLabels }) {
    const headers = lote.headers || [];
    const [step, setStep] = useState(2);
    const [mapping, setMapping] = useState(MAPPING_HUB_DEFAULT);
    const [resumen, setResumen] = useState(lote.resumen_simulacion);
    const [mappingSimulado, setMappingSimulado] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        const base = { ...MAPPING_HUB_DEFAULT, ...(lote.mapping || {}) };
        setMapping(headers.length ? autoMapearHub(headers, base) : base);
        if (lote.estado === 'simulado' && lote.resumen_simulacion) {
            setStep(3);
            setMappingSimulado(JSON.stringify(base));
        }
    }, [lote.id]);

    const camposVisibles = useMemo(() => {
        const ops = lote.operaciones || [];
        const keys = new Set(['sku']);
        if (ops.includes('ficha_producto')) {
            ['folio', 'descripcion', 'marca', 'categoria', 'codigo_barras', 'peso', 'activo'].forEach((k) => keys.add(k));
        }
        if (ops.includes('asignacion_almacen')) keys.add('ubicacion');
        if (ops.includes('cantidades_referencia')) keys.add('existencia');
        if (ops.includes('costos_precios')) ['costo', 'costo_reposicion', 'precio_venta'].forEach((k) => keys.add(k));
        return [...keys];
    }, [lote.operaciones]);

    const requiereFichaProducto = (lote.operaciones || []).includes('ficha_producto');

    const validarMappingObligatorio = () => {
        const keys = requiereFichaProducto ? MAPPING_HUB_FICHA_REQUIRED : ['sku'];
        const faltantes = keys.filter((k) => !mapping[k]);
        if (faltantes.length) {
            setError(`Mapea las columnas obligatorias: ${faltantes.map((k) => MAPPING_HUB_LABELS[k] || k).join(', ')}.`);
            return false;
        }
        return true;
    };

    const simular = async () => {
        if (!validarMappingObligatorio()) {
            return;
        }
        setError('');
        setLoading(true);
        try {
            const { data } = await axios.post(route('almacenes.importaciones.simular', lote.id), { mapping });
            setResumen(data.resumen);
            setMappingSimulado(JSON.stringify(mapping));
            setStep(3);
        } catch (err) {
            setError(mensajeErrorImportacion(err, 'Error en simulación.'));
        } finally {
            setLoading(false);
        }
    };

    const aplicar = async () => {
        if (!validarMappingObligatorio()) {
            return;
        }
        if (mappingSimulado === null || JSON.stringify(mapping) !== mappingSimulado) {
            setError('El mapeo cambió después de la simulación. Vuelve a ejecutar «Simular cambios».');
            return;
        }
        if (lote.estado !== 'simulado') {
            setError('Debes simular los cambios antes de aplicar.');
            return;
        }
        setLoading(true);
        setError('');
        try {
            const { data } = await axios.post(route('almacenes.importaciones.aplicar', lote.id), { mapping });
            if (data.log_id) {
                startImportacionAlmacenTracking(data.log_id);
                setStep(4);
            }
        } catch (err) {
            setError(mensajeErrorImportacion(err, 'No se pudo aplicar.'));
        } finally {
            setLoading(false);
        }
    };

    const etiquetasOperaciones = (lote.operaciones || [])
        .map((o) => operacionesLabels[o] || o)
        .join(' · ');

    return (
        <AppLayout auth={auth}>
            <Head title={`Importación #${lote.id}`} />
            <div className="max-w-4xl mx-auto p-4 md:p-8 space-y-6">
                <div className={geliaCardClass('p-6 space-y-2')}>
                    <Link href={route('almacenes.importaciones.index')} className="text-[10px] font-black uppercase theme-text-muted">← Importaciones</Link>
                    <h1 className="text-xl font-black uppercase theme-text-main">Lote #{lote.id}</h1>
                    <p className="text-[11px] font-bold theme-text-muted">{etiquetasOperaciones}</p>
                    {lote.almacen && (
                        <p className="text-[10px] font-bold theme-text-muted">
                            Almacén: {lote.almacen.codigo} — {lote.almacen.nombre}
                            {lote.almacen.sucursal ? ` · Sucursal: ${lote.almacen.sucursal.nombre}` : ''}
                        </p>
                    )}
                    <p className="text-[10px] font-bold theme-text-muted">Estado: {lote.estado}</p>
                </div>

                {step === 2 && (
                    <div className={geliaCardClass('p-6 space-y-4')}>
                        <p className="text-[10px] font-black uppercase theme-text-muted">Paso 2 — Columnas</p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {camposVisibles.map((key) => (
                                <div key={key}>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">{MAPPING_HUB_LABELS[key]}</label>
                                    <select
                                        value={mapping[key] || ''}
                                        onChange={(e) => {
                                            setMapping({ ...mapping, [key]: e.target.value });
                                            if (step >= 3) setMappingSimulado(null);
                                        }}
                                        className="theme-input w-full mt-1 px-3 py-2 text-[11px] font-bold"
                                    >
                                        <option value="">— No mapear —</option>
                                        {headers.map((h, i) => <option key={i} value={h}>{h}</option>)}
                                    </select>
                                </div>
                            ))}
                        </div>
                        {error && <p className="text-red-500 text-[11px] font-bold">{error}</p>}
                        <button type="button" disabled={loading} onClick={simular} className={`${THEME_BTN_PRIMARY} w-full py-3`}>
                            {loading ? 'Simulando…' : 'Paso 3: Simular cambios'}
                        </button>
                    </div>
                )}

                {step >= 3 && resumen && (
                    <div className={geliaCardClass('p-6 space-y-3')}>
                        <p className="text-[10px] font-black uppercase theme-text-muted">Paso 3 — Simulación</p>
                        <ResumenSimulacionImportacion resumen={resumen} />
                        {resumen.detalle_errores?.length > 0 && (
                            <ul className="text-[10px] font-bold text-red-500 max-h-40 overflow-auto">
                                {resumen.detalle_errores.map((e, i) => (
                                    <li key={i}>Fila {e.fila} ({e.sku}): {e.mensaje}</li>
                                ))}
                            </ul>
                        )}
                        {error && <p className="text-red-500 text-[11px] font-bold">{error}</p>}
                        {step === 3 && (
                            <button type="button" disabled={loading} onClick={aplicar} className={`${THEME_BTN_PRIMARY} w-full py-3`}>
                                {loading ? 'Aplicando…' : 'Paso 4: Aplicar en segundo plano'}
                            </button>
                        )}
                    </div>
                )}

                {step === 4 && (
                    <div className={geliaCardClass('p-6')}>
                        <p className="text-[11px] font-bold theme-text-main">La importación se está procesando. Puedes seguir el progreso en el indicador flotante.</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
