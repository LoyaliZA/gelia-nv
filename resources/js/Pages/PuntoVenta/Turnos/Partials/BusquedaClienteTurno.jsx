import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { Loader2, Search, User, X } from 'lucide-react';
import { THEME_INPUT } from './turnosStyles';
import { esListaDiamanteCliente } from './altaTurnoUtils';

export default function BusquedaClienteTurno({
    clienteSeleccionado = null,
    onSeleccionar,
    onLimpiar,
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
            const { data } = await axios.get('/api/clientes', {
                params: { q: limpio },
                signal: controller.signal,
            });
            setResultados(Array.isArray(data) ? data : []);
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
        onLimpiar?.();
        if (debounceRef.current) clearTimeout(debounceRef.current);
        if (!valor.trim()) {
            abortRef.current?.abort();
            setResultados([]);
            setMostrarLista(false);
            return;
        }
        debounceRef.current = setTimeout(() => buscar(valor), 400);
    };

    const seleccionar = (cliente) => {
        onSeleccionar?.(cliente);
        setConsulta(cliente.numero_cliente ? `${cliente.numero_cliente} — ${cliente.nombre}` : cliente.nombre);
        setMostrarLista(false);
        setResultados([]);
    };

    const limpiarSeleccion = () => {
        setConsulta('');
        setResultados([]);
        setMostrarLista(false);
        onLimpiar?.();
    };

    if (clienteSeleccionado?.id) {
        return (
            <div className="rounded-2xl border theme-border theme-element p-4 space-y-2">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Cliente seleccionado</p>
                        <p className="text-sm font-bold theme-text-main m-0 truncate">{clienteSeleccionado.nombre}</p>
                        {clienteSeleccionado.numero_cliente && (
                            <p className="text-xs font-semibold theme-text-muted m-0">No. {clienteSeleccionado.numero_cliente}</p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={limpiarSeleccion}
                        disabled={deshabilitado}
                        className="shrink-0 min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl theme-border border"
                        aria-label="Quitar cliente seleccionado"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>
                {esListaDiamanteCliente(clienteSeleccionado) && (
                    <span className="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-black uppercase bg-violet-500/15 text-violet-700 dark:text-violet-300">
                        Lista Diamante
                    </span>
                )}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted" htmlFor="busqueda-cliente-turno">
                Buscar cliente
            </label>
            <div className="relative">
                <Search className="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 theme-text-muted pointer-events-none" aria-hidden />
                <input
                    id="busqueda-cliente-turno"
                    type="search"
                    inputMode="search"
                    autoComplete="off"
                    value={consulta}
                    onChange={onCambioConsulta}
                    disabled={deshabilitado}
                    placeholder="Número o nombre (mín. 2 caracteres)"
                    className={`${THEME_INPUT} !pl-11 min-h-[44px]`}
                    aria-autocomplete="list"
                    aria-expanded={mostrarLista}
                    aria-controls="resultados-cliente-turno"
                />
                {buscando && (
                    <Loader2
                        className="w-4 h-4 animate-spin absolute right-4 top-1/2 -translate-y-1/2"
                        style={{ color: 'var(--color-primario)' }}
                        aria-hidden
                    />
                )}
            </div>

            {mostrarLista && (
                <ul
                    id="resultados-cliente-turno"
                    className="rounded-2xl border theme-border theme-element overflow-hidden divide-y theme-divide max-h-56 overflow-y-auto"
                    role="listbox"
                >
                    {resultados.length === 0 && !buscando ? (
                        <li className="px-4 py-3 text-sm font-semibold theme-text-muted">Sin coincidencias</li>
                    ) : (
                        resultados.map((cliente) => (
                            <li key={cliente.id}>
                                <button
                                    type="button"
                                    role="option"
                                    onClick={() => seleccionar(cliente)}
                                    className="w-full text-left px-4 py-3 min-h-[44px] hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                                >
                                    <span className="flex items-center gap-2">
                                        <User className="w-4 h-4 shrink-0 theme-text-muted" aria-hidden />
                                        <span className="min-w-0">
                                            <span className="block text-sm font-bold theme-text-main truncate">{cliente.nombre}</span>
                                            {cliente.numero_cliente && (
                                                <span className="block text-xs font-semibold theme-text-muted">No. {cliente.numero_cliente}</span>
                                            )}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))
                    )}
                </ul>
            )}
        </div>
    );
}
