import React, { useState, useRef } from 'react';
import axios from 'axios';
import {
    Calculator,
    Search,
    TrendingUp,
    AlertTriangle,
    CheckCircle2,
    Trash2,
    Users,
    Eraser,
} from 'lucide-react';
import GeliaTituloCard from '@/Components/GeliaTituloCard';
import { THEME_INPUT, geliaCardClass } from '@/utils/geliaTheme';
import {
    evaluarEscalonamiento,
    desgloseSimulacionPorLista,
    fmtMontoEscalonamiento,
} from '@/utils/escalonamiento';

const FilaMontoEscalonamiento = ({ etiqueta, valor, destacado = false, valorClassName = '', compacto = false }) => (
    <div className={`flex justify-between items-baseline gap-3 ${destacado ? 'py-2.5 px-3 rounded-xl bg-black/5 dark:bg-white/5' : compacto ? 'py-1' : 'py-1.5'}`}>
        <span className={`${destacado ? 'text-sm font-bold' : 'text-sm font-semibold'} gelia-desglose-fila-label theme-text-muted leading-snug`}>{etiqueta}</span>
        <span className={`text-sm font-black tabular-nums shrink-0 ${destacado ? 'text-base text-[var(--color-primario)]' : 'theme-text-main'} ${valorClassName}`}>
            {fmtMontoEscalonamiento(valor)}
        </span>
    </div>
);

const TarjetaDesgloseNivel = ({ item, esProyectada }) => {
    const califica = item.califica_neto;
    const calificaBrutoSolo = item.califica_bruto && !item.califica_neto;

    return (
        <div className={`rounded-2xl border-2 p-4 md:p-5 flex flex-col gap-3 h-full min-w-0 ${
            esProyectada
                ? 'border-[var(--color-primario)]/50 bg-[var(--color-primario)]/5 ring-2 ring-[var(--color-primario)]/20'
                : califica
                    ? 'border-emerald-500/40 bg-emerald-500/5'
                    : calificaBrutoSolo
                        ? 'border-amber-500/40 bg-amber-500/5'
                        : 'theme-element border theme-border'
        }`}>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-sm font-black theme-text-main uppercase tracking-wide m-0 leading-snug truncate">
                        {item.nombre.replace('MAYOREO ', '')}
                    </p>
                    <p className="text-xs font-bold theme-text-main mt-1 m-0">
                        Umbral {fmtMontoEscalonamiento(item.monto_requerido)}
                    </p>
                </div>
                <span className="text-xs font-black tabular-nums px-2.5 py-1 rounded-lg bg-black/10 dark:bg-white/10 theme-text-main shrink-0">
                    {item.porcentaje_descuento.toFixed(2)}%
                </span>
            </div>

            {esProyectada && (
                <span className="inline-block self-start text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md bg-[var(--color-primario)] text-white">
                    Proyectado
                </span>
            )}

            <div className="space-y-0.5 pt-1 border-t theme-border flex-1">
                <FilaMontoEscalonamiento etiqueta="Cotización neta" valor={item.monto_cotizado_neto} compacto />
                <FilaMontoEscalonamiento
                    etiqueta="Total neto acum."
                    valor={item.total_proyectado_neto}
                    compacto
                    valorClassName={califica ? 'text-emerald-600 dark:text-emerald-400 font-black' : ''}
                />
            </div>

            <div className={`flex items-center gap-2 p-2.5 rounded-xl text-xs font-bold mt-auto ${
                califica
                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                    : calificaBrutoSolo
                        ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400'
                        : 'bg-black/5 dark:bg-white/5 theme-text-muted'
            }`}>
                {califica ? (
                    <><CheckCircle2 className="w-4 h-4 shrink-0" /> Califica neto</>
                ) : calificaBrutoSolo ? (
                    <><AlertTriangle className="w-4 h-4 shrink-0" /> Bruto sí, neto no</>
                ) : (
                    <><AlertTriangle className="w-4 h-4 shrink-0 opacity-70" /> No califica</>
                )}
            </div>

            {!item.califica_neto && item.faltante_neto > 0 && (
                <p className="text-xs font-bold text-amber-800 dark:text-amber-300 leading-snug m-0">
                    Faltan {fmtMontoEscalonamiento(item.faltante_neto)} netos
                    {item.monto_bruto_adicional > 0 && (
                        <> (~{fmtMontoEscalonamiento(item.monto_bruto_adicional)} bruto)</>
                    )}
                </p>
            )}
        </div>
    );
};

const PanelSeccion = ({ titulo, subtitulo, children, className = '' }) => (
    <div className={`${geliaCardClass('p-4 md:p-5')} ${className}`}>
        <div className="mb-4">
            <p className="text-xs font-black uppercase tracking-widest theme-text-main m-0">{titulo}</p>
            {subtitulo && (
                <p className="text-xs font-medium theme-text-muted mt-1.5 m-0 leading-snug">{subtitulo}</p>
            )}
        </div>
        {children}
    </div>
);

export default function EjercicioEscalonamientoPanel({ listas, variant = 'page' }) {
    const isModal = variant === 'modal';
    const [clientesEnVista, setClientesEnVista] = useState([]);
    const [terminoBusqueda, setTerminoBusqueda] = useState('');
    const [resultadosBusqueda, setResultadosBusqueda] = useState([]);
    const [buscando, setBuscando] = useState(false);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);

    const temporizadorBusqueda = useRef(null);
    const abortBusqueda = useRef(null);

    const manejarBusqueda = (valor) => {
        setTerminoBusqueda(valor);
        if (temporizadorBusqueda.current) clearTimeout(temporizadorBusqueda.current);

        if (valor.trim() === '') {
            abortBusqueda.current?.abort();
            setResultadosBusqueda([]);
            setMostrarDropdown(false);
            return;
        }

        temporizadorBusqueda.current = setTimeout(() => buscarClientes(valor), 400);
    };

    const buscarClientes = async (term) => {
        const limpio = term.trim();
        if (limpio.length < 2) return;

        abortBusqueda.current?.abort();
        const controller = new AbortController();
        abortBusqueda.current = controller;

        setBuscando(true);
        setMostrarDropdown(true);

        try {
            const response = await axios.get('/api/clientes', {
                params: { q: limpio },
                signal: controller.signal,
            });
            setResultadosBusqueda(response.data);
        } catch (err) {
            if (!axios.isCancel(err) && err?.code !== 'ERR_CANCELED') {
                setResultadosBusqueda([]);
            }
        } finally {
            if (!controller.signal.aborted) setBuscando(false);
        }
    };

    const agregarCliente = (cliente) => {
        if (clientesEnVista.some(c => c.id === cliente.id)) {
            setMostrarDropdown(false);
            setTerminoBusqueda('');
            return;
        }

        setClientesEnVista(prev => [...prev, { ...cliente, monto_cotizado_input: '' }]);
        setMostrarDropdown(false);
        setTerminoBusqueda('');
    };

    const quitarCliente = (id) => {
        setClientesEnVista(prev => prev.filter(c => c.id !== id));
    };

    const limpiarTodo = () => {
        setClientesEnVista([]);
        setTerminoBusqueda('');
        setResultadosBusqueda([]);
        setMostrarDropdown(false);
    };

    const actualizarMontoCliente = (id, valor) => {
        setClientesEnVista(prev => prev.map(c =>
            c.id === id ? { ...c, monto_cotizado_input: valor } : c
        ));
    };

    const buscadorAside = (
        <div className={`${isModal ? 'w-full' : 'w-full md:w-80'} relative`}>
            <div className="theme-field-with-icon relative">
                <Search className="theme-field-icon w-5 h-5" aria-hidden />
                <input
                    type="text"
                    value={terminoBusqueda}
                    onChange={e => manejarBusqueda(e.target.value)}
                    onFocus={() => { if (terminoBusqueda) setMostrarDropdown(true); }}
                    placeholder="Buscar cliente..."
                    className={`${THEME_INPUT} w-full py-3.5 text-sm`}
                />
            </div>
            {mostrarDropdown && (
                <div className="absolute top-[100%] mt-2 left-0 right-0 theme-surface border theme-border rounded-2xl shadow-2xl z-50 max-h-60 overflow-y-auto custom-scrollbar p-2">
                    {buscando ? (
                        <div className="p-4 text-center text-xs font-bold theme-text-muted animate-pulse italic">Consultando...</div>
                    ) : resultadosBusqueda.length > 0 ? (
                        resultadosBusqueda.map(c => (
                            <div
                                key={c.id}
                                onClick={() => agregarCliente(c)}
                                className="p-3 rounded-xl hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer flex justify-between items-center mb-1"
                            >
                                <p className="text-xs font-black uppercase theme-text-main truncate">{c.numero_cliente} - {c.nombre}</p>
                                {c.es_heredado && <span className="text-[8px] font-bold bg-purple-500/20 text-purple-500 px-2 py-0.5 rounded uppercase shrink-0 ml-2">Heredado</span>}
                            </div>
                        ))
                    ) : (
                        <div className="p-4 text-center text-xs font-bold theme-text-muted italic">Sin resultados</div>
                    )}
                </div>
            )}
        </div>
    );

    const toolbar = (
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full md:w-auto min-w-0">
            {clientesEnVista.length > 0 && (
                <button
                    type="button"
                    onClick={limpiarTodo}
                    className="inline-flex items-center justify-center gap-2 px-4 py-3.5 text-xs font-black uppercase tracking-wide rounded-xl border theme-border theme-element theme-text-main hover:border-red-500/40 hover:bg-red-500/10 hover:text-red-600 dark:hover:text-red-400 transition-all shrink-0 whitespace-nowrap"
                    title="Quitar todos los clientes de la vista"
                >
                    <Eraser className="w-4 h-4 shrink-0" />
                    Limpiar todo
                </button>
            )}
            {buscadorAside}
        </div>
    );

    const gridPrincipalClass = isModal ? 'grid grid-cols-1 gap-4 items-stretch' : 'grid grid-cols-1 xl:grid-cols-12 gap-4 items-stretch';
    const gridDesgloseClass = isModal ? 'grid grid-cols-1 gap-3 items-stretch' : 'grid grid-cols-1 sm:grid-cols-3 gap-3 items-stretch';
    const colResumenClass = isModal ? 'min-w-0' : 'xl:col-span-3 min-w-0';
    const colNivelesClass = isModal ? 'min-w-0' : 'xl:col-span-9 min-w-0';

    const contenido = clientesEnVista.length === 0 ? (
        <div className={`${geliaCardClass()} flex flex-col items-center justify-center p-12 md:p-20 border-dashed opacity-70`}>
            <Calculator className="w-14 h-14 theme-text-muted mb-4 opacity-50" style={{ color: 'var(--color-primario)' }} />
            <h3 className="text-lg font-black theme-text-main uppercase tracking-tight mb-2 m-0">Sin clientes en revisión</h3>
            <p className="text-xs font-medium theme-text-muted text-center max-w-md m-0">
                Usa el buscador {isModal ? 'superior' : 'del encabezado'} para agregar clientes y calcular escalonamiento.
            </p>
        </div>
    ) : (
        <div className="space-y-6">
            {clientesEnVista.map(cliente => {
                const analisis = evaluarEscalonamiento(cliente, cliente.monto_cotizado_input, listas);
                const desgloseNiveles = desgloseSimulacionPorLista(cliente, cliente.monto_cotizado_input, listas);
                const listaActualObj = listas.find(l => l.id == cliente.lista_actual_id || l.nombre === cliente.lista_actual);
                const listaProyectadaId = analisis.listaAnticipada?.id;
                const tieneCotizacion = cliente.monto_cotizado_input && parseFloat(cliente.monto_cotizado_input) > 0;
                const mostrarAlertas = tieneCotizacion && (
                    (analisis.brutoCalificaNetoNo && analisis.listaAnticipada)
                    || (analisis.mantieneListaAnticipada && analisis.listaAnticipada && !analisis.listaAnticipada.nombre.toUpperCase().includes('PUBLICO'))
                    || (analisis.listaSiguienteNeto && analisis.faltanteNetoSiguiente > 0 && !analisis.brutoCalificaNetoNo && analisis.listaSiguienteNeto.id !== analisis.listaAnticipada?.id)
                );

                return (
                    <article key={cliente.id} className={`${geliaCardClass()} animate-fade-in`}>
                        <div className="p-4 md:p-5 border-b theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                            <div className="flex flex-col lg:flex-row lg:items-start gap-4 lg:gap-5">
                                <div className="flex-1 min-w-0">
                                    <h3 className="text-lg md:text-xl font-black theme-text-main italic leading-tight m-0">
                                        <span className="tabular-nums not-italic">{cliente.numero_cliente}</span>
                                        <span className="theme-text-muted font-bold not-italic mx-2">·</span>
                                        <span className="break-words">{cliente.nombre}</span>
                                    </h3>
                                    <div className="flex flex-wrap gap-2 mt-3">
                                        <span className="text-xs font-bold bg-[var(--color-primario)] text-white px-3 py-1.5 rounded-lg uppercase">
                                            {listaActualObj?.nombre || 'Público General'}
                                        </span>
                                        {cliente.es_heredado && (
                                            <span className="text-xs font-black bg-purple-500 text-white px-3 py-1.5 rounded-lg uppercase">Heredado</span>
                                        )}
                                        {tieneCotizacion && analisis.listaAnticipada && (
                                            <span className="text-xs font-black bg-emerald-500/15 text-emerald-800 dark:text-emerald-300 border border-emerald-500/40 px-3 py-1.5 rounded-lg uppercase flex items-center gap-1.5">
                                                <TrendingUp className="w-3.5 h-3.5" />
                                                {analisis.listaAnticipada.nombre.replace('MAYOREO ', '')} · {analisis.porcentajeDescuento.toFixed(2)}%
                                            </span>
                                        )}
                                    </div>
                                </div>

                                <div className="flex flex-row lg:flex-col items-stretch gap-3 shrink-0 w-full lg:w-auto">
                                    <button
                                        type="button"
                                        onClick={(e) => {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            quitarCliente(cliente.id);
                                        }}
                                        className="inline-flex items-center justify-center gap-2 px-4 py-3 text-xs font-black uppercase tracking-wide rounded-xl border border-red-500/35 bg-red-500/10 text-red-700 dark:text-red-400 hover:bg-red-500/20 hover:border-red-500/50 transition-all shrink-0 self-start lg:self-end"
                                        aria-label={`Quitar cliente ${cliente.numero_cliente}`}
                                    >
                                        <Trash2 className="w-4 h-4 shrink-0" />
                                        <span>Quitar</span>
                                    </button>

                                    <div className="flex-1 lg:w-72 lg:flex-none min-w-0">
                                        <label className="text-xs font-black uppercase theme-text-main tracking-widest ml-1 block mb-1.5">Simular cotización_</label>
                                        <div className="theme-field-with-icon relative">
                                            <TrendingUp className="theme-field-icon w-4 h-4" aria-hidden />
                                            <input
                                                type="number"
                                                step="0.01"
                                                value={cliente.monto_cotizado_input}
                                                onChange={e => actualizarMontoCliente(cliente.id, e.target.value)}
                                                placeholder="Monto bruto..."
                                                className={`${THEME_INPUT} w-full py-3.5 text-base font-bold`}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {!tieneCotizacion ? (
                            <div className="flex items-center justify-center gap-3 p-10 theme-text-main">
                                <Calculator className="w-9 h-9 opacity-50" />
                                <p className="text-base font-bold m-0">Ingresa un monto para ver el desglose {isModal ? '' : 'horizontal'}.</p>
                            </div>
                        ) : (
                            <div className="p-4 md:p-6 space-y-4">
                                <div className={gridPrincipalClass}>
                                    <div className={colResumenClass}>
                                        <PanelSeccion titulo="Desglose de cálculo">
                                            <div className="space-y-0.5">
                                                <FilaMontoEscalonamiento etiqueta="Historial" valor={analisis.montoHistorico} compacto />
                                                <FilaMontoEscalonamiento etiqueta="Cotización bruta" valor={analisis.montoCotizado} destacado />
                                                <FilaMontoEscalonamiento etiqueta="Total bruto" valor={analisis.totalProyectadoBruto} compacto />
                                                {analisis.listaAnticipada && (
                                                    <>
                                                        <div className="my-2 border-t border-dashed theme-border" />
                                                        <FilaMontoEscalonamiento
                                                            etiqueta={`Desc. ${analisis.porcentajeDescuento.toFixed(2)}%`}
                                                            valor={analisis.montoCotizado - analisis.montoFinalTentativo}
                                                            compacto
                                                        />
                                                        <FilaMontoEscalonamiento etiqueta="Cotización neta" valor={analisis.montoFinalTentativo} compacto />
                                                        <div className={`mt-3 py-3 px-3.5 rounded-xl border-2 ${
                                                            analisis.mantieneListaAnticipada
                                                                ? 'border-emerald-500/40 bg-emerald-500/10'
                                                                : 'border-amber-500/40 bg-amber-500/10'
                                                        }`}>
                                                            <p className="text-xs font-black uppercase gelia-desglose-fila-label theme-text-muted m-0 mb-1">Total acumulado neto</p>
                                                            <p className={`text-2xl font-black tabular-nums m-0 ${
                                                                analisis.mantieneListaAnticipada
                                                                    ? 'text-emerald-700 dark:text-emerald-300'
                                                                    : 'text-amber-700 dark:text-amber-300'
                                                            }`}>
                                                                {fmtMontoEscalonamiento(analisis.totalProyectadoNeto)}
                                                            </p>
                                                        </div>
                                                    </>
                                                )}
                                            </div>
                                        </PanelSeccion>
                                    </div>

                                    {desgloseNiveles.length > 0 && (
                                        <div className={colNivelesClass}>
                                            <PanelSeccion
                                                titulo="Desglose Plata · Oro · Diamante"
                                                subtitulo="Simulación con el % de descuento de cada lista aplicado al monto cotizado"
                                            >
                                                <div className={gridDesgloseClass}>
                                                    {desgloseNiveles.map(item => (
                                                        <TarjetaDesgloseNivel
                                                            key={item.id}
                                                            item={item}
                                                            esProyectada={item.id === listaProyectadaId}
                                                        />
                                                    ))}
                                                </div>
                                            </PanelSeccion>
                                        </div>
                                    )}
                                </div>

                                {mostrarAlertas && (
                                    <div className={`${geliaCardClass('p-4 md:p-5')} space-y-3`}>
                                        <p className="text-xs font-black uppercase tracking-widest theme-text-main m-0 flex items-center gap-2">
                                            <Users className="w-4 h-4" />
                                            Estado del escalonamiento
                                        </p>
                                        <div className={`grid gap-3 ${isModal ? 'grid-cols-1' : 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3'}`}>
                                            {analisis.brutoCalificaNetoNo && analisis.listaAnticipada && (
                                                <div className="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/35 flex gap-2.5 items-start">
                                                    <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                                    <p className="text-sm font-bold text-amber-900 dark:text-amber-200 leading-snug m-0">
                                                        Faltan {fmtMontoEscalonamiento(analisis.faltanteNetoMantener)} netos para {analisis.listaAnticipada.nombre.replace('MAYOREO ', '')}
                                                        {analisis.montoBrutoParaMantener > 0 && (
                                                            <> (~{fmtMontoEscalonamiento(analisis.montoBrutoParaMantener)} bruto)</>
                                                        )}
                                                    </p>
                                                </div>
                                            )}
                                            {analisis.mantieneListaAnticipada && analisis.listaAnticipada && !analisis.listaAnticipada.nombre.toUpperCase().includes('PUBLICO') && (
                                                <div className="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/35 flex gap-2.5 items-center">
                                                    <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                                                    <p className="text-sm font-bold text-emerald-800 dark:text-emerald-300 m-0">
                                                        Pago final mantiene {analisis.listaAnticipada.nombre.replace('MAYOREO ', '')}
                                                    </p>
                                                </div>
                                            )}
                                            {analisis.listaSiguienteNeto && analisis.faltanteNetoSiguiente > 0 && !analisis.brutoCalificaNetoNo && analisis.listaSiguienteNeto.id !== analisis.listaAnticipada?.id && (
                                                <div className="p-3.5 rounded-xl bg-blue-500/10 border border-blue-500/35 flex gap-2.5 items-start">
                                                    <TrendingUp className="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                                                    <p className="text-sm font-bold text-blue-900 dark:text-blue-200 leading-snug m-0">
                                                        Siguiente: {analisis.listaSiguienteNeto.nombre.replace('MAYOREO ', '')}. Faltan {fmtMontoEscalonamiento(analisis.faltanteNetoSiguiente)} netos (~{fmtMontoEscalonamiento(analisis.montoBrutoParaSiguiente)} bruto)
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </article>
                );
            })}
        </div>
    );

    if (isModal) {
        return (
            <div className="space-y-4">
                {toolbar}
                {contenido}
            </div>
        );
    }

    return (
        <>
            <GeliaTituloCard
                eyebrow="Herramientas"
                title="Ejercicio"
                titleHighlight="Escalonamiento"
                description="Simula y compara proyecciones de descuento por cliente · Plata · Oro · Diamante"
                icon={Calculator}
                aside={toolbar}
            />
            {contenido}
        </>
    );
}
