import React from 'react';
import { CheckCircle2, Clock3, FileText, TriangleAlert, Users, X, ArrowUpRight } from 'lucide-react';
import { claseEtiquetaTipoCliente, iconoTipoCliente } from './solicitudesStyles';
import EtiquetaConIcono from '@/Components/EtiquetaConIcono';

// Cada indicador lleva icono y tono; los que filtran la lista abren su pestaña.
const INDICADORES = [
    { key: 'clientes', etiqueta: 'Clientes únicos', icono: Users, tono: 'primario' },
    { key: 'solicitudes', etiqueta: 'Solicitudes', icono: FileText, tono: 'info', tab: 'TODAS' },
    { key: 'vigentes', etiqueta: 'Vigentes', icono: CheckCircle2, tono: 'exito', tab: 'VIGENTES' },
    { key: 'errores', etiqueta: 'Con error', icono: TriangleAlert, tono: 'peligro', tab: 'INCORRECTAS', apagarEnCero: true },
];

const INDICADORES_ACTIVIDAD = [
    { key: 'pendientes', etiqueta: 'Pendientes', icono: Clock3, tono: 'aviso', tab: 'PENDIENTES' },
    { key: 'respondidas_hoy', etiqueta: 'Respondidas hoy', icono: CheckCircle2, tono: 'exito' },
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

function CeldaKpi({ etiqueta, valor, numero, icono: Icono, tono, tab, activo, seleccionado = activo, onSeleccionarTab, apagarEnCero }) {
    const apagado = apagarEnCero && !valor;
    const Tag = tab && onSeleccionarTab ? 'button' : 'div';
    const botonProps = Tag === 'button'
        ? { type: 'button', onClick: () => onSeleccionarTab(tab), 'aria-pressed': Boolean(seleccionado), 'aria-label': `${etiqueta}: ${numero(valor)}. Ver solicitudes` }
        : {};

    return (
        <div role="listitem" className="contents">
        <Tag className={`gelia-solicitudes-indicador gelia-tag-kpi ${Tag === 'button' ? FOCUS_TIPO : ''}`} data-tone={apagado ? 'neutro' : tono} data-active={seleccionado || undefined} {...botonProps}>
            <span className="gelia-tag-kpi-icono" aria-hidden="true"><Icono className="h-5 w-5" /></span>
            <span className="min-w-0 text-left">
                <span className="block text-xs font-medium theme-text-muted">{etiqueta}</span>
                <span className="mt-0.5 block text-2xl font-bold tabular-nums leading-none theme-text-main">{numero(valor)}</span>
            </span>
            {Tag === 'button' && <ArrowUpRight className="ml-auto h-4 w-4 shrink-0 theme-text-muted" aria-hidden="true" />}
        </Tag>
        </div>
    );
}

function EtiquetaTipo({ nombre }) {
    return (
        <EtiquetaConIcono
            icon={iconoTipoCliente(nombre)}
            className={claseEtiquetaTipoCliente(nombre)}
            iconClassName="h-3 w-3 shrink-0"
            size={12}
        >
            <span className="truncate">{nombre}</span>
        </EtiquetaConIcono>
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

export function DesgloseTipoCliente({
    resumen,
    filtroTipoCliente = '',
    onFiltrarTipo,
    onLimpiarTipo,
}) {
    const numero = (value) => new Intl.NumberFormat('es-MX').format(value || 0);
    const tipoActivo = filtroTipoCliente ? String(filtroTipoCliente) : '';

    return (
        <div className="gelia-tag-resultados-details-body">
            {tipoActivo && onLimpiarTipo && (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="m-0 text-xs theme-text-muted">
                        Mostrando solo un tipo de cliente. Los totales de la bandeja corresponden a este filtro.
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
    );
}

export default function ResumenSolicitudes({
    resumen,
    metricas = null,
    tabActiva = 'TODAS',
    onSeleccionarTab,
}) {
    const numero = (value) => new Intl.NumberFormat('es-MX').format(value || 0);

    return (
        <section
            className="gelia-solicitudes-indicadores gelia-tag-resultados-kpis"
            aria-label="Resumen de los resultados filtrados"
            role="list"
        >
            {INDICADORES.map(({ key, ...kpi }) => (
                <CeldaKpi key={key} {...kpi} valor={resumen[key]} numero={numero} activo={kpi.tab === tabActiva && kpi.tab !== 'TODAS'} seleccionado={kpi.tab === tabActiva} onSeleccionarTab={onSeleccionarTab} />
            ))}
            {metricas && INDICADORES_ACTIVIDAD.map(({ key, ...kpi }) => (
                <CeldaKpi key={key} {...kpi} valor={metricas[key]} numero={numero} activo={kpi.tab === tabActiva} onSeleccionarTab={onSeleccionarTab} />
            ))}
        </section>
    );
}
