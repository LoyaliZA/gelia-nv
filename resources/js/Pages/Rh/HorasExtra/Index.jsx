import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Clock, Plus, Eye, Pencil } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoMoneda, formatearHora, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import FiltrosHorasExtra from './Partials/FiltrosHorasExtra';
import ModalFormHorasExtra from './Partials/ModalFormHorasExtra';
import { ESTADO_PAGO_BADGE, ESTADO_PAGO_LABELS } from './Partials/horasExtraStyles';

function tabFromFiltros(filtros) {
    if (filtros.estado_pago === 'pendiente') return 'PENDIENTES';
    if (filtros.estado_pago === 'programado') return 'PROGRAMADAS';
    return 'TODAS';
}

export default function Index({
    auth,
    registros,
    metricas,
    colaboradores,
    departamentos,
    supervisores,
    configuracion,
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
        if (registro.estado_pago !== 'pendiente') return;
        setRegistroEditando(registro);
        setModalAbierto(true);
    };

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > registros.last_page) return;
        router.get(route('rh.horas_extra.index'), { ...filtros, page: pagina }, { preserveState: true, preserveScroll: true });
    };

    const kpis = [
        { label: 'Registros hoy', value: metricas.registros_hoy, accent: true },
        { label: 'Horas extra a pagar', value: metricas.horas_extra_a_pagar, color: '#3b82f6' },
        { label: 'Pendientes', value: metricas.pendientes_pago, color: '#f59e0b' },
        { label: 'Monto periodo', value: formatoMoneda(metricas.monto_total), color: '#10b981' },
    ];

    return (
        <AppLayout auth={auth}>
            <Head title="Horas Extra | RH" />
            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className={geliaCardClass('p-6 md:p-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6')}>
                    <div>
                        <h1 className="text-2xl sm:text-3xl md:text-4xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                            Registro de <span style={{ color: 'var(--color-primario)' }}>Horas Extra</span>
                        </h1>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                            {registros.total.toLocaleString('es-MX')} registros
                        </p>
                    </div>
                    {puedeCrear && (
                        <button type="button" onClick={abrirNuevo} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Plus className="w-4 h-4" /> Nuevo registro
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
                    <FiltrosHorasExtra
                        filtros={filtros}
                        colaboradores={colaboradores}
                        departamentos={departamentos}
                        tabActiva={tabActiva}
                        onCambiarTab={setTabActiva}
                    />

                    <div className="hidden lg:block overflow-x-auto">
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="border-b theme-border">
                                    {['Folio', 'Fecha', 'Colaborador', 'Horario', 'Total hrs', 'HE a pagar', 'Total MXN', 'Estado', 'Supervisor', ''].map((h) => (
                                        <th key={h} className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {registros.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={10} className="py-16 text-center">
                                            <Clock className="w-10 h-10 mx-auto mb-3 opacity-40 theme-text-muted" />
                                            <p className="theme-text-muted italic text-sm m-0">No hay registros con estos filtros.</p>
                                        </td>
                                    </tr>
                                ) : (
                                    registros.data.map((reg) => (
                                        <tr key={reg.id} className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                                            <td className="px-4 py-4 text-xs font-mono font-bold">{reg.folio}</td>
                                            <td className="px-4 py-4 text-xs">{reg.fecha_turno?.slice?.(0, 10) || reg.fecha_turno}</td>
                                            <td className="px-4 py-4 text-sm font-bold">{nombreCompletoColaborador(reg.colaborador)}</td>
                                            <td className="px-4 py-4 text-xs theme-text-muted">
                                                {formatearHora(reg.hora_entrada)} – {formatearHora(reg.hora_salida)}
                                                {reg.salida_dia_siguiente && <span className="block text-[9px]">+1 día</span>}
                                            </td>
                                            <td className="px-4 py-4 text-sm">{reg.total_horas_laboradas} h</td>
                                            <td className="px-4 py-4 text-sm font-bold">{reg.horas_extra_a_pagar} h</td>
                                            <td className="px-4 py-4 text-sm font-bold">{formatoMoneda(reg.total_economico)}</td>
                                            <td className="px-4 py-4">
                                                <span className={`px-2 py-1 rounded-lg text-[9px] font-black uppercase border ${ESTADO_PAGO_BADGE[reg.estado_pago]}`}>
                                                    {ESTADO_PAGO_LABELS[reg.estado_pago]}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4 text-xs">{reg.supervisor?.name}</td>
                                            <td className="px-4 py-4 text-right whitespace-nowrap">
                                                <Link href={route('rh.horas_extra.show', reg.id)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase mr-3" style={{ color: 'var(--color-primario)' }}>
                                                    <Eye className="w-3.5 h-3.5" /> Ver
                                                </Link>
                                                {puedeEditar && reg.estado_pago === 'pendiente' && (
                                                    <button type="button" onClick={() => abrirEditar(reg)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase theme-text-muted">
                                                        <Pencil className="w-3.5 h-3.5" /> Editar
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
            </div>

            <ModalFormHorasExtra
                abierto={modalAbierto}
                onCerrar={() => { setModalAbierto(false); setRegistroEditando(null); }}
                registro={registroEditando}
                colaboradores={colaboradores}
                supervisores={supervisores}
                configuracion={configuracion}
            />
        </AppLayout>
    );
}
