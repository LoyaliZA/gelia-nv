import React, { useState, useEffect, useCallback } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { 
    CreditCard, Users, Clock, Coins, Search, FileText, ArrowRight, Upload, 
    Volume2, VolumeX, Phone, MessageSquare, Check, X, AlertCircle, BarChart3
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import { geliaCardClass, THEME_INPUT, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../utils/geliaTheme';
import { formatoMoneda } from '../../utils/formatoMoneda';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import NotificationBrowserService from '../../Services/NotificationBrowserService';

const ORDEN_OPCIONES = [
    { value: 'saldo_desc', label: 'Mayor saldo primero' },
    { value: 'saldo_asc', label: 'Menor saldo primero' },
    { value: 'fecha_credito_desc', label: 'Inicio de crédito reciente' },
    { value: 'nombre_asc', label: 'Nombre A-Z' },
    { value: 'nombre_desc', label: 'Nombre Z-A' },
    { value: 'numero_asc', label: 'No. cliente' },
];

export default function Index({ auth, clientes, alertas = [], cartera = {}, filtros = {} }) {
    const puedeEjecutarLlamadas = auth?.user?.permissions?.includes('cobranza.ejecutar_llamadas') || auth?.user?.roles?.includes('Super Admin');
    const puedeImportarReporte = auth?.user?.permissions?.includes('cobranza.importar_reporte') || auth?.user?.roles?.includes('Super Admin');
    const puedeVerBitacora = auth?.user?.permissions?.includes('cobranza.ver_bitacora') || auth?.user?.roles?.includes('Super Admin');
    const puedeRecibirAlertas = auth?.user?.permissions?.includes('cobranza.recibir_alertas') || auth?.user?.roles?.includes('Super Admin');

    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [filtroOrden, setFiltroOrden] = useState(filtros.orden || 'saldo_desc');
    const [cargandoLista, setCargandoLista] = useState(false);
    const [speaking, setSpeaking] = useState(false);
    const [activeTab, setActiveTab] = useState(puedeEjecutarLlamadas ? 'operational' : 'admin'); // 'operational' | 'admin' | 'clients'
    
    // Modal para actualizar resultado de llamada
    const [selectedAlerta, setSelectedAlerta] = useState(null);
    const formAlerta = useForm({
        estado: 'llamado',
        observaciones: ''
    });

    // Bitácora
    const [selectedBitacoraCliente, setSelectedBitacoraCliente] = useState(null);
    const [bitacoraData, setBitacoraData] = useState([]);
    const [cargandoBitacora, setCargandoBitacora] = useState(false);

    useEffect(() => {
        if (puedeRecibirAlertas && window.Echo) {
            window.Echo.channel('cobranza-alertas')
                .listen('.AlertaAumentoCreditoEvent', (e) => {
                    NotificationBrowserService.triggerFullAlert(
                        '¡Alerta de Cobranza!', 
                        `El cliente ${e.nombre} (No. ${e.numero_cliente}) aumentó su saldo de $${e.monto_anterior} a $${e.monto_nuevo} sin pagarlo previamente.`,
                        `Atención. El cliente ${e.nombre} aumentó su saldo de crédito sin pagar el saldo anterior.`,
                        { sonido: true, voz: true, escritorio: true }
                    );
                    router.reload({ only: ['clientes'] });
                });
        }
        return () => {
            if (puedeRecibirAlertas && window.Echo) {
                window.Echo.leave('cobranza-alertas');
            }
        };
    }, [puedeRecibirAlertas]);

    const abrirBitacora = (cliente) => {
        setSelectedBitacoraCliente(cliente);
        setCargandoBitacora(true);
        axios.get(route('auto-cobranza.bitacora', { clienteId: cliente.id }))
            .then(res => {
                setBitacoraData(res.data);
                setCargandoBitacora(false);
            })
            .catch(err => {
                console.error(err);
                setCargandoBitacora(false);
            });
    };

    const formImportar = useForm({
        archivo: null,
    });

    useEffect(() => {
        setBusqueda(filtros.q || '');
        setFiltroOrden(filtros.orden || 'saldo_desc');
    }, [filtros.q, filtros.orden]);

    const paramsFiltros = useCallback(() => ({
        q: busqueda.trim() || undefined,
        orden: filtroOrden || 'saldo_desc',
    }), [busqueda, filtroOrden]);

    const recargarClientes = useCallback((extra = {}) => {
        router.get(route('auto-cobranza.index'), { ...paramsFiltros(), ...extra }, {
            only: ['clientes', 'filtros'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            showProgress: false,
            onStart: () => setCargandoLista(true),
            onFinish: () => setCargandoLista(false),
        });
    }, [paramsFiltros]);

    useEffect(() => {
        if (activeTab !== 'clients') return undefined;

        const t = setTimeout(() => {
            const qActual = filtros.q || '';
            const ordenActual = filtros.orden || 'saldo_desc';
            if (busqueda !== qActual || filtroOrden !== ordenActual) {
                recargarClientes({ page: 1 });
            }
        }, 400);

        return () => clearTimeout(t);
    }, [busqueda, filtroOrden, filtros, recargarClientes, activeTab]);

    const handlePageChange = (page) => {
        recargarClientes({ page });
    };

    const montoAutorizado = (cliente) => {
        const monto = cliente.monto_credito_autorizado;
        return monto != null && Number(monto) > 0 ? Number(monto) : null;
    };

    const saldoConsolidado = (cliente) => {
        const saldo = cliente.factura_cobranza_activa?.monto;
        return saldo != null && Number(saldo) > 0 ? Number(saldo) : null;
    };

    // Text to Speech logic
    const reproducirVozReporte = () => {
        if ('speechSynthesis' in window) {
            if (speaking) {
                window.speechSynthesis.cancel();
                setSpeaking(false);
                return;
            }

            if (alertas.length === 0) {
                NotificationBrowserService.speakText("No hay alertas de cobranza pendientes de reportar por voz hoy.", true);
                return;
            }

            setSpeaking(true);
            let index = 0;

            const speakNext = () => {
                if (index >= alertas.length) {
                    setSpeaking(false);
                    return;
                }

                const alert = alertas[index];
                const msg = `Cliente ${alert.cliente.nombre} tiene un saldo vencido hace ${alert.dias_atraso} días.`;
                const utterance = new SpeechSynthesisUtterance(msg);
                utterance.lang = 'es-MX';
                utterance.rate = 0.9;
                utterance.onend = () => {
                    index++;
                    speakNext();
                };
                utterance.onerror = () => {
                    setSpeaking(false);
                };
                window.speechSynthesis.speak(utterance);
            };

            speakNext();
        } else {
            alert('Su navegador no soporta reproducción de voz.');
        }
    };

    // Limpieza al desmontar
    useEffect(() => {
        return () => {
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
        };
    }, []);

    const abrirModalLlamada = (alerta) => {
        setSelectedAlerta(alerta);
        formAlerta.setData({
            estado: alerta.estado,
            observaciones: alerta.observaciones || '',
        });
    };

    const guardarResultadoLlamada = (e) => {
        e.preventDefault();
        formAlerta.put(route('auto-cobranza.alertas.update', selectedAlerta.id), {
            onSuccess: () => {
                setSelectedAlerta(null);
                formAlerta.reset();
            },
            preserveScroll: true,
        });
    };

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            formImportar.setData('archivo', file);
        }
    };

    const submitImportar = (e) => {
        e.preventDefault();
        if (!formImportar.data.archivo) return;

        formImportar.post(route('auto-cobranza.importar'), {
            onSuccess: () => {
                formImportar.reset();
                router.reload({ only: ['clientes', 'filtros', 'cartera'] });
            },
            onError: () => {},
            preserveScroll: true,
        });
    };

    // Montos totales para KPIs
    const totalCarteraVencida = Object.values(cartera).reduce((acc, curr) => acc + curr.total, 0);
    const totalAlertasHoy = alertas.length;

    return (
        <AppLayout auth={auth}>
            <Head title="Módulo de Auto-Cobranza | GELIA" />
            <GeliaPageShell className="space-y-6">
                
                {/* Header */}
                <header className={geliaCardClass('p-6 md:p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-6 border-2 theme-border relative overflow-hidden')}>
                    <div className="absolute top-0 right-0 w-64 h-64 bg-[var(--color-primario)]/5 rounded-full blur-3xl pointer-events-none -mr-20 -mt-20"></div>
                    <div className="space-y-2 relative z-10">
                        <div className="flex items-center space-x-2">
                            <span className="h-1.5 w-8 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Sprint 1.1</p>
                        </div>
                        <h1 className="text-3xl md:text-4xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                            Auto-Cobranza
                        </h1>
                        <p className="text-xs font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">
                            Gestión e Inteligencia de Cobros
                        </p>
                    </div>
                </header>

                {/* KPI Panel */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className={geliaCardClass('p-6 border theme-border flex items-center justify-between')}>
                        <div className="space-y-1">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Total Cartera Vencida</p>
                            <h3 className="text-3xl font-black theme-text-main m-0">{formatoMoneda(totalCarteraVencida)}</h3>
                            <p className="text-[9px] font-bold text-red-500 uppercase tracking-wider">Monto total en mora</p>
                        </div>
                        <div className="p-3 rounded-2xl theme-element border theme-border shadow-sm">
                            <Coins className="w-6 h-6 text-red-500" />
                        </div>
                    </div>

                    <div className={geliaCardClass('p-6 border theme-border flex items-center justify-between')}>
                        <div className="space-y-1">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Alertas Operativas Hoy</p>
                            <h3 className="text-3xl font-black theme-text-main m-0">{totalAlertasHoy}</h3>
                            <p className="text-[9px] font-bold text-amber-500 uppercase tracking-wider">Pendientes de llamada (3, 6, 9, 12 días)</p>
                        </div>
                        <div className="p-3 rounded-2xl theme-element border theme-border shadow-sm">
                            <Clock className="w-6 h-6 text-amber-500" />
                        </div>
                    </div>

                    <div className={geliaCardClass('p-6 border theme-border flex items-center justify-between')}>
                        <div className="space-y-1">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Reporte de Voz</p>
                            <button 
                                onClick={reproducirVozReporte}
                                className={`px-4 py-2 mt-2 rounded-xl text-[9px] font-black uppercase tracking-widest text-white flex items-center gap-2 transition-transform active:scale-95 shadow-md ${speaking ? 'bg-red-500' : 'bg-emerald-600'}`}
                            >
                                {speaking ? <VolumeX className="w-4 h-4" /> : <Volume2 className="w-4 h-4" />}
                                {speaking ? 'Detener Voz' : 'Iniciar Reporte de Voz'}
                            </button>
                            <p className="text-[9px] font-bold theme-text-muted uppercase tracking-wider mt-1">Automatizado a las 11:00 AM</p>
                        </div>
                        <div className="p-3 rounded-2xl theme-element border theme-border shadow-sm">
                            <Volume2 className="w-6 h-6 text-emerald-500" />
                        </div>
                    </div>
                </div>

                {/* Tabs - Segmented Control */}
                <div className="flex flex-wrap items-center gap-1.5 p-1.5 bg-zinc-100 dark:bg-zinc-950 rounded-2xl w-fit shadow-inner border border-zinc-200 dark:border-zinc-800/50">
                    {puedeEjecutarLlamadas && (
                        <button
                            onClick={() => setActiveTab('operational')}
                            className={`px-6 py-3 text-[10px] font-black uppercase tracking-widest outline-none rounded-xl transition-all duration-300 ease-out ${
                                activeTab === 'operational' 
                                    ? 'bg-white dark:bg-zinc-800 text-[var(--color-primario)] shadow-sm' 
                                    : 'bg-transparent text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 hover:bg-black/5 dark:hover:bg-white/5'
                            }`}
                        >
                            Ejecución Operativa (Llamadas)
                        </button>
                    )}
                    <button
                        onClick={() => setActiveTab('admin')}
                        className={`px-6 py-3 text-[10px] font-black uppercase tracking-widest outline-none rounded-xl transition-all duration-300 ease-out ${
                            activeTab === 'admin' 
                                ? 'bg-white dark:bg-zinc-800 text-[var(--color-primario)] shadow-sm' 
                                : 'bg-transparent text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 hover:bg-black/5 dark:hover:bg-white/5'
                        }`}
                    >
                        Visualización Administrativa (Cartera Vencida)
                    </button>
                    <button
                        onClick={() => setActiveTab('clients')}
                        className={`px-6 py-3 text-[10px] font-black uppercase tracking-widest outline-none rounded-xl transition-all duration-300 ease-out ${
                            activeTab === 'clients' 
                                ? 'bg-white dark:bg-zinc-800 text-[var(--color-primario)] shadow-sm' 
                                : 'bg-transparent text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 hover:bg-black/5 dark:hover:bg-white/5'
                        }`}
                    >
                        Clientes con Crédito
                    </button>
                </div>

                {/* Operational Tab */}
                {activeTab === 'operational' && (
                    <div className="space-y-6">
                        <div className={geliaCardClass('p-6 border theme-border space-y-4')}>
                            <div className="flex items-center gap-2">
                                <Phone className="w-5 h-5 text-amber-500" />
                                <h2 className="text-lg font-black uppercase italic tracking-tighter theme-text-main m-0">Motor de Alertas y Cronograma Diario_</h2>
                            </div>
                            <p className="text-xs theme-text-muted">
                                A continuación se muestran las llamadas que deben ser procesadas hoy. Las alertas se habilitan al cumplir exactamente **3, 6, 9 y 12 días** de atraso.
                            </p>

                            <div className="overflow-x-auto">
                                <table className="w-full border-collapse">
                                    <thead>
                                        <tr className="border-b theme-border bg-black/5 dark:bg-white/5">
                                            <th className="px-6 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Cliente</th>
                                            <th className="px-6 py-4 text-center text-[9px] font-black uppercase tracking-widest theme-text-muted">Días Vencido</th>
                                            <th className="px-6 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Monto Vencido</th>
                                            <th className="px-6 py-4 text-center text-[9px] font-black uppercase tracking-widest theme-text-muted">Fecha Alerta</th>
                                            <th className="px-6 py-4 text-center text-[9px] font-black uppercase tracking-widest theme-text-muted">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {alertas.length === 0 ? (
                                            <tr>
                                                <td colSpan="5" className="px-6 py-12 text-center text-xs theme-text-muted uppercase tracking-wider font-bold">
                                                    No hay llamadas programadas para hoy.
                                                </td>
                                            </tr>
                                        ) : (
                                            alertas.map((alerta) => (
                                                <tr key={alerta.id} className="border-b theme-border last:border-0 hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                                    <td className="px-6 py-4">
                                                        <div className="text-sm font-bold theme-text-main">{alerta.cliente.nombre}</div>
                                                        <div className="text-[10px] theme-text-muted font-mono">{alerta.cliente.numero_cliente}</div>
                                                    </td>
                                                    <td className="px-6 py-4 text-center">
                                                        <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-black bg-red-500/10 text-red-500 border border-red-500/20">
                                                            {alerta.dias_atraso} días
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 text-right text-sm font-black theme-text-main">
                                                        {formatoMoneda(alerta.factura.monto)}
                                                    </td>
                                                    <td className="px-6 py-4 text-center text-xs font-semibold theme-text-muted">
                                                        {alerta.fecha_alerta}
                                                    </td>
                                                    <td className="px-6 py-4 text-center">
                                                        <button
                                                            onClick={() => abrirModalLlamada(alerta)}
                                                            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-[9px] font-black uppercase theme-element border theme-border hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all"
                                                        >
                                                            Registrar Bitácora <MessageSquare className="w-3 h-3" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {/* Administrative Tab */}
                {activeTab === 'admin' && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Rangos de Atraso */}
                        <div className={geliaCardClass('p-6 border theme-border space-y-6')}>
                            <div className="flex items-center gap-2">
                                <BarChart3 className="w-5 h-5 text-indigo-500" />
                                <h2 className="text-lg font-black uppercase italic tracking-tighter theme-text-main m-0">Agrupación por Antigüedad de Saldos_</h2>
                            </div>
                            <p className="text-xs theme-text-muted">
                                Distribución administrativa y financiera referencial de la cartera vencida en base a la antigüedad.
                            </p>

                            <div className="space-y-4">
                                {[
                                    { label: 'De 1 a 30 días', val: cartera.rango_1_30, color: 'bg-emerald-500' },
                                    { label: 'De 31 a 60 días', val: cartera.rango_31_60, color: 'bg-blue-500' },
                                    { label: 'De 61 a 90 días', val: cartera.rango_61_90, color: 'bg-amber-500' },
                                    { label: 'De 91 a 120 días', val: cartera.rango_91_120, color: 'bg-orange-500' },
                                    { label: 'Más de 120 días', val: cartera.rango_120_mas, color: 'bg-red-500' },
                                ].map((bucket, idx) => {
                                    const percentage = totalCarteraVencida > 0 ? (bucket.val.total / totalCarteraVencida) * 100 : 0;
                                    return (
                                        <div key={idx} className="space-y-2">
                                            <div className="flex justify-between text-xs font-bold">
                                                <span className="theme-text-main">{bucket.label}</span>
                                                <span className="theme-text-muted">
                                                    {bucket.val.cantidad} fact. ({formatoMoneda(bucket.val.total)})
                                                </span>
                                            </div>
                                            <div className="w-full bg-slate-200 dark:bg-slate-700 h-2.5 rounded-full overflow-hidden">
                                                <div 
                                                    className={`h-full ${bucket.color}`} 
                                                    style={{ width: `${percentage}%` }}
                                                ></div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Importar Carga de Reporte */}
                        {puedeImportarReporte && (
                            <div className={geliaCardClass('p-6 border theme-border space-y-6')}>
                            <div className="flex items-center gap-2">
                                <Upload className="w-5 h-5 text-emerald-500" />
                                <h2 className="text-lg font-black uppercase italic tracking-tighter theme-text-main m-0">Subir Reporte de Cobranza_</h2>
                            </div>
                            <p className="text-xs theme-text-muted">
                                Sube el archivo CSV de cobranza consolidada para actualizar automáticamente el inicio de crédito de los clientes y recalcular la antigüedad de los saldos.
                            </p>

                            <form onSubmit={submitImportar} className="space-y-4">
                                <div className="border-2 border-dashed theme-border rounded-3xl p-8 text-center flex flex-col items-center justify-center cursor-pointer hover:border-[var(--color-primario)]/50 transition-colors relative">
                                    <input
                                        type="file"
                                        accept=".csv,.txt"
                                        onChange={handleFileChange}
                                        className="absolute inset-0 opacity-0 cursor-pointer"
                                    />
                                    <Upload className="w-8 h-8 theme-text-muted mb-2" />
                                    {formImportar.data.archivo ? (
                                        <p className="text-xs font-bold text-emerald-500">
                                            Archivo seleccionado: {formImportar.data.archivo.name}
                                        </p>
                                    ) : (
                                        <div>
                                            <p className="text-xs font-black uppercase tracking-wider theme-text-main">
                                                Seleccionar o arrastrar archivo
                                            </p>
                                            <p className="text-[10px] theme-text-muted mt-1">
                                                CSV estructurado (Cliente, Consolidado, De 1 a 30 días, etc.)
                                            </p>
                                        </div>
                                    )}
                                </div>

                                {formImportar.errors.archivo && (
                                    <p className="text-xs text-red-500 font-bold">{formImportar.errors.archivo}</p>
                                )}

                                <button
                                    type="submit"
                                    disabled={formImportar.processing || !formImportar.data.archivo}
                                    className="w-full py-3.5 rounded-2xl text-white font-black uppercase tracking-widest text-[11px] shadow-md flex justify-center items-center gap-2 outline-none disabled:opacity-50"
                                    style={{ backgroundColor: 'var(--color-primario)' }}
                                >
                                    <Check className="w-4 h-4" /> {formImportar.processing ? 'Importando...' : 'Actualizar Cartera y Crédito'}
                                </button>
                            </form>
                        </div>
                        )}
                    </div>
                )}

                {/* Clients Tab */}
                {activeTab === 'clients' && (
                    <div className="space-y-6">
                        {/* Search and Filters */}
                        <div className={geliaCardClass('p-6 flex flex-col md:flex-row items-center justify-between gap-4 border theme-border')}>
                            <div className="relative w-full md:max-w-md">
                                <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                <input
                                    type="text"
                                    placeholder="Buscar cliente por nombre o número..."
                                    value={busqueda}
                                    onChange={(e) => setBusqueda(e.target.value)}
                                    className={`${THEME_INPUT} w-full pl-12 pr-4 py-3 rounded-2xl text-xs font-bold`}
                                />
                            </div>
                            <div className="flex items-center gap-3 w-full md:w-auto">
                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted whitespace-nowrap">
                                    Ordenar
                                </label>
                                <select
                                    value={filtroOrden}
                                    onChange={(e) => setFiltroOrden(e.target.value)}
                                    className={`${THEME_INPUT} px-4 py-3 rounded-2xl text-xs font-bold min-w-[200px]`}
                                >
                                    {ORDEN_OPCIONES.map((op) => (
                                        <option key={op.value} value={op.value}>{op.label}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        {cargandoLista && (
                            <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted text-center">
                                Actualizando lista...
                            </p>
                        )}

                        {/* Cards of Clients */}
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {(clientes?.data?.length ?? 0) === 0 ? (
                                <div className="col-span-full py-16 text-center text-xs theme-text-muted uppercase tracking-wider font-bold">
                                    No se encontraron clientes con crédito activo.
                                </div>
                            ) : (
                                clientes.data.map((cliente) => {
                                    const autorizado = montoAutorizado(cliente);
                                    const consolidado = saldoConsolidado(cliente);
                                    const hasAlerta = cliente.alerta_aumento_credito;
                                    return (
                                        <div key={cliente.id} className={geliaCardClass(`p-6 border flex flex-col hover:shadow-md transition-all duration-300 ${hasAlerta ? 'border-red-500/50 bg-red-500/5 dark:bg-red-500/10 hover:border-red-500' : 'theme-border hover:border-[var(--color-primario)]/50'}`)}>
                                            <div className="flex justify-between items-start mb-4">
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2 mb-1">
                                                        <span className="text-[10px] font-black uppercase tracking-widest text-[var(--color-primario)]">
                                                            No. {cliente.numero_cliente}
                                                        </span>
                                                        {hasAlerta && (
                                                            <span className="px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-widest bg-red-500 text-white animate-pulse">
                                                                Alerta
                                                            </span>
                                                        )}
                                                    </div>
                                                    <h3 className="text-sm font-bold theme-text-main leading-tight flex items-center gap-1.5">
                                                        {cliente.nombre}
                                                    </h3>
                                                    <p className="text-[10px] theme-text-muted mt-1.5">{cliente.rfc || 'Sin RFC'}</p>
                                                </div>
                                                {puedeVerBitacora && (
                                                    <button onClick={() => abrirBitacora(cliente)} className="p-1.5 rounded-xl theme-element border border-transparent hover:border-[var(--color-primario)]/30 text-zinc-400 hover:text-[var(--color-primario)] transition-colors" title="Ver Bitácora">
                                                        <FileText className="w-4 h-4" />
                                                    </button>
                                                )}
                                            </div>
                                            
                                            <div className="grid grid-cols-2 gap-4 mt-auto pt-4 border-t theme-border/60">
                                                <div>
                                                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Autorizado</p>
                                                    <p className="text-sm font-black theme-text-main">
                                                        {autorizado != null ? formatoMoneda(autorizado) : <span className="text-xs font-semibold theme-text-muted">N/A</span>}
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Consolidado</p>
                                                    <p className="text-sm font-black text-amber-600 dark:text-amber-400">
                                                        {consolidado != null ? formatoMoneda(consolidado) : <span className="text-xs font-semibold theme-text-muted">S/S</span>}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="flex items-center justify-between mt-4 pt-4 border-t theme-border/60">
                                                <div className="flex flex-col">
                                                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Plazo</span>
                                                    {cliente.dias_credito > 0 ? (
                                                        <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black bg-blue-500/10 text-blue-500 border border-blue-500/20 w-fit">
                                                            {cliente.dias_credito} días
                                                        </span>
                                                    ) : (
                                                        <span className="text-[10px] font-bold theme-text-muted">—</span>
                                                    )}
                                                </div>
                                                <div className="flex flex-col items-end">
                                                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Inicio de Crédito</span>
                                                    <span className="text-[11px] font-bold theme-text-main">
                                                        {cliente.fecha_inicio_credito || <span className="theme-text-muted">—</span>}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>

                        <div className="mt-4">
                            <GeliaPaginacion paginator={clientes} onIrAPagina={handlePageChange} />
                        </div>
                    </div>
                )}

            </GeliaPageShell>

            {/* Modal para Bitácora de Llamada */}
            {selectedAlerta && (
                <div className={`${THEME_MODAL_OVERLAY} z-[9999]`} onClick={() => setSelectedAlerta(null)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-lg p-6 md:p-8 modal-pop flex flex-col`} onClick={e => e.stopPropagation()}>
                        <div className="flex justify-between items-center mb-6">
                            <h3 className="text-lg font-black uppercase italic tracking-tighter theme-text-main">
                                Registrar Llamada: <span style={{ color: 'var(--color-primario)' }}>{selectedAlerta.cliente.nombre}</span>
                            </h3>
                            <button onClick={() => setSelectedAlerta(null)} className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={guardarResultadoLlamada} className="space-y-4">
                            <div className="space-y-2">
                                <label className="text-[9px] font-black uppercase theme-text-muted tracking-widest">Resultado de la Llamada</label>
                                <select
                                    value={formAlerta.data.estado}
                                    onChange={e => formAlerta.setData('estado', e.target.value)}
                                    className="w-full px-4 py-3 theme-surface border theme-border rounded-xl font-bold text-xs theme-text-main outline-none cursor-pointer"
                                >
                                    <option value="pendiente">Pendiente de llamar</option>
                                    <option value="llamado">Llamada contestada</option>
                                    <option value="no_contesto">No contestó / Fuera de servicio</option>
                                    <option value="compromiso_pago">Compromiso de pago registrado</option>
                                </select>
                            </div>

                            <div className="space-y-2">
                                <label className="text-[9px] font-black uppercase theme-text-muted tracking-widest">Observaciones y Acuerdos</label>
                                <textarea
                                    value={formAlerta.data.observaciones}
                                    onChange={e => formAlerta.setData('observaciones', e.target.value)}
                                    rows={4}
                                    placeholder="Ingrese los acuerdos y detalles de la llamada..."
                                    className="w-full px-4 py-3 theme-surface border theme-border rounded-xl font-bold text-xs theme-text-main outline-none"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={formAlerta.processing}
                                className="w-full py-4 rounded-xl text-white font-black uppercase tracking-widest text-[11px] transition-transform hover:scale-102 shadow-md flex justify-center items-center gap-2 outline-none disabled:opacity-50"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Check className="w-4 h-4" /> {formAlerta.processing ? 'Registrando...' : 'Guardar Resultado'}
                            </button>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal para Bitácora */}
            {selectedBitacoraCliente && (
                <div className={`${THEME_MODAL_OVERLAY} z-[9999] p-4`} onClick={() => setSelectedBitacoraCliente(null)}>
                    <div className={`${THEME_MODAL_SHELL} w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden`} onClick={e => e.stopPropagation()}>
                        <div className="p-6 md:p-8 flex justify-between items-center border-b theme-border bg-black/5 dark:bg-white/5">
                            <div>
                                <h3 className="text-lg font-black uppercase italic tracking-tighter theme-text-main flex items-center gap-2">
                                    <BarChart3 className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                    Bitácora de Crédito
                                </h3>
                                <p className="text-xs theme-text-muted mt-1 font-bold">
                                    Cliente: <span style={{ color: 'var(--color-primario)' }}>{selectedBitacoraCliente.nombre}</span>
                                </p>
                            </div>
                            <button onClick={() => setSelectedBitacoraCliente(null)} className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="flex-1 overflow-y-auto p-6 md:p-8">
                            {cargandoBitacora ? (
                                <div className="text-center py-8">
                                    <div className="w-6 h-6 border-2 border-[var(--color-primario)] border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                                    <p className="text-xs font-bold theme-text-muted uppercase tracking-widest">Cargando bitácora...</p>
                                </div>
                            ) : bitacoraData.length === 0 ? (
                                <p className="text-center text-xs theme-text-muted uppercase tracking-widest font-bold py-12">
                                    No hay registros de crédito para este cliente.
                                </p>
                            ) : (
                                <div className="space-y-6 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-zinc-300 dark:before:via-zinc-700 before:to-transparent">
                                    {bitacoraData.map((item, index) => {
                                        const isAlerta = item.es_alerta || item.tipo_evento === 'alerta_aumento';
                                        const isPago = item.tipo_evento === 'pago';
                                        const isInicio = item.tipo_evento === 'inicio_credito';
                                        
                                        let icon = <FileText className="w-3.5 h-3.5 text-zinc-500" />;
                                        let dotClass = "bg-zinc-200 dark:bg-zinc-800 border-zinc-300 dark:border-zinc-700";
                                        
                                        if (isAlerta) {
                                            icon = <AlertCircle className="w-3.5 h-3.5 text-white" />;
                                            dotClass = "bg-red-500 border-red-200 dark:border-red-900";
                                        } else if (isPago) {
                                            icon = <Check className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />;
                                            dotClass = "bg-emerald-100 dark:bg-emerald-900 border-emerald-300 dark:border-emerald-700";
                                        } else if (isInicio) {
                                            icon = <Coins className="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />;
                                            dotClass = "bg-blue-100 dark:bg-blue-900 border-blue-300 dark:border-blue-700";
                                        }

                                        return (
                                            <div key={item.id} className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                                                <div className="flex items-center justify-center w-10 h-10 rounded-full border-4 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10 mx-auto bg-white dark:bg-zinc-900 border-white dark:border-zinc-900">
                                                    <div className={`w-full h-full rounded-full flex items-center justify-center border-2 ${dotClass}`}>
                                                        {icon}
                                                    </div>
                                                </div>
                                                <div className={`w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-2xl border shadow-sm ${isAlerta ? 'border-red-500/50 bg-red-500/5' : 'theme-border bg-white dark:bg-zinc-900'}`}>
                                                    <div className="flex items-center justify-between mb-2">
                                                        <span className={`text-[9px] font-black uppercase tracking-widest ${isAlerta ? 'text-red-500' : 'text-[var(--color-primario)]'}`}>
                                                            {item.tipo_evento.replace('_', ' ')}
                                                        </span>
                                                        <span className="text-[9px] font-bold theme-text-muted">
                                                            {new Date(item.created_at).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                                        </span>
                                                    </div>
                                                    <p className="text-xs theme-text-main mb-3 font-medium">
                                                        {item.descripcion}
                                                    </p>
                                                    {(item.monto_anterior > 0 || item.monto_nuevo > 0) && (
                                                        <div className="flex items-center gap-3 pt-3 border-t theme-border/50">
                                                            <div>
                                                                <p className="text-[8px] font-black uppercase tracking-widest theme-text-muted mb-0.5">Anterior</p>
                                                                <p className="text-xs font-mono font-bold theme-text-main">${item.monto_anterior}</p>
                                                            </div>
                                                            <ArrowRight className="w-3 h-3 theme-text-muted" />
                                                            <div>
                                                                <p className="text-[8px] font-black uppercase tracking-widest theme-text-muted mb-0.5">Nuevo</p>
                                                                <p className={`text-xs font-mono font-black ${isAlerta ? 'text-red-500' : (item.monto_nuevo < item.monto_anterior ? 'text-emerald-500' : 'theme-text-main')}`}>
                                                                    ${item.monto_nuevo}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    )}
                                                    {item.usuario && (
                                                        <p className="text-[9px] font-bold theme-text-muted mt-3 italic text-right">
                                                            por {item.usuario.name}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

        </AppLayout>
    );
}
