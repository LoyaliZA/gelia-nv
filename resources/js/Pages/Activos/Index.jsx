import React, { useState, useEffect, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Package, Plus, Download, Eye, Settings, PenTool, BookOpen, Bell, Tag } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import { NotificationCountBadge } from '../../Components/NotificationBell';
import FiltrosActivos from './Partials/FiltrosActivos';
import ModalFormActivo from './Partials/ModalFormActivo';
import ModalVistaPreviaResponsiva from './Partials/ModalVistaPreviaResponsiva';
import ModalConfigActivos from './Partials/ModalConfigActivos';
import ModalFirmarActivo from './Partials/ModalFirmarActivo';
import DrawerAlertasActivos from './Partials/DrawerAlertasActivos';
import GuiaVisualActivos from './Partials/GuiaVisualActivos';
import TarjetaActivoCard from './Partials/TarjetaActivoCard';
import { totalAlertasResumen } from './Partials/ListadoAlertasActivos';
import { getActivosCardClass, BTN_PRIMARY_CLASS, BTN_SECONDARY_CLASS } from './Partials/activosFormStyles';
import {
    leerFiltrosActivosGuardados,
    navegarListadoActivos,
    paramsFiltrosActivos,
    STORAGE_FILTROS_ACTIVOS,
} from './Partials/navegarListadoActivos';

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

export default function Index({ auth, activos, tipos, categorias = [], departamentos, usuarios, filtros, colaboradorAsignaciones, alertasResumen, alertas, terminosCondiciones }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalConfigAbierto, setModalConfigAbierto] = useState(false);
    const [modalBulkFirmar, setModalBulkFirmar] = useState(null);
    const [modalAlertasAbierto, setModalAlertasAbierto] = useState(false);
    const [previewResponsiva, setPreviewResponsiva] = useState(null);
    const [guiaOculta, setGuiaOculta] = useState(() => {
        if (typeof window === 'undefined') return false;
        return localStorage.getItem('activos_guia_oculta') === '1';
    });
    const [guiaKey, setGuiaKey] = useState(0);
    const filtrosRestaurados = useRef(false);

    useEffect(() => {
        if (typeof window === 'undefined') return;
        const params = new URLSearchParams(window.location.search);
        const paramsObj = Object.fromEntries(params.entries());
        const hasActiveParams = Object.keys(paramsObj).some(k => k !== 'page' && paramsObj[k] !== '');

        if (!filtrosRestaurados.current) {
            filtrosRestaurados.current = true;
            if (!hasActiveParams) {
                const guardados = leerFiltrosActivosGuardados();
                const params = paramsFiltrosActivos(guardados);
                if (Object.keys(params).length > 0) {
                    navegarListadoActivos({ replace: true, preserveState: true });
                    return;
                }
            }
        }

        const aGuardar = paramsFiltrosActivos(filtros || {});
        if (Object.keys(aGuardar).length > 0 || hasActiveParams) {
            sessionStorage.setItem(STORAGE_FILTROS_ACTIVOS, JSON.stringify(aGuardar));
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
        router.get(route('activos.index'), { ...filtros, page: pagina }, {
            preserveState: true,
            preserveScroll: true,
            showProgress: false,
        });
    };

    const mostrarGuiaNuevamente = () => {
        localStorage.removeItem('activos_guia_oculta');
        setGuiaOculta(false);
        setGuiaKey((k) => k + 1);
    };

    const totalAlertas = totalAlertasResumen(alertasResumen);
    const cardHeader = getActivosCardClass('p-6 md:p-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6');
    const cardListado = getActivosCardClass('overflow-hidden');
    const listaVacia = activos.data.length === 0;

    return (
        <AppLayout auth={auth}>
            <Head title="Control de Activos" />
            <div className="max-w-[1920px] w-full mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className={cardHeader}>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>Panel General</p>
                        </div>
                        <h1 className="text-2xl sm:text-3xl md:text-5xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                            Control de <span style={{ color: 'var(--color-primario)' }}>Activos</span>
                        </h1>
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
                        <button
                            type="button"
                            onClick={() => setModalAlertasAbierto(true)}
                            className={`${BTN_SECONDARY_CLASS} theme-btn-primary--compact relative overflow-visible`}
                            aria-label={`Alertas activas${totalAlertas > 0 ? `: ${totalAlertas}` : ''}`}
                        >
                            <Bell className="w-4 h-4 shrink-0" />
                            Alertas
                            <NotificationCountBadge count={totalAlertas} className="-top-2 -right-2" />
                        </button>
                        {can('activos.exportar') && (
                            <>
                                <Link href={route('activos.etiquetas')} className={`${BTN_SECONDARY_CLASS} theme-btn-primary--compact inline-flex items-center gap-2`}>
                                    <Tag className="w-4 h-4 shrink-0" /> Etiquetas
                                </Link>
                                <button type="button" onClick={exportar} className={`${BTN_SECONDARY_CLASS} theme-btn-primary--compact`}>
                                    <Download className="w-4 h-4 shrink-0" /> Exportar
                                </button>
                            </>
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

                <div className="space-y-6 md:space-y-8">
                    {!guiaOculta && (
                        <GuiaVisualActivos
                            key={guiaKey}
                            onOcultar={() => setGuiaOculta(true)}
                        />
                    )}

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
                                    <button
                                        type="button"
                                        onClick={() => setPreviewResponsiva({
                                            previewUrl: route('activos.usuarios.responsiva_conjunta_vista_previa', userId),
                                            downloadUrl: route('activos.usuarios.responsiva_conjunta', userId),
                                            titulo: 'Vista previa — Responsiva completa',
                                        })}
                                        className={`${BTN_SECONDARY_CLASS} theme-btn-primary--compact inline-flex items-center`}
                                    >
                                        <Eye className="w-3.5 h-3.5 shrink-0" />
                                        Vista previa responsiva
                                    </button>
                                </div>
                            </div>
                        );
                    })()}

                    <FiltrosActivos filtros={filtros} tipos={tipos} categorias={categorias} departamentos={departamentos} usuarios={usuarios} />

                    <div className={cardListado}>
                        <div className="p-4 md:p-6">
                            {listaVacia ? (
                                <div className="py-16 text-center">
                                    <Package className="w-10 h-10 mx-auto mb-3 opacity-40 theme-text-muted" />
                                    <p className="theme-text-muted italic text-sm m-0">No hay activos registrados con estos filtros.</p>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 md:gap-5">
                                    {activos.data.map((activo) => (
                                        <TarjetaActivoCard
                                            key={activo.id}
                                            activo={activo}
                                            fotoUrl={urlFotoActivo(fotoPrincipal(activo))}
                                            tieneMantenimiento={tieneMantenimientoActivo(activo)}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                        {!listaVacia && (
                            <GeliaPaginacion paginator={activos} onIrAPagina={irAPagina} embedded />
                        )}
                    </div>
                </div>
            </div>

            <DrawerAlertasActivos
                abierto={modalAlertasAbierto}
                onCerrar={() => setModalAlertasAbierto(false)}
                alertas={alertas}
                alertasResumen={alertasResumen}
            />

            <ModalFormActivo abierto={modalAbierto} onCerrar={() => setModalAbierto(false)} tipos={tipos} categorias={categorias} departamentos={departamentos} />
            <ModalVistaPreviaResponsiva
                abierto={!!previewResponsiva}
                onCerrar={() => setPreviewResponsiva(null)}
                previewUrl={previewResponsiva?.previewUrl}
                downloadUrl={previewResponsiva?.downloadUrl}
                titulo={previewResponsiva?.titulo}
            />
            <ModalConfigActivos abierto={modalConfigAbierto} onCerrar={() => setModalConfigAbierto(false)} terminosCondiciones={terminosCondiciones} />
            <ModalFirmarActivo abierto={!!modalBulkFirmar} onCerrar={() => setModalBulkFirmar(null)} asignacion={modalBulkFirmar} terminosCondiciones={terminosCondiciones} />
        </AppLayout>
    );
}
