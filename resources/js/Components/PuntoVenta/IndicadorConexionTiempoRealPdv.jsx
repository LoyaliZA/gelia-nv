import React from 'react';
import { Wifi, WifiOff } from 'lucide-react';
import { formatearUltimaActualizacion } from '@/Pages/PuntoVenta/Operacion/Partials/operacionUtils';
import {
    conexionDegradadaPdv,
    etiquetaConexionTiempoRealPdv,
    PDV_ESTADO_CONEXION,
} from '@/utils/pdvAlertQueueUtils';

export default function IndicadorConexionTiempoRealPdv({
    estadoConexion = null,
    ultimaActualizacion = null,
    className = '',
    mostrarUltimaActualizacion = true,
}) {
    if (!estadoConexion) return null;

    const degradada = conexionDegradadaPdv(estadoConexion);
    const etiqueta = etiquetaConexionTiempoRealPdv(estadoConexion);
    const esReconectando = estadoConexion === PDV_ESTADO_CONEXION.conectando;
    const ultimaEtiqueta = mostrarUltimaActualizacion && ultimaActualizacion
        ? formatearUltimaActualizacion(ultimaActualizacion)
        : null;

    return (
        <div
            className={`flex items-center gap-2 rounded-2xl border theme-border theme-surface shadow-sm px-3 py-2.5 shrink-0 ${className}`}
            role="status"
            aria-live="polite"
            data-pdv-indicador-conexion={estadoConexion}
        >
            {degradada ? (
                <WifiOff
                    className={`w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 ${esReconectando ? 'animate-pulse' : ''}`}
                    aria-hidden
                />
            ) : (
                <Wifi className="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" aria-hidden />
            )}
            <div className="min-w-0">
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Tiempo real</p>
                <p className="text-sm font-black theme-text-main m-0">{etiqueta}</p>
                {ultimaEtiqueta && (
                    <p className="text-[10px] font-semibold theme-text-muted m-0 mt-0.5">
                        Actualizado {ultimaEtiqueta}
                    </p>
                )}
            </div>
        </div>
    );
}
