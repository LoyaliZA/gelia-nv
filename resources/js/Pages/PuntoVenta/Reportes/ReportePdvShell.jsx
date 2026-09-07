import React, { useCallback, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, BarChart3, Loader2 } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import { geliaCardClass } from '../../../utils/geliaTheme';
import NavegacionReportesPdv from './Partials/NavegacionReportesPdv';
import ResumenMetricasReporte from './Partials/ResumenMetricasReporte';
import DetalleMetricasReporte from './Partials/DetalleMetricasReporte';
import MenuExportarReportePdv from './Partials/MenuExportarReportePdv';
import ReportePdvFloatingTracker from './Partials/ReportePdvFloatingTracker';
import FiltrosReporteResguardos from './Partials/FiltrosReporteResguardos';
import FiltrosReporteTurnosOperacion from './Partials/FiltrosReporteTurnosOperacion';
import {
    ETIQUETAS_TIPO_REPORTE,
    RUTAS_REPORTE_PDV,
    TIPO_REPORTE_CONJUNTO,
    TIPO_REPORTE_RESGUARDOS,
    TIPO_REPORTE_TURNOS_OPERACION,
    estadoFiltrosDesdePayload,
    filtrosConjuntoParaApi,
    filtrosResguardosParaApi,
    filtrosTurnosOperacionParaApi,
    paramsFiltrosReportePdv,
} from './reportesPdvUtils';

export default function ReportePdvShell({
    tipo_reporte,
    payload = {},
    filtros = {},
    catalogos = {},
    permisos = {},
    definiciones_metricas = {},
    etiquetas_metricas = {},
    secciones = [],
    exportaciones_recientes = [],
    vistas_disponibles = {},
    errors = {},
}) {
    const [filtrosLocales, setFiltrosLocales] = useState(() => estadoFiltrosDesdePayload(filtros, tipo_reporte));
    const [cargando, setCargando] = useState(false);
    const [errorCliente, setErrorCliente] = useState(null);

    const navegar = useCallback((params) => {
        setCargando(true);
        setErrorCliente(null);
        router.get(route(RUTAS_REPORTE_PDV[tipo_reporte]), paramsFiltrosReportePdv(params), {
            preserveState: true,
            replace: true,
            onFinish: () => setCargando(false),
            onError: () => setCargando(false),
        });
    }, [tipo_reporte]);

    const aplicarFiltros = () => {
        if (tipo_reporte === TIPO_REPORTE_RESGUARDOS) {
            navegar(filtrosResguardosParaApi(filtrosLocales));
            return;
        }
        if (tipo_reporte === TIPO_REPORTE_TURNOS_OPERACION) {
            navegar(filtrosTurnosOperacionParaApi(filtrosLocales));
            return;
        }
        navegar(filtrosConjuntoParaApi(filtrosLocales));
    };

    const limpiarFiltros = () => {
        const vacio = tipo_reporte === TIPO_REPORTE_CONJUNTO
            ? { resguardos: {}, turnos_operacion: {} }
            : {};
        setFiltrosLocales(vacio);
        navegar({});
    };

    const errorServidor = errors?.hasta || errors?.desde || errors?.franja || Object.values(errors || {})[0];
    const sinDatos = secciones.every((sec) => {
        const ids = sec.metricas || [];
        return ids.length === 0;
    });

    const renderFiltros = () => {
        if (tipo_reporte === TIPO_REPORTE_RESGUARDOS) {
            return (
                <FiltrosReporteResguardos
                    valores={filtrosLocales}
                    catalogos={{ ...catalogos, ...(catalogos.resguardos || {}) }}
                    onChange={setFiltrosLocales}
                    onAplicar={aplicarFiltros}
                    onLimpiar={limpiarFiltros}
                    cargando={cargando}
                />
            );
        }
        if (tipo_reporte === TIPO_REPORTE_TURNOS_OPERACION) {
            return (
                <FiltrosReporteTurnosOperacion
                    valores={filtrosLocales}
                    catalogos={catalogos}
                    onChange={setFiltrosLocales}
                    onAplicar={aplicarFiltros}
                    onLimpiar={limpiarFiltros}
                    cargando={cargando}
                />
            );
        }
        return (
            <div className="space-y-4">
                <FiltrosReporteResguardos
                    valores={filtrosLocales.resguardos || {}}
                    catalogos={{ ...catalogos, ...(catalogos.resguardos || {}) }}
                    onChange={(v) => setFiltrosLocales((prev) => ({ ...prev, resguardos: v }))}
                    onAplicar={aplicarFiltros}
                    onLimpiar={limpiarFiltros}
                    cargando={cargando}
                />
                <FiltrosReporteTurnosOperacion
                    valores={filtrosLocales.turnos_operacion || {}}
                    catalogos={catalogos}
                    onChange={(v) => setFiltrosLocales((prev) => ({ ...prev, turnos_operacion: v }))}
                    onAplicar={aplicarFiltros}
                    onLimpiar={limpiarFiltros}
                    cargando={cargando}
                />
            </div>
        );
    };

    const filtrosExportacion = tipo_reporte === TIPO_REPORTE_CONJUNTO
        ? filtrosLocales
        : filtrosLocales;

    return (
        <AppLayout>
            <Head title={`Reportes PDV — ${ETIQUETAS_TIPO_REPORTE[tipo_reporte]}`} />
            <GeliaPageShell>
                <GeliaTituloCard
                    title="Reportes"
                    description={ETIQUETAS_TIPO_REPORTE[tipo_reporte]}
                    icon={BarChart3}
                />
                <div className="space-y-4 md:space-y-6 mt-4 md:mt-6">
                    <NavegacionReportesPdv
                        tipoActivo={tipo_reporte}
                        vistasDisponibles={vistas_disponibles}
                    />

                    {renderFiltros()}

                    {(errorServidor || errorCliente) && (
                        <div className={geliaCardClass('p-4 flex items-start gap-3 border-red-500/30')}>
                            <AlertTriangle className="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                            <p className="text-sm text-red-500 m-0">{errorServidor || errorCliente}</p>
                        </div>
                    )}

                    {cargando && (
                        <div className="flex items-center justify-center gap-2 py-8 theme-text-muted">
                            <Loader2 className="w-5 h-5 animate-spin" />
                            <span className="text-sm">Cargando métricas…</span>
                        </div>
                    )}

                    {!cargando && !errorServidor && (
                        <>
                            {secciones.map((seccion) => (
                                <ResumenMetricasReporte
                                    key={`resumen-${seccion.id}`}
                                    seccion={seccion}
                                    payload={payload}
                                    tipoReporte={tipo_reporte}
                                    etiquetasMetricas={etiquetas_metricas}
                                    definicionesMetricas={definiciones_metricas}
                                />
                            ))}

                            {sinDatos && (
                                <div className={geliaCardClass('p-8 text-center')}>
                                    <p className="text-sm theme-text-muted m-0">
                                        No hay métricas para los filtros seleccionados en este periodo.
                                    </p>
                                </div>
                            )}

                            {secciones.map((seccion) => (
                                <DetalleMetricasReporte
                                    key={`detalle-${seccion.id}`}
                                    seccion={seccion}
                                    payload={payload}
                                    tipoReporte={tipo_reporte}
                                    etiquetasMetricas={etiquetas_metricas}
                                    definicionesMetricas={definiciones_metricas}
                                />
                            ))}

                            <MenuExportarReportePdv
                                tipoReporte={tipo_reporte}
                                filtrosLocales={filtrosExportacion}
                                puedeExportar={permisos.exportar}
                                exportacionesRecientes={exportaciones_recientes}
                                onError={setErrorCliente}
                            />
                        </>
                    )}
                </div>
            </GeliaPageShell>
            <ReportePdvFloatingTracker canView={permisos.exportar} />
        </AppLayout>
    );
}
