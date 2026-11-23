import useActualizacionesPedidos from './Partials/useActualizacionesPedidos';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import { geliaCardClass } from '../../utils/geliaTheme';
import EncabezadoGestionPedidos from './Partials/EncabezadoGestionPedidos';
import FiltrosPedidos from './Partials/FiltrosPedidos';
import MetricasBandejaPedidos from './Partials/MetricasBandejaPedidos';
import TablaPedidos from './Partials/TablaPedidos';
import ModalMovimientosTienda from './Partials/ModalMovimientosTienda';
import ModalPiezasAdicionales from './Partials/ModalPiezasAdicionales';
import ModalCambioATiendaMunicipal from './Partials/ModalCambioATiendaMunicipal';
import ModalFormPedido, { hayBorradorPedidoLocal } from './Partials/ModalFormPedido';
import ModalDetallePedido from './Partials/ModalDetallePedido';
import ModalBitacoraPedido from './Partials/ModalBitacoraPedido';
import ModalCancelarPedido from './Partials/ModalCancelarPedido';
import ModalAlertaPedido from './Partials/ModalAlertaPedido';
import ModalConfirmarAccion from './Partials/ModalConfirmarAccion';
import ModalAuditoriaEliminacionPedido from './Partials/ModalAuditoriaEliminacionPedido';
import ModalEliminarRegistroPedido from './Partials/ModalEliminarRegistroPedido';
import ModalGenerarLinkDireccion from './Partials/ModalGenerarLinkDireccion';
import ModalAnexarPagoEnvio from './Partials/ModalAnexarPagoEnvio';
import ModalAnexoExtraordinario from './Nacional/ModalAnexoExtraordinario';
import ModalCargarGuiaCliente from './Partials/ModalCargarGuiaCliente';
import ModalLiberarResguardoAbierto from './Auditar/Partials/ModalLiberarResguardoAbierto';
import useListadoDiscreto from './Partials/useListadoDiscreto';

const REFRESCO_LISTADO_MS = 15000;

export default function Index({ auth, pedidos, metricas = {}, filtros = {}, catalogos = {}, direcciones_normalizadas = false, registrar_tienda = {} }) {
    const { flash } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const can = (permiso) => permisos.includes(permiso) || auth?.user?.roles?.includes('Super Admin');

    const {
        pedidos: pedidosVista,
        metricas: metricasVista,
        cargando,
        cargar,
    } = useListadoDiscreto({
        listadoRoute: 'control_pedidos.listado',
        indexRoute: 'control_pedidos.index',
        pedidos,
        metricas,
    });

    const [tabActiva, setTabActiva] = useState(filtros.tab || 'TODAS');
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [filtrosExtra, setFiltrosExtra] = useState({
        desde: filtros.desde || '',
        hasta: filtros.hasta || '',
        almacen_id: filtros.almacen_id || '',
        mios: filtros.mios === '1' || filtros.mios === 'true' || filtros.mios === true ? '1' : '',
    });
    const filtrosExtraRef = useRef(filtrosExtra);
    const [modalForm, setModalForm] = useState({ abierto: false, pedido: null, recuperarBorrador: false, instancia: 'new-clean' });
    const [lecturaPropiaFormulario, setLecturaPropiaFormulario] = useState(false);
    const [confirmarBorradorNuevo, setConfirmarBorradorNuevo] = useState(false);
    const [modalDetalle, setModalDetalle] = useState({ abierto: false, pedido: null });
    const [movimientoTienda, setMovimientoTienda] = useState(null);
    const [piezasAdicionalesId, setPiezasAdicionalesId] = useState(null);
    const [cambioATiendaId, setCambioATiendaId] = useState(null);
    const [modalBitacora, setModalBitacora] = useState({ abierto: false, pedido: null });
    const [pedidoAEliminar, setPedidoAEliminar] = useState(null);
    const [pedidoAEliminarRegistro, setPedidoAEliminarRegistro] = useState(null);
    const [pedidoACancelar, setPedidoACancelar] = useState(null);
    const [pedidoRestaurar, setPedidoRestaurar] = useState(null);
    const [pedidoAuditoria, setPedidoAuditoria] = useState(null);
    const [modalLinkDireccion, setModalLinkDireccion] = useState(false);
    const [modalAnexo, setModalAnexo] = useState({ abierto: false, pedido: null });
    const [modalAnexoExtraordinario, setModalAnexoExtraordinario] = useState({ abierto: false, pedido: null });
    const [modalCompletarEnvio, setModalCompletarEnvio] = useState({ abierto: false, pedido: null });
    const [modalCargarGuia, setModalCargarGuia] = useState({ abierto: false, pedido: null });
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const [ultimaSync, setUltimaSync] = useState(null);
    const detalleDesdeEnlaceRef = useRef(false);
    const debounceBusqueda = useRef(null);
    const paramsListado = useRef({ tab: tabActiva, q: busqueda || undefined, ...filtrosExtraRef.current, page: pedidos?.current_page || 1 });
    const flashMostradoRef = useRef(null);

    const cargarYMarcar = useCallback(async (params, opts) => {
        paramsListado.current = params;
        const data = await cargar(params, opts);
        if (data) setUltimaSync(new Date());
        return data;
    }, [cargar]);

    const refrescarListado = useCallback(() => cargarYMarcar(paramsListado.current, { silencioso: true }), [cargarYMarcar]);

    useEffect(() => {
        let vigente = true;
        let recarga;
        const programarRecarga = () => {
            window.clearTimeout(recarga);
            recarga = window.setTimeout(refrescarListado, 180);
        };
        const interceptor = axios.interceptors.response.use((respuesta) => {
            const { method, url } = respuesta.config;
            if (!vigente || !['post', 'put', 'patch', 'delete'].includes(method) || respuesta.headers['x-inertia']
                || !respuesta.headers['content-type']?.includes('application/json') || respuesta.data?.ok === false) return respuesta;

            const destino = new URL(url, window.location.href);
            const [, modulo, recurso, pago] = destino.pathname.split('/');
            if (destino.origin !== window.location.origin || modulo !== 'control-pedidos') return respuesta;

            const guardaPedido = !recurso || recurso === 'autoguardar' || /^\d+$/.test(recurso)
                || (recurso === 'pagos' && /^\d+$/.test(pago));
            const guardaDireccion = ['actualizar-campos-direccion', 'registrar-direccion-catalogo'].includes(recurso)
                && respuesta.data?.pedido_id;
            // Reverb excluye el socket que realizó la escritura.
            if (guardaPedido || guardaDireccion) {
                programarRecarga();
            }
            return respuesta;
        });
        const retirarInertia = router.on('success', ({ detail: { page } }) => {
            if (page.component === 'ControlPedidos/Index' && page.props.flash?.success) programarRecarga();
        });
        return () => {
            vigente = false;
            axios.interceptors.response.eject(interceptor);
            retirarInertia();
            window.clearTimeout(recarga);
        };
    }, [refrescarListado]);

    useEffect(() => {
        // Con el borrador abierto, el feedback va en el propio formulario (evitar alerta Index + click-through).
        const token = flash?.success || flash?.error || null;
        if (!token || flashMostradoRef.current === token) return;
        flashMostradoRef.current = token;
        if (modalForm.abierto) return;
        if (flash?.success) {
            setAlerta({ abierto: true, tipo: 'success', titulo: 'Operación exitosa', mensaje: flash.success });
        } else if (flash?.error) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: flash.error });
        }
    }, [flash?.success, flash?.error, modalForm.abierto]);

    useEffect(() => {
        if (detalleDesdeEnlaceRef.current || !filtros.q) return;
        const filas = pedidos?.data || [];
        const q = String(filtros.q);
        const exacto = filas.find((p) => String(p.folio) === q || String(p.folio_remision) === q || String(p.id) === q);
        const pedido = exacto || (filas.length === 1 ? filas[0] : null);
        if (!pedido) return;
        detalleDesdeEnlaceRef.current = true;
        setModalDetalle({ abierto: true, pedido });
    }, [filtros.q, pedidos]);

    useEffect(() => {
        const filas = pedidosVista?.data || [];
        const refrescar = (m) => {
            if (!m.abierto) return m;
            const idVivo = m.pedido?.id || m.pedidoIdVivo;
            if (!idVivo) return m;
            const fresco = filas.find((p) => p.id === idVivo);
            if (!fresco) return m;
            const mismo = fresco.updated_at === m.pedido.updated_at
                && fresco.pesaje_respondido_at === m.pedido.pesaje_respondido_at
                && fresco.estatus_envio === m.pedido.estatus_envio
                && fresco.catalogo_estatus_pedido_id === m.pedido.catalogo_estatus_pedido_id
                && Number(fresco.version_consulta ?? 0) === Number(m.pedido.version_consulta ?? 0)
                && fresco.consulta_cerrada_at === m.pedido.consulta_cerrada_at
                && JSON.stringify(fresco.solicitud_preparacion ?? null) === JSON.stringify(m.pedido.solicitud_preparacion ?? null)
                && Number(fresco.peso_real_kg) === Number(m.pedido.peso_real_kg)
                && Number(fresco.numero_cajas) === Number(m.pedido.numero_cajas);
            if (mismo) return m;
            return { ...m, pedido: fresco };
        };
        if (!lecturaPropiaFormulario) setModalForm(refrescar);
        setModalDetalle(refrescar);
    }, [pedidosVista, lecturaPropiaFormulario]);

    // Con el formulario abierto sí seguimos refrescando el listado (CEDIS → modal).
    // Pausamos solo overlays que no necesitan sync en vivo.
    // El detalle es de solo lectura: no se pausa para que la respuesta de Control de pedidos se refleje sin cerrar y reabrir.
    const pausarPollingListado = modalBitacora.abierto
        || modalAnexo.abierto
        || modalAnexoExtraordinario.abierto
        || modalCompletarEnvio.abierto
        || modalCargarGuia.abierto
        || modalLinkDireccion
        || confirmarBorradorNuevo
        || Boolean(pedidoAEliminar)
        || Boolean(pedidoAEliminarRegistro)
        || Boolean(pedidoACancelar)
        || Boolean(pedidoRestaurar)
        || Boolean(pedidoAuditoria);

    useActualizacionesPedidos({
        activo: !lecturaPropiaFormulario,
        onActualizar: refrescarListado,
        pausado: Boolean(pausarPollingListado || cargando),
        intervaloRespaldo: REFRESCO_LISTADO_MS,
    });


    // Notificación en vivo (pesaje listo, errores CEDIS, etc.): refrescar al instante si el modal está abierto.
    useEffect(() => {
        const onNotification = (e) => {
            const pedidoId = Number(e.detail?.pedido_bma_id || e.detail?.data?.pedido_bma_id);
            const tipo = String(e.detail?.tipo || e.detail?.data?.tipo || '');
            const modalId = Number(modalForm.pedido?.id);
            if (!pedidoId || !tipo.startsWith('pedido_')) return;
            if (!modalForm.abierto) return;
            if (lecturaPropiaFormulario) return;
            const vivoId = modalId || Number(modalForm.pedidoIdVivo);
            if (vivoId && vivoId !== pedidoId) return;
            refrescarListado();
        };
        window.addEventListener('notification-received', onNotification);
        return () => window.removeEventListener('notification-received', onNotification);
    }, [modalForm.abierto, modalForm.pedido?.id, modalForm.pedidoIdVivo, lecturaPropiaFormulario, refrescarListado]);

    useEffect(() => () => clearTimeout(debounceBusqueda.current), []);

    const onTabChange = (tab) => {
        clearTimeout(debounceBusqueda.current);
        setTabActiva(tab);
        cargarYMarcar({ tab, q: busqueda || undefined, ...filtrosExtraRef.current, page: 1 });
    };

    const onCambiarFiltros = (cambios) => {
        clearTimeout(debounceBusqueda.current);
        filtrosExtraRef.current = { ...filtrosExtraRef.current, ...cambios };
        setFiltrosExtra(filtrosExtraRef.current);
        cargarYMarcar({ tab: tabActiva, q: busqueda || undefined, ...filtrosExtraRef.current, page: 1 });
    };

    const onBuscar = (valor) => {
        setBusqueda(valor);
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            cargarYMarcar({ tab: tabActiva, q: valor || undefined, ...filtrosExtraRef.current, page: 1 });
        }, 400);
    };

    const onIrAPagina = (page) => {
        cargarYMarcar({ tab: tabActiva, q: busqueda || undefined, ...filtrosExtraRef.current, page });
    };

    const onActualizar = () => {
        cargarYMarcar({ tab: tabActiva, q: busqueda || undefined, ...filtrosExtraRef.current, page: pedidosVista?.current_page || 1 });
    };

    const abrirNuevo = () => {
        if (hayBorradorPedidoLocal(auth?.user?.id)) {
            setConfirmarBorradorNuevo(true);
            return;
        }
        setModalForm({ abierto: true, pedido: null, recuperarBorrador: false, instancia: 'new-clean' });
    };
    const abrirNuevoLimpio = () => {
        setConfirmarBorradorNuevo(false);
        setModalForm({ abierto: true, pedido: null, recuperarBorrador: false, instancia: 'new-clean' });
    };
    const abrirNuevoConBorrador = () => {
        setConfirmarBorradorNuevo(false);
        setModalForm({ abierto: true, pedido: null, recuperarBorrador: true, instancia: 'new-draft' });
    };
    const abrirEditar = (pedido) => {
        setModalForm({ abierto: true, pedido, recuperarBorrador: false, instancia: `edit-${pedido.id}` });
    };
    const abrirConversion = (pedido) => {
        setModalForm({
            abierto: true,
            pedido,
            recuperarBorrador: false,
            instancia: `conv-${pedido.id}`,
            intencion: 'conversion',
        });
    };
    const abrirVer = (pedido) => setModalDetalle({ abierto: true, pedido });
    const abrirBitacora = (pedido) => setModalBitacora({ abierto: true, pedido });

    const confirmarEliminar = () => {
        if (!pedidoAEliminar) return;
        const id = pedidoAEliminar.id;
        setPedidoAEliminar(null);
        router.delete(route('control_pedidos.destroy', id), { preserveScroll: true });
    };

    const confirmarEliminarRegistro = (motivo, onFinish) => {
        const pedido = pedidoAEliminarRegistro;
        if (!pedido) {
            onFinish?.();
            return;
        }
        router.delete(route('control_pedidos.eliminar_registro', pedido.id), {
            data: { motivo },
            preserveScroll: true,
            onFinish: () => onFinish?.(),
            onSuccess: () => setPedidoAEliminarRegistro(null),
        });
    };

    const confirmarRestaurar = () => {
        if (!pedidoRestaurar) return;
        const id = pedidoRestaurar.id;
        setPedidoRestaurar(null);
        router.put(route('control_pedidos.restaurar_registro', id), {}, { preserveScroll: true });
    };

    const exportarCsv = () => {
        window.location.href = route('control_pedidos.exportar', { tab: tabActiva, q: busqueda || '', ...Object.fromEntries(Object.entries(filtrosExtra).filter(([, v]) => v)) });
    };

    const etiquetaEliminar = pedidoAEliminar?.folio_remision || pedidoAEliminar?.folio || 'este borrador';

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de pedidos | GELIANV" />
            <GeliaPageShell className="gelia-tienda-op gelia-pedidos-bma space-y-4 md:space-y-6">
                <div className="gelia-pedidos-bma-contenido space-y-4 md:space-y-6">
                <EncabezadoGestionPedidos
                    can={can}
                    cargando={cargando}
                    ultimaSync={ultimaSync}
                    onNuevo={abrirNuevo}
                    onExportar={exportarCsv}
                    onLinkDireccion={() => setModalLinkDireccion(true)}
                />

                <div className={`${geliaCardClass()} gelia-tienda-op-workspace p-4 md:p-5 space-y-3`}>
                    <MetricasBandejaPedidos
                        metricas={metricasVista}
                        tabActiva={tabActiva}
                        onTabChange={onTabChange}
                        incluirEliminadas={can('control_pedidos.eliminados')}
                    />
                    <div className="gelia-tienda-op-divider" aria-hidden />
                    <FiltrosPedidos
                        filtros={filtros}
                        busqueda={busqueda}
                        onBuscar={onBuscar}
                        onActualizar={onActualizar}
                        buscando={cargando}
                        filtrosExtra={filtrosExtra}
                        onCambiarFiltros={onCambiarFiltros}
                        almacenes={catalogos.almacenes || []}
                    />
                </div>

                <TablaPedidos
                    pedidos={pedidosVista}
                    can={can}
                    tabActiva={tabActiva}
                    busqueda={busqueda}
                    cargando={cargando}
                    onLimpiarBusqueda={() => onBuscar('')}
                    onVerTodas={() => onTabChange('TODAS')}
                    onNuevo={can('control_pedidos.crear') ? abrirNuevo : undefined}
                    onIrAPagina={onIrAPagina}
                    onVer={abrirVer}
                    onBitacora={abrirBitacora}
                    onMovimientoTienda={(pedido, modo) => {
                        if (modo === 'modalidad' && (pedido.conversion_modalidad?.puede_iniciar || pedido.conversion_modalidad?.puede_continuar)) {
                            abrirConversion(pedido);
                            return;
                        }
                        setMovimientoTienda({ pedido, modo });
                    }}
                    onCambiarModalidad={abrirConversion}
                    onAgregarPiezas={(pedido) => setPiezasAdicionalesId(pedido.id)}
                    onCambioATienda={(pedido) => setCambioATiendaId(pedido.id)}
                    onSaldoEnvio={(pedido) => router.visit(route('saldos_favor.index', { saldo_envio_pedido: pedido.id }))}
                    onEditar={abrirEditar}
                    onEliminar={setPedidoAEliminar}
                    onEliminarRegistro={setPedidoAEliminarRegistro}
                    onRestaurar={setPedidoRestaurar}
                    onVerAuditoria={setPedidoAuditoria}
                    onCancelar={setPedidoACancelar}
                    onAnexarEnvio={(pedido) => setModalAnexo({ abierto: true, pedido })}
                    onAnexoExtraordinario={(pedido) => setModalAnexoExtraordinario({ abierto: true, pedido })}
                    onCompletarEnvio={(pedido) => setModalCompletarEnvio({ abierto: true, pedido })}
                    onCargarGuia={(pedido) => setModalCargarGuia({ abierto: true, pedido })}
                />
                </div>
            </GeliaPageShell>

            {modalForm.abierto ? (
                <ModalFormPedido
                    key={modalForm.instancia ?? (modalForm.recuperarBorrador ? 'new-draft' : 'new-clean')}
                    abierto
                    pedido={modalForm.pedido}
                    intencion={modalForm.intencion || 'alta'}
                    onActualizar={refrescarListado}
                    recuperarBorrador={modalForm.recuperarBorrador}
                    onPedidoCreado={(p) => {
                        setModalForm((m) => {
                            if (!m.abierto || !p?.id) return m;
                            return {
                                ...m,
                                pedidoIdVivo: p.id,
                                pedido: m.pedido?.id ? m.pedido : { ...(m.pedido || {}), id: p.id, folio: p.folio || m.pedido?.folio },
                            };
                        });
                    }}
                    catalogos={catalogos}
                    registrarTienda={registrar_tienda}
                    onLecturaPropia={setLecturaPropiaFormulario}
                    direccionesNormalizadas={direcciones_normalizadas}
                    onClose={() => {
                        setModalForm({ abierto: false, pedido: null, recuperarBorrador: false, instancia: 'new-clean', pedidoIdVivo: null, intencion: 'alta' });
                        refrescarListado();
                    }}
                />
            ) : null}
            <ModalAnexarPagoEnvio
                abierto={modalAnexo.abierto}
                pedido={modalAnexo.pedido}
                bancos={catalogos.bancos || []}
                onClose={() => setModalAnexo({ abierto: false, pedido: null })}
            />
            <ModalAnexoExtraordinario
                abierto={modalAnexoExtraordinario.abierto}
                pedido={modalAnexoExtraordinario.pedido}
                onClose={() => setModalAnexoExtraordinario({ abierto: false, pedido: null })}
                onCreado={() => router.reload({ preserveScroll: true })}
            />
            <ModalLiberarResguardoAbierto
                abierto={modalCompletarEnvio.abierto}
                pedido={modalCompletarEnvio.pedido}
                bancos={catalogos.bancos || []}
                catalogos={catalogos}
                routeName="control_pedidos.completar_envio_resguardo"
                titulo="Completar envío del resguardo"
                etiquetaConfirmar="Completar y anexar envío"
                onClose={() => setModalCompletarEnvio({ abierto: false, pedido: null })}
            />
            <ModalCargarGuiaCliente
                abierto={modalCargarGuia.abierto}
                pedido={modalCargarGuia.pedido}
                onClose={() => setModalCargarGuia({ abierto: false, pedido: null })}
            />
            <ModalDetallePedido
                abierto={modalDetalle.abierto}
                pedido={modalDetalle.pedido}
                onClose={() => setModalDetalle({ abierto: false, pedido: null })}
            />
            <ModalPiezasAdicionales
                abierto={piezasAdicionalesId !== null}
                pedido={(pedidosVista?.data || []).find((p) => p.id === piezasAdicionalesId) || null}
                bancos={catalogos.bancos || []}
                onClose={() => setPiezasAdicionalesId(null)}
                onActualizado={onActualizar}
            />
            <ModalCambioATiendaMunicipal
                abierto={cambioATiendaId !== null}
                pedido={(pedidosVista?.data || []).find((p) => p.id === cambioATiendaId) || null}
                onClose={() => setCambioATiendaId(null)}
                onActualizado={onActualizar}
            />
            <ModalMovimientosTienda
                abierto={Boolean(movimientoTienda)}
                pedido={movimientoTienda?.pedido}
                modo={movimientoTienda?.modo}
                onClose={() => setMovimientoTienda(null)}
                onContinuarConversion={(pedido) => {
                    setMovimientoTienda(null);
                    abrirConversion(pedido);
                }}
            />
            <ModalBitacoraPedido
                abierto={modalBitacora.abierto}
                pedido={modalBitacora.pedido}
                onClose={() => setModalBitacora({ abierto: false, pedido: null })}
            />
            <ModalCancelarPedido
                abierto={Boolean(pedidoACancelar)}
                pedido={pedidoACancelar}
                onClose={() => setPedidoACancelar(null)}
            />
            <ModalConfirmarAccion
                abierto={Boolean(pedidoRestaurar)}
                titulo="Restaurar registro"
                mensaje={`¿Restaurar el pedido ${pedidoRestaurar?.folio_remision || pedidoRestaurar?.folio || ''}? Volverá a aparecer en el listado operativo.`}
                etiquetaConfirmar="Restaurar"
                variante="primary"
                onClose={() => setPedidoRestaurar(null)}
                onConfirm={confirmarRestaurar}
            />
            <ModalAuditoriaEliminacionPedido
                abierto={Boolean(pedidoAuditoria)}
                pedido={pedidoAuditoria}
                onClose={() => setPedidoAuditoria(null)}
            />
            <ModalEliminarRegistroPedido
                abierto={Boolean(pedidoAEliminarRegistro)}
                pedido={pedidoAEliminarRegistro}
                onClose={() => setPedidoAEliminarRegistro(null)}
                onConfirm={confirmarEliminarRegistro}
            />
            <ModalConfirmarAccion
                abierto={Boolean(pedidoAEliminar)}
                titulo="Eliminar borrador"
                mensaje={`¿Eliminar el borrador ${etiquetaEliminar}?`}
                etiquetaConfirmar="Eliminar"
                variante="danger"
                onClose={() => setPedidoAEliminar(null)}
                onConfirm={confirmarEliminar}
            />
            <ModalConfirmarAccion
                abierto={confirmarBorradorNuevo}
                titulo="Borrador en curso"
                mensaje="Hay un pedido en borrador. ¿Desea continuar con ese borrador o iniciar uno nuevo en limpio? El borrador previo se conserva en la lista si ya se guardó en el servidor."
                etiquetaConfirmar="Iniciar limpio"
                etiquetaAlternativa="Continuar borrador"
                variante="primary"
                onClose={() => setConfirmarBorradorNuevo(false)}
                onConfirm={abrirNuevoLimpio}
                onAlternativa={abrirNuevoConBorrador}
            />
            <ModalGenerarLinkDireccion
                abierto={modalLinkDireccion}
                onClose={() => setModalLinkDireccion(false)}
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
