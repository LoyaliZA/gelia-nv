import useActualizacionesPedidos from '../Partials/useActualizacionesPedidos';
import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { Head, router, usePage } from '@inertiajs/react';
import { Warehouse, Clock, Package, Loader2, PackageOpen, Store, Truck } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import useFiltrosOperacion from '../Partials/useFiltrosOperacion';
import { IndicadoresOperacion } from '../Partials/FiltrosBandejaOperativa';
import FiltrosCedis from './Partials/FiltrosCedis';
import TarjetasCedis from './Partials/TarjetasCedis';
import ModalDetalleCedis from './Partials/ModalDetalleCedis';
import ModalResponderPesaje from './Partials/ModalResponderPesaje';
import ModalReportarErrorDatos from '../Partials/ModalReportarErrorDatos';
import ModalMarcarApartadoResguardo from './Partials/ModalMarcarApartadoResguardo';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';
import ModalBitacoraPedido from '../Partials/ModalBitacoraPedido';
import ModalLiberarMercancia from '../Partials/ModalLiberarMercancia';
import PanelRespuestaDisponibilidad from '../Partials/PanelRespuestaDisponibilidad';
import useListadoDiscreto from '../Partials/useListadoDiscreto';
import { lecturaConsultaVigente } from '../Partials/vigenciaConsultaPedido';

const KPI_CONFIG = [
    { id: '', label: 'Todos', icon: Package },
    { id: 'tienda', label: 'Para tienda', icon: Store },
    { id: 'mismo_dia', label: 'Mismo día', icon: Clock },
    { id: 'municipal', label: 'Municipales', icon: Truck },
    { id: 'nacional', label: 'Nacionales', icon: PackageOpen },
];

export default function Index({
    auth,
    pedidos,
    liberaciones = null,
    metricas = {},
    filtros = {},
    fecha_hoy,
    tipos_caja = [],
    almacenes_busqueda = [],
    can: canProps = {},
}) {
    const { flash } = usePage().props;
    const puedeVerBitacora = canProps.bitacora ?? ((auth?.user?.permissions || []).includes('control_pedidos.cedis.bitacora') || auth?.user?.roles?.includes('Super Admin'));
    const {
        pedidos: pedidosVista,
        metricas: metricasVista,
        cargando,
        errorListado,
        cargar,
    } = useListadoDiscreto({
        listadoRoute: 'control_pedidos.cedis.listado',
        indexRoute: 'control_pedidos.cedis.index',
        pedidos,
        metricas,
    });

    const { valores, params, cambiar, limpiar } = useFiltrosOperacion(filtros, cargar, { tipo: '' });
    const tabActiva = valores.tab;
    const busqueda = valores.q;
    const [modalDetalle, setModalDetalle] = useState({ abierto: false, pedido: null });
    const [modalPesaje, setModalPesaje] = useState({ abierto: false, pedido: null });
    const [modalErrorDatos, setModalErrorDatos] = useState({ abierto: false, pedido: null });
    const [modalApartado, setModalApartado] = useState({ abierto: false, pedido: null });
    const [modalBitacora, setModalBitacora] = useState({ abierto: false, pedido: null });
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const [modalLiberar, setModalLiberar] = useState({ abierto: false, tarea: null });
    const [disponibilidad, setDisponibilidad] = useState({ abierto: false, pedido: null });
    const [capturaDisponibilidad, setCapturaDisponibilidad] = useState(false);
    const [avisoDisponibilidad, setAvisoDisponibilidad] = useState('');
    const lecturaDisponibilidadRef = useRef(0);
    const consultaAccesibleRef = useRef(true);
    const modalAbiertoRef = useRef(false);
    useEffect(() => {
        if (flash?.success) {
            setAlerta({ abierto: true, tipo: 'success', titulo: 'Operación exitosa', mensaje: flash.success });
        } else if (flash?.error) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: flash.error });
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        modalAbiertoRef.current = modalDetalle.abierto || modalPesaje.abierto || modalErrorDatos.abierto || modalApartado.abierto || modalBitacora.abierto || modalLiberar.abierto || disponibilidad.abierto;
    }, [modalDetalle.abierto, modalPesaje.abierto, modalErrorDatos.abierto, modalApartado.abierto, modalBitacora.abierto, modalLiberar.abierto, disponibilidad.abierto]);

    const leerDisponibilidad = async (id) => {
        const lectura = ++lecturaDisponibilidadRef.current;
        try {
            const { data } = await axios.get(`/control-pedidos/${id}/disponibilidad-tienda`);
            if (lectura !== lecturaDisponibilidadRef.current) return false;
            setDisponibilidad(actual => actual.abierto && actual.pedido?.id === id && lecturaConsultaVigente(actual.pedido, data) ? { ...actual, pedido: data } : actual);
            setAvisoDisponibilidad('');
            return data;
        } catch (error) {
            if (error?.response?.status === 403) consultaAccesibleRef.current = false;
            setAvisoDisponibilidad(error?.response?.status === 403
                ? 'La consulta ya no está disponible en su bandeja. Se conserva la respuesta mostrada.'
                : 'No se pudo actualizar la consulta. Se conserva su captura y se volverá a intentar.');
            return false;
        }
    };
    useEffect(() => {
        setCapturaDisponibilidad(false);
        setAvisoDisponibilidad('');
        consultaAccesibleRef.current = true;
        if (disponibilidad.abierto) leerDisponibilidad(disponibilidad.pedido.id);
        return () => { lecturaDisponibilidadRef.current++; };
    }, [disponibilidad.abierto, disponibilidad.pedido?.id]);

    // La misma suscripción actualiza el pedido abierto o la bandeja, nunca ambos a la vez.
    useActualizacionesPedidos({
        pedidoId: disponibilidad.abierto ? disponibilidad.pedido?.id : null,
        intervaloRespaldo: disponibilidad.abierto ? 15000 : 0,
        respaldoAunqueSuscrito: disponibilidad.abierto,
        onActualizar: async () => {
            if (disponibilidad.abierto) {
                if (!consultaAccesibleRef.current) {
                    // La etapa ya no permite GET por ID. El mismo hook detecta el
                    // regreso en el listado autorizado, conservando filtros y URL.
                    const { data: listado } = await axios.get(route('control_pedidos.cedis.listado'), {
                        params: { q: disponibilidad.pedido.folio, tab: 'TODOS' }, headers: { Accept: 'application/json' },
                    });
                    if (!(listado.pedidos?.data || []).some(pedido => pedido.id === disponibilidad.pedido.id)) return;
                }
                const lectura = await leerDisponibilidad(disponibilidad.pedido.id);
                if (lectura) consultaAccesibleRef.current = true;
                return lectura;
            }
            return tabActiva === 'LIBERACIONES' ? router.reload({ only: ['liberaciones', 'metricas'] }) : cargar(params({ page: pedidosVista?.current_page || 1 }), { silencioso: true });
        },
        // Con la consulta abierta la lectura solo fusiona datos operativos; la captura vive en el panel y no se pausa.
        pausado: disponibilidad.abierto ? false : (modalAbiertoRef.current || cargando),
    });


    const onActualizar = () => {
        if (tabActiva === 'LIBERACIONES') {
            router.reload({ only: ['liberaciones', 'metricas'] });
            return;
        }
        cargar(params({ page: pedidosVista?.current_page || 1 }));
    };
    const onIrAPagina = (page) => {
        if (tabActiva === 'LIBERACIONES') {
            router.get(route('control_pedidos.cedis.index'), params({ page }), { preserveState: true, preserveScroll: true });
            return;
        }
        cargar(params({ page }));
    };

    const abrirDetalle = (pedido) => setModalDetalle({ abierto: true, pedido });
    const abrirPesaje = (pedido) => {
        if (pedido?.solicitud_preparacion?.area_revision === 'control_pedidos' || pedido?.solicitud_preparacion?.consulta_municipal?.area === 'cedis') {
            setDisponibilidad({ abierto: true, pedido });
            return;
        }
        setModalPesaje({ abierto: true, pedido });
    };
    const abrirErrorDatos = (pedido) => setModalErrorDatos({ abierto: true, pedido });
    const abrirApartado = (pedido) => setModalApartado({ abierto: true, pedido });
    const abrirBitacora = (pedido) => { if (puedeVerBitacora && pedido.puede_ver_bitacora !== false) setModalBitacora({ abierto: true, pedido }); };

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de pedidos CEDIS | GELIANV" />
            <GeliaPageShell className="gelia-pedidos-bma gelia-pedidos-cedis space-y-3 md:space-y-6">
                <header className={`${geliaCardClass()} gelia-pedidos-encabezado p-3 md:p-6`}>
                    <div className="flex items-center gap-3">
                        <span className="gelia-pedidos-icono !w-10 !h-10 md:!w-12 md:!h-12" aria-hidden="true"><Warehouse className="w-5 h-5 md:w-6 md:h-6" /></span>
                        <div className="min-w-0">
                            <h1 className="text-lg md:text-2xl font-bold theme-text-main m-0">Control pedidos CEDIS</h1>
                            <p className="hidden sm:block text-sm theme-text-muted mt-1 m-0">Consulta, pesaje y empaque de pedidos</p>
                            <a className="inline-flex items-center min-h-[44px] sm:min-h-0 text-primario underline text-sm" href={route('traspasos.abastecimiento.index')}>Abastecimiento a preparación en PDV</a>
                        </div>
                    </div>
                </header>

                <div className={`${geliaCardClass()} p-3 sm:p-5 space-y-3 sm:space-y-4`}>
                    <IndicadoresOperacion label="Tipo de pedido" activo={valores.tipo || ''}
                        opciones={KPI_CONFIG.map(opcion => ({ ...opcion, valor: opcion.id ? metricasVista.tipos?.[opcion.id] : metricasVista.todos }))}
                        onCambiar={tipo => cambiar({ tipo, tab: 'TODOS' })} />
                    {tabActiva !== 'LIBERACIONES' && <FiltrosCedis fechaHoy={fecha_hoy} valores={valores} onCambiar={cambiar} onLimpiar={limpiar} onActualizar={onActualizar} buscando={cargando} />}
                    {errorListado && <p role="alert" className="text-sm theme-text-peligro">{errorListado}</p>}
                </div>

                {(canProps.liberar || metricasVista.liberaciones_pendientes > 0 || tabActiva === 'LIBERACIONES') && <a className="inline-flex items-center gap-2 min-h-[44px] text-sm font-semibold theme-text-primario underline" href={route('control_pedidos.cedis.index', { tab: tabActiva === 'LIBERACIONES' ? 'TODOS' : 'LIBERACIONES' })}>{tabActiva === 'LIBERACIONES' ? 'Volver a todos los pedidos' : `Liberaciones pendientes${metricasVista.liberaciones_pendientes ? ` · ${metricasVista.liberaciones_pendientes}` : ''}`}</a>}
                <div className="relative min-h-[12rem] space-y-3" aria-busy={cargando}>
                    <div className="flex flex-wrap justify-between items-center gap-2 px-1">
                        <h2 className="text-sm font-semibold theme-text-main m-0">{tabActiva === 'LIBERACIONES' ? 'Liberaciones' : 'Pedidos'}</h2>
                        <p className="text-xs theme-text-muted m-0 tabular-nums">{(() => { const total = (tabActiva === 'LIBERACIONES' ? liberaciones : pedidosVista)?.total ?? 0; return `${total} ${total === 1 ? 'resultado' : 'resultados'}${busqueda ? ` para “${busqueda}”` : ''}`; })()}</p>
                    </div>
                    <p className="sr-only" role="status">{cargando ? 'Actualizando pedidos…' : 'Listado actualizado'}</p>
                    {cargando && tabActiva !== 'LIBERACIONES' && (
                        <div className="absolute inset-0 z-10 flex items-start justify-center pt-16 pointer-events-none">
                            <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} aria-label="Cargando pedidos" />
                        </div>
                    )}
                    {tabActiva === 'LIBERACIONES' ? (
                        <div className="space-y-3">
                            {(liberaciones?.data || []).length === 0 && (
                                <div className={`${geliaCardClass()} p-8 text-center text-sm font-bold theme-text-muted`}>
                                    Sin liberaciones pendientes
                                </div>
                            )}
                            {(liberaciones?.data || []).map((tarea) => {
                                const folio = tarea.pedido?.folio_remision || tarea.pedido?.folio || `#${tarea.id}`;
                                return (
                                    <div key={tarea.id} className={`${geliaCardClass()} p-4 flex flex-wrap items-center justify-between gap-3`}>
                                        <div>
                                            <p className="text-sm font-black theme-text-main m-0">{folio}</p>
                                            <p className="text-xs font-bold theme-text-muted m-0">
                                                {tarea.almacen?.nombre || '—'} · {tarea.modalidad?.nombre || tarea.estado}
                                            </p>
                                            <p className="text-xs font-bold theme-text-muted m-0 mt-1">
                                                {(tarea.productos || []).slice(0, 3).map((p) => p.descripcion_snapshot || p.sku).join(', ') || 'Sin productos'}
                                            </p>
                                        </div>
                                        {(canProps.liberar || (auth?.user?.permissions || []).includes('control_pedidos.cedis.liberar')) && (
                                            <button
                                                type="button"
                                                className="px-4 py-2 min-h-[44px] rounded-xl text-xs font-black uppercase tracking-wide text-white"
                                                style={{ background: 'var(--color-primario)' }}
                                                onClick={() => setModalLiberar({ abierto: true, tarea })}
                                            >
                                                Liberar mercancía
                                            </button>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <TarjetasCedis
                            pedidos={pedidosVista}
                            onVerDetalle={abrirDetalle}
                            onResponderPesaje={abrirPesaje}
                            onReportarErrorDatos={abrirErrorDatos}
                            onMarcarApartado={abrirApartado}
                            onBitacora={puedeVerBitacora ? abrirBitacora : undefined}
                        />
                    )}
                    <GeliaPaginacion paginator={tabActiva === 'LIBERACIONES' ? liberaciones : pedidosVista} onIrAPagina={onIrAPagina} embedded />
                </div>
            </GeliaPageShell>

            <ModalLiberarMercancia
                abierto={modalLiberar.abierto}
                onClose={() => setModalLiberar({ abierto: false, tarea: null })}
                tarea={modalLiberar.tarea}
                routeName="control_pedidos.cedis.liberar"
            />
            <ModalDetalleCedis
                abierto={modalDetalle.abierto}
                pedido={modalDetalle.pedido}
                onClose={() => setModalDetalle({ abierto: false, pedido: null })}
                onReportarErrorDatos={abrirErrorDatos}
                onMarcarApartado={abrirApartado}
            />
            <ModalBitacoraPedido
                abierto={Boolean(puedeVerBitacora && modalBitacora.abierto)}
                pedido={modalBitacora.pedido}
                onClose={() => setModalBitacora({ abierto: false, pedido: null })}
            />
            <PanelRespuestaDisponibilidad
                key={disponibilidad.pedido?.id || 'cerrada'}
                pedido={disponibilidad.pedido || {}}
                modo="dialogo"
                abierto={disponibilidad.abierto}
                onClose={() => { setDisponibilidad({ abierto: false, pedido: null }); onActualizar(); }}
                documentos={(disponibilidad.pedido?.documentos || []).filter((documento) => ['pdf_pedido', 'cotizacion', 'anexo', 'anexo_piezas'].includes(documento.tipo))}
                revision={disponibilidad.pedido?.solicitud_preparacion?.revision_disponibilidad}
                piezas={(disponibilidad.pedido?.revisiones_producto || []).filter((pieza) => !pieza.resolucion && ['regular', 'malo', 'danado', 'sin_existencia'].includes(pieza.estado_fisico)).map((pieza) => ({ id: pieza.id, descripcion: pieza.descripcion_producto, sku: pieza.sku, condicion: pieza.estado_fisico === 'sin_existencia' ? 'faltante' : 'detalle', unica_pieza: pieza.unica_pieza, mejor_ejemplar: pieza.mejor_ejemplar, cantidad: pieza.cantidad_solicitada, detalle: pieza.comentario }))}
                puedeResponder
                tomada
                onDirtyChange={setCapturaDisponibilidad}
                avisoActualizacion={avisoDisponibilidad}
                puedeLeerDocumentos={consultaAccesibleRef.current}
                onEnviar={async (form) => {
                    const { data } = await axios.post(route('control_pedidos.cedis.disponibilidad', disponibilidad.pedido.id), form, { headers: { Accept: 'application/json' } });
                    if (data.pedido) {
                        lecturaDisponibilidadRef.current++;
                        consultaAccesibleRef.current = data.puede_consultar !== false;
                        setDisponibilidad(actual => actual.abierto && actual.pedido?.id === data.pedido.id && lecturaConsultaVigente(actual.pedido, data.pedido) ? { ...actual, pedido: data.pedido } : actual);
                        setAvisoDisponibilidad('');
                    } else await leerDisponibilidad(disponibilidad.pedido.id);
                    onActualizar();
                }}
            />
            <ModalResponderPesaje
                abierto={modalPesaje.abierto}
                pedido={modalPesaje.pedido}
                tiposCaja={tipos_caja}
                almacenesBusqueda={almacenes_busqueda}
                onClose={() => setModalPesaje({ abierto: false, pedido: null })}
            />
            <ModalReportarErrorDatos
                abierto={modalErrorDatos.abierto}
                pedido={modalErrorDatos.pedido}
                origen="cedis"
                onClose={() => setModalErrorDatos({ abierto: false, pedido: null })}
                onSuccess={() => {
                    setModalErrorDatos({ abierto: false, pedido: null });
                    setModalDetalle({ abierto: false, pedido: null });
                }}
            />
            <ModalMarcarApartadoResguardo
                abierto={modalApartado.abierto}
                pedido={modalApartado.pedido}
                onClose={() => setModalApartado({ abierto: false, pedido: null })}
            />
            <ModalAlertaPedido
                abierto={alerta.abierto}
                tipo={alerta.tipo}
                titulo={alerta.titulo}
                mensaje={alerta.mensaje}
                onClose={() => setAlerta({ ...alerta, abierto: false })}
            />
        </AppLayout>
    );
}
