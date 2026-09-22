import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Package, Loader2, AlertTriangle, Truck } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import FiltrosResguardos from './Partials/FiltrosResguardos';
import ListadoResguardos from './Partials/ListadoResguardos';
import BusquedaRapidaRecepcion from './Partials/BusquedaRapidaRecepcion';
import AlertasCustodiaResguardo from './Partials/AlertasCustodiaResguardo';
import SelectorSucursalActivaPdv, { RELOAD_ONLY_RESGUARDOS } from '@/Components/PuntoVenta/SelectorSucursalActivaPdv';
import AccionRegistrarResguardoManual from './Partials/AccionRegistrarResguardoManual';
import useListadoResguardos from './Partials/useListadoResguardos';
import BarraAccionesMasivasGerente from './Partials/BarraAccionesMasivasGerente';
import { antiguedadValidaEnBandeja, paramsListadoResguardos } from './Partials/resguardosUtils';
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
}) {
    const antiguedadConfigurada = Boolean(operativa.antiguedad_configurada);
    const puedeRegistrarManual = Boolean(operativa.registro_manual) && Boolean(permisos.recibir);

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
    const [idsSeleccionados, setIdsSeleccionados] = useState([]);
    const debounceBusqueda = useRef(null);

    useEffect(() => {
        setBandejaActiva(filtros.bandeja || bandejaInicial || 'por_recibir');
        setPasoActivo(filtros.paso || 'gerente');
        setBusqueda(filtros.q || '');
        setEstado(filtros.estado || '');
        setAntiguedad(filtros.antiguedad || '');
    }, [filtros, bandejaInicial]);

    const paramsActuales = (extra = {}) => paramsListadoResguardos({
        bandeja: bandejaActiva,
        paso: bandejaActiva === 'por_recibir' ? pasoActivo : undefined,
        q: busqueda,
        estado,
        antiguedad,
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

    const onLimpiar = () => {
        setBusqueda('');
        setEstado('');
        setAntiguedad('');
        recargar({ q: undefined, estado: undefined, antiguedad: undefined, page: 1 });
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

    const hayFiltrosActivos = Boolean(busqueda || estado || antiguedad);

    const bandejaRender = bandejaVista || bandejaActiva;

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
                <GeliaPageShell className="space-y-5">
                <GeliaTituloCard
                    eyebrow="Punto de Venta"
                    title="Resguardos"
                    titleHighlight="en sucursal"
                    icon={null}
                    aside={(
                        <div className="flex items-center gap-2 shrink-0 self-start md:self-center">
                            <PdvEncabezadoAlertasPdv />
                            <AccionRegistrarResguardoManual
                                habilitado={puedeRegistrarManual}
                                origenes={catalogos.origenes_pedido || []}
                                onExito={() => recargar({ bandeja: 'por_recibir', paso: 'gerente', page: 1 })}
                            />
                            <div className="p-2.5 rounded-xl theme-element border theme-border flex items-center justify-center">
                                <Package className="w-5 h-5" style={{ color: 'var(--color-primario)' }} aria-hidden />
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

                <BusquedaRapidaRecepcion
                    puedeRecibir={Boolean(permisos.recibir)}
                    onRecepcionExito={() => recargar({ page: resguardosVista?.current_page || 1 })}
                />

                <div className={GELIA_SEGMENT_TABS_SCROLL}>
                    <div
                        className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`}
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
                                    className="gelia-segment-btn whitespace-nowrap gap-2"
                                >
                                    <span>{etiqueta}</span>
                                    <span
                                        className={`text-[9px] font-black px-1.5 py-0.5 rounded-md tabular-nums border ${
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

                {bandejaRender === 'por_recibir' && (
                    <div className={GELIA_SEGMENT_TABS_SCROLL}>
                        <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`} role="tablist" aria-label="Paso de recepción">
                            {[
                                { id: 'gerente', etiqueta: 'Recepción gerente', visible: permisos.recibir },
                                { id: 'recepcionista', etiqueta: 'Custodia recepción', visible: permisos.confirmar_custodia },
                            ].filter((opcion) => opcion.visible).map(({ id, etiqueta }) => {
                                const activa = pasoActivo === id;
                                return (
                                    <button
                                        key={id}
                                        type="button"
                                        role="tab"
                                        aria-selected={activa}
                                        data-active={activa}
                                        onClick={() => {
                                            setPasoActivo(id);
                                            recargar({ paso: id, page: 1 });
                                        }}
                                        className="gelia-segment-btn whitespace-nowrap"
                                    >
                                        {etiqueta}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

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
                />

                {cargando && !resguardosVista?.data?.length ? (
                    <div className={`${geliaCardClass()} p-12 flex flex-col items-center gap-3`}>
                        <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} />
                        <p className="text-sm theme-text-muted font-bold uppercase tracking-widest m-0">Cargando resguardos</p>
                    </div>
                ) : (
                    <ListadoResguardos
                        resguardos={resguardosVista}
                        bandeja={bandejaRender}
                        catalogos={catalogos}
                        permisos={permisos}
                        hayFiltrosActivos={hayFiltrosActivos}
                        onLimpiarFiltros={onLimpiar}
                        puedeRecibir={Boolean(permisos.recibir)}
                        puedeConfirmarCustodia={Boolean(permisos.confirmar_custodia)}
                        paso={bandejaRender === 'por_recibir' ? pasoActivo : undefined}
                        puedeEntregar={Boolean(permisos.entregar)}
                        idsSeleccionados={idsSeleccionados}
                        onToggleSeleccion={
                            (permisos.entregar && bandejaRender === 'en_custodia')
                            || (permisos.recibir && bandejaRender === 'por_recibir' && pasoActivo === 'gerente')
                                ? toggleSeleccion
                                : undefined
                        }
                        onReponerExito={() => recargar({ page: resguardosVista?.current_page || 1 })}
                        onEntregaExito={() => recargar({ page: resguardosVista?.current_page || 1 })}
                        onRecepcionExito={() => recargar({ page: resguardosVista?.current_page || 1 })}
                    />
                )}

                {Boolean(permisos.recibir) && bandejaRender === 'por_recibir' && pasoActivo === 'gerente' && idsSeleccionados.length > 0 && (
                    <BarraAccionesMasivasGerente
                        resguardos={resguardosVista?.data || []}
                        idsSeleccionados={idsSeleccionados}
                        onLimpiarSeleccion={() => setIdsSeleccionados([])}
                        onExito={() => recargar({ page: resguardosVista?.current_page || 1 })}
                    />
                )}

                {Boolean(permisos.entregar) && bandejaRender === 'en_custodia' && idsSeleccionados.length > 0 && (
                    <div className={`${geliaCardClass()} p-4 sticky bottom-3 z-10 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between`}>
                        <p className="text-sm font-bold theme-text-main m-0">
                            {idsSeleccionados.length} seleccionado{idsSeleccionados.length === 1 ? '' : 's'} para entrega conjunta
                        </p>
                        <button
                            type="button"
                            onClick={irAEntregaMultiple}
                            disabled={idsSeleccionados.length < 2}
                            className={`${THEME_BTN_PRIMARY} min-h-[48px] inline-flex items-center justify-center gap-2 text-[10px] font-black uppercase tracking-widest disabled:opacity-50`}
                        >
                            <Truck className="w-4 h-4" />
                            Entregar seleccionados
                        </button>
                    </div>
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
