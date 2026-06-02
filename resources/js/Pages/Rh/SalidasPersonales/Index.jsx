import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Clock, Plus, Eye, Pencil, Trash2, LogOut } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import RhPageHeader from '../Partials/RhPageHeader';
import FiltrosSalidasPersonales from './Partials/FiltrosSalidasPersonales';
import ModalFormSalidaPersonal from './Partials/ModalFormSalidaPersonal';
import { getSalidaStatusInfo } from './Partials/salidasStyles';

function tabFromFiltros(filtros) {
    if (filtros.estado_cobro === 'pendiente') return 'PENDIENTES';
    if (filtros.estado_cobro === 'cobrado') return 'COBRADAS';
    return 'TODAS';
}

export default function Index({
    auth,
    registros,
    metricas,
    colaboradores,
    departamentos,
    configuracion,
    filtros,
    puedeCrear,
    puedeEditar,
    puedeEliminar,
}) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [registroEditando, setRegistroEditando] = useState(null);
    const [tabActiva, setTabActiva] = useState(() => tabFromFiltros(filtros));

    const abrirNuevo = () => {
        setRegistroEditando(null);
        setModalAbierto(true);
    };

    const abrirEditar = (registro) => {
        if (registro.fecha_deduccion_nomina !== null) return;
        setRegistroEditando(registro);
        setModalAbierto(true);
    };

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > registros.last_page) return;
        router.get(route('rh.salidas_personales.index'), { ...filtros, page: pagina }, { preserveState: true, preserveScroll: true });
    };

    const handleEliminar = (reg) => {
        if (!window.confirm(`¿Estás seguro de eliminar el registro de salida con folio ${reg.folio}?`)) return;
        router.delete(route('rh.salidas_personales.destroy', reg.id), {
            preserveScroll: true,
        });
    };

    const kpis = [
        { label: 'Salidas hoy', value: metricas.registros_hoy, accent: true },
        { label: 'Tiempo ausente', value: `${metricas.total_minutos} min`, color: '#3b82f6' },
        { label: 'Deducciones totales', value: formatoMoneda(metricas.total_deduccion), color: '#ef4444' },
        { label: 'Por cobrar en nómina', value: `${metricas.pendientes_cobro} (${formatoMoneda(metricas.monto_pendiente_cobro)})`, color: '#f59e0b' },
    ];

    return (
        <AppLayout auth={auth}>
            <Head title="Salidas Personales | RH" />
            <GeliaPageShell className="space-y-8 relative">
                <RhPageHeader
                    title="Registro de"
                    titleHighlight="Salidas Personales"
                    description={`${registros.total.toLocaleString('es-MX')} registros`}
                    icon={LogOut}
                    aside={
                        puedeCrear ? (
                            <button type="button" onClick={abrirNuevo} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center justify-center gap-2 animate-pulse-subtle" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Plus className="w-4 h-4" /> Registrar Salida
                            </button>
                        ) : null
                    }
                />

                <RhSubNav />

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {kpis.map(({ label, value, color, accent }) => (
                        <div key={label} className={geliaCardClass('p-5 transition-transform hover:scale-[1.01]')}>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">{label}</p>
                            <p className="text-xl font-black italic m-0" style={accent ? { color: 'var(--color-primario)' } : { color }}>{value}</p>
                        </div>
                    ))}
                </div>

                <div className={geliaCardClass('overflow-hidden')}>
                    <FiltrosSalidasPersonales
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
                                    {['Folio', 'Fecha', 'Colaborador', 'Motivo', 'Horario Salida', 'Horario Regreso', 'Minutos', 'Salario/min', 'Deducción', 'Estatus', ''].map((h) => (
                                        <th key={h} className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {registros.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={11} className="py-16 text-center">
                                            <Clock className="w-10 h-10 mx-auto mb-3 opacity-40 theme-text-muted" />
                                            <p className="theme-text-muted italic text-sm m-0">No hay registros con estos filtros.</p>
                                        </td>
                                    </tr>
                                ) : (
                                    registros.data.map((reg) => {
                                        const statusInfo = getSalidaStatusInfo(reg);
                                        return (
                                            <tr key={reg.id} className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                                <td className="px-4 py-4 text-xs font-mono font-bold">{reg.folio}</td>
                                                <td className="px-4 py-4 text-xs">{reg.fecha_evento?.slice?.(0, 10) || reg.fecha_evento}</td>
                                                <td className="px-4 py-4 text-xs">
                                                    <span className="font-bold block theme-text-main">{nombreCompletoColaborador(reg.colaborador)}</span>
                                                    <span className="text-[9px] theme-text-muted uppercase font-black block">
                                                        {reg.colaborador?.departamento?.nombre || '—'} · {reg.colaborador?.area?.nombre || '—'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4 text-xs theme-text-muted font-medium">{reg.motivo}</td>
                                                <td className="px-4 py-4 text-xs font-semibold theme-text-main">{reg.hora_salida ? reg.hora_salida.slice(0, 5) : '—'}</td>
                                                <td className="px-4 py-4 text-xs font-semibold theme-text-main">
                                                    {reg.hora_regreso ? reg.hora_regreso.slice(0, 5) : (
                                                        <span className="text-[9px] font-black px-1.5 py-0.5 rounded bg-rose-500/10 text-rose-600 border border-rose-500/20">FUERA</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-4 text-xs font-mono font-bold">{reg.minutos_ausente || 0} min</td>
                                                <td className="px-4 py-4 text-xs font-mono theme-text-muted">{formatoMoneda(reg.salario_por_minuto_snapshot || 0, 4)}</td>
                                                <td className="px-4 py-4 text-xs font-mono font-black text-red-500">-{formatoMoneda(reg.monto_a_deducir || 0)}</td>
                                                <td className="px-4 py-4">
                                                    <span className={`px-2.5 py-1 rounded-lg text-[9px] font-black uppercase ${statusInfo.badgeClass}`}>
                                                        {statusInfo.label}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4 text-right whitespace-nowrap">
                                                    <Link href={route('rh.salidas_personales.show', reg.id)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase mr-4" style={{ color: 'var(--color-primario)' }}>
                                                        <Eye className="w-3.5 h-3.5" /> Ver
                                                    </Link>
                                                    {puedeEditar && reg.fecha_deduccion_nomina === null && (
                                                        <button type="button" onClick={() => abrirEditar(reg)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main mr-4">
                                                            <Pencil className="w-3.5 h-3.5" /> {!reg.hora_regreso ? 'Registrar Regreso' : 'Editar'}
                                                        </button>
                                                    )}
                                                    {puedeEliminar && reg.fecha_deduccion_nomina === null && (
                                                        <button type="button" onClick={() => handleEliminar(reg)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase text-red-500 hover:text-red-700">
                                                            <Trash2 className="w-3.5 h-3.5" /> Eliminar
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {registros.data.length > 0 && (
                        <GeliaPaginacion paginator={registros} onIrAPagina={irAPagina} embedded />
                    )}
                </div>
            </GeliaPageShell>

            <ModalFormSalidaPersonal
                abierto={modalAbierto}
                onCerrar={() => { setModalAbierto(false); setRegistroEditando(null); }}
                registro={registroEditando}
                colaboradores={colaboradores}
                puedeEditar={puedeEditar}
            />
        </AppLayout>
    );
}
