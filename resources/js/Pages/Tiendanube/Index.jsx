import React, { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { geliaCardClass } from '../../utils/geliaTheme';
import { Plus, Settings, RefreshCw, Store, Search } from 'lucide-react';
import ModalConfiguracion from './Partials/ModalConfiguracion';
import ModalHerramientas from './Partials/ModalHerramientas';
import TablaProductos from './Partials/TablaProductos';
import ModalDetalleProducto from './Partials/ModalDetalleProducto';
import ModalCrearProducto from './Partials/ModalCrearProducto';

export default function Index({
    auth,
    configuracion,
    productos,
    totales,
    procesoActivo,
    imageImportActivo,
    ultimosSyncs,
    ultimosImportImagenes = [],
    categorias = [],
    filters,
    permisos,
}) {
    const [showConfig, setShowConfig] = useState(false);
    const [showHerramientas, setShowHerramientas] = useState(false);
    const [showCrear, setShowCrear] = useState(false);
    const [detalleId, setDetalleId] = useState(null);
    const [search, setSearch] = useState(filters?.search || '');
    const [filtroAlertaImagenes, setFiltroAlertaImagenes] = useState(!!filters?.imagenes_alerta);
    const [syncLogId, setSyncLogId] = useState(procesoActivo?.id || null);
    const [imageImportId, setImageImportId] = useState(imageImportActivo?.id || null);

    useEffect(() => {
        setFiltroAlertaImagenes(!!filters?.imagenes_alerta);
    }, [filters?.imagenes_alerta]);

    useEffect(() => {
        if (procesoActivo?.id) {
            setSyncLogId(procesoActivo.id);
        }
    }, [procesoActivo?.id]);

    useEffect(() => {
        if (imageImportActivo?.id) {
            setImageImportId(imageImportActivo.id);
        }
    }, [imageImportActivo?.id]);

    // Si el modal está cerrado, seguir el progreso en segundo plano para el indicador del header.
    useEffect(() => {
        if (showHerramientas) return undefined;
        if (!syncLogId && !imageImportId) return undefined;

        let cancelled = false;
        const terminal = (estado) => estado && !['pendiente', 'en_proceso'].includes(estado);

        const poll = async () => {
            try {
                let done = true;
                if (syncLogId) {
                    const res = await fetch(route('tiendanube.progreso', syncLogId), { headers: { Accept: 'application/json' } });
                    if (res.ok) {
                        const data = await res.json();
                        if (!terminal(data.estado)) done = false;
                    }
                }
                if (imageImportId) {
                    const res = await fetch(route('tiendanube.imagenes.importar.progreso', imageImportId), {
                        headers: { Accept: 'application/json' },
                    });
                    if (res.ok) {
                        const data = await res.json();
                        if (!terminal(data.estado)) done = false;
                    }
                }
                if (!cancelled && done) {
                    setSyncLogId(null);
                    setImageImportId(null);
                    router.reload({
                        only: ['productos', 'totales', 'procesoActivo', 'ultimosSyncs', 'imageImportActivo', 'ultimosImportImagenes'],
                    });
                }
            } catch {
                // ignore
            }
        };

        poll();
        const id = setInterval(poll, 3000);
        return () => {
            cancelled = true;
            clearInterval(id);
        };
    }, [showHerramientas, syncLogId, imageImportId]);

    const aplicarFiltros = (overrides = {}) => {
        const alerta = Object.prototype.hasOwnProperty.call(overrides, 'imagenes_alerta')
            ? overrides.imagenes_alerta
            : filtroAlertaImagenes;
        router.get(
            route('tiendanube.index'),
            {
                search: (overrides.search !== undefined ? overrides.search : search) || undefined,
                imagenes_alerta: alerta ? 1 : undefined,
            },
            { preserveState: true }
        );
    };

    const buscar = (e) => {
        e.preventDefault();
        aplicarFiltros();
    };

    const toggleAlertaImagenes = () => {
        const next = !filtroAlertaImagenes;
        setFiltroAlertaImagenes(next);
        aplicarFiltros({ imagenes_alerta: next });
    };

    const reloadLista = () => router.reload({ only: ['productos', 'totales', 'ultimosImportImagenes', 'imageImportActivo'] });

    const canHerramientas = permisos.sincronizar || permisos.editar || permisos.configurar;
    const hayProcesoFondo = !!(procesoActivo || imageImportActivo);

    return (
        <AppLayout auth={auth}>
            <Head title="Tiendanube" />

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className={`${geliaCardClass()} p-6 md:p-10 flex flex-col md:flex-row items-center justify-between gap-4 border-b-[4px]`} style={{ borderColor: 'var(--color-primario)' }}>
                    <div className="text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Vinculaciones</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            TIENDA<span style={{ color: 'var(--color-primario)' }}>NUBE</span>
                        </h1>
                        <p className="theme-text-muted mt-2 text-sm">
                            Catálogo: productos, categorías, SEO e imágenes.
                            {configuracion?.store_name ? ` · ${configuracion.store_name}` : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 justify-center">
                        {permisos.editar && (
                            <button
                                type="button"
                                onClick={() => setShowCrear(true)}
                                disabled={!configuracion?.credenciales_configuradas}
                                className="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest flex items-center gap-2 text-white disabled:opacity-50"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Plus className="w-4 h-4" /> Nuevo producto
                            </button>
                        )}
                        {canHerramientas && (
                            <button
                                type="button"
                                onClick={() => setShowHerramientas(true)}
                                className="px-4 py-2 rounded-xl border theme-border bg-white dark:bg-zinc-900 text-[10px] font-black uppercase tracking-widest flex items-center gap-2 theme-text-main hover:bg-gray-50 dark:hover:bg-zinc-800 relative"
                            >
                                <RefreshCw className={`w-4 h-4 ${hayProcesoFondo ? 'animate-spin' : ''}`} style={{ color: 'var(--color-primario)' }} />
                                Herramientas
                                {hayProcesoFondo && (
                                    <span className="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full bg-amber-500" />
                                )}
                            </button>
                        )}
                        {permisos.configurar && (
                            <button
                                type="button"
                                onClick={() => setShowConfig(true)}
                                className="px-4 py-2 rounded-xl border theme-border bg-white dark:bg-zinc-900 text-[10px] font-black uppercase tracking-widest flex items-center gap-2 theme-text-main hover:bg-gray-50 dark:hover:bg-zinc-800"
                            >
                                <Settings className="w-4 h-4" /> Configuración
                            </button>
                        )}
                    </div>
                </header>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div className={`${geliaCardClass()} p-5 flex items-center gap-4`}>
                        <Store className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Estado</p>
                            <p className="text-sm font-bold theme-text-main">
                                {configuracion?.credenciales_configuradas ? 'Conectado' : 'Sin credenciales'}
                            </p>
                            <p className="text-xs theme-text-muted">Store ID: {configuracion?.store_id || '—'}</p>
                        </div>
                    </div>
                    <div className={`${geliaCardClass()} p-5`}>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Productos</p>
                        <p className="text-3xl font-black theme-text-main">{totales?.productos ?? 0}</p>
                    </div>
                    <div className={`${geliaCardClass()} p-5`}>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Categorías</p>
                        <p className="text-3xl font-black theme-text-main">{totales?.categorias ?? 0}</p>
                    </div>
                    <button
                        type="button"
                        onClick={toggleAlertaImagenes}
                        className={`${geliaCardClass()} p-5 text-left transition-colors ${
                            filtroAlertaImagenes ? 'ring-2 ring-amber-500/60' : 'hover:bg-black/[0.02] dark:hover:bg-white/[0.02]'
                        }`}
                    >
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Imágenes a revisar</p>
                        <p className="text-3xl font-black text-amber-600">{totales?.productos_alerta_imagenes ?? 0}</p>
                        <p className="text-[10px] theme-text-muted mt-1">
                            {filtroAlertaImagenes ? 'Filtro activo · clic para quitar' : 'Clic para filtrar'}
                        </p>
                    </button>
                </div>

                <div className={`${geliaCardClass()} p-4 md:p-6 space-y-4`}>
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main">
                            Catálogo
                        </h2>
                        <form onSubmit={buscar} className="flex gap-2 w-full sm:w-auto">
                            <div className="flex items-center flex-1 theme-element border theme-border rounded-xl px-3">
                                <Search className="w-4 h-4 theme-text-muted" />
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Buscar ID, SKU, SEO, marca…"
                                    className="w-full bg-transparent py-2.5 px-2 text-sm outline-none theme-text-main"
                                />
                            </div>
                            <button
                                type="submit"
                                className="px-4 py-2 rounded-xl text-[10px] font-black uppercase text-white"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                Buscar
                            </button>
                        </form>
                    </div>

                    <TablaProductos productos={productos} onSelect={(id) => setDetalleId(id)} />
                </div>
            </div>

            {showConfig && (
                <ModalConfiguracion
                    configuracion={configuracion}
                    onClose={() => {
                        setShowConfig(false);
                        router.reload({ only: ['configuracion'] });
                    }}
                />
            )}

            {showHerramientas && (
                <ModalHerramientas
                    permisos={permisos}
                    credencialesOk={!!configuracion?.credenciales_configuradas}
                    procesoActivo={procesoActivo}
                    ultimosSyncs={ultimosSyncs}
                    syncLogId={syncLogId}
                    onSyncStarted={(id) => setSyncLogId(id)}
                    imageImportActivo={imageImportActivo}
                    ultimosImportImagenes={ultimosImportImagenes}
                    onImportStarted={(id) => {
                        setImageImportId(id);
                        router.reload({ only: ['ultimosImportImagenes', 'imageImportActivo'] });
                    }}
                    webhookUrl={configuracion?.webhook_url}
                    eventosRecomendados={configuracion?.webhook_events || []}
                    onClose={() => {
                        setShowHerramientas(false);
                        router.reload({ only: ['productos', 'totales', 'procesoActivo', 'ultimosSyncs', 'imageImportActivo', 'ultimosImportImagenes'] });
                    }}
                />
            )}

            {showCrear && (
                <ModalCrearProducto
                    categorias={categorias}
                    onClose={() => setShowCrear(false)}
                    onCreated={(id) => {
                        setShowCrear(false);
                        reloadLista();
                        setDetalleId(id);
                    }}
                />
            )}

            {detalleId && (
                <ModalDetalleProducto
                    productoId={detalleId}
                    categorias={categorias}
                    canEdit={!!permisos.editar}
                    onClose={() => setDetalleId(null)}
                    onChanged={reloadLista}
                />
            )}
        </AppLayout>
    );
}
