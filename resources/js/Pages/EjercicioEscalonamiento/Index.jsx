import React, { useState, useRef, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { 
    Calculator, 
    Search, 
    X, 
    TrendingUp, 
    AlertTriangle, 
    CheckCircle2, 
    Circle,
    Store,
    Trash2
} from 'lucide-react';
import { THEME_INPUT, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '@/utils/geliaTheme';

const obtenerPorcentajeEscalonamiento = (lista, porcentajes) => {
    if (!lista) return 0;
    
    if (lista.porcentaje_escalonamiento_pct != null && lista.porcentaje_escalonamiento_pct !== '') {
        return parseFloat(lista.porcentaje_escalonamiento_pct) || 0;
    }

    const pct = porcentajes.find(p => p.id === lista.catalogo_porcentaje_escalonamiento_lista_id);
    if (!pct) return 0;
    if (pct.activo === false || pct.activo === 0) return 0;
    return parseFloat(pct.porcentaje_descuento || 0);
};

const calcularMontoFinalTentativo = (montoCotizado, porcentaje) => {
    return Math.round(montoCotizado * (1 - porcentaje / 100) * 100) / 100;
};

const fmtMonto = (valor) =>
    `$${Number(valor).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const FilaMontoEscalonamiento = ({ etiqueta, valor, destacado = false, valorClassName = '' }) => (
    <div className={`flex justify-between items-baseline gap-4 ${destacado ? 'py-2.5 px-3 rounded-xl bg-black/5 dark:bg-white/5' : 'py-1'}`}>
        <span className={`${destacado ? 'text-sm font-bold' : 'text-sm font-medium'} theme-text-muted leading-snug`}>{etiqueta}</span>
        <span className={`text-sm font-bold tabular-nums shrink-0 ${destacado ? 'text-base font-black text-[var(--color-primario)]' : 'theme-text-main'} ${valorClassName}`}>
            {fmtMonto(valor)}
        </span>
    </div>
);

const calcularMontoBrutoNecesario = (faltanteNeto, porcentaje) => {
    if (faltanteNeto <= 0) return 0;
    const mult = 1 - porcentaje / 100;
    return mult <= 0 ? faltanteNeto : Math.round((faltanteNeto / mult) * 100) / 100;
};

const evaluarEscalonamiento = (cliente, montoCotizadoInput, catalogoListas, porcentajes) => {
    const montoHistorico = parseFloat(cliente?.monto_venta_actual?.toString().replace(/[^0-9.-]+/g, '') || 0);
    const montoCotizado = parseFloat(montoCotizadoInput || 0);

    const listasValidas = [...catalogoListas]
        .filter(l => !l.nombre.toUpperCase().includes('COLABORADOR') && !l.nombre.toUpperCase().includes('PLATAFORMAS'))
        .sort((a, b) => parseFloat(b.monto_requerido) - parseFloat(a.monto_requerido));

    const totalProyectadoBruto = montoHistorico + montoCotizado;

    const listaCalificadaBruto = listasValidas.find(l => totalProyectadoBruto >= parseFloat(l.monto_requerido)) || null;

    const listaActualObj = catalogoListas.find(l => l.id == cliente.lista_actual_id || l.nombre === cliente.lista_actual);
    const requisitoListaActual = listaActualObj ? parseFloat(listaActualObj.monto_requerido || 0) : 0;
    const esAscenso = listaCalificadaBruto && parseFloat(listaCalificadaBruto.monto_requerido) > requisitoListaActual;

    const listaAnticipada = listaCalificadaBruto;

    const porcentajeDescuento = obtenerPorcentajeEscalonamiento(listaAnticipada, porcentajes);
    const montoFinalTentativo = calcularMontoFinalTentativo(montoCotizado, porcentajeDescuento);
    const totalProyectadoNeto = montoHistorico + montoFinalTentativo;

    const listaCalificadaNeto = listasValidas.find(l => totalProyectadoNeto >= parseFloat(l.monto_requerido)) || null;

    const listasAscendentes = [...listasValidas].sort((a, b) => parseFloat(a.monto_requerido) - parseFloat(b.monto_requerido));
    const listaSiguienteNeto = listasAscendentes.find(l => parseFloat(l.monto_requerido) > totalProyectadoNeto) || null;
    const faltanteNetoSiguiente = listaSiguienteNeto
        ? Math.max(0, parseFloat(listaSiguienteNeto.monto_requerido) - totalProyectadoNeto)
        : 0;
    const porcentajeSiguiente = listaSiguienteNeto
        ? obtenerPorcentajeEscalonamiento(listaSiguienteNeto, porcentajes)
        : porcentajeDescuento;
    const montoBrutoParaSiguiente = calcularMontoBrutoNecesario(faltanteNetoSiguiente, porcentajeSiguiente);

    let mantieneListaAnticipada = true;
    let faltanteNetoMantener = 0;
    let montoBrutoParaMantener = 0;
    let brutoCalificaNetoNo = false;

    if (listaAnticipada) {
        const umbral = parseFloat(listaAnticipada.monto_requerido);
        mantieneListaAnticipada = totalProyectadoNeto >= umbral;
        faltanteNetoMantener = Math.max(0, umbral - totalProyectadoNeto);
        montoBrutoParaMantener = calcularMontoBrutoNecesario(faltanteNetoMantener, porcentajeDescuento);
        brutoCalificaNetoNo = totalProyectadoBruto >= umbral && !mantieneListaAnticipada;
    }

    const desgloseListas = listasAscendentes.map(l => ({
        id: l.id,
        nombre: l.nombre,
        monto_requerido: parseFloat(l.monto_requerido),
        cubre: totalProyectadoNeto >= parseFloat(l.monto_requerido),
    }));

    return {
        montoHistorico,
        montoCotizado,
        porcentajeDescuento,
        porcentajeSiguiente,
        montoFinalTentativo,
        totalProyectadoBruto,
        totalProyectadoNeto,
        listaCalificadaBruto,
        listaCalificadaNeto,
        listaAnticipada,
        listaSiguienteNeto,
        faltanteNetoSiguiente,
        montoBrutoParaSiguiente,
        mantieneListaAnticipada,
        faltanteNetoMantener,
        montoBrutoParaMantener,
        brutoCalificaNetoNo,
        esAscenso,
        desgloseListas,
    };
};

export default function EjercicioEscalonamiento({ listas, porcentajes }) {
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

        temporizadorBusqueda.current = setTimeout(() => {
            buscarClientes(valor);
        }, 400);
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
            if (!controller.signal.aborted) {
                setBuscando(false);
            }
        }
    };

    const agregarCliente = (cliente) => {
        if (clientesEnVista.some(c => c.id === cliente.id)) {
            // Ya está en la vista
            setMostrarDropdown(false);
            setTerminoBusqueda('');
            return;
        }

        setClientesEnVista(prev => [...prev, {
            ...cliente,
            monto_cotizado_input: ''
        }]);
        setMostrarDropdown(false);
        setTerminoBusqueda('');
    };

    const quitarCliente = (id) => {
        setClientesEnVista(prev => prev.filter(c => c.id !== id));
    };

    const actualizarMontoCliente = (id, valor) => {
        setClientesEnVista(prev => prev.map(c => 
            c.id === id ? { ...c, monto_cotizado_input: valor } : c
        ));
    };

    return (
        <AppLayout title="Ejercicio de Escalonamiento">
            <Head title="Ejercicio de Escalonamiento" />

            <div className="max-w-screen-2xl mx-auto p-4 md:p-8 space-y-8 animate-fade-in">
                
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <Calculator className="w-8 h-8 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                            <h1 className="text-3xl font-black italic theme-text-main uppercase tracking-tighter m-0">
                                Ejercicio Escalonamiento_
                            </h1>
                        </div>
                        <p className="text-sm font-medium theme-text-muted">
                            Añade múltiples clientes para simular y comparar sus proyecciones de descuento y escalonamiento de lista.
                        </p>
                    </div>

                    <div className="w-full md:w-96 relative">
                        <div className="theme-field-with-icon relative">
                            <Search className="theme-field-icon w-5 h-5" aria-hidden />
                            <input 
                                type="text" 
                                value={terminoBusqueda}
                                onChange={e => manejarBusqueda(e.target.value)}
                                onFocus={() => { if (terminoBusqueda) setMostrarDropdown(true); }}
                                placeholder="Buscar cliente por folio o nombre..." 
                                className={`${THEME_INPUT} w-full py-4 text-sm`} 
                            />
                        </div>

                        {mostrarDropdown && (
                            <div className="absolute top-[100%] mt-2 left-0 right-0 theme-surface border theme-border rounded-2xl shadow-2xl z-50 max-h-60 overflow-y-auto custom-scrollbar p-2">
                                {buscando ? (
                                    <div className="p-6 text-center text-xs font-bold theme-text-muted animate-pulse italic">
                                        Consultando directorio...
                                    </div>
                                ) : resultadosBusqueda.length > 0 ? (
                                    resultadosBusqueda.map(c => (
                                        <div 
                                            key={c.id} 
                                            onClick={() => agregarCliente(c)} 
                                            className="p-4 rounded-xl hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer flex justify-between items-center group mb-1 border border-transparent"
                                        >
                                            <p className="text-xs font-black uppercase theme-text-main">{c.numero_cliente} - {c.nombre}</p>
                                            {c.es_heredado ? <span className="text-[8px] font-bold bg-purple-500/20 text-purple-500 px-2 py-0.5 rounded uppercase">Heredado</span> : null}
                                        </div>
                                    ))
                                ) : (
                                    <div className="p-6 text-center text-xs font-bold theme-text-muted italic">
                                        No se encontraron clientes.
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                {clientesEnVista.length === 0 ? (
                    <div className="flex flex-col items-center justify-center p-12 md:p-24 theme-surface border theme-border border-dashed rounded-3xl opacity-60">
                        <Calculator className="w-16 h-16 theme-text-muted mb-4 opacity-50" />
                        <h3 className="text-xl font-black theme-text-main uppercase tracking-tight mb-2">No hay clientes en revisión</h3>
                        <p className="text-sm theme-text-muted text-center max-w-md">Utiliza el buscador de arriba para agregar uno o más clientes y comenzar a calcular sus proyecciones.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 xl:grid-cols-2 2xl:grid-cols-3 gap-6 items-start">
                        {clientesEnVista.map(cliente => {
                            const analisis = evaluarEscalonamiento(cliente, cliente.monto_cotizado_input, listas, porcentajes);
                            const listaActualObj = listas.find(l => l.id == cliente.lista_actual_id || l.nombre === cliente.lista_actual);

                            return (
                                <div key={cliente.id} className="theme-surface border theme-border shadow-sm rounded-[2rem] flex flex-col relative overflow-hidden animate-fade-in group">
                                    <button 
                                        onClick={() => quitarCliente(cliente.id)}
                                        className="absolute top-4 right-4 p-2 theme-text-muted hover:text-red-500 hover:bg-red-500/10 rounded-xl transition-all z-10"
                                        title="Quitar cliente"
                                    >
                                        <Trash2 className="w-5 h-5" />
                                    </button>

                                    <div className="p-6 md:p-8 border-b theme-border bg-black/[0.01] dark:bg-white/[0.01]">
                                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mb-1">{cliente.numero_cliente}</p>
                                        <h3 className="text-lg font-black theme-text-main italic pr-8 leading-tight mb-3">
                                            {cliente.nombre}
                                        </h3>
                                        <div className="flex gap-2">
                                            <span className="text-[10px] font-bold bg-[var(--color-primario)] text-white px-3 py-1 rounded-lg uppercase shadow-sm">
                                                Lista Actual: {listaActualObj?.nombre || 'Público General'}
                                            </span>
                                            {cliente.es_heredado && (
                                                <span className="text-[10px] font-black bg-purple-500 text-white px-3 py-1 rounded-lg uppercase tracking-widest shadow-sm">
                                                    HEREDADO
                                                </span>
                                            )}
                                        </div>

                                        <div className="mt-6 space-y-2">
                                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Simular Cotización_</label>
                                            <div className="theme-field-with-icon relative">
                                                <TrendingUp className="theme-field-icon w-5 h-5" aria-hidden />
                                                <input 
                                                    type="number" 
                                                    step="0.01"
                                                    value={cliente.monto_cotizado_input}
                                                    onChange={e => actualizarMontoCliente(cliente.id, e.target.value)}
                                                    placeholder="Ingresa monto bruto a cotizar..." 
                                                    className={`${THEME_INPUT} w-full py-4 text-sm font-bold`} 
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    {cliente.monto_cotizado_input && parseFloat(cliente.monto_cotizado_input) > 0 ? (
                                        <div className="flex-1 flex flex-col">
                                            {analisis.listaAnticipada && (
                                                <div className="px-6 py-4 bg-emerald-500/10 border-b border-emerald-500/20 flex items-center gap-2.5">
                                                    <TrendingUp className="w-5 h-5 text-emerald-500 shrink-0" />
                                                    <p className="text-sm font-black text-emerald-700 dark:text-emerald-400 m-0 leading-snug">
                                                        Nivel proyectado: {analisis.listaAnticipada.nombre}
                                                        <span className="font-bold opacity-80"> · {analisis.porcentajeDescuento.toFixed(2)}% desc.</span>
                                                    </p>
                                                </div>
                                            )}

                                            <div className="p-6 md:p-8 space-y-6 flex-1">
                                                {/* Resumen de montos */}
                                                <div>
                                                    <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-3 m-0">Desglose de cálculo</p>
                                                    <div className="space-y-1">
                                                        <FilaMontoEscalonamiento etiqueta="Historial de compra" valor={analisis.montoHistorico} />
                                                        <FilaMontoEscalonamiento etiqueta="Monto bruto (cotización)" valor={analisis.montoCotizado} destacado />
                                                        <FilaMontoEscalonamiento 
                                                            etiqueta="Total proyectado (bruto)" 
                                                            valor={analisis.totalProyectadoBruto} 
                                                            valorClassName={analisis.listaAnticipada && analisis.totalProyectadoBruto >= parseFloat(analisis.listaAnticipada.monto_requerido) ? 'text-emerald-600 dark:text-emerald-400 font-black text-base' : ''}
                                                        />
                                                    </div>
                                                </div>

                                                {/* Montos Tentativos */}
                                                {analisis.listaAnticipada && (
                                                    <div className="pt-4 border-t-2 border-dashed theme-border space-y-3">
                                                        <FilaMontoEscalonamiento 
                                                            etiqueta={`Descuento aplicado (${analisis.porcentajeDescuento.toFixed(2)}%)`} 
                                                            valor={analisis.montoCotizado - analisis.montoFinalTentativo} 
                                                        />
                                                        <FilaMontoEscalonamiento 
                                                            etiqueta="Monto tentativo final (neto)" 
                                                            valor={analisis.montoFinalTentativo} 
                                                            valorClassName="text-base font-black theme-text-main"
                                                        />
                                                        <div className={`flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 py-3.5 px-4 rounded-xl border-2 mt-4 ${
                                                            analisis.mantieneListaAnticipada ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-amber-500/40 bg-amber-500/10'
                                                        }`}>
                                                            <span className="text-sm font-black theme-text-main uppercase tracking-wide leading-snug">
                                                                Total pago final (Acumulado)
                                                            </span>
                                                            <span className={`text-2xl font-black tabular-nums ${
                                                                analisis.mantieneListaAnticipada ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'
                                                            }`}>
                                                                {fmtMonto(analisis.totalProyectadoNeto)}
                                                            </span>
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Alertas de Escalonamiento */}
                                                {((analisis.brutoCalificaNetoNo && analisis.listaAnticipada) || 
                                                  (analisis.mantieneListaAnticipada && analisis.listaAnticipada && !analisis.listaAnticipada.nombre.toUpperCase().includes('PUBLICO')) || 
                                                  (analisis.listaSiguienteNeto && analisis.faltanteNetoSiguiente > 0 && !analisis.brutoCalificaNetoNo && analisis.listaSiguienteNeto.id !== analisis.listaAnticipada?.id)) && (
                                                    <div className="pt-6 border-t theme-border space-y-3">
                                                        <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted m-0">Estado del escalonamiento</p>

                                                        {analisis.brutoCalificaNetoNo && analisis.listaAnticipada && (
                                                            <div className="p-4 rounded-xl bg-amber-500/10 border-2 border-amber-500/30 flex gap-3 items-start">
                                                                <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                                                                <p className="text-sm font-bold text-amber-800 dark:text-amber-300 leading-relaxed m-0">
                                                                    Faltan <span className="font-black tabular-nums">{fmtMonto(analisis.faltanteNetoMantener)}</span> netos para mantener{' '}
                                                                    <span className="font-black">{analisis.listaAnticipada.nombre}</span>
                                                                    {analisis.montoBrutoParaMantener > 0 && (
                                                                        <> (~<span className="font-black tabular-nums">{fmtMonto(analisis.montoBrutoParaMantener)}</span> bruto adicional)</>
                                                                    )}.
                                                                </p>
                                                            </div>
                                                        )}

                                                        {analisis.mantieneListaAnticipada && analisis.listaAnticipada && !analisis.listaAnticipada.nombre.toUpperCase().includes('PUBLICO') && (
                                                            <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex gap-2.5 items-center">
                                                                <CheckCircle2 className="w-5 h-5 text-emerald-500 shrink-0" />
                                                                <p className="text-sm font-bold text-emerald-700 dark:text-emerald-400 m-0">
                                                                    El pago final mantiene la lista {analisis.listaAnticipada.nombre}.
                                                                </p>
                                                            </div>
                                                        )}

                                                        {analisis.listaSiguienteNeto && analisis.faltanteNetoSiguiente > 0 && !analisis.brutoCalificaNetoNo && analisis.listaSiguienteNeto.id !== analisis.listaAnticipada?.id && (
                                                            <div className="p-3 rounded-xl bg-blue-500/10 border border-blue-500/30 flex gap-2.5 items-start">
                                                                <TrendingUp className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" />
                                                                <p className="text-sm font-bold text-blue-700 dark:text-blue-400 leading-relaxed m-0">
                                                                    Siguiente nivel: <span className="font-black">{analisis.listaSiguienteNeto.nombre}</span>. Faltan <span className="font-black tabular-nums">{fmtMonto(analisis.faltanteNetoSiguiente)}</span> netos 
                                                                    (~<span className="font-black tabular-nums">{fmtMonto(analisis.montoBrutoParaSiguiente)}</span> bruto al {analisis.porcentajeSiguiente.toFixed(2)}%).
                                                                </p>
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex-1 flex flex-col items-center justify-center p-8 text-center bg-black/5 dark:bg-white/5 opacity-80">
                                            <Calculator className="w-12 h-12 theme-text-muted mb-3 opacity-50" />
                                            <p className="text-sm font-bold theme-text-muted">Ingresa un monto para simular el desglose.</p>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
