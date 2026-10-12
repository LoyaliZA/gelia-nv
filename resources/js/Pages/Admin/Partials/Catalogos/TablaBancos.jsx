import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Landmark, Edit2, Trash2, Plus, X, Save, AlertTriangle } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import {
    THEME_INPUT,
    THEME_TEXTAREA,
    THEME_LABEL,
    THEME_MODAL_OVERLAY,
    THEME_BTN_PRIMARY,
    GELIA_FIELDSET_LEGEND,
    GELIA_ESTADO_VIVO_TONO,
} from '../../../../utils/geliaTheme';

function badgeActivo(activo) {
    const tono = activo ? GELIA_ESTADO_VIVO_TONO.exito : GELIA_ESTADO_VIVO_TONO.error;
    return `gelia-estado-vivo gelia-estado-vivo--compacto inline-flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest ${tono}${activo ? ' gelia-estado-vivo--pulsando' : ''}`;
}

export default function TablaBancos({ datos = [], departamentos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, put, processing, reset, errors } = useForm({
        nombre: '',
        activo: true,
        compartido: true,
        facturacion_habilitada: true,
        motivo_bloqueo_facturacion: '',
        departamento_ids: [],
    });

    const abrirNuevo = () => {
        setItemActual(null);
        reset();
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        setData({
            nombre: item.nombre,
            activo: item.activo,
            compartido: item.compartido ?? true,
            facturacion_habilitada: item.facturacion_habilitada ?? true,
            motivo_bloqueo_facturacion: item.motivo_bloqueo_facturacion || '',
            departamento_ids: (item.departamentos || []).filter((d) => d.activo).map((d) => d.departamento_id),
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('admin.catalogos.bancos.update', itemActual.id)
            : route('admin.catalogos.bancos.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        router.delete(route('admin.catalogos.bancos.destroy', itemActual.id), {
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando Banco_" />

            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <Landmark className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Bancos_</h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">{datos.length} registros</p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={abrirNuevo}
                    className="flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase text-xs transition-all hover:scale-105 shadow-md text-white outline-none"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    <Plus className="w-4 h-4" /> Nuevo
                </button>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Nombre / ID_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Status_</th>
                            <th className="px-6 py-4 text-right text-[9px] font-black theme-text-muted uppercase tracking-widest">Acciones_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="group transition-all duration-150 border-b theme-border last:border-0 hover:ring-2 hover:ring-inset hover:ring-[var(--color-primario)]/30">
                                <td className="px-6 py-5">
                                    <p className="text-sm font-black theme-text-main uppercase italic leading-tight m-0">{item.nombre}</p>
                                    <p className="text-[9px] font-bold theme-text-muted uppercase tracking-tighter mt-0.5 m-0">ID: #{item.id}</p>
                                </td>
                                <td className="px-6 py-5">
                                    <span className={badgeActivo(item.activo)}>
                                        {item.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                    <p className={`text-xs mt-2 mb-0 ${item.facturacion_habilitada === false ? 'theme-text-peligro' : 'theme-text-muted'}`}>
                                        {item.facturacion_habilitada === false ? 'Facturación bloqueada' : 'Facturación habilitada'}
                                    </p>
                                </td>
                                <td className="px-6 py-5 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button type="button" onClick={() => abrirEditar(item)} className="p-2.5 theme-element border theme-border rounded-xl hover:border-[var(--color-primario)] transition-all outline-none group/btn">
                                            <Edit2 className="w-4 h-4 theme-text-main group-hover/btn:text-[var(--color-primario)] transition-colors" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => { setItemActual(item); setModalEliminar(true); }}
                                            className="p-2.5 theme-element border theme-border rounded-xl hover:border-[var(--color-peligro)] hover:bg-[color-mix(in_srgb,var(--color-peligro)_12%,transparent)] transition-all outline-none"
                                        >
                                            <Trash2 className="w-4 h-4 theme-text-main" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {modalAbierto && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} z-[200]`} onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl relative" onClick={(e) => e.stopPropagation()}>
                        <button type="button" onClick={() => setModalAbierto(false)} className="absolute top-4 right-4 p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none">
                            <X className="w-5 h-5" />
                        </button>
                        <h3 className="text-xl font-black italic theme-text-main uppercase mb-6 m-0">{itemActual ? 'Editar Banco_' : 'Nuevo Banco_'}</h3>
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Nombre_</label>
                                <input
                                    type="text"
                                    required
                                    value={data.nombre}
                                    onChange={(e) => setData('nombre', e.target.value)}
                                    className={`${THEME_INPUT} w-full mt-2 text-sm font-bold`}
                                />
                                {errors.nombre && <p className="text-xs theme-text-peligro mt-1 m-0">{errors.nombre}</p>}
                            </div>
                            <fieldset className="border theme-border rounded-xl p-4 space-y-3">
                                <legend className={GELIA_FIELDSET_LEGEND}>Facturación de pagos</legend>
                                <label className="flex items-start gap-3 cursor-pointer text-sm theme-text-main">
                                    <input
                                        type="checkbox"
                                        checked={data.facturacion_habilitada}
                                        onChange={(e) => setData('facturacion_habilitada', e.target.checked)}
                                        className="mt-1"
                                    />
                                    Permitir facturación de pagos recibidos en este banco
                                </label>
                                {errors.facturacion_habilitada && <p role="alert" className="text-sm theme-text-peligro m-0">{errors.facturacion_habilitada}</p>}
                                <p className="text-xs theme-text-muted m-0">
                                    Si se desactiva, el banco seguirá visible en las solicitudes internas y bloqueará su envío y emisión. Los demás módulos pueden seguir recibiendo pagos.
                                </p>
                                {!data.facturacion_habilitada && (
                                    <div>
                                        <label htmlFor="banco-motivo-facturacion" className={`${THEME_LABEL} mb-2`}>Motivo del bloqueo</label>
                                        <textarea
                                            id="banco-motivo-facturacion"
                                            name="motivo_bloqueo_facturacion"
                                            rows={3}
                                            maxLength={500}
                                            value={data.motivo_bloqueo_facturacion}
                                            onChange={(e) => setData('motivo_bloqueo_facturacion', e.target.value)}
                                            placeholder="Explica qué debe hacer quien solicita la factura…"
                                            className={`${THEME_TEXTAREA} w-full`}
                                        />
                                        {errors.motivo_bloqueo_facturacion && <p role="alert" className="text-sm theme-text-peligro m-0 mt-1">{errors.motivo_bloqueo_facturacion}</p>}
                                    </div>
                                )}
                            </fieldset>
                            <label className="flex items-center gap-3 cursor-pointer text-sm theme-text-main">
                                <input type="checkbox" checked={data.compartido} onChange={(e) => setData('compartido', e.target.checked)} className="w-4 h-4" />
                                Disponible para todos los departamentos
                            </label>
                            {!data.compartido && (
                                <fieldset className="border theme-border rounded-xl p-4 space-y-2">
                                    <legend className={GELIA_FIELDSET_LEGEND}>Departamentos que pueden utilizarlo</legend>
                                    {departamentos.filter((d) => d.activo).map((d) => (
                                        <label key={d.id} className="flex items-center gap-2 text-sm theme-text-main cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={data.departamento_ids.includes(d.id)}
                                                onChange={(e) => setData(
                                                    'departamento_ids',
                                                    e.target.checked
                                                        ? [...data.departamento_ids, d.id]
                                                        : data.departamento_ids.filter((id) => id !== d.id)
                                                )}
                                            />
                                            {d.nombre}
                                        </label>
                                    ))}
                                    {errors.departamento_ids && <p role="alert" className="text-sm theme-text-peligro m-0">{errors.departamento_ids}</p>}
                                </fieldset>
                            )}
                            <label className="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} className="w-4 h-4" />
                                <span className="text-sm font-bold theme-text-main">Activo</span>
                            </label>
                            <button type="submit" disabled={processing} className={`${THEME_BTN_PRIMARY} w-full py-4 text-[11px] tracking-widest flex items-center justify-center gap-2`}>
                                <Save className="w-4 h-4" /> Guardar
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {modalEliminar && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} z-[200]`} onClick={() => setModalEliminar(false)}>
                    <div className="w-full max-w-sm theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl text-center" onClick={(e) => e.stopPropagation()}>
                        <AlertTriangle className="w-12 h-12 theme-text-peligro mx-auto mb-4" />
                        <h3 className="text-lg font-black theme-text-main uppercase mb-2 m-0">¿Eliminar banco?</h3>
                        <p className="text-sm theme-text-muted mb-6 m-0">Se eliminará «{itemActual?.nombre}».</p>
                        <div className="flex gap-3">
                            <button type="button" onClick={() => setModalEliminar(false)} className="flex-1 py-3 theme-element border theme-border rounded-xl font-black uppercase text-[10px] outline-none">Cancelar</button>
                            <button type="button" onClick={confirmDelete} className="flex-1 py-3 rounded-xl font-black uppercase text-[10px] outline-none text-white" style={{ backgroundColor: 'var(--color-peligro)' }}>Eliminar</button>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
}
