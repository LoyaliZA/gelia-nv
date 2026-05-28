import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Package, Edit2, Trash2, Plus, X, Save, AlertTriangle } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';

const CATEGORIAS = [
    { value: 'fisico', label: 'Físico' },
    { value: 'tecnologico', label: 'Tecnológico' },
    { value: 'intangible', label: 'Intangible' },
    { value: 'vestimenta', label: 'Vestimenta' },
];

const TIPOS_CAMPO = [
    { value: 'text', label: 'Texto' },
    { value: 'number', label: 'Número' },
    { value: 'date', label: 'Fecha' },
    { value: 'select', label: 'Selección' },
    { value: 'boolean', label: 'Sí/No' },
    { value: 'textarea', label: 'Texto largo' },
];

export default function TablaTiposActivo({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, put, processing, reset, errors } = useForm({
        nombre: '',
        categoria: 'fisico',
        icono: '',
        activo: true,
        esquema_atributos: { fields: [] },
    });

    const abrirNuevo = () => {
        setItemActual(null);
        reset();
        setData('esquema_atributos', { fields: [] });
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        setData({
            nombre: item.nombre,
            categoria: item.categoria,
            icono: item.icono || '',
            activo: item.activo,
            esquema_atributos: item.esquema_atributos || { fields: [] },
        });
        setModalAbierto(true);
    };

    const agregarCampo = () => {
        const fields = [...(data.esquema_atributos?.fields || []), { key: '', label: '', type: 'text', required: false }];
        setData('esquema_atributos', { fields });
    };

    const actualizarCampo = (index, key, value) => {
        const fields = [...(data.esquema_atributos?.fields || [])];
        fields[index] = { ...fields[index], [key]: value };
        if (key === 'options' && typeof value === 'string') {
            fields[index].options = value.split(',').map((s) => s.trim()).filter(Boolean);
        }
        setData('esquema_atributos', { fields });
    };

    const eliminarCampo = (index) => {
        const fields = (data.esquema_atributos?.fields || []).filter((_, i) => i !== index);
        setData('esquema_atributos', { fields });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const fields = (data.esquema_atributos?.fields || []).map((f) => ({
            ...f,
            key: f.key || f.label?.toLowerCase().replace(/\s+/g, '_'),
        }));
        setData('esquema_atributos', { fields });

        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('admin.catalogos.tipos_activo.update', itemActual.id)
            : route('admin.catalogos.tipos_activo.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        router.delete(route('admin.catalogos.tipos_activo.destroy', itemActual.id), {
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    return (
        <div className="p-6">
            <GeliaLoader isVisible={processing} message="Guardando_" />

            <div className="flex justify-between items-center mb-6">
                <div className="flex items-center gap-3">
                    <Package className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    <h3 className="text-lg font-black uppercase italic theme-text-main">Tipos de Activo</h3>
                </div>
                <button type="button" onClick={abrirNuevo} className="flex items-center gap-2 px-4 py-2 rounded-xl text-[10px] font-black uppercase text-white" style={{ backgroundColor: 'var(--color-primario)' }}>
                    <Plus className="w-4 h-4" /> Nuevo tipo
                </button>
            </div>

            <table className="w-full text-left">
                <thead>
                    <tr className="border-b theme-border text-[10px] font-black uppercase theme-text-muted">
                        <th className="py-3">Nombre</th>
                        <th className="py-3">Categoría</th>
                        <th className="py-3">Campos</th>
                        <th className="py-3">Estado</th>
                        <th className="py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    {datos.map((item) => (
                        <tr key={item.id} className="border-b theme-border">
                            <td className="py-3 font-bold theme-text-main">{item.nombre}</td>
                            <td className="py-3 text-xs theme-text-muted">{item.categoria}</td>
                            <td className="py-3 text-xs theme-text-muted">{item.esquema_atributos?.fields?.length || 0}</td>
                            <td className="py-3 text-xs">{item.activo ? 'Activo' : 'Inactivo'}</td>
                            <td className="py-3 flex gap-2">
                                <button type="button" onClick={() => abrirEditar(item)}><Edit2 className="w-4 h-4" /></button>
                                <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }}><Trash2 className="w-4 h-4 text-red-500" /></button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {modalAbierto && createPortal(
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50">
                    <div className="theme-surface rounded-3xl border theme-border w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                        <div className="flex justify-between p-6 border-b theme-border">
                            <h2 className="font-black uppercase italic">{itemActual ? 'Editar tipo' : 'Nuevo tipo'}</h2>
                            <button type="button" onClick={() => setModalAbierto(false)}><X /></button>
                        </div>
                        <form onSubmit={handleSubmit} className="p-6 space-y-4">
                            <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} placeholder="Nombre" className="w-full rounded-xl px-3 py-2 theme-element border theme-border" />
                            <select value={data.categoria} onChange={(e) => setData('categoria', e.target.value)} className="w-full rounded-xl px-3 py-2 theme-element border theme-border">
                                {CATEGORIAS.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                            </select>
                            <input value={data.icono} onChange={(e) => setData('icono', e.target.value)} placeholder="Icono Lucide (opcional)" className="w-full rounded-xl px-3 py-2 theme-element border theme-border" />

                            <div className="border-t theme-border pt-4">
                                <div className="flex justify-between mb-3">
                                    <span className="text-[10px] font-black uppercase theme-text-muted">Campos del esquema</span>
                                    <button type="button" onClick={agregarCampo} className="text-[10px] font-black uppercase" style={{ color: 'var(--color-primario)' }}>+ Campo</button>
                                </div>
                                {(data.esquema_atributos?.fields || []).map((field, index) => (
                                    <div key={index} className="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2 p-3 rounded-xl theme-element border theme-border">
                                        <input value={field.label || ''} onChange={(e) => actualizarCampo(index, 'label', e.target.value)} placeholder="Etiqueta" className="rounded-lg px-2 py-1 text-sm border theme-border" />
                                        <input value={field.key || ''} onChange={(e) => actualizarCampo(index, 'key', e.target.value)} placeholder="Clave" className="rounded-lg px-2 py-1 text-sm border theme-border" />
                                        <select value={field.type || 'text'} onChange={(e) => actualizarCampo(index, 'type', e.target.value)} className="rounded-lg px-2 py-1 text-sm border theme-border">
                                            {TIPOS_CAMPO.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                                        </select>
                                        {field.type === 'select' && (
                                            <input
                                                defaultValue={(field.options || []).join(', ')}
                                                onBlur={(e) => actualizarCampo(index, 'options', e.target.value)}
                                                placeholder="Opciones (coma)"
                                                className="rounded-lg px-2 py-1 text-sm border theme-border col-span-2"
                                            />
                                        )}
                                        <label className="flex items-center gap-1 text-xs col-span-2">
                                            <input type="checkbox" checked={!!field.required} onChange={(e) => actualizarCampo(index, 'required', e.target.checked)} />
                                            Requerido
                                        </label>
                                        <button type="button" onClick={() => eliminarCampo(index)} className="text-red-500 text-xs">Eliminar</button>
                                    </div>
                                ))}
                            </div>

                            <button type="submit" className="flex items-center gap-2 px-5 py-2 rounded-xl text-white font-black uppercase text-sm" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Save className="w-4 h-4" /> Guardar
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {modalEliminar && createPortal(
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50">
                    <div className="theme-surface rounded-2xl p-6 max-w-sm border theme-border">
                        <AlertTriangle className="w-8 h-8 text-red-500 mb-3" />
                        <p className="font-bold mb-4">¿Eliminar tipo {itemActual?.nombre}?</p>
                        <div className="flex gap-2">
                            <button type="button" onClick={() => setModalEliminar(false)} className="flex-1 py-2 rounded-xl theme-element">Cancelar</button>
                            <button type="button" onClick={confirmDelete} className="flex-1 py-2 rounded-xl bg-red-600 text-white font-black uppercase text-xs">Eliminar</button>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
}
