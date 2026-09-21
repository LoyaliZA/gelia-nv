import React from 'react';
import CronometroVisualOperacion from './CronometroVisualOperacion';

export default function MetricasAtencionCompacta({
    atencion,
    servidorAt,
}) {
    if (!atencion) {
        return null;
    }

    const enCurso = Boolean(atencion.atencion_en_curso);
    const esperaVencida = Boolean(atencion.espera_inicial_vencida);
    const prorrogaActiva = Boolean(atencion.prorroga_activa);

    return (
        <div className="rounded-xl px-3 py-2 bg-black/5 dark:bg-white/5 space-y-1.5">
            <div className="flex items-center justify-between gap-2 min-w-0">
                <div className="min-w-0">
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 truncate">
                        {atencion.folio}
                    </p>
                    {atencion.cliente && (
                        <p className="text-xs font-semibold theme-text-main m-0 truncate">
                            {atencion.cliente}
                        </p>
                    )}
                </div>
                {enCurso ? (
                    prorrogaActiva ? (
                        <span className="shrink-0 inline-flex px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-500/15 text-amber-800 dark:text-amber-200">
                            Prórroga
                        </span>
                    ) : (
                        <span className="shrink-0 inline-flex px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-emerald-500/15 text-emerald-800 dark:text-emerald-200">
                            En curso
                        </span>
                    )
                ) : (
                    <span className={`shrink-0 inline-flex px-2 py-0.5 rounded-full text-[9px] font-black uppercase ${
                        esperaVencida
                            ? 'bg-amber-500/15 text-amber-800 dark:text-amber-200'
                            : 'bg-sky-500/15 text-sky-800 dark:text-sky-200'
                    }`}
                    >
                        Espera
                    </span>
                )}
            </div>

            {enCurso ? (
                <CronometroVisualOperacion
                    etiqueta="Atención"
                    referenciaAt={atencion.atencion_inicio_at}
                    servidorAt={servidorAt}
                    modo="transcurrido"
                    compacto
                    alerta={prorrogaActiva}
                />
            ) : (
                <CronometroVisualOperacion
                    etiqueta="Inicio"
                    referenciaAt={atencion.espera_inicial_expira_at}
                    servidorAt={servidorAt}
                    modo="restante"
                    compacto
                    alerta={esperaVencida}
                />
            )}
        </div>
    );
}
