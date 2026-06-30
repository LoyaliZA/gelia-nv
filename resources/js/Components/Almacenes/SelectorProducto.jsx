import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Loader2, Search } from 'lucide-react';

function etiquetaProducto(p) {
    if (!p) return '';
    return `${p.sku} - ${p.descripcion}`;
}

export default function SelectorProducto({ value, onChange, productoInicial, disabled = false, required = false }) {
    const [texto, setTexto] = useState('');
    const [abierto, setAbierto] = useState(false);
    const [productos, setProductos] = useState([]);
    const [pagina, setPagina] = useState(1);
    const [ultimaPagina, setUltimaPagina] = useState(1);
    const [cargando, setCargando] = useState(false);
    const [seleccionado, setSeleccionado] = useState(productoInicial || null);
    const contenedorRef = useRef(null);
    const listaRef = useRef(null);
    const debounceRef = useRef(null);
    const abortRef = useRef(null);

    useEffect(() => {
        if (productoInicial) {
            setSeleccionado(productoInicial);
            setTexto(etiquetaProducto(productoInicial));
        }
    }, [productoInicial]);

    useEffect(() => {
        if (value && seleccionado && String(seleccionado.id) === String(value)) return;
        if (!value) {
            setSeleccionado(null);
            if (!abierto) setTexto('');
        }
    }, [value, seleccionado, abierto]);

    const cargarLote = useCallback(async (termino, paginaSolicitada, reemplazar = false) => {
        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setCargando(true);
        try {
            const params = new URLSearchParams({
                page: String(paginaSolicitada),
                per_page: '25',
            });
            if (termino.trim()) {
                params.set('q', termino.trim());
            }

            const resp = await fetch(`${route('almacenes.productos.buscar')}?${params}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            });

            if (!resp.ok) {
                if (!reemplazar) setProductos([]);
                return;
            }

            const json = await resp.json();
            const lote = json.data || [];
            setProductos((prev) => (reemplazar ? lote : [...prev, ...lote]));
            setPagina(json.current_page ?? paginaSolicitada);
            setUltimaPagina(json.last_page ?? 1);
        } catch (err) {
            if (err.name !== 'AbortError' && !reemplazar) {
                setProductos([]);
            }
        } finally {
            setCargando(false);
        }
    }, []);

    const programarBusqueda = useCallback((termino) => {
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            setPagina(1);
            cargarLote(termino, 1, true);
        }, 300);
    }, [cargarLote]);

    const abrirLista = () => {
        if (disabled) return;
        setAbierto(true);
        if (productos.length === 0) {
            cargarLote(texto, 1, true);
        }
    };

    const seleccionar = (producto) => {
        setSeleccionado(producto);
        setTexto(etiquetaProducto(producto));
        setAbierto(false);
        onChange(producto.id, producto);
    };

    const handleScroll = (e) => {
        const el = e.currentTarget;
        const cercaDelFondo = el.scrollHeight - el.scrollTop - el.clientHeight < 40;
        if (cercaDelFondo && !cargando && pagina < ultimaPagina) {
            cargarLote(texto, pagina + 1, false);
        }
    };

    useEffect(() => {
        const handleClickFuera = (e) => {
            if (contenedorRef.current && !contenedorRef.current.contains(e.target)) {
                setAbierto(false);
                if (seleccionado) {
                    setTexto(etiquetaProducto(seleccionado));
                }
            }
        };
        document.addEventListener('mousedown', handleClickFuera);
        return () => document.removeEventListener('mousedown', handleClickFuera);
    }, [seleccionado]);

    useEffect(() => () => {
        clearTimeout(debounceRef.current);
        abortRef.current?.abort();
    }, []);

    return (
        <div ref={contenedorRef} className="relative">
            <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" />
                <input
                    type="text"
                    value={texto}
                    required={required && !value}
                    disabled={disabled}
                    placeholder="Buscar por nombre o código..."
                    className="theme-input w-full mt-1 pl-10 pr-3 py-3 text-sm font-bold"
                    onFocus={abrirLista}
                    onChange={(e) => {
                        const val = e.target.value;
                        setTexto(val);
                        setAbierto(true);
                        if (seleccionado && val !== etiquetaProducto(seleccionado)) {
                            setSeleccionado(null);
                            onChange('', null);
                        }
                        programarBusqueda(val);
                    }}
                />
                {cargando && (
                    <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin theme-text-muted" />
                )}
            </div>

            {abierto && !disabled && (
                <div
                    ref={listaRef}
                    onScroll={handleScroll}
                    className="absolute z-20 w-full mt-1 theme-surface border theme-border rounded-2xl shadow-xl max-h-48 overflow-y-auto"
                >
                    {productos.length === 0 && !cargando && (
                        <p className="px-4 py-3 text-xs font-bold theme-text-muted uppercase">
                            {texto.trim() ? 'Sin resultados' : 'Cargando productos...'}
                        </p>
                    )}
                    {productos.map((p) => (
                        <button
                            key={p.id}
                            type="button"
                            onClick={() => seleccionar(p)}
                            className={`w-full text-left px-4 py-2 text-xs theme-text-main hover:bg-black/5 dark:hover:bg-white/5 border-b theme-border last:border-b-0 ${
                                String(value) === String(p.id) ? 'bg-black/5 dark:bg-white/5' : ''
                            }`}
                        >
                            <span className="block font-bold leading-snug">{p.descripcion}</span>
                            <span className="block text-[10px] theme-text-muted mt-0.5 font-mono font-bold">SKU: {p.sku}</span>
                        </button>
                    ))}
                    {cargando && productos.length > 0 && (
                        <p className="px-4 py-2 text-[10px] font-bold theme-text-muted text-center uppercase">Cargando más...</p>
                    )}
                </div>
            )}

            <input type="hidden" name="producto_id" value={value || ''} />
        </div>
    );
}
