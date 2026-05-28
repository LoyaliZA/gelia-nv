import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Package, Plus, Download, Eye, Wrench, ImageIcon, BookOpen, ChevronLeft, ChevronRight } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import FiltrosActivos from './Partials/FiltrosActivos';
import ModalFormActivo from './Partials/ModalFormActivo';
import PanelAlertas from './Partials/PanelAlertas';
import GuiaVisualActivos from './Partials/GuiaVisualActivos';
import TarjetaActivoMobile from './Partials/TarjetaActivoMobile';
import { ESTADO_BADGE, ESTADO_LABELS, ESTILOS_PAGINA, getActivosCardClass } from './Partials/activosFormStyles';

function fotoPrincipal(activo) {
    const fotos = activo.fotos || [];
    return fotos.find((f) => f.es_principal) || fotos[0];
}

function urlFotoActivo(foto) {
    return foto?.url || (foto?.ruta ? `/storage/${foto.ruta}` : null);
}

function tieneMantenimientoActivo(activo) {
    return (activo.mantenimiento_activo?.length || activo.mantenimientoActivo?.length || 0) > 0
        || activo.estado === 'mantenimiento';
}

function PaginacionActivos({ activos, filtros, onIrAPagina }) {
    const paginaActual = activos.current_page;
    const totalPaginas = activos.last_page;
    if (totalPaginas <= 1) return null;

    const generarPaginas = () => {
        const paginas = [];
        if (totalPaginas <= 7) {
            for (let i = 1; i <= totalPaginas; i++) paginas.push(i);
        } else {
            paginas.push(1);
            if (paginaActual > 3) paginas.push('...');
            for (let i = Math.max(2, paginaActual - 1); i <= Math.min(totalPaginas - 1, paginaActual + 1); i++) {
                paginas.push(i);
            }
            if (paginaActual < totalPaginas - 2) paginas.push('...');
            paginas.push(totalPaginas);
        }
        return paginas;
    };

    const desde = activos.from || 0;
    const hasta = activos.to || 0;
    const total = activos.total || activos.data.length;

    return (
        <div className="border-t theme-border p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                Viendo {desde} al {hasta} de {total.toLocaleString('es-MX')}
            </span>
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    onClick={() => onIrAPagina(paginaActual - 1)}
                    disabled={paginaActual <= 1}
                    className="paginacion-btn theme-surface border theme-border theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]"
                >
                    <ChevronLeft className="w-4 h-4" />
                </button>
                {generarPaginas().map((p, i) => (
                    p === '...' ? (
                        <span key={`dots-${i}`} className="w-10 text-center text-[11px] font-black theme-text-muted">…</span>
                    ) : (
                        <button
                            key={p}
                            type="button"
                            onClick={() => onIrAPagina(p)}
                            className={`paginacion-btn theme-border ${p === paginaActual ? '' : 'theme-surface theme-text-main hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]'}`}
                            style={p === paginaActual ? { backgroundColor: 'var(--color-primario)', color: '#fff', borderColor: 'var(--color-primario)' } : {}}
                        >
                            {p}
                        </button>
                    )
                ))}
                <button
                    type="button"
                    onClick={() => onIrAPagina(paginaActual + 1)}
                    disabled={paginaActual >= totalPaginas}
                    className="paginacion-btn theme-surface border theme-border theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]"
                >
                    <ChevronRight className="w-4 h-4" />
                </button>
            </div>
        </div>
    );
}

function FilaActivoDesktop({ activo }) {
    const foto = fotoPrincipal(activo);
    const urlFoto = urlFotoActivo(foto);
    const mantenimiento = tieneMantenimientoActivo(activo);

    return (
        <tr className="border-b theme-border hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
            <td className="px-4 py-3">
                <div className="w-10 h-10 rounded-lg overflow-hidden border theme-border bg-black/5 flex items-center justify-center">
                    {urlFoto ? (
                        <img src={urlFoto} alt="" className="w-full h-full object-cover" />
                    ) : (
                        <ImageIcon className="w-4 h-4 theme-text-muted opacity-40" />
                    )}
                </div>
            </td>
            <td className="px-4 py-3 text-xs font-mono font-bold theme-text-main">{activo.folio}</td>
            <td className="px-4 py-3 text-sm font-bold theme-text-main">{activo.nombre}</td>
            <td className="px-4 py-3 text-xs theme-text-muted">
                {activo.atributos?.marca || '—'}
                {activo.atributos?.modelo && <span className="block">{activo.atributos.modelo}</span>}
            </td>
            <td className="px-4 py-3 text-xs theme-text-muted">{activo.tipo?.nombre}</td>
            <td className="px-4 py-3 text-xs">
                {activo.responsable ? (
                    <Link href={route('activos.index', { responsable_user_id: activo.responsable.id })} className="font-bold hover:underline" style={{ color: 'var(--color-primario)' }}>
                        {activo.responsable.name}
                    </Link>
                ) : (
                    <span className="theme-text-muted italic">Sin asignar</span>
                )}
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-1 flex-wrap">
                    <span className={`px-2 py-1 rounded-lg text-[10px] font-black uppercase ${ESTADO_BADGE[activo.estado] || ''}`}>
                        {ESTADO_LABELS[activo.estado] || activo.estado}
                    </span>
                    {mantenimiento && <Wrench className="w-3 h-3 text-amber-500" title="Mantenimiento" />}
                </div>
            </td>
            <td className="px-4 py-3">
                <Link href={route('activos.show', activo.id)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase" style={{ color: 'var(--color-primario)' }}>
                    <Eye className="w-3 h-3" /> Ver
                </Link>
            </td>
        </tr>
    );
}

export default function Index({ auth, activos, tipos, departamentos, usuarios, filtros, alertasResumen, alertas }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [guiaOculta, setGuiaOculta] = useState(() => {
        if (typeof window === 'undefined') return false;
        return localStorage.getItem('activos_guia_oculta') === '1';
    });
    const [guiaKey, setGuiaKey] = useState(0);

    const can = (permiso) => {
        const roles = auth?.user?.roles || [];
        const isAdmin = roles.includes('Admin') || roles.includes('Super admin (admin)') || roles.includes('Super Admin');
        return auth?.user?.permissions?.includes(permiso) || isAdmin;
    };

    const exportar = () => {
        const params = new URLSearchParams(Object.entries(filtros || {}).filter(([, v]) => v));
        window.location.href = `${route('activos.exportar')}?${params.toString()}`;
    };

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > activos.last_page) return;
        router.get(route('activos.index'), { ...filtros, page: pagina }, { preserveState: true });
    };

    const mostrarGuiaNuevamente = () => {
        localStorage.removeItem('activos_guia_oculta');
        setGuiaOculta(false);
        setGuiaKey((k) => k + 1);
    };

    const cardHeader = getActivosCardClass({ extra: 'p-6 md:p-10 flex flex-col md:flex-row justify-between gap-4' });
    const cardTable = getActivosCardClass({ extra: 'overflow-hidden' });
    const listaVacia = activos.data.length === 0;

    return (
        <AppLayout auth={auth}>
            <Head title="Control de Activos" />
            <style>{ESTILOS_PAGINA}</style>

            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6">
                <header className={cardHeader}>
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Panel General</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                            Control de <span style={{ color: 'var(--color-primario)' }}>Activos</span>
                        </h1>
                        {(alertasResumen?.vencidos > 0 || alertasResumen?.proximos_7 > 0 || alertasResumen?.mantenimiento > 0) && (
                            <p className="text-xs theme-text-muted mt-2">
                                {alertasResumen.vencidos > 0 && `${alertasResumen.vencidos} vencidos`}
                                {alertasResumen.vencidos > 0 && alertasResumen.proximos_7 > 0 && ' · '}
                                {alertasResumen.proximos_7 > 0 && `${alertasResumen.proximos_7} por vencer (7 días)`}
                                {(alertasResumen.vencidos > 0 || alertasResumen.proximos_7 > 0) && alertasResumen.mantenimiento > 0 && ' · '}
                                {alertasResumen.mantenimiento > 0 && `${alertasResumen.mantenimiento} mantenimiento`}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2 items-center">
                        {guiaOculta && (
                            <button
                                type="button"
                                onClick={mostrarGuiaNuevamente}
                                className="flex items-center gap-2 px-4 py-3 rounded-xl theme-element border theme-border text-[10px] font-black uppercase theme-text-muted hover:theme-text-main"
                            >
                                <BookOpen className="w-4 h-4" /> Mostrar guía
                            </button>
                        )}
                        {can('activos.exportar') && (
                            <button type="button" onClick={exportar} className="flex items-center gap-2 px-4 py-3 rounded-xl theme-element border theme-border text-[10px] font-black uppercase theme-text-main">
                                <Download className="w-4 h-4" /> Exportar
                            </button>
                        )}
                        {can('activos.crear') && (
                            <button type="button" onClick={() => setModalAbierto(true)} className="flex items-center gap-2 px-5 py-3 rounded-xl text-[10px] font-black uppercase text-white" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Plus className="w-4 h-4" /> Registrar
                            </button>
                        )}
                    </div>
                </header>

                <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div className="xl:col-span-2 space-y-6">
                        <FiltrosActivos filtros={filtros} tipos={tipos} departamentos={departamentos} usuarios={usuarios} />

                        <div className="block lg:hidden space-y-4 animate-page-reveal" style={{ animationDelay: '200ms' }}>
                            {listaVacia ? (
                                <div className={getActivosCardClass({ extra: 'p-8 text-center' })}>
                                    <Package className="w-8 h-8 mx-auto mb-2 opacity-40 theme-text-muted" />
                                    <p className="theme-text-muted italic text-sm">No hay activos registrados con estos filtros.</p>
                                </div>
                            ) : (
                                activos.data.map((activo) => (
                                    <TarjetaActivoMobile
                                        key={activo.id}
                                        activo={activo}
                                        fotoUrl={urlFotoActivo(fotoPrincipal(activo))}
                                        tieneMantenimiento={tieneMantenimientoActivo(activo)}
                                    />
                                ))
                            )}
                            {!listaVacia && (
                                <PaginacionActivos activos={activos} filtros={filtros} onIrAPagina={irAPagina} />
                            )}
                        </div>

                        <div className={`hidden lg:block ${cardTable}`} style={{ animationDelay: '200ms' }}>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left min-w-[900px]">
                                    <thead>
                                        <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                            <th className="px-4 py-3 w-14"></th>
                                            <th className="px-4 py-3">Folio</th>
                                            <th className="px-4 py-3">Nombre</th>
                                            <th className="px-4 py-3">Marca / Modelo</th>
                                            <th className="px-4 py-3">Tipo</th>
                                            <th className="px-4 py-3">Pertenece a</th>
                                            <th className="px-4 py-3">Estado</th>
                                            <th className="px-4 py-3">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {listaVacia ? (
                                            <tr>
                                                <td colSpan={8} className="px-4 py-12 text-center theme-text-muted italic">
                                                    <Package className="w-8 h-8 mx-auto mb-2 opacity-40" />
                                                    No hay activos registrados con estos filtros.
                                                </td>
                                            </tr>
                                        ) : activos.data.map((activo) => (
                                            <FilaActivoDesktop key={activo.id} activo={activo} />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <PaginacionActivos activos={activos} filtros={filtros} onIrAPagina={irAPagina} />
                        </div>
                    </div>

                    <div className="space-y-6">
                        {!guiaOculta && (
                            <GuiaVisualActivos
                                key={guiaKey}
                                onOcultar={() => setGuiaOculta(true)}
                            />
                        )}
                        <PanelAlertas alertas={alertas} />
                    </div>
                </div>
            </div>

            <ModalFormActivo abierto={modalAbierto} onCerrar={() => setModalAbierto(false)} tipos={tipos} departamentos={departamentos} />
        </AppLayout>
    );
}
