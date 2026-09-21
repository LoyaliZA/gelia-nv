import React from 'react';
import { ChevronRight, Megaphone, UserRound } from 'lucide-react';
import NombreAjustable from './NombreAjustable';

function EncabezadoTarjeta({ titulo, badge, badgeTono = 'muted' }) {
    const tono = badgeTono === 'primario'
        ? { color: 'var(--color-primario)', borderColor: 'color-mix(in srgb, var(--color-primario) 35%, transparent)' }
        : { color: 'var(--theme-text-muted)', borderColor: 'var(--theme-border)' };

    return (
        <div className="mb-3 flex items-center justify-between gap-2">
            <p className="text-[11px] font-black uppercase tracking-[0.18em] theme-text-muted">{titulo}</p>
            {badge ? (
                <span
                    className="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-bold"
                    style={tono}
                >
                    {badgeTono === 'primario' ? (
                        <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} aria-hidden />
                    ) : null}
                    {badge}
                </span>
            ) : null}
        </div>
    );
}

function CurrentTurnCard({ turno }) {
    return (
        <article
            className="flex min-h-0 flex-1 flex-col rounded-[1.75rem] border theme-border theme-surface p-4"
            data-pdv-sala-llamado-actual
            aria-live="polite"
        >
            <EncabezadoTarjeta
                titulo="Turno actual"
                badge="Te estamos atendiendo"
                badgeTono="primario"
            />
            {!turno ? (
                <div className="flex flex-1 items-center justify-center" data-pdv-sala-sin-llamado>
                    <p className="text-center text-lg font-semibold theme-text-muted">
                        Esperando siguiente turno
                    </p>
                </div>
            ) : (
                <div
                    key={turno.turno_id}
                    className="grid min-h-0 flex-1 grid-cols-[minmax(0,1.2fr)_auto_minmax(0,0.9fr)] items-center gap-3"
                    style={{ animation: 'gelia-page-reveal 420ms ease' }}
                >
                    <div className="min-h-0 min-w-0">
                        <p className="text-[clamp(2.4rem,4.2vw,4.4rem)] font-black leading-none tracking-tight theme-text-main" data-pdv-sala-folio>
                            {turno.folio}
                        </p>
                        <NombreAjustable
                            className="mt-2 font-semibold theme-text-main"
                            minPx={16}
                            maxPx={26}
                            maxLines={3}
                        >
                            {turno.snapshot_nombre_llamado}
                        </NombreAjustable>
                    </div>
                    <span className="h-16 w-px self-center" style={{ backgroundColor: 'var(--theme-border)' }} aria-hidden />
                    <div className="min-w-0">
                        <UserRound className="mb-2 h-6 w-6 theme-text-primario" aria-hidden />
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                            Vendedor asignado
                        </p>
                        <NombreAjustable className="mt-1 font-bold theme-text-main" minPx={14} maxPx={20} maxLines={3}>
                            {turno.atencion_nombre || turno.atencion_primer_nombre || '—'}
                        </NombreAjustable>
                    </div>
                </div>
            )}
        </article>
    );
}

function UpcomingTurnsCard({ turnos }) {
    return (
        <article className="flex min-h-0 flex-[0.85] flex-col rounded-[1.75rem] border theme-border theme-surface p-4">
            <EncabezadoTarjeta titulo="Próximos turnos" badge="En espera" />
            {!turnos.length ? (
                <p className="flex flex-1 items-center justify-center text-sm theme-text-muted">
                    No hay clientes en espera.
                </p>
            ) : (
                <ul className="flex min-h-0 flex-1 flex-col gap-2 overflow-hidden">
                    {turnos.map((turno) => (
                        <li
                            key={turno.turno_id}
                            className="flex min-h-0 items-center justify-between gap-3 rounded-2xl theme-element px-3 py-2"
                        >
                            <div className="min-w-0">
                                <p className="text-lg font-black theme-text-main">{turno.folio}</p>
                                <NombreAjustable className="theme-text-muted" minPx={12} maxPx={16} maxLines={2}>
                                    {turno.snapshot_nombre_llamado}
                                </NombreAjustable>
                            </div>
                            <ChevronRight className="h-5 w-5 shrink-0 theme-text-muted" aria-hidden />
                        </li>
                    ))}
                </ul>
            )}
        </article>
    );
}

function PreviousTurnsCard({ turnos }) {
    return (
        <article className="flex min-h-0 flex-[0.75] flex-col rounded-[1.75rem] border theme-border theme-surface p-4">
            <EncabezadoTarjeta titulo="Turnos anteriores" badge="Ya atendidos" />
            {!turnos.length ? (
                <p className="flex flex-1 items-center justify-center text-sm theme-text-muted">
                    Aún no hay turnos atendidos.
                </p>
            ) : (
                <ul className="flex min-h-0 flex-1 flex-col gap-2 overflow-hidden">
                    {turnos.map((turno) => (
                        <li key={turno.turno_id} className="rounded-2xl theme-element px-3 py-2">
                            <div className="flex items-start justify-between gap-2">
                                <p className="text-sm font-black theme-text-main">{turno.folio}</p>
                                <p className="text-sm font-bold tabular-nums theme-text-main">{turno.hora}</p>
                            </div>
                            <NombreAjustable className="mt-0.5 theme-text-muted" minPx={11} maxPx={14} maxLines={2}>
                                {turno.snapshot_nombre_llamado}
                            </NombreAjustable>
                            <p className="mt-1 text-[11px] theme-text-muted">
                                Atendido por: <span className="font-semibold theme-text-main">{turno.atendido_por || '—'}</span>
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </article>
    );
}

export default function QueuePanel({ turnoActual, proximos = [], anteriores = [] }) {
    return (
        <aside className="flex h-full min-h-0 flex-col gap-3" data-pdv-sala-cola>
            <div className="flex items-center gap-2 px-1">
                <Megaphone className="h-4 w-4 theme-text-primario" aria-hidden />
                <span className="text-[10px] font-black uppercase tracking-[0.2em] theme-text-muted">
                    Turnos
                </span>
            </div>
            <CurrentTurnCard turno={turnoActual} />
            <UpcomingTurnsCard turnos={proximos} />
            <PreviousTurnsCard turnos={anteriores} />
        </aside>
    );
}
