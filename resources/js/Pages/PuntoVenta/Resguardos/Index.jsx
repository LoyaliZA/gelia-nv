import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Package, Loader2, AlertTriangle } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK } from '../../../utils/geliaTheme';
import FiltrosResguardos from './Partials/FiltrosResguardos';
import ListadoResguardos from './Partials/ListadoResguardos';
import BusquedaRapidaRecepcion from './Partials/BusquedaRapidaRecepcion';
import useListadoResguardos from './Partials/useListadoResguardos';
import { paramsListadoResguardos } from './Partials/resguardosUtils';

const BANDEJAS = ['por_recibir', 'en_custodia', 'incidencias'];

const METRICAS_ANTIGUEDAD = [
    { key: 'rezagado', tone: 'text-orange-600' },
    { key: 'proximo_a_vencer', tone: 'text-amber-600' },
    { key: 'vencido', tone: 'text-red-600' },
];

export default function Index({
    auth,
    resguardos,
    metricas = {},
    filtros = {},
    bandeja: bandejaInicial,
    catalogos = {},
    permisos = {},
}) {
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
        recargar({ bandeja: nuevaBandeja, page: 1 });
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
            <GeliaPageShell className="space-y-6">
                <GeliaTituloCard
                    eyebrow="Punto de Venta"
                    title="Resguardos"
                    titleHighlight="en sucursal"
                    description="Consulta recepciones esperadas, custodia e incidencias de la sucursal activa."
                    icon={Package}
                />

                <BusquedaRapidaRecepcion puedeRecibir={Boolean(permisos.recibir)} />

                <div className={`${geliaCardClass()} p-2`}>
                    <div className={GELIA_SEGMENT_TABS_SCROLL}>
                        <div className={GELIA_SEGMENT_TABS_TRACK}>
                            {metricasBandeja.map(({ clave, etiqueta, total }) => (
                                <button
                                    key={clave}
                                    type="button"
                                    onClick={() => onBandeja(clave)}
                                    className={`gelia-segment-tab ${bandejaRender === clave ? 'gelia-segment-tab--active' : ''}`}
                                >
                                    <span>{etiqueta}</span>
                                    <span className="tabular-nums opacity-80">({total})</span>
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    {METRICAS_ANTIGUEDAD.map(({ key, tone }) => {
                        if (key === 'vencido' && !permisos.ver_vencidos) return null;
                        return (
                            <div key={key} className={`${geliaCardClass()} p-4`}>
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                                    {catalogos.antiguedades?.[key] || key}
                                </p>
                                <p className={`text-2xl font-black m-0 mt-1 tabular-nums ${tone}`}>
                                    {metricasVista?.[key] ?? 0}
                                </p>
                            </div>
                        );
                    })}
                </div>

                <FiltrosResguardos
                    busqueda={busqueda}
                    onBusqueda={onBusqueda}
                    estado={estado}
                    onEstado={onEstado}
                    antiguedad={antiguedad}
                    onAntiguedad={onAntiguedad}
                    catalogos={catalogos}
                    puedeVerVencidos={Boolean(permisos.ver_vencidos)}
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
