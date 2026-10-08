import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Package, Loader2, AlertTriangle, Truck, History } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import {
    geliaCardClass,
    GELIA_BTN_OUTLINE,
    GELIA_PREVENT_OVERFLOW_X,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
} from '../../../utils/geliaTheme';
import BarraStickySeleccionResguardo from './Partials/BarraStickySeleccionResguardo';
import FiltrosResguardos from './Partials/FiltrosResguardos';
import ListadoResguardos from './Partials/ListadoResguardos';
import AlertasCustodiaResguardo from './Partials/AlertasCustodiaResguardo';
import SelectorSucursalActivaPdv, { RELOAD_ONLY_RESGUARDOS } from '@/Components/PuntoVenta/SelectorSucursalActivaPdv';
import AccionRegistrarResguardoManual from './Partials/AccionRegistrarResguardoManual';
import ModalDetalleResguardo from './Partials/ModalDetalleResguardo';
import useListadoResguardos from './Partials/useListadoResguardos';
import BarraAccionesMasivasGerente from './Partials/BarraAccionesMasivasGerente';
import { antiguedadValidaEnBandeja, metricasAntiguedadClaves, paramsListadoResguardos } from './Partials/resguardosUtils';
import { resguardoSeleccionableGerente } from './Partials/recepcionGerenteApi';
import { useDetalleResguardoModalBridge } from './Partials/resguardoDetalleModalBridge';
import PdvAlertProvider, { usePdvAlertReload } from '../../../Components/PuntoVenta/PdvAlertProvider';
import PdvEncabezadoAlertasPdv from '../../../Components/PuntoVenta/PdvEncabezadoAlertasPdv';
import useToastAlCambiar from '../../../hooks/useToastAlCambiar';

const BANDEJAS = ['por_recibir', 'en_custodia', 'incidencias'];

export default function Index({
    auth,
    resguardos,
    metricas = {},
    filtros = {},
    bandeja: bandejaInicial,
    catalogos = {},
    permisos = {},
    sucursal_activa: sucursalActiva = null,
    sucursales_asignadas: sucursalesAsignadas = [],
    operativa = {},
    detalle_modal_id: detalleModalIdInicial = null,
    puede_ver_historial_entregas: puedeVerHistorialEntregas = false,
}) {
    const antiguedadConfigurada = Boolean(operativa.antiguedad_configurada);
    const puedeRegistrarManual = Boolean(operativa.registro_manual) && Boolean(permisos.registrar_manual);
    const puedeConfirmarLlegada = Boolean(permisos.confirmar_llegada);
    const puedeEnviarACustodia = Boolean(permisos.enviar_a_custodia);
    const puedePasoLlegada = puedeConfirmarLlegada || puedeEnviarACustodia;

    const {
        resguardos: resguardosVista,
        metricas: metricasVista,
        bandeja: bandejaVista,
        cargando,
        error,
        cargar,
    } = useListadoResguardos({
        listadoRoute: 'punto_venta.resguardos.listado',
        indexRoute: 'punto_venta.resguardos.index',
        resguardos,
        metricas,
        bandeja: bandejaInicial || filtros.bandeja || 'por_recibir',
    });

    useToastAlCambiar(error, 'error');

    const [bandejaActiva, setBandejaActiva] = useState(filtros.bandeja || bandejaInicial || 'por_recibir');
    const [pasoActivo, setPasoActivo] = useState(filtros.paso || 'gerente');
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [estado, setEstado] = useState(filtros.estado || '');
    const [antiguedad, setAntiguedad] = useState(filtros.antiguedad || '');
    const [origenId, setOrigenId] = useState(filtros.origen_id ? String(filtros.origen_id) : '');
    const [alta, setAlta] = useState(filtros.alta || '');
    const [evidencia, setEvidencia] = useState(filtros.evidencia || '');
    const [altaHoy, setAltaHoy] = useState(Boolean(filtros.alta_hoy));
    const [idsSeleccionados, setIdsSeleccionados] = useState([]);
    const [detalleResguardoId, setDetalleResguardoId] = useState(null);
    const [detalleResguardoResumen, setDetalleResguardoResumen] = useState(null);
    const debounceBusqueda = useRef(null);

    const abrirDetalleResguardo = useCallback((id, resumen = null) => {
        setDetalleResguardoId(id);
        setDetalleResguardoResumen(resumen);
    }, []);

    const cerrarDetalleResguardo = useCallback(() => {
        setDetalleResguardoId(null);
        setDetalleResguardoResumen(null);
    }, []);

    useDetalleResguardoModalBridge(abrirDetalleResguardo);

    useEffect(() => {
        if (!detalleModalIdInicial) {
            return;
        }
        abrirDetalleResguardo(detalleModalIdInicial);
    }, [detalleModalIdInicial, abrirDetalleResguardo]);

    useEffect(() => {
        setBandejaActiva(filtros.bandeja || bandejaInicial || 'por_recibir');
        setPasoActivo(filtros.paso || 'gerente');
        setBusqueda(filtros.q || '');
        setEstado(filtros.estado || '');
        setAntiguedad(filtros.antiguedad || '');
        setOrigenId(filtros.origen_id ? String(filtros.origen_id) : '');
        setAlta(filtros.alta || '');
        setEvidencia(filtros.evidencia || '');
        setAltaHoy(Boolean(filtros.alta_hoy));
    }, [filtros, bandejaInicial]);

    useEffect(() => {
        if (bandejaActiva !== 'por_recibir') return;
        const visibles = [];
        if (puedePasoLlegada) visibles.push('gerente');
        if (permisos.confirmar_custodia) visibles.push('recepcionista');
        if (!visibles.length || visibles.includes(pasoActivo)) return;
        const siguiente = visibles[0];
        setPasoActivo(siguiente);
        recargarRef.current({ paso: siguiente, page: 1 });
    }, [bandejaActiva, pasoActivo, puedePasoLlegada, permisos.confirmar_custodia]);

    const paramsActuales = (extra = {}) => paramsListadoResguardos({
        bandeja: bandejaActiva,
        paso: bandejaActiva === 'por_recibir' ? pasoActivo : undefined,
        q: busqueda,
        estado,
        antiguedad,
        origenId,
        alta,
        evidencia,
        altaHoy,
        page: resguardosVista?.current_page || 1,
        ...extra,
    });

    const recargar = (extra = {}, opts) => cargar(paramsActuales(extra), opts);
    const recargarRef = useRef(recargar);

    useEffect(() => {
        recargarRef.current = recargar;
    });

    const recargarSilencioso = useCallback(() => {
        recargarRef.current({ page: resguardosVista?.current_page || 1 }, { silencioso: true });
    }, [resguardosVista?.current_page]);

    const onBandeja = (nuevaBandeja) => {
        setBandejaActiva(nuevaBandeja);
        const extra = { bandeja: nuevaBandeja, page: 1 };

        if (nuevaBandeja === 'por_recibir' && estado) {
            setEstado('');
            extra.estado = undefined;
        }
        if (!antiguedadValidaEnBandeja(nuevaBandeja, antiguedad)) {
            setAntiguedad('');
            extra.antiguedad = undefined;
        }

        recargar(extra);
        setIdsSeleccionados([]);
    };

    const onBusqueda = (valor) => {
        setBusqueda(valor);
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            recargar({ q: valor || undefined, page: 1 });
        }, 400);
    };

    const onEstado = (valor) => {
        setEstado(valor);
        recargar({ estado: valor || undefined, page: 1 });
    };

    const onAntiguedad = (valor) => {
        setAntiguedad(valor);
        recargar({ antiguedad: valor || undefined, page: 1 });
    };

    const onOrigen = (valor) => {
        setOrigenId(valor);
        recargar({ origenId: valor || undefined, page: 1 });
    };

    const onAlta = (valor) => {
        setAlta(valor);
        recargar({ alta: valor || undefined, page: 1 });
    };

    const onEvidencia = (valor) => {
        setEvidencia(valor);
        recargar({ evidencia: valor || undefined, page: 1 });
    };

    const onAltaHoy = (valor) => {
        setAltaHoy(valor);
        recargar({ altaHoy: valor || undefined, page: 1 });
    };

    const onLimpiar = () => {
        setBusqueda('');
        setEstado('');
        setAntiguedad('');
        setOrigenId('');
        setAlta('');
        setEvidencia('');
        setAltaHoy(false);
        recargar({
            q: undefined,
            estado: undefined,
            antiguedad: undefined,
            origenId: undefined,
            alta: undefined,
            evidencia: undefined,
            altaHoy: undefined,
            page: 1,
        });
    };

    const onIrAPagina = (page) => {
        recargar({ page });
        setIdsSeleccionados([]);
    };

    const toggleSeleccion = (id) => {
        setIdsSeleccionados((prev) => (
            prev.includes(id) ? prev.filter((actual) => actual !== id) : [...prev, id]
        ));
    };

    const irAEntregaMultiple = () => {
        if (idsSeleccionados.length < 2) return;
        router.visit(route('punto_venta.resguardos.entregas_multiples.create', { ids: idsSeleccionados }));
    };

    const hayFiltrosActivos = Boolean(busqueda || estado || antiguedad || origenId || alta || evidencia || altaHoy);

    const bandejaRender = bandejaVista || bandejaActiva;
    const puedeConfirmarEscaneo = puedeConfirmarLlegada
        && bandejaRender === 'por_recibir'
        && pasoActivo === 'gerente';
    const antiguedadEnTarjetas = antiguedadConfigurada
        && metricasAntiguedadClaves(
            bandejaRender,
            Boolean(permisos.ver_vencidos),
            Boolean(permisos.ver_rezagados),
        ).length > 0;

    const idsSeleccionablesPagina = useMemo(() => {
        const data = resguardosVista?.data || [];
        if (puedePasoLlegada && bandejaRender === 'por_recibir' && pasoActivo === 'gerente') {
            return data.filter((item) => resguardoSeleccionableGerente(item, {
                confirmarLlegada: puedeConfirmarLlegada,
                enviarACustodia: puedeEnviarACustodia,
            })).map((item) => item.id);
        }
        if (Boolean(permisos.entregar) && bandejaRender === 'en_custodia') {
            return data
                .filter((item) => item.estado === 'en_custodia' && !item.entrega_bloqueada)
                .map((item) => item.id);
        }
        return [];
    }, [resguardosVista, puedePasoLlegada, puedeConfirmarLlegada, puedeEnviarACustodia, permisos.entregar, bandejaRender, pasoActivo]);
    const paginaSeleccionada = idsSeleccionablesPagina.length > 0
        && idsSeleccionablesPagina.every((id) => idsSeleccionados.includes(id));

    const metricasBandeja = useMemo(() => (
        BANDEJAS.map((clave) => ({
            clave,
            etiqueta: catalogos.bandejas?.[clave] || clave,
            total: metricasVista?.[clave] ?? 0,
        }))
    ), [catalogos.bandejas, metricasVista]);

    return (
        <AppLayout auth={auth}>
            <Head title="Resguardos | Punto de Venta" />
            <PdvAlertProvider
                sucursalId={sucursalActiva?.id}
                userId={auth?.user?.id}
                habilitado={Boolean(sucursalActiva?.id)}
            >
                <ResguardosRealtimeSync refrescar={recargarSilencioso} />
                <GeliaPageShell className={`space-y-3 md:space-y-4 pb-[max(5.5rem,env(safe-area-inset-bottom))] md:pb-6 ${GELIA_PREVENT_OVERFLOW_X}`}>
                <GeliaTituloCard
                    eyebrow="Punto de Venta"
                    title="Resguardos"
                    titleHighlight="en sucursal"
                    icon={null}
                    aside={(
                        <div className="flex flex-wrap items-center justify-end gap-2 w-full sm:w-auto shrink-0 self-start md:self-center">
                            <PdvEncabezadoAlertasPdv />
                            {puedeVerHistorialEntregas && (
                                <Link
                                    href={route('punto_venta.resguardos.entregados.index')}
                                    className={`${GELIA_BTN_OUTLINE} min-h-[44px] px-3 sm:px-4 no-underline text-xs`}
                                >
                                    <History className="w-4 h-4 shrink-0" aria-hidden />
                                    <span className="hidden min-[400px]:inline">Historial entregas</span>
                                    <span className="min-[400px]:hidden">Historial</span>
                                </Link>
                            )}
                            <AccionRegistrarResguardoManual
                                habilitado={puedeRegistrarManual}
                                origenes={catalogos.origenes_pedido || []}
                                onExito={(data) => recargar({
                                    bandeja: 'por_recibir',
                                    paso: data?.resguardo?.estado === 'en_recepcion' ? 'recepcionista' : 'gerente',
                                    page: 1,
                                })}
                            />
                            <div className="hidden sm:flex p-2.5 rounded-xl theme-element border theme-border items-center justify-center" aria-hidden>
                                <Package className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                            </div>
                        </div>
                    )}
                    className="!p-4 md:!p-5 lg:!p-6 !gap-3 md:!gap-4 [&_h1]:!text-2xl sm:[&_h1]:!text-3xl md:[&_h1]:!text-3xl"
                >
                    <SelectorSucursalActivaPdv
                        sucursalActiva={sucursalActiva}
                        sucursalesAsignadas={sucursalesAsignadas}
                        variante="compacto"
                        reloadOnly={RELOAD_ONLY_RESGUARDOS}
                    />
                </GeliaTituloCard>

                <div className="w-full min-w-0 max-w-full">
                    <div
                        className="gelia-segment flex w-full min-w-0 max-w-full p-1 shadow-sm"
                        role="tablist"
                        aria-label="Bandejas de resguardos"
                    >
                        {metricasBandeja.map(({ clave, etiqueta, total }) => {
                            const activa = bandejaRender === clave;
                            return (
                                <button
                                    key={clave}
                                    type="button"
                                    role="tab"
                                    aria-selected={activa}
                                    data-active={activa}
                                    onClick={() => onBandeja(clave)}
                                    className="gelia-segment-btn flex-1 min-w-0 !flex-col sm:!flex-row gap-0.5 sm:gap-2 px-1 py-2 sm:px-2.5 leading-tight"
                                >
                                    <span className="text-[0.625rem] sm:text-xs text-center leading-snug line-clamp-2">{etiqueta}</span>
                                    <span
                                        className={`shrink-0 text-[10px] sm:text-[11px] font-bold px-1 py-0.5 rounded-md tabular-nums border ${
                                            activa
                                                ? 'border-[var(--color-primario)]/30 bg-[var(--color-primario)]/10 text-[var(--color-primario)]'
                                                : 'theme-element theme-border theme-text-muted'
                                        }`}
                                    >
                                        {total}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {(bandejaRender === 'en_custodia' || (bandejaRender === 'por_recibir' && Boolean(permisos.ver_rezagados))) && (
                    <AlertasCustodiaResguardo
                        bandeja={bandejaRender}
                        catalogos={catalogos}
                        metricas={metricasVista}
                        totalBandeja={metricasVista?.[bandejaRender] ?? 0}
                        antiguedadActiva={antiguedad}
                        onAntiguedad={onAntiguedad}
                        antiguedadConfigurada={antiguedadConfigurada}
                        puedeVerVencidos={Boolean(permisos.ver_vencidos)}
                        puedeVerRezagados={Boolean(permisos.ver_rezagados)}
                    />
                )}

                <FiltrosResguardos
                    bandeja={bandejaRender}
                    busqueda={busqueda}
                    onBusqueda={onBusqueda}
                    estado={estado}
                    onEstado={onEstado}
                    antiguedad={antiguedad}
                    onAntiguedad={onAntiguedad}
                    catalogos={catalogos}
                    puedeVerVencidos={Boolean(permisos.ver_vencidos)}
                    puedeVerRezagados={Boolean(permisos.ver_rezagados)}
                    antiguedadConfigurada={antiguedadConfigurada}
                    cargando={cargando}
                    hayFiltrosActivos={hayFiltrosActivos}
                    onLimpiar={onLimpiar}
                    puedeConfirmarEscaneo={puedeConfirmarEscaneo}
                    ocultarAntiguedad={antiguedadEnTarjetas}
                    onRecepcionExito={() => recargar({ page: resguardosVista?.current_page || 1 })}
                    origenId={origenId}
                    onOrigen={onOrigen}
                    alta={alta}
                    onAlta={onAlta}
                    evidencia={evidencia}
                    onEvidencia={onEvidencia}
                    altaHoy={altaHoy}
                    onAltaHoy={onAltaHoy}
                />

                {cargando && !resguardosVista?.data?.length ? (
                    <div className={`${geliaCardClass()} p-12 flex flex-col items-center gap-3`}>
                        <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} />
                        <p className="text-sm theme-text-muted font-semibold m-0">Cargando resguardos…</p>
                    </div>
                ) : (
                    <ListadoResguardos
                        resguardos={resguardosVista}
                        bandeja={bandejaRender}
                        catalogos={catalogos}
                        permisos={permisos}
                        hayFiltrosActivos={hayFiltrosActivos}
                        onLimpiarFiltros={onLimpiar}
                        puedeConfirmarLlegada={puedeConfirmarLlegada}
                        puedeEnviarACustodia={puedeEnviarACustodia}
                        puedeConfirmarCustodia={Boolean(permisos.confirmar_custodia)}
                        paso={bandejaRender === 'por_recibir' ? pasoActivo : undefined}
                        onPaso={(id) => {
                            setPasoActivo(id);
                            recargar({ paso: id, page: 1 });
                            setIdsSeleccionados([]);
                        }}
                        puedeEntregar={Boolean(permisos.entregar)}
                        idsSeleccionados={idsSeleccionados}
                        onToggleSeleccion={
                            (permisos.entregar && bandejaRender === 'en_custodia')
                            || (puedePasoLlegada && bandejaRender === 'por_recibir' && pasoActivo === 'gerente')
                                ? toggleSeleccion
                                : undefined
                        }
                        onReponerExito={() => recargar({ page: resguardosVista?.current_page || 1 })}
                        onEntregaExito={() => recargar({ page: resguardosVista?.current_page || 1 })}
                        onRecepcionExito={(data) => {
                            const estado = data?.resguardo?.estado;
                            if (estado === 'en_recepcion') {
                                setPasoActivo('recepcionista');
                                recargar({ paso: 'recepcionista', page: 1 });
                                return;
                            }
                            recargar({ page: resguardosVista?.current_page || 1 });
                        }}
                        onSeleccionarPagina={() => setIdsSeleccionados(idsSeleccionablesPagina)}
                        paginaSeleccionada={paginaSeleccionada}
                        idsSeleccionablesPagina={idsSeleccionablesPagina}
                    />
                )}

                <ModalDetalleResguardo
                    abierto={detalleResguardoId != null}
                    resguardoId={detalleResguardoId}
                    resguardoResumen={detalleResguardoResumen}
                    onClose={cerrarDetalleResguardo}
                    onAccionExito={() => recargar({ page: resguardosVista?.current_page || 1 }, { silencioso: true })}
                />

                {puedePasoLlegada && bandejaRender === 'por_recibir' && pasoActivo === 'gerente' && idsSeleccionados.length > 0 && (
                    <BarraAccionesMasivasGerente
                        resguardos={resguardosVista?.data || []}
                        idsSeleccionados={idsSeleccionados}
                        puedeConfirmarLlegada={puedeConfirmarLlegada}
                        puedeEnviarACustodia={puedeEnviarACustodia}
                        onLimpiarSeleccion={() => setIdsSeleccionados([])}
                        onSeleccionarPagina={() => setIdsSeleccionados(idsSeleccionablesPagina)}
                        paginaSeleccionada={paginaSeleccionada}
                        onExito={() => recargar({ page: resguardosVista?.current_page || 1 })}
                    />
                )}

                {Boolean(permisos.entregar) && bandejaRender === 'en_custodia' && idsSeleccionados.length > 0 && (
                    <BarraStickySeleccionResguardo
                        titulo={`${idsSeleccionados.length} para entrega conjunta`}
                        meta={idsSeleccionados.length < 2 ? 'Selecciona al menos dos para continuar' : undefined}
                        acciones={(
                            <>
                                <button
                                    type="button"
                                    onClick={() => setIdsSeleccionados(idsSeleccionablesPagina)}
                                    disabled={paginaSeleccionada || idsSeleccionablesPagina.length === 0}
                                    className={`${THEME_BTN_SECONDARY} text-xs font-semibold min-h-[44px] px-3 sm:px-4 disabled:opacity-50`}
                                >
                                    <span className="hidden sm:inline">Seleccionar página</span>
                                    <span className="sm:hidden">Página</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setIdsSeleccionados([])}
                                    className={`${THEME_BTN_SECONDARY} text-xs font-semibold min-h-[44px] px-3 sm:px-4`}
                                >
                                    Limpiar
                                </button>
                            </>
                        )}
                    >
                        <button
                            type="button"
                            onClick={irAEntregaMultiple}
                            disabled={idsSeleccionados.length < 2}
                            className={`${THEME_BTN_PRIMARY} min-h-[48px] w-full inline-flex items-center justify-center gap-2 text-sm font-semibold disabled:opacity-50`}
                        >
                            <Truck className="w-4 h-4 shrink-0" aria-hidden />
                            Entregar seleccionados
                        </button>
                    </BarraStickySeleccionResguardo>
                )}

                <GeliaPaginacion paginator={resguardosVista} onIrAPagina={onIrAPagina} />

                </GeliaPageShell>
            </PdvAlertProvider>
        </AppLayout>
    );
}

function ResguardosRealtimeSync({ refrescar }) {
    usePdvAlertReload({
        dominio: 'resguardos',
        refrescar,
    });
    return null;
}
