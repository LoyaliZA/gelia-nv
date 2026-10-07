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

            {puedeOperar && (
                <ResolucionMasiva periodo={periodo} tipos={opciones.tipos || []} />
            )}

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
                                                {inc.numero_cliente ? `${inc.numero_cliente} · ` : ''}{inc.nombre || 'Sin cliente en la base'}
                                                {inc.documentos_pendientes > 0 && (
                                                    <span className="block text-xs theme-text-muted mt-1">
                                                        {inc.documentos_pendientes} documento(s) pendiente(s)
                                                    </span>
                                                )}
                                                {inc.lista_bloqueada && (
                                                    <span className="block text-xs theme-text-aviso mt-1">
                                                        Lista protegida. La divergencia se mantiene hasta quitar esa protección.
                                                    </span>
                                                )}
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
                        Listas o documentos excluidos por configuración. No bloquean el cierre ni cuentan en incidencias abiertas del período; solo documentan que la remisión no sumó al acumulado.
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

function ResolucionMasiva({ periodo, tipos }) {
    const codigoInicial = tipos[0]?.codigo || '';
    const form = useForm({
        periodo_id: periodo?.id || '',
        codigo: codigoInicial,
        accion: accionesDeTipo(codigoInicial)[0]?.id || 'nota',
        resolucion: '',
    });

    const acciones = accionesDeTipo(form.data.codigo);

    const enviar = (event) => {
        event.preventDefault();
        form.post(route('escalonamiento.incidencias.resolver_lote'), { preserveScroll: true });
    };

    return (
        <form onSubmit={enviar} className={`${geliaCardClass('p-5 md:p-6')} space-y-4`}>
            <div className="space-y-1">
                <h3 className="text-base font-bold theme-text-main m-0">Resolución masiva</h3>
                <p className="text-sm theme-text-muted m-0">
                    Aplica la misma resolución a las incidencias abiertas de un tipo. Las que no puedan resolverse se quedan abiertas.
                </p>
            </div>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label className="space-y-2">
                    <span className={THEME_LABEL}>Tipo</span>
                    <select
                        className={THEME_SELECT}
                        value={form.data.codigo}
                        onChange={(e) => {
                            const codigo = e.target.value;
                            const siguiente = accionesDeTipo(codigo)[0]?.id || 'nota';
                            form.setData({ ...form.data, codigo, accion: siguiente });
                        }}
                    >
                        {tipos.length === 0 && <option value="">Sin tipos abiertos</option>}
                        {tipos.map((t) => (
                            <option key={t.codigo} value={t.codigo}>{t.etiqueta}</option>
                        ))}
                    </select>
                </label>
                <label className="space-y-2">
                    <span className={THEME_LABEL}>Resolución</span>
                    <select
                        className={THEME_SELECT}
                        value={form.data.accion}
                        onChange={(e) => form.setData('accion', e.target.value)}
                    >
                        {acciones.map((accion) => (
                            <option key={accion.id} value={accion.id}>{accion.etiqueta}</option>
                        ))}
                    </select>
                </label>
                {form.data.accion === 'nota' && (
                    <label className="space-y-2 md:col-span-2">
                        <span className={THEME_LABEL}>Nota</span>
                        <input
                            className={THEME_INPUT}
                            value={form.data.resolucion}
                            onChange={(e) => form.setData('resolucion', e.target.value)}
                            placeholder="Nota que se guarda en cada incidencia"
                        />
                    </label>
                )}
            </div>
            {(form.errors.accion || form.errors.resolucion || form.errors.codigo) && (
                <p className="text-xs theme-text-peligro m-0">{form.errors.accion || form.errors.resolucion || form.errors.codigo}</p>
            )}
            <div className="flex justify-end">
                <button type="submit" className={THEME_BTN_SECONDARY} disabled={form.processing || !form.data.codigo}>
                    Aplicar a las abiertas de este tipo
                </button>
            </div>
        </form>
    );
}

function accionesDeTipo(codigo) {
    if (codigo === 'cliente_no_identificado') {
        return [
            { id: 'crear_cliente_y_registrar', etiqueta: 'Agregar a la base y registrar documentos' },
            { id: 'crear_cliente', etiqueta: 'Agregar a la base de datos' },
            { id: 'nota', etiqueta: 'Cerrar con nota' },
        ];
    }
    if (codigo === 'divergencia_lista_operativa') {
        return [
            { id: 'alinear_lista_operativa', etiqueta: 'Actualizar monto y lista vigente' },
            { id: 'nota', etiqueta: 'Cerrar con nota' },
        ];
    }

    return [{ id: 'nota', etiqueta: 'Cerrar con nota' }];
}

function ResolverIncidencia({ incidencia }) {
    const acciones = incidencia.acciones?.length ? incidencia.acciones : [{ id: 'nota', etiqueta: 'Cerrar con nota' }];
    const form = useForm({
        accion: acciones[0].id,
        resolucion: '',
        cliente_id: '',
        numero_cliente: '',
    });
    const requiereCliente = form.data.accion === 'vincular_cliente' || form.data.accion === 'vincular_cliente_y_registrar';
    const pideNumero = incidencia.requiere_numero && (form.data.accion === 'crear_cliente' || form.data.accion === 'crear_cliente_y_registrar');

    const enviar = (event) => {
        event.preventDefault();
        form.post(route('escalonamiento.incidencias.resolver', incidencia.id), { preserveScroll: true });
    };

    return (
        <form onSubmit={enviar} className="flex flex-col gap-3 min-w-[16rem]">
            <select
                className={THEME_SELECT}
                value={form.data.accion}
                onChange={(e) => form.setData('accion', e.target.value)}
                aria-label={`Resolución incidencia ${incidencia.id}`}
            >
                {acciones.map((accion) => (
                    <option key={accion.id} value={accion.id}>{accion.etiqueta}</option>
                ))}
            </select>
            {pideNumero && (
                <input
                    className={THEME_INPUT}
                    value={form.data.numero_cliente}
                    onChange={(e) => form.setData('numero_cliente', e.target.value)}
                    placeholder="Número de cliente"
                    aria-label={`Número para incidencia ${incidencia.id}`}
                />
            )}
            {requiereCliente && (
                <FiltroClienteEscalonamiento
                    valorId={form.data.cliente_id}
                    label="Cliente existente"
                    onSeleccionar={(cliente) => form.setData('cliente_id', cliente?.id || '')}
                />
            )}
            {form.data.accion === 'nota' && (
                <input
                    className={THEME_INPUT}
                    value={form.data.resolucion}
                    onChange={(e) => form.setData('resolucion', e.target.value)}
                    placeholder="Nota de resolución"
                    aria-label={`Nota incidencia ${incidencia.id}`}
                />
            )}
            <button type="submit" className={THEME_BTN_SECONDARY} disabled={form.processing}>
                Aplicar resolución
            </button>
            {(form.errors.accion || form.errors.resolucion || form.errors.cliente_id || form.errors.numero_cliente) && (
                <p className="text-xs theme-text-peligro m-0">
                    {form.errors.accion || form.errors.resolucion || form.errors.cliente_id || form.errors.numero_cliente}
                </p>
            )}
        </form>
    );
}
