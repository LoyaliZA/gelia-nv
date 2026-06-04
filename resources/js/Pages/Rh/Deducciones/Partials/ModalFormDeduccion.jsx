import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, User, FileText, DollarSign, X, Save, Calculator, Package } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import FirmaCanvas from '../../../../Components/Rh/FirmaCanvas';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_INPUT,
    THEME_SELECT,
    THEME_TEXTAREA,
    THEME_LABEL,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_BTN_ICON,
} from '../../../../utils/geliaTheme';
import {
    calcularDeduccionPreview, formatoDeduccionEntera, formatoMoneda, nombreCompletoColaborador,
} from '../../../../utils/formatoMoneda';
import { ESTADO_DEDUCCION_LABELS, ORIGEN_DEDUCCION_LABELS } from './deduccionesStyles';

const FORM_INICIAL = {
    fecha_ocurrencia: new Date().toISOString().slice(0, 10),
    rh_colaborador_id: '',
    catalogo_regla_incidencia_id: '',
    producto_id: '',
    producto_sku: '',
    descripcion_detallada: '',
    origen_deduccion: 'nomina',
    fecha_deduccion_nomina: '',
};

const REQUIERE_PRODUCTO = ['cobro_costo_producto', 'cobro_precio_venta_producto'];

export default function ModalFormDeduccion({
    abierto,
    onCerrar,
    registro = null,
    colaboradores = [],
    reglasIncidencia = [],
    usuarioActual = null,
    puedeEditar = true,
}) {
    const { data, setData, post, put, processing, errors, reset, clearErrors, transform } = useForm({ ...FORM_INICIAL });

    transform((formData) => ({
        ...formData,
        factor_multiplicador: 1, // Siempre 1 tras esta actualización
        firma_gerente_data: firmaGerenteRef.current?.getDataUrl() || undefined,
        firma_colaborador_data: firmaColaboradorRef.current?.getDataUrl() || undefined,
    }));
    const [reglasFiltradas, setReglasFiltradas] = useState(reglasIncidencia);
    const [productosSku, setProductosSku] = useState([]);
    const [productoSel, setProductoSel] = useState(null);
    const firmaGerenteRef = useRef(null);
    const firmaColaboradorRef = useRef(null);

    const colaboradorSel = useMemo(
        () => colaboradores.find((c) => String(c.id) === String(data.rh_colaborador_id)),
        [colaboradores, data.rh_colaborador_id],
    );

    const reglaSel = useMemo(
        () => reglasFiltradas.find((r) => String(r.id) === String(data.catalogo_regla_incidencia_id)),
        [reglasFiltradas, data.catalogo_regla_incidencia_id],
    );

    const preview = useMemo(
        () => calcularDeduccionPreview(data, colaboradorSel, reglaSel, productoSel),
        [data, colaboradorSel, reglaSel, productoSel],
    );

    useEffect(() => {
        if (!abierto || !data.rh_colaborador_id) {
            setReglasFiltradas(reglasIncidencia);
            return;
        }

        const cargar = async () => {
            try {
                const params = new URLSearchParams({
                    rh_colaborador_id: data.rh_colaborador_id,
                    regla_id_actual: registro?.catalogo_regla_incidencia_id || '',
                });
                const resp = await fetch(`${route('rh.deducciones.reglas_disponibles')}?${params}`);
                if (resp.ok) {
                    const json = await resp.json();
                    setReglasFiltradas(json.reglas || []);
                }
            } catch {
                setReglasFiltradas(reglasIncidencia);
            }
        };

        cargar();
    }, [abierto, data.rh_colaborador_id, registro?.catalogo_regla_incidencia_id, reglasIncidencia]);

    useEffect(() => {
        if (!abierto) return;

        if (registro) {
            setData({
                fecha_ocurrencia: registro.fecha_ocurrencia?.slice?.(0, 10) || registro.fecha_ocurrencia || '',
                rh_colaborador_id: registro.rh_colaborador_id || '',
                catalogo_regla_incidencia_id: registro.catalogo_regla_incidencia_id || '',
                producto_id: registro.producto_id || '',
                producto_sku: registro.producto_sku_snapshot || '',
                descripcion_detallada: registro.descripcion_detallada || registro.observaciones || '',
                origen_deduccion: registro.origen_deduccion || 'nomina',
                fecha_deduccion_nomina: registro.fecha_deduccion_nomina?.slice?.(0, 10) || registro.fecha_deduccion_nomina || '',
            });
            if (registro.producto) setProductoSel(registro.producto);
        } else {
            reset();
            clearErrors();
            setProductoSel(null);
        }
    }, [abierto, registro]);

    useEffect(() => {
        if (!data.producto_sku || data.producto_sku.length < 2) {
            setProductosSku([]);
            return;
        }

        const timer = setTimeout(async () => {
            try {
                const resp = await fetch(`${route('rh.deducciones.buscar_sku')}?q=${encodeURIComponent(data.producto_sku)}`);
                if (resp.ok) {
                    const json = await resp.json();
                    setProductosSku(json.productos || []);
                }
            } catch {
                setProductosSku([]);
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [data.producto_sku]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!puedeEditar && registro) return;

        const accion = registro ? put : post;
        const ruta = registro
            ? route('rh.deducciones.update', registro.id)
            : route('rh.deducciones.store');

        accion(ruta, {
            preserveScroll: true,
            onSuccess: () => {
                onCerrar();
                reset();
            },
        });
    };

    const seleccionarProducto = (p) => {
        setProductoSel(p);
        setData('producto_id', p.id);
        setData('producto_sku', p.sku);
        setProductosSku([]);
    };

    if (!abierto) return null;

    const muestraProducto = reglaSel && REQUIERE_PRODUCTO.includes(reglaSel.tipo_comportamiento);

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6 overflow-y-auto`} onClick={onCerrar}>
            <GeliaLoader isVisible={processing} message="Guardando deducción_" />
            <div className={`${THEME_MODAL_SHELL} max-w-4xl modal-pop text-left`} onClick={(e) => e.stopPropagation()}>
                <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center">
                    <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-3 m-0">
                        <AlertTriangle className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        {registro ? 'Editar Deducción' : 'Nueva Deducción'}
                    </h2>
                    <button type="button" onClick={onCerrar} className={THEME_BTN_ICON}><X className="w-6 h-6" /></button>
                </div>

                <form onSubmit={handleSubmit} className="gelia-modal-body p-6 md:p-8 custom-scrollbar space-y-8">
                    {registro && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 rounded-2xl theme-element border theme-border">
                            <div>
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">ID Reporte / Folio</p>
                                <p className="text-sm font-mono font-bold m-0">{registro.folio}</p>
                            </div>
                            <div>
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">UUID</p>
                                <p className="text-xs font-mono theme-text-muted m-0 break-all">{registro.uuid}</p>
                            </div>
                        </div>
                    )}

                    <section>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                            <User className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Colaborador y fecha
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className={THEME_LABEL}>Colaborador sancionado *</label>
                                <select value={data.rh_colaborador_id} onChange={(e) => setData({ ...data, rh_colaborador_id: e.target.value, catalogo_regla_incidencia_id: '' })} className={THEME_SELECT} required disabled={!!registro && registro.estado_deduccion === 'aplicado'}>
                                    <option value="">Seleccionar...</option>
                                    {colaboradores.map((c) => (
                                        <option key={c.id} value={c.id}>{nombreCompletoColaborador(c)} — {c.departamento?.nombre || 'Sin depto'}</option>
                                    ))}
                                </select>
                                {errors.rh_colaborador_id && <p className="text-red-500 text-[10px] font-bold ml-2">{errors.rh_colaborador_id}</p>}
                            </div>
                            <div>
                                <label className={THEME_LABEL}>Fecha del evento *</label>
                                <input type="date" value={data.fecha_ocurrencia} onChange={(e) => setData('fecha_ocurrencia', e.target.value)} className={THEME_INPUT} required />
                            </div>
                            {colaboradorSel && (
                                <>
                                    <div className="p-3 rounded-2xl theme-element border theme-border">
                                        <p className="text-[9px] font-black uppercase theme-text-muted m-0">Departamento</p>
                                        <p className="text-sm font-bold m-0 theme-text-main">{colaboradorSel.departamento?.nombre || '—'}</p>
                                    </div>
                                    <div className="p-3 rounded-2xl theme-element border theme-border">
                                        <p className="text-[9px] font-black uppercase theme-text-muted m-0">Área</p>
                                        <p className="text-sm font-bold m-0 theme-text-main">{colaboradorSel.area?.nombre || '—'}</p>
                                    </div>
                                </>
                            )}
                        </div>
                    </section>

                    <section>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                            <FileText className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Concepto y cálculo
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="md:col-span-2">
                                <label className={THEME_LABEL}>Concepto seleccionado *</label>
                                <select value={data.catalogo_regla_incidencia_id} onChange={(e) => setData({ ...data, catalogo_regla_incidencia_id: e.target.value, producto_id: '', producto_sku: '' })} className={THEME_SELECT} required disabled={!data.rh_colaborador_id}>
                                    <option value="">{data.rh_colaborador_id ? 'Seleccionar concepto...' : 'Primero seleccione colaborador'}</option>
                                    {reglasFiltradas.map((r) => (
                                        <option key={r.id} value={r.id}>[{r.categoria}] {r.nombre}</option>
                                    ))}
                                </select>
                                {errors.catalogo_regla_incidencia_id && <p className="text-red-500 text-[10px] font-bold ml-2">{errors.catalogo_regla_incidencia_id}</p>}
                            </div>

                            {muestraProducto && (
                                <div className="md:col-span-2 relative">
                                    <label className={`${THEME_LABEL} flex items-center gap-1`}>
                                        <Package className="w-3 h-3" /> Código producto / SKU *
                                    </label>
                                    <input type="text" value={data.producto_sku} onChange={(e) => setData({ ...data, producto_sku: e.target.value, producto_id: '' })} className={THEME_INPUT} placeholder="Buscar o escanear SKU..." />
                                    {productosSku.length > 0 && (
                                        <div className="absolute z-10 w-full mt-1 theme-surface border theme-border rounded-2xl shadow-xl max-h-40 overflow-y-auto">
                                            {productosSku.map((p) => (
                                                <button key={p.id} type="button" onClick={() => seleccionarProducto(p)} className="w-full text-left px-4 py-2 text-xs hover:bg-black/5 dark:hover:bg-white/5">
                                                    <span className="font-mono font-bold">{p.sku}</span> — {p.descripcion} ({formatoMoneda(p.costo)} / {formatoMoneda(p.precio_venta)})
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                    {errors.producto_id && <p className="text-red-500 text-[10px] font-bold ml-2">{errors.producto_id}</p>}
                                </div>
                            )}

                            <div className="md:col-span-2">
                                <label className={THEME_LABEL}>Origen de la deducción *</label>
                                <select value={data.origen_deduccion} onChange={(e) => setData('origen_deduccion', e.target.value)} className={THEME_SELECT}>
                                    {Object.entries(ORIGEN_DEDUCCION_LABELS).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {reglaSel && preview && (
                            <div className="mt-4 p-4 rounded-2xl border border-red-500/20 bg-red-500/5 space-y-2">
                                <h4 className="text-[10px] font-black uppercase text-red-500 tracking-widest mb-3">Desglose de la Deducción</h4>
                                
                                {preview.deduccion_salario_base > 0 && (
                                    <div className="flex justify-between items-center text-xs">
                                        <span className="theme-text-main font-bold">Salario Base</span>
                                        <span className="font-mono font-bold text-red-500">-{formatoMoneda(preview.deduccion_salario_base)}</span>
                                    </div>
                                )}
                                {preview.deduccion_bono_puntualidad > 0 && (
                                    <div className="flex justify-between items-center text-xs">
                                        <span className="theme-text-main font-bold">Bono Puntualidad</span>
                                        <span className="font-mono font-bold text-red-500">-{formatoMoneda(preview.deduccion_bono_puntualidad)}</span>
                                    </div>
                                )}
                                {preview.deduccion_bono_productividad > 0 && (
                                    <div className="flex justify-between items-center text-xs">
                                        <span className="theme-text-main font-bold">Bono Productividad</span>
                                        <span className="font-mono font-bold text-red-500">-{formatoMoneda(preview.deduccion_bono_productividad)}</span>
                                    </div>
                                )}
                                {reglaSel.tipo_comportamiento !== 'deduccion_nomina' && reglaSel.tipo_comportamiento !== 'cancelacion_bono_especifico' && preview.monto_deduccion_base > 0 && (
                                    <div className="flex justify-between items-center text-xs">
                                        <span className="theme-text-main font-bold">Monto Fijo / Producto</span>
                                        <span className="font-mono font-bold text-red-500">-{formatoMoneda(preview.monto_deduccion_base)}</span>
                                    </div>
                                )}
                                
                                <div className="flex justify-between items-center pt-3 mt-3 border-t border-red-500/20">
                                    <span className="text-sm font-black uppercase theme-text-main">Total a descontar</span>
                                    <span className="text-lg font-black font-mono text-red-500">-{formatoMoneda(preview.monto_total_final)}</span>
                                </div>
                                <div className="text-right">
                                    <span className="inline-block px-2 py-1 mt-1 rounded text-[9px] font-black uppercase bg-black/10 dark:bg-white/10 theme-text-muted">
                                        Estado: {ESTADO_DEDUCCION_LABELS[preview.estado_deduccion] || preview.estado_deduccion}
                                    </span>
                                </div>
                            </div>
                        )}
                    </section>

                    <section>
                        <label className={THEME_LABEL}>Descripción detallada de la incidencia</label>
                        <textarea value={data.descripcion_detallada} onChange={(e) => setData('descripcion_detallada', e.target.value)} rows={4} className={THEME_TEXTAREA} placeholder="Relato descriptivo para el expediente..." />
                    </section>

                    <section className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="p-4 rounded-2xl theme-element border theme-border">
                            <p className="text-[9px] font-black uppercase theme-text-muted m-0">Reportado por / Auditor</p>
                            <p className="text-sm font-bold m-0 mt-1 theme-text-main">{usuarioActual?.name || registro?.registrado_por?.name || '—'}</p>
                        </div>
                        <div>
                            <label className={THEME_LABEL}>Fecha deducción nómina (opcional)</label>
                            <input type="date" value={data.fecha_deduccion_nomina} onChange={(e) => setData('fecha_deduccion_nomina', e.target.value)} className={THEME_INPUT} />
                        </div>
                    </section>

                    {!registro && (
                        <section className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <FirmaCanvas ref={firmaGerenteRef} label="Firma gerente autorizado" />
                            <FirmaCanvas ref={firmaColaboradorRef} label="Firma colaborador" />
                        </section>
                    )}

                    <div className="flex justify-end gap-3 pt-4 border-t theme-border">
                        <button type="button" onClick={onCerrar} className={THEME_BTN_SECONDARY}>Cancelar</button>
                        <button type="submit" disabled={processing} className={THEME_BTN_PRIMARY}>
                            <Save className="w-4 h-4" /> {registro ? 'Actualizar' : 'Registrar deducción'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body,
    );
}

function CalcItem({ label, value, highlight = false }) {
    return (
        <div>
            <p className="text-[9px] font-black uppercase theme-text-muted m-0 flex items-center gap-1">
                <Calculator className="w-3 h-3" /> {label}
            </p>
            <p className={`m-0 mt-1 ${highlight ? 'text-lg font-black' : 'text-sm font-bold'} theme-text-main`}>{value}</p>
        </div>
    );
}
