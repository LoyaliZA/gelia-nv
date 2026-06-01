import React from 'react';
import { Link } from '@inertiajs/react';
import { Briefcase, Clock, DollarSign, AlertTriangle } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';
import { formatoDeduccionEntera, formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';

export default function WidgetRh({ rh_widget = {}, variant = 'desktop' }) {
    const pendientesHe = rh_widget.pendientes_he ?? rh_widget.pendientes ?? 0;
    const montoPendienteHe = rh_widget.monto_pendiente_he ?? rh_widget.monto_pendiente ?? 0;
    const pendientesInc = rh_widget.pendientes_incidencias ?? 0;
    const montoDeduccionInc = rh_widget.monto_deduccion_incidencias ?? 0;
    const destacadosHe = rh_widget.destacados_he ?? rh_widget.destacados ?? [];
    const destacadosInc = rh_widget.destacados_incidencias ?? [];

    const totalPendientes = pendientesHe + pendientesInc;
    const hayPendientes = totalPendientes > 0;

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Recursos Humanos_"
            icon={Briefcase}
            iconClassName="text-rose-500"
            href={route('rh.index')}
            ctaLabel="Abrir módulo RH"
            minimalCount={totalPendientes}
            minimalCountLabel={hayPendientes ? 'Pendientes RH' : 'Al día'}
            badge={hayPendientes ? (
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-amber-600 bg-amber-500/10 px-2 py-1 rounded-md border border-amber-500/20 shrink-0">
                    <Clock className="w-3 h-3" />
                    {totalPendientes} pendientes
                </span>
            ) : (
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded-md border border-emerald-500/20 shrink-0">
                    <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full" />
                    OK
                </span>
            )}
            summary={hayPendientes ? (
                <div className="flex flex-wrap gap-2">
                    {pendientesHe > 0 && (
                        <span className="text-[9px] font-black uppercase px-2 py-1 rounded-lg bg-rose-500/10 text-rose-600 border border-rose-500/20">
                            HE {formatoMoneda(montoPendienteHe)}
                        </span>
                    )}
                    {pendientesInc > 0 && (
                        <span className="text-[9px] font-black uppercase px-2 py-1 rounded-lg bg-amber-500/10 text-amber-600 border border-amber-500/20">
                            Ded. {formatoDeduccionEntera(montoDeduccionInc)}
                        </span>
                    )}
                </div>
            ) : null}
        >
            {destacadosHe.length === 0 && destacadosInc.length === 0 ? (
                <p className="text-[11px] theme-text-muted italic m-0 py-2">Sin pendientes de HE ni incidencias.</p>
            ) : (
                <ul className="space-y-2">
                    {destacadosHe.map((reg) => (
                        <li key={`he-${reg.id}`}>
                            <Link
                                href={route('rh.horas_extra.show', reg.id)}
                                className="flex items-center justify-between gap-2 p-2 rounded-xl hover:bg-black/[0.03] dark:hover:bg-white/[0.03] transition-colors"
                            >
                                <div className="min-w-0">
                                    <p className="text-[10px] font-mono font-bold theme-text-main m-0 truncate">{reg.folio}</p>
                                    <p className="text-[10px] theme-text-muted m-0 truncate">{nombreCompletoColaborador(reg.colaborador)}</p>
                                </div>
                                <div className="text-right shrink-0">
                                    <p className="text-[10px] font-bold theme-text-main m-0 flex items-center gap-1 justify-end">
                                        <Clock className="w-3 h-3" />
                                        {formatoMoneda(reg.total_economico)}
                                    </p>
                                    <p className="text-[9px] theme-text-muted m-0">Horas extra</p>
                                </div>
                            </Link>
                        </li>
                    ))}
                    {destacadosInc.map((reg) => (
                        <li key={`inc-${reg.id}`}>
                            <Link
                                href={route('rh.deducciones.show', reg.id)}
                                className="flex items-center justify-between gap-2 p-2 rounded-xl hover:bg-black/[0.03] dark:hover:bg-white/[0.03] transition-colors"
                            >
                                <div className="min-w-0">
                                    <p className="text-[10px] font-mono font-bold theme-text-main m-0 truncate">{reg.folio}</p>
                                    <p className="text-[10px] theme-text-muted m-0 truncate">{nombreCompletoColaborador(reg.colaborador)}</p>
                                </div>
                                <div className="text-right shrink-0">
                                    <p className="text-[10px] font-bold theme-text-main m-0 flex items-center gap-1 justify-end">
                                        <AlertTriangle className="w-3 h-3" />
                                        {formatoDeduccionEntera(reg.total_deduccion)}
                                    </p>
                                    <p className="text-[9px] theme-text-muted m-0">Incidencia</p>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </DashboardAdaptiveWidget>
    );
}
