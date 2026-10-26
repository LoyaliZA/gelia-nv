import React, { useEffect, useId, useMemo, useRef, useState } from 'react';
import { Check, ChevronDown, Search } from 'lucide-react';

const normalizar = (valor) => String(valor ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('es-MX');

export default function SelectorConBusqueda({
    id,
    etiqueta,
    valor = '',
    onChange,
    opciones = [],
    textoVacio = 'Cualquiera',
    permitirVacio = false,
    placeholderBusqueda = 'Buscar…',
    textoSeleccionado = '',
    textoSecundario = '',
    ocultarEtiqueta = false,
    busquedaRemota = false,
    cargando = false,
    hayMas = false,
    aviso = '',
    onBuscar,
    activo = false,
    sinBusqueda = false,
    animar = false,
    className = '',
}) {
    const uid = useId();
    const controlId = id || uid;
    const listaId = `${uid}-lista`;
    const [abierto, setAbierto] = useState(false);
    const [presente, setPresente] = useState(false);
    const [estadoPanel, setEstadoPanel] = useState('cerrado');
    const [busqueda, setBusqueda] = useState('');
    const [indice, setIndice] = useState(0);
    const raizRef = useRef(null);
    const busquedaRef = useRef(null);
    const disparadorRef = useRef(null);

    const seleccionada = opciones.find((opcion) => String(opcion.value) === String(valor));
    const textoVisible = seleccionada?.label || textoSeleccionado || textoVacio;
    const IconoSeleccionado = seleccionada?.icono;

    const filtradas = useMemo(() => {
        const base = permitirVacio ? [{ value: '', label: textoVacio }, ...opciones] : opciones;
        if (busquedaRemota) return base;
        const palabras = normalizar(busqueda).trim().split(/\s+/).filter(Boolean);
        if (palabras.length === 0) return base;
        return base.filter((opcion) => {
            const texto = normalizar(`${opcion.label} ${opcion.texto || ''}`);
            return palabras.every((palabra) => texto.includes(palabra));
        });
    }, [opciones, busqueda, permitirVacio, textoVacio, busquedaRemota]);

    const cerrar = (devolverFoco = false) => {
        setAbierto(false);
        setBusqueda('');
        if (devolverFoco) disparadorRef.current?.focus();
    };

    const elegir = (opcion) => {
        onChange?.(opcion.value);
        cerrar(true);
    };

    useEffect(() => {
        if (abierto) {
            setPresente(true);
            const marco = requestAnimationFrame(() => setEstadoPanel('abierto'));
            return () => cancelAnimationFrame(marco);
        }
        setEstadoPanel('cerrado');
        if (!animar) return undefined;
        const timer = window.setTimeout(() => setPresente(false), 260);
        return () => window.clearTimeout(timer);
    }, [abierto, animar]);

    useEffect(() => {
        if (!abierto || sinBusqueda) return undefined;
        busquedaRef.current?.focus({ preventScroll: true });
    }, [abierto, sinBusqueda]);

    useEffect(() => {
        if (!abierto) return undefined;
        const fuera = (event) => {
            if (!raizRef.current?.contains(event.target)) cerrar();
        };
        const escape = (event) => {
            if (event.key !== 'Escape') return;
            event.preventDefault();
            event.stopPropagation();
            cerrar(true);
        };
        document.addEventListener('mousedown', fuera);
        window.addEventListener('keydown', escape, true);
        return () => {
            document.removeEventListener('mousedown', fuera);
            window.removeEventListener('keydown', escape, true);
        };
    }, [abierto]);

    const opcionesRef = useRef(opciones);
    const valorRef = useRef(valor);
    opcionesRef.current = opciones;
    valorRef.current = valor;

    useEffect(() => {
        if (!abierto) return;
        if (busqueda.trim() || !sinBusqueda) {
            setIndice(0);
            return;
        }
        const actual = opcionesRef.current.findIndex((opcion) => String(opcion.value) === String(valorRef.current));
        setIndice(actual >= 0 ? actual : 0);
    }, [busqueda, abierto, sinBusqueda]);

    useEffect(() => {
        raizRef.current?.querySelector('[data-highlighted="true"]')?.scrollIntoView?.({ block: 'nearest' });
    }, [indice]);

    const teclado = (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setIndice((actual) => {
                if (filtradas.length === 0) return 0;
                const delta = event.key === 'ArrowDown' ? 1 : -1;
                return (actual + delta + filtradas.length) % filtradas.length;
            });
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (filtradas[indice]) elegir(filtradas[indice]);
        } else if (event.key === 'Tab') {
            cerrar();
        }
    };

    return (
        <div className={`usuarios-selector ${className}`} ref={raizRef}>
            <label className={`usuarios-selector-etiqueta${ocultarEtiqueta ? ' sr-only' : ''}`} htmlFor={controlId}>{etiqueta}</label>
            <button
                ref={disparadorRef}
                id={controlId}
                type="button"
                className="usuarios-selector-trigger"
                aria-haspopup="listbox"
                aria-expanded={abierto}
                aria-controls={abierto ? listaId : undefined}
                data-activo={activo ? 'true' : 'false'}
                title={textoVisible}
                onClick={() => {
                    if (abierto) cerrar();
                    else {
                        setBusqueda('');
                        setAbierto(true);
                        onBuscar?.('');
                    }
                }}
                aria-activedescendant={sinBusqueda && abierto && filtradas[indice] ? `${uid}-op-${indice}` : undefined}
                onKeyDown={(event) => {
                    if (sinBusqueda && abierto) {
                        teclado(event);
                        return;
                    }
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        setBusqueda('');
                        setAbierto(true);
                        onBuscar?.('');
                    }
                }}
            >
                <span className="usuarios-selector-valor">
                    {IconoSeleccionado ? (
                        <IconoSeleccionado className="w-4 h-4 shrink-0" aria-hidden="true" />
                    ) : null}
                    <span className="usuarios-selector-nombre">{textoVisible}</span>
                    {textoSecundario ? <span className="usuarios-selector-secundario">{textoSecundario}</span> : null}
                </span>
                <ChevronDown className="usuarios-selector-chevron w-4 h-4 shrink-0 theme-text-muted" aria-hidden="true" />
            </button>
            {(animar ? presente : abierto) && (
                <div
                    className="usuarios-selector-panel"
                    data-estado={animar ? estadoPanel : 'abierto'}
                    onTransitionEnd={(event) => {
                        if (!animar || event.target !== event.currentTarget || event.propertyName !== 'opacity') return;
                        if (!abierto) setPresente(false);
                    }}
                >
                    {!sinBusqueda && (
                    <div className="usuarios-selector-buscar">
                        <Search className="w-4 h-4 shrink-0 theme-text-muted" aria-hidden="true" />
                        <input
                            ref={busquedaRef}
                            type="search"
                            role="combobox"
                            aria-label={`Buscar ${etiqueta.toLowerCase()}`}
                            aria-expanded="true"
                            aria-controls={listaId}
                            aria-autocomplete="list"
                            aria-activedescendant={filtradas[indice] ? `${uid}-op-${indice}` : undefined}
                            value={busqueda}
                            placeholder={placeholderBusqueda}
                            autoComplete="off"
                            onChange={(event) => {
                                setBusqueda(event.target.value);
                                onBuscar?.(event.target.value);
                            }}
                            onKeyDown={teclado}
                        />
                    </div>
                    )}
                    <div id={listaId} role="listbox" aria-label={etiqueta} className="usuarios-selector-lista">
                        {filtradas.map((opcion, i) => {
                            const elegido = String(opcion.value) === String(valor ?? '');
                            const detalle = opcion.detalle;
                            const Icono = opcion.icono;
                            return (
                                <button
                                    key={`${opcion.value || 'vacio'}-${i}`}
                                    id={`${uid}-op-${i}`}
                                    type="button"
                                    role="option"
                                    tabIndex={-1}
                                    aria-selected={elegido}
                                    data-highlighted={i === indice ? 'true' : 'false'}
                                    onMouseDown={(event) => event.preventDefault()}
                                    onMouseEnter={() => setIndice(i)}
                                    onClick={() => elegir(opcion)}
                                    className="usuarios-selector-opcion"
                                >
                                    <span className="usuarios-selector-opcion-cuerpo">
                                        {Icono ? <Icono className="w-4 h-4 shrink-0 theme-text-muted" aria-hidden="true" /> : null}
                                        <span className="min-w-0">
                                            <span className="usuarios-selector-opcion-titulo">{opcion.label}</span>
                                            {detalle ? <span className="usuarios-selector-opcion-detalle">{detalle}</span> : null}
                                        </span>
                                    </span>
                                    {elegido && <Check className="w-4 h-4 shrink-0 theme-text-primario" aria-hidden="true" />}
                                </button>
                            );
                        })}
                        {filtradas.length === 0 && (
                            <p className="usuarios-selector-vacio">{cargando ? 'Buscando…' : (aviso || 'Sin coincidencias')}</p>
                        )}
                        {hayMas && filtradas.length > 0 && (
                            <p className="usuarios-selector-vacio">Hay más resultados. Afina la búsqueda.</p>
                        )}
                    </div>
                    <p className="sr-only" role="status">{cargando ? 'Buscando' : `${filtradas.length} opciones`}</p>
                </div>
            )}
        </div>
    );
}
