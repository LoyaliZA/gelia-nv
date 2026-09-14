import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head } from '@inertiajs/react';
import { RefreshCw, Tag } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import { GELIA_BTN_OUTLINE, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, geliaCardClass } from '../../../utils/geliaTheme';
import PreciosNav from './Partials/PreciosNav';
import FiltrosCatalogo from './Partials/FiltrosCatalogo';
import TablaCatalogoPrecios from './Partials/TablaCatalogoPrecios';
import BarraSeleccion from './Partials/BarraSeleccion';
import EditorCalculoPanel from './Partials/EditorCalculoPanel';
import RevisionPreciosPanel from './Partials/RevisionPreciosPanel';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const STORAGE_KEY = 'tn_precio_selection_id';
const LOTE_KEY = 'tn_precio_lote_id';

const FILTROS_VACIOS = {
    q: '',
    categoria_ids: [],
    incluir_subcategorias: false,
    sin_categoria: false,
    precio_min: '',
    precio_max: '',
    promocion: 'cualquiera',
    costo: 'cualquiera',
    sort: 'id',
    dir: 'asc',
    page: 1,
    per_page: 25,
};

export default function Index({
    auth,
    configuracion,
    catalogo,
    categorias = [],
    sync,
    seleccion: seleccionInicial,
    hay_listas: hayListas,
    metadatos_reglas: metadatosReglas,
    permisos,
}) {
    const [filtros, setFiltros] = useState({ ...FILTROS_VACIOS, ...(catalogo?.filtros || {}) });
    const [listado, setListado] = useState(catalogo);
    const [syncState, setSyncState] = useState(sync);
    const [seleccion, setSeleccion] = useState(seleccionInicial);
    const [busy, setBusy] = useState(false);
    const [mensaje, setMensaje] = useState(null);
    const [confirmarFiltros, setConfirmarFiltros] = useState(null);
    const [editorAbierto, setEditorAbierto] = useState(false);
    const [lote, setLote] = useState(null);
    const [modoRevision, setModoRevision] = useState(false);
    const [exportando, setExportando] = useState(false);
    const abortRef = useRef(null);
    const debounceRef = useRef(null);
    const skipFetch = useRef(true);

    const puedeVerCosto = !!permisos?.precios_ver;
    const puedeSync = !!permisos?.sincronizar;
    const puedeConfigurarCalculo = !!permisos?.reglas_ver && (seleccion?.total_variantes || 0) > 0;
    const filas = listado?.data || [];
    const idsPagina = useMemo(() => filas.map((f) => f.variante_id), [filas]);

    const jsonHeaders = { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() };

    const persistSelectionId = (id) => {
        if (id) {
            sessionStorage.setItem(STORAGE_KEY, id);
        } else {
            sessionStorage.removeItem(STORAGE_KEY);
        }
    };

    const persistLoteId = (id) => {
        if (id) {
            sessionStorage.setItem(LOTE_KEY, id);
        } else {
            sessionStorage.removeItem(LOTE_KEY);
        }
    };

    const queryCatalogo = useCallback(async (nextFiltros, selectionId) => {
        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;
        const params = new URLSearchParams();
        Object.entries(nextFiltros).forEach(([k, v]) => {
            if (v === '' || v === null || v === undefined || v === false) return;
            if (k === 'categoria_ids' && Array.isArray(v)) {
                v.forEach((id) => params.append('categoria_ids[]', id));
                return;
            }
            if (v === true) {
                params.set(k, '1');
                return;
            }
            params.set(k, String(v));
        });
        if (selectionId) params.set('selection_id', selectionId);
        const res = await fetch(`${route('tiendanube.precios.catalogo.listar')}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (!res.ok) {
            throw new Error('No se pudo cargar el catálogo.');
        }
        return res.json();
    }, []);

    const cargar = useCallback(async (nextFiltros, selectionId = seleccion?.selection_id) => {
        try {
            const data = await queryCatalogo(nextFiltros, selectionId);
            setListado(data);
            setSyncState(data.sync || syncState);
            if (data.seleccion) {
                setSeleccion(data.seleccion);
                persistSelectionId(data.seleccion.selection_id);
                if (data.seleccion.filtros_alineados === false && (data.seleccion.total_variantes || 0) > 0) {
                    setConfirmarFiltros({ filas_ocultas: data.seleccion.filas_ocultas });
                } else {
                    setConfirmarFiltros(null);
                }
            }
        } catch (e) {
            if (e.name !== 'AbortError') {
                setMensaje(e.message || 'No se pudo cargar el catálogo.');
            }
        }
    }, [queryCatalogo, seleccion?.selection_id, syncState]);

    useEffect(() => {
        const stored = sessionStorage.getItem(STORAGE_KEY);
        if (!seleccion?.selection_id && stored) {
            cargar(filtros, stored);
        }
        const params = new URLSearchParams(window.location.search);
        const loteFromUrl = params.get('lote_id');
        const loteStored = loteFromUrl || sessionStorage.getItem(LOTE_KEY);
        if (loteStored) {
            fetch(route('tiendanube.precios.lotes.show', loteStored), { headers: { Accept: 'application/json' } })
                .then((res) => (res.ok ? res.json() : null))
                .then((data) => {
                    if (data?.lote_id) {
                        setLote(data);
                        setModoRevision(true);
                    } else {
                        persistLoteId(null);
                    }
                })
                .catch(() => persistLoteId(null));
        }
        // primer pintado ya viene de Inertia
        const t = setTimeout(() => {
            skipFetch.current = false;
        }, 0);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (skipFetch.current) return undefined;
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(async () => {
            await cargar(filtros);
        }, 300);
        return () => clearTimeout(debounceRef.current);
    }, [filtros]); // eslint-disable-line react-hooks/exhaustive-deps

    const pollSync = async (id) => {
        const res = await fetch(route('tiendanube.progreso', id), { headers: { Accept: 'application/json' } });
        if (!res.ok) return false;
        const data = await res.json();
        setSyncState((prev) => ({
            ...prev,
            proceso_activo: ['pendiente', 'en_proceso'].includes(data.estado)
                ? { id: data.id, estado: data.estado, porcentaje: data.porcentaje, fase: data.fase }
                : null,
            ultimo_sync: {
                id: data.id,
                estado: data.estado,
                mensaje_error: data.mensaje_error,
                updated_at: data.updated_at,
            },
        }));
        return !['pendiente', 'en_proceso'].includes(data.estado);
    };

    useEffect(() => {
        const id = syncState?.proceso_activo?.id;
        if (!id) return undefined;
        let cancelled = false;
        const tick = async () => {
            const done = await pollSync(id);
            if (!cancelled && done) {
                cargar(filtros);
            }
        };
        tick();
        const t = setInterval(tick, 3000);
        return () => {
            cancelled = true;
            clearInterval(t);
        };
    }, [syncState?.proceso_activo?.id]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (!confirmarFiltros) return undefined;
        const onKey = (e) => {
            if (e.key === 'Escape') setConfirmarFiltros(null);
        };
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = 'unset';
            window.removeEventListener('keydown', onKey);
        };
    }, [confirmarFiltros]);

    const cambiarFiltro = (patch) => {
        setFiltros((prev) => ({ ...prev, ...patch, page: patch.page || 1 }));
    };

    const sincronizar = async () => {
        if (!puedeSync) {
            setMensaje('Se requiere permiso de sincronización para actualizar el catálogo.');
            return;
        }
        setBusy(true);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.sincronizar'), {
                method: 'POST',
                headers: { ...jsonHeaders, 'Content-Type': 'application/json' },
            });
            const data = await res.json();
            if (res.status === 409) {
                if (syncState?.proceso_activo?.id) {
                    setMensaje('La sincronización ya está en curso. Se muestra el progreso existente.');
                    return;
                }
                setMensaje(data.message || 'Ya hay una sincronización en curso.');
                return;
            }
            if (!res.ok) {
                setMensaje(data.message || 'No se pudo sincronizar.');
                return;
            }
            setSyncState((prev) => ({
                ...prev,
                proceso_activo: { id: data.sync_log_id, estado: 'pendiente', porcentaje: 0 },
            }));
        } finally {
            setBusy(false);
        }
    };

    const apiSeleccion = async (url, method, body) => {
        const res = await fetch(url, {
            method,
            headers: { ...jsonHeaders, 'Content-Type': 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.message || 'No se pudo actualizar la selección.');
        }
        return data;
    };

    const asegurarSeleccion = async (varianteIds, modo = 'pagina') => {
        if (seleccion?.selection_id) {
            return seleccion;
        }
        const created = await apiSeleccion(route('tiendanube.precios.selecciones.store'), 'POST', {
            modo,
            variante_ids: varianteIds,
            page_variante_ids: idsPagina,
            filtros,
        });
        setSeleccion(created);
        persistSelectionId(created.selection_id);
        return created;
    };

    const onToggleFila = async (varianteId, marcar) => {
        try {
            let actual = seleccion;
            if (!actual?.selection_id) {
                if (!marcar) return;
                actual = await asegurarSeleccion([varianteId]);
                return;
            }
            const next = await apiSeleccion(route('tiendanube.precios.selecciones.update', actual.selection_id), 'PATCH', {
                version: actual.version,
                accion: marcar ? 'agregar' : 'quitar',
                variante_ids: [varianteId],
                page_variante_ids: idsPagina,
            });
            setSeleccion(next);
        } catch (e) {
            setMensaje(e.message);
        }
    };

    const onTogglePagina = async (ids, marcar) => {
        try {
            let actual = seleccion;
            if (!actual?.selection_id) {
                if (!marcar) return;
                actual = await asegurarSeleccion(ids);
                return;
            }
            const next = await apiSeleccion(route('tiendanube.precios.selecciones.update', actual.selection_id), 'PATCH', {
                version: actual.version,
                accion: marcar ? 'agregar' : 'quitar',
                variante_ids: ids,
                page_variante_ids: idsPagina,
            });
            setSeleccion(next);
        } catch (e) {
            setMensaje(e.message);
        }
    };

    const onSeleccionarTodos = async () => {
        try {
            let actual = seleccion;
            if (!actual?.selection_id) {
                actual = await apiSeleccion(route('tiendanube.precios.selecciones.store'), 'POST', {
                    modo: 'todos_resultados',
                    filtros,
                });
                setSeleccion(actual);
                persistSelectionId(actual.selection_id);
                return;
            }
            const next = await apiSeleccion(route('tiendanube.precios.selecciones.update', actual.selection_id), 'PATCH', {
                version: actual.version,
                accion: 'seleccionar_todos',
                filtros,
                page_variante_ids: idsPagina,
            });
            setSeleccion(next);
        } catch (e) {
            setMensaje(e.message);
        }
    };

    const onQuitar = async () => {
        if (!seleccion?.selection_id) return;
        try {
            await apiSeleccion(route('tiendanube.precios.selecciones.update', seleccion.selection_id), 'PATCH', {
                version: seleccion.version,
                accion: 'vaciar',
            });
            setSeleccion(null);
            persistSelectionId(null);
        } catch (e) {
            setMensaje(e.message);
        }
    };

    const exportarProductos = async () => {
        if (!seleccion?.selection_id) return;
        setExportando(true);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.precios.catalogo.exportaciones_csv'), {
                method: 'POST',
                headers: { ...jsonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    selection_id: seleccion.selection_id,
                    preset: 'producto_completo',
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.message || 'No se pudo exportar el catálogo.');
            }
            window.location.href = route('tiendanube.precios.exportaciones_csv.descargar', data.id);
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setExportando(false);
        }
    };

    const simularLote = async (payload) => {
        const res = await fetch(route('tiendanube.precios.lotes.store'), {
            method: 'POST',
            headers: { ...jsonHeaders, 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.message || 'No se pudo simular la selección.');
        }
        setLote(data);
        persistLoteId(data.lote_id);
        setModoRevision(true);
        setEditorAbierto(false);
    };

    const recalcularLote = async () => {
        if (!lote?.lote_id) return;
        setBusy(true);
        try {
            const res = await fetch(route('tiendanube.precios.lotes.simular', lote.lote_id), {
                method: 'POST',
                headers: { ...jsonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify({}),
            });
            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.message || 'No se pudo recalcular.');
            }
            setLote(data);
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const estado = listado?.estado;
    const hayCatalogo = (listado?.conteos?.productos_catalogo || 0) > 0;
    const syncActivo = syncState?.proceso_activo;

    return (
        <AppLayout auth={auth}>
            <Head title="Precios Tiendanube" />
            <div className="max-w-[1440px] mx-auto p-3 md:p-4 space-y-3">
                <header className={`${geliaCardClass()} p-3 md:p-4`}>
                    <PreciosNav activa="catalogo" />
                    <div className="flex flex-col md:flex-row md:items-center gap-3">
                        <Tag className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        <div className="flex-1 min-w-0">
                            <h1 className="text-xl font-black italic uppercase theme-text-main m-0">Precios</h1>
                            <p className="text-xs theme-text-muted m-0">
                                {configuracion?.store_name || 'Tienda'}
                                {configuracion?.store_id ? ` · ${configuracion.store_id}` : ''}
                                {syncState?.ultima_actualizacion ? ` · actualizado ${syncState.ultima_actualizacion}` : ' · sin sincronización registrada'}
                            </p>
                        </div>
                        {puedeSync && (
                            <button
                                type="button"
                                onClick={sincronizar}
                                disabled={busy || !!syncActivo}
                                className="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <RefreshCw className={`w-4 h-4 ${syncActivo ? 'animate-spin' : ''}`} />
                                {hayCatalogo ? 'Actualizar catálogo' : 'Cargar catálogo de TiendaNube'}
                            </button>
                        )}
                        {!puedeSync && !hayCatalogo && (
                            <p className="text-xs theme-text-muted">El catálogo aún no está cargado. Quien sincroniza puede iniciar la primera carga.</p>
                        )}
                    </div>
                    {syncActivo && (
                        <p className="text-xs theme-text-muted mt-2">
                            Sincronización en curso ({syncActivo.porcentaje || 0}%). No se inicia otro proceso.
                        </p>
                    )}
                    {syncState?.ultimo_sync?.estado === 'error' && hayCatalogo && (
                        <p className="text-xs text-amber-700 mt-2">
                            La última actualización falló. Se conserva el catálogo previo.
                            {syncState.ultimo_sync.mensaje_error ? ` ${syncState.ultimo_sync.mensaje_error}` : ''}
                        </p>
                    )}
                </header>

                {mensaje && <div className={`${geliaCardClass()} p-3 text-sm theme-text-main`}>{mensaje}</div>}

                {modoRevision && lote ? (
                    <RevisionPreciosPanel
                        lote={lote}
                        permisos={permisos}
                        onLoteChange={(next) => {
                            setLote(next);
                            persistLoteId(next?.lote_id);
                        }}
                        onVolver={() => setModoRevision(false)}
                        onRecalcular={recalcularLote}
                    />
                ) : (
                    <>
                {!hayListas && (
                    <p className="text-xs theme-text-muted px-1">
                        Puedes usar los precios y costos de la tienda o crear una lista opcional.
                    </p>
                )}

                <section className={`${geliaCardClass()} p-3 md:p-4 space-y-3`}>
                    <FiltrosCatalogo
                        filtros={filtros}
                        categorias={categorias}
                        puedeVerCosto={puedeVerCosto}
                        onChange={cambiarFiltro}
                        onLimpiar={() => setFiltros({ ...FILTROS_VACIOS })}
                    />
                    {estado === 'sin_primera_carga' && (
                        <p className="text-sm theme-text-main">No hay productos locales. Cargue el catálogo de TiendaNube para ver precios y variantes.</p>
                    )}
                    {estado === 'sin_coincidencias' && (
                        <p className="text-sm theme-text-main">Ninguna variante coincide con los filtros. Ajuste o limpie los filtros.</p>
                    )}
                    {estado === 'listo' && (
                        <TablaCatalogoPrecios
                            filas={filas}
                            meta={listado.meta}
                            conteos={listado.conteos}
                            puedeVerCosto={puedeVerCosto}
                            puedeEditarCosto={!!permisos?.precios_editar}
                            puedeVerHistorial={!!permisos?.reglas_ver}
                            seleccionadosPagina={seleccion?.seleccionados_pagina || []}
                            onToggleFila={onToggleFila}
                            onTogglePagina={onTogglePagina}
                            onPagina={(page) => cambiarFiltro({ page })}
                            sort={filtros.sort}
                            dir={filtros.dir}
                            onSort={(col) => cambiarFiltro({
                                sort: col,
                                dir: filtros.sort === col && filtros.dir === 'asc' ? 'desc' : 'asc',
                            })}
                        />
                    )}
                </section>

                <BarraSeleccion
                    seleccion={seleccion}
                    variantesResultado={listado?.conteos?.variantes_total || 0}
                    onSeleccionarTodos={onSeleccionarTodos}
                    onQuitar={onQuitar}
                    puedeConfigurar={puedeConfigurarCalculo}
                    puedeExportar={!!permisos?.precios_exportar && !exportando}
                    onConfigurarCalculo={() => setEditorAbierto(true)}
                    onExportarProductos={exportarProductos}
                />
                    </>
                )}
            </div>

            <EditorCalculoPanel
                abierto={editorAbierto}
                onCerrar={() => setEditorAbierto(false)}
                seleccion={seleccion}
                metadatos={metadatosReglas}
                permisos={permisos}
                onSimular={simularLote}
            />

            {confirmarFiltros && createPortal(
                <div
                    className={`${THEME_MODAL_OVERLAY} items-end md:items-center`}
                    onClick={() => setConfirmarFiltros(null)}
                    role="presentation"
                >
                    <div
                        className={`${THEME_MODAL_SHELL} max-w-lg w-full p-4 md:p-6 space-y-3 modal-pop`}
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="tn-filtro-sel"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h2 id="tn-filtro-sel" className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                            Hay una selección activa
                        </h2>
                        <p className="text-sm theme-text-main m-0">
                            Los filtros nuevos no se agregan solos a la selección. Puede mantener la selección congelada
                            {typeof confirmarFiltros.filas_ocultas === 'number' ? ` (${confirmarFiltros.filas_ocultas} filas quedarían fuera de la vista)` : ''}
                            {' '}o reemplazarla por el universo nuevo, vacío hasta que vuelva a marcar filas.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                className={GELIA_BTN_OUTLINE}
                                onClick={async () => {
                                    setConfirmarFiltros(null);
                                    await cargar(filtros, seleccion?.selection_id);
                                }}
                            >
                                Mantener congelada
                            </button>
                            <button
                                type="button"
                                className="px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                                onClick={async () => {
                                    try {
                                        if (seleccion?.selection_id) {
                                            const next = await apiSeleccion(route('tiendanube.precios.selecciones.update', seleccion.selection_id), 'PATCH', {
                                                version: seleccion.version,
                                                accion: 'reemplazar',
                                                modo: 'pagina',
                                                variante_ids: [],
                                                filtros,
                                            });
                                            setSeleccion(next);
                                        }
                                        setConfirmarFiltros(null);
                                        await cargar(filtros, seleccion?.selection_id);
                                    } catch (e) {
                                        setMensaje(e.message);
                                    }
                                }}
                            >
                                Reemplazar selección
                            </button>
                        </div>
                    </div>
                </div>,
                document.body,
            )}
        </AppLayout>
    );
}
