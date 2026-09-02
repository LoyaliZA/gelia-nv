import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Package, Loader2, AlertTriangle, MapPin } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK } from '../../../utils/geliaTheme';
import FiltrosResguardos from './Partials/FiltrosResguardos';
import ListadoResguardos from './Partials/ListadoResguardos';
import BusquedaRapidaRecepcion from './Partials/BusquedaRapidaRecepcion';
import AlertasCustodiaResguardo from './Partials/AlertasCustodiaResguardo';
import useListadoResguardos from './Partials/useListadoResguardos';
import { antiguedadValidaEnBandeja, paramsListadoResguardos } from './Partials/resguardosUtils';

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
    operativa = {},
}) {
    const antiguedadConfigurada = Boolean(operativa.antiguedad_configurada);

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

    const [bandejaActiva, setBandejaActiva] = useState(filtros.bandeja || bandejaInicial || 'por_recibir');
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [estado, setEstado] = useState(filtros.estado || '');
    const [antiguedad, setAntiguedad] = useState(filtros.antiguedad || '');
    const debounceBusqueda = useRef(null);

    useEffect(() => {
        setBandejaActiva(filtros.bandeja || bandejaInicial || 'por_recibir');
        setBusqueda(filtros.q || '');
        setEstado(filtros.estado || '');
        setAntiguedad(filtros.antiguedad || '');
    }, [filtros, bandejaInicial]);

    const paramsActuales = (extra = {}) => paramsListadoResguardos({
        bandeja: bandejaActiva,
        q: busqueda,
        estado,
        antiguedad,
        page: resguardosVista?.current_page || 1,
        ...extra,
    });

    const recargar = (extra = {}, opts) => cargar(paramsActuales(extra), opts);

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

    const onIrAPagina = (page) => recargar({ page });

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
            <GeliaPageShell className="space-y-5">
                <GeliaTituloCard
                    eyebrow="Punto de Venta"
                    title="Resguardos"
                    titleHighlight="en sucursal"
                    icon={null}
                    aside={(
                        <div className="p-2.5 rounded-xl theme-element border theme-border flex items-center justify-center shrink-0 self-start md:self-center">
                            <Package className="w-5 h-5" style={{ color: 'var(--color-primario)' }} aria-hidden />
                        </div>
                    )}
                    className="!p-4 md:!p-5 lg:!p-6 !gap-3 md:!gap-4 [&_h1]:!text-2xl sm:[&_h1]:!text-3xl md:[&_h1]:!text-3xl"
                >
                    {sucursalActiva?.nombre && (
                        <p className="text-[10px] md:text-[11px] font-bold theme-text-muted uppercase tracking-widest m-0 flex items-center gap-1.5 flex-wrap">
                            <MapPin className="w-3.5 h-3.5 shrink-0" style={{ color: 'var(--color-primario)' }} aria-hidden />
                            <span>
                                Sucursal:{' '}
                                <span className="theme-text-main">{sucursalActiva.nombre}</span>
                            </span>
                        </p>
                    )}
                </GeliaTituloCard>

                <BusquedaRapidaRecepcion puedeRecibir={Boolean(permisos.recibir)} />

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

                {(bandejaRender === 'en_custodia' || bandejaRender === 'por_recibir') && (
                    <AlertasCustodiaResguardo
                        bandeja={bandejaRender}
                        catalogos={catalogos}
                        metricas={metricasVista}
                        antiguedadActiva={antiguedad}
                        onAntiguedad={onAntiguedad}
                        antiguedadConfigurada={antiguedadConfigurada}
                        puedeVerVencidos={Boolean(permisos.ver_vencidos)}
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
                    antiguedadConfigurada={antiguedadConfigurada}
                    cargando={cargando}
                    hayFiltrosActivos={hayFiltrosActivos}
                    onLimpiar={onLimpiar}
                />

                {error && (
                    <div className={`${geliaCardClass()} p-4 flex items-start gap-3 border border-red-500/30`}>
                        <AlertTriangle className="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                        <p className="text-sm font-semibold text-red-600 dark:text-red-300 m-0">{error}</p>
                    </div>
                )}

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
                        hayFiltrosActivos={hayFiltrosActivos}
                        onLimpiarFiltros={onLimpiar}
                        puedeRecibir={Boolean(permisos.recibir)}
                        puedeEntregar={Boolean(permisos.entregar)}
                    />
                )}

                <GeliaPaginacion paginator={resguardosVista} onIrAPagina={onIrAPagina} />
            </GeliaPageShell>
        </AppLayout>
    );
}
