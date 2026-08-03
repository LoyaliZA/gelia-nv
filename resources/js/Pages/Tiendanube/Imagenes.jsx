import React, { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { geliaCardClass } from '../../utils/geliaTheme';
import { ArrowLeft, Images, Search } from 'lucide-react';
import { inertiaVisitUrl } from '../../utils/inertiaVisitUrl';
import SeccionImagenes from './Partials/SeccionImagenes';

export default function Imagenes({
    auth,
    configuracion,
    productos,
    totales,
    imageImportActivo,
    ultimosImportImagenes = [],
    filters,
    permisos,
}) {
    const [search, setSearch] = useState(filters?.search || '');
    const [filtroAlerta, setFiltroAlerta] = useState(!!filters?.imagenes_alerta);
    const [filtroSinImagen, setFiltroSinImagen] = useState(!!filters?.sin_imagen);
    const [imageImportId, setImageImportId] = useState(imageImportActivo?.id || null);

    useEffect(() => {
        setFiltroAlerta(!!filters?.imagenes_alerta);
        setFiltroSinImagen(!!filters?.sin_imagen);
        setSearch(filters?.search || '');
    }, [filters?.imagenes_alerta, filters?.sin_imagen, filters?.search]);

    useEffect(() => {
        if (imageImportActivo?.id) {
            setImageImportId(imageImportActivo.id);
        }
    }, [imageImportActivo?.id]);

    useEffect(() => {
        if (!imageImportId) return undefined;
        let cancelled = false;
        const terminal = (estado) => estado && !['pendiente', 'en_proceso'].includes(estado);

        const poll = async () => {
            try {
                const res = await fetch(route('tiendanube.imagenes.importar.progreso', imageImportId), {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (!cancelled && terminal(data.estado)) {
                    setImageImportId(null);
                    router.reload({ only: ['productos', 'totales', 'imageImportActivo', 'ultimosImportImagenes'] });
                }
            } catch {
                // ignore
            }
        };

        poll();
        const id = setInterval(poll, 2500);
        return () => {
            cancelled = true;
            clearInterval(id);
        };
    }, [imageImportId]);

    const aplicarFiltros = (overrides = {}) => {
        const alerta = Object.prototype.hasOwnProperty.call(overrides, 'imagenes_alerta')
            ? overrides.imagenes_alerta
            : filtroAlerta;
        const sinImg = Object.prototype.hasOwnProperty.call(overrides, 'sin_imagen')
            ? overrides.sin_imagen
            : filtroSinImagen;
        router.get(
            route('tiendanube.imagenes.index'),
            {
                search: (overrides.search !== undefined ? overrides.search : search) || undefined,
                imagenes_alerta: alerta ? 1 : undefined,
                sin_imagen: sinImg ? 1 : undefined,
            },
            { preserveState: true }
        );
    };

    const buscar = (e) => {
        e.preventDefault();
        aplicarFiltros();
    };

    const reloadLista = () => router.reload({ only: ['productos', 'totales', 'ultimosImportImagenes', 'imageImportActivo'] });

    const rows = productos?.data || [];

    const irAPagina = (url) => {
        const href = inertiaVisitUrl(url);
        if (!href) return;
        router.get(href, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Imágenes Tiendanube" />

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className={`${geliaCardClass()} p-6 md:p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-4`}>
                    <div>
                        <button
                            type="button"
                            onClick={() => router.visit(route('tiendanube.index'))}
                            className="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main mb-3"
                        >
                            <ArrowLeft className="w-4 h-4" /> Volver al catálogo
                        </button>
                        <div className="flex items-center gap-3">
                            <Images className="w-8 h-8" style={{ color: 'var(--color-primario)' }} />
                            <div>
                                <h1 className="text-2xl md:text-3xl font-black italic uppercase theme-text-main m-0">
                                    Gestionar imágenes
                                </h1>
                                <p className="text-xs theme-text-muted mt-1">
                                    Catálogo visual, carga individual/ZIP y revisión de medidas.
                                    {configuracion?.store_name ? ` · ${configuracion.store_name}` : ''}
                                </p>
                            </div>
                        </div>
                    </div>
                </header>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className={`${geliaCardClass()} p-4`}>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Productos</p>
                        <p className="text-2xl font-black theme-text-main">{totales?.productos ?? 0}</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => {
                            const next = !filtroSinImagen;
                            setFiltroSinImagen(next);
                            if (next) setFiltroAlerta(false);
                            aplicarFiltros({ sin_imagen: next, imagenes_alerta: next ? false : filtroAlerta });
                        }}
                        className={`${geliaCardClass()} p-4 text-left ${filtroSinImagen ? 'ring-2 ring-zinc-400' : ''}`}
                    >
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Sin imagen</p>
                        <p className="text-2xl font-black theme-text-main">{totales?.sin_imagen ?? 0}</p>
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            const next = !filtroAlerta;
                            setFiltroAlerta(next);
                            if (next) setFiltroSinImagen(false);
                            aplicarFiltros({ imagenes_alerta: next, sin_imagen: next ? false : filtroSinImagen });
                        }}
                        className={`${geliaCardClass()} p-4 text-left ${filtroAlerta ? 'ring-2 ring-amber-500/60' : ''}`}
                    >
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">A revisar</p>
                        <p className="text-2xl font-black text-amber-600">{totales?.productos_alerta_imagenes ?? 0}</p>
                    </button>
                </div>

                <SeccionImagenes
                    permisos={permisos}
                    credencialesOk={!!configuracion?.credenciales_configuradas}
                    imageImportActivo={imageImportActivo}
                    ultimosImportImagenes={ultimosImportImagenes}
                    onImportStarted={(id) => {
                        setImageImportId(id);
                        router.reload({ only: ['ultimosImportImagenes', 'imageImportActivo'] });
                    }}
                    onChanged={reloadLista}
                />

                <div className={`${geliaCardClass()} p-4 md:p-6 space-y-4`}>
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main">
                            Catálogo de imágenes
                        </h2>
                        <form onSubmit={buscar} className="flex gap-2 w-full sm:w-auto">
                            <div className="flex items-center flex-1 theme-element border theme-border rounded-xl px-3">
                                <Search className="w-4 h-4 theme-text-muted" />
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="ID, SKU, marca…"
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

                    {rows.length === 0 ? (
                        <p className="text-sm theme-text-muted py-8 text-center">No hay productos con estos filtros.</p>
                    ) : (
                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                            {rows.map((p) => (
                                <div
                                    key={p.id}
                                    className="rounded-xl border theme-border overflow-hidden bg-black/[0.02] dark:bg-white/[0.02]"
                                >
                                    <div className="aspect-square bg-zinc-100 dark:bg-zinc-800 relative">
                                        {p.imagen ? (
                                            <img src={p.imagen} alt="" className="w-full h-full object-cover" />
                                        ) : (
                                            <div className="w-full h-full flex items-center justify-center text-[10px] font-black uppercase theme-text-muted">
                                                Sin imagen
                                            </div>
                                        )}
                                        {(p.tiene_alerta_imagenes || p.requiere_revision) && (
                                            <span className="absolute top-1.5 left-1.5 text-[8px] font-black uppercase px-1.5 py-0.5 rounded bg-amber-500 text-white">
                                                Revisar
                                            </span>
                                        )}
                                    </div>
                                    <div className="p-2 space-y-0.5">
                                        <p className="text-[11px] font-bold theme-text-main line-clamp-2 leading-tight">{p.nombre || '—'}</p>
                                        <p className="text-[9px] font-mono theme-text-muted">{p.sku || `#${p.id}`}</p>
                                        <p className="text-[9px] font-mono theme-text-muted">
                                            {p.width && p.height ? `${p.width}×${p.height}` : 'sin medida'}
                                            {p.num_imagenes > 1 ? ` · ${p.num_imagenes} imgs` : ''}
                                        </p>
                                        {(p.alerta_pequena || p.alerta_no_cuadrada) && (
                                            <p className="text-[8px] font-bold uppercase text-amber-600">
                                                {[
                                                    p.alerta_pequena ? '<800' : null,
                                                    p.alerta_no_cuadrada ? 'no cuadrada' : null,
                                                ].filter(Boolean).join(' · ')}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {productos?.links && (
                        <div className="flex flex-wrap gap-2 justify-center pt-2">
                            {productos.links.map((link, i) => (
                                <button
                                    key={i}
                                    type="button"
                                    disabled={!link.url}
                                    onClick={() => irAPagina(link.url)}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-bold border theme-border ${
                                        link.active ? 'text-white' : 'theme-text-muted'
                                    } ${!link.url ? 'opacity-40 cursor-not-allowed' : ''}`}
                                    style={link.active ? { backgroundColor: 'var(--color-primario)' } : {}}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
