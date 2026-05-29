import React, { useState, useCallback, useMemo, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Head, router } from '@inertiajs/react';
import {
    Ban, CheckCircle2, AlertOctagon, CheckSquare, History, Trash2, XCircle,
    FileSpreadsheet, Download, Plus, Loader2,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import FiltrosOperativas from './Partials/FiltrosOperativas';
import TarjetaOperativa from './Partials/TarjetaOperativa';
import ModalFormOperativa from './Partials/ModalFormOperativa';
import ModalRespuestaOperativa from './Partials/ModalRespuestaOperativa';
import ModalSolicitarCancelacion from './Partials/ModalSolicitarCancelacion';
import ModalConfirmarCancelacion from './Partials/ModalConfirmarCancelacion';
import ModalBitacoraSolicitud from '../Solicitudes/Partials/ModalBitacoraSolicitud';
import { BTN_PRIMARY, BTN_SECONDARY } from './Partials/operativasStyles';
import { filtrarSolicitudesPorTab } from './Partials/operativasFiltros';
import { geliaCardClass } from '../../utils/geliaTheme';

const OPCIONES_LISTADO = {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    showProgress: false,
    only: ['solicitudes', 'filtros'],
};

const MenuAcciones = ({
    menuAbierto, menuSolicitud, menuPos, setMenuAbierto, setModalRespuesta, setModalBitacora,
    setModalCancelacion, setModalConfirmarCancelacion, eliminarSolicitud, can, auth,
}) => {
    if (!menuAbierto || !menuSolicitud) return null;
    const solicitud = menuSolicitud;
    const esCancelada = solicitud.estado?.nombre === 'Cancelada';
    const estadosActivos = ['Pendiente', 'Respondida', 'Verificada'];
    const roles = auth?.user?.roles || [];
    const esGerente = roles.some((r) => String(r).toLowerCase().includes('gerente'));
    const puedeReportarError = (can('cancelaciones_cotizaciones.reportar') || can('cancelaciones_cotizaciones.verificar') || esGerente)
        && solicitud.estado?.nombre !== 'Incorrecta'
        && !esCancelada;

    const puedeSolicitarCancelacion = can('cancelaciones_cotizaciones.solicitar_cancelacion')
        && solicitud.vendedor_id === auth.user.id
        && estadosActivos.includes(solicitud.estado?.nombre)
        && !solicitud.cancelacion_solicitada_at
        && !esCancelada;

    const puedeConfirmarCancelacion = can('cancelaciones_cotizaciones.cancelar')
        && solicitud.cancelacion_solicitada_at
        && !esCancelada;

    return createPortal(
        <>
            <div className="fixed inset-0 z-[999]" onClick={() => setMenuAbierto(null)} />
            <div className="fixed z-[1000] theme-surface border theme-border shadow-2xl rounded-2xl p-2 flex flex-col gap-1 w-56 backdrop-blur-xl" style={{ top: menuPos.top, left: menuPos.left }}>
                {puedeSolicitarCancelacion && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalCancelacion({ abierto: true, solicitud }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none">
                        <Ban className="w-4 h-4" /> Solicitar Cancelación
                    </button>
                )}
                {puedeConfirmarCancelacion && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalConfirmarCancelacion({ abierto: true, solicitud }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none">
                        <XCircle className="w-4 h-4" /> Confirmar Cancelación
                    </button>
                )}
                {can('cancelaciones_cotizaciones.reportar') && !esCancelada && solicitud.estado?.nombre === 'Pendiente' && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: 2 }); }} className="flex items-center gap-3 px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none" style={{ color: 'var(--color-primario)' }}>
                        <CheckCircle2 className="w-4 h-4" /> Aprobar
                    </button>
                )}
                {puedeReportarError && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: 4 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none">
                        <AlertOctagon className="w-4 h-4" /> Reportar Error
                    </button>
                )}
                {can('cancelaciones_cotizaciones.verificar') && !esCancelada && solicitud.estado?.nombre === 'Respondida' && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: 3 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 text-emerald-600 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none">
                        <CheckSquare className="w-4 h-4" /> Verificado
                    </button>
                )}
                {can('configuracion.ver_auditoria') && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalBitacora({ abierto: true, solicitud }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-purple-50 dark:hover:bg-purple-500/10 text-purple-600 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none border-t theme-border mt-1 pt-3">
                        <History className="w-4 h-4" /> Ver Bitácora
                    </button>
                )}
                {can('cancelaciones_cotizaciones.eliminar') && (
                    <button type="button" onClick={() => eliminarSolicitud(solicitud.id)} className="flex items-center gap-3 px-4 py-3 hover:bg-red-900/10 text-red-600 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none border-t theme-border mt-1 pt-3">
                        <Trash2 className="w-4 h-4" /> Eliminar
                    </button>
                )}
            </div>
        </>,
        document.body
    );
};

export default function Index({ auth, solicitudes, metricas, filtros = {}, procesos = [], bancos = [], vendedores = [] }) {
    const permisos = auth?.user?.permissions || [];
    const can = (permiso) => permisos.includes(permiso) || auth?.user?.roles?.includes('Super Admin');
    const puedeCrear = can('cancelaciones_cotizaciones.crear');
    const puedeExportar = can('cancelaciones_cotizaciones.exportar');

    const [tabActiva, setTabActiva] = useState(filtros.tab || 'TODAS');
    const [tipoOperativo, setTipoOperativo] = useState(filtros.tipo_operativo || '');
    const [listaCargando, setListaCargando] = useState(false);
    const [modalForm, setModalForm] = useState({ abierto: false, procesoId: '' });
    const [modalRespuesta, setModalRespuesta] = useState({ abierto: false, solicitud: null, estadoId: null });
    const [modalBitacora, setModalBitacora] = useState({ abierto: false, solicitud: null });
    const [modalCancelacion, setModalCancelacion] = useState({ abierto: false, solicitud: null });
    const [modalConfirmarCancelacion, setModalConfirmarCancelacion] = useState({ abierto: false, solicitud: null });
    const [menuAbierto, setMenuAbierto] = useState(null);
    const [menuSolicitud, setMenuSolicitud] = useState(null);
    const [menuPos, setMenuPos] = useState({ top: 0, left: 0 });

    useEffect(() => {
        setTabActiva(filtros.tab || 'TODAS');
    }, [filtros.tab]);

    useEffect(() => {
        setTipoOperativo(filtros.tipo_operativo || '');
    }, [filtros.tipo_operativo]);

    const paramsListado = useCallback(
        (extra = {}) => {
            const p = {
                q: filtros.q || undefined,
                vendedor_id: filtros.vendedor_id || undefined,
                fecha_inicio: filtros.fecha_inicio || undefined,
                fecha_fin: filtros.fecha_fin || undefined,
                tab: tabActiva,
                tipo_operativo: tipoOperativo || undefined,
                page: 1,
                ...extra,
            };
            return Object.fromEntries(
                Object.entries(p).filter(([, v]) => v !== '' && v !== null && v !== undefined)
            );
        },
        [filtros.q, filtros.vendedor_id, filtros.fecha_inicio, filtros.fecha_fin, tabActiva, tipoOperativo]
    );

    const recargarListado = useCallback(
        (extra = {}) => {
            router.get(route('cancelaciones_cotizaciones.index'), paramsListado(extra), {
                ...OPCIONES_LISTADO,
                onStart: () => setListaCargando(true),
                onFinish: () => setListaCargando(false),
            });
        },
        [paramsListado]
    );

    const cambiarTab = useCallback(
        (tab) => {
            setTabActiva(tab);
            recargarListado({ tab, page: 1 });
        },
        [recargarListado]
    );

    const cambiarTipo = useCallback(
        (tipo) => {
            setTipoOperativo(tipo);
            recargarListado({ tipo_operativo: tipo || undefined, page: 1 });
        },
        [recargarListado]
    );

    const irAPagina = useCallback(
        (pagina) => {
            if (pagina < 1 || pagina > (solicitudes?.last_page || 1)) return;
            router.get(route('cancelaciones_cotizaciones.index'), paramsListado({ page: pagina }), {
                ...OPCIONES_LISTADO,
                preserveScroll: true,
                onStart: () => setListaCargando(true),
                onFinish: () => setListaCargando(false),
            });
        },
        [paramsListado, solicitudes?.last_page]
    );

    const abrirMenu = (e, solicitud) => {
        e.stopPropagation();
        const rect = e.currentTarget.getBoundingClientRect();
        setMenuPos({ top: rect.bottom + 8, left: Math.max(8, rect.left - 180) });
        setMenuSolicitud(solicitud);
        setMenuAbierto(solicitud.id);
    };

    const filtrosActivos = [filtros.vendedor_id, filtros.fecha_inicio, filtros.fecha_fin].filter(Boolean).length;

    const eliminarSolicitud = (id) => {
        const motivo = window.prompt('Motivo de eliminación (mín. 10 caracteres):');
        if (!motivo || motivo.length < 10) return;
        router.delete(route('cancelaciones_cotizaciones.destroy', id), { data: { motivo } });
    };

    const abrirFormulario = (procesoId = '') => {
        setModalForm({ abierto: true, procesoId });
    };

    const listaServidor = solicitudes?.data || [];

    const filtrosSincronizados = (filtros.tab || 'TODAS') === tabActiva
        && (filtros.tipo_operativo || '') === tipoOperativo;

    const listaVisible = useMemo(() => {
        if (!listaCargando || filtrosSincronizados) {
            return listaServidor;
        }
        return filtrarSolicitudesPorTab(listaServidor, tabActiva);
    }, [listaServidor, tabActiva, listaCargando, filtrosSincronizados]);

    const exportParams = useMemo(
        () => ({
            ...filtros,
            tab: tabActiva,
            tipo_operativo: tipoOperativo || undefined,
        }),
        [filtros, tabActiva, tipoOperativo]
    );

    const procesoPorSubtipo = (subtipo) => {
        const map = {
            remision: procesos.find((p) => /REMISIÓN|REMISION/i.test(p.nombre)),
            pedido: procesos.find((p) => /CANCELACIÓN DE PEDIDO|CANCELACION DE PEDIDO/i.test(p.nombre)),
            cotizacion: procesos.find((p) => /COTIZACIÓN SOBRE PEDIDO|COTIZACION SOBRE PEDIDO/i.test(p.nombre)),
        };
        return map[subtipo]?.id || '';
    };

    const tituloListado = listaCargando && !filtrosSincronizados
        ? 'Actualizando listado…'
        : solicitudes?.total != null
            ? `${solicitudes.total.toLocaleString('es-MX')} solicitudes`
            : `${listaVisible.length} en esta página`;

    return (
        <AppLayout>
            <Head title="Cancelaciones y Cotizaciones" />

            <div className="gelia-page-shell space-y-6 md:space-y-8">
                <header className={geliaCardClass('p-6 md:p-10 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-6')}>
                    <div className="min-w-0">
                        <div className="flex items-center gap-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>
                                Operaciones
                            </p>
                        </div>
                        <h1 className="text-2xl sm:text-3xl md:text-5xl font-black italic uppercase tracking-tighter theme-text-main m-0 leading-none">
                            Cancelaciones y <span style={{ color: 'var(--color-primario)' }}>Cotizaciones</span>
                        </h1>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                            Remisión, pedido y cotización sobre pedido
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 w-full lg:w-auto shrink-0">
                        {puedeExportar && (
                            <a href={route('cancelaciones_cotizaciones.exportar', exportParams)} className={BTN_SECONDARY}>
                                <Download className="w-4 h-4" /> Exportar
                            </a>
                        )}
                        {puedeCrear && (
                            <>
                                <button type="button" onClick={() => abrirFormulario(procesoPorSubtipo('remision'))} className={BTN_SECONDARY}>
                                    <Plus className="w-4 h-4" /> Remisión
                                </button>
                                <button type="button" onClick={() => abrirFormulario(procesoPorSubtipo('pedido'))} className={BTN_SECONDARY}>
                                    <Plus className="w-4 h-4" /> Pedido
                                </button>
                                <button type="button" onClick={() => abrirFormulario(procesoPorSubtipo('cotizacion'))} className={BTN_PRIMARY}>
                                    <Plus className="w-4 h-4" /> Cotización
                                </button>
                            </>
                        )}
                    </div>
                </header>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-6 min-w-0">
                    {[
                        { label: 'Pendientes', value: metricas?.pendientes ?? 0, color: '#f59e0b' },
                        { label: 'Respondidas hoy', value: metricas?.respondidas_hoy ?? 0, accent: true },
                        { label: 'Incorrectas', value: metricas?.incorrectas ?? 0, color: '#ef4444' },
                    ].map(({ label, value, color, accent }) => (
                        <div key={label} className={geliaCardClass('p-5 md:p-6 flex items-center gap-4 min-w-0')}>
                            <div className="p-3 rounded-2xl theme-element border theme-border shrink-0">
                                <FileSpreadsheet className="w-6 h-6" style={{ color: accent ? 'var(--color-primario)' : color }} />
                            </div>
                            <div className="min-w-0">
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
                                <p className="text-2xl font-black theme-text-main m-0 tabular-nums leading-tight mt-1">{value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                <FiltrosOperativas
                    tabActiva={tabActiva}
                    busqueda={filtros.q || ''}
                    tipoOperativo={tipoOperativo}
                    fechaInicio={filtros.fecha_inicio || ''}
                    fechaFin={filtros.fecha_fin || ''}
                    filtroVendedor={filtros.vendedor_id || ''}
                    vendedores={vendedores}
                    filtrosActivos={filtrosActivos}
                    listaCargando={listaCargando}
                    onCambiarTab={cambiarTab}
                    onCambiarTipo={cambiarTipo}
                    onAplicarFiltros={recargarListado}
                    onLimpiarAdicionales={() => recargarListado({ vendedor_id: undefined, fecha_inicio: undefined, fecha_fin: undefined, page: 1 })}
                />

                {listaVisible.length === 0 && !listaCargando ? (
                    <div className={`${geliaCardClass()} text-center py-14 md:py-16 px-6`}>
                        <FileSpreadsheet className="w-10 h-10 mx-auto mb-3 theme-text-muted opacity-50" />
                        <p className="text-sm font-black italic uppercase theme-text-main m-0">Sin solicitudes</p>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                            No hay solicitudes operativas en esta vista
                        </p>
                    </div>
                ) : (
                    <div className={`${geliaCardClass('overflow-hidden')} relative transition-opacity duration-200 ${listaCargando ? 'opacity-60' : ''}`}>
                        {listaCargando && (
                            <div className="absolute inset-0 z-10 flex items-center justify-center bg-[var(--theme-surface-bg)]/50 backdrop-blur-[2px] pointer-events-none rounded-[inherit]">
                                <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} aria-hidden />
                                <span className="sr-only">Actualizando listado</span>
                            </div>
                        )}
                        <div className="p-4 md:p-6 border-b theme-border">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                {tituloListado}
                            </p>
                        </div>
                        <div className="p-4 md:p-6">
                            <div className="gelia-listado-grid">
                                {listaVisible.map((sol) => (
                                    <TarjetaOperativa
                                        key={sol.id}
                                        solicitud={sol}
                                        auth={auth}
                                        onMenu={abrirMenu}
                                        onAprobar={(s) => setModalRespuesta({ abierto: true, solicitud: s, estadoId: 2 })}
                                        onReportar={(s) => setModalRespuesta({ abierto: true, solicitud: s, estadoId: 4 })}
                                        onVerificar={(s) => setModalRespuesta({ abierto: true, solicitud: s, estadoId: 3 })}
                                    />
                                ))}
                            </div>
                        </div>
                        {!listaCargando && (solicitudes?.last_page || 1) > 1 && (
                            <GeliaPaginacion
                                paginator={solicitudes}
                                onIrAPagina={irAPagina}
                                embedded
                            />
                        )}
                    </div>
                )}
            </div>

            {modalForm.abierto && (
                <ModalFormOperativa
                    onClose={() => setModalForm({ abierto: false, procesoId: '' })}
                    procesos={procesos}
                    bancos={bancos}
                    procesoInicialId={modalForm.procesoId}
                />
            )}
            {modalRespuesta.abierto && (
                <ModalRespuestaOperativa
                    onClose={() => setModalRespuesta({ abierto: false, solicitud: null, estadoId: null })}
                    solicitud={modalRespuesta.solicitud}
                    estadoId={modalRespuesta.estadoId}
                />
            )}
            {modalBitacora.abierto && (
                <ModalBitacoraSolicitud
                    onClose={() => setModalBitacora({ abierto: false, solicitud: null })}
                    solicitud={modalBitacora.solicitud}
                />
            )}
            {modalCancelacion.abierto && (
                <ModalSolicitarCancelacion
                    onClose={() => setModalCancelacion({ abierto: false, solicitud: null })}
                    solicitud={modalCancelacion.solicitud}
                />
            )}
            {modalConfirmarCancelacion.abierto && (
                <ModalConfirmarCancelacion
                    onClose={() => setModalConfirmarCancelacion({ abierto: false, solicitud: null })}
                    solicitud={modalConfirmarCancelacion.solicitud}
                />
            )}

            <MenuAcciones
                menuAbierto={menuAbierto}
                menuSolicitud={menuSolicitud}
                menuPos={menuPos}
                setMenuAbierto={setMenuAbierto}
                setModalRespuesta={setModalRespuesta}
                setModalBitacora={setModalBitacora}
                setModalCancelacion={setModalCancelacion}
                setModalConfirmarCancelacion={setModalConfirmarCancelacion}
                eliminarSolicitud={eliminarSolicitud}
                can={can}
                auth={auth}
            />
        </AppLayout>
    );
}
