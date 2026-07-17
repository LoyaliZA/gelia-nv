import React, { useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Copy, Link2, MapPin, Pencil, Star, Trash2, X, ClipboardList } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_SELECT,
    THEME_TEXTAREA,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '../../../utils/geliaTheme';
import { labelEstadoSolicitud } from '../Partials/DireccionPedidoResumen';
import { codigoDireccionCliente } from '../Partials/codigoDireccionCliente';

const CAMPOS_VACIOS = {
    nombre_destinatario: '',
    telefono_destinatario: '',
    calle: '',
    numero_exterior: '',
    numero_interior: '',
    colonia: '',
    codigo_postal: '',
    municipio: '',
    ciudad: '',
    estado: '',
    pais: 'México',
    referencias: '',
    indicaciones_entrega: '',
    etiqueta: '',
    tipo_direccion: 'envio',
    verificar: true,
    es_principal: false,
};

function badgeVerificacion(estado) {
    const map = {
        verified: 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30',
        pending: 'bg-amber-500/10 text-amber-700 border-amber-500/30',
        rejected: 'bg-red-500/10 text-red-700 border-red-500/30',
    };
    return `inline-flex px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest border ${map[estado] || 'theme-element theme-border theme-text-muted'}`;
}

function detalleLinea(d) {
    const calle = [
        d.calle,
        d.numero_exterior ? `Ext. ${d.numero_exterior}` : null,
        d.numero_interior ? `Int. ${d.numero_interior}` : null,
    ].filter(Boolean).join(' ');
    const lugar = [
        d.colonia ? `Col. ${d.colonia}` : null,
        d.municipio || d.ciudad || null,
        d.estado || null,
    ].filter(Boolean).join(', ');
    return [calle, lugar].filter(Boolean);
}

export default function FichaCliente({ cliente, direcciones = [], enlaces = [], solicitudes_pendientes = 0 }) {
    const { flash, auth } = usePage().props;
    const can = (p) => auth?.user?.permissions?.includes(p)
        || auth?.user?.roles?.includes('Admin')
        || auth?.user?.roles?.includes('Super Admin')
        || auth?.user?.roles?.includes('Super admin (admin)');

    const [modal, setModal] = useState(null);
    const [editId, setEditId] = useState(null);
    const [copiado, setCopiado] = useState(null);
    const form = useForm({ ...CAMPOS_VACIOS });

    const activas = useMemo(() => (direcciones || []).filter((d) => d.esta_activa), [direcciones]);
    const inactivas = useMemo(() => (direcciones || []).filter((d) => !d.esta_activa), [direcciones]);
    const vigente = useMemo(() => (enlaces || []).filter((e) => e.esta_vigente), [enlaces]);

    const abrirAlta = () => {
        form.setData({ ...CAMPOS_VACIOS });
        form.clearErrors();
        setEditId(null);
        setModal('alta');
    };

    const abrirEditar = (d) => {
        form.setData({
            ...CAMPOS_VACIOS,
            nombre_destinatario: d.nombre_destinatario || '',
            telefono_destinatario: d.telefono_destinatario || '',
            calle: d.calle || '',
            numero_exterior: d.numero_exterior || '',
            numero_interior: d.numero_interior || '',
            colonia: d.colonia || '',
            codigo_postal: d.codigo_postal || '',
            municipio: d.municipio || '',
            ciudad: d.ciudad || '',
            estado: d.estado || '',
            pais: d.pais || 'México',
            referencias: d.referencias || '',
            indicaciones_entrega: d.indicaciones_entrega || '',
            etiqueta: d.etiqueta || '',
            tipo_direccion: d.tipo_direccion || 'envio',
            verificar: true,
            es_principal: Boolean(d.es_principal),
        });
        form.clearErrors();
        setEditId(d.id);
        setModal('editar');
    };

    const cerrarModal = () => {
        setModal(null);
        setEditId(null);
        form.reset();
        form.clearErrors();
    };

    const guardar = (e) => {
        e.preventDefault();
        if (modal === 'editar' && editId) {
            form.put(route('control_pedidos.direcciones.update', [cliente.id, editId]), {
                onSuccess: () => cerrarModal(),
            });
        } else {
            form.post(route('control_pedidos.direcciones.store', cliente.id), {
                onSuccess: () => cerrarModal(),
            });
        }
    };

    const copiarTexto = async (texto, id = 'flash') => {
        if (!texto) return;
        try {
            await navigator.clipboard.writeText(texto);
            setCopiado(id);
            setTimeout(() => setCopiado(null), 2000);
        } catch { /* ignore */ }
    };

    const tarjetaDireccion = (d) => {
        const lineas = detalleLinea(d);
        return (
            <article key={d.id} className={`${geliaCardClass()} p-5 md:p-6 ${!d.esta_activa ? 'opacity-70' : ''}`}>
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2 min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-mono text-sm font-black theme-text-main px-2.5 py-1 rounded-lg border theme-border">
                                {codigoDireccionCliente(cliente.numero_cliente, d.numero_direccion)}
                            </span>
                            {d.etiqueta && (
                                <span className="text-sm font-bold theme-text-main">{d.etiqueta}</span>
                            )}
                            {d.es_principal && (
                                <span
                                    className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest border"
                                    style={{
                                        color: 'var(--color-primario)',
                                        background: 'color-mix(in srgb, var(--color-primario) 12%, transparent)',
                                        borderColor: 'color-mix(in srgb, var(--color-primario) 30%, transparent)',
                                    }}
                                >
                                    <Star className="w-3 h-3" /> Principal
                                </span>
                            )}
                            <span className={badgeVerificacion(d.estado_verificacion)}>
                                {d.estado_verificacion === 'verified' ? 'Verificada' : labelEstadoSolicitud(d.estado_verificacion)}
                            </span>
                            <span className={`inline-flex px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest border ${d.esta_activa ? 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30' : 'theme-element theme-border theme-text-muted'}`}>
                                {d.esta_activa ? 'Activa' : 'Inactiva'}
                            </span>
                        </div>
                        <p className="m-0 font-bold theme-text-main text-base">{d.nombre_destinatario}</p>
                        {d.telefono_destinatario && (
                            <p className="m-0 text-xs theme-text-muted">Tel. {d.telefono_destinatario}</p>
                        )}
                        {lineas.length > 0 ? (
                            <div className="text-sm theme-text-muted space-y-0.5">
                                {lineas.map((l) => (
                                    <p key={l} className="m-0">{l}</p>
                                ))}
                            </div>
                        ) : (
                            <p className="m-0 text-sm theme-text-muted">{d.resumen}</p>
                        )}
                        {d.codigo_postal && (
                            <p className="m-0 text-xs font-mono theme-text-muted">C.P. {d.codigo_postal}</p>
                        )}
                        {(d.referencias || d.indicaciones_entrega) && (
                            <div className="pt-2 border-t theme-border space-y-1">
                                {d.referencias && (
                                    <p className="m-0 text-xs theme-text-muted"><span className="font-bold">Ref:</span> {d.referencias}</p>
                                )}
                                {d.indicaciones_entrega && (
                                    <p className="m-0 text-xs theme-text-muted"><span className="font-bold">Indicaciones:</span> {d.indicaciones_entrega}</p>
                                )}
                            </div>
                        )}
                    </div>
                    {d.esta_activa && (
                        <div className="flex flex-wrap gap-2 shrink-0">
                            {can('clientes.direcciones.editar') && (
                                <button type="button" className={THEME_BTN_SECONDARY} onClick={() => abrirEditar(d)}>
                                    <Pencil className="w-3.5 h-3.5 inline mr-1" /> Editar
                                </button>
                            )}
                            {can('clientes.direcciones.editar') && !d.es_principal && (
                                <button
                                    type="button"
                                    className={THEME_BTN_SECONDARY}
                                    onClick={() => router.post(route('control_pedidos.direcciones.principal', [cliente.id, d.id]))}
                                >
                                    <Star className="w-3.5 h-3.5 inline mr-1" /> Principal
                                </button>
                            )}
                            {can('clientes.direcciones.desactivar') && (
                                <button
                                    type="button"
                                    className="text-xs font-bold text-red-600 px-3 py-2 rounded-xl border border-red-500/30 hover:bg-red-500/10"
                                    onClick={() => {
                                        if (confirm('¿Desactivar esta dirección?')) {
                                            router.post(route('control_pedidos.direcciones.desactivar', [cliente.id, d.id]));
                                        }
                                    }}
                                >
                                    <Trash2 className="w-3.5 h-3.5 inline mr-1" /> Desactivar
                                </button>
                            )}
                        </div>
                    )}
                </div>
            </article>
        );
    };

    return (
        <AppLayout>
            <Head title={`Direcciones · ${cliente.numero_cliente}`} />
            <GeliaPageShell className="space-y-6">
                <header className={`${geliaCardClass()} p-6 md:p-8`}>
                    <Link
                        href={route('control_pedidos.direcciones.index')}
                        className="inline-flex items-center gap-1 text-xs font-bold theme-text-muted mb-4"
                    >
                        <ArrowLeft className="w-3.5 h-3.5" /> Volver al listado
                    </Link>
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>
                                Direcciones de envío
                            </p>
                            <h1 className="text-3xl font-black italic tracking-tighter uppercase theme-text-main m-0 mt-1">
                                {cliente.nombre}
                            </h1>
                            <p className="text-sm theme-text-muted mt-1 font-mono">{cliente.numero_cliente}</p>
                            <p className="text-xs theme-text-muted mt-2 m-0">
                                {activas.length} activa{activas.length === 1 ? '' : 's'}
                                {inactivas.length > 0 ? ` · ${inactivas.length} inactiva${inactivas.length === 1 ? '' : 's'}` : ''}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {can('clientes.direcciones.revisar_solicitudes') && (
                                <button
                                    type="button"
                                    className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-1.5`}
                                    onClick={() => router.get(route('control_pedidos.direcciones.solicitudes.index'), {
                                        q: cliente.numero_cliente,
                                        cliente_id: cliente.id,
                                    })}
                                >
                                    <ClipboardList className="w-4 h-4" />
                                    Solicitudes
                                    {solicitudes_pendientes > 0 && (
                                        <span className="text-[9px] font-black px-1.5 py-0.5 rounded-md bg-amber-500/15 text-amber-700 border border-amber-500/30">
                                            {solicitudes_pendientes}
                                        </span>
                                    )}
                                </button>
                            )}
                            {can('clientes.direcciones.generar_enlace') && (
                                <button
                                    type="button"
                                    className={THEME_BTN_SECONDARY}
                                    onClick={() => router.post(route('control_pedidos.direcciones.enlace', cliente.id))}
                                >
                                    <Link2 className="w-4 h-4 inline mr-1.5" />
                                    Generar enlace
                                </button>
                            )}
                            {can('clientes.direcciones.crear') && (
                                <button type="button" className={THEME_BTN_PRIMARY} onClick={abrirAlta}>
                                    Agregar dirección
                                </button>
                            )}
                        </div>
                    </div>
                </header>

                {flash?.enlace_direccion_url && (
                    <div className={`${geliaCardClass()} p-4 flex flex-wrap items-center gap-3`}>
                        <div className="flex-1 min-w-0">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1">Enlace listo para compartir</p>
                            <p className="text-sm break-all theme-text-main m-0 font-mono">{flash.enlace_direccion_url}</p>
                        </div>
                        <button type="button" className={THEME_BTN_SECONDARY} onClick={() => copiarTexto(flash.enlace_direccion_url, 'flash')}>
                            <Copy className="w-3.5 h-3.5 inline mr-1" />
                            {copiado === 'flash' ? 'Copiado' : 'Copiar'}
                        </button>
                    </div>
                )}

                {vigente.length > 0 && (
                    <div className={`${geliaCardClass()} p-4 md:p-5 space-y-3`}>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Enlaces vigentes_</p>
                        {vigente.map((e) => (
                            <div key={e.id} className="flex flex-wrap items-center justify-between gap-2 text-sm border-t theme-border pt-3 first:border-0 first:pt-0">
                                <div className="min-w-0 flex-1 space-y-1">
                                    {e.url ? (
                                        <p className="m-0 font-mono text-sm theme-text-main break-all">{e.url}</p>
                                    ) : (
                                        <p className="m-0 theme-text-muted">Enlace sin URL corta (legado)</p>
                                    )}
                                    <span className="theme-text-muted text-xs">
                                        {e.accion_permitida || 'cualquier acción'}
                                        {e.expira_en ? ` · expira ${new Date(e.expira_en).toLocaleString('es-MX')}` : ''}
                                    </span>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {e.url && (
                                        <button
                                            type="button"
                                            className={THEME_BTN_SECONDARY}
                                            onClick={() => copiarTexto(e.url, `e-${e.id}`)}
                                        >
                                            <Copy className="w-3.5 h-3.5 inline mr-1" />
                                            {copiado === `e-${e.id}` ? 'Copiado' : 'Copiar'}
                                        </button>
                                    )}
                                    {can('clientes.direcciones.generar_enlace') && (
                                        <button
                                            type="button"
                                            className="text-xs font-bold text-red-600 underline px-2"
                                            onClick={() => router.post(route('control_pedidos.direcciones.enlace.revocar', [cliente.id, e.id]))}
                                        >
                                            Revocar
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <section className="space-y-3">
                    <h2 className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 px-1">
                        Direcciones actuales_
                    </h2>
                    {activas.length === 0 ? (
                        <div className={`${geliaCardClass()} p-12 text-center`}>
                            <MapPin className="w-10 h-10 mx-auto theme-text-muted opacity-40 mb-3" />
                            <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted italic m-0">
                                Sin direcciones activas_
                            </p>
                            <p className="text-xs theme-text-muted mt-2 m-0">
                                Genere un enlace corto para el cliente o agregue una dirección aquí.
                            </p>
                        </div>
                    ) : (
                        <div className="grid gap-4">{activas.map(tarjetaDireccion)}</div>
                    )}
                </section>

                {inactivas.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 px-1">
                            Inactivas_
                        </h2>
                        <div className="grid gap-4">{inactivas.map(tarjetaDireccion)}</div>
                    </section>
                )}
            </GeliaPageShell>

            {modal && (
                <div className={`${THEME_MODAL_OVERLAY} z-[100]`} onClick={cerrarModal}>
                    <div className={`${THEME_MODAL_SHELL} max-w-2xl max-h-[90vh] overflow-y-auto p-6 md:p-8`} onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-between items-start mb-6">
                            <div>
                                <h2 className="text-xl font-black italic uppercase theme-text-main m-0">
                                    {modal === 'editar' ? 'Editar' : 'Nueva'} <span style={{ color: 'var(--color-primario)' }}>dirección</span>
                                </h2>
                                {modal === 'editar' && (
                                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mt-1">
                                        Se crea una nueva versión
                                    </p>
                                )}
                            </div>
                            <button type="button" onClick={cerrarModal} className="p-2 rounded-full theme-element theme-text-muted">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={guardar} className="grid gap-3 md:grid-cols-2">
                            {[
                                ['etiqueta', 'Etiqueta'],
                                ['nombre_destinatario', 'Destinatario *'],
                                ['telefono_destinatario', 'Teléfono'],
                                ['calle', 'Calle *'],
                                ['numero_exterior', 'Exterior'],
                                ['numero_interior', 'Interior'],
                                ['colonia', 'Colonia *'],
                                ['codigo_postal', 'CP *'],
                                ['municipio', 'Municipio *'],
                                ['ciudad', 'Ciudad'],
                                ['estado', 'Estado *'],
                                ['pais', 'País'],
                            ].map(([key, label]) => (
                                <label key={key} className="block text-sm">
                                    <span className="mb-1 block text-[9px] font-black uppercase tracking-widest theme-text-muted">{label}</span>
                                    <input
                                        className={THEME_INPUT}
                                        value={form.data[key]}
                                        inputMode={key === 'codigo_postal' ? 'numeric' : undefined}
                                        maxLength={key === 'codigo_postal' ? 5 : undefined}
                                        onChange={(e) => {
                                            const v = key === 'codigo_postal'
                                                ? e.target.value.replace(/\D/g, '').slice(0, 5)
                                                : e.target.value;
                                            form.setData(key, v);
                                        }}
                                    />
                                    {form.errors[key] && <span className="text-xs text-red-600">{form.errors[key]}</span>}
                                </label>
                            ))}
                            <label className="block text-sm md:col-span-2">
                                <span className="mb-1 block text-[9px] font-black uppercase tracking-widest theme-text-muted">Tipo</span>
                                <select className={THEME_SELECT} value={form.data.tipo_direccion} onChange={(e) => form.setData('tipo_direccion', e.target.value)}>
                                    <option value="envio">Envío</option>
                                    <option value="fiscal">Fiscal</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </label>
                            <label className="md:col-span-2 block text-sm">
                                <span className="mb-1 block text-[9px] font-black uppercase tracking-widest theme-text-muted">Referencias del domicilio</span>
                                <textarea className={THEME_TEXTAREA} value={form.data.referencias} onChange={(e) => form.setData('referencias', e.target.value)} rows={3} placeholder="Cómo encontrar el domicilio…" />
                            </label>
                            <label className="md:col-span-2 block text-sm">
                                <span className="mb-1 block text-[9px] font-black uppercase tracking-widest theme-text-muted">Indicaciones de entrega</span>
                                <textarea className={THEME_TEXTAREA} value={form.data.indicaciones_entrega} onChange={(e) => form.setData('indicaciones_entrega', e.target.value)} rows={3} placeholder="Instrucciones para el repartidor…" />
                            </label>
                            <label className="flex items-center gap-2 text-sm font-bold theme-text-main">
                                <input type="checkbox" checked={form.data.verificar} onChange={(e) => form.setData('verificar', e.target.checked)} />
                                Marcar como verificada
                            </label>
                            {modal === 'alta' && (
                                <label className="flex items-center gap-2 text-sm font-bold theme-text-main">
                                    <input type="checkbox" checked={form.data.es_principal} onChange={(e) => form.setData('es_principal', e.target.checked)} />
                                    Es principal
                                </label>
                            )}
                            <div className="md:col-span-2 flex justify-end gap-2 pt-2">
                                <button type="button" className={THEME_BTN_SECONDARY} onClick={cerrarModal}>Cancelar</button>
                                <button type="submit" className={THEME_BTN_PRIMARY} disabled={form.processing}>
                                    {form.processing ? 'Guardando…' : 'Guardar'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
