import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, Download, Eye, History, Minus, Search } from 'lucide-react';
import BadgeListaDescuento from '../../../Components/BadgeListaDescuento';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import {
    GELIA_ESTADO_VIVO_TONO,
    GELIA_BTN_OUTLINE,
    THEME_BTN_PRIMARY,
    THEME_INPUT,
    THEME_LABEL,
    geliaCardClass,
    geliaToggleBtnClass,
} from '../../../utils/geliaTheme';
import EscalonamientoMenuAcciones from './EscalonamientoMenuAcciones';
import FiltrosEscalonamientoClientes from './FiltrosEscalonamientoClientes';
import {
    dinero,
    ETIQUETAS_RESULTADO,
    TONO_RESULTADO,
} from './escalonamientoUi';

const RAPIDOS = [
    { id: '', label: 'Todos' },
    { id: 'con_cambios', label: 'Con cambios' },
    { id: 'con_pendientes', label: 'Con pendientes' },
    { id: 'sin_compras', label: 'Sin compras' },
    { id: 'no_participantes', label: 'No participantes' },
];

export default function TablaClientesEscalonamiento({
    periodo,
    clientes = { data: [], paginacion: {} },
    filtros = {},
    opcionesListas = [],
    mensajeDocumentos,
}) {
    const payloadBase = () => ({
        periodo_id: periodo?.id,
        tab: 'clientes',
        q: filtros.q || undefined,
        orden: filtros.orden,
        direccion: filtros.direccion,
        filtro_rapido: filtros.filtro_rapido || undefined,
        per_page: filtros.per_page,
        ocultar_inactivos: filtros.ocultar_inactivos ? 1 : undefined,
        lista_vigente_id: filtros.lista_vigente_id || undefined,
        lista_calculada_id: filtros.lista_calculada_id || undefined,
        ventas_min: filtros.ventas_min || undefined,
        ventas_max: filtros.ventas_max || undefined,
        devoluciones_min: filtros.devoluciones_min || undefined,
        devoluciones_max: filtros.devoluciones_max || undefined,
        resultado: filtros.resultado?.length ? (Array.isArray(filtros.resultado) ? filtros.resultado.join(',') : filtros.resultado) : undefined,
    });

    const navegar = (extra = {}) => {
        router.get(route('escalonamiento.index'), { ...payloadBase(), ...extra }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const ordenar = (columna) => {
        const misma = filtros.orden === columna;
        const direccion = misma && filtros.direccion === 'asc' ? 'desc' : 'asc';
        navegar({ orden: columna, direccion });
    };

    const IconoOrden = ({ columna }) => {
        if (filtros.orden !== columna) return <ArrowUpDown className="w-3.5 h-3.5 opacity-40" aria-hidden />;
        return filtros.direccion === 'asc'
            ? <ArrowUp className="w-3.5 h-3.5" aria-hidden />
            : <ArrowDown className="w-3.5 h-3.5" aria-hidden />;
    };

    const enviarBusqueda = (event) => {
        event.preventDefault();
        const q = new FormData(event.currentTarget).get('q');
        navegar({ q: q || undefined, page: 1 });
    };

    const limpiarTodo = () => {
        router.get(route('escalonamiento.index'), { periodo_id: periodo?.id, tab: 'clientes', orden: 'numero', direccion: 'asc' }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const limpiarSoloAvanzados = () => {
        navegar({
            lista_vigente_id: undefined,
            lista_calculada_id: undefined,
            ventas_min: undefined,
            ventas_max: undefined,
            devoluciones_min: undefined,
            devoluciones_max: undefined,
            ocultar_inactivos: undefined,
            resultado: undefined,
            page: 1,
        });
    };

    const hayBusquedaORapido = Boolean(filtros.q || filtros.filtro_rapido);
    const filas = clientes.data || [];
    const paginacion = clientes.paginacion || {};

    return (
        <div className="space-y-5">
            <div className={`${geliaCardClass()} p-5 md:p-6 space-y-5`}>
                <div className="flex flex-col lg:flex-row lg:items-end gap-4">
                    <form onSubmit={enviarBusqueda} className="flex-1 flex flex-col sm:flex-row gap-4 sm:items-end">
                        <label className="flex-1 space-y-1.5">
                            <span className={THEME_LABEL}>Buscar cliente</span>
                            <div className="theme-field-with-icon">
                                <Search className="theme-field-icon" aria-hidden />
                                <input
                                    name="q"
                                    className={`${THEME_INPUT} w-full`}
                                    placeholder="Número o nombre"
                                    defaultValue={filtros.q || ''}
                                />
                            </div>
                        </label>
                        <button type="submit" className={`${THEME_BTN_PRIMARY} shrink-0`}>
                            <Search className="w-4 h-4" aria-hidden />
                            Buscar
                        </button>
                    </form>
                    <div className="flex flex-wrap gap-3 items-center">
                        <a
                            href={route('escalonamiento.clientes.exportar', { periodo_id: periodo?.id, ...payloadBase() })}
                            className={GELIA_BTN_OUTLINE}
                        >
                            <Download className="w-4 h-4" aria-hidden />
                            Exportar
                        </a>
                    </div>
                </div>

                <FiltrosEscalonamientoClientes
                    filtros={filtros}
                    opcionesListas={opcionesListas}
                    onAplicar={(extra) => navegar(extra)}
                    onLimpiar={limpiarSoloAvanzados}
                />

                <div className="flex flex-wrap gap-3" role="group" aria-label="Filtros rápidos">
                    {RAPIDOS.map((item) => (
                        <button
                            key={item.id || 'todos'}
                            type="button"
                            className={geliaToggleBtnClass((filtros.filtro_rapido || '') === item.id)}
                            onClick={() => navegar({ filtro_rapido: item.id || undefined, page: 1 })}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                {(hayBusquedaORapido) && (
                    <button type="button" className={GELIA_BTN_OUTLINE} onClick={limpiarTodo}>
                        Limpiar búsqueda y rápidos
                    </button>
                )}

                {mensajeDocumentos && (
                    <p className="text-sm theme-text-muted m-0">{mensajeDocumentos}</p>
                )}
            </div>

            <div className={geliaCardClass('overflow-hidden p-0')}>
                <div className="overflow-x-auto max-h-[min(70vh,720px)] px-3 pt-3 pb-2 sm:px-4 sm:pt-4">
                    <table className="w-full text-sm min-w-[960px]">
                        <thead className="sticky top-0 z-10 theme-surface border-b theme-border">
                            <tr className="text-left theme-text-muted">
                                <th className="py-3 pl-3 pr-4 text-xs font-bold uppercase tracking-wide sticky left-0 z-20 theme-surface sm:pl-4">
                                    <button type="button" className="inline-flex items-center gap-1" onClick={() => ordenar('nombre')}>
                                        Cliente <IconoOrden columna="nombre" />
                                    </button>
                                </th>
                                <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide text-right">
                                    <button type="button" className="inline-flex items-center gap-1 ml-auto" onClick={() => ordenar('ventas')}>
                                        Ventas <IconoOrden columna="ventas" />
                                    </button>
                                </th>
                                <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide text-right">
                                    <button type="button" className="inline-flex items-center gap-1 ml-auto" onClick={() => ordenar('devoluciones')}>
                                        Devoluciones <IconoOrden columna="devoluciones" />
                                    </button>
                                </th>
                                <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide text-right">
                                    <button type="button" className="inline-flex items-center gap-1 ml-auto" onClick={() => ordenar('neto')}>
                                        Venta neta <IconoOrden columna="neto" />
                                    </button>
                                </th>
                                <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Lista vigente</th>
                                <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Lista calculada</th>
                                <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Resultado</th>
                                <th className="py-3 pl-4 pr-3 text-xs font-bold uppercase tracking-wide text-right sm:pr-4">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="py-8 px-3 text-center text-sm theme-text-muted">
                                        No se encontraron clientes con estos filtros.
                                    </td>
                                </tr>
                            ) : filas.map((fila) => (
                                <tr key={fila.cliente_id} className="border-t theme-border hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                                    <td className="py-3.5 pl-4 pr-4 theme-text-main align-top sticky left-0 theme-surface sm:pl-5">
                                        <span className="font-semibold block">{fila.nombre}</span>
                                        <span className="text-xs theme-text-muted tabular-nums block">{fila.numero_cliente}</span>
                                        {fila.lista_operativa && (
                                            <span className="text-xs theme-text-muted block mt-1.5">
                                                Operativa: <BadgeListaDescuento nombre={fila.lista_operativa} />
                                            </span>
                                        )}
                                        <div className="flex flex-wrap gap-2 mt-2">
                                            {fila.es_inactivo && (
                                                <span className="gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--neutro inline-flex text-xs font-semibold">
                                                    Inactivo
                                                </span>
                                            )}
                                            {fila.propone_inactivo && (
                                                <span className="gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--aviso inline-flex text-xs font-semibold">
                                                    Riesgo inactividad
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="py-3.5 px-4 theme-text-main text-right tabular-nums align-top whitespace-nowrap">{dinero(fila.ventas)}</td>
                                    <td className="py-3.5 px-4 theme-text-main text-right tabular-nums align-top whitespace-nowrap">{dinero(fila.devoluciones_aplicadas)}</td>
                                    <td className="py-3.5 px-4 theme-text-main text-right tabular-nums align-top whitespace-nowrap">{dinero(fila.venta_neta)}</td>
                                    <td className="py-3.5 px-4 align-top">
                                        <BadgeListaDescuento nombre={fila.lista_vigente} vacio={fila.lista_vigente ? undefined : 'sin_lista'} />
                                    </td>
                                    <td className="py-3.5 px-4 align-top">
                                        <BadgeListaDescuento
                                            nombre={fila.lista_calculada}
                                            vacio={fila.lista_calculada ? 'sin_lista' : 'no_participa'}
                                        />
                                    </td>
                                    <td className="py-3.5 px-4 align-top">
                                        <EstadoResultado codigo={fila.resultado} />
                                    </td>
                                    <td className="py-3.5 pl-4 pr-4 text-right align-top sm:pr-5">
                                        <EscalonamientoMenuAcciones
                                            items={[
                                                {
                                                    key: 'detalle',
                                                    label: 'Ver detalle',
                                                    icon: Eye,
                                                    href: route('escalonamiento.clientes.ficha', fila.cliente_id),
                                                },
                                                {
                                                    key: 'bitacora',
                                                    label: 'Bitácora',
                                                    icon: History,
                                                    href: `${route('escalonamiento.clientes.ficha', fila.cliente_id)}#movimientos`,
                                                },
                                            ]}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <GeliaPaginacion
                    embedded
                    paginator={paginacion}
                    onIrAPagina={(page) => navegar({ page })}
                />
            </div>
        </div>
    );
}

function EstadoResultado({ codigo }) {
    const etiqueta = ETIQUETAS_RESULTADO[codigo] || codigo || '—';
    const tono = GELIA_ESTADO_VIVO_TONO[TONO_RESULTADO[codigo] || 'neutro'];
    const Icono = codigo === 'ascenso' ? ArrowUp : codigo === 'descenso' ? ArrowDown : codigo === 'sin_cambio' ? Minus : null;

    return (
        <span className={`gelia-estado-vivo gelia-estado-vivo--compacto inline-flex text-xs font-semibold ${tono}`}>
            {Icono ? <Icono className="w-3.5 h-3.5 shrink-0" aria-hidden /> : null}
            {etiqueta}
        </span>
    );
}
