import React from 'react';
import { Calendar, UserCheck, UserRound } from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import AvisoOperativoPedido from '../../../ControlPedidos/Partials/AvisoOperativoPedido';
import {
    BANNER_SUCURSAL_VISITA,
    CELDA_ETIQUETA,
    CELDA_VALOR,
    badgeEstadoVisita,
    badgeIntencionVisita,
    badgeTiempoVisita,
    formatearFechaVisita,
} from './visitasProgramadasStyles';

function CeldaDato({ etiqueta, valor }) {
    return (
        <div>
            <p className={CELDA_ETIQUETA}>{etiqueta}</p>
            <p className={CELDA_VALOR}>{valor || '—'}</p>
        </div>
    );
}

function BadgePill({ badge }) {
    if (!badge) return null;
    return (
        <span className={badge.className} style={badge.style}>
            {badge.label}
        </span>
    );
}

/**
 * Tarjeta horizontal (md+) con lenguaje visual alineado a Control Pedidos / CEDIS.
 */
export default function TarjetaVisitaProgramada({
    visita,
    variante = 'comercial',
    puedeConfirmarLlegada = false,
    confirmando = false,
    onConfirmarLlegada = null,
}) {
    const badgeEstado = badgeEstadoVisita(visita.estado, visita.estado_etiqueta);
    const badgeIntencion = badgeIntencionVisita(visita.intencion, visita.intencion_etiqueta);
    const badgeTiempo = badgeTiempoVisita(visita.estado_tiempo);
    const sucursalNombre = visita.sucursal?.nombre;
    const bannerTexto = variante === 'pdv'
        ? (sucursalNombre ? `SUCURSAL: ${sucursalNombre}` : 'VISITA DEL DÍA')
        : (sucursalNombre ? `SUCURSAL: ${sucursalNombre}` : 'VISITA PROGRAMADA');

    const mostrarRegistrador = variante === 'pdv' || Boolean(visita.registrado_por?.nombre);
    const ringRetraso = visita.estado_tiempo === 'retrasado' && visita.estado === 'programada';

    return (
        <article
            className={`${geliaCardClass('!rounded-xl overflow-hidden p-0')} ${ringRetraso ? 'ring-1 ring-amber-500/40' : ''}`}
        >
            <p className={BANNER_SUCURSAL_VISITA} style={{ color: 'var(--color-primario)' }}>
                {bannerTexto}
            </p>

            <div className="p-4 space-y-3 md:space-y-0 md:flex md:items-stretch md:gap-5 lg:gap-6">
                <div className="min-w-0 md:w-[min(240px,30%)] md:shrink-0 md:border-r md:theme-border md:pr-5">
                    <p className="text-2xl md:text-3xl font-black text-[var(--color-primario)] leading-tight m-0 tabular-nums">
                        {visita.cliente?.numero_cliente || '—'}
                    </p>
                    <p className="text-sm md:text-base font-black theme-text-main uppercase italic m-0 mt-1 leading-snug">
                        {visita.cliente?.nombre || 'Cliente'}
                    </p>
                    <p className="text-[10px] theme-text-muted font-bold m-0 mt-2 flex items-center gap-1.5">
                        <Calendar className="w-3.5 h-3.5 shrink-0 opacity-70" aria-hidden />
                        {formatearFechaVisita(visita.fecha)}
                    </p>
                </div>

                <div className="flex-1 min-w-0 space-y-3">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-3 text-[10px] font-bold theme-text-muted uppercase">
                        <CeldaDato etiqueta="Hora" valor={visita.hora_etiqueta} />
                        <CeldaDato
                            etiqueta="Sucursal"
                            valor={visita.sucursal?.nombre}
                        />
                        {mostrarRegistrador && (
                            <CeldaDato
                                etiqueta="Registró"
                                valor={visita.registrado_por?.nombre}
                            />
                        )}
                        {variante === 'pdv' && visita.registrado_por?.departamento && (
                            <CeldaDato
                                etiqueta="Departamento"
                                valor={visita.registrado_por.departamento}
                            />
                        )}
                        {variante === 'comercial' && (
                            <CeldaDato etiqueta="Estado" valor={visita.estado_etiqueta} />
                        )}
                    </div>

                    <AvisoOperativoPedido label="Probabilidad de asistencia" tono="info" icon={UserRound}>
                        {visita.intencion_etiqueta}
                    </AvisoOperativoPedido>
                </div>

                <div className="md:w-[min(168px,24%)] md:shrink-0 flex flex-row md:flex-col items-center md:items-end justify-between md:justify-start gap-2 md:gap-1.5">
                    <div className="flex flex-wrap md:flex-col items-end gap-1.5 max-w-full">
                        <BadgePill badge={badgeEstado} />
                        <BadgePill badge={badgeIntencion} />
                        <BadgePill badge={badgeTiempo} />
                    </div>

                    {puedeConfirmarLlegada && onConfirmarLlegada && (
                        <button
                            type="button"
                            disabled={confirmando}
                            onClick={() => onConfirmarLlegada(visita.id)}
                            className={`${THEME_BTN_PRIMARY} w-full md:mt-auto min-h-[44px] md:min-h-[48px] text-[10px] font-black uppercase tracking-widest px-3 inline-flex items-center justify-center gap-2`}
                        >
                            <UserCheck className="w-4 h-4 shrink-0" aria-hidden />
                            {confirmando ? 'Registrando…' : 'Registrar llegada'}
                        </button>
                    )}
                </div>
            </div>
        </article>
    );
}
