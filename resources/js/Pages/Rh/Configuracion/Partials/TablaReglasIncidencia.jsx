import React, { useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { Scale, Edit2, Trash2, Plus, X, Save, AlertTriangle, Info } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_INPUT,
    THEME_SELECT,
    THEME_LABEL,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_BTN_ICON,
} from '../../../../utils/geliaTheme';
import { formatoMoneda } from '../../../../utils/formatoMoneda';

const COMPORTAMIENTOS = {
    cobro_fijo: 'Cobro Fijo',
    cobro_costo_producto: 'Cobro por Costo de Producto',
    cobro_precio_venta_producto: 'Cobro por Precio de Venta',
    cancelacion_bono_especifico: 'Cancelación de Bono Específico',
    deduccion_nomina: 'Deducción Nómina (Faltas/Retardos)',
};

const CATEGORIAS = {
    falta: 'Falta',
    retardo: 'Retardo',
    operativa: 'Operativa',
};

const FORM_INICIAL = {
    nombre: '',
    categoria: 'operativa',
    tipo_comportamiento: 'cobro_fijo',
    monto_fijo: '',
    catalogo_bono_id: '',
    factor_penalizacion_puntualidad: '0',
    factor_penalizacion_productividad: '0',
    aplica_deduccion_salario_base: false,
    recompensa_auditor_activa: false,
    monto_recompensa_auditor: '',
    activo: true,
    departamentos_aplicables: [],
    areas_aplicables: [],
    departamentos_visibilidad: [],
    areas_visibilidad: [],
};

function MultiSelectDeptArea({ label, help, departamentos, selectedDepts, selectedAreas, onChangeDepts, onChangeAreas }) {
    const areasFiltradas = useMemo(() => {
        if (selectedDepts.length === 0) return departamentos.flatMap((d) => d.areas || []);
        return departamentos.filter((d) => selectedDepts.includes(String(d.id))).flatMap((d) => d.areas || []);
    }, [departamentos, selectedDepts]);

    const toggle = (list, id, setter) => {
        const sid = String(id);
        setter(list.includes(sid) ? list.filter((x) => x !== sid) : [...list, sid]);
    };

    return (
        <div className="space-y-2 p-4 rounded-2xl border theme-border bg-black/[0.02] dark:bg-white/[0.02]">
            <p className="text-[10px] font-black uppercase theme-text-main m-0">{label}</p>
            {help && <p className="text-[9px] theme-text-muted m-0 mb-2">{help}</p>}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <p className="text-[9px] font-black uppercase theme-text-muted mb-1">Departamentos</p>
                    <div className="max-h-28 overflow-y-auto space-y-1">
                        {departamentos.map((d) => (
                            <label key={d.id} className="flex items-center gap-2 text-xs cursor-pointer theme-text-main">
                                <input type="checkbox" checked={selectedDepts.includes(String(d.id))} onChange={() => toggle(selectedDepts, d.id, onChangeDepts)} />
                                {d.nombre}
                            </label>
                        ))}
                    </div>
                </div>
                <div>
                    <p className="text-[9px] font-black uppercase theme-text-muted mb-1">Áreas</p>
                    <div className="max-h-28 overflow-y-auto space-y-1">
                        {areasFiltradas.map((a) => (
                            <label key={a.id} className="flex items-center gap-2 text-xs cursor-pointer theme-text-main">
                                <input type="checkbox" checked={selectedAreas.includes(String(a.id))} onChange={() => toggle(selectedAreas, a.id, onChangeAreas)} />
                                {a.nombre}
                            </label>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function TablaReglasIncidencia({ datos = [], bonos = [], departamentos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, put, processing, reset, errors, transform } = useForm({ ...FORM_INICIAL });

    transform((formData) => ({
        ...formData,
        departamentos_aplicables: (formData.departamentos_aplicables || []).map(Number),
        areas_aplicables: (formData.areas_aplicables || []).map(Number),
        departamentos_visibilidad: (formData.departamentos_visibilidad || []).map(Number),
        areas_visibilidad: (formData.areas_visibilidad || []).map(Number),
        catalogo_bono_id: formData.catalogo_bono_id || null,
        monto_fijo: formData.tipo_comportamiento === 'cobro_fijo' ? formData.monto_fijo : null,
    }));

    const abrirNuevo = () => {
        setItemActual(null);
        reset();
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        setData({
            nombre: item.nombre,
            categoria: item.categoria || 'operativa',
            tipo_comportamiento: item.tipo_comportamiento,
            monto_fijo: item.monto_fijo != null ? String(item.monto_fijo) : '',
            catalogo_bono_id: item.catalogo_bono_id ? String(item.catalogo_bono_id) : '',
            factor_penalizacion_puntualidad: String(item.factor_penalizacion_puntualidad ?? 0),
            factor_penalizacion_productividad: String(item.factor_penalizacion_productividad ?? 0),
            aplica_deduccion_salario_base: !!item.aplica_deduccion_salario_base,
            recompensa_auditor_activa: !!item.recompensa_auditor_activa,
            monto_recompensa_auditor: item.monto_recompensa_auditor != null ? String(item.monto_recompensa_auditor) : '',
            activo: !!item.activo,
            departamentos_aplicables: (item.departamentos_aplicables || []).map((d) => String(d.id)),
            areas_aplicables: (item.areas_aplicables || []).map((a) => String(a.id)),
            departamentos_visibilidad: (item.departamentos_visibilidad || []).map((d) => String(d.id)),
            areas_visibilidad: (item.areas_visibilidad || []).map((a) => String(a.id)),
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('rh.catalogos.reglas_incidencia.update', itemActual.id)
            : route('rh.catalogos.reglas_incidencia.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        post(route('rh.catalogos.reglas_incidencia.destroy', itemActual.id), {
            _method: 'delete',
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    const detalleRegla = (item) => {
        if (item.tipo_comportamiento === 'cobro_fijo') return formatoMoneda(item.monto_fijo);
        if (item.tipo_comportamiento === 'cancelacion_bono_especifico') return item.bono?.nombre || '—';
        return 'Según producto';
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando regla_" />

            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <Scale className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Catálogo de Deducciones</h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">{datos.length} conceptos (faltas, retardos, operativas)</p>
                    </div>
                </div>
                <button type="button" onClick={abrirNuevo} className={THEME_BTN_PRIMARY}>
                    <Plus className="w-4 h-4" /> Nueva regla
                </button>
            </div>

            <div className="p-4 mx-6 mt-4 rounded-2xl border theme-border flex gap-2 items-start">
                <Info className="w-4 h-4 shrink-0 mt-0.5" style={{ color: 'var(--color-primario)' }} />
                <p className="text-[10px] theme-text-muted m-0">Departamentos/áreas vacíos en aplicabilidad = universal. Visibilidad filtra qué auditoras ven el concepto según su perfil.</p>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase">Folio</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase">Categoría</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase">Concepto</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase">Comportamiento</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase">Valor / Bono</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase">Alcance</th>
                            <th className="px-4 py-4 text-right text-[9px] font-black theme-text-muted uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="border-b theme-border last:border-0">
                                <td className="px-4 py-4 text-xs font-mono font-bold">{item.folio}</td>
                                <td className="px-4 py-4 text-[10px] font-black uppercase">{CATEGORIAS[item.categoria] || item.categoria}</td>
                                <td className="px-4 py-4 text-sm font-black">{item.nombre}</td>
                                <td className="px-4 py-4">
                                    <span className="inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase bg-[var(--color-primario)]/10" style={{ color: 'var(--color-primario)' }}>
                                        {COMPORTAMIENTOS[item.tipo_comportamiento] || item.tipo_comportamiento}
                                    </span>
                                </td>
                                <td className="px-4 py-4 text-sm">{detalleRegla(item)}</td>
                                <td className="px-4 py-4 text-[10px] theme-text-muted">
                                    {(item.departamentos_aplicables?.length || 0) + (item.areas_aplicables?.length || 0) > 0
                                        ? `${item.departamentos_aplicables?.length || 0} dept / ${item.areas_aplicables?.length || 0} áreas`
                                        : 'Universal'}
                                </td>
                                <td className="px-4 py-4 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button type="button" onClick={() => abrirEditar(item)} className="p-2.5 theme-element border theme-border rounded-xl"><Edit2 className="w-4 h-4" /></button>
                                        <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2.5 theme-element border theme-border rounded-xl"><Trash2 className="w-4 h-4" /></button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {modalAbierto && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center p-4 overflow-y-auto`} onClick={() => setModalAbierto(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-2xl w-full modal-pop my-4`} onClick={(e) => e.stopPropagation()}>
                        <div className="p-6 border-b theme-border flex justify-between items-center">
                            <h2 className="text-lg font-black uppercase italic theme-text-main m-0">{itemActual ? 'Editar regla' : 'Nueva regla'}</h2>
                            <button type="button" onClick={() => setModalAbierto(false)} className={THEME_BTN_ICON}><X className="w-5 h-5" /></button>
                        </div>
                        <form onSubmit={handleSubmit} className="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                            <div>
                                <label className={THEME_LABEL}>Concepto / Nombre *</label>
                                <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} required className={THEME_INPUT} />
                                {errors.nombre && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.nombre}</p>}
                            </div>
                            <div>
                                <label className={THEME_LABEL}>Categoría *</label>
                                <select value={data.categoria} onChange={(e) => setData('categoria', e.target.value)} className={THEME_SELECT}>
                                    {Object.entries(CATEGORIAS).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className={THEME_LABEL}>Tipo de comportamiento *</label>
                                <select value={data.tipo_comportamiento} onChange={(e) => setData('tipo_comportamiento', e.target.value)} className={THEME_SELECT}>
                                    {Object.entries(COMPORTAMIENTOS).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>
                            {data.tipo_comportamiento === 'cobro_fijo' && (
                                <div>
                                    <label className={THEME_LABEL}>Monto fijo *</label>
                                    <input type="number" min="0" step="0.01" value={data.monto_fijo} onChange={(e) => setData('monto_fijo', e.target.value)} required className={THEME_INPUT} />
                                    {errors.monto_fijo && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.monto_fijo}</p>}
                                </div>
                            )}
                            {data.tipo_comportamiento === 'cancelacion_bono_especifico' && (
                                <div>
                                    <label className={THEME_LABEL}>Bono a cancelar *</label>
                                    <select value={data.catalogo_bono_id} onChange={(e) => setData('catalogo_bono_id', e.target.value)} required className={THEME_SELECT}>
                                        <option value="">Selecciona...</option>
                                        {bonos.filter((b) => b.activo).map((b) => (
                                            <option key={b.id} value={b.id}>{b.nombre}</option>
                                        ))}
                                    </select>
                                    {errors.catalogo_bono_id && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.catalogo_bono_id}</p>}
                                </div>
                            )}
                            {(data.tipo_comportamiento === 'deduccion_nomina' || data.categoria === 'falta' || data.categoria === 'retardo') && (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 rounded-2xl border theme-border">
                                    <div>
                                        <label className={THEME_LABEL}>Factor bono puntualidad (0.5=mitad, 1=completo)</label>
                                        <input type="number" min="0" step="0.01" value={data.factor_penalizacion_puntualidad} onChange={(e) => setData('factor_penalizacion_puntualidad', e.target.value)} className={THEME_INPUT} />
                                    </div>
                                    <div>
                                        <label className={THEME_LABEL}>Factor bono productividad</label>
                                        <input type="number" min="0" step="0.01" value={data.factor_penalizacion_productividad} onChange={(e) => setData('factor_penalizacion_productividad', e.target.value)} className={THEME_INPUT} />
                                    </div>
                                    <label className="flex items-center gap-2 md:col-span-2 cursor-pointer">
                                        <input type="checkbox" checked={!!data.aplica_deduccion_salario_base} onChange={(e) => setData('aplica_deduccion_salario_base', e.target.checked)} />
                                        <span className="text-[10px] font-black uppercase theme-text-main">Aplica deducción salario base diario</span>
                                    </label>
                                </div>
                            )}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 rounded-2xl border theme-border">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" checked={!!data.recompensa_auditor_activa} onChange={(e) => setData('recompensa_auditor_activa', e.target.checked)} />
                                    <span className="text-[10px] font-black uppercase theme-text-main">Recompensa auditora activa</span>
                                </label>
                                {data.recompensa_auditor_activa && (
                                    <div>
                                        <label className={THEME_LABEL}>Monto recompensa auditor</label>
                                        <input type="number" min="0" step="0.01" value={data.monto_recompensa_auditor} onChange={(e) => setData('monto_recompensa_auditor', e.target.value)} className={THEME_INPUT} />
                                    </div>
                                )}
                            </div>
                            <MultiSelectDeptArea
                                label="Aplicabilidad (colaborador sancionado)"
                                help="A quién aplica esta deducción. Vacío = todos."
                                departamentos={departamentos}
                                selectedDepts={data.departamentos_aplicables}
                                selectedAreas={data.areas_aplicables}
                                onChangeDepts={(v) => setData('departamentos_aplicables', v)}
                                onChangeAreas={(v) => setData('areas_aplicables', v)}
                            />
                            <MultiSelectDeptArea
                                label="Visibilidad (auditora)"
                                help="Filtra qué usuarios ven este concepto según su departamento/área."
                                departamentos={departamentos}
                                selectedDepts={data.departamentos_visibilidad}
                                selectedAreas={data.areas_visibilidad}
                                onChangeDepts={(v) => setData('departamentos_visibilidad', v)}
                                onChangeAreas={(v) => setData('areas_visibilidad', v)}
                            />
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" checked={!!data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                                <span className="text-[10px] font-black uppercase theme-text-main">Activa</span>
                            </label>
                            <button type="submit" className={`${THEME_BTN_PRIMARY} w-full`}>
                                <Save className="w-4 h-4" /> Guardar regla
                            </button>
                        </form>
                    </div>
                </div>,
                document.body,
            )}

            {modalEliminar && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} items-center p-4`} onClick={() => setModalEliminar(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-md modal-pop p-6`} onClick={(e) => e.stopPropagation()}>
                        <AlertTriangle className="w-8 h-8 text-red-500 mb-3" />
                        <p className="text-sm theme-text-muted mb-4">¿Eliminar regla «{itemActual?.nombre}»?</p>
                        <div className="flex gap-3">
                            <button type="button" onClick={() => setModalEliminar(false)} className={`${THEME_BTN_SECONDARY} flex-1`}>Cancelar</button>
                            <button type="button" onClick={confirmDelete} className="theme-btn-danger flex-1">Eliminar</button>
                        </div>
                    </div>
                </div>,
                document.body,
            )}
        </div>
    );
}
