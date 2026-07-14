import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_INPUT,
    THEME_SELECT,
    THEME_TEXTAREA,
} from '../../../utils/geliaTheme';

const LABEL = 'mb-1.5 block text-[9px] font-black uppercase tracking-widest theme-text-muted';
const TITULO_SEC = 'text-[10px] font-black uppercase tracking-[0.2em] theme-text-main m-0';

export default function FormularioPublico({
    token,
    enlace_valido,
    cliente,
    direcciones = [],
    accion_permitida = null,
    acciones = [],
}) {
    const accionInicial = accion_permitida
        || acciones[0]?.value
        || 'register_first_address';

    const { data, setData, post, processing, errors } = useForm({
        token: token || '',
        numero_cliente: '',
        nombre_declarado: '',
        telefono_declarado: '',
        correo_declarado: '',
        accion_solicitada: accionInicial,
        direccion_seleccionada_id: '',
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
        anexa_remision: false,
        archivo_remision: null,
        comentario: '',
    });

    const enviar = (e) => {
        e.preventDefault();
        post(route('direcciones.publicas.store'), { forceFormData: true });
    };

    const setCp = (raw) => setData('codigo_postal', String(raw || '').replace(/\D/g, '').slice(0, 5));

    const remisionOk = !data.archivo_remision || /\.(pdf|jpe?g|png)$/i.test(data.archivo_remision.name || '');
    const seccion = `${geliaCardClass()} p-4 md:p-5 space-y-4`;

    return (
        <div
            className="min-h-screen px-4 py-10 md:py-14"
            style={{
                background: 'radial-gradient(ellipse at top, color-mix(in srgb, var(--color-primario) 12%, transparent), var(--color-fondo, #f4f4f5) 55%)',
            }}
        >
            <Head title="Datos de envío" />
            <div className={`mx-auto max-w-3xl ${geliaCardClass()} p-6 md:p-10`}>
                <p className="text-[10px] font-black uppercase tracking-[0.35em] m-0" style={{ color: 'var(--color-primario)' }}>
                    Gelia NV
                </p>
                <h1 className="mt-2 text-3xl md:text-4xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                    Dirección de envío
                </h1>
                <p className="mt-3 text-sm theme-text-muted m-0">
                    Complete el formulario. Su solicitud será revisada antes de actualizar el padrón.
                </p>

                {enlace_valido && cliente ? (
                    <div className="mt-5 rounded-xl border theme-border theme-element px-4 py-3 text-sm theme-text-main">
                        Cliente: {cliente.nombre_enmascarado} · Número: {cliente.numero_enmascarado}
                    </div>
                ) : (
                    <div className="mt-5 rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
                        Sin enlace asociado: valide su número de cliente y un segundo factor (correo o teléfono registrado).
                    </div>
                )}

                <form onSubmit={enviar} className="mt-8 space-y-5">
                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Identidad</h2>
                        {!enlace_valido && (
                            <div className="grid gap-4 md:grid-cols-2">
                                <Campo label="Número de cliente" error={errors.numero_cliente}>
                                    <input className={THEME_INPUT} value={data.numero_cliente} onChange={(e) => setData('numero_cliente', e.target.value)} />
                                </Campo>
                                <Campo label="Correo registrado" error={errors.correo_declarado}>
                                    <input type="email" className={THEME_INPUT} value={data.correo_declarado} onChange={(e) => setData('correo_declarado', e.target.value)} />
                                </Campo>
                            </div>
                        )}
                        <div className="grid gap-4 md:grid-cols-2">
                            <Campo label="Nombre completo" error={errors.nombre_declarado}>
                                <input className={THEME_INPUT} value={data.nombre_declarado} onChange={(e) => setData('nombre_declarado', e.target.value)} required />
                            </Campo>
                            <Campo label="Teléfono" error={errors.telefono_declarado}>
                                <input className={THEME_INPUT} value={data.telefono_declarado} onChange={(e) => setData('telefono_declarado', e.target.value)} required />
                            </Campo>
                        </div>
                    </section>

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Acción</h2>
                        <Campo label="¿Qué deseas hacer?" error={errors.accion_solicitada || errors.token}>
                            <select
                                className={THEME_SELECT}
                                value={data.accion_solicitada}
                                onChange={(e) => setData('accion_solicitada', e.target.value)}
                                disabled={Boolean(accion_permitida) && acciones.length <= 1}
                            >
                                {acciones.map((a) => (
                                    <option key={a.value} value={a.value}>{a.label}</option>
                                ))}
                            </select>
                        </Campo>
                        {data.accion_solicitada === 'update_address' && (
                            <Campo label="Dirección a actualizar" error={errors.direccion_seleccionada_id}>
                                <select className={THEME_SELECT} value={data.direccion_seleccionada_id} onChange={(e) => setData('direccion_seleccionada_id', e.target.value)}>
                                    <option value="">Seleccione…</option>
                                    {direcciones.map((d) => (
                                        <option key={d.id} value={d.id}>
                                            {d.codigo || `#${d.numero_direccion}`}
                                            {d.etiqueta ? ` · ${d.etiqueta}` : ''}
                                            {' — '}
                                            {d.resumen}
                                        </option>
                                    ))}
                                </select>
                            </Campo>
                        )}
                        <div className="grid gap-4 md:grid-cols-2">
                            <Campo label="Etiqueta (ej. Casa, Oficina)" error={errors.etiqueta}>
                                <input className={THEME_INPUT} value={data.etiqueta} onChange={(e) => setData('etiqueta', e.target.value)} />
                            </Campo>
                            <Campo label="Tipo de dirección" error={errors.tipo_direccion}>
                                <select className={THEME_SELECT} value={data.tipo_direccion} onChange={(e) => setData('tipo_direccion', e.target.value)}>
                                    <option value="envio">Envío</option>
                                    <option value="fiscal">Fiscal</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </Campo>
                        </div>
                    </section>

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Destinatario</h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Campo label="Nombre del destinatario" error={errors.nombre_destinatario}>
                                <input className={THEME_INPUT} value={data.nombre_destinatario} onChange={(e) => setData('nombre_destinatario', e.target.value)} required />
                            </Campo>
                            <Campo label="Teléfono del destinatario" error={errors.telefono_destinatario}>
                                <input className={THEME_INPUT} value={data.telefono_destinatario} onChange={(e) => setData('telefono_destinatario', e.target.value)} />
                            </Campo>
                        </div>
                    </section>

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Domicilio</h2>
                        <Campo label="Calle" error={errors.calle}>
                            <input className={THEME_INPUT} value={data.calle} onChange={(e) => setData('calle', e.target.value)} required />
                        </Campo>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Campo label="No. exterior" error={errors.numero_exterior}>
                                <input className={THEME_INPUT} value={data.numero_exterior} onChange={(e) => setData('numero_exterior', e.target.value)} />
                            </Campo>
                            <Campo label="No. interior" error={errors.numero_interior}>
                                <input className={THEME_INPUT} value={data.numero_interior} onChange={(e) => setData('numero_interior', e.target.value)} />
                            </Campo>
                            <Campo label="Código postal" error={errors.codigo_postal}>
                                <input
                                    className={THEME_INPUT}
                                    value={data.codigo_postal}
                                    onChange={(e) => setCp(e.target.value)}
                                    inputMode="numeric"
                                    maxLength={5}
                                    pattern="\d{5}"
                                    required
                                />
                            </Campo>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Campo label="Colonia" error={errors.colonia}>
                                <input className={THEME_INPUT} value={data.colonia} onChange={(e) => setData('colonia', e.target.value)} required />
                            </Campo>
                            <Campo label="Municipio" error={errors.municipio}>
                                <input className={THEME_INPUT} value={data.municipio} onChange={(e) => setData('municipio', e.target.value)} required />
                            </Campo>
                            <Campo label="Ciudad" error={errors.ciudad}>
                                <input className={THEME_INPUT} value={data.ciudad} onChange={(e) => setData('ciudad', e.target.value)} />
                            </Campo>
                            <Campo label="Estado" error={errors.estado}>
                                <input className={THEME_INPUT} value={data.estado} onChange={(e) => setData('estado', e.target.value)} required />
                            </Campo>
                        </div>
                        <Campo label="Referencias" error={errors.referencias}>
                            <textarea className={THEME_TEXTAREA} value={data.referencias} onChange={(e) => setData('referencias', e.target.value)} />
                        </Campo>
                        <Campo label="Indicaciones de entrega" error={errors.indicaciones_entrega}>
                            <textarea className={THEME_TEXTAREA} value={data.indicaciones_entrega} onChange={(e) => setData('indicaciones_entrega', e.target.value)} />
                        </Campo>
                    </section>

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Remisión</h2>
                        <label className="flex items-center gap-2 text-sm font-bold theme-text-main">
                            <input type="checkbox" checked={data.anexa_remision} onChange={(e) => setData('anexa_remision', e.target.checked)} />
                            Anexar remisión
                        </label>
                        {data.anexa_remision && (
                            <Campo label="Archivo de remisión (PDF / JPG / PNG)" error={errors.archivo_remision}>
                                <input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    onChange={(e) => setData('archivo_remision', e.target.files?.[0] || null)}
                                />
                                {data.archivo_remision && (
                                    <p className={`mt-2 text-xs m-0 ${remisionOk ? 'theme-text-muted' : 'text-red-600'}`}>
                                        {data.archivo_remision.name}
                                        {' · '}
                                        {(data.archivo_remision.size / 1024).toFixed(1)} KB
                                        {!remisionOk && ' — formato no válido'}
                                    </p>
                                )}
                            </Campo>
                        )}
                    </section>

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Comentario</h2>
                        <Campo label="Comentario adicional" error={errors.comentario}>
                            <textarea className={THEME_TEXTAREA} value={data.comentario} onChange={(e) => setData('comentario', e.target.value)} />
                        </Campo>
                    </section>

                    <button
                        type="submit"
                        disabled={processing || (data.anexa_remision && !remisionOk)}
                        className={`${THEME_BTN_PRIMARY} w-full md:w-auto disabled:opacity-60`}
                    >
                        {processing ? 'Enviando…' : 'Enviar solicitud'}
                    </button>
                </form>
            </div>
        </div>
    );
}

function Campo({ label, error, children }) {
    return (
        <label className="block">
            <span className={LABEL}>{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}
