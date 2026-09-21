import React from 'react';
import { Wifi, WifiOff } from 'lucide-react';
import PdvIndicadorEstadoVivo from '@/Components/PuntoVenta/PdvIndicadorEstadoVivo';
import { GELIA_ICON_BOX } from '@/utils/geliaTheme';
import { formatearUltimaActualizacion } from '@/Pages/PuntoVenta/Operacion/Partials/operacionUtils';
import {
    conexionDegradadaPdv,
    etiquetaConexionTiempoRealPdv,
    mensajeConexionDegradadaPdv,
    PDV_ESTADO_CONEXION,
} from '@/utils/pdvAlertQueueUtils';

export default function IndicadorConexionTiempoRealPdv({
    estadoConexion = null,
    ultimaActualizacion = null,
    className = '',
    mostrarUltimaActualizacion = true,
    variante = 'tarjeta',
    onClick = null,
}) {
    if (!estadoConexion) return null;

    const degradada = conexionDegradadaPdv(estadoConexion);
    const etiqueta = etiquetaConexionTiempoRealPdv(estadoConexion);
    const esReconectando = estadoConexion === PDV_ESTADO_CONEXION.conectando;
    const ultimaEtiqueta = mostrarUltimaActualizacion && ultimaActualizacion
        ? formatearUltimaActualizacion(ultimaActualizacion)
        : null;

    if (variante === 'icono') {
        const conectado = estadoConexion === PDV_ESTADO_CONEXION.conectado;
        return (
            <PdvIndicadorEstadoVivo
                icono={conectado ? Wifi : WifiOff}
                etiqueta={conectado ? 'Tiempo real conectado' : mensajeConexionDegradadaPdv(estadoConexion)}
                titulo={conectado ? 'En línea' : etiqueta}
                tono={conectado ? 'exito' : (esReconectando ? 'aviso' : 'error')}
                pulsando={degradada}
                clickeable={degradada && typeof onClick === 'function'}
                onClick={onClick}
                dataAtributo={`conexion-${estadoConexion}`}
            />
        );
    }

    return (
        <div
            className={`inline-flex items-center gap-2 shrink-0 ${className}`}
            role="status"
            aria-live="polite"
            data-pdv-indicador-conexion={estadoConexion}
        >
            <span className={`${GELIA_ICON_BOX} !p-2 !min-h-[36px] !min-w-[36px]`} aria-hidden>
                {degradada ? (
                    <WifiOff
                        className={`w-4 h-4 theme-text-aviso ${esReconectando ? 'animate-pulse' : ''}`}
                    />
                ) : (
                    <Wifi className="w-4 h-4 theme-text-exito" />
                )}
            </span>
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
