import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import EncabezadoBandejaAuditoria from './Partials/EncabezadoBandejaAuditoria';
import MetricasBandejaAuditoria from './Partials/MetricasBandejaAuditoria';
import FiltrosAuditoria from './Partials/FiltrosAuditoria';
import TablaAuditoria from './Partials/TablaAuditoria';
import ModalRevisarPedido from './Partials/ModalRevisarPedido';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';
import ModalAnexarPagoEnvio from '../Partials/ModalAnexarPagoEnvio';
import ModalBitacoraPedido from '../Partials/ModalBitacoraPedido';
import useListadoDiscreto from '../Partials/useListadoDiscreto';

const REFRESCO_LISTADO_MS = 15000;

export default function Index({ auth, pedidos, metricas = {}, filtros = {}, catalogos = {} }) {
    const { flash } = usePage().props;

    const {
        pedidos: pedidosVista,
        metricas: metricasVista,
        cargando,
        cargar,
    } = useListadoDiscreto({
        listadoRoute: 'control_pedidos.auditar.listado',
        indexRoute: 'control_pedidos.auditar.index',
        pedidos,
        metricas,
    });

    const [tabActiva, setTabActiva] = useState(filtros.tab || 'PENDIENTES');
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [paqueteriaId, setPaqueteriaId] = useState(filtros.catalogo_paqueteria_id || '');
    const [departamentoId, setDepartamentoId] = useState(filtros.departamento_id || '');
    const [clienteFiltro, setClienteFiltro] = useState(filtros.cliente || '');
    const [ordenar, setOrdenar] = useState(filtros.ordenar || 'fecha_desc');
    const [modalRevisar, setModalRevisar] = useState({ abierto: false, pedido: null });
    const [modalAnexo, setModalAnexo] = useState({ abierto: false, pedido: null });
    const [modalBitacora, setModalBitacora] = useState({ abierto: false, pedido: null });
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const [ultimaSync, setUltimaSync] = useState(null);
    const debounceBusqueda = useRef(null);
    const debounceCliente = useRef(null);
    const modalAbiertoRef = useRef(false);

    const paramsListado = (extra = {}) => ({
        tab: tabActiva,
        q: busqueda || undefined,
        catalogo_paqueteria_id: paqueteriaId || undefined,
        departamento_id: departamentoId || undefined,
        cliente: clienteFiltro || undefined,
        ordenar: ordenar && ordenar !== 'fecha_desc' ? ordenar : undefined,
        page: pedidosVista?.current_page || 1,
        ...extra,
    });

    const cargarYMarcar = useCallback(async (params, opts) => {
        const data = await cargar(params, opts);
        if (data) setUltimaSync(new Date());
        return data;
    }, [cargar]);

    useEffect(() => {
        if (flash?.success) {
            setAlerta({ abierto: true, tipo: 'success', titulo: 'Operación exitosa', mensaje: flash.success });
        } else if (flash?.error) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: flash.error });
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        modalAbiertoRef.current = modalRevisar.abierto || modalAnexo.abierto || modalBitacora.abierto;
    }, [modalRevisar.abierto, modalAnexo.abierto, modalBitacora.abierto]);

    useEffect(() => {
        const interval = setInterval(() => {
            if (modalAbiertoRef.current || cargando) return;
            cargarYMarcar(paramsListado(), { silencioso: true });
        }, REFRESCO_LISTADO_MS);
        return () => clearInterval(interval);
    }, [cargando, tabActiva, busqueda, paqueteriaId, departamentoId, clienteFiltro, ordenar, pedidosVista?.current_page, cargarYMarcar]);

    const onTabChange = (tab) => {
        setTabActiva(tab);
        cargarYMarcar(paramsListado({ tab, page: 1 }));
    };

    const onBuscar = (valor) => {
        setBusqueda(valor);
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            cargarYMarcar(paramsListado({ q: valor || undefined, page: 1 }));
        }, 400);
    };

    const onPaqueteriaChange = (valor) => {
        setPaqueteriaId(valor);
        cargarYMarcar(paramsListado({ catalogo_paqueteria_id: valor || undefined, page: 1 }));
    };

    const onDepartamentoChange = (valor) => {
        setDepartamentoId(valor);
        cargarYMarcar(paramsListado({ departamento_id: valor || undefined, page: 1 }));
    };

    const onClienteFiltroChange = (valor) => {
        setClienteFiltro(valor);
        if (debounceCliente.current) clearTimeout(debounceCliente.current);
        debounceCliente.current = setTimeout(() => {
            cargarYMarcar(paramsListado({ cliente: valor || undefined, page: 1 }));
        }, 400);
    };

    const onOrdenarChange = (valor) => {
        setOrdenar(valor || 'fecha_desc');
        cargarYMarcar(paramsListado({
            ordenar: valor && valor !== 'fecha_desc' ? valor : undefined,
            page: 1,
        }));
    };

    const onLimpiarFiltros = () => {
        setTabActiva('PENDIENTES');
        setBusqueda('');
        setPaqueteriaId('');
        setDepartamentoId('');
        setClienteFiltro('');
        setOrdenar('fecha_desc');
        cargarYMarcar({ tab: 'PENDIENTES', page: 1 });
    };

    const onIrAPagina = (page) => {
        cargarYMarcar(paramsListado({ page }));
    };

    const onActualizar = () => {
        cargarYMarcar(paramsListado());
    };

    const abrirRevisar = (pedido) => setModalRevisar({ abierto: true, pedido });
    const abrirAnexar = (pedido) => setModalAnexo({ abierto: true, pedido });
    const abrirBitacora = (pedido) => setModalBitacora({ abierto: true, pedido });

    const atencion = (metricasVista.pendientes ?? 0) + (metricasVista.corregidos ?? 0);
    const hayFiltrosActivos = Boolean(busqueda) || Boolean(paqueteriaId) || Boolean(departamentoId)
        || Boolean(clienteFiltro) || (ordenar && ordenar !== 'fecha_desc');

    return (
        <AppLayout auth={auth}>
            <Head title="Revisión de pedidos | GELIANV" />
            <GeliaPageShell className="gelia-tienda-op gelia-pedidos-bma space-y-4 md:space-y-6">
                <div className="gelia-pedidos-bma-contenido space-y-4 md:space-y-6">
                    <EncabezadoBandejaAuditoria
                        cargando={cargando}
                        ultimaSync={ultimaSync}
                        atencion={atencion}
                    />

                    <div className={`${geliaCardClass()} gelia-tienda-op-workspace p-4 md:p-5 space-y-4`}>
                        <MetricasBandejaAuditoria
                            metricas={metricasVista}
                            tabActiva={tabActiva}
                            onTabChange={onTabChange}
                        />
                        <div className="gelia-tienda-op-divider" aria-hidden />
                        <FiltrosAuditoria
                            tabActiva={tabActiva}
                            busqueda={busqueda}
                            paqueteriaId={paqueteriaId}
                            departamentoId={departamentoId}
                            clienteFiltro={clienteFiltro}
                            ordenar={ordenar}
                            paqueterias={catalogos.paqueterias || []}
                            departamentos={catalogos.departamentos || []}
                            onTabChange={onTabChange}
                            onBuscar={onBuscar}
                            onPaqueteriaChange={onPaqueteriaChange}
                            onDepartamentoChange={onDepartamentoChange}
                            onClienteFiltroChange={onClienteFiltroChange}
                            onOrdenarChange={onOrdenarChange}
                            onLimpiarFiltros={onLimpiarFiltros}
                            onActualizar={onActualizar}
                            metricas={metricasVista}
                            buscando={cargando}
                        />
                    </div>

                    <TablaAuditoria
                        pedidos={pedidosVista}
                        tabActiva={tabActiva}
                        cargando={cargando}
                        hayFiltrosActivos={hayFiltrosActivos}
                        onLimpiarFiltros={onLimpiarFiltros}
                        onIrAPagina={onIrAPagina}
                        onRevisar={abrirRevisar}
                        onAnexarEnvio={abrirAnexar}
                        onBitacora={abrirBitacora}
                        onFiltrarBusqueda={onBuscar}
                        onFiltrarPaqueteria={onPaqueteriaChange}
                        onFiltrarTab={onTabChange}
                    />
                </div>
            </GeliaPageShell>

            <ModalRevisarPedido
                abierto={modalRevisar.abierto}
                pedido={modalRevisar.pedido}
                bancos={catalogos.bancos || []}
                catalogos={catalogos}
                onClose={() => setModalRevisar({ abierto: false, pedido: null })}
            />
            <ModalBitacoraPedido
                abierto={modalBitacora.abierto}
                pedido={modalBitacora.pedido}
                onClose={() => setModalBitacora({ abierto: false, pedido: null })}
            />
            <ModalAnexarPagoEnvio
                abierto={modalAnexo.abierto}
                pedido={modalAnexo.pedido}
                bancos={catalogos.bancos || []}
                routeName="control_pedidos.auditar.anexar_pago_envio"
                onClose={() => setModalAnexo({ abierto: false, pedido: null })}
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
