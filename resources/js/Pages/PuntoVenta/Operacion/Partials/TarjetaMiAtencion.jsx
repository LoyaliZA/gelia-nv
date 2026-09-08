import React from 'react';
import { Headphones, Pause, User } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import CronometroVisualOperacion from './CronometroVisualOperacion';
import {
    claseBadgeEstadoVendedor,
    cronometroDesdeEstado,
    etiquetaEstadoVendedor,
    esEstadoVendedorConocido,
    formatearUltimaActualizacion,
    inicialesNombre,
    mensajeMiAtencion,
} from './operacionUtils';

export default function TarjetaMiAtencion({
    estado,
    turnoAsignado = null,
    nombre = null,
    sucursal = null,
    servidorAt,
}) {
    const estadoVendedor = estado?.estado_vendedor;
    const estadoConocido = esEstadoVendedorConocido(estadoVendedor);
    const etiquetaEstado = estadoConocido
        ? etiquetaEstadoVendedor(estadoVendedor)
        : 'Estado desconocido';
    const mensaje = mensajeMiAtencion(estadoVendedor);
    const cronometro = cronometroDesdeEstado(estado);
    const mostrarTurno = estadoVendedor === 'atendiendo' && turnoAsignado;
    const enPausa = estadoVendedor === 'en_retencion';
    const motivoPausa = estado?.pausa_motivo || estado?.intervalo?.motivo || null;

    return (
        <section className={`${geliaCardClass()} p-5 space-y-4`} aria-labelledby="mi-atencion-titulo">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex items-start gap-3 min-w-0">
                    <div
                        className="w-10 h-10 rounded-2xl bg-black/5 dark:bg-white/10 flex items-center justify-center shrink-0"
                        aria-hidden
                    >
                        {nombre ? (
                            <span className="text-xs font-black theme-text-main">{inicialesNombre(nombre)}</span>
                        ) : (
                            <User className="w-4 h-4 theme-text-muted" />
                        )}
                    </div>
                    <div className="min-w-0">
                        <h2 id="mi-atencion-titulo" className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                            Mi atención
                        </h2>
                        {nombre && (
                            <p className="text-sm font-bold theme-text-main m-0 mt-1 truncate">{nombre}</p>
                        )}
                        {sucursal && (
                            <p className="text-xs font-semibold theme-text-muted m-0 mt-0.5 truncate">
                                Sucursal: {sucursal}
                            </p>
                        )}
                        <p className="text-xs font-semibold theme-text-muted m-0 mt-1">
                            Estado operativo asignado por gerencia.
                        </p>
                    </div>
                </div>
                <span
                    className={`inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase ${claseBadgeEstadoVendedor(estadoConocido ? estadoVendedor : null)}`}
                    aria-label={`Estado: ${etiquetaEstado}`}
                >
                    {etiquetaEstado}
                </span>
            </div>

            {mensaje && (
                <p className="text-sm font-semibold theme-text-main m-0">{mensaje}</p>
            )}

            {enPausa && (
                <div
                    className="flex items-start gap-3 rounded-xl px-3 py-2 bg-amber-500/10 border border-amber-500/20"
                    role="status"
                    aria-label="Pausa activa"
                >
                    <Pause className="w-4 h-4 shrink-0 text-amber-700 dark:text-amber-300 mt-0.5" aria-hidden />
                    <div className="min-w-0">
                        <p className="text-[10px] font-black uppercase tracking-widest text-amber-800 dark:text-amber-200 m-0">
                            Pausa activa
                        </p>
                        <p className="text-xs font-semibold text-amber-800/90 dark:text-amber-200/90 m-0 mt-1">
                            {motivoPausa
                                ? `Motivo: ${motivoPausa}`
                                : 'Gerencia activó la pausa. No puedes modificarla desde aquí.'}
                        </p>
                    </div>
                </div>
            )}

            {cronometro && (
                <CronometroVisualOperacion
                    etiqueta={cronometro.etiqueta}
                    referenciaAt={cronometro.referenciaAt}
                    servidorAt={servidorAt}
                    modo={cronometro.modo}
                />
            )}

            {mostrarTurno && (
                <div className="flex items-start gap-3 rounded-xl px-3 py-2 bg-black/5 dark:bg-white/5">
                    <Headphones className="w-4 h-4 shrink-0 theme-text-muted mt-0.5" aria-hidden />
                    <div className="min-w-0">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Turno asignado</p>
                        <p className="text-sm font-black theme-text-main m-0 mt-1">
                            {turnoAsignado.folio || turnoAsignado.codigo || `#${turnoAsignado.id}`}
                        </p>
                        {turnoAsignado.servicio && (
                            <p className="text-xs font-semibold theme-text-muted m-0 mt-1">{turnoAsignado.servicio}</p>
                        )}
                    </div>
                </div>
            )}

            <p className="text-[10px] font-semibold theme-text-muted m-0 border-t border-black/5 dark:border-white/10 pt-3">
                Última actualización: {formatearUltimaActualizacion(servidorAt)}
            </p>
        </section>
    );
}
