import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { Head, router } from '@inertiajs/react';
import {
    Ban, CheckCircle2, AlertOctagon, CheckSquare, History, Trash2, XCircle,
    FileSpreadsheet, Download, Plus, X,
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
import { ACCENT, BTN_PRIMARY, BTN_SECONDARY } from './Partials/operativasStyles';
import { geliaCardClass } from '../../utils/geliaTheme';

const MenuAcciones = ({
    menuAbierto, menuSolicitud, menuPos, setMenuAbierto, setModalRespuesta, setModalBitacora,
    setModalCancelacion, setModalConfirmarCancelacion, eliminarSolicitud, can, auth,
}) => {
    if (!menuAbierto || !menuSolicitud) return null;
    const solicitud = menuSolicitud;
    const esCancelada = solicitud.estado?.nombre === 'Cancelada';
    const estadosActivos = ['Pendiente', 'Respondida', 'Verificada'];
    const roles = auth?.user?.roles || [];
    const esGerente = roles.some(r => String(r).toLowerCase().includes('gerente'));
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
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: 2 }); }} className="flex items-center gap-3 px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none" style={{ color: ACCENT }}>
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
    const [modalForm, setModalForm] = useState({ abierto: false, procesoId: '' });
    const [modalRespuesta, setModalRespuesta] = useState({ abierto: false, solicitud: null, estadoId: null });
    const [modalBitacora, setModalBitacora] = useState({ abierto: false, solicitud: null });
    const [modalCancelacion, setModalCancelacion] = useState({ abierto: false, solicitud: null });
    const [modalConfirmarCancelacion, setModalConfirmarCancelacion] = useState({ abierto: false, solicitud: null });
    const [menuAbierto, setMenuAbierto] = useState(null);
    const [menuSolicitud, setMenuSolicitud] = useState(null);
    const [menuPos, setMenuPos] = useState({ top: 0, left: 0 });

    const abrirMenu = (e, solicitud) => {
        e.stopPropagation();
        const rect = e.currentTarget.getBoundingClientRect();
        setMenuPos({ top: rect.bottom + 8, left: Math.max(8, rect.left - 180) });
        setMenuSolicitud(solicitud);
        setMenuAbierto(solicitud.id);
    };

    const navegar = (params = {}) => {
        router.get(route('cancelaciones_cotizaciones.index'), {
            tab: tabActiva,
            tipo_operativo: tipoOperativo,
            q: filtros.q || '',
            vendedor_id: filtros.vendedor_id || '',
            fecha_inicio: filtros.fecha_inicio || '',
            fecha_fin: filtros.fecha_fin || '',
            ...params,
        }, { preserveState: true, replace: true });
    };

    const cambiarTab = (tab) => {
        setTabActiva(tab);
        navegar({ tab });
    };

    const cambiarTipo = (tipo) => {
        setTipoOperativo(tipo);
        navegar({ tipo_operativo: tipo });
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

    const lista = solicitudes?.data || [];
    const procesoPorSubtipo = (subtipo) => {
        const map = {
            remision: procesos.find(p => /REMISIÓN|REMISION/i.test(p.nombre)),
            pedido: procesos.find(p => /CANCELACIÓN DE PEDIDO|CANCELACION DE PEDIDO/i.test(p.nombre)),
            cotizacion: procesos.find(p => /COTIZACIÓN SOBRE PEDIDO|COTIZACION SOBRE PEDIDO/i.test(p.nombre)),
        };
        return map[subtipo]?.id || '';
    };

    return (
        <AppLayout>
            <Head title="Cancelaciones y Cotizaciones" />

            <div className="space-y-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black italic theme-text-main uppercase tracking-tighter m-0">
                            Cancelaciones y <span style={{ color: ACCENT }}>Cotizaciones</span>_
                        </h1>
                        <p className="text-xs font-bold theme-text-muted mt-1">Cancelación de remisión/pedido y cotización sobre pedido</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {puedeExportar && (
                            <a href={route('cancelaciones_cotizaciones.exportar', filtros)} className={BTN_SECONDARY}>
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
                                <button type="button" onClick={() => abrirFormulario(procesoPorSubtipo('cotizacion'))} className={BTN_PRIMARY} style={{ backgroundColor: ACCENT }}>
                                    <Plus className="w-4 h-4" /> Cotización
                                </button>
                            </>
                        )}
                    </div>
                </header>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    {[
                        { label: 'Pendientes', value: metricas?.pendientes ?? 0, color: '#f59e0b' },
                        { label: 'Respondidas hoy', value: metricas?.respondidas_hoy ?? 0, accent: true },
                        { label: 'Incorrectas', value: metricas?.incorrectas ?? 0, color: '#ef4444' },
                    ].map(({ label, value, color, accent }) => (
                        <div key={label} className={geliaCardClass('rounded-2xl p-5 flex items-center gap-4')}>
                            <div className="p-3 rounded-xl theme-element border theme-border">
                                <FileSpreadsheet className="w-6 h-6" style={{ color: accent ? ACCENT : color }} />
                            </div>
                            <div>
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
                                <p className="text-2xl font-black theme-text-main m-0">{value}</p>
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
                    onCambiarTab={cambiarTab}
                    onCambiarBusqueda={() => {}}
                    onCambiarTipo={cambiarTipo}
                    onAplicarFiltros={navegar}
                    onLimpiarAdicionales={() => navegar({ vendedor_id: '', fecha_inicio: '', fecha_fin: '' })}
                />

                {lista.length === 0 ? (
                    <div className={`${geliaCardClass('rounded-2xl p-12 text-center')}`}>
                        <p className="text-sm font-bold theme-text-muted m-0">No hay solicitudes operativas en esta vista.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        {lista.map(sol => (
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
                )}

                {solicitudes?.last_page > 1 && (
                    <GeliaPaginacion
                        paginator={solicitudes}
                        onIrAPagina={(page) => navegar({ page })}
                    />
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
