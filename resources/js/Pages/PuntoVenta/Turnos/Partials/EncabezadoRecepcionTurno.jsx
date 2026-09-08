import React from 'react';
import { Users } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import IndicadorConexionTiempoRealPdv from '../../../../Components/PuntoVenta/IndicadorConexionTiempoRealPdv';
import { formatearDuracion } from '../../Reportes/reportesPdvUtils';
import { resumenBandejaRecepcion } from './recepcionTurnoUtils';

function MetricaResumen({ etiqueta, valor, destacado = false }) {
    return (
        <div
            className={`rounded-xl border px-3 py-2 min-w-0 ${
                destacado ? 'border-[color-mix(in_srgb,var(--color-primario)_35%,transparent)]' : 'theme-border'
            }`}
        >
            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 truncate">{etiqueta}</p>
            <p className="text-lg font-black theme-text-main m-0 mt-0.5 tabular-nums truncate">{valor}</p>
        </div>
    );
}

export default function EncabezadoRecepcionTurno({
    bandeja = null,
    estadoConexion = null,
    ultimaActualizacion = null,
}) {
    const {
        enEspera,
        asignados,
        mayorEsperaSegundos,
        vendedoresDisponibles,
    } = resumenBandejaRecepcion(bandeja);

    const mayorEsperaEtiqueta = mayorEsperaSegundos > 0
        ? formatearDuracion(mayorEsperaSegundos)
        : '—';

    return (
        <section
            className={`${geliaCardClass()} p-3 sm:p-4`}
            aria-labelledby="recepcion-turno-resumen"
            data-recepcion-turno-encabezado
        >
            <div className="flex flex-wrap items-center justify-between gap-3 mb-2">
                <div className="flex items-center gap-2 min-w-0">
                    <Users className="w-4 h-4 theme-text-muted shrink-0" aria-hidden />
                    <h2 id="recepcion-turno-resumen" className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        Fila actual
                    </h2>
                </div>
                {estadoConexion && (
                    <IndicadorConexionTiempoRealPdv
                        estadoConexion={estadoConexion}
                        ultimaActualizacion={ultimaActualizacion}
                        className="rounded-xl py-2"
                    />
                )}
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <MetricaResumen etiqueta="En espera" valor={enEspera} destacado />
                <MetricaResumen etiqueta="Asignados" valor={asignados} />
                <MetricaResumen etiqueta="Mayor espera" valor={mayorEsperaEtiqueta} />
                <MetricaResumen etiqueta="Vendedores disp." valor={vendedoresDisponibles} />
            </div>
        </section>
    );
}
