import React, { useCallback, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    CreditCard,
    Edit2,
    MoreVertical,
    Package,
    Search,
    SlidersHorizontal,
    Trash2,
    X,
    FileText,
    FileSpreadsheet,
} from 'lucide-react';
import { createPortal } from 'react-dom';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { formatoMoneda } from '../../../utils/formatoMoneda';
import { formatoFechaCorta } from '../../../utils/formatoFecha';
import { puedePermiso } from '../../../utils/permisos';
import {
    BADGE_ESTATUS,
    BADGE_TIPO,
    BTN_PRIMARY_STYLE,
    BTN_SEARCH,
    CONTABILIDAD_TABLE_HEAD,
    SECTION_TITLE,
    TABLE_TD,
    TABLE_TH,
    contabilidadCard,
} from './contabilidadStyles';
import { contabilidadRoutes } from '../contabilidadRoutes';
import ModalConfirmarRetiro from './ModalConfirmarRetiro';
import ModalVerProductos from './ModalVerProductos';
import ModalEditarPedido from './ModalEditarPedido';

function badgeTipo(codigo) {
    const key = (codigo || 'venta').toLowerCase();
    if (key.includes('reembolso')) return BADGE_TIPO.reembolso;
    if (key.includes('contracargo')) return BADGE_TIPO.contracargo;
    return BADGE_TIPO.venta;
}

function labelTipo(pedido) {
    const codigo = pedido.tipo_transaccion?.codigo || 'venta';
    if (codigo === 'venta') return 'Venta normal';
    return pedido.tipo_transaccion?.nombre || codigo;
}

function datosPedido(pedido) {
    const transferido = pedido.estatus_pago?.codigo === 'transferido';
    const utilPreRetiro = Number(pedido.utilidad_total) + Number(pedido.comision_transferencia || 0);
    return { transferido, utilPreRetiro };
}

function MenuAccionesPedido({
    pedido,
    auth,
    pos,
    onCerrar,
    onConfirmar,
    onVerProductos,
    onEditar,
    onEliminar,
}) {
    const puedeConfirmar = puedePermiso(auth, 'contabilidad.retiros.confirmar')
        && pedido.estatus_pago?.codigo === 'pendiente';
    const puedeEditar = puedePermiso(auth, 'contabilidad.pedidos.editar') && !pedido.bloqueado;
    const puedeEliminar = puedePermiso(auth, 'contabilidad.pedidos.eliminar') && !pedido.bloqueado;

    return createPortal(
        <>
            <div className="fixed inset-0 z-[999]" onClick={onCerrar} />
            <div
                className="fixed z-[1000] theme-surface border theme-border shadow-2xl rounded-2xl p-2 flex flex-col gap-1 backdrop-blur-xl w-56"
                style={{ top: pos.top, left: pos.left }}
            >
                {puedeConfirmar && (
                    <button type="button" onClick={() => { onCerrar(); onConfirmar(pedido); }} className="flex items-center gap-3 px-4 py-3 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-xl text-[10px] font-black uppercase tracking-widest">
                        <CreditCard className="w-4 h-4" /> Confirmar pago
                    </button>
                )}
                <button type="button" onClick={() => { onCerrar(); onVerProductos(pedido); }} className="flex items-center gap-3 px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5 rounded-xl text-[10px] font-black uppercase tracking-widest theme-text-main">
                    <Package className="w-4 h-4" /> Ver productos
                </button>
                {puedeEditar && (
                    <button type="button" onClick={() => { onCerrar(); onEditar(pedido); }} className="flex items-center gap-3 px-4 py-3 hover:bg-amber-50 dark:hover:bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-xl text-[10px] font-black uppercase tracking-widest">
                        <Edit2 className="w-4 h-4" /> Editar valores
                    </button>
                )}
                {puedeEliminar && (
                    <button type="button" onClick={() => { onCerrar(); onEliminar(pedido); }} className="flex items-center gap-3 px-4 py-3 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 dark:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-widest border-t theme-border mt-1 pt-3">
                        <Trash2 className="w-4 h-4" /> Eliminar registro
                    </button>
                )}
            </div>
        </>,
        document.body
    );
}

function CeldaAcciones({ pedido, auth, onConfirmar, onVerProductos, onEditar, onEliminar }) {
    const [menuAbierto, setMenuAbierto] = useState(false);
    const [pos, setPos] = useState({ top: 0, left: 0 });

    const abrirMenu = (e) => {
        const rect = e.currentTarget.getBoundingClientRect();
        const menuWidth = 224;
        let left = rect.right - menuWidth;
        if (left < 8) left = 8;
        setPos({ top: rect.bottom + 8, left });
        setMenuAbierto(true);
    };

    return (
        <>
            <button type="button" onClick={abrirMenu} className="p-3 theme-element border theme-border hover:border-[var(--color-primario)] rounded-2xl transition-all shadow-sm outline-none">
                <MoreVertical className="w-5 h-5 theme-text-main" />
            </button>
            {menuAbierto && (
                <MenuAccionesPedido
                    pedido={pedido}
                    auth={auth}
                    pos={pos}
                    onCerrar={() => setMenuAbierto(false)}
                    onConfirmar={onConfirmar}
                    onVerProductos={onVerProductos}
                    onEditar={onEditar}
                    onEliminar={onEliminar}
                />
            )}
        </>
    );
}

function FilaPedidoDesktop({ pedido, auth, onConfirmar, onVerProductos, onEditar, onEliminar }) {
    const { transferido, utilPreRetiro } = datosPedido(pedido);

    return (
        <tr className="border-b theme-border transition-colors hover:bg-black/5 dark:hover:bg-white/5 group">
            <td className={`${TABLE_TD} theme-text-main font-medium`}>{formatoFechaCorta(pedido.fecha_salida)}</td>
            <td className={`${TABLE_TD} theme-text-muted`}>{pedido.plataforma_pago?.nombre || '—'}</td>
            <td className={`${TABLE_TD} font-black tracking-wide`} style={{ color: 'var(--color-primario)' }}>{pedido.numero_pedido}</td>
            <td className={TABLE_TD}>
                <span className={`text-[9px] font-black uppercase px-2 py-1 rounded-md ${badgeTipo(pedido.tipo_transaccion?.codigo)}`}>{labelTipo(pedido)}</span>
            </td>
            <td className={`${TABLE_TD} theme-text-main max-w-[200px] truncate font-bold uppercase italic`} title={pedido.cliente_nombre || ''}>{pedido.cliente_nombre || 'Sin nombre'}</td>
            <td className={`${TABLE_TD} text-right`}>
                <div className="font-black italic theme-text-main text-sm bg-black/5 dark:bg-white/5 px-3 py-1.5 rounded-lg inline-block border theme-border">{formatoMoneda(pedido.venta_total)}</div>
            </td>
            <td className={`${TABLE_TD} text-right font-black italic ${transferido ? 'theme-text-muted' : 'text-amber-500'}`}>{formatoMoneda(utilPreRetiro)}</td>
            <td className={`${TABLE_TD} text-right font-black italic ${transferido ? (Number(pedido.utilidad_total) >= 0 ? 'text-emerald-500' : 'text-red-500') : 'theme-text-muted'}`}>
                {transferido ? formatoMoneda(pedido.utilidad_total) : <span className="opacity-40">—</span>}
            </td>
            <td className={`${TABLE_TD} text-center`}>
                <span className={`text-[9px] font-black uppercase px-2.5 py-1 rounded-xl ${BADGE_ESTATUS[pedido.estatus_pago?.codigo] || BADGE_ESTATUS.pendiente}`}>
                    {pedido.estatus_pago?.nombre || 'Pendiente'}
                </span>
            </td>
            <td className={`${TABLE_TD} text-center sticky-actions`}>
                <CeldaAcciones pedido={pedido} auth={auth} onConfirmar={onConfirmar} onVerProductos={onVerProductos} onEditar={onEditar} onEliminar={onEliminar} />
            </td>
        </tr>
    );
}

function TarjetaPedidoMobile({ pedido, auth, onConfirmar, onVerProductos, onEditar, onEliminar }) {
    const { transferido, utilPreRetiro } = datosPedido(pedido);

    return (
        <div className="theme-surface rounded-3xl border theme-border p-5 shadow-lg flex flex-col gap-4">
            <div className="flex items-start justify-between border-b theme-border pb-3">
                <div>
                    <div className="font-black text-base" style={{ color: 'var(--color-primario)' }}>PED-{pedido.numero_pedido}</div>
                    <div className="text-[11px] font-bold theme-text-muted mt-0.5 uppercase">{formatoFechaCorta(pedido.fecha_salida)} · {pedido.plataforma_pago?.nombre || '—'}</div>
                </div>
                <span className={`text-[9px] font-black uppercase px-2.5 py-1 rounded-lg ${BADGE_ESTATUS[pedido.estatus_pago?.codigo] || BADGE_ESTATUS.pendiente}`}>
                    {pedido.estatus_pago?.nombre || 'Pendiente'}
                </span>
            </div>
            <div>
                <div className="font-bold text-base theme-text-main uppercase italic leading-tight">{pedido.cliente_nombre || 'Sin nombre'}</div>
                <span className={`inline-flex mt-2 text-[9px] font-black uppercase px-2 py-1 rounded-md ${badgeTipo(pedido.tipo_transaccion?.codigo)}`}>{labelTipo(pedido)}</span>
            </div>
            <div className="bg-black/5 dark:bg-white/5 p-3 rounded-2xl border theme-border grid grid-cols-2 gap-3">
                <div>
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Venta</p>
                    <p className="font-black italic theme-text-main text-sm mt-0.5">{formatoMoneda(pedido.venta_total)}</p>
                </div>
                <div>
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Util. neta</p>
                    <p className={`font-black italic text-sm mt-0.5 ${transferido ? (Number(pedido.utilidad_total) >= 0 ? 'text-emerald-500' : 'text-red-500') : 'theme-text-muted opacity-40'}`}>
                        {transferido ? formatoMoneda(pedido.utilidad_total) : '—'}
                    </p>
                </div>
            </div>
            <div className="flex justify-end pt-2 border-t theme-border">
                <CeldaAcciones pedido={pedido} auth={auth} onConfirmar={onConfirmar} onVerProductos={onVerProductos} onEditar={onEditar} onEliminar={onEliminar} />
            </div>
        </div>
    );
}

export default function HistorialPedidos({
    auth,
    pedidos,
    plataformas,
    tiposTransaccion,
    estatusPago,
    filtros,
}) {
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [plataformaId, setPlataformaId] = useState(filtros.plataforma_id ? String(filtros.plataforma_id) : '');
    const [estatusId, setEstatusId] = useState(filtros.estatus_pago_id ? String(filtros.estatus_pago_id) : '');
    const [tipoId, setTipoId] = useState(filtros.tipo_transaccion_id ? String(filtros.tipo_transaccion_id) : '');
    const [mostrarFiltros, setMostrarFiltros] = useState(
        Boolean(filtros.plataforma_id || filtros.estatus_pago_id || filtros.tipo_transaccion_id)
    );

    const [modalConfirmar, setModalConfirmar] = useState(null);
    const [modalProductos, setModalProductos] = useState(null);
    const [modalEditar, setModalEditar] = useState(null);

    const aplicarFiltros = useCallback(
        (extra = {}) => {
            router.get(
                contabilidadRoutes.index(),
                {
                    mes: filtros.mes,
                    anio: filtros.anio,
                    q: busqueda || undefined,
                    plataforma_id: plataformaId || undefined,
                    estatus_pago_id: estatusId || undefined,
                    tipo_transaccion_id: tipoId || undefined,
                    ...extra,
                },
                { preserveState: true, preserveScroll: true, replace: true }
            );
        },
        [busqueda, plataformaId, estatusId, tipoId, filtros.anio, filtros.mes]
    );

    const eliminarPedido = (pedido) => {
        if (!window.confirm(`¿Eliminar el pedido ${pedido.numero_pedido}?`)) return;
        router.delete(contabilidadRoutes.pedidosDestroy(pedido.id), { preserveScroll: true });
    };

    const limpiarFiltros = () => {
        setPlataformaId('');
        setEstatusId('');
        setTipoId('');
        router.get(contabilidadRoutes.index(), { mes: filtros.mes, anio: filtros.anio, q: busqueda || undefined, page: 1 }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const filtrosActivos = [plataformaId, estatusId, tipoId].filter(Boolean).length;

    return (
        <div className={`${contabilidadCard('p-5 md:p-6')} animate-page-reveal space-y-4`} style={{ animationDelay: '200ms' }}>
            <div className="flex flex-col gap-4 border-b theme-border pb-5">
                <h2 className={SECTION_TITLE}>Historial del periodo</h2>

                <div className="flex flex-col lg:flex-row gap-3 items-stretch lg:items-center">
                    <div className="flex flex-col sm:flex-row gap-2 flex-1 min-w-0">
                        <div className="relative flex-1 min-w-0">
                            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted pointer-events-none" />
                            <input
                                type="search"
                                placeholder="Buscar por pedido o cliente..."
                                value={busqueda}
                                onChange={(e) => setBusqueda(e.target.value)}
                                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); aplicarFiltros({ page: 1 }); } }}
                                className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm"
                            />
                        </div>
                        <button type="button" onClick={() => aplicarFiltros({ page: 1 })} className={BTN_SEARCH} style={BTN_PRIMARY_STYLE}>
                            <Search className="w-4 h-4 shrink-0" /> Buscar
                        </button>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            onClick={() => setMostrarFiltros((v) => !v)}
                            className={`flex items-center justify-center gap-2 px-4 py-3 rounded-xl border text-[10px] font-black uppercase tracking-widest transition-all ${
                                mostrarFiltros || filtrosActivos > 0
                                    ? 'border-[var(--color-primario)] text-[var(--color-primario)] bg-[color-mix(in_srgb,var(--color-primario)_10%,transparent)]'
                                    : 'theme-border theme-element theme-text-muted hover:border-[var(--color-primario)]'
                            }`}
                        >
                            <SlidersHorizontal className="w-4 h-4" />
                            Filtros
                            {filtrosActivos > 0 && (
                                <span className="w-5 h-5 rounded-full text-white text-[9px] flex items-center justify-center" style={{ backgroundColor: 'var(--color-primario)' }}>
                                    {filtrosActivos}
                                </span>
                            )}
                        </button>

                        <a
                            href={contabilidadRoutes.exportarPdf({
                                mes: filtros.mes,
                                anio: filtros.anio,
                                q: busqueda || undefined,
                                plataforma_id: plataformaId || undefined,
                                estatus_pago_id: estatusId || undefined,
                                tipo_transaccion_id: tipoId || undefined,
                            })}
                            className="flex items-center justify-center gap-2 px-4 py-3 rounded-xl border theme-border theme-element theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] text-[10px] font-black uppercase tracking-widest transition-all"
                            title="Exportar PDF"
                        >
                            <FileText className="w-4 h-4" />
                            PDF
                        </a>

                        <a
                            href={contabilidadRoutes.exportarCsv({
                                mes: filtros.mes,
                                anio: filtros.anio,
                                q: busqueda || undefined,
                                plataforma_id: plataformaId || undefined,
                                estatus_pago_id: estatusId || undefined,
                                tipo_transaccion_id: tipoId || undefined,
                            })}
                            className="flex items-center justify-center gap-2 px-4 py-3 rounded-xl border theme-border theme-element theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] text-[10px] font-black uppercase tracking-widest transition-all"
                            title="Exportar CSV"
                        >
                            <FileSpreadsheet className="w-4 h-4" />
                            CSV
                        </a>
                    </div>
                </div>

                {mostrarFiltros && (
                    <div className="theme-element border theme-border rounded-2xl p-4 space-y-3">
                        <div className="flex items-center justify-between">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Filtros de tabla</p>
                            {filtrosActivos > 0 && (
                                <button type="button" onClick={limpiarFiltros} className="text-[9px] font-black uppercase tracking-widest theme-text-muted hover:text-red-500 flex items-center gap-1">
                                    <X className="w-3 h-3" /> Limpiar
                                </button>
                            )}
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <select className="theme-input px-4 py-3 rounded-xl text-sm font-bold" value={plataformaId} onChange={(e) => setPlataformaId(e.target.value)}>
                                <option value="">Todas las plataformas</option>
                                {(plataformas || []).map((p) => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                            </select>
                            <select className="theme-input px-4 py-3 rounded-xl text-sm font-bold" value={estatusId} onChange={(e) => setEstatusId(e.target.value)}>
                                <option value="">Todos los estatus</option>
                                {(estatusPago || []).map((e) => <option key={e.id} value={e.id}>{e.nombre}</option>)}
                            </select>
                            <select className="theme-input px-4 py-3 rounded-xl text-sm font-bold" value={tipoId} onChange={(e) => setTipoId(e.target.value)}>
                                <option value="">Todos los tipos</option>
                                {(tiposTransaccion || []).map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                            </select>
                        </div>
                        <button type="button" onClick={() => aplicarFiltros({ page: 1 })} className="text-[10px] font-black uppercase tracking-widest text-[var(--color-primario)] hover:underline">
                            Aplicar filtros
                        </button>
                    </div>
                )}
            </div>

            <GeliaPaginacion paginator={pedidos} onIrAPagina={(page) => aplicarFiltros({ page })} />

            <div className="block lg:hidden space-y-4">
                {pedidos.data.length === 0 ? (
                    <div className="theme-element rounded-2xl p-8 text-center theme-text-muted font-bold text-sm border theme-border">Sin registros para este periodo_</div>
                ) : (
                    pedidos.data.map((pedido) => (
                        <TarjetaPedidoMobile
                            key={pedido.id}
                            pedido={pedido}
                            auth={auth}
                            onConfirmar={setModalConfirmar}
                            onVerProductos={setModalProductos}
                            onEditar={setModalEditar}
                            onEliminar={eliminarPedido}
                        />
                    ))
                )}
            </div>

            <div className="hidden lg:block overflow-hidden rounded-2xl border theme-border">
                <div className="overflow-x-auto custom-scrollbar">
                    <table className="w-full text-left border-collapse min-w-[1100px]">
                        <thead>
                            <tr className={`border-b theme-border ${CONTABILIDAD_TABLE_HEAD}`}>
                                <th className={`${TABLE_TH} text-left`}>Fecha_</th>
                                <th className={`${TABLE_TH} text-left`}>Plataforma_</th>
                                <th className={`${TABLE_TH} text-left`}>Pedido_</th>
                                <th className={`${TABLE_TH} text-left`}>Tipo_</th>
                                <th className={`${TABLE_TH} text-left`}>Cliente_</th>
                                <th className={`${TABLE_TH} text-right`}>Venta_</th>
                                <th className={`${TABLE_TH} text-right`}>Util. pre-retiro_</th>
                                <th className={`${TABLE_TH} text-right`}>Util. neta_</th>
                                <th className={`${TABLE_TH} text-center`}>Estatus_</th>
                                <th className={`${TABLE_TH} text-center`}>Acciones_</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pedidos.data.length === 0 ? (
                                <tr>
                                    <td colSpan={10} className="p-12 text-center theme-text-muted font-bold text-sm">Sin registros para este periodo_</td>
                                </tr>
                            ) : (
                                pedidos.data.map((pedido) => (
                                    <FilaPedidoDesktop
                                        key={pedido.id}
                                        pedido={pedido}
                                        auth={auth}
                                        onConfirmar={setModalConfirmar}
                                        onVerProductos={setModalProductos}
                                        onEditar={setModalEditar}
                                        onEliminar={eliminarPedido}
                                    />
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {modalConfirmar && <ModalConfirmarRetiro pedido={modalConfirmar} onCerrar={() => setModalConfirmar(null)} />}
            {modalProductos && <ModalVerProductos pedido={modalProductos} onCerrar={() => setModalProductos(null)} />}
            {modalEditar && (
                <ModalEditarPedido
                    key={modalEditar.id}
                    pedido={modalEditar}
                    plataformas={plataformas}
                    tiposTransaccion={tiposTransaccion}
                    onCerrar={() => setModalEditar(null)}
                />
            )}
        </div>
    );
}
