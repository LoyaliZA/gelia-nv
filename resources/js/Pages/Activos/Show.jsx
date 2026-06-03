import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { navegarListadoActivos } from './Partials/navegarListadoActivos';
import { ArrowLeft, User, UserMinus, UserPlus, ArrowRightLeft, Wrench, Ban, Edit2, MoreHorizontal, ChevronDown, ChevronUp, Eye } from 'lucide-react';
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
import QrActivo from './Partials/QrActivo';
import AccionesActivoSheet from './Partials/AccionesActivoSheet';
import useDispositivoCampo from './Partials/useDispositivoCampo';
import GeliaLoader from '../../Components/GeliaLoader';
import ModalFirmarActivo from './Partials/ModalFirmarActivo';
import ModalVistaPreviaResponsiva from './Partials/ModalVistaPreviaResponsiva';
import { ESTADO_BADGE, ESTADO_LABELS, METADATA_BADGE, CHIP_BADGE, getActivosCardClass } from './Partials/activosFormStyles';

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
                <span key={chip.label} className={CHIP_BADGE}>
                    <span className="opacity-70">{chip.label}: </span>
                    {chip.value}
                </span>
            ))}
        </div>
    );
}

function SeccionAcordeon({ titulo, children, defaultAbierto = false, className = '' }) {
    const [abierto, setAbierto] = useState(defaultAbierto);

    return (
        <div className={`${getActivosCardClass(`overflow-hidden ${className}`.trim())} lg:hidden`}>
            <button
                type="button"
                onClick={() => setAbierto((v) => !v)}
                className="w-full flex items-center justify-between p-5 text-left"
            >
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-muted m-0">{titulo}</h2>
                {abierto ? <ChevronUp className="w-4 h-4 theme-text-muted" /> : <ChevronDown className="w-4 h-4 theme-text-muted" />}
            </button>
            {abierto && <div className="px-5 pb-5 space-y-4 border-t theme-border pt-4">{children}</div>}
        </div>
    );
}

export default function Show({ auth, activo, tipos, categorias = [], departamentos, terminosCondiciones }) {
    const [modalEditar, setModalEditar] = useState(false);
    const [modalAsignar, setModalAsignar] = useState(false);
    const [modalTransferir, setModalTransferir] = useState(false);
    const [modalEstado, setModalEstado] = useState(null);
    const [modalMantenimiento, setModalMantenimiento] = useState(false);
    const [modalFirmar, setModalFirmar] = useState(null);
    const [previewResponsiva, setPreviewResponsiva] = useState(null);
    const [sheetAcciones, setSheetAcciones] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const { esMovil } = useDispositivoCampo();

    const can = (permiso) => {
        const roles = auth?.user?.roles || [];
        const isAdmin = roles.includes('Admin') || roles.includes('Super admin (admin)') || roles.includes('Super Admin');
        return auth?.user?.permissions?.includes(permiso) || isAdmin;
    };

    const devolver = () => {
        const condiciones = prompt('¿Confirmas la devolución de este activo? Ingresa las condiciones en las que se regresa (opcional):', 'Excelente estado');
        if (condiciones === null) return;
        setProcesando(true);
        router.post(route('activos.devolver', activo.id), { condiciones_devolucion: condiciones }, { onFinish: () => setProcesando(false) });
    };

    const cardClass = (extra = '') => getActivosCardClass(`p-6 space-y-4 ${extra}`.trim());

    const accionPrimaria = () => {
        if (can('activos.asignar') && activo.estado !== 'baja' && activo.estado !== 'mantenimiento') {
            return { label: activo.responsable ? 'Reasignar' : 'Asignar', onClick: () => setModalAsignar(true), icon: UserPlus };
        }
        if (can('activos.editar') && activo.estado !== 'baja') {
            return { label: 'Editar', onClick: () => setModalEditar(true), icon: Edit2 };
        }
        return null;
    };

    const primaria = accionPrimaria();
    const PrimariaIcon = primaria?.icon;

    const activeAsignacion = activo.asignaciones?.find((a) => a.activa);

    const contenidoPertenece = (
        <>
            {activo.responsable ? (
                <div className="space-y-4">
                    <div className="flex items-center gap-3 rounded-xl p-4 theme-element border theme-border">
                        <User className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <div className="min-w-0 flex-1">
                            <Link href={route('activos.index', { responsable_user_id: activo.responsable.id })} className="text-lg font-black hover:underline block truncate" style={{ color: 'var(--color-primario)' }}>
                                {activo.responsable.name}
                            </Link>
                            <p className="text-xs theme-text-muted truncate">{activo.responsable.email}</p>
                        </div>
                    </div>

                    {activeAsignacion && (
                        <div className="rounded-xl p-4 theme-element border theme-border text-xs space-y-2">
                            {activeAsignacion.condiciones_entrega && (
                                <div>
                                    <span className="text-[9px] font-black uppercase theme-text-muted block">Condiciones de Entrega</span>
                                    <p className="m-0 theme-text-main italic font-medium">"{activeAsignacion.condiciones_entrega}"</p>
                                </div>
                            )}
                            <div className="flex items-center justify-between gap-2 pt-2 border-t theme-border">
                                <div>
                                    <span className="text-[9px] font-black uppercase theme-text-muted block">Estado de Firma</span>
                                    <span className={`inline-block font-black uppercase text-[9px] ${activeAsignacion.firmado ? 'text-green-600' : 'text-amber-600 animate-pulse'}`}>
                                        {activeAsignacion.firmado ? 'Firmado' : 'Firma Pendiente'}
                                    </span>
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setPreviewResponsiva({
                                            previewUrl: route('activos.asignaciones.responsiva_vista_previa', activeAsignacion.id),
                                            downloadUrl: route('activos.asignaciones.responsiva', activeAsignacion.id),
                                            titulo: `Vista previa — ${activo.folio}`,
                                        })}
                                        className="px-2 py-1 bg-blue-50 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400 rounded text-[9px] font-black uppercase tracking-wider hover:opacity-85"
                                    >
                                        <Eye className="w-3 h-3 inline mr-1" />
                                        Vista previa
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setPreviewResponsiva({
                                            previewUrl: route('activos.usuarios.responsiva_conjunta_vista_previa', activeAsignacion.user_id),
                                            downloadUrl: route('activos.usuarios.responsiva_conjunta', activeAsignacion.user_id),
                                            titulo: 'Vista previa — Responsiva completa',
                                        })}
                                        className="px-2 py-1 bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 rounded text-[9px] font-black uppercase tracking-wider hover:opacity-85"
                                        title="Vista previa de todos los activos asignados a este colaborador"
                                    >
                                        Completa
                                    </button>
                                    {!activeAsignacion.firmado && (auth.user?.id === activeAsignacion.user_id || can('activos.asignar')) && (
                                        <button
                                            type="button"
                                            onClick={() => setModalFirmar(activeAsignacion)}
                                            className="px-2 py-1 bg-amber-50 dark:bg-amber-950/20 text-amber-600 dark:text-amber-400 rounded text-[9px] font-black uppercase tracking-wider hover:opacity-85 cursor-pointer"
                                        >
                                            Firmar
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            ) : (
                <p className="text-sm theme-text-muted italic">Sin usuario asignado — disponible en inventario.</p>
            )}
            <HistorialAsignaciones 
                asignaciones={activo.asignaciones} 
                canSign={can('activos.asignar')} 
                onSign={(a) => setModalFirmar(a)}
                onPreviewResponsiva={(a) => setPreviewResponsiva({
                    previewUrl: route('activos.asignaciones.responsiva_vista_previa', a.id),
                    downloadUrl: route('activos.asignaciones.responsiva', a.id),
                    titulo: `Vista previa — ${activo.folio}`,
                })}
                currentUser={auth.user} 
            />
        </>
    );

    const contenidoDetalle = (
        <>
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
            {activo.padre && (
                <div className="rounded-xl p-4 theme-element border theme-border text-sm">
                    <span className="text-[9px] font-black uppercase theme-text-muted block mb-1">Vinculado a</span>
                    <Link href={route('activos.show', activo.padre.id)} className="font-bold hover:underline" style={{ color: 'var(--color-primario)' }}>
                        {activo.padre.folio} — {activo.padre.nombre}
                    </Link>
                </div>
            )}
            {activo.accesorios?.length > 0 && (
                <div className="rounded-xl p-4 theme-element border theme-border text-sm space-y-2">
                    <span className="text-[9px] font-black uppercase theme-text-muted block">Accesorios vinculados</span>
                    <ul className="space-y-2 m-0 p-0 list-none">
                        {activo.accesorios.map((acc) => (
                            <li key={acc.id} className="flex items-center justify-between gap-2">
                                <Link href={route('activos.show', acc.id)} className="font-medium hover:underline truncate" style={{ color: 'var(--color-primario)' }}>
                                    {acc.folio} — {acc.nombre}
                                </Link>
                                <span className={`px-2 py-0.5 rounded text-[9px] font-black uppercase shrink-0 ${ESTADO_BADGE[acc.estado] || ''}`}>
                                    {ESTADO_LABELS[acc.estado] || acc.estado}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            <DynamicActivoFields
                fields={activo.tipo?.esquema_atributos?.fields || []}
                values={activo.atributos || {}}
                onChange={() => {}}
                readOnly
                tipoActivoId={activo.catalogo_tipo_activo_id}
            />
        </>
    );

    return (
        <AppLayout auth={auth}>
            <Head title={`${activo.folio} | Activos`} />
            <GeliaLoader isVisible={procesando} message="Procesando_" />

            <div className={`max-w-[1400px] mx-auto p-4 md:p-8 space-y-6 ${esMovil ? 'pb-44' : ''}`}>
                <button
                    type="button"
                    onClick={() => navegarListadoActivos()}
                    className="inline-flex items-center gap-2 text-sm font-black uppercase theme-text-main theme-surface rounded-xl px-4 py-2 border theme-border hover:opacity-90 transition-opacity animate-page-reveal"
                >
                    <ArrowLeft className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Volver al listado
                </button>

                <header className={getActivosCardClass('p-6 md:p-10')}>
                    <div className="flex flex-col lg:flex-row gap-6">
                        <div className="lg:w-1/3 space-y-4">
                            <GaleriaFotosActivo
                                fotosExistentes={activo.fotos || []}
                                activoId={activo.id}
                                editable={can('activos.editar')}
                                maxFotos={5}
                                modo="directo"
                                variant="hero"
                                modoCapturaCampo
                                colapsada
                            />
                            <QrActivo activo={activo} puedeEditar={can('activos.editar')} compacto={esMovil} />
                        </div>
                        <div className="flex-1 flex flex-col md:flex-row justify-between gap-4">
                            <div>
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">{activo.folio}</p>
                                <h1 className="text-2xl sm:text-3xl font-black italic uppercase theme-text-main mt-1">{activo.nombre}</h1>
                                <div className="flex flex-wrap gap-2 mt-3">
                                    <span className={`px-2 py-1 rounded-lg text-[10px] font-black uppercase ${ESTADO_BADGE[activo.estado] || ''}`}>
                                        {ESTADO_LABELS[activo.estado] || activo.estado}
                                    </span>
                                    <span className={METADATA_BADGE}>{activo.tipo?.nombre}</span>
                                    {activo.categoria?.nombre && <span className={METADATA_BADGE}>{activo.categoria.nombre}</span>}
                                    <span className={METADATA_BADGE}>{activo.departamento?.nombre}</span>
                                </div>
                                <IdentificacionChips atributos={activo.atributos} />
                            </div>

                            <div className="hidden lg:flex flex-wrap gap-2 content-start">
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

                <div className="hidden lg:grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className={cardClass('lg:col-span-1')} style={{ animationDelay: '100ms' }}>
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-muted">Pertenece a</h2>
                        {contenidoPertenece}
                    </div>
                    <div className={cardClass('lg:col-span-1')} style={{ animationDelay: '150ms' }}>
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-muted">Detalle</h2>
                        {contenidoDetalle}
                    </div>
                    <PanelMantenimiento
                        activo={activo}
                        canCambiarEstado={can('activos.cambiar_estado')}
                        onProgramar={() => setModalMantenimiento(true)}
                    />
                </div>

                <SeccionAcordeon titulo="Pertenece a" defaultAbierto>
                    {contenidoPertenece}
                </SeccionAcordeon>
                <SeccionAcordeon titulo="Detalle">
                    {contenidoDetalle}
                </SeccionAcordeon>
                <div className="lg:hidden">
                    <PanelMantenimiento
                        activo={activo}
                        canCambiarEstado={can('activos.cambiar_estado')}
                        onProgramar={() => setModalMantenimiento(true)}
                    />
                </div>

                <div className={getActivosCardClass('p-6')}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-muted mb-4">Bitácora</h2>
                    <TimelineMovimientos movimientos={activo.movimientos} mantenimientos={activo.mantenimientos} />
                </div>
            </div>

            {esMovil && activo.estado !== 'baja' && !sheetAcciones && (
                <div className="activos-mobile-dock">
                    {primaria && (
                        <button type="button" onClick={primaria.onClick} className="activos-mobile-dock__primary">
                            {PrimariaIcon && <PrimariaIcon className="w-4 h-4 shrink-0" />}
                            {primaria.label}
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={() => setSheetAcciones(true)}
                        className="activos-mobile-dock__more"
                        aria-label="Más acciones"
                    >
                        <MoreHorizontal className="w-5 h-5" />
                    </button>
                </div>
            )}

            <AccionesActivoSheet
                abierto={sheetAcciones}
                onCerrar={() => setSheetAcciones(false)}
                activo={activo}
                can={can}
                onEditar={() => setModalEditar(true)}
                onAsignar={() => setModalAsignar(true)}
                onDevolver={devolver}
                onTransferir={() => setModalTransferir(true)}
                onMantenimiento={() => setModalMantenimiento(true)}
                onBaja={() => setModalEstado('baja')}
            />

            <ModalFormActivo abierto={modalEditar} onCerrar={() => setModalEditar(false)} tipos={tipos} categorias={categorias} departamentos={departamentos} activo={activo} />
            <ModalAsignacion abierto={modalAsignar} onCerrar={() => setModalAsignar(false)} activo={activo} />
            <ModalTransferencia abierto={modalTransferir} onCerrar={() => setModalTransferir(false)} activo={activo} departamentos={departamentos} />
            <ModalCambioEstado abierto={!!modalEstado} onCerrar={() => setModalEstado(null)} activo={activo} estadoDestino={modalEstado} />
            <ModalMantenimiento abierto={modalMantenimiento} onCerrar={() => setModalMantenimiento(false)} activo={activo} />
            <ModalFirmarActivo abierto={!!modalFirmar} onCerrar={() => setModalFirmar(null)} asignacion={modalFirmar} terminosCondiciones={terminosCondiciones} />
            <ModalVistaPreviaResponsiva
                abierto={!!previewResponsiva}
                onCerrar={() => setPreviewResponsiva(null)}
                previewUrl={previewResponsiva?.previewUrl}
                downloadUrl={previewResponsiva?.downloadUrl}
                titulo={previewResponsiva?.titulo}
            />
        </AppLayout>
    );
}
