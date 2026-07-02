import React from 'react';
import { CreditCard, FileText, MessageSquare, RefreshCw } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoMoneda } from '../../../utils/formatoMoneda';
import {
    facturaCriticaCliente,
    facturasActivasCliente,
    saldoTotalCliente,
    saldoVencidoCliente,
    parseFechaCobranza,
} from '../../../utils/cobranzaCliente';
import DesgloseFoliosCliente from './DesgloseFoliosCliente';

function montoAutorizado(cliente) {
    const monto = cliente?.monto_credito_autorizado;
    return monto != null && Number(monto) > 0 ? Number(monto) : null;
}

function tieneAbonoActivo(cliente) {
    return facturasActivasCliente(cliente).some((f) => f.tiene_abono);
}

function calcularBadgeVencimiento(fechaVencimiento) {
    if (!fechaVencimiento) {
        return { colorBadge: '', textoBadge: '', fechaFormateada: '', diasRestantes: null };
    }

    const vDate = parseFechaCobranza(fechaVencimiento);
    if (!vDate) {
        return { colorBadge: '', textoBadge: '', fechaFormateada: '', diasRestantes: null };
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const fechaFormateada = vDate.toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    });

    const diasRestantes = Math.ceil((vDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

    if (diasRestantes > 7) {
        return {
            colorBadge: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
            textoBadge: `Vence en ${diasRestantes} días`,
            fechaFormateada,
            diasRestantes,
        };
    }

    if (diasRestantes > 0) {
        return {
            colorBadge: 'bg-amber-500/10 text-amber-500 border-amber-500/20',
            textoBadge: `Vence en ${diasRestantes} días`,
            fechaFormateada,
            diasRestantes,
        };
    }

    if (diasRestantes === 0) {
        return {
            colorBadge: 'bg-red-500/10 text-red-500 border-red-500/20 animate-pulse',
            textoBadge: 'Vence HOY',
            fechaFormateada,
            diasRestantes,
        };
    }

    return {
        colorBadge: 'bg-red-500/10 text-red-500 border-red-500/20 font-black',
        textoBadge: `Vencido hace ${Math.abs(diasRestantes)} días`,
        fechaFormateada,
        diasRestantes,
    };
}

export default function TarjetaClienteCredito({
    cliente,
    modo = 'credito',
    hasAlerta = false,
    limiteSuperado = false,
    puedeVerBitacora = false,
    puedeRepararFecha = false,
    onVerFolios,
    onBitacora,
    onRepararFecha,
    onRecalcular,
    alerta = null,
    esDiaDeLlamada = true,
    diasParaLlamar = 0,
    esPendiente = true,
    onRegistrarLlamada,
}) {
    const autorizado = montoAutorizado(cliente);
    const consolidado = saldoTotalCliente(cliente);
    const vencido = saldoVencidoCliente(cliente);
    const facturaActiva = facturaCriticaCliente(cliente);
    const numFolios = cliente?.cantidad_folios_activos ?? facturasActivasCliente(cliente).length;

    const destacarCard = modo === 'operacional'
        ? (alerta?.tipo === 'limite_superado' || esDiaDeLlamada)
        : hasAlerta;

    const { colorBadge, textoBadge, fechaFormateada, diasRestantes } = calcularBadgeVencimiento(
        facturaActiva?.fecha_vencimiento,
    );

    return (
        <div
            className={geliaCardClass(
                `p-6 border flex flex-col hover:shadow-md transition-all duration-300 ${
                    destacarCard
                        ? 'border-red-500/50 bg-red-500/5 dark:bg-red-500/10 hover:border-red-500'
                        : 'theme-border hover:border-[var(--color-primario)]/50'
                }`,
            )}
        >
            <div className="flex justify-between items-start mb-4">
                <div className="flex-1 min-w-0">
                    <div className="flex items-center flex-wrap gap-2 mb-1.5">
                        <span className="text-xs font-black uppercase tracking-widest text-[var(--color-primario)]">
                            No. {cliente.numero_cliente}
                        </span>

                        {modo === 'operacional' ? (
                            <>
                                {alerta?.tipo === 'limite_superado' ? (
                                    <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest bg-purple-500 text-white animate-pulse">
                                        Límite Superado
                                    </span>
                                ) : !esDiaDeLlamada ? (
                                    <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest bg-amber-500 text-white">
                                        {diasParaLlamar} {diasParaLlamar === 1 ? 'día' : 'días'} p/ llamar
                                    </span>
                                ) : (
                                    <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest bg-red-500 text-white animate-pulse">
                                        Vencido ({alerta?.dias_atraso ?? 0} días)
                                    </span>
                                )}
                                {!esPendiente && (
                                    <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest bg-blue-500 text-white">
                                        {alerta?.estado?.replace('_', ' ')}
                                    </span>
                                )}
                            </>
                        ) : (
                            <>
                                {cliente.alerta_aumento_credito && (
                                    <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest bg-red-500 text-white animate-pulse">
                                        Alerta
                                    </span>
                                )}
                                {limiteSuperado && (
                                    <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest bg-purple-500 text-white animate-pulse">
                                        Límite Excedido
                                    </span>
                                )}
                                {tieneAbonoActivo(cliente) && (
                                    <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                                        Abonado
                                    </span>
                                )}
                            </>
                        )}

                        {numFolios > 1 && (
                            <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest bg-blue-500/10 text-blue-500 border border-blue-500/20">
                                {numFolios} folios
                            </span>
                        )}
                    </div>

                    <h3 className="text-base font-bold theme-text-main leading-snug">
                        {cliente.nombre}
                    </h3>
                    <p className="text-xs theme-text-muted mt-1.5">{cliente.rfc || 'Sin RFC'}</p>
                </div>

                <div className="flex items-center gap-1 shrink-0 ml-2">
                    {numFolios > 0 && onVerFolios && (
                        <button
                            type="button"
                            onClick={onVerFolios}
                            className="p-2 rounded-xl theme-element border border-transparent hover:border-[var(--color-primario)]/30 text-zinc-400 hover:text-[var(--color-primario)] transition-colors"
                            title="Ver todos los folios"
                        >
                            <CreditCard className="w-4 h-4" />
                        </button>
                    )}
                    {puedeVerBitacora && onBitacora && (
                        <button
                            type="button"
                            onClick={onBitacora}
                            className="p-2 rounded-xl theme-element border border-transparent hover:border-[var(--color-primario)]/30 text-zinc-400 hover:text-[var(--color-primario)] transition-colors"
                            title="Ver Bitácora"
                        >
                            <FileText className="w-4 h-4" />
                        </button>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-3 gap-3 mt-auto pt-4 border-t theme-border/60">
                <div>
                    <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1">Saldo Total</p>
                    <p className="text-base font-black text-amber-600 dark:text-amber-400">
                        {consolidado != null ? formatoMoneda(consolidado) : <span className="text-sm font-semibold theme-text-muted">S/S</span>}
                    </p>
                </div>
                <div>
                    <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1">Saldo Vencido</p>
                    <p className={`text-base font-black ${vencido > 0 ? 'text-red-500' : 'theme-text-muted'}`}>
                        {vencido > 0 ? formatoMoneda(vencido) : '—'}
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1">Autorizado</p>
                    <p className="text-base font-black theme-text-main">
                        {autorizado != null ? formatoMoneda(autorizado) : <span className="text-sm font-semibold theme-text-muted">N/A</span>}
                    </p>
                </div>
            </div>

            {modo === 'operacional' && (
                <div className="grid grid-cols-2 gap-4 mt-4 pt-4 border-t theme-border/60">
                    <div>
                        <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1">Teléfono</p>
                        <p className="text-sm font-bold theme-text-main truncate">
                            {cliente.telefono || <span className="theme-text-muted font-normal italic">Sin teléfono</span>}
                        </p>
                    </div>
                    <div className="text-right">
                        <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1">Correo</p>
                        <p className="text-sm font-bold theme-text-main truncate" title={cliente.correo_electronico}>
                            {cliente.correo_electronico || <span className="theme-text-muted font-normal italic">Sin correo</span>}
                        </p>
                    </div>
                </div>
            )}

            {numFolios > 0 && (
                <div className="mt-4 pt-4 border-t theme-border/60">
                    <DesgloseFoliosCliente
                        cliente={cliente}
                        compacto
                        maxItems={numFolios > 1 ? 2 : 1}
                        onVerTodos={numFolios > 2 && onVerFolios ? onVerFolios : null}
                    />
                </div>
            )}

            <div className="flex flex-col lg:flex-row lg:items-center justify-between mt-4 pt-4 border-t theme-border/60 gap-4">
                <div className="flex flex-wrap items-center gap-4">
                    <div className="flex flex-col">
                        <span className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1">Plazo</span>
                        {cliente.dias_credito > 0 ? (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-black bg-blue-500/10 text-blue-500 border border-blue-500/20 w-fit">
                                {cliente.dias_credito} días
                            </span>
                        ) : (
                            <span className="text-sm font-bold theme-text-muted">—</span>
                        )}
                    </div>

                    {textoBadge && (
                        <div className="flex flex-col border-l theme-border/50 pl-4">
                            <span className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1">
                                {diasRestantes < 0 ? 'Más crítico' : 'Próximo vencimiento'}
                            </span>
                            {facturaActiva?.folio && (
                                <span className="text-xs font-black text-[var(--color-primario)] mb-1">
                                    Folio {facturaActiva.folio}
                                </span>
                            )}
                            <span className="text-sm font-black uppercase tracking-widest theme-text-main mb-1.5 leading-none">
                                {fechaFormateada}
                            </span>
                            <span className={`inline-flex items-center px-3 py-1.5 rounded-md text-sm font-black border ${colorBadge} w-fit`}>
                                {textoBadge}
                                {facturaActiva?.monto != null && (
                                    <span className="ml-2 opacity-90">
                                        · {formatoMoneda(facturaActiva.monto)}
                                    </span>
                                )}
                            </span>
                        </div>
                    )}
                </div>

                <div className="flex flex-col items-end gap-3">
                    <div className="flex flex-col items-end">
                        <span className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1">Inicio de Crédito</span>
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-bold theme-text-main">
                                {cliente.fecha_inicio_credito || <span className="theme-text-muted">—</span>}
                            </span>
                            {modo === 'credito' && puedeRepararFecha && (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => onRepararFecha?.(cliente.id, cliente.fecha_inicio_credito)}
                                        title="Reparar Fecha de Inicio de Crédito"
                                        className="p-1 rounded-full text-zinc-400 hover:text-blue-500 hover:bg-blue-500/10 transition-colors"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" /><path d="m15 5 4 4" /></svg>
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => onRecalcular?.(cliente.id)}
                                        title="Recalcular vencimiento y alertas"
                                        className="p-1 rounded-full text-zinc-400 hover:text-emerald-500 hover:bg-emerald-500/10 transition-colors"
                                    >
                                        <RefreshCw className="w-3.5 h-3.5" />
                                    </button>
                                </>
                            )}
                        </div>
                    </div>

                    {modo === 'operacional' && onRegistrarLlamada && (
                        <button
                            type="button"
                            onClick={onRegistrarLlamada}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[11px] font-black uppercase theme-element border theme-border hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all"
                        >
                            <MessageSquare className="w-4 h-4" /> Registrar llamada
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
