import React from 'react';
import { FileTextIcon } from '@animateicons/react/lucide/file-text-icon';
import CronometroVisualOperacion from './CronometroVisualOperacion';

export default function MetricasAtencionCompacta({
    atencion,
    servidorAt,
    prominente = false,
}) {
    if (!atencion) {
        return null;
    }

    const enCurso = Boolean(atencion.atencion_en_curso);
    const esperaVencida = Boolean(atencion.espera_inicial_vencida);
    const prorrogaActiva = Boolean(atencion.prorroga_activa);
    const esTercero = Boolean(atencion.es_tercero);
    const cuentaTitular = esTercero ? String(atencion.cliente_titular || '').trim() : '';

    const ficha = (
        <div className={`flex items-center justify-between gap-2 min-w-0 ${prominente ? `${esTercero ? 'min-h-14' : 'h-14'} rounded-xl px-3 py-2 theme-element border theme-border` : ''}`}>
            <div className="flex min-w-0 items-center gap-2">
                {prominente && <FileTextIcon size={16} className="shrink-0 theme-text-muted" />}
                <div className="min-w-0">
                    <p className={`${prominente ? 'text-sm font-bold' : 'text-sm font-semibold'} theme-text-main m-0 truncate`}>
                        {atencion.folio}
                    </p>
                    {atencion.cliente && (
                        <div className="flex items-center gap-1.5 min-w-0">
                            <p className={`m-0 truncate ${prominente ? 'text-xs font-semibold theme-text-main' : 'text-sm theme-text-main'}`}>
                                {atencion.cliente}
                            </p>
                            {esTercero && (
                                <span className="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wide bg-sky-500/15 text-sky-800 dark:text-sky-200">
                                    Tercero
                                </span>
                            )}
                        </div>
                    )}
                    {cuentaTitular && (
                        <p className="m-0 truncate text-[11px] font-semibold theme-text-muted" title={`Entra con el número de ${cuentaTitular}`}>
                            Cuenta: {cuentaTitular}
                        </p>
                    )}
                </div>
            </div>
            {enCurso ? (
                <span className="shrink-0 gelia-estado-vivo gelia-estado-vivo--exito gelia-estado-vivo--compacto text-xs font-semibold">
                    En curso
                </span>
            ) : (
                <span className={`shrink-0 gelia-estado-vivo gelia-estado-vivo--compacto text-xs font-semibold ${
                    esperaVencida
                        ? 'gelia-estado-vivo--aviso'
                        : 'gelia-estado-vivo--info'
                }`}
                >
                    Espera
                </span>
            )}
        </div>
    );

    const reloj = enCurso ? (
        <CronometroVisualOperacion
            etiqueta="Atención"
            referenciaAt={atencion.atencion_inicio_at}
            servidorAt={servidorAt}
            modo="transcurrido"
            compacto={!prominente}
            prominente={prominente}
            alerta={prorrogaActiva}
            superficieAlerta={prorrogaActiva ? 'prorroga' : null}
        />
    ) : (
        <CronometroVisualOperacion
            etiqueta="Inicio"
            referenciaAt={atencion.espera_inicial_expira_at}
            servidorAt={servidorAt}
            modo="restante"
            compacto={!prominente}
            prominente={prominente}
            alerta={esperaVencida}
        />
    );

    if (prominente) {
        return (
            <div className="flex flex-col gap-2">
                {ficha}
                {reloj}
            </div>
        );
    }

    return (
        <div className="rounded-xl px-3 py-2 theme-element border theme-border space-y-2">
            {ficha}
            {reloj}
        </div>
    );
}
