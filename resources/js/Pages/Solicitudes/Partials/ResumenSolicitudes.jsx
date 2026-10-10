import React from 'react';
import { ChevronDown, Users, X } from 'lucide-react';
import { geliaCardClass } from '@/utils/geliaTheme';
import { claseEtiquetaTipoCliente } from './solicitudesStyles';

const METRICAS_TOTALES = [
    ['clientes', 'Clientes únicos'],
    ['solicitudes', 'Solicitudes'],
    ['vigentes', 'Solicitudes vigentes'],
    ['errores', 'Solicitudes con error'],
];

const METRICAS_TIPO = [
    ['clientes', 'Clientes únicos'],
    ['solicitudes', 'Solicitudes'],
    ['vigentes', 'Vigentes'],
    ['errores', 'Con error'],
];

const FOCUS_TIPO = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]';

function idFiltroTipo(tipo) {
    return tipo.id == null ? 'SIN_TIPO' : String(tipo.id);
}

function CeldaKpi({ etiqueta, valor, numero }) {
    return (
        <div>
            <p className="m-0 text-xs theme-text-muted">{etiqueta}</p>
            <p className="m-0 mt-1 text-xl font-semibold tabular-nums theme-text-main sm:text-2xl">{numero(valor)}</p>
        </div>
    );
}

function EtiquetaTipo({ nombre }) {
    return (
        <span className={claseEtiquetaTipoCliente(nombre)}>
            <Users className="h-3 w-3 shrink-0" aria-hidden="true" />
            <span className="truncate">{nombre}</span>
        </span>
    );
}

function TarjetaTipoKpi({ tipo, numero, activo, onSeleccionar, onQuitarFiltro }) {
    const filtroId = idFiltroTipo(tipo);

    return (
        <button
            type="button"
            className={`gelia-tag-resultados-tipo gelia-tag-resultados-tipo-btn ${FOCUS_TIPO}`}
            data-active={activo}
            aria-pressed={activo}
            onClick={() => (activo ? onQuitarFiltro?.() : onSeleccionar?.(filtroId))}
            aria-label={`Filtrar por ${tipo.nombre}: ${numero(tipo.solicitudes)} solicitudes`}
        >
            <div className="gelia-tag-resultados-tipo-encabezado">
                <EtiquetaTipo nombre={tipo.nombre} />
            </div>

            <div className="gelia-tag-resultados-tipo-resumen" aria-hidden="true">
                <span className="gelia-tag-resultados-tipo-resumen-total tabular-nums">{numero(tipo.solicitudes)}</span>
                <span className="text-[10px] font-semibold uppercase tracking-wide theme-text-muted">Sol.</span>
            </div>

            <dl className="gelia-tag-resultados-tipo-metricas">
                {METRICAS_TIPO.map(([key, label]) => (
                    <div key={key}>
                        <dt className="text-xs theme-text-muted">{label}</dt>
                        <dd className="m-0 mt-0.5 text-lg font-semibold tabular-nums theme-text-main">
                            {numero(tipo[key])}
                        </dd>
                    </div>
                ))}
            </dl>
        </button>
    );
}

export default function ResumenSolicitudes({
    resumen,
    filtroTipoCliente = '',
    onFiltrarTipo,
    onLimpiarTipo,
}) {
    const numero = (value) => new Intl.NumberFormat('es-MX').format(value || 0);
    const tipoActivo = filtroTipoCliente ? String(filtroTipoCliente) : '';

    return (
        <section
            className={geliaCardClass('gelia-tag-resultados p-4 md:p-5')}
            aria-label="Resumen de los resultados filtrados"
        >
            <div className="gelia-solicitudes-indicadores gelia-tag-resultados-kpis" role="list">
                {METRICAS_TOTALES.map(([key, label]) => (
                    <div key={key} className="gelia-solicitudes-indicador" role="listitem">
                        <CeldaKpi etiqueta={label} valor={resumen[key]} numero={numero} />
                    </div>
                ))}
            </div>

            <details className="gelia-tag-event-details gelia-tag-resultados-details" defaultOpen={Boolean(resumen.tipos?.length)}>
                <summary>
                    Desglose por tipo de cliente
                    <span className="hidden text-xs font-normal theme-text-muted sm:inline">
                        Toca un tipo para filtrar la lista
                    </span>
                    <ChevronDown className="h-4 w-4 shrink-0" aria-hidden="true" />
                </summary>
                <div className="gelia-tag-resultados-details-body">
                    {tipoActivo && onLimpiarTipo && (
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="m-0 text-xs theme-text-muted">
                                Mostrando solo un tipo de cliente. Los totales arriba corresponden a este filtro.
                            </p>
                            <button
                                type="button"
                                className={`gelia-tag-resultados-limpiar-tipo ${FOCUS_TIPO}`}
                                onClick={onLimpiarTipo}
                            >
                                <X className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                Ver todos los tipos
                            </button>
                        </div>
                    )}
                    <p className="m-0 text-xs theme-text-muted">
                        Tipo registrado en cada solicitud. Un cliente con solicitudes de distintos tipos puede aparecer en más de una tarjeta.
                        Vigentes: aprobadas o verificadas sin cancelación pendiente ni reversión. Con error: estado Incorrecta.
                    </p>
                    {resumen.tipos?.length ? (
                        <div className="gelia-tag-resultados-tipos" role="list">
                            {resumen.tipos.map((tipo) => (
                                <TarjetaTipoKpi
                                    key={tipo.id ?? 'sin-tipo'}
                                    tipo={tipo}
                                    numero={numero}
                                    activo={tipoActivo === idFiltroTipo(tipo)}
                                    onSeleccionar={onFiltrarTipo}
                                    onQuitarFiltro={onLimpiarTipo}
                                />
                            ))}
                        </div>
                    ) : (
                        <p className="m-0 text-sm theme-text-muted">No hay solicitudes para estos filtros.</p>
                    )}
                </div>
            </details>
        </section>
    );
}
