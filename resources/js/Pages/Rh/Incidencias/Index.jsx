import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Plus, Eye, Pencil } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoDeduccionEntera, formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import FiltrosIncidencias from './Partials/FiltrosIncidencias';
import ModalFormIncidencia from './Partials/ModalFormIncidencia';
import { ESTADO_DEDUCCION_BADGE, ESTADO_DEDUCCION_LABELS } from './Partials/incidenciasStyles';

function tabFromFiltros(filtros) {
    if (filtros.estado_deduccion === 'pendiente') return 'PENDIENTES';
    if (filtros.estado_deduccion === 'programado') return 'PROGRAMADAS';
    if (filtros.estado_deduccion === 'aplicado') return 'APLICADAS';
    return 'TODAS';
}

function FilaAcciones({ reg, puedeEditar, onEditar }) {
    return (
        <div className="flex flex-wrap gap-3 mt-3">
            <Link href={route('rh.incidencias.show', reg.id)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase" style={{ color: 'var(--color-primario)' }}>
                <Eye className="w-3.5 h-3.5" /> Ver
            </Link>
            {puedeEditar && reg.estado_deduccion !== 'aplicado' && (
                <button type="button" onClick={() => onEditar(reg)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase theme-text-muted">
                    <Pencil className="w-3.5 h-3.5" /> Editar
                </button>
            )}
        </div>
    );
}

export default function Index({
    auth,
    registros,
    metricas,
    colaboradores,
    tiposFalta,
    departamentos,
    filtros,
    puedeCrear,
    puedeEditar,
}) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [registroEditando, setRegistroEditando] = useState(null);
    const [tabActiva, setTabActiva] = useState(() => tabFromFiltros(filtros));

    const abrirNuevo = () => {
        setRegistroEditando(null);
        setModalAbierto(true);
    };

    const abrirEditar = (registro) => {
        if (registro.estado_deduccion === 'aplicado') return;
        setRegistroEditando(registro);
        setModalAbierto(true);
    };

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > registros.last_page) return;
        router.get(route('rh.incidencias.index'), { ...filtros, page: pagina }, { preserveState: true, preserveScroll: true });
    };

    const kpis = [
        { label: 'Incidencias hoy', value: metricas.registros_hoy, accent: true },
        { label: 'Pendientes deducción', value: metricas.pendientes_deduccion, color: '#f59e0b' },
        { label: 'Total deducción periodo', value: formatoDeduccionEntera(metricas.total_deduccion_periodo), color: '#ef4444' },
        { label: 'Monto pendiente', value: formatoDeduccionEntera(metricas.monto_pendiente), color: '#3b82f6' },
    ];

    return (
        <AppLayout auth={auth}>
            <Head title="Incidencias | RH" />
            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className={geliaCardClass('p-6 md:p-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6')}>
                    <div>
                        <h1 className="text-2xl sm:text-3xl md:text-4xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                            Faltas, <span style={{ color: 'var(--color-primario)' }}>Permisos y Retardos</span>
                        </h1>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                            {registros.total.toLocaleString('es-MX')} registros operativos
                        </p>
                    </div>
                    {puedeCrear && (
                        <button type="button" onClick={abrirNuevo} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Plus className="w-4 h-4" /> Nueva incidencia
                        </button>
                    )}
                </header>

                <RhSubNav />

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {kpis.map(({ label, value, color, accent }) => (
                        <div key={label} className={geliaCardClass('p-5')}>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">{label}</p>
                            <p className="text-xl font-black italic m-0" style={accent ? { color: 'var(--color-primario)' } : { color }}>{value}</p>
                        </div>
                    ))}
                </div>

                <div className={geliaCardClass('overflow-hidden')}>
                    <FiltrosIncidencias
                        filtros={filtros}
                        colaboradores={colaboradores}
                        tiposFalta={tiposFalta}
                        departamentos={departamentos}
                        tabActiva={tabActiva}
                        onCambiarTab={setTabActiva}
                    />

                    {registros.data.length === 0 ? (
                        <div className="py-16 text-center">
                            <AlertTriangle className="w-10 h-10 mx-auto mb-3 opacity-40 theme-text-muted" />
                            <p className="theme-text-muted italic text-sm m-0">No hay incidencias con estos filtros.</p>
                        </div>
                    ) : (
                        <>
                            <div className="lg:hidden divide-y theme-border">
                                {registros.data.map((reg) => (
                                    <div key={reg.id} className="p-4 space-y-2">
                                        <div className="flex justify-between items-start gap-2">
                                            <div>
                                                <p className="text-xs font-mono font-bold theme-text-main m-0">{reg.folio}</p>
                                                <p className="text-sm font-bold theme-text-main m-0 mt-1">{nombreCompletoColaborador(reg.colaborador)}</p>
                                                <p className="text-[10px] theme-text-muted m-0">{reg.tipo_falta_nombre_snapshot || reg.tipo_falta?.nombre}</p>
                                            </div>
                                            <span className={`px-2 py-1 rounded-lg text-[9px] font-black uppercase border shrink-0 ${ESTADO_DEDUCCION_BADGE[reg.estado_deduccion]}`}>
                                                {ESTADO_DEDUCCION_LABELS[reg.estado_deduccion]}
                                            </span>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2 text-[10px]">
                                            <div><span className="theme-text-muted uppercase font-black">Fecha:</span> {reg.fecha_ocurrencia?.slice?.(0, 10)}</div>
                                            <div><span className="theme-text-muted uppercase font-black">Total:</span> {formatoDeduccionEntera(reg.total_deduccion)}</div>
                                            <div><span className="theme-text-muted uppercase font-black">Salario:</span> {formatoMoneda(reg.deduccion_salario_base)}</div>
                                            <div><span className="theme-text-muted uppercase font-black">Bonos:</span> {formatoMoneda(Number(reg.deduccion_bono_puntualidad) + Number(reg.deduccion_bono_productividad))}</div>
                                        </div>
                                        <FilaAcciones reg={reg} puedeEditar={puedeEditar} onEditar={abrirEditar} />
                                    </div>
                                ))}
                            </div>

                            <div className="hidden lg:block overflow-x-auto">
                                <table className="w-full border-collapse">
                                    <thead>
                                        <tr className="border-b theme-border">
                                            {['Folio', 'Fecha', 'Colaborador', 'Tipo', 'Salario', 'Bono punt.', 'Bono prod.', 'Total', 'Estado', ''].map((h) => (
                                                <th key={h} className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">{h}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {registros.data.map((reg) => (
                                            <tr key={reg.id} className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                                                <td className="px-4 py-4 text-xs font-mono font-bold">{reg.folio}</td>
                                                <td className="px-4 py-4 text-xs">{reg.fecha_ocurrencia?.slice?.(0, 10) || reg.fecha_ocurrencia}</td>
                                                <td className="px-4 py-4 text-sm font-bold">{nombreCompletoColaborador(reg.colaborador)}</td>
                                                <td className="px-4 py-4 text-xs">{reg.tipo_falta_nombre_snapshot || reg.tipo_falta?.nombre}</td>
                                                <td className="px-4 py-4 text-xs">{formatoMoneda(reg.deduccion_salario_base)}</td>
                                                <td className="px-4 py-4 text-xs">{formatoMoneda(reg.deduccion_bono_puntualidad)}</td>
                                                <td className="px-4 py-4 text-xs">{formatoMoneda(reg.deduccion_bono_productividad)}</td>
                                                <td className="px-4 py-4 text-sm font-bold">{formatoDeduccionEntera(reg.total_deduccion)}</td>
                                                <td className="px-4 py-4">
                                                    <span className={`px-2 py-1 rounded-lg text-[9px] font-black uppercase border ${ESTADO_DEDUCCION_BADGE[reg.estado_deduccion]}`}>
                                                        {ESTADO_DEDUCCION_LABELS[reg.estado_deduccion]}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4 text-right whitespace-nowrap">
                                                    <FilaAcciones reg={reg} puedeEditar={puedeEditar} onEditar={abrirEditar} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}

                    {registros.data.length > 0 && (
                        <GeliaPaginacion paginator={registros} onIrAPagina={irAPagina} embedded />
                    )}
                </div>
            </div>

            <ModalFormIncidencia
                abierto={modalAbierto}
                onCerrar={() => { setModalAbierto(false); setRegistroEditando(null); }}
                registro={registroEditando}
                colaboradores={colaboradores}
                tiposFalta={tiposFalta}
            />
        </AppLayout>
    );
}
