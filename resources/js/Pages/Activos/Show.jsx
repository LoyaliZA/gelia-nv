import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, User, UserMinus, UserPlus, ArrowRightLeft, Wrench, Ban, Edit2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import DynamicActivoFields from './Partials/DynamicActivoFields';
import TimelineMovimientos, { HistorialAsignaciones } from './Partials/TimelineMovimientos';
import ModalFormActivo from './Partials/ModalFormActivo';
import ModalAsignacion from './Partials/ModalAsignacion';
import ModalTransferencia from './Partials/ModalTransferencia';
import ModalCambioEstado from './Partials/ModalCambioEstado';
import ModalMantenimiento from './Partials/ModalMantenimiento';
import PanelMantenimiento from './Partials/PanelMantenimiento';
import GaleriaFotosActivo from './Partials/GaleriaFotosActivo';
import GeliaLoader from '../../Components/GeliaLoader';
import { ESTADO_BADGE, ESTADO_LABELS, ESTILOS_PAGINA, getActivosCardClass } from './Partials/activosFormStyles';

function IdentificacionChips({ atributos = {} }) {
    const chips = [
        atributos.marca && { label: 'Marca', value: atributos.marca },
        atributos.modelo && { label: 'Modelo', value: atributos.modelo },
        atributos.serial && { label: 'Serial', value: atributos.serial },
    ].filter(Boolean);

    if (!chips.length) return null;

    return (
        <div className="flex flex-wrap gap-2 mt-3">
            {chips.map((chip) => (
                <span key={chip.label} className="px-3 py-1 rounded-xl text-[10px] font-black uppercase theme-element border theme-border">
                    <span className="theme-text-muted">{chip.label}: </span>
                    <span className="theme-text-main">{chip.value}</span>
                </span>
            ))}
        </div>
    );
}

export default function Show({ auth, activo, tipos, departamentos }) {
    const [modalEditar, setModalEditar] = useState(false);
    const [modalAsignar, setModalAsignar] = useState(false);
    const [modalTransferir, setModalTransferir] = useState(false);
    const [modalEstado, setModalEstado] = useState(null);
    const [modalMantenimiento, setModalMantenimiento] = useState(false);
    const [procesando, setProcesando] = useState(false);

    const can = (permiso) => {
        const roles = auth?.user?.roles || [];
        const isAdmin = roles.includes('Admin') || roles.includes('Super admin (admin)') || roles.includes('Super Admin');
        return auth?.user?.permissions?.includes(permiso) || isAdmin;
    };

    const devolver = () => {
        if (!confirm('¿Devolver este activo al inventario disponible?')) return;
        setProcesando(true);
        router.post(route('activos.devolver', activo.id), {}, { onFinish: () => setProcesando(false) });
    };

    const cardClass = (extra = '') => getActivosCardClass({ extra: `p-6 space-y-4 ${extra}`.trim() });

    return (
        <AppLayout auth={auth}>
            <Head title={`${activo.folio} | Activos`} />
            <style>{ESTILOS_PAGINA}</style>
            <GeliaLoader isVisible={procesando} message="Procesando_" />

            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6">
                <Link
                    href={route('activos.index')}
                    className="inline-flex items-center gap-2 text-sm font-black uppercase theme-text-main theme-surface rounded-xl px-4 py-2 border theme-border hover:opacity-90 transition-opacity animate-page-reveal"
                >
                    <ArrowLeft className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Volver al listado
                </Link>

                <header className={getActivosCardClass({ extra: 'p-6 md:p-10' })}>
                    <div className="flex flex-col lg:flex-row gap-6">
                        <div className="lg:w-1/3">
                            <GaleriaFotosActivo
                                fotosExistentes={activo.fotos || []}
                                activoId={activo.id}
                                editable={can('activos.editar')}
                                maxFotos={5}
                                modo="directo"
                                variant="hero"
                            />
                        </div>
                        <div className="flex-1 flex flex-col md:flex-row justify-between gap-4">
                            <div>
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">{activo.folio}</p>
                                <h1 className="text-3xl font-black italic uppercase theme-text-main mt-1">{activo.nombre}</h1>
                                <div className="flex flex-wrap gap-2 mt-3">
                                    <span className={`px-2 py-1 rounded-lg text-[10px] font-black uppercase ${ESTADO_BADGE[activo.estado] || ''}`}>
                                        {ESTADO_LABELS[activo.estado] || activo.estado}
                                    </span>
                                    <span className="px-2 py-1 rounded-lg text-[10px] font-black uppercase theme-element border theme-border">{activo.tipo?.nombre}</span>
                                    <span className="px-2 py-1 rounded-lg text-[10px] font-black uppercase theme-element border theme-border">{activo.departamento?.nombre}</span>
                                </div>
                                <IdentificacionChips atributos={activo.atributos} />
                            </div>

                            <div className="flex flex-wrap gap-2 content-start">
                                {can('activos.editar') && activo.estado !== 'baja' && (
                                    <button type="button" onClick={() => setModalEditar(true)} className="flex items-center gap-1 px-3 py-2 rounded-xl theme-element border theme-border text-[10px] font-black uppercase theme-text-main">
                                        <Edit2 className="w-3 h-3" /> Editar
                                    </button>
                                )}
                                {can('activos.asignar') && activo.estado !== 'baja' && activo.estado !== 'mantenimiento' && (
                                    <button type="button" onClick={() => setModalAsignar(true)} className="flex items-center gap-1 px-3 py-2 rounded-xl text-[10px] font-black uppercase text-white" style={{ backgroundColor: 'var(--color-primario)' }}>
                                        <UserPlus className="w-3 h-3" /> {activo.responsable ? 'Reasignar' : 'Asignar'}
                                    </button>
                                )}
                                {can('activos.asignar') && activo.estado === 'asignado' && (
                                    <button type="button" onClick={devolver} className="flex items-center gap-1 px-3 py-2 rounded-xl theme-element border theme-border text-[10px] font-black uppercase theme-text-main">
                                        <UserMinus className="w-3 h-3" /> Devolver
                                    </button>
                                )}
                                {can('activos.transferir') && activo.estado !== 'baja' && (
                                    <button type="button" onClick={() => setModalTransferir(true)} className="flex items-center gap-1 px-3 py-2 rounded-xl theme-element border theme-border text-[10px] font-black uppercase theme-text-main">
                                        <ArrowRightLeft className="w-3 h-3" /> Transferir
                                    </button>
                                )}
                                {can('activos.cambiar_estado') && activo.estado !== 'baja' && activo.estado !== 'mantenimiento' && (
                                    <button type="button" onClick={() => setModalMantenimiento(true)} className="flex items-center gap-1 px-3 py-2 rounded-xl theme-element border theme-border text-[10px] font-black uppercase theme-text-main">
                                        <Wrench className="w-3 h-3" /> Mantenimiento
                                    </button>
                                )}
                                {can('activos.cambiar_estado') && activo.estado !== 'baja' && (
                                    <button type="button" onClick={() => setModalEstado('baja')} className="flex items-center gap-1 px-3 py-2 rounded-xl bg-red-600 text-white text-[10px] font-black uppercase">
                                        <Ban className="w-3 h-3" /> Baja
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                </header>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className={cardClass('lg:col-span-1')} style={{ animationDelay: '100ms' }}>
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-muted">Pertenece a</h2>
                        {activo.responsable ? (
                            <div className="flex items-center gap-3 rounded-xl p-4 theme-element border theme-border">
                                <User className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                <div>
                                    <Link href={route('activos.index', { responsable_user_id: activo.responsable.id })} className="text-lg font-black hover:underline" style={{ color: 'var(--color-primario)' }}>
                                        {activo.responsable.name}
                                    </Link>
                                    <p className="text-xs theme-text-muted">{activo.responsable.email}</p>
                                </div>
                            </div>
                        ) : (
                            <p className="text-sm theme-text-muted italic">Sin usuario asignado — disponible en inventario.</p>
                        )}
                        <HistorialAsignaciones asignaciones={activo.asignaciones} />
                    </div>

                    <div className={cardClass('lg:col-span-1')} style={{ animationDelay: '150ms' }}>
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-muted">Detalle</h2>
                        {activo.descripcion && <p className="text-sm theme-text-main">{activo.descripcion}</p>}
                        <div className="grid grid-cols-2 gap-3 text-sm">
                            {activo.fecha_adquisicion && (
                                <div><span className="text-[10px] font-black uppercase theme-text-muted block">Adquisición</span><span className="theme-text-main">{String(activo.fecha_adquisicion).substring(0, 10)}</span></div>
                            )}
                            {activo.fecha_vencimiento && (
                                <div><span className="text-[10px] font-black uppercase theme-text-muted block">Vencimiento</span><span className="theme-text-main">{String(activo.fecha_vencimiento).substring(0, 10)}</span></div>
                            )}
                            {activo.valor && (
                                <div><span className="text-[10px] font-black uppercase theme-text-muted block">Valor</span><span className="theme-text-main">${Number(activo.valor).toLocaleString('es-MX')}</span></div>
                            )}
                        </div>
                        <DynamicActivoFields
                            fields={activo.tipo?.esquema_atributos?.fields || []}
                            values={activo.atributos || {}}
                            onChange={() => {}}
                            readOnly
                            tipoActivoId={activo.catalogo_tipo_activo_id}
                        />
                    </div>

                    <PanelMantenimiento
                        activo={activo}
                        canCambiarEstado={can('activos.cambiar_estado')}
                        onProgramar={() => setModalMantenimiento(true)}
                    />
                </div>

                <div className={getActivosCardClass({ extra: 'p-6' })} style={{ animationDelay: '250ms' }}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-muted mb-4">Bitácora</h2>
                    <TimelineMovimientos movimientos={activo.movimientos} mantenimientos={activo.mantenimientos} />
                </div>
            </div>

            <ModalFormActivo abierto={modalEditar} onCerrar={() => setModalEditar(false)} tipos={tipos} departamentos={departamentos} activo={activo} />
            <ModalAsignacion abierto={modalAsignar} onCerrar={() => setModalAsignar(false)} activo={activo} />
            <ModalTransferencia abierto={modalTransferir} onCerrar={() => setModalTransferir(false)} activo={activo} departamentos={departamentos} />
            <ModalCambioEstado abierto={!!modalEstado} onCerrar={() => setModalEstado(null)} activo={activo} estadoDestino={modalEstado} />
            <ModalMantenimiento abierto={modalMantenimiento} onCerrar={() => setModalMantenimiento(false)} activo={activo} />
        </AppLayout>
    );
}
