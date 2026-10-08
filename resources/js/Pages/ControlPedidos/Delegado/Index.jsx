import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { FileSpreadsheet, Loader2, Radio } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import { geliaCardClass } from '../../../utils/geliaTheme';
import TablaDelegado from './Partials/TablaDelegado';
import PanelImportExport from './Partials/PanelImportExport';
import MetricasBandejaDelegado from './Partials/MetricasBandejaDelegado';
import FiltrosDelegado from './Partials/FiltrosDelegado';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';
import { TABS_DELEGADO } from '../Partials/pedidosBmaStyles';
import useListadoDiscreto from '../Partials/useListadoDiscreto';

const POLL_MS = 180_000;
const COALESCE_MS = 1500;

const TIPOS_NOTIF_DELEGADO = new Set([
    'pedido_pendiente_guia',
    'pedido_error_guia',
    'pedido_guia_retraso',
    'pedido_retraso_empaque',
    'pedido_retraso_recoleccion',
    'pedido_enviado',
    'pedido_pendiente_envio',
]);

function normalizarPaqueteriaIdsInicial(filtros) {
    const raw = filtros?.paqueteria_ids;
    if (!Array.isArray(raw)) return [];
    return raw.map(String);
}

export default function Index({ auth, pedidos, metricas = {}, filtros = {}, catalogos = {} }) {
    const { flash } = usePage().props;
    const {
        pedidos: pedidosVista,
        metricas: metricasVista,
        cargando,
        cargar,
    } = useListadoDiscreto({
        listadoRoute: 'control_pedidos.delegado.listado',
        indexRoute: 'control_pedidos.delegado.index',
        pedidos,
        metricas,
    });

    const [tabActiva, setTabActiva] = useState(filtros.tab || 'PENDIENTES_GUIA');
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [ordenar, setOrdenar] = useState(filtros.ordenar || 'fecha_desc');
    const [situacion, setSituacion] = useState(filtros.situacion || '');
    const [paqueteriaIds, setPaqueteriaIds] = useState(() => normalizarPaqueteriaIdsInicial(filtros));
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const [ultimaSync, setUltimaSync] = useState(null);

    const debounceBusqueda = useRef(null);
    const modalAbiertoRef = useRef(false);
    const syncPendienteRef = useRef(false);
    const ultimoIntentoSyncRef = useRef(0);

    const paqueterias = catalogos?.paqueterias || [];

    const paramsListado = useCallback((extra = {}) => ({
        tab: tabActiva,
        q: busqueda || undefined,
        ordenar: ordenar && ordenar !== 'fecha_desc' ? ordenar : undefined,
        situacion: situacion || undefined,
        paqueteria_ids: paqueteriaIds.length
            ? paqueteriaIds.map((id) => Number(id))
            : undefined,
        page: pedidosVista?.current_page || 1,
        ...extra,
    }), [tabActiva, busqueda, ordenar, situacion, paqueteriaIds, pedidosVista?.current_page]);

    const puedeSincronizar = useCallback(() => (
        !modalAbiertoRef.current
        && !cargando
        && typeof document !== 'undefined'
        && document.visibilityState === 'visible'
    ), [cargando]);

    const cargarYMarcar = useCallback(async (params, opts) => {
        const data = await cargar(params, opts);
        if (data) setUltimaSync(new Date());
        return data;
    }, [cargar]);

    const solicitarRecargaSilenciosa = useCallback(() => {
        const ahora = Date.now();
        if (ahora - ultimoIntentoSyncRef.current < COALESCE_MS) return;
        ultimoIntentoSyncRef.current = ahora;

        if (!puedeSincronizar()) {
            if (modalAbiertoRef.current) {
                syncPendienteRef.current = true;
            }
            return;
        }
        cargarYMarcar(paramsListado(), { silencioso: true });
    }, [cargarYMarcar, paramsListado, puedeSincronizar]);

    const tabLabel = (TABS_DELEGADO.find((t) => t.id === tabActiva) || TABS_DELEGADO[0]).label;

    const idsPaqueteriaInvalidos = useMemo(
        () => paqueteriaIds.filter((id) => !paqueterias.some((p) => String(p.id) === String(id))),
        [paqueteriaIds, paqueterias]
    );

    const hayFiltrosAdicionalesActivos = Boolean(busqueda)
        || Boolean(situacion)
        || paqueteriaIds.length > 0
        || (ordenar && ordenar !== 'fecha_desc');

    const mensajeFiltroVacio = useMemo(() => {
        const sinResultados = (pedidosVista?.total ?? 0) === 0;
        if (!sinResultados) return null;
        if (idsPaqueteriaInvalidos.length > 0) {
            return 'No hay pedidos para las paqueterías de la URL: los identificadores no son válidos o no están activos en el catálogo.';
        }
        if (hayFiltrosAdicionalesActivos) {
            return 'Ningún pedido coincide con los filtros activos. Prueba otros criterios o usa Limpiar.';
        }
        return null;
    }, [pedidosVista?.total, idsPaqueteriaInvalidos.length, hayFiltrosAdicionalesActivos]);

    useEffect(() => {
        if (flash?.success) {
            setAlerta({ abierto: true, tipo: 'success', titulo: 'Operación exitosa', mensaje: flash.success });
            cargarYMarcar(paramsListado(), { silencioso: true });
        } else if (flash?.error) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: flash.error });
        }
    }, [flash?.success, flash?.error, cargarYMarcar, paramsListado]);

    useEffect(() => {
        const interval = setInterval(() => {
            if (!puedeSincronizar()) return;
            cargarYMarcar(paramsListado(), { silencioso: true });
        }, POLL_MS);
        return () => clearInterval(interval);
    }, [cargarYMarcar, paramsListado, puedeSincronizar]);

    useEffect(() => {
        if (!cargando && pedidosVista) {
            setUltimaSync(new Date());
        }
    }, [cargando, pedidosVista?.data]);

    useEffect(() => {
        const onNotification = (e) => {
            const tipo = e.detail?.tipo;
            if (!TIPOS_NOTIF_DELEGADO.has(tipo)) return;

            solicitarRecargaSilenciosa();

            if (tipo === 'pedido_pendiente_guia') {
                setAlerta({
                    abierto: true,
                    tipo: 'success',
                    titulo: 'Nuevo pedido pendiente de guía',
                    mensaje: e.detail?.mensaje || e.detail?.mensaje_visible || 'Hay un pedido listo para captura de guía.',
                });
            }
        };
        window.addEventListener('notification-received', onNotification);
        return () => window.removeEventListener('notification-received', onNotification);
    }, [solicitarRecargaSilenciosa]);

    const onModalAbierto = useCallback((abierto) => {
        const estabaAbierto = modalAbiertoRef.current;
        modalAbiertoRef.current = abierto;
        if (estabaAbierto && !abierto && syncPendienteRef.current) {
            syncPendienteRef.current = false;
            cargarYMarcar(paramsListado(), { silencioso: true });
        }
    }, [cargarYMarcar, paramsListado]);

    const onBuscar = (valor) => {
        setBusqueda(valor);
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            cargarYMarcar(paramsListado({ q: valor || undefined, page: 1 }));
        }, 400);
    };

    const onTabChange = (tab) => {
        setTabActiva(tab);
        cargarYMarcar(paramsListado({ tab, page: 1 }));
    };

    const onPaqueteriaIdsChange = (ids) => {
        const normalizados = (ids || []).map(String);
        setPaqueteriaIds(normalizados);
        cargarYMarcar(paramsListado({
            paqueteria_ids: normalizados.length ? normalizados.map((id) => Number(id)) : undefined,
            page: 1,
        }));
    };

    const onSituacionChange = (valor) => {
        setSituacion(valor || '');
        cargarYMarcar(paramsListado({ situacion: valor || undefined, page: 1 }));
    };

    const onOrdenarChange = (valor) => {
        const next = valor || 'fecha_desc';
        setOrdenar(next);
        cargarYMarcar(paramsListado({
            ordenar: next !== 'fecha_desc' ? next : undefined,
            page: 1,
        }));
    };

    const onLimpiarFiltros = () => {
        setBusqueda('');
        setPaqueteriaIds([]);
        setSituacion('');
        setOrdenar('fecha_desc');
        cargarYMarcar({ tab: tabActiva, page: 1 });
    };

    const onActualizar = () => {
        if (cargando) return;
        cargarYMarcar(paramsListado());
    };

    const onIrAPagina = (page) => {
        cargarYMarcar(paramsListado({ page }));
    };

    const syncAside = (
        <div className="flex flex-col items-end gap-1 text-right" aria-live="polite">
            <span className="inline-flex items-center gap-2 text-xs font-semibold theme-text-muted">
                {cargando ? (
                    <Loader2 className="w-3.5 h-3.5 animate-spin" aria-hidden />
                ) : (
                    <Radio className="w-3.5 h-3.5 text-primario" aria-hidden />
                )}
                {cargando ? 'Sincronizando…' : 'Alertas en vivo · respaldo 3 min'}
            </span>
            {ultimaSync && !cargando && (
                <span className="text-[11px] theme-text-muted font-medium">
                    Actualizado {ultimaSync.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}
                </span>
            )}
        </div>
    );

    return (
        <AppLayout auth={auth}>
            <Head title="Actualizar guías | GELIANV" />
            <GeliaPageShell className="gelia-tienda-op space-y-4 md:space-y-6">
                <GeliaTituloCard
                    eyebrow="Control de pedidos"
                    title="Actualizar"
                    titleHighlight="guías"
                    description="Revisa datos, captura o corrige guías, y reporta errores al área correspondiente."
                    icon={FileSpreadsheet}
                    aside={syncAside}
                />

                <div className={`${geliaCardClass()} gelia-tienda-op-workspace p-4 md:p-5 space-y-4`}>
                    <MetricasBandejaDelegado
                        metricas={metricasVista}
                        tabActiva={tabActiva}
                        onTabChange={onTabChange}
                    />
                    <div className="gelia-tienda-op-divider" aria-hidden />
                    <FiltrosDelegado
                        busqueda={busqueda}
                        paqueteriaIds={paqueteriaIds}
                        situacion={situacion}
                        ordenar={ordenar}
                        paqueterias={paqueterias}
                        onBuscar={onBuscar}
                        onPaqueteriaIdsChange={onPaqueteriaIdsChange}
                        onSituacionChange={onSituacionChange}
                        onOrdenarChange={onOrdenarChange}
                        onLimpiarFiltros={onLimpiarFiltros}
                        onActualizar={onActualizar}
                        buscando={cargando}
                        toolbarAside={
                            tabActiva === 'PENDIENTES_GUIA'
                                ? (
                                    <PanelImportExport
                                        onAlerta={setAlerta}
                                        onImportSuccess={() => cargarYMarcar(paramsListado())}
                                    />
                                )
                                : null
                        }
                    />
                </div>

                <TablaDelegado
                    pedidos={pedidosVista}
                    tabActiva={tabActiva}
                    tabLabel={tabLabel}
                    busqueda={busqueda}
                    cargando={cargando}
                    onModalAbierto={onModalAbierto}
                    onIrAPagina={onIrAPagina}
                    onLimpiarBusqueda={() => onBuscar('')}
                    mensajeFiltroVacio={mensajeFiltroVacio}
                    onLimpiarFiltros={onLimpiarFiltros}
                />
            </GeliaPageShell>

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
