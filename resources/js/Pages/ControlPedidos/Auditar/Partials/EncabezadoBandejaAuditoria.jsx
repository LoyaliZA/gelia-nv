import React from 'react';
import { ClipboardCheck, Loader2 } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';

const DESCRIPCION_ESCRITORIO =
    'Valida pagos, cobertura y remisión antes de que el pedido avance a CEDIS y logística.';
const DESCRIPCION_MOVIL = 'Pagos, cobertura y remisión.';

function LineaSincronizacion({ cargando, ultimaSync, atencion }) {
    const hora = ultimaSync && !cargando
        ? ultimaSync.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
        : null;

    return (
        <p className="m-0 text-[11px] md:text-xs font-medium theme-text-muted min-w-0" aria-live="polite">
            {cargando ? (
                <span className="inline-flex items-center gap-1.5">
                    <Loader2 className="w-3 h-3 shrink-0 animate-spin theme-text-primario" aria-hidden />
                    <span>Sincronizando listado…</span>
                </span>
            ) : (
                <span className="block leading-snug">
                    En vivo · 15 s
                    {typeof atencion === 'number' && atencion > 0 && (
                        <>
                            <span className="mx-1 opacity-50" aria-hidden>·</span>
                            <span className="font-semibold theme-text-main tabular-nums">{atencion}</span>
                            <span> requieren atención</span>
                        </>
                    )}
                    {hora ? (
                        <>
                            <span className="hidden sm:inline">
                                <span className="mx-1 opacity-50" aria-hidden>·</span>
                                Actualizado {hora}
                            </span>
                            <span className="sm:hidden block tabular-nums mt-0.5">Actualizado {hora}</span>
                        </>
                    ) : null}
                </span>
            )}
        </p>
    );
}

export default function EncabezadoBandejaAuditoria({
    cargando = false,
    ultimaSync = null,
    atencion = 0,
}) {
    return (
        <header className={geliaCardClass('p-4 md:p-5 overflow-visible relative z-20')}>
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between md:gap-3">
                <div className="min-w-0 flex-1 space-y-1">
                    <p className="m-0 flex items-center gap-1.5 text-xs font-semibold theme-text-muted">
                        <ClipboardCheck
                            className="w-3.5 h-3.5 shrink-0 opacity-70"
                            style={{ color: 'var(--color-primario)' }}
                            aria-hidden
                        />
                        Control de pedidos · Auxiliar
                    </p>
                    <h1 className="m-0 text-[1.375rem] md:text-[1.75rem] font-bold leading-tight tracking-tight theme-text-main">
                        Revisión de pedidos
                    </h1>
                    <p className="hidden md:block m-0 text-xs md:text-sm font-medium theme-text-muted leading-snug max-w-xl">
                        {DESCRIPCION_ESCRITORIO}
                    </p>
                    <p className="md:hidden m-0 text-xs font-medium theme-text-muted leading-snug line-clamp-2">
                        {DESCRIPCION_MOVIL}
                    </p>
                </div>

                <div className="shrink-0 w-full md:w-auto md:max-w-md md:text-right">
                    <LineaSincronizacion cargando={cargando} ultimaSync={ultimaSync} atencion={atencion} />
                </div>
            </div>
        </header>
    );
}
