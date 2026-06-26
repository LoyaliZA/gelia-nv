import React, { useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    CheckCircle2,
    Search,
    Wallet,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { formatoMoneda } from '../../utils/formatoMoneda';
import { formatoFechaCorta } from '../../utils/formatoFecha';
import { puedePermiso } from '../../utils/permisos';
import {
    BTN_ACCION,
    BTN_PRIMARY,
    BTN_PRIMARY_STYLE,
    HERO_TITLE,
    SECTION_TITLE,
    contabilidadCard,
} from './Partials/contabilidadStyles';
import { colorPlataforma, contabilidadRoutes, montoEsperadoBanco } from './contabilidadRoutes';

export default function Retiros({ auth, datos_plataformas = [] }) {
    const { flash } = usePage().props;
    const [tabActiva, setTabActiva] = useState(datos_plataformas[0]?.plataforma?.id ?? null);
    const [busqueda, setBusqueda] = useState('');
    const [ordenAsc, setOrdenAsc] = useState(true);
    const [seleccionados, setSeleccionados] = useState({});
    const [montosReales, setMontosReales] = useState({});
    const [fechaBanco, setFechaBanco] = useState(new Date().toISOString().split('T')[0]);
    const [procesando, setProcesando] = useState(false);

    const puedeConfirmar = puedePermiso(auth, 'contabilidad.retiros.confirmar');

    const plataformaActiva = useMemo(
        () => datos_plataformas.find((d) => d.plataforma.id === tabActiva) || datos_plataformas[0],
        [datos_plataformas, tabActiva]
    );

    const colorActivo = colorPlataforma(plataformaActiva?.plataforma?.nombre);

    const gruposFiltrados = useMemo(() => {
        if (!plataformaActiva) return {};
        const q = busqueda.trim().toLowerCase();
        const grupos = { ...plataformaActiva.grupos };

        Object.keys(grupos).forEach((nombre) => {
            let pedidos = [...grupos[nombre]];
            if (q) {
                pedidos = pedidos.filter((p) =>
                    `${p.numero_pedido} ${p.cliente_nombre || ''}`.toLowerCase().includes(q)
                );
            }
            pedidos.sort((a, b) => {
                const da = new Date(a.fecha_salida);
                const db = new Date(b.fecha_salida);
                return ordenAsc ? da - db : db - da;
            });
            grupos[nombre] = pedidos;
        });

        return grupos;
    }, [plataformaActiva, busqueda, ordenAsc]);

    const idsSeleccionados = Object.keys(seleccionados).filter((id) => seleccionados[id]);

    const resumen = useMemo(() => {
        if (!plataformaActiva) return { cantidad: 0, esperado: 0, real: 0 };
        let esperado = 0;
        let real = 0;
        idsSeleccionados.forEach((id) => {
            const pedido = Object.values(plataformaActiva.grupos).flat().find((p) => String(p.id) === id);
            if (!pedido) return;
            const esp = montoEsperadoBanco(pedido);
            esperado += esp;
            real += parseFloat(montosReales[id] ?? esp.toFixed(2));
        });
        return { cantidad: idsSeleccionados.length, esperado, real };
    }, [idsSeleccionados, montosReales, plataformaActiva]);

    const togglePedido = (pedido, checked) => {
        const esp = montoEsperadoBanco(pedido);
        setSeleccionados((prev) => ({ ...prev, [pedido.id]: checked }));
        if (checked) {
            setMontosReales((prev) => ({ ...prev, [pedido.id]: prev[pedido.id] ?? esp.toFixed(2) }));
        }
    };

    const seleccionarGrupo = (pedidos) => {
        const nextSel = { ...seleccionados };
        const nextMontos = { ...montosReales };
        pedidos.forEach((p) => {
            nextSel[p.id] = true;
            nextMontos[p.id] = nextMontos[p.id] ?? montoEsperadoBanco(p).toFixed(2);
        });
        setSeleccionados(nextSel);
        setMontosReales(nextMontos);
    };

    const procesarLote = (e) => {
        e.preventDefault();
        if (!puedeConfirmar || idsSeleccionados.length === 0) return;
        setProcesando(true);
        router.post(
            contabilidadRoutes.retirosConfirmarLote(),
            {
                plataforma_pago_id: plataformaActiva.plataforma.id,
                fecha_deposito: fechaBanco,
                pedidos: idsSeleccionados.map((id) => ({
                    id: Number(id),
                    monto_real: parseFloat(montosReales[id] || 0),
                })),
            },
            {
                preserveScroll: true,
                onFinish: () => setProcesando(false),
            }
        );
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Conciliación y Retiros" />

            <div className="max-w-[1600px] mx-auto p-4 md:p-8 space-y-6 contabilidad-page min-h-screen flex flex-col">
                {flash?.success && (
                    <div className="theme-surface theme-card border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 rounded-2xl px-4 py-3 text-sm font-bold">
                        {flash.success}
                    </div>
                )}

                <header className={`${contabilidadCard()} p-5 md:p-6 flex flex-col lg:flex-row justify-between items-center gap-4`}>
                    <h1 className={`${HERO_TITLE} !text-2xl md:!text-3xl flex items-center gap-3`}>
                        <Wallet className="w-8 h-8 text-[var(--color-primario)]" />
                        Conciliación y <span style={{ color: 'var(--color-primario)' }}>Retiros</span>
                    </h1>
                    <div className="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                        <div className="relative flex-1 sm:max-w-xs">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                            <input
                                type="search"
                                placeholder="Buscar pedido o cliente..."
                                value={busqueda}
                                onChange={(e) => setBusqueda(e.target.value)}
                                className="w-full pl-9 pr-3 py-2.5 theme-surface border theme-border rounded-xl text-sm font-bold"
                            />
                        </div>
                        <button type="button" onClick={() => setOrdenAsc((v) => !v)} className={BTN_ACCION.analisis}>
                            {ordenAsc ? <ArrowDown className="w-4 h-4" /> : <ArrowUp className="w-4 h-4" />}
                            {ordenAsc ? 'Más antiguos' : 'Más recientes'}
                        </button>
                        <Link href={contabilidadRoutes.index()} className={BTN_ACCION.analisis}>
                            <ArrowLeft className="w-4 h-4" /> Volver
                        </Link>
                    </div>
                </header>

                <div className="flex flex-col xl:flex-row gap-6 flex-1 min-h-0">
                    <div className="w-full xl:w-2/3 flex flex-col gap-4 min-h-0">
                        <div className="flex gap-2 overflow-x-auto pb-1 custom-scrollbar">
                            {datos_plataformas.map((dato) => {
                                const activo = dato.plataforma.id === (plataformaActiva?.plataforma?.id);
                                const color = colorPlataforma(dato.plataforma.nombre);
                                return (
                                    <button
                                        key={dato.plataforma.id}
                                        type="button"
                                        onClick={() => { setTabActiva(dato.plataforma.id); setSeleccionados({}); }}
                                        className={`px-5 py-2.5 rounded-xl font-black text-[10px] uppercase tracking-widest border whitespace-nowrap flex items-center gap-2 transition-all ${
                                            activo ? '' : 'theme-element theme-border theme-text-muted'
                                        }`}
                                        style={activo ? { backgroundColor: color, borderColor: color, color: '#ffffff' } : {}}
                                    >
                                        {dato.plataforma.nombre}
                                        {dato.total_pendientes > 0 && (
                                            <span className="bg-red-500 text-white text-[9px] px-2 py-0.5 rounded-full">{dato.total_pendientes}</span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>

                        <div className={`${contabilidadCard('p-4 flex-1 overflow-y-auto custom-scrollbar max-h-[70vh] xl:max-h-none')}`}>
                            {!plataformaActiva || Object.keys(gruposFiltrados).length === 0 ? (
                                <div className="p-12 text-center theme-text-muted flex flex-col items-center">
                                    <CheckCircle2 className="w-12 h-12 mb-3 opacity-50" />
                                    <p className="font-black italic uppercase">Todo al día. No hay retiros pendientes.</p>
                                </div>
                            ) : (
                                Object.entries(gruposFiltrados).map(([nombreGrupo, pedidosGrupo]) => {
                                    if (pedidosGrupo.length === 0) return null;
                                    return (
                                        <div key={nombreGrupo} className="mb-4 border theme-border rounded-2xl overflow-hidden">
                                            <div className="p-4 flex flex-col sm:flex-row justify-between gap-3" style={{ backgroundColor: `${colorActivo}10`, borderBottom: `1px solid ${colorActivo}30` }}>
                                                <div>
                                                    <h3 className="font-black theme-text-main text-sm uppercase">{nombreGrupo}</h3>
                                                    <p className="text-xs theme-text-muted font-bold">{pedidosGrupo.length} operaciones detectadas</p>
                                                </div>
                                                {puedeConfirmar && (
                                                    <button type="button" onClick={() => seleccionarGrupo(pedidosGrupo)} className="text-[10px] font-black uppercase tracking-widest text-white px-4 py-2 rounded-xl" style={{ backgroundColor: `${colorActivo}cc` }}>
                                                        Seleccionar grupo
                                                    </button>
                                                )}
                                            </div>
                                            <div className="overflow-x-auto">
                                                <table className="w-full text-xs whitespace-nowrap">
                                                    <thead className="theme-element text-[10px] uppercase theme-text-muted border-b theme-border">
                                                        <tr>
                                                            <th className="p-2 w-8" />
                                                            <th className="p-2 text-left font-black">Fecha</th>
                                                            <th className="p-2 text-left font-black">Pedido</th>
                                                            <th className="p-2 text-left font-black">Cliente</th>
                                                            <th className="p-2 text-right font-black">Venta</th>
                                                            <th className="p-2 text-right font-black">Retiro esp.</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y theme-border">
                                                        {pedidosGrupo.map((pedido) => {
                                                            const esVenta = (pedido.tipo_transaccion?.codigo || 'venta').includes('venta');
                                                            const retiroEsp = retiroEsperadoPedido(pedido);
                                                            const filtro = `${pedido.numero_pedido} ${pedido.cliente_nombre || ''}`.toLowerCase();
                                                            if (busqueda && !filtro.includes(busqueda.toLowerCase())) return null;

                                                            return (
                                                                <tr key={pedido.id} className={`hover:bg-black/5 dark:hover:bg-white/5 ${!esVenta ? 'bg-red-500/5' : ''}`}>
                                                                    <td className="p-2 text-center">
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={Boolean(seleccionados[pedido.id])}
                                                                            onChange={(e) => togglePedido(pedido, e.target.checked)}
                                                                            className="rounded border theme-border"
                                                                            style={{ accentColor: colorActivo }}
                                                                        />
                                                                    </td>
                                                                    <td className="p-2 theme-text-muted font-medium">{formatoFechaCorta(pedido.fecha_salida)}</td>
                                                                    <td className="p-2 font-black" style={{ color: colorActivo }}>{pedido.numero_pedido}</td>
                                                                    <td className="p-2 theme-text-main truncate max-w-[140px]" title={pedido.cliente_nombre || ''}>{pedido.cliente_nombre || 'Sin nombre'}</td>
                                                                    <td className="p-2 text-right font-bold">{formatoMoneda(pedido.venta_total)}</td>
                                                                    <td className={`p-2 text-right font-black ${retiroEsp >= 0 ? 'text-emerald-500' : 'text-red-500'}`}>{formatoMoneda(retiroEsp)}</td>
                                                                </tr>
                                                            );
                                                        })}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    <div className="w-full xl:w-1/3">
                        <div className={`${contabilidadCard('p-6 flex flex-col h-full')}`}>
                            <h2 className={`${SECTION_TITLE} border-b theme-border pb-3 mb-4 flex items-center gap-2`}>
                                <CheckCircle2 className="w-5 h-5 text-emerald-500" />
                                Panel de lote a procesar
                            </h2>

                            <div className="theme-element border theme-border rounded-2xl p-4 mb-4 space-y-2">
                                <div className="flex justify-between items-center">
                                    <span className="text-[10px] font-black uppercase theme-text-muted">Pedidos seleccionados</span>
                                    <span className="text-lg font-black theme-text-main bg-black/5 dark:bg-white/5 px-2 rounded-lg">{resumen.cantidad}</span>
                                </div>
                                <div className="flex justify-between items-center text-sm">
                                    <span className="theme-text-muted font-bold">Retiro esperado global</span>
                                    <span className="font-black text-blue-500">{formatoMoneda(resumen.esperado)}</span>
                                </div>
                                <div className="flex justify-between items-center pt-2 border-t theme-border">
                                    <span className="text-sm font-black uppercase theme-text-main">Total a ingresar</span>
                                    <span className="text-2xl font-black italic text-emerald-500">{formatoMoneda(resumen.real)}</span>
                                </div>
                            </div>

                            <form onSubmit={procesarLote} className="flex flex-col flex-1 min-h-0">
                                <label className="text-[10px] font-black uppercase tracking-widest theme-text-main mb-2">Desglose individual</label>
                                <div className="theme-element border theme-border rounded-2xl p-3 mb-4 overflow-y-auto custom-scrollbar flex-1 max-h-[280px] space-y-2">
                                    {idsSeleccionados.length === 0 ? (
                                        <p className="text-xs theme-text-muted text-center italic py-6">Selecciona pedidos para desglosarlos aquí.</p>
                                    ) : (
                                        idsSeleccionados.map((id) => {
                                            const pedido = Object.values(plataformaActiva?.grupos || {}).flat().find((p) => String(p.id) === id);
                                            if (!pedido) return null;
                                            return (
                                                <div key={id} className="flex items-center justify-between gap-2 text-xs border-b theme-border pb-2">
                                                    <span className="font-black" style={{ color: colorActivo }}>{pedido.numero_pedido}</span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        className="theme-input w-28 text-right font-bold py-1.5"
                                                        value={montosReales[id] ?? ''}
                                                        onChange={(e) => setMontosReales((prev) => ({ ...prev, [id]: e.target.value }))}
                                                    />
                                                </div>
                                            );
                                        })
                                    )}
                                </div>

                                <div className="mb-4">
                                    <label className="text-[10px] font-black uppercase theme-text-muted">Fecha de ingreso a banco</label>
                                    <input type="date" required value={fechaBanco} onChange={(e) => setFechaBanco(e.target.value)} className="theme-input w-full mt-1 py-3 font-bold rounded-xl" />
                                </div>

                                <button
                                    type="submit"
                                    disabled={!puedeConfirmar || idsSeleccionados.length === 0 || procesando}
                                    className={`${BTN_PRIMARY} w-full !bg-emerald-600 hover:!bg-emerald-500`}
                                    style={{ ...BTN_PRIMARY_STYLE, backgroundColor: idsSeleccionados.length > 0 ? '#059669' : undefined }}
                                >
                                    <CheckCircle2 className="w-5 h-5" />
                                    {procesando ? 'Procesando...' : 'Aprobar retiro'}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
