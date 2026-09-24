import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { Loader2, Package, X } from 'lucide-react';
import InputConEscanner from '../../../../Components/Escanner/InputConEscanner';
import { THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL } from '../../../../utils/geliaTheme';

export default function BusquedaProductoResguardo({
    lineas = [],
    onAgregar,
    onQuitar,
    onCambiarCantidad,
    deshabilitado = false,
}) {
    const [consulta, setConsulta] = useState('');
    const [resultados, setResultados] = useState([]);
    const [buscando, setBuscando] = useState(false);
    const [mostrarLista, setMostrarLista] = useState(false);
    const abortRef = useRef(null);
    const debounceRef = useRef(null);

    useEffect(() => () => {
        abortRef.current?.abort();
        if (debounceRef.current) clearTimeout(debounceRef.current);
    }, []);

    const buscar = async (termino) => {
        const limpio = String(termino || '').trim();
        if (limpio.length < 2) {
            setResultados([]);
            setMostrarLista(false);
            return;
        }

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;
        setBuscando(true);
        setMostrarLista(true);

        try {
            const { data } = await axios.get(route('punto_venta.resguardos.productos.buscar'), {
                params: { q: limpio },
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            });
            setResultados(Array.isArray(data?.data) ? data.data : []);
        } catch (err) {
            if (!axios.isCancel(err) && err?.code !== 'ERR_CANCELED') {
                setResultados([]);
            }
        } finally {
            if (!controller.signal.aborted) {
                setBuscando(false);
            }
        }
    };

    const onCambioConsulta = (event) => {
        const valor = event.target.value;
        setConsulta(valor);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        if (!valor.trim()) {
            abortRef.current?.abort();
            setResultados([]);
            setMostrarLista(false);
            return;
        }
        debounceRef.current = setTimeout(() => buscar(valor), 400);
    };

    const seleccionar = (producto) => {
        onAgregar?.(producto);
        setConsulta('');
        setMostrarLista(false);
        setResultados([]);
    };

    return (
        <div className="space-y-3">
            <div className="space-y-2">
                <label className={THEME_LABEL} htmlFor="busqueda-producto-resguardo">
                    Buscar o escanear producto
                </label>
                <div className="relative">
                    <InputConEscanner
                        value={consulta}
                        onChange={onCambioConsulta}
                        label="producto"
                        escaneoContinuo={false}
                        className={`${THEME_INPUT} min-h-[44px]`}
                        inputProps={{
                            id: 'busqueda-producto-resguardo',
                            type: 'search',
                            autoComplete: 'off',
                            disabled: deshabilitado,
                            placeholder: 'SKU, código o descripción',
                        }}
                    />
                    {buscando && (
                        <Loader2
                            className="w-4 h-4 animate-spin absolute right-14 top-1/2 -translate-y-1/2"
                            style={{ color: 'var(--color-primario)' }}
                            aria-hidden
                        />
                    )}
                </div>
            </div>

            {mostrarLista && (
                <ul
                    className="rounded-2xl border theme-border theme-element overflow-hidden divide-y theme-border max-h-56 overflow-y-auto"
                    role="listbox"
                >
                    {resultados.length === 0 && !buscando ? (
                        <li className="px-4 py-3 text-sm font-semibold theme-text-muted">Sin coincidencias</li>
                    ) : (
                        resultados.map((producto) => (
                            <li key={producto.id}>
                                <button
                                    type="button"
                                    role="option"
                                    onClick={() => seleccionar(producto)}
                                    disabled={deshabilitado}
                                    className="w-full text-left px-4 py-3 min-h-[44px] hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                                >
                                    <span className="flex items-center gap-2">
                                        <Package className="w-4 h-4 shrink-0 theme-text-muted" aria-hidden />
                                        <span className="min-w-0">
                                            <span className="block text-sm font-bold theme-text-main truncate">{producto.descripcion}</span>
                                            <span className="block text-xs font-semibold theme-text-muted">
                                                {producto.sku || 'Sin SKU'}
                                                {producto.codigo_barras ? ` · ${producto.codigo_barras}` : ''}
                                            </span>
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))
                    )}
                </ul>
            )}

            {lineas.length > 0 && (
                <ul className="space-y-2 m-0 p-0 list-none w-full max-w-full">
                    {lineas.map((linea) => (
                        <li
                            key={linea.producto_id}
                            className="rounded-2xl border theme-border theme-element p-3 flex items-center gap-2 sm:gap-3 w-full max-w-full overflow-hidden"
                        >
                            <div className="min-w-0 flex-1 overflow-hidden">
                                <p className="text-sm font-bold theme-text-main m-0 truncate">{linea.descripcion}</p>
                                <p className="text-xs font-semibold theme-text-muted m-0 truncate">{linea.sku || 'Sin SKU'}</p>
                            </div>
                            <input
                                type="number"
                                min={1}
                                max={9999}
                                value={linea.cantidad}
                                disabled={deshabilitado}
                                onChange={(event) => onCambiarCantidad?.(linea.producto_id, event.target.value)}
                                className={`${THEME_INPUT} min-h-[44px] text-center shrink-0 tabular-nums`}
                                style={{ width: '4.25rem', maxWidth: '4.25rem' }}
                                aria-label={`Cantidad de ${linea.descripcion}`}
                            />
                            <button
                                type="button"
                                onClick={() => onQuitar?.(linea.producto_id)}
                                disabled={deshabilitado}
                                className={`${THEME_BTN_SECONDARY} min-h-[44px] min-w-[44px] p-2 inline-flex items-center justify-center shrink-0`}
                                aria-label={`Quitar ${linea.descripcion}`}
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
