import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { X, Plus, Trash2, Package, Send } from 'lucide-react';
import SelectorProducto from '../../../Components/Almacenes/SelectorProducto';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';

function estimarEntrega(horarios = []) {
    const ahora = new Date();
    const hora = `${String(ahora.getHours()).padStart(2, '0')}:${String(ahora.getMinutes()).padStart(2, '0')}:00`;
    const lista = [...horarios].sort((a, b) => (a.orden || 0) - (b.orden || 0));
    let match = null;
    for (const h of lista) {
        const ini = String(h.hora_inicio).substring(0, 8);
        const fin = String(h.hora_fin).substring(0, 8);
        if (hora >= ini && (hora < fin || fin >= '23:59:00')) {
            match = h;
            break;
        }
    }
    if (!match && lista.length) match = lista[lista.length - 1];
    if (!match) return null;
    const fecha = new Date(ahora);
    fecha.setHours(0, 0, 0, 0);
    fecha.setDate(fecha.getDate() + (Number(match.dias_para_entrega) || 0));
    return { horario: match, fecha: fecha.toISOString().slice(0, 10) };
}

export default function ModalFormTraspaso({ onClose, almacenes = [], horarios = [], onExito }) {
    const [infoCliente, setInfoCliente] = useState(null);
    const [listaClientes, setListaClientes] = useState([]);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [lineaProductoId, setLineaProductoId] = useState('');
    const [lineaProducto, setLineaProducto] = useState(null);
    const [lineaPiezas, setLineaPiezas] = useState(1);
    const debounceRef = useRef(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        numero_cliente: '',
        almacen_origen_id: almacenes[0]?.id || '',
        productos: [],
    });

    const estimacion = useMemo(() => estimarEntrega(horarios), [horarios]);
    const totalPiezas = useMemo(
        () => data.productos.reduce((acc, p) => acc + (Number(p.piezas) || 0), 0),
        [data.productos]
    );

    useEffect(() => {
        if (almacenes.length && !data.almacen_origen_id) {
            setData('almacen_origen_id', almacenes[0].id);
        }
    }, [almacenes]);

    const buscarClientes = (valor) => {
        setData('numero_cliente', valor);
        setInfoCliente(null);
        clearTimeout(debounceRef.current);
        if (!valor || valor.length < 2) {
            setListaClientes([]);
            setMostrarDropdown(false);
            return;
        }
        debounceRef.current = setTimeout(async () => {
            setBuscandoCliente(true);
            try {
                const response = await axios.get('/api/clientes', { params: { q: valor } });
                setListaClientes(response.data?.data || response.data || []);
                setMostrarDropdown(true);
            } catch {
                setListaClientes([]);
            } finally {
                setBuscandoCliente(false);
            }
        }, 300);
    };

    const seleccionarCliente = (cliente) => {
        setData('numero_cliente', cliente.numero_cliente);
        setInfoCliente(cliente);
        setMostrarDropdown(false);
    };

    const agregarProducto = () => {
        if (!lineaProducto || !lineaPiezas || lineaPiezas < 1) return;
        const yaExiste = data.productos.findIndex((p) => String(p.producto_id) === String(lineaProducto.id));
        let productos;
        if (yaExiste >= 0) {
            productos = data.productos.map((p, i) => (
                i === yaExiste ? { ...p, piezas: Number(p.piezas) + Number(lineaPiezas) } : p
            ));
        } else {
            productos = [
                ...data.productos,
                {
                    producto_id: lineaProducto.id,
                    sku: lineaProducto.sku,
                    descripcion: lineaProducto.descripcion,
                    piezas: Number(lineaPiezas),
                },
            ];
        }
        setData('productos', productos);
        setLineaProductoId('');
        setLineaProducto(null);
        setLineaPiezas(1);
    };

    const quitarProducto = (idx) => {
        setData('productos', data.productos.filter((_, i) => i !== idx));
    };

    const enviar = (e) => {
        e.preventDefault();
        if (!infoCliente) {
            alert('Selecciona un cliente del directorio.');
            return;
        }
        if (!data.productos.length) {
            alert('Agrega al menos un producto.');
            return;
        }
        post(route('traspasos.store'), {
            onSuccess: () => {
                reset();
                onExito?.();
                onClose();
            },
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-3xl w-full flex flex-col text-left`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="flex items-center gap-3 min-w-0">
                        <Package className="w-7 h-7 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <div>
                            <h2 className="text-lg font-black italic theme-text-main uppercase m-0">Nueva Solicitud de Traspaso_</h2>
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">Piezas desde almacén origen</p>
                        </div>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 theme-text-muted rounded-full hover:bg-black/5 dark:hover:bg-white/5"><X className="w-5 h-5" /></button>
                </div>

                <form onSubmit={enviar} className="gelia-modal-body p-5 md:p-6 overflow-y-auto custom-scrollbar flex-1 min-h-0 space-y-6">
                    <div className="relative space-y-2">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Número de cliente</label>
                        <input
                            value={data.numero_cliente}
                            onChange={(e) => buscarClientes(e.target.value)}
                            onFocus={() => { if (data.numero_cliente) setMostrarDropdown(true); }}
                            className="theme-input w-full px-4 py-3 font-bold"
                            placeholder="Buscar por número o nombre…"
                            autoComplete="off"
                        />
                        {infoCliente && (
                            <p className="text-xs font-bold theme-text-main m-0">{infoCliente.numero_cliente} — {infoCliente.nombre}</p>
                        )}
                        {errors.numero_cliente && <p className="text-xs text-red-500">{errors.numero_cliente}</p>}
                        {mostrarDropdown && (
                            <div className="absolute z-20 left-0 right-0 mt-1 max-h-56 overflow-y-auto rounded-xl border theme-border theme-element shadow-lg">
                                {buscandoCliente ? (
                                    <div className="p-4 text-xs font-bold theme-text-muted">Consultando…</div>
                                ) : listaClientes.map((c) => (
                                    <button
                                        type="button"
                                        key={c.id}
                                        onClick={() => seleccionarCliente(c)}
                                        className="w-full text-left p-3 text-xs font-black uppercase theme-text-main hover:bg-black/5 dark:hover:bg-white/5 border-b theme-border last:border-0"
                                    >
                                        {c.numero_cliente} — {c.nombre}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="space-y-2">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Almacén origen</label>
                        <select
                            value={data.almacen_origen_id}
                            onChange={(e) => setData('almacen_origen_id', e.target.value)}
                            className="theme-input w-full px-4 py-3 font-bold"
                            required
                        >
                            <option value="">Seleccionar…</option>
                            {almacenes.map((a) => (
                                <option key={a.id} value={a.id}>{a.codigo} — {a.nombre}</option>
                            ))}
                        </select>
                        {errors.almacen_origen_id && <p className="text-xs text-red-500">{errors.almacen_origen_id}</p>}
                    </div>

                    {estimacion && (
                        <div className="rounded-xl border theme-border theme-element p-3 text-[10px] font-bold theme-text-muted">
                            Ventana: <span className="theme-text-main">{estimacion.horario.nombre}</span>
                            {' · '}Entrega estimada: <span className="theme-text-main">{estimacion.fecha}</span>
                            {estimacion.horario.descripcion ? ` — ${estimacion.horario.descripcion}` : ''}
                        </div>
                    )}

                    <div className="space-y-3">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Registrar productos</label>
                        <div className="grid grid-cols-1 md:grid-cols-[1fr_100px_auto] gap-3 items-end">
                            <SelectorProducto
                                value={lineaProductoId}
                                onChange={(id, producto) => {
                                    setLineaProductoId(id || '');
                                    setLineaProducto(producto || null);
                                }}
                            />
                            <input
                                type="number"
                                min="1"
                                value={lineaPiezas}
                                onChange={(e) => setLineaPiezas(Number(e.target.value) || 1)}
                                className="theme-input w-full px-3 py-2.5 font-bold"
                                placeholder="Piezas"
                            />
                            <button
                                type="button"
                                onClick={agregarProducto}
                                className="theme-btn-primary theme-btn-primary--compact !py-2.5"
                            >
                                <Plus className="w-4 h-4" /> Agregar
                            </button>
                        </div>
                        {lineaProducto && (
                            <p className="text-[10px] font-bold theme-text-muted m-0">
                                SKU: <span className="font-mono theme-text-main">{lineaProducto.sku}</span>
                                {' · '}{lineaProducto.descripcion}
                            </p>
                        )}

                        <div className="rounded-xl border theme-border overflow-hidden">
                            <table className="w-full text-left">
                                <thead>
                                    <tr className="text-[9px] font-black uppercase theme-text-muted border-b theme-border theme-element">
                                        <th className="px-3 py-2">SKU</th>
                                        <th className="px-3 py-2">Nombre</th>
                                        <th className="px-3 py-2 text-right">Piezas</th>
                                        <th className="px-3 py-2 w-10" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.productos.length === 0 && (
                                        <tr><td colSpan={4} className="px-3 py-6 text-center text-xs theme-text-muted">Sin productos</td></tr>
                                    )}
                                    {data.productos.map((p, idx) => (
                                        <tr key={`${p.producto_id}-${idx}`} className="border-b theme-border last:border-0">
                                            <td className="px-3 py-2 font-mono text-xs font-bold theme-text-main">{p.sku}</td>
                                            <td className="px-3 py-2 text-xs font-bold theme-text-main">{p.descripcion}</td>
                                            <td className="px-3 py-2 text-xs font-black text-right theme-text-main">{p.piezas}</td>
                                            <td className="px-3 py-2 text-right">
                                                <button type="button" onClick={() => quitarProducto(idx)} className="p-1 theme-text-muted hover:text-red-500">
                                                    <Trash2 className="w-3.5 h-3.5" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <p className="text-sm font-black theme-text-main m-0 text-right">Total piezas: {totalPiezas}</p>
                        {errors.productos && <p className="text-xs text-red-500">{errors.productos}</p>}
                    </div>

                    <button type="submit" disabled={processing} className="theme-btn-primary w-full !py-3">
                        <Send className="w-4 h-4" /> {processing ? 'Enviando…' : 'Solicitar traspaso'}
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
