import React, { useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { MapPin, Edit2, Trash2, Plus, X, Save, AlertTriangle, Upload, Download } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import { THEME_INPUT, THEME_LABEL, THEME_MODAL_OVERLAY, THEME_SELECT } from '../../../../utils/geliaTheme';
import { formatearMoneda } from '../../../ControlPedidos/Partials/pedidosBmaStyles';

export default function TablaReexpedicionPedido({ datos = [], paqueterias = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [modalImport, setModalImport] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const [processing, setProcessing] = useState(false);
    const fileRef = useRef(null);

    const [form, setForm] = useState({
        codigo_postal: '',
        costo_base: '',
        activo: true,
        seleccionadas: {}, // { [paqId]: { on: bool, costo: '' } }
    });

    const cpsUnicos = useMemo(() => new Set(datos.map((d) => d.codigo_postal)).size, [datos]);

    const abrirNuevo = () => {
        setItemActual(null);
        const sel = {};
        paqueterias.forEach((p) => { sel[p.id] = { on: false, costo: '' }; });
        setForm({ codigo_postal: '', costo_base: '', activo: true, seleccionadas: sel });
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        setForm({
            codigo_postal: item.codigo_postal || '',
            costo_base: item.costo_adicional ?? '',
            activo: item.activo,
            seleccionadas: {
                [item.paqueteria_id]: { on: true, costo: item.costo_adicional ?? '' },
            },
            paqueteria_id: item.paqueteria_id,
            costo_adicional: item.costo_adicional ?? '',
        });
        setModalAbierto(true);
    };

    const togglePaq = (id) => {
        setForm((prev) => ({
            ...prev,
            seleccionadas: {
                ...prev.seleccionadas,
                [id]: {
                    on: !prev.seleccionadas[id]?.on,
                    costo: prev.seleccionadas[id]?.costo ?? '',
                },
            },
        }));
    };

    const setCostoPaq = (id, costo) => {
        setForm((prev) => ({
            ...prev,
            seleccionadas: {
                ...prev.seleccionadas,
                [id]: { ...(prev.seleccionadas[id] || { on: true }), on: true, costo },
            },
        }));
    };

    const enviar = (e) => {
        e.preventDefault();
        setProcessing(true);
        const opts = {
            onFinish: () => setProcessing(false),
            onSuccess: () => { setModalAbierto(false); },
        };

        if (itemActual) {
            router.put(route('admin.catalogos.reexpedicion_pedido.update', itemActual.id), {
                codigo_postal: form.codigo_postal,
                paqueteria_id: form.paqueteria_id,
                costo_adicional: form.costo_adicional === '' ? form.costo_base : form.costo_adicional,
                activo: form.activo,
            }, opts);
            return;
        }

        const paqueteriasPayload = Object.entries(form.seleccionadas)
            .filter(([, v]) => v.on)
            .map(([id, v]) => ({
                paqueteria_id: Number(id),
                costo_adicional: v.costo === '' ? null : v.costo,
            }));

        router.post(route('admin.catalogos.reexpedicion_pedido.store'), {
            codigo_postal: form.codigo_postal,
            costo_base: form.costo_base === '' ? null : form.costo_base,
            activo: form.activo,
            paqueterias: paqueteriasPayload,
        }, opts);
    };

    const confirmDelete = () => {
        setProcessing(true);
        router.delete(route('admin.catalogos.reexpedicion_pedido.destroy', itemActual.id), {
            onFinish: () => setProcessing(false),
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    const importar = (e) => {
        e.preventDefault();
        const file = fileRef.current?.files?.[0];
        if (!file) return;
        setProcessing(true);
        router.post(route('admin.catalogos.reexpedicion_pedido.importar'), { archivo: file }, {
            forceFormData: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => { setModalImport(false); if (fileRef.current) fileRef.current.value = ''; },
        });
    };

    const nombrePaq = (item) => item.paqueteria?.nombre || paqueterias.find((p) => String(p.id) === String(item.paqueteria_id))?.nombre || '—';

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Procesando Reexpedición_" />
            <div className="p-6 md:p-8 border-b theme-border flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <MapPin className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase m-0">Reexpedición_</h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">
                            {cpsUnicos} C.P. · {datos.length} vínculos
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <a
                        href={route('admin.catalogos.reexpedicion_pedido.plantilla')}
                        className="flex items-center gap-2 px-4 py-3 rounded-2xl font-black uppercase text-xs theme-element border theme-border outline-none"
                    >
                        <Download className="w-4 h-4" /> Plantilla
                    </a>
                    <button type="button" onClick={() => setModalImport(true)} className="flex items-center gap-2 px-4 py-3 rounded-2xl font-black uppercase text-xs theme-element border theme-border outline-none">
                        <Upload className="w-4 h-4" /> Importar CSV
                    </button>
                    <button type="button" onClick={abrirNuevo} className="flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase text-xs text-white outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                        <Plus className="w-4 h-4" /> Nuevo
                    </button>
                </div>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">C.P._</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Paquetería_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Costo adic._</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Status_</th>
                            <th className="px-6 py-4 text-right text-[9px] font-black theme-text-muted uppercase tracking-widest">Acciones_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="border-b theme-border last:border-0">
                                <td className="px-6 py-5 text-sm font-black theme-text-main">{item.codigo_postal}</td>
                                <td className="px-6 py-5 text-sm theme-text-main uppercase">{nombrePaq(item)}</td>
                                <td className="px-6 py-5 text-sm theme-text-muted">{item.costo_adicional != null ? formatearMoneda(item.costo_adicional) : '—'}</td>
                                <td className="px-6 py-5 text-sm">{item.activo ? 'Activo' : 'Inactivo'}</td>
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
                    <div className="w-full max-w-lg theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                        <button type="button" onClick={() => setModalAbierto(false)} className="absolute top-4 right-4 p-2 rounded-full theme-text-muted hover:theme-text-main outline-none"><X className="w-5 h-5" /></button>
                        <h3 className="text-xl font-black italic theme-text-main uppercase mb-2">
                            {itemActual ? 'Editar vínculo_' : 'Nueva Reexpedición_'}
                        </h3>
                        {!itemActual && (
                            <p className="text-xs theme-text-muted mb-6 m-0">
                                Un C.P. con costo base y una o más paqueterías. Deje el costo de la paquetería vacío para usar el base; personalícelo si difiere.
                            </p>
                        )}
                        <form onSubmit={enviar} className="space-y-4">
                            <div>
                                <label className={THEME_LABEL}>Código postal_</label>
                                <input type="text" required maxLength={10} value={form.codigo_postal} onChange={(e) => setForm((f) => ({ ...f, codigo_postal: e.target.value }))} className={`${THEME_INPUT} w-full mt-1.5 text-sm font-bold`} />
                            </div>

                            {itemActual ? (
                                <>
                                    <div>
                                        <label className={THEME_LABEL}>Paquetería_</label>
                                        <select required value={form.paqueteria_id} onChange={(e) => setForm((f) => ({ ...f, paqueteria_id: e.target.value }))} className={`${THEME_SELECT} w-full mt-1.5 text-sm font-bold`}>
                                            {paqueterias.map((p) => (
                                                <option key={p.id} value={p.id}>{p.nombre}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className={THEME_LABEL}>Costo adicional_</label>
                                        <input type="number" step="0.01" min="0" value={form.costo_adicional} onChange={(e) => setForm((f) => ({ ...f, costo_adicional: e.target.value }))} className={`${THEME_INPUT} w-full mt-1.5 text-sm font-bold`} />
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div>
                                        <label className={THEME_LABEL}>Costo adicional base_</label>
                                        <input type="number" step="0.01" min="0" value={form.costo_base} onChange={(e) => setForm((f) => ({ ...f, costo_base: e.target.value }))} className={`${THEME_INPUT} w-full mt-1.5 text-sm font-bold`} placeholder="Se aplica a las paqueterías sin override" />
                                    </div>
                                    <div>
                                        <label className={THEME_LABEL}>Paqueterías_</label>
                                        <div className="mt-2 space-y-2 max-h-56 overflow-y-auto rounded-xl border theme-border p-3">
                                            {paqueterias.length === 0 && (
                                                <p className="text-xs theme-text-muted m-0">No hay paqueterías activas.</p>
                                            )}
                                            {paqueterias.map((p) => {
                                                const sel = form.seleccionadas[p.id] || { on: false, costo: '' };
                                                return (
                                                    <div key={p.id} className="flex flex-wrap items-center gap-2">
                                                        <label className="flex items-center gap-2 cursor-pointer min-w-[140px] flex-1">
                                                            <input type="checkbox" checked={!!sel.on} onChange={() => togglePaq(p.id)} className="w-4 h-4" />
                                                            <span className="text-sm font-bold theme-text-main uppercase">{p.nombre}</span>
                                                        </label>
                                                        {sel.on && (
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                placeholder={form.costo_base !== '' ? `Base ${form.costo_base}` : 'Costo'}
                                                                value={sel.costo}
                                                                onChange={(e) => setCostoPaq(p.id, e.target.value)}
                                                                className={`${THEME_INPUT} w-28 py-2 text-xs font-bold`}
                                                                title="Vacío = usar costo base"
                                                            />
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </>
                            )}

                            <label className="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" checked={form.activo} onChange={(e) => setForm((f) => ({ ...f, activo: e.target.checked }))} className="w-4 h-4" />
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

            {modalImport && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} z-[200]`} onClick={() => setModalImport(false)}>
                    <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl relative" onClick={(e) => e.stopPropagation()}>
                        <button type="button" onClick={() => setModalImport(false)} className="absolute top-4 right-4 p-2 rounded-full theme-text-muted outline-none"><X className="w-5 h-5" /></button>
                        <h3 className="text-xl font-black italic theme-text-main uppercase mb-2">Importar CSV_</h3>
                        <p className="text-xs theme-text-muted mb-4 m-0">
                            Columnas: <code>codigo_postal</code>, <code>paqueterias</code> (nombres con |), <code>costo_adicional</code>, <code>activo</code>.
                            Misma fila con varias paqs = mismo costo; override = fila aparte con una paq.
                        </p>
                        <form onSubmit={importar} className="space-y-4">
                            <input ref={fileRef} type="file" accept=".csv,text/csv" required className={`${THEME_INPUT} w-full text-sm`} />
                            <button type="submit" className="w-full py-4 text-white rounded-xl font-black uppercase outline-none flex items-center justify-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Upload className="w-4 h-4" /> Subir
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
                        <p className="text-sm theme-text-muted mb-6">¿Eliminar vínculo CP «{itemActual?.codigo_postal}» / {nombrePaq(itemActual)}?</p>
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
