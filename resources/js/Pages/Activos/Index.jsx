import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Package, Plus, Download, Eye, Wrench, ImageIcon, BookOpen, Settings, PenTool } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import FiltrosActivos from './Partials/FiltrosActivos';
import ModalFormActivo from './Partials/ModalFormActivo';
import ModalConfigActivos from './Partials/ModalConfigActivos';
import ModalFirmarActivo from './Partials/ModalFirmarActivo';
import PanelAlertas from './Partials/PanelAlertas';
import GuiaVisualActivos from './Partials/GuiaVisualActivos';
import TarjetaActivoMobile from './Partials/TarjetaActivoMobile';
import ResumenAlertasActivos from './Partials/ResumenAlertasActivos';
import { ESTADO_BADGE, ESTADO_LABELS, getActivosCardClass, BTN_PRIMARY_CLASS, BTN_SECONDARY_CLASS, FAB_CLASS } from './Partials/activosFormStyles';

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

function FilaActivoDesktop({ activo }) {
    const foto = fotoPrincipal(activo);
    const urlFoto = urlFotoActivo(foto);
    const mantenimiento = tieneMantenimientoActivo(activo);

    return (
        <tr className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
            <td className="px-4 py-4">
                <div className="w-10 h-10 rounded-lg overflow-hidden border theme-border theme-element flex items-center justify-center shrink-0">
                    {urlFoto ? (
                        <img src={urlFoto} alt="" className="w-full h-full object-cover" />
                    ) : (
                        <ImageIcon className="w-4 h-4 theme-text-muted opacity-40" />
                    )}
                </div>
            </td>
            <td className="px-4 py-4 text-xs font-mono font-bold theme-text-main whitespace-nowrap">{activo.folio}</td>
            <td className="px-4 py-4 text-sm font-bold theme-text-main min-w-[120px]">{activo.nombre}</td>
            <td className="px-4 py-4 text-xs theme-text-muted min-w-[100px]">
                {activo.atributos?.marca || '—'}
                {activo.atributos?.modelo && <span className="block">{activo.atributos.modelo}</span>}
            </td>
            <td className="px-4 py-4 text-xs theme-text-muted whitespace-nowrap">{activo.tipo?.nombre}</td>
            <td className="px-4 py-4 text-xs min-w-[120px]">
                {activo.responsable ? (
                    <Link href={route('activos.index', { responsable_user_id: activo.responsable.id })} className="font-bold hover:underline" style={{ color: 'var(--color-primario)' }}>
                        {activo.responsable.name}
                    </Link>
                ) : (
                    <span className="theme-text-muted italic">Sin asignar</span>
                )}
            </td>
            <td className="px-4 py-4 whitespace-nowrap">
                <div className="flex items-center gap-1.5 flex-wrap">
                    <span className={`px-2 py-1 rounded-lg text-[10px] font-black uppercase ${ESTADO_BADGE[activo.estado] || ''}`}>
                        {ESTADO_LABELS[activo.estado] || activo.estado}
                    </span>
                    {mantenimiento && <Wrench className="w-3.5 h-3.5 text-amber-500 shrink-0" title="Mantenimiento" />}
                </div>
            </td>
            <td className="px-4 py-4 text-right whitespace-nowrap">
                <Link href={route('activos.show', activo.id)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase" style={{ color: 'var(--color-primario)' }}>
                    <Eye className="w-3.5 h-3.5" /> Ver
                </Link>
            </td>
        </tr>
    );
}

export default function Index({ auth, activos, tipos, departamentos, usuarios, filtros, colaboradorAsignaciones, alertasResumen, alertas, terminosCondiciones }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalConfigAbierto, setModalConfigAbierto] = useState(false);
    const [modalBulkFirmar, setModalBulkFirmar] = useState(null);
    const [guiaOculta, setGuiaOculta] = useState(() => {
        if (typeof window === 'undefined') return false;
        return localStorage.getItem('activos_guia_oculta') === '1';
    });
    const [guiaKey, setGuiaKey] = useState(0);

    useEffect(() => {
        if (typeof window === 'undefined') return;
        const params = new URLSearchParams(window.location.search);
        const paramsObj = Object.fromEntries(params.entries());
        const hasActiveParams = Object.keys(paramsObj).some(k => k !== 'page' && paramsObj[k] !== '');

        if (!hasActiveParams) {
            const guardadosRaw = sessionStorage.getItem('activos_filtros_guardados');
            if (guardadosRaw) {
                try {
                    const guardados = JSON.parse(guardadosRaw);
                    if (Object.keys(guardados).length > 0) {
                        router.replace(route('activos.index'), { data: guardados, replace: true });
                        return;
                    }
                } catch (e) {
                    // ignore
                }
            }
        }

        const aGuardar = {};
        for (const [k, v] of Object.entries(filtros || {})) {
            if (v !== '' && v !== null && v !== undefined) {
                aGuardar[k] = v;
            }
        }
        if (Object.keys(aGuardar).length > 0) {
            sessionStorage.setItem('activos_filtros_guardados', JSON.stringify(aGuardar));
        }
    }, [filtros]);

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
        router.get(route('activos.index'), { ...filtros, page: pagina }, { preserveState: true, preserveScroll: true });
    };

    const mostrarGuiaNuevamente = () => {
        localStorage.removeItem('activos_guia_oculta');
        setGuiaOculta(false);
        setGuiaKey((k) => k + 1);
    };

    const cardHeader = getActivosCardClass('p-6 md:p-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6');
    const cardListado = getActivosCardClass('overflow-hidden');
    const listaVacia = activos.data.length === 0;

    return (
        <AppLayout auth={auth}>
            <Head title="Control de Activos" />
            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className={cardHeader}>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>Panel General</p>
                        </div>
                        <h1 className="text-2xl sm:text-3xl md:text-5xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                            Control de <span style={{ color: 'var(--color-primario)' }}>Activos</span>
                        </h1>
                        {(alertasResumen?.vencidos > 0 || alertasResumen?.proximos_7 > 0 || alertasResumen?.mantenimiento > 0) && (
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                                {alertasResumen.vencidos > 0 && `${alertasResumen.vencidos} vencidos`}
                                {alertasResumen.vencidos > 0 && alertasResumen.proximos_7 > 0 && ' · '}
                                {alertasResumen.proximos_7 > 0 && `${alertasResumen.proximos_7} por vencer (7 días)`}
                                {(alertasResumen.vencidos > 0 || alertasResumen.proximos_7 > 0) && alertasResumen.mantenimiento > 0 && ' · '}
                                {alertasResumen.mantenimiento > 0 && `${alertasResumen.mantenimiento} en mantenimiento`}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2 items-center w-full md:w-auto shrink-0">
                        {guiaOculta && (
                            <button
                                type="button"
                                onClick={mostrarGuiaNuevamente}
                                className={`${BTN_SECONDARY_CLASS} theme-btn-primary--compact`}
                            >
                                <BookOpen className="w-4 h-4 shrink-0" /> Mostrar guía
                            </button>
                        )}
                        {can('activos.exportar') && (
                            <button type="button" onClick={exportar} className={`${BTN_SECONDARY_CLASS} theme-btn-primary--compact`}>
                                <Download className="w-4 h-4 shrink-0" /> Exportar
                            </button>
                        )}
                        {can('activos.configurar_tipos') && (
                            <button type="button" onClick={() => setModalConfigAbierto(true)} className={`${BTN_SECONDARY_CLASS} theme-btn-primary--compact`}>
                                <Settings className="w-4 h-4 shrink-0" /> Configurar
                            </button>
                        )}
                        {can('activos.crear') && (
                            <button type="button" onClick={() => setModalAbierto(true)} className={`${BTN_PRIMARY_CLASS} theme-btn-primary--compact`}>
                                <Plus className="w-4 h-4 shrink-0" /> Registrar
                            </button>
                        )}
                    </div>
                </header>

                <div className="grid grid-cols-1 xl:grid-cols-3 gap-6 md:gap-8">
                    <div className="xl:col-span-2 min-w-0 space-y-6 md:space-y-8">
                        <ResumenAlertasActivos alertasResumen={alertasResumen} alertas={alertas} />

                        {(() => {
                            const userId = filtros.mis_activos ? auth.user?.id : filtros.responsable_user_id;
                            const activeAsignaciones = colaboradorAsignaciones || [];
                            const pendientesFirma = activeAsignaciones.filter(a => !a.firmado);

                            if (!userId || activeAsignaciones.length === 0) return null;

                            return (
                                <div className={getActivosCardClass('p-5 border border-[var(--color-primario)]/30 bg-[var(--color-primario)]/[0.01] flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 animate-page-reveal')}>
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <span className="h-1.5 w-1.5 rounded-full bg-[var(--color-primario)]" />
                                            <h3 className="text-[10px] font-black uppercase tracking-widest text-[var(--color-primario)] m-0">
                                                Entrega en Conjunto / Asignaciones Colectivas
                                            </h3>
                                        </div>
                                        <p className="text-xs font-bold theme-text-main m-0">
                                            El colaborador tiene {activeAsignaciones.length} equipo{activeAsignaciones.length !== 1 ? 's' : ''} asignado{activeAsignaciones.length !== 1 ? 's' : ''}.
                                            {pendientesFirma.length > 0 && (
                                                <span className="text-amber-600 dark:text-amber-400 font-black uppercase text-[9px] ml-1.5 px-2 py-0.5 rounded bg-amber-500/10">
                                                    Hay {pendientesFirma.length} pendiente{pendientesFirma.length !== 1 ? 's' : ''} de firma
                                                </span>
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2 items-center w-full sm:w-auto">
                                        {pendientesFirma.length > 0 && (
                                            <button
                                                type="button"
                                                onClick={() => setModalBulkFirmar(pendientesFirma)}
                                                className={`${BTN_PRIMARY_CLASS} theme-btn-primary--compact`}
                                            >
                                                <PenTool className="w-3.5 h-3.5 shrink-0" />
                                                Firmar {pendientesFirma.length} Pendiente{pendientesFirma.length !== 1 ? 's' : ''}
                                            </button>
                                        )}
                                        <a
                                            href={route('activos.usuarios.responsiva_conjunta', userId)}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className={`${BTN_SECONDARY_CLASS} theme-btn-primary--compact inline-flex items-center`}
                                        >
                                            <Download className="w-3.5 h-3.5 shrink-0" />
                                            Responsiva Completa
                                        </a>
                                    </div>
                                </div>
                            );
                        })()}

                        <FiltrosActivos filtros={filtros} tipos={tipos} departamentos={departamentos} usuarios={usuarios} />

                        <div className={`lg:hidden ${cardListado}`}>
                            <div className="p-4 md:p-6 space-y-4">
                                {listaVacia ? (
                                    <div className="py-12 text-center">
                                        <Package className="w-10 h-10 mx-auto mb-3 opacity-40 theme-text-muted" />
                                        <p className="theme-text-muted italic text-sm m-0">No hay activos registrados con estos filtros.</p>
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
                            </div>
                            {!listaVacia && (
                                <GeliaPaginacion paginator={activos} onIrAPagina={irAPagina} embedded />
                            )}
                        </div>

                        <div className={`hidden lg:block ${cardListado}`}>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left min-w-[900px] border-collapse">
                                    <thead>
                                        <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                            <th className="px-4 py-4 w-14" />
                                            <th className="px-4 py-4">Folio</th>
                                            <th className="px-4 py-4">Nombre</th>
                                            <th className="px-4 py-4">Marca / Modelo</th>
                                            <th className="px-4 py-4">Tipo</th>
                                            <th className="px-4 py-4">Pertenece a</th>
                                            <th className="px-4 py-4">Estado</th>
                                            <th className="px-4 py-4 text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {listaVacia ? (
                                            <tr>
                                                <td colSpan={8} className="px-4 py-16 text-center theme-text-muted italic">
                                                    <Package className="w-10 h-10 mx-auto mb-3 opacity-40" />
                                                    No hay activos registrados con estos filtros.
                                                </td>
                                            </tr>
                                        ) : (
                                            activos.data.map((activo) => (
                                                <FilaActivoDesktop key={activo.id} activo={activo} />
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <GeliaPaginacion paginator={activos} onIrAPagina={irAPagina} embedded />
                        </div>
                    </div>

                    <aside className="min-w-0 space-y-6 md:space-y-8">
                        {!guiaOculta && (
                            <GuiaVisualActivos
                                key={guiaKey}
                                onOcultar={() => setGuiaOculta(true)}
                            />
                        )}
                        <PanelAlertas alertas={alertas} />
                    </aside>
                </div>
            </div>

            <ModalFormActivo abierto={modalAbierto} onCerrar={() => setModalAbierto(false)} tipos={tipos} departamentos={departamentos} />
            <ModalConfigActivos abierto={modalConfigAbierto} onCerrar={() => setModalConfigAbierto(false)} terminosCondiciones={terminosCondiciones} />
            <ModalFirmarActivo abierto={!!modalBulkFirmar} onCerrar={() => setModalBulkFirmar(null)} asignacion={modalBulkFirmar} terminosCondiciones={terminosCondiciones} />

            {can('activos.crear') && (
                <button
                    type="button"
                    onClick={() => setModalAbierto(true)}
                    className={FAB_CLASS}
                    style={{ backgroundColor: 'var(--color-primario)' }}
                    aria-label="Registrar activo"
                >
                    <Plus className="w-5 h-5" /> Registrar
                </button>
            )}
        </AppLayout>
    );
}
