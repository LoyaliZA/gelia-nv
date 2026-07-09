import React, { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Lock, Plus, Trash2, Upload, X } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL } from '../../../utils/geliaTheme';
import {
    BADGE_SUCCESS,
    BTN_ACCION,
    BTN_PRIMARY,
    BTN_PRIMARY_STYLE,
    CONTABILIDAD_INNER,
    SECTION_TITLE,
    STORAGE_LISTA_MAPEO,
    STORAGE_LISTA_NOMBRE,
    STORAGE_LISTA_PRECIOS,
    contabilidadCard,
} from './contabilidadStyles';
import { contabilidadRoutes } from '../contabilidadRoutes';
import ModalMapeoListaPrecios from './ModalMapeoListaPrecios';

function productoVacio() {
    return { sku: '', nombre: '', piezas: 1, precio: '', tipo_devolucion: 'normal' };
}

export default function FormRegistroPedido({ plataformas, tiposTransaccion, puedeCrear, configuracion, puedeConfigurar }) {
    const [listaCargada, setListaCargada] = useState(false);
    const [nombreLista, setNombreLista] = useState('');
    const [columnaPrecio, setColumnaPrecio] = useState('');
    const [diccionarioSku, setDiccionarioSku] = useState({});
    const [archivoPendiente, setArchivoPendiente] = useState(null);
    const [modalMapeoAbierto, setModalMapeoAbierto] = useState(false);
    const [errorLista, setErrorLista] = useState('');

    const { data, setData, post, processing, errors, reset } = useForm({
        fecha_salida: new Date().toISOString().split('T')[0],
        numero_pedido: '',
        cliente_nombre: '',
        tipo_transaccion: 'venta',
        plataforma_pago_id: '',
        venta_total: '',
        costo_envio: '99',
        comision_real: '',
        envio_pagado_cliente: false,
        productos: [productoVacio()],
    });

    useEffect(() => {
        try {
            const raw = sessionStorage.getItem(STORAGE_LISTA_PRECIOS);
            const nombre = sessionStorage.getItem(STORAGE_LISTA_NOMBRE);
            const mapeoRaw = sessionStorage.getItem(STORAGE_LISTA_MAPEO);
            if (raw) {
                const parsed = JSON.parse(raw);
                if (parsed && Object.keys(parsed).length > 0) {
                    setDiccionarioSku(parsed);
                    setListaCargada(true);
                    setNombreLista(nombre || 'Lista en memoria');
                    if (mapeoRaw) {
                        const mapeo = JSON.parse(mapeoRaw);
                        setColumnaPrecio(mapeo.precio_base || '');
                    }
                }
            }
        } catch {
            /* ignore */
        }
    }, []);

    const abrirMapeo = (file) => {
        if (!file) return;
        setErrorLista('');
        setArchivoPendiente(file);
        setModalMapeoAbierto(true);
    };

    const confirmarLista = ({ diccionario, mapping, nombreArchivo }) => {
        setDiccionarioSku(diccionario);
        setListaCargada(true);
        setNombreLista(nombreArchivo);
        setColumnaPrecio(mapping.precio_base || '');
        sessionStorage.setItem(STORAGE_LISTA_PRECIOS, JSON.stringify(diccionario));
        sessionStorage.setItem(STORAGE_LISTA_NOMBRE, nombreArchivo);
        sessionStorage.setItem(STORAGE_LISTA_MAPEO, JSON.stringify(mapping));
        setModalMapeoAbierto(false);
        setArchivoPendiente(null);
    };

    const limpiarLista = () => {
        sessionStorage.removeItem(STORAGE_LISTA_PRECIOS);
        sessionStorage.removeItem(STORAGE_LISTA_NOMBRE);
        sessionStorage.removeItem(STORAGE_LISTA_MAPEO);
        setListaCargada(false);
        setDiccionarioSku({});
        setNombreLista('');
        setColumnaPrecio('');
    };

    const actualizarProducto = (index, campo, valor) => {
        const productos = [...data.productos];
        productos[index] = { ...productos[index], [campo]: valor };

        if (campo === 'sku' && diccionarioSku[valor]) {
            productos[index].nombre = diccionarioSku[valor].nombre;
            productos[index].precio = String(diccionarioSku[valor].precio);
        }

        setData('productos', productos);
    };

    const agregarProducto = () => {
        setData('productos', [...data.productos, productoVacio()]);
    };

    const quitarProducto = (index) => {
        if (data.productos.length <= 1) return;
        setData('productos', data.productos.filter((_, i) => i !== index));
    };

    const enviar = (e) => {
        e.preventDefault();
        post(contabilidadRoutes.pedidosStore(), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setData('productos', [productoVacio()]);
                setData('fecha_salida', new Date().toISOString().split('T')[0]);
                setData('costo_envio', '99');
            },
        });
    };

    if (!puedeCrear) return null;

    return (
        <div className="space-y-4 animate-page-reveal" style={{ animationDelay: '150ms' }}>
            <div className={`${contabilidadCard('p-5 md:p-6')}`}>
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h2 className={`${SECTION_TITLE} flex items-center gap-2`}>
                            <Upload className="w-4 h-4 text-[var(--color-primario)]" />
                            1. Lista de precios del día
                        </h2>
                        <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted mt-2">
                            Sube el Excel de resurtido y confirma el mapeo de columnas antes de registrar pedidos.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <label className={BTN_ACCION.upload}>
                            <Upload className="w-4 h-4 shrink-0" />
                            Seleccionar Excel
                            <input
                                type="file"
                                accept=".xlsx,.csv,.xls"
                                className="hidden"
                                onChange={(e) => {
                                    const file = e.target.files?.[0];
                                    e.target.value = '';
                                    abrirMapeo(file);
                                }}
                            />
                        </label>
                        {listaCargada && (
                            <>
                                <span className={BADGE_SUCCESS}>Lista cargada</span>
                                <button
                                    type="button"
                                    onClick={limpiarLista}
                                    className="text-[9px] font-black uppercase tracking-widest text-red-500 hover:text-red-400 flex items-center gap-1 transition-colors"
                                >
                                    <X className="w-3 h-3" /> Borrar
                                </button>
                            </>
                        )}
                        <span className="text-[10px] font-bold theme-text-muted italic uppercase tracking-widest">
                            {listaCargada
                                ? `${nombreLista}${columnaPrecio ? ` · ${columnaPrecio}` : ''}`
                                : 'Sin archivo cargado'}
                        </span>
                    </div>
                </div>
                {errorLista && <p className="text-xs text-red-500 mt-3 font-bold">{errorLista}</p>}
            </div>

            <div className={`${contabilidadCard('p-5 md:p-6 relative')}`}>
                {!listaCargada && (
                    <div className="absolute inset-0 z-10 theme-surface/90 backdrop-blur-md rounded-[2rem] flex flex-col items-center justify-center gap-3 border theme-border">
                        <Lock className="w-10 h-10 text-[var(--color-primario)]" />
                        <p className="font-black italic uppercase theme-text-main text-sm">Carga la lista primero</p>
                        <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted text-center px-6">
                            El formulario se habilita tras mapear y confirmar el Excel de precios.
                        </p>
                    </div>
                )}

                <form onSubmit={enviar} className="space-y-5">
                    <h2 className={`${SECTION_TITLE} border-b theme-border pb-3`}>
                        2. Nuevo registro
                    </h2>

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label className={THEME_LABEL}>Fecha</label>
                            <input
                                type="date"
                                className={`${THEME_INPUT} w-full mt-1`}
                                value={data.fecha_salida}
                                onChange={(e) => setData('fecha_salida', e.target.value)}
                                required
                            />
                            {errors.fecha_salida && <p className="text-xs text-red-500 mt-1">{errors.fecha_salida}</p>}
                        </div>
                        <div>
                            <label className={THEME_LABEL}>Pedido #</label>
                            <input
                                type="text"
                                className={`${THEME_INPUT} w-full mt-1 uppercase font-bold`}
                                value={data.numero_pedido}
                                onChange={(e) => setData('numero_pedido', e.target.value)}
                                placeholder="Ej: 28098"
                                required
                            />
                            {errors.numero_pedido && <p className="text-xs text-red-500 mt-1">{errors.numero_pedido}</p>}
                        </div>
                        <div>
                            <label className={THEME_LABEL}>Cliente</label>
                            <input
                                type="text"
                                className={`${THEME_INPUT} w-full mt-1`}
                                value={data.cliente_nombre}
                                onChange={(e) => setData('cliente_nombre', e.target.value)}
                            />
                        </div>
                        <div>
                            <label className={THEME_LABEL}>Transacción</label>
                            <select
                                className={`${THEME_INPUT} w-full mt-1`}
                                value={data.tipo_transaccion}
                                onChange={(e) => setData('tipo_transaccion', e.target.value)}
                            >
                                {(tiposTransaccion || []).map((t) => (
                                    <option key={t.id} value={t.codigo}>{t.nombre}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className={THEME_LABEL}>Plataforma</label>
                            <select
                                className={`${THEME_INPUT} w-full mt-1`}
                                value={data.plataforma_pago_id}
                                onChange={(e) => setData('plataforma_pago_id', e.target.value)}
                                required
                            >
                                <option value="">Seleccione...</option>
                                {(plataformas || []).map((p) => (
                                    <option key={p.id} value={p.id}>{p.nombre}</option>
                                ))}
                            </select>
                            {errors.plataforma_pago_id && <p className="text-xs text-red-500 mt-1">{errors.plataforma_pago_id}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label className={THEME_LABEL}>Venta total</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                className={`${THEME_INPUT} w-full mt-1`}
                                value={data.venta_total}
                                onChange={(e) => setData('venta_total', e.target.value)}
                                required
                            />
                        </div>
                        <div>
                            <label className={THEME_LABEL}>Costo envío</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                className={`${THEME_INPUT} w-full mt-1`}
                                value={data.costo_envio}
                                onChange={(e) => setData('costo_envio', e.target.value)}
                                required
                            />
                        </div>
                        <div>
                            <label className={`${THEME_LABEL} text-[var(--color-primario)]`}>Comisión real (opcional)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                className={`${THEME_INPUT} w-full mt-1 border-[var(--color-primario)]/30`}
                                value={data.comision_real}
                                onChange={(e) => setData('comision_real', e.target.value)}
                                placeholder="0.00"
                            />
                        </div>
                        <div className="flex items-end pb-2">
                            <label className="flex items-center gap-2 cursor-pointer theme-text-main text-sm font-bold">
                                <input
                                    type="checkbox"
                                    checked={data.envio_pagado_cliente}
                                    onChange={(e) => setData('envio_pagado_cliente', e.target.checked)}
                                    className="rounded border theme-border"
                                />
                                Cliente pagó envío
                            </label>
                        </div>
                    </div>

                    <div className="border-t theme-border pt-4">
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-main">Productos incluidos</h3>
                            <button
                                type="button"
                                onClick={agregarProducto}
                                className={`${BTN_ACCION.upload} !py-1.5 !px-3 text-[9px]`}
                            >
                                <Plus className="w-3 h-3 shrink-0" /> Agregar
                            </button>
                        </div>

                        <div className="space-y-2 max-h-[220px] overflow-y-auto custom-scrollbar pr-1">
                            {data.productos.map((prod, index) => (
                                <div key={index} className={`${CONTABILIDAD_INNER} p-3 grid grid-cols-12 gap-2 items-end`}>
                                    <div className="col-span-2">
                                        <label className="text-[10px] uppercase theme-text-muted font-black tracking-widest">SKU</label>
                                        <input
                                            list={`skus-list-${index}`}
                                            className={`${THEME_INPUT} w-full mt-1 text-sm`}
                                            value={prod.sku}
                                            onChange={(e) => actualizarProducto(index, 'sku', e.target.value.trim())}
                                            required
                                        />
                                        <datalist id={`skus-list-${index}`}>
                                            {Object.keys(diccionarioSku).map((sku) => (
                                                <option key={sku} value={sku} />
                                            ))}
                                        </datalist>
                                    </div>
                                    <div className="col-span-3">
                                        <label className="text-[10px] uppercase theme-text-muted font-black tracking-widest">Producto</label>
                                        <input
                                            className={`${THEME_INPUT} w-full mt-1 text-sm`}
                                            value={prod.nombre}
                                            onChange={(e) => actualizarProducto(index, 'nombre', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <label className="text-[10px] uppercase theme-text-muted font-black tracking-widest">Piezas</label>
                                        <input
                                            type="number"
                                            min="1"
                                            className={`${THEME_INPUT} w-full mt-1 text-sm`}
                                            value={prod.piezas}
                                            onChange={(e) => actualizarProducto(index, 'piezas', parseInt(e.target.value, 10) || 1)}
                                            required
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <label className="text-[10px] uppercase theme-text-muted font-black tracking-widest">Precio</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className={`${THEME_INPUT} w-full mt-1 text-sm`}
                                            value={prod.precio}
                                            onChange={(e) => actualizarProducto(index, 'precio', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <label className="text-[10px] uppercase theme-text-muted font-black tracking-widest">Estado</label>
                                        <select
                                            className={`${THEME_INPUT} w-full mt-1 text-xs py-1.5 px-2 pr-8`}
                                            value={prod.tipo_devolucion || 'normal'}
                                            onChange={(e) => actualizarProducto(index, 'tipo_devolucion', e.target.value)}
                                        >
                                            <option value="normal">Normal</option>
                                            <option value="devuelto">Devuelto</option>
                                            <option value="perdido_danado">Perdido/Dañado</option>
                                        </select>
                                    </div>
                                    <div className="col-span-1 flex justify-end">
                                        <button
                                            type="button"
                                            onClick={() => quitarProducto(index)}
                                            className="theme-btn-icon p-2 text-red-500 rounded-xl border theme-border"
                                            disabled={data.productos.length <= 1}
                                        >
                                            <Trash2 className="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                        {errors.productos && <p className="text-xs text-red-500 mt-2">{errors.productos}</p>}
                    </div>

                    <div className="flex justify-end pt-2">
                        <button
                            type="submit"
                            disabled={processing || !listaCargada}
                            className={BTN_PRIMARY}
                            style={BTN_PRIMARY_STYLE}
                        >
                            {processing ? 'Guardando...' : 'Guardar registro'}
                        </button>
                    </div>
                </form>
            </div>

            {modalMapeoAbierto && archivoPendiente && (
                <ModalMapeoListaPrecios
                    archivo={archivoPendiente}
                    configuracion={configuracion}
                    puedeConfigurar={puedeConfigurar}
                    onCerrar={() => {
                        setModalMapeoAbierto(false);
                        setArchivoPendiente(null);
                    }}
                    onConfirmar={confirmarLista}
                />
            )}
        </div>
    );
}
