import React from 'react';
import { Users } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import IndicadorConexionTiempoRealPdv from '../../../../Components/PuntoVenta/IndicadorConexionTiempoRealPdv';

function MetricaResumen({ etiqueta, valor, destacado = false }) {
    return (
        <div
            className={`rounded-2xl border px-3 py-2.5 min-w-0 ${
                destacado ? 'border-[color-mix(in_srgb,var(--color-primario)_35%,transparent)]' : 'theme-border'
            }`}
        >
            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 truncate">{etiqueta}</p>
            <p className="text-xl font-black theme-text-main m-0 mt-1 tabular-nums">{valor}</p>
        </div>
    );
}

export default function EncabezadoGestionVendedores({
    resumen = {},
    clientesEnFila = 0,
    estadoConexion = null,
    ultimaActualizacion = null,
}) {
    const {
        configurados = 0,
        activos_hoy: activosHoy = 0,
        disponibles = 0,
        atendiendo = 0,
        en_pausa: enPausa = 0,
    } = resumen;

    return (
        <section
            className={`${geliaCardClass()} p-4 space-y-3`}
            aria-labelledby="gestion-vendedores-resumen"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2 min-w-0">
                    <Users className="w-4 h-4 theme-text-muted shrink-0" aria-hidden />
                    <h2 id="gestion-vendedores-resumen" className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        Resumen del equipo
                    </h2>
                </div>
                {estadoConexion && (
                    <IndicadorConexionTiempoRealPdv
                        estadoConexion={estadoConexion}
                        ultimaActualizacion={ultimaActualizacion}
                    />
                )}
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-2">
                <MetricaResumen etiqueta="Configurados" valor={configurados} />
                <MetricaResumen etiqueta="Activos hoy" valor={activosHoy} destacado />
                <MetricaResumen etiqueta="Disponibles" valor={disponibles} />
                <MetricaResumen etiqueta="Atendiendo" valor={atendiendo} />
                <MetricaResumen etiqueta="En pausa" valor={enPausa} />
                <MetricaResumen etiqueta="Clientes en fila" valor={clientesEnFila} />
            </div>
        </section>
    );
}
