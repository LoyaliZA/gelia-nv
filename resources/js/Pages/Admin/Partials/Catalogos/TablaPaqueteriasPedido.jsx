import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Edit2, Trash2, Plus, X, Save, AlertTriangle } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import { THEME_INPUT, THEME_MODAL_OVERLAY } from '../../../../utils/geliaTheme';

const ETIQUETAS_CATEGORIA = {
    comercial: 'Comercial',
    local_regional: 'Local / Regional',
};

const formVacio = () => ({
    nombre: '',
    categoria: 'local_regional',
    permite_costo_diferido: true,
    modalidad_tarifa: '',
    tarifa_monto: '',
    tarifa_unidad_peso: 'kg',
    tarifa_paso_peso: '1',
    activo: true,
});

const etiquetaTarifa = (item) => {
    if (item.categoria === 'comercial' || !item.modalidad_tarifa) return '—';
    if (item.modalidad_tarifa === 'fija') {
        return `Fija $${Number(item.tarifa_monto || 0).toFixed(2)}`;
    }
    if (item.modalidad_tarifa === 'por_peso') {
        return `Por peso $${Number(item.tarifa_monto || 0).toFixed(2)} / ${item.tarifa_paso_peso || 1}${item.tarifa_unidad_peso || 'kg'}`;
    }
    return item.modalidad_tarifa;
};

export default function TablaPaqueteriasPedido({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, put, processing, reset, errors } = useForm(formVacio());

    const abrirNuevo = () => {
        setItemActual(null);
        reset();
        setData(formVacio());
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        setData({
            nombre: item.nombre,
            categoria: item.categoria || 'local_regional',
            permite_costo_diferido: item.permite_costo_diferido ?? item.categoria === 'local_regional',
            modalidad_tarifa: item.modalidad_tarifa || '',
            tarifa_monto: item.tarifa_monto ?? '',
            tarifa_unidad_peso: item.tarifa_unidad_peso || 'kg',
            tarifa_paso_peso: item.tarifa_paso_peso ?? '1',
            activo: item.activo,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const payload = {
            nombre: data.nombre,
            categoria: data.categoria,
            permite_costo_diferido: Boolean(data.permite_costo_diferido),
            modalidad_tarifa: data.modalidad_tarifa || null,
            tarifa_monto: data.tarifa_monto === '' ? null : data.tarifa_monto,
            tarifa_unidad_peso: data.tarifa_unidad_peso || null,
            tarifa_paso_peso: data.tarifa_paso_peso === '' ? null : data.tarifa_paso_peso,
            activo: Boolean(data.activo),
        };
        if (payload.categoria === 'comercial' || !payload.modalidad_tarifa) {
            payload.modalidad_tarifa = null;
            payload.tarifa_monto = null;
            payload.tarifa_unidad_peso = null;
            payload.tarifa_paso_peso = null;
        }
        const ruta = itemActual
            ? route('admin.catalogos.paqueterias_pedido.update', itemActual.id)
            : route('admin.catalogos.paqueterias_pedido.store');
        const opts = { onSuccess: () => { setModalAbierto(false); reset(); } };
        if (itemActual) {
            router.put(ruta, payload, opts);
        } else {
            router.post(ruta, payload, opts);
        }
    };

    const confirmDelete = () => {
        router.delete(route('admin.catalogos.paqueterias_pedido.destroy', itemActual.id), {
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    const esLocal = data.categoria === 'local_regional';
    const muestraTarifa = esLocal && Boolean(data.modalidad_tarifa);
    const muestraPaso = muestraTarifa && data.modalidad_tarifa === 'por_peso';

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando Paquetería_" />
            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                <div>
                    <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Paqueterías_</h2>
                    <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">{datos.length} registros</p>
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
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Categoría_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Tarifa local_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Costo diferido_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Status_</th>
                            <th className="px-6 py-4 text-right text-[9px] font-black theme-text-muted uppercase tracking-widest">Acciones_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="border-b theme-border last:border-0">
                                <td className="px-6 py-5 text-sm font-black theme-text-main uppercase italic">{item.nombre}</td>
                                <td className="px-6 py-5 text-sm font-bold theme-text-muted">{ETIQUETAS_CATEGORIA[item.categoria] || item.categoria}</td>
                                <td className="px-6 py-5 text-sm font-bold theme-text-muted">{etiquetaTarifa(item)}</td>
                                <td className="px-6 py-5 text-sm font-bold theme-text-muted">{item.permite_costo_diferido ? 'Sí' : 'No'}</td>
                                <td className="px-6 py-5">
                                    <span className={`inline-flex px-3 py-1.5 rounded-full text-[9px] font-black uppercase ${item.activo ? 'bg-emerald-500/10 text-emerald-600' : 'bg-red-500/10 text-red-600'}`}>
                                        {item.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                <td className="px-6 py-5 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button type="button" onClick={() => abrirEditar(item)} className="p-2.5 theme-element border theme-border rounded-xl outline-none"><Edit2 className="w-4 h-4" /></button>
                                        <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2.5 theme-element border theme-border rounded-xl outline-none"><Trash2 className="w-4 h-4" /></button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {modalAbierto && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} z-[200]`} onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl relative max-h-[90dvh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                        <button type="button" onClick={() => setModalAbierto(false)} className="absolute top-4 right-4 p-2 rounded-full outline-none"><X className="w-5 h-5" /></button>
                        <h3 className="text-xl font-black italic theme-text-main uppercase mb-6">{itemActual ? 'Editar_' : 'Nuevo_'}</h3>
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Nombre_</label>
                                <input type="text" required value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className={`${THEME_INPUT} w-full mt-2 text-sm font-bold`} />
                                {errors.nombre && <p className="text-xs text-red-500 mt-1">{errors.nombre}</p>}
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Categoría_</label>
                                <select
                                    value={data.categoria}
                                    onChange={(e) => {
                                        const categoria = e.target.value;
                                        setData({
                                            ...data,
                                            categoria,
                                            permite_costo_diferido: categoria === 'local_regional',
                                            modalidad_tarifa: categoria === 'comercial' ? '' : data.modalidad_tarifa,
                                        });
                                    }}
                                    className={`${THEME_INPUT} w-full mt-2 text-sm font-bold`}
                                >
                                    <option value="comercial">Comercial</option>
                                    <option value="local_regional">Local / Regional</option>
                                </select>
                            </div>
                            <label className="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={Boolean(data.permite_costo_diferido)}
                                    onChange={(e) => setData('permite_costo_diferido', e.target.checked)}
                                    className="w-4 h-4"
                                />
                                <span className="text-sm font-bold theme-text-main">Permite costo de envío diferido (municipio)</span>
                            </label>
                            {esLocal && (
                                <div className="space-y-4 p-4 rounded-2xl border theme-border">
                                    <p className="text-[10px] font-black uppercase theme-text-muted tracking-widest m-0">Tarifa local_</p>
                                    <div>
                                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Modalidad_</label>
                                        <select
                                            value={data.modalidad_tarifa}
                                            onChange={(e) => setData('modalidad_tarifa', e.target.value)}
                                            className={`${THEME_INPUT} w-full mt-2 text-sm font-bold`}
                                        >
                                            <option value="">Sin tarifa automática</option>
                                            <option value="fija">Tarifa fija</option>
                                            <option value="por_peso">Tarifa por peso</option>
                                        </select>
                                    </div>
                                    {muestraTarifa && (
                                        <div>
                                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">
                                                {data.modalidad_tarifa === 'fija' ? 'Monto fijo_' : 'Monto por paso_'}
                                            </label>
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                required
                                                value={data.tarifa_monto}
                                                onChange={(e) => setData('tarifa_monto', e.target.value)}
                                                className={`${THEME_INPUT} w-full mt-2 text-sm font-bold`}
                                            />
                                            {errors.tarifa_monto && <p className="text-xs text-red-500 mt-1">{errors.tarifa_monto}</p>}
                                        </div>
                                    )}
                                    {muestraPaso && (
                                        <>
                                            <div>
                                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Unidad de peso_</label>
                                                <select
                                                    value={data.tarifa_unidad_peso}
                                                    onChange={(e) => setData('tarifa_unidad_peso', e.target.value)}
                                                    className={`${THEME_INPUT} w-full mt-2 text-sm font-bold`}
                                                >
                                                    <option value="kg">Kilogramos (kg)</option>
                                                    <option value="g">Gramos (g)</option>
                                                </select>
                                                {errors.tarifa_unidad_peso && <p className="text-xs text-red-500 mt-1">{errors.tarifa_unidad_peso}</p>}
                                            </div>
                                            <div>
                                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">
                                                    Cobrar cada_ ({data.tarifa_unidad_peso || 'kg'})
                                                </label>
                                                <input
                                                    type="number"
                                                    min="0.0001"
                                                    step="any"
                                                    required
                                                    value={data.tarifa_paso_peso}
                                                    onChange={(e) => setData('tarifa_paso_peso', e.target.value)}
                                                    className={`${THEME_INPUT} w-full mt-2 text-sm font-bold`}
                                                />
                                                {errors.tarifa_paso_peso && <p className="text-xs text-red-500 mt-1">{errors.tarifa_paso_peso}</p>}
                                                <p className="text-[10px] theme-text-muted font-bold mt-1 m-0">
                                                    Ej. 1 kg o 500 g: se redondea hacia arriba por cada paso iniciado.
                                                </p>
                                            </div>
                                        </>
                                    )}
                                </div>
                            )}
                            <label className="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} className="w-4 h-4" />
                                <span className="text-sm font-bold theme-text-main">Activo</span>
                            </label>
                            <button type="submit" disabled={processing} className="w-full py-4 text-white rounded-xl font-black uppercase text-[11px] flex items-center justify-center gap-2 outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
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
