import React, { useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Copy, Link2, MapPin, Pencil, Star, Trash2, X } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
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
import { labelEstadoSolicitud } from '../../ControlPedidos/Partials/DireccionPedidoResumen';

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

export default function Index({ cliente, direcciones = [], enlaces = [] }) {
    const { flash, auth } = usePage().props;
    const can = (p) => auth?.user?.permissions?.includes(p)
        || auth?.user?.roles?.includes('Admin')
        || auth?.user?.roles?.includes('Super admin (admin)');

    const [modal, setModal] = useState(null); // 'alta' | 'editar' | null
    const [editId, setEditId] = useState(null);
    const [copiado, setCopiado] = useState(false);

    const form = useForm({ ...CAMPOS_VACIOS });

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
            form.put(route('admin.clientes.direcciones.update', [cliente.id, editId]), {
                onSuccess: () => cerrarModal(),
            });
        } else {
            form.post(route('admin.clientes.direcciones.store', cliente.id), {
                onSuccess: () => cerrarModal(),
            });
        }
    };

    const copiarEnlace = async () => {
        const url = flash?.enlace_direccion_url;
        if (!url) return;
        try {
            await navigator.clipboard.writeText(url);
            setCopiado(true);
            setTimeout(() => setCopiado(false), 2000);
        } catch {
            /* ignore */
        }
    };

    const vigente = useMemo(
        () => (enlaces || []).filter((e) => e.esta_vigente),
        [enlaces],
    );

    return (
        <AppLayout>
            <Head title={`Direcciones · ${cliente.numero_cliente}`} />
            <div className="max-w-[1100px] mx-auto p-4 md:p-8 space-y-6">
                <header className={`${geliaCardClass()} p-6 md:p-10`}>
                    <Link
                        href={route('admin.clientes')}
                        className="inline-flex items-center gap-1 text-xs font-bold theme-text-muted mb-4"
                    >
                        <ArrowLeft className="w-3.5 h-3.5" /> Clientes
                    </Link>
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>
                                Direcciones de envío
                            </p>
                            <h1 className="text-3xl font-black italic tracking-tighter uppercase theme-text-main m-0 mt-1">
                                {cliente.nombre}
                            </h1>
                            <p className="text-sm theme-text-muted mt-1">Cliente {cliente.numero_cliente}</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {can('clientes.direcciones.generar_enlace') && (
                                <button
                                    type="button"
                                    className={THEME_BTN_SECONDARY}
                                    onClick={() => router.post(route('admin.clientes.direcciones.enlace', cliente.id))}
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
                        <p className="flex-1 min-w-0 text-sm break-all theme-text-main m-0 font-mono">{flash.enlace_direccion_url}</p>
                        <button type="button" className={THEME_BTN_SECONDARY} onClick={copiarEnlace}>
                            <Copy className="w-3.5 h-3.5 inline mr-1" />
                            {copiado ? 'Copiado' : 'Copiar'}
                        </button>
                    </div>
                )}

                {vigente.length > 0 && (
                    <div className={`${geliaCardClass()} p-4 md:p-5 space-y-3`}>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Enlaces vigentes_</p>
                        {vigente.map((e) => (
                            <div key={e.id} className="flex flex-wrap items-center justify-between gap-2 text-sm border-t theme-border pt-3 first:border-0 first:pt-0">
                                <span className="theme-text-muted">
                                    {e.accion_permitida || 'cualquier acción'}
                                    {e.expira_en ? ` · expira ${new Date(e.expira_en).toLocaleString('es-MX')}` : ''}
                                </span>
                                {can('clientes.direcciones.generar_enlace') && (
                                    <button
                                        type="button"
                                        className="text-xs font-bold text-red-600 underline"
                                        onClick={() => router.post(route('admin.clientes.direcciones.enlace.revocar', [cliente.id, e.id]))}
                                    >
                                        Revocar
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                <div className="grid gap-4">
                    {direcciones.length === 0 ? (
                        <div className={`${geliaCardClass()} p-12 text-center`}>
                            <MapPin className="w-10 h-10 mx-auto theme-text-muted opacity-40 mb-3" />
                            <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted italic m-0">
                                Sin direcciones registradas_
                            </p>
                        </div>
                    ) : (
                        direcciones.map((d) => (
                            <article key={d.id} className={`${geliaCardClass()} p-5 md:p-6`}>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="space-y-2 min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-mono text-xs font-black theme-text-muted">#{d.numero_direccion}</span>
                                            {d.etiqueta && (
                                                <span className="text-sm font-bold theme-text-main">{d.etiqueta}</span>
                                            )}
                                            {d.es_principal && (
                                                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest bg-[color-mix(in_srgb,var(--color-primario)_12%,transparent)] border border-[color-mix(in_srgb,var(--color-primario)_30%,transparent)]" style={{ color: 'var(--color-primario)' }}>
                                                    <Star className="w-3 h-3" /> Principal
                                                </span>
                                            )}
                                            <span className={badgeVerificacion(d.estado_verificacion)}>
                                                {d.estado_verificacion === 'verified' ? 'Verificada' : labelEstadoSolicitud(d.estado_verificacion)}
                                            </span>
                                            <span className={`inline-flex px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest border ${d.esta_activa ? 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30' : 'theme-element theme-border theme-text-muted'}`}>
                                                {d.esta_activa ? 'Activa' : 'Inactiva'}
                                            </span>
                                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">v{d.version}</span>
                                        </div>
                                        <p className="m-0 font-bold theme-text-main">{d.nombre_destinatario}</p>
                                        <p className="m-0 text-sm theme-text-muted">{d.resumen}</p>
                                        {d.codigo_postal && (
                                            <p className="m-0 text-xs theme-text-muted">C.P. {d.codigo_postal}</p>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {can('clientes.direcciones.editar') && d.esta_activa && (
                                            <button type="button" className={THEME_BTN_SECONDARY} onClick={() => abrirEditar(d)}>
                                                <Pencil className="w-3.5 h-3.5 inline mr-1" /> Editar
                                            </button>
                                        )}
                                        {can('clientes.direcciones.editar') && !d.es_principal && d.esta_activa && (
                                            <button
                                                type="button"
                                                className={THEME_BTN_SECONDARY}
                                                onClick={() => router.post(route('admin.clientes.direcciones.principal', [cliente.id, d.id]))}
                                            >
                                                <Star className="w-3.5 h-3.5 inline mr-1" /> Principal
                                            </button>
                                        )}
                                        {can('clientes.direcciones.desactivar') && d.esta_activa && (
                                            <button
                                                type="button"
                                                className="text-xs font-bold text-red-600 px-3 py-2 rounded-xl border border-red-500/30 hover:bg-red-500/10"
                                                onClick={() => {
                                                    if (confirm('¿Desactivar esta dirección?')) {
                                                        router.post(route('admin.clientes.direcciones.desactivar', [cliente.id, d.id]));
                                                    }
                                                }}
                                            >
                                                <Trash2 className="w-3.5 h-3.5 inline mr-1" /> Desactivar
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </article>
                        ))
                    )}
                </div>
            </div>

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
                                <textarea className={THEME_TEXTAREA} value={form.data.indicaciones_entrega} onChange={(e) => form.setData('indicaciones_entrega', e.target.value)} rows={3} placeholder="Instrucciones para el repartidor (horario, quién recibe, etc.)…" />
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
