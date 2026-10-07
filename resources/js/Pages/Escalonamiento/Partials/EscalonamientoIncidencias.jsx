import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Filter } from 'lucide-react';
import {
    GELIA_ESTADO_VIVO_TONO,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
    THEME_SELECT,
    geliaCardClass,
} from '../../../utils/geliaTheme';
import FiltroClienteEscalonamiento from './FiltroClienteEscalonamiento';
import { TONO_ESTADO_INCIDENCIA, TONO_GRAVEDAD_INCIDENCIA } from './escalonamientoUi';

export default function EscalonamientoIncidencias({
    incidencias = [],
    exclusiones = [],
    filtros = {},
    opciones = { tipos: [], gravedades: [] },
    periodo,
    puedeOperar,
    clienteSeleccionado = null,
}) {
    const [clienteId, setClienteId] = useState(filtros.cliente_id ? String(filtros.cliente_id) : '');

    useEffect(() => {
        setClienteId(filtros.cliente_id ? String(filtros.cliente_id) : '');
    }, [filtros.cliente_id]);

    const aplicar = (event) => {
        event.preventDefault();
        const datos = new FormData(event.currentTarget);
        router.get(route('escalonamiento.index'), {
            periodo_id: periodo?.id,
            tab: 'incidencias',
            tipo_incidencia: datos.get('tipo_incidencia') || undefined,
            estado_incidencia: datos.get('estado_incidencia') || undefined,
            gravedad_incidencia: datos.get('gravedad_incidencia') || undefined,
            cliente_id: clienteId || undefined,
        }, { preserveState: true, preserveScroll: true });
    };

    return (
        <div className="space-y-6">
            <form onSubmit={aplicar} className={`${geliaCardClass('p-5 md:p-6')} space-y-5`}>
                <div className="flex items-center gap-3 theme-text-main">
                    <Filter className="w-5 h-5" aria-hidden />
                    <span className="text-base font-bold">Filtros</span>
                </div>
                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-5">
                    <FiltroClienteEscalonamiento
                        valorId={clienteId}
                        etiquetaInicial={clienteSeleccionado?.etiqueta}
                        onSeleccionar={(c) => setClienteId(c?.id || '')}
                    />
                    <label className="space-y-2 md:col-span-2 xl:col-span-2">
                        <span className={THEME_LABEL}>Tipo</span>
                        <select name="tipo_incidencia" className={THEME_SELECT} defaultValue={filtros.tipo || ''}>
                            <option value="">Todos los tipos</option>
                            {opciones.tipos?.map((t) => (
                                <option key={t.codigo} value={t.codigo}>{t.etiqueta}</option>
                            ))}
                        </select>
                    </label>
                    <label className="space-y-2">
                        <span className={THEME_LABEL}>Estado</span>
                        <select name="estado_incidencia" className={THEME_SELECT} defaultValue={filtros.estado || 'abierta'}>
                            <option value="abierta">Abiertas</option>
                            <option value="resuelta">Resueltas</option>
                            <option value="todas">Todas</option>
                        </select>
                    </label>
                    <label className="space-y-2">
                        <span className={THEME_LABEL}>Gravedad</span>
                        <select name="gravedad_incidencia" className={THEME_SELECT} defaultValue={filtros.gravedad || ''}>
                            <option value="">Todas</option>
                            {opciones.gravedades?.map((g) => (
                                <option key={g} value={g}>{g}</option>
                            ))}
                        </select>
                    </label>
                </div>
                <div className="flex justify-end pt-1"><button type="submit" className={THEME_BTN_SECONDARY}>Aplicar filtros</button></div>
            </form>

            <section className="space-y-4">
                <div className="space-y-1">
                    <h3 className="text-base font-bold theme-text-main m-0">Incidencias accionables</h3>
                    <p className="text-sm theme-text-muted m-0">Revisa y resuelve las incidencias que requieren intervención operativa.</p>
                </div>
                {incidencias.length === 0 ? (
                    <p className="theme-text-muted m-0 text-sm">No hay incidencias con los filtros actuales.</p>
                ) : (
                    <div className={geliaCardClass('overflow-hidden p-0')}>
                        <div className="overflow-x-auto px-3 pt-3 pb-2 sm:px-4 sm:pt-4">
                            <table className="w-full text-sm min-w-[720px]">
                                <thead className="sticky top-0 z-10 theme-surface border-b theme-border">
                                    <tr className="text-left theme-text-muted">
                                        <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Gravedad</th>
                                        <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Tipo</th>
                                        <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Cliente</th>
                                        <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Motivo</th>
                                        <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Estado</th>
                                        {puedeOperar && <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Acción</th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {incidencias.map((inc) => (
                                        <tr key={inc.id} className="border-t theme-border align-top">
                                            <td className="py-3.5 px-4 align-top">
                                                <EtiquetaEstado tono={TONO_GRAVEDAD_INCIDENCIA[inc.gravedad] || 'neutro'} texto={inc.gravedad || '—'} />
                                            </td>
                                            <td className="py-3.5 px-4 align-top">
                                                <EtiquetaEstado
                                                    tono={inc.codigo === 'cliente_no_identificado' ? 'aviso' : 'neutro'}
                                                    texto={inc.tipo_legible || inc.codigo}
                                                />
                                            </td>
                                            <td className="py-3.5 px-4 theme-text-main align-top leading-relaxed">
                                                {inc.numero_cliente ? `${inc.numero_cliente} · ` : ''}{inc.nombre || 'Sin cliente'}
                                            </td>
                                            <td className="py-3.5 px-4 theme-text-muted max-w-md align-top leading-relaxed">{inc.motivo}</td>
                                            <td className="py-3.5 px-4 align-top">
                                                <EtiquetaEstado tono={TONO_ESTADO_INCIDENCIA[inc.estado] || 'neutro'} texto={inc.estado} />
                                            </td>
                                            {puedeOperar && (
                                                <td className="py-3.5 px-4 align-top">
                                                    {inc.estado === 'abierta' && !inc.es_informativa && (
                                                        <ResolverIncidencia incidencia={inc} />
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </section>

            {exclusiones.length > 0 && (
                <section className="space-y-4">
                    <h3 className="text-base font-bold theme-text-main m-0">Exclusiones informativas</h3>
                    <p className="text-sm theme-text-muted m-0">
                        Listas o documentos excluidos por configuración. No requieren resolución operativa.
                    </p>
                    <ul className="m-0 p-0 list-none space-y-2 text-sm">
                        {exclusiones.map((exc) => (
                            <li key={exc.id} className={`${geliaCardClass('p-4')} theme-text-muted leading-relaxed`}>
                                <span className="theme-text-main font-semibold">{exc.numero_cliente} · {exc.nombre || 'Cliente'}</span>
                                <span className="block mt-1">{exc.motivo}</span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}

function EtiquetaEstado({ tono, texto }) {
    const clase = GELIA_ESTADO_VIVO_TONO[tono] || GELIA_ESTADO_VIVO_TONO.neutro;
    return (
        <span className={`gelia-estado-vivo gelia-estado-vivo--compacto inline-flex text-xs font-semibold capitalize ${clase}`}>
            {texto}
        </span>
    );
}

function ResolverIncidencia({ incidencia }) {
    const form = useForm({ resolucion: '' });

    const enviar = (event) => {
        event.preventDefault();
        form.post(route('escalonamiento.incidencias.resolver', incidencia.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={enviar} className="flex flex-col gap-3 min-w-[14rem]">
            <input
                className={THEME_INPUT}
                value={form.data.resolucion}
                onChange={(e) => form.setData('resolucion', e.target.value)}
                placeholder="Nota de resolución"
                aria-label={`Resolución incidencia ${incidencia.id}`}
            />
            <button type="submit" className={THEME_BTN_SECONDARY} disabled={form.processing}>
                Registrar resolución
            </button>
            {form.errors.resolucion && <p className="text-xs theme-text-peligro m-0">{form.errors.resolucion}</p>}
        </form>
    );
}
