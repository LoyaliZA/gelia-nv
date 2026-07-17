import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Package, Edit2, Trash2, Plus, X, Save, AlertTriangle } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import { THEME_INPUT, THEME_LABEL, THEME_MODAL_OVERLAY } from '../../../../utils/geliaTheme';

function medidasDesdeDims(largo, ancho, alto) {
    const parts = [largo, ancho, alto].filter((v) => v !== '' && v != null && Number(v) > 0);
    if (parts.length !== 3) return '';
    return `${largo} x ${ancho} x ${alto} cm`;
}

export default function TablaTiposCajaPedido({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, processing, reset } = useForm({
        nombre: '',
        peso_volumetrico: 0,
        medidas: '',
        largo: '',
        ancho: '',
        alto: '',
        activo: true,
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
            peso_volumetrico: item.peso_volumetrico ?? 0,
            medidas: item.medidas ?? '',
            largo: item.largo ?? '',
            ancho: item.ancho ?? '',
            alto: item.alto ?? '',
            activo: item.activo,
        });
        setModalAbierto(true);
    };

    const enviar = (e) => {
        e.preventDefault();
        const medidas = medidasDesdeDims(data.largo, data.ancho, data.alto) || data.medidas || '';
        const body = { ...data, medidas };
        const opts = { onSuccess: () => { setModalAbierto(false); reset(); } };
        if (itemActual) {
            router.put(route('admin.catalogos.tipos_caja_pedido.update', itemActual.id), body, opts);
        } else {
            router.post(route('admin.catalogos.tipos_caja_pedido.store'), body, opts);
        }
    };

    const confirmDelete = () => {
        router.delete(route('admin.catalogos.tipos_caja_pedido.destroy', itemActual.id), {
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    const dimLabel = (item) => {
        if (item.largo != null && item.ancho != null && item.alto != null) {
            return `${item.largo} × ${item.ancho} × ${item.alto} cm`;
        }
        return item.medidas || '—';
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando Tipo Caja_" />
            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <Package className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase m-0">Tipos de Caja_</h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">{datos.length} registros</p>
                    </div>
                </div>
                <button type="button" onClick={abrirNuevo} className="flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase text-xs text-white outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                    <Plus className="w-4 h-4" /> Nuevo
                </button>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Nombre_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Peso vol._</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Dimensiones_</th>
                            <th className="px-6 py-4 text-right text-[9px] font-black theme-text-muted uppercase tracking-widest">Acciones_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="border-b theme-border last:border-0 hover:ring-2 hover:ring-inset hover:ring-[var(--color-primario)]/30">
                                <td className="px-6 py-5 font-black uppercase italic text-sm theme-text-main">{item.nombre}</td>
                                <td className="px-6 py-5 text-sm theme-text-main">{item.peso_volumetrico}</td>
                                <td className="px-6 py-5 text-sm theme-text-muted">{dimLabel(item)}</td>
                                <td className="px-6 py-5 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button type="button" onClick={() => abrirEditar(item)} className="p-2.5 theme-element border theme-border rounded-xl outline-none"><Edit2 className="w-4 h-4 theme-text-main" /></button>
                                        <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2.5 theme-element border theme-border rounded-xl outline-none"><Trash2 className="w-4 h-4 theme-text-main" /></button>
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
                        <button type="button" onClick={() => setModalAbierto(false)} className="absolute top-4 right-4 p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none"><X className="w-5 h-5" /></button>
                        <h3 className="text-xl font-black italic theme-text-main uppercase mb-6">{itemActual ? 'Editar Tipo Caja_' : 'Nuevo Tipo Caja_'}</h3>
                        <form onSubmit={enviar} className="space-y-4">
                            <div>
                                <label className={THEME_LABEL}>Nombre_</label>
                                <input type="text" required placeholder="Nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className={`${THEME_INPUT} w-full mt-1.5 text-sm font-bold`} />
                            </div>
                            <div>
                                <label className={THEME_LABEL}>Peso volumétrico_</label>
                                <input type="number" step="0.0001" min="0" placeholder="Peso volumétrico" value={data.peso_volumetrico} onChange={(e) => setData('peso_volumetrico', e.target.value)} className={`${THEME_INPUT} w-full mt-1.5 text-sm font-bold`} />
                            </div>
                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className={THEME_LABEL}>Largo (cm)_</label>
                                    <input type="number" step="0.01" min="0" value={data.largo} onChange={(e) => setData('largo', e.target.value)} className={`${THEME_INPUT} w-full mt-1.5 text-sm font-bold`} />
                                </div>
                                <div>
                                    <label className={THEME_LABEL}>Ancho (cm)_</label>
                                    <input type="number" step="0.01" min="0" value={data.ancho} onChange={(e) => setData('ancho', e.target.value)} className={`${THEME_INPUT} w-full mt-1.5 text-sm font-bold`} />
                                </div>
                                <div>
                                    <label className={THEME_LABEL}>Alto (cm)_</label>
                                    <input type="number" step="0.01" min="0" value={data.alto} onChange={(e) => setData('alto', e.target.value)} className={`${THEME_INPUT} w-full mt-1.5 text-sm font-bold`} />
                                </div>
                            </div>
                            <label className="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} className="w-4 h-4" />
                                <span className="text-sm font-bold theme-text-main">Activo</span>
                            </label>
                            <button type="submit" className="w-full py-4 text-white rounded-xl font-black uppercase outline-none flex items-center justify-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Save className="w-4 h-4" /> Guardar
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}
            {modalEliminar && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} z-[200]`} onClick={() => setModalEliminar(false)}>
                    <div className="theme-surface border theme-border rounded-[2rem] p-8 text-center max-w-sm shadow-2xl" onClick={(e) => e.stopPropagation()}>
                        <AlertTriangle className="w-12 h-12 text-red-500 mx-auto mb-4" />
                        <p className="text-sm theme-text-muted mb-6">¿Eliminar «{itemActual?.nombre}»?</p>
                        <div className="flex gap-3">
                            <button type="button" onClick={() => setModalEliminar(false)} className="flex-1 py-3 theme-element border theme-border rounded-xl font-black uppercase text-[10px] outline-none">Cancelar</button>
                            <button type="button" onClick={confirmDelete} className="flex-1 py-3 bg-red-600 text-white rounded-xl font-black uppercase text-[10px] outline-none">Eliminar</button>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
}
