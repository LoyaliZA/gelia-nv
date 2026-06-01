import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Wallet, Plus, Eye, Pencil, Zap } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import FiltrosPrestamos from './Partials/FiltrosPrestamos';
import ModalFormPrestamo from './Partials/ModalFormPrestamo';
import {
    ESTADO_PRESTAMO_BADGE, ESTADO_PRESTAMO_LABELS, MODALIDAD_BADGE, MODALIDAD_LABELS, TAB_ESTADO_MAP,
} from './Partials/prestamosStyles';

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
    configuracion,
    filtros,
    puedeCrear,
    puedeEditar,
    puedeGenerar,
}) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [registroEditando, setRegistroEditando] = useState(null);
    const [tabActiva, setTabActiva] = useState(() => tabFromFiltros(filtros));

    const abrirNuevo = () => {
        setRegistroEditando(null);
        setModalAbierto(true);
    };

    const abrirEditar = (registro) => {
        setRegistroEditando(registro);
        setModalAbierto(true);
    };

    const generarCuotas = () => {
        if (!window.confirm('¿Generar cuotas del periodo para todos los convenios activos elegibles?')) return;
        router.post(route('rh.prestamos.generar_cuotas'), {}, { preserveScroll: true });
    };

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > registros.last_page) return;
        router.get(route('rh.prestamos.index'), { ...filtros, page: pagina }, { preserveState: true, preserveScroll: true });
    };

    const kpis = [
        { label: 'Activos', value: metricas.activos, accent: true },
        { label: 'Pausados', value: metricas.pausados, color: '#f59e0b' },
        { label: 'Liquidados', value: metricas.liquidados, color: '#64748b' },
        { label: 'Cuota activa / periodo', value: formatoMoneda(metricas.monto_cuota_activo), color: '#10b981' },
    ];

    return (
        <AppLayout auth={auth}>
            <Head title="Préstamos y Pagos Fijos | RH" />
            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className={geliaCardClass('p-6 md:p-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6')}>
                    <div>
                        <h1 className="text-2xl sm:text-3xl md:text-4xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                            Préstamos y <span style={{ color: 'var(--color-primario)' }}>Pagos Fijos</span>
                        </h1>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                            {registros.total.toLocaleString('es-MX')} convenios registrados
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {puedeGenerar && (
                            <button type="button" onClick={generarCuotas} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase theme-element theme-border border flex items-center gap-2">
                                <Zap className="w-4 h-4" /> Generar cuotas del periodo
                            </button>
                        )}
                        {puedeCrear && (
                            <button type="button" onClick={abrirNuevo} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Plus className="w-4 h-4" /> Nuevo convenio
                            </button>
                        )}
                    </div>
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
                    <FiltrosPrestamos
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
                                    {['Folio', 'Colaborador', 'Concepto', 'Cuota', 'Pagos', 'Modalidad', 'Estado', ''].map((h) => (
                                        <th key={h} className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {registros.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-16 text-center">
                                            <Wallet className="w-10 h-10 mx-auto mb-3 opacity-40 theme-text-muted" />
                                            <p className="theme-text-muted italic text-sm m-0">No hay convenios con estos filtros.</p>
                                        </td>
                                    </tr>
                                ) : (
                                    registros.data.map((reg) => (
                                        <tr key={reg.id} className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                                            <td className="px-4 py-4 text-xs font-mono font-bold">{reg.folio}</td>
                                            <td className="px-4 py-4 text-sm font-bold">{nombreCompletoColaborador(reg.colaborador)}</td>
                                            <td className="px-4 py-4 text-xs max-w-[200px] truncate">{reg.concepto}</td>
                                            <td className="px-4 py-4 text-xs">{formatoMoneda(reg.monto_cuota)}</td>
                                            <td className="px-4 py-4 text-xs">
                                                {reg.pagos_realizados}{reg.num_pagos_total ? ` / ${reg.num_pagos_total}` : ' / ∞'}
                                            </td>
                                            <td className="px-4 py-4">
                                                <span className={`px-2 py-1 rounded-lg text-[9px] font-black uppercase border ${MODALIDAD_BADGE[reg.modalidad]}`}>
                                                    {MODALIDAD_LABELS[reg.modalidad]}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4">
                                                <span className={`px-2 py-1 rounded-lg text-[9px] font-black uppercase border ${ESTADO_PRESTAMO_BADGE[reg.estado]}`}>
                                                    {ESTADO_PRESTAMO_LABELS[reg.estado]}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="flex gap-2">
                                                    <Link href={route('rh.prestamos.show', reg.id)} className="p-2 rounded-xl theme-element theme-border border">
                                                        <Eye className="w-4 h-4" />
                                                    </Link>
                                                    {puedeEditar && !['liquidado', 'cancelado'].includes(reg.estado) && (
                                                        <button type="button" onClick={() => abrirEditar(reg)} className="p-2 rounded-xl theme-element theme-border border">
                                                            <Pencil className="w-4 h-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {registros.last_page > 1 && (
                        <div className="p-4 border-t theme-border">
                            <GeliaPaginacion paginaActual={registros.current_page} ultimaPagina={registros.last_page} onCambiarPagina={irAPagina} />
                        </div>
                    )}
                </div>
            </div>

            <ModalFormPrestamo
                abierto={modalAbierto}
                onCerrar={() => setModalAbierto(false)}
                registro={registroEditando}
                colaboradores={colaboradores}
                configuracion={configuracion}
                puedeEditar={puedeEditar}
            />
        </AppLayout>
    );
}
