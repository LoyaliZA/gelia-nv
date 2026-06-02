import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { TimerReset, Plus, Pencil, Trash2, CheckCircle2 } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import RhPageHeader from '../Partials/RhPageHeader';
import FiltrosBancoTiempo from './Partials/FiltrosBancoTiempo';
import ModalFormBancoTiempo from './Partials/ModalFormBancoTiempo';
import { ESTADO_BT_BADGE, ESTADO_BT_LABELS, TAB_ESTADO_MAP } from './Partials/bancoTiempoStyles';
import { rhBadgeClass } from '../rhModuleStyles';

function tabFromFiltros(filtros) {
    const entry = Object.entries(TAB_ESTADO_MAP).find(([, v]) => v === (filtros.estado || ''));
    return entry ? entry[0] : 'TODAS';
}

export default function Index({
    auth,
    registros,
    metricas,
    colaboradores,
    departamentos,
    filtros,
    puedeCrear,
    puedeEditar,
    puedeSaldar,
    puedeEliminar,
}) {
    const [modalAbierto, setModalAbierto]     = useState(false);
    const [registroActivo, setRegistroActivo] = useState(null);
    const [modoModal, setModoModal]           = useState('crear');
    const [tabActiva, setTabActiva]           = useState(() => tabFromFiltros(filtros));

    const abrirNuevo = () => {
        setRegistroActivo(null);
        setModoModal('crear');
        setModalAbierto(true);
    };

    const abrirEditar = (reg) => {
        setRegistroActivo(reg);
        setModoModal('editar');
        setModalAbierto(true);
    };

    const abrirSaldar = (reg) => {
        setRegistroActivo(reg);
        setModoModal('saldar');
        setModalAbierto(true);
    };

    const cerrarModal = () => {
        setModalAbierto(false);
        setRegistroActivo(null);
    };

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > registros.last_page) return;
        router.get(route('rh.banco_tiempo.index'), { ...filtros, page: pagina }, { preserveState: true, preserveScroll: true });
    };

    const handleEliminar = (reg) => {
        if (!window.confirm(`¿Eliminar la deuda ${reg.folio}? Esta acción es irreversible.`)) return;
        router.delete(route('rh.banco_tiempo.destroy', reg.id), { preserveScroll: true });
    };

    const kpis = [
        { label: 'Deudas activas', value: metricas.activas, accent: true },
        { label: 'Horas pendientes', value: `${metricas.horas_pendientes} hrs`, color: '#f59e0b' },
        { label: 'Registros saldados', value: metricas.saldadas, color: '#10b981' },
        { label: 'Total de registros', value: metricas.total, color: '#64748b' },
    ];

    const COLS = ['Folio', 'Colaborador', 'Horas Pendientes', 'Origen de la Deuda', 'Estado', 'Fecha Acuerdo', 'Fecha Devolución', ''];

    return (
        <AppLayout auth={auth}>
            <Head title="Banco de Tiempo Laboral | RH" />
            <GeliaPageShell className="space-y-8 relative">
                <RhPageHeader
                    title="Banco de"
                    titleHighlight="Tiempo Laboral"
                    description={`${registros.total.toLocaleString('es-MX')} registros · cuenta corriente de horas`}
                    icon={TimerReset}
                    aside={
                        puedeCrear ? (
                            <button
                                type="button"
                                onClick={abrirNuevo}
                                className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center justify-center gap-2 animate-pulse-subtle"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Plus className="w-4 h-4" /> Registrar deuda de tiempo
                            </button>
                        ) : null
                    }
                />

                <RhSubNav />

                {/* ── KPIs */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {kpis.map(({ label, value, color, accent }) => (
                        <div key={label} className={geliaCardClass('p-5 transition-transform hover:scale-[1.01]')}>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">{label}</p>
                            <p className="text-xl font-black italic m-0" style={accent ? { color: 'var(--color-primario)' } : { color }}>{value}</p>
                        </div>
                    ))}
                </div>

                {/* ── Tabla */}
                <div className={geliaCardClass('overflow-hidden')}>
                    <FiltrosBancoTiempo
                        filtros={filtros}
                        colaboradores={colaboradores}
                        departamentos={departamentos}
                        tabActiva={tabActiva}
                        onCambiarTab={setTabActiva}
                    />

                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="border-b theme-border">
                                    {COLS.map((h) => (
                                        <th key={h} className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted whitespace-nowrap">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {registros.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={COLS.length} className="py-16 text-center">
                                            <TimerReset className="w-10 h-10 mx-auto mb-3 opacity-40 theme-text-muted" />
                                            <p className="theme-text-muted italic text-sm m-0">
                                                No hay registros de banco de tiempo con estos filtros.
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    registros.data.map((reg) => (
                                        <tr key={reg.id} className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                            <td className="px-4 py-4 text-xs font-mono font-bold whitespace-nowrap">{reg.folio}</td>
                                            <td className="px-4 py-4 text-xs">
                                                <span className="font-bold block theme-text-main">{nombreCompletoColaborador(reg.colaborador)}</span>
                                                <span className="text-[9px] theme-text-muted uppercase font-black block">
                                                    {reg.colaborador?.departamento?.nombre || '—'} · {reg.colaborador?.area?.nombre || '—'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4 text-xs font-black text-center">
                                                <span
                                                    className="text-lg"
                                                    style={{ color: reg.estado === 'activa' ? '#f59e0b' : '#10b981' }}
                                                >
                                                    {reg.horas_pendientes}
                                                </span>
                                                <span className="text-[9px] theme-text-muted block">hrs</span>
                                            </td>
                                            <td className="px-4 py-4 text-xs theme-text-muted max-w-[280px]">
                                                <p className="line-clamp-2 m-0">{reg.origen_deuda}</p>
                                            </td>
                                            <td className="px-4 py-4">
                                                <span className={`px-2.5 py-1 rounded-lg text-[9px] font-black uppercase border ${ESTADO_BT_BADGE[reg.estado] || ''}`}>
                                                    {ESTADO_BT_LABELS[reg.estado] || reg.estado}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4 text-xs font-mono whitespace-nowrap">
                                                {reg.fecha_acuerdo?.slice?.(0, 10) || reg.fecha_acuerdo || '—'}
                                            </td>
                                            <td className="px-4 py-4 text-xs font-mono whitespace-nowrap">
                                                {reg.fecha_devolucion
                                                    ? <span className="text-emerald-600 font-bold">{reg.fecha_devolucion?.slice?.(0, 10) || reg.fecha_devolucion}</span>
                                                    : <span className={rhBadgeClass('amber')}>Pendiente</span>
                                                }
                                            </td>
                                            <td className="px-4 py-4 text-right whitespace-nowrap space-x-3">
                                                {puedeSaldar && reg.estado === 'activa' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => abrirSaldar(reg)}
                                                        className="inline-flex items-center gap-1 text-[10px] font-black uppercase text-emerald-600 hover:text-emerald-700"
                                                    >
                                                        <CheckCircle2 className="w-3.5 h-3.5" /> Saldar
                                                    </button>
                                                )}
                                                {puedeEditar && reg.estado === 'activa' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => abrirEditar(reg)}
                                                        className="inline-flex items-center gap-1 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main"
                                                    >
                                                        <Pencil className="w-3.5 h-3.5" /> Editar
                                                    </button>
                                                )}
                                                {puedeEliminar && reg.estado === 'activa' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleEliminar(reg)}
                                                        className="inline-flex items-center gap-1 text-[10px] font-black uppercase text-red-500 hover:text-red-700"
                                                    >
                                                        <Trash2 className="w-3.5 h-3.5" /> Eliminar
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {registros.data.length > 0 && (
                        <GeliaPaginacion paginator={registros} onIrAPagina={irAPagina} embedded />
                    )}
                </div>
            </GeliaPageShell>

            <ModalFormBancoTiempo
                abierto={modalAbierto}
                onCerrar={cerrarModal}
                registro={registroActivo}
                modo={modoModal}
                colaboradores={colaboradores}
                puedeEditar={puedeEditar}
            />
        </AppLayout>
    );
}
