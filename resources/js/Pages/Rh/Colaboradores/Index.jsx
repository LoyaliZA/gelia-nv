import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Users, Plus, Eye, Pencil, Copy, Check } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import FiltrosColaboradores from './Partials/FiltrosColaboradores';
import ModalFormColaborador from './Partials/ModalFormColaborador';

export default function Index({
    auth,
    colaboradores,
    departamentos,
    puestos,
    usuarios,
    configuracion,
    filtros,
    puedeCrear,
    puedeEditar,
    puedeVincular,
}) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [colaboradorEditando, setColaboradorEditando] = useState(null);
    const [uuidCopiado, setUuidCopiado] = useState(null);

    const abrirNuevo = () => {
        setColaboradorEditando(null);
        setModalAbierto(true);
    };

    const abrirEditar = (colaborador) => {
        setColaboradorEditando(colaborador);
        setModalAbierto(true);
    };

    const cerrarModal = () => {
        setModalAbierto(false);
        setColaboradorEditando(null);
    };

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > colaboradores.last_page) return;
        router.get(route('rh.colaboradores.index'), { ...filtros, page: pagina }, { preserveState: true, preserveScroll: true });
    };

    const copiarUuid = async (uuid) => {
        try {
            await navigator.clipboard.writeText(uuid);
            setUuidCopiado(uuid);
            setTimeout(() => setUuidCopiado(null), 2000);
        } catch {
            // ignore
        }
    };

    const cardClass = geliaCardClass('overflow-hidden');

    return (
        <AppLayout auth={auth}>
            <Head title="Recursos Humanos — Colaboradores" />
            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className={geliaCardClass('p-6 md:p-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6')}>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>Módulo RH</p>
                        </div>
                        <h1 className="text-2xl sm:text-3xl md:text-5xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                            Perfil de <span style={{ color: 'var(--color-primario)' }}>Colaboradores</span>
                        </h1>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                            {colaboradores.total.toLocaleString('es-MX')} registros · Periodo de pago: {configuracion.dias_periodo_pago} días
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {puedeCrear && (
                            <button
                                type="button"
                                onClick={abrirNuevo}
                                className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Plus className="w-4 h-4" /> Nuevo colaborador
                            </button>
                        )}
                    </div>
                </header>

                <RhSubNav />

                <div className={cardClass}>
                    <FiltrosColaboradores filtros={filtros} departamentos={departamentos} puestos={puestos} />

                    <div className="hidden lg:block overflow-x-auto">
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="border-b theme-border">
                                    <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Folio</th>
                                    <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Colaborador</th>
                                    <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Organización</th>
                                    <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Puesto</th>
                                    <th className="px-4 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Salario Base</th>
                                    <th className="px-4 py-4 text-center text-[9px] font-black uppercase tracking-widest theme-text-muted">Cuenta</th>
                                    <th className="px-4 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {colaboradores.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-16 text-center">
                                            <Users className="w-10 h-10 mx-auto mb-3 opacity-40 theme-text-muted" />
                                            <p className="theme-text-muted italic text-sm m-0">No hay colaboradores con estos filtros.</p>
                                        </td>
                                    </tr>
                                ) : (
                                    colaboradores.data.map((colaborador) => (
                                        <tr key={colaborador.id} className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                                            <td className="px-4 py-4">
                                                <p className="text-xs font-mono font-bold theme-text-main m-0">{colaborador.folio}</p>
                                                <button
                                                    type="button"
                                                    onClick={() => copiarUuid(colaborador.uuid)}
                                                    className="text-[9px] font-mono theme-text-muted hover:theme-text-main flex items-center gap-1 mt-1"
                                                    title={colaborador.uuid}
                                                >
                                                    {colaborador.uuid.slice(0, 8)}…
                                                    {uuidCopiado === colaborador.uuid ? <Check className="w-3 h-3 text-emerald-500" /> : <Copy className="w-3 h-3" />}
                                                </button>
                                            </td>
                                            <td className="px-4 py-4">
                                                <p className="text-sm font-bold theme-text-main m-0">{nombreCompletoColaborador(colaborador)}</p>
                                                <span className={`text-[9px] font-black uppercase ${colaborador.activo ? 'text-emerald-500' : 'text-red-500'}`}>
                                                    {colaborador.activo ? 'Activo' : 'Inactivo'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4 text-xs theme-text-muted">
                                                {colaborador.departamento?.nombre || '—'}
                                                {colaborador.area && <span className="block">{colaborador.area.nombre}</span>}
                                            </td>
                                            <td className="px-4 py-4 text-xs theme-text-main font-bold">{colaborador.puesto?.nombre || '—'}</td>
                                            <td className="px-4 py-4 text-right text-sm font-bold theme-text-main">{formatoMoneda(colaborador.salario_base)}</td>
                                            <td className="px-4 py-4 text-center">
                                                <span className={`px-2 py-1 rounded-lg text-[9px] font-black uppercase ${colaborador.user_id ? 'bg-blue-500/10 text-blue-500' : 'bg-zinc-500/10 theme-text-muted'}`}>
                                                    {colaborador.user_id ? 'Vinculada' : 'Sin cuenta'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4 text-right whitespace-nowrap">
                                                <Link href={route('rh.colaboradores.show', colaborador.id)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase mr-3" style={{ color: 'var(--color-primario)' }}>
                                                    <Eye className="w-3.5 h-3.5" /> Ver
                                                </Link>
                                                {puedeEditar && (
                                                    <button type="button" onClick={() => abrirEditar(colaborador)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main">
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

                    <div className="lg:hidden p-4 space-y-4">
                        {colaboradores.data.map((colaborador) => (
                            <div key={colaborador.id} className="p-4 rounded-2xl theme-element border theme-border space-y-3">
                                <div className="flex justify-between items-start gap-3">
                                    <div>
                                        <p className="text-xs font-mono font-bold theme-text-main m-0">{colaborador.folio}</p>
                                        <p className="text-sm font-bold theme-text-main m-0 mt-1">{nombreCompletoColaborador(colaborador)}</p>
                                    </div>
                                    <span className="text-sm font-bold">{formatoMoneda(colaborador.salario_base)}</span>
                                </div>
                                <p className="text-xs theme-text-muted m-0">{colaborador.departamento?.nombre}{colaborador.area ? ` · ${colaborador.area.nombre}` : ''}</p>
                                <div className="flex gap-3">
                                    <Link href={route('rh.colaboradores.show', colaborador.id)} className="text-[10px] font-black uppercase" style={{ color: 'var(--color-primario)' }}>Ver ficha</Link>
                                    {puedeEditar && (
                                        <button type="button" onClick={() => abrirEditar(colaborador)} className="text-[10px] font-black uppercase theme-text-muted">Editar</button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    {colaboradores.data.length > 0 && (
                        <GeliaPaginacion paginator={colaboradores} onIrAPagina={irAPagina} embedded />
                    )}
                </div>
            </div>

            <ModalFormColaborador
                abierto={modalAbierto}
                onCerrar={cerrarModal}
                colaborador={colaboradorEditando}
                departamentos={departamentos}
                puestos={puestos}
                usuarios={usuarios}
                configuracion={configuracion}
                puedeVincular={puedeVincular}
            />
        </AppLayout>
    );
}
