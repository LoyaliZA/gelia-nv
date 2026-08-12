import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus, FileSpreadsheet, Package, Link2, Loader2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import { geliaCardClass } from '../../utils/geliaTheme';
import FiltrosPedidos from './Partials/FiltrosPedidos';
import TablaPedidos from './Partials/TablaPedidos';
import ModalFormPedido, { hayBorradorPedidoLocal } from './Partials/ModalFormPedido';
import ModalDetallePedido from './Partials/ModalDetallePedido';
import ModalBitacoraPedido from './Partials/ModalBitacoraPedido';
import ModalCancelarPedido from './Partials/ModalCancelarPedido';
import ModalAlertaPedido from './Partials/ModalAlertaPedido';
import ModalConfirmarAccion from './Partials/ModalConfirmarAccion';
import ModalGenerarLinkDireccion from './Partials/ModalGenerarLinkDireccion';
import ModalAnexarPagoEnvio from './Partials/ModalAnexarPagoEnvio';
import ModalCargarGuiaCliente from './Partials/ModalCargarGuiaCliente';
import ModalLiberarResguardoAbierto from './Auditar/Partials/ModalLiberarResguardoAbierto';
import { BTN_PRIMARY, BTN_SECONDARY } from './Partials/pedidosBmaStyles';
import useListadoDiscreto from './Partials/useListadoDiscreto';

const REFRESCO_LISTADO_MS = 15000;

export default function Index({ auth, pedidos, metricas = {}, filtros = {}, catalogos = {}, direcciones_normalizadas = false }) {
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
    const [modalForm, setModalForm] = useState({ abierto: false, pedido: null, recuperarBorrador: false });
    const [confirmarBorradorNuevo, setConfirmarBorradorNuevo] = useState(false);
    const [modalDetalle, setModalDetalle] = useState({ abierto: false, pedido: null });
    const [modalBitacora, setModalBitacora] = useState({ abierto: false, pedido: null });
    const [pedidoAEliminar, setPedidoAEliminar] = useState(null);
    const [pedidoACancelar, setPedidoACancelar] = useState(null);
    const [modalLinkDireccion, setModalLinkDireccion] = useState(false);
    const [modalAnexo, setModalAnexo] = useState({ abierto: false, pedido: null });
    const [modalCompletarEnvio, setModalCompletarEnvio] = useState({ abierto: false, pedido: null });
    const [modalCargarGuia, setModalCargarGuia] = useState({ abierto: false, pedido: null });
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const debounceBusqueda = useRef(null);
    const refrescoPendiente = useRef(false);

    useEffect(() => {
        // Con el borrador abierto, el feedback va en el propio formulario (evitar alerta Index + click-through).
        if (modalForm.abierto) return;
        if (flash?.success) {
            setAlerta({ abierto: true, tipo: 'success', titulo: 'Operación exitosa', mensaje: flash.success });
        } else if (flash?.error) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: flash.error });
        }
    }, [flash?.success, flash?.error, modalForm.abierto]);

    useEffect(() => {
        const filas = pedidosVista?.data || [];
        const refrescar = (m) => {
            if (!m.abierto || !m.pedido?.id) return m;
            const fresco = filas.find((p) => p.id === m.pedido.id);
            if (!fresco) return m;
            // Misma fila del listado: actualizar si cambió algo relevante de CEDIS/servidor.
            if (
                fresco.updated_at === m.pedido.updated_at
                && fresco.pesaje_respondido_at === m.pedido.pesaje_respondido_at
                && fresco.estatus_envio === m.pedido.estatus_envio
                && fresco.catalogo_estatus_pedido_id === m.pedido.catalogo_estatus_pedido_id
                && Number(fresco.peso_real_kg) === Number(m.pedido.peso_real_kg)
                && Number(fresco.numero_cajas) === Number(m.pedido.numero_cajas)
            ) {
                return m;
            }
            return { ...m, pedido: fresco };
        };
        setModalForm(refrescar);
        setModalDetalle(refrescar);
    }, [pedidosVista]);

    // Con el formulario abierto sí seguimos refrescando el listado (CEDIS → modal).
    // Pausamos solo overlays que no necesitan sync en vivo.
    const pausarPollingListado = modalDetalle.abierto
        || modalBitacora.abierto
        || modalAnexo.abierto
        || modalCompletarEnvio.abierto
        || modalCargarGuia.abierto
        || modalLinkDireccion
        || confirmarBorradorNuevo
        || Boolean(pedidoAEliminar)
        || Boolean(pedidoACancelar);

    useEffect(() => {
        const params = {
            tab: tabActiva,
            q: busqueda || undefined,
            page: pedidosVista?.current_page || 1,
        };
        const refrescar = () => cargar(params, { silencioso: true });

        if (pausarPollingListado) {
            refrescoPendiente.current = true;
            return undefined;
        }
        if (refrescoPendiente.current) {
            refrescoPendiente.current = false;
            refrescar();
        }

        const intervalo = setInterval(refrescar, REFRESCO_LISTADO_MS);
        return () => clearInterval(intervalo);
    }, [pausarPollingListado, tabActiva, busqueda, pedidosVista?.current_page, cargar]);

    // Notificación en vivo (pesaje listo, errores CEDIS, etc.): refrescar al instante si el modal está abierto.
    useEffect(() => {
        const onNotification = (e) => {
            const pedidoId = Number(e.detail?.pedido_bma_id);
            const tipo = String(e.detail?.tipo || '');
            if (!pedidoId || !tipo.startsWith('pedido_')) return;
            if (!modalForm.abierto || Number(modalForm.pedido?.id) !== pedidoId) return;
            cargar(
                { tab: tabActiva, q: busqueda || undefined, page: pedidosVista?.current_page || 1 },
                { silencioso: true }
            );
        };
        window.addEventListener('notification-received', onNotification);
        return () => window.removeEventListener('notification-received', onNotification);
    }, [modalForm.abierto, modalForm.pedido?.id, tabActiva, busqueda, pedidosVista?.current_page, cargar]);

    const onTabChange = (tab) => {
        setTabActiva(tab);
        cargar({ tab, q: busqueda || undefined, page: 1 });
    };

    const onBuscar = (valor) => {
        setBusqueda(valor);
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            cargar({ tab: tabActiva, q: valor || undefined, page: 1 });
        }, 400);
    };

    const onIrAPagina = (page) => {
        cargar({ tab: tabActiva, q: busqueda || undefined, page });
    };

    const abrirNuevo = () => {
        if (hayBorradorPedidoLocal()) {
            setConfirmarBorradorNuevo(true);
            return;
        }
        setModalForm({ abierto: true, pedido: null, recuperarBorrador: false });
    };
    const abrirNuevoLimpio = () => {
        setConfirmarBorradorNuevo(false);
        setModalForm({ abierto: true, pedido: null, recuperarBorrador: false });
    };
    const abrirNuevoConBorrador = () => {
        setConfirmarBorradorNuevo(false);
        setModalForm({ abierto: true, pedido: null, recuperarBorrador: true });
    };
    const abrirEditar = (pedido) => setModalForm({ abierto: true, pedido, recuperarBorrador: false });
    const abrirVer = (pedido) => setModalDetalle({ abierto: true, pedido });
    const abrirBitacora = (pedido) => setModalBitacora({ abierto: true, pedido });

    const confirmarEliminar = () => {
        if (!pedidoAEliminar) return;
        const id = pedidoAEliminar.id;
        setPedidoAEliminar(null);
        router.delete(route('control_pedidos.destroy', id), { preserveScroll: true });
    };

    const exportarCsv = () => {
        window.location.href = route('control_pedidos.exportar', { tab: tabActiva, q: busqueda || '' });
    };

    const etiquetaEliminar = pedidoAEliminar?.folio_remision || pedidoAEliminar?.folio || 'este borrador';

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de pedidos | GELIANV" />
            <GeliaPageShell className="space-y-6">
                <header className={`${geliaCardClass()} p-6 md:p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4`}>
                    <div>
                        <div className="flex items-center gap-2 mb-2">
                            <Package className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Control de pedidos_</span>
                        </div>
                        <h1 className="text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                            Gestión de <span style={{ color: 'var(--color-primario)' }}>pedidos</span>
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        {can('control_pedidos.exportar') && (
                            <button type="button" onClick={exportarCsv} className={`${BTN_SECONDARY} flex items-center gap-2 outline-none`}>
                                <FileSpreadsheet className="w-4 h-4" /> Exportar CSV
                            </button>
                        )}
                        {can('clientes.direcciones.generar_enlace') && (
                            <button type="button" onClick={() => setModalLinkDireccion(true)} className={`${BTN_SECONDARY} flex items-center gap-2 outline-none`}>
                                <Link2 className="w-4 h-4" /> Link de dirección
                            </button>
                        )}
                        {can('control_pedidos.crear') && (
                            <button type="button" onClick={abrirNuevo} className={`${BTN_PRIMARY} flex items-center gap-2 outline-none`}>
                                <Plus className="w-4 h-4" /> Nuevo pedido
                            </button>
                        )}
                    </div>
                </header>

                <div className={`${geliaCardClass()} p-5`}>
                    <FiltrosPedidos
                        filtros={filtros}
                        tabActiva={tabActiva}
                        busqueda={busqueda}
                        onTabChange={onTabChange}
                        onBuscar={onBuscar}
                        metricas={metricasVista}
                        pedidos={pedidosVista}
                        onIrAPagina={onIrAPagina}
                        buscando={cargando}
                    />
                </div>

                <div className="relative min-h-[12rem]">
                    {cargando && (
                        <div className="absolute inset-0 z-10 flex items-start justify-center pt-16 pointer-events-none">
                            <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} aria-label="Cargando pedidos" />
                        </div>
                    )}
                    <TablaPedidos
                        pedidos={pedidosVista}
                        can={can}
                        onVer={abrirVer}
                        onBitacora={abrirBitacora}
                        onEditar={abrirEditar}
                        onEliminar={setPedidoAEliminar}
                        onCancelar={setPedidoACancelar}
                        onAnexarEnvio={(pedido) => setModalAnexo({ abierto: true, pedido })}
                        onCompletarEnvio={(pedido) => setModalCompletarEnvio({ abierto: true, pedido })}
                        onCargarGuia={(pedido) => setModalCargarGuia({ abierto: true, pedido })}
                    />
                </div>
            </GeliaPageShell>

            {modalForm.abierto ? (
                <ModalFormPedido
                    key={modalForm.pedido?.id ?? (modalForm.recuperarBorrador ? 'new-draft' : 'new-clean')}
                    abierto
                    pedido={modalForm.pedido}
                    recuperarBorrador={modalForm.recuperarBorrador}
                    catalogos={catalogos}
                    direccionesNormalizadas={direcciones_normalizadas}
                    onClose={() => setModalForm({ abierto: false, pedido: null, recuperarBorrador: false })}
                />
            ) : null}
            <ModalAnexarPagoEnvio
                abierto={modalAnexo.abierto}
                pedido={modalAnexo.pedido}
                bancos={catalogos.bancos || []}
                onClose={() => setModalAnexo({ abierto: false, pedido: null })}
            />
            <ModalLiberarResguardoAbierto
                abierto={modalCompletarEnvio.abierto}
                pedido={modalCompletarEnvio.pedido}
                bancos={catalogos.bancos || []}
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
