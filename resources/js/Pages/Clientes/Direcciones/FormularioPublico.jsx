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
const ETIQUETAS = ['Casa', 'Trabajo', 'Local', 'Otro'];

export default function FormularioPublico({
    token,
    cliente,
}) {
    const { data, setData, post, processing, errors } = useForm({
        token: token || '',
        nombres_destinatario: '',
        apellidos_destinatario: '',
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
        etiqueta_opcion: 'Casa',
        etiqueta_personalizada: '',
        anexa_remision: false,
    });

    const enviar = (e) => {
        e.preventDefault();
        if (!data.token) return;
        post(route('direcciones.publicas.store'), {
            preserveScroll: true,
            onFinish: () => {
                // Tras éxito el servidor redirige a confirmación y cierra el enlace.
            },
        });
    };

    const setCp = (raw) => setData('codigo_postal', String(raw || '').replace(/\D/g, '').slice(0, 5));
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
                    Complete los datos de entrega. Al guardar, su dirección quedará registrada y este enlace se cerrará.
                </p>

                {cliente && (
                    <div className="mt-5 rounded-xl border theme-border theme-element px-4 py-3 text-sm theme-text-main">
                        Cliente: {cliente.nombre_enmascarado} · Número: {cliente.numero_enmascarado}
                    </div>
                )}

                <form onSubmit={enviar} className="mt-8 space-y-5" autoComplete="off">
                    <input type="hidden" name="token" value={data.token} />

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Destinatario</h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Campo label="Nombre(s)" error={errors.nombres_destinatario}>
                                <input className={THEME_INPUT} value={data.nombres_destinatario} onChange={(e) => setData('nombres_destinatario', e.target.value)} required maxLength={120} />
                            </Campo>
                            <Campo label="Apellidos" error={errors.apellidos_destinatario}>
                                <input className={THEME_INPUT} value={data.apellidos_destinatario} onChange={(e) => setData('apellidos_destinatario', e.target.value)} required maxLength={120} />
                            </Campo>
                            <Campo label="Teléfono del destinatario" error={errors.telefono_destinatario}>
                                <input className={THEME_INPUT} value={data.telefono_destinatario} onChange={(e) => setData('telefono_destinatario', e.target.value)} maxLength={30} inputMode="tel" />
                            </Campo>
                        </div>
                    </section>

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Etiqueta</h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Campo label="Tipo de dirección" error={errors.etiqueta_opcion}>
                                <select className={THEME_SELECT} value={data.etiqueta_opcion} onChange={(e) => setData('etiqueta_opcion', e.target.value)}>
                                    {ETIQUETAS.map((e) => (
                                        <option key={e} value={e}>{e}</option>
                                    ))}
                                </select>
                            </Campo>
                            {data.etiqueta_opcion === 'Otro' && (
                                <Campo label="Etiqueta personalizada" error={errors.etiqueta_personalizada}>
                                    <input className={THEME_INPUT} value={data.etiqueta_personalizada} onChange={(e) => setData('etiqueta_personalizada', e.target.value)} required maxLength={100} />
                                </Campo>
                            )}
                        </div>
                    </section>

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Domicilio</h2>
                        <Campo label="Calle" error={errors.calle}>
                            <input className={THEME_INPUT} value={data.calle} onChange={(e) => setData('calle', e.target.value)} required maxLength={255} />
                        </Campo>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Campo label="No. exterior" error={errors.numero_exterior}>
                                <input className={THEME_INPUT} value={data.numero_exterior} onChange={(e) => setData('numero_exterior', e.target.value)} maxLength={30} />
                            </Campo>
                            <Campo label="No. interior" error={errors.numero_interior}>
                                <input className={THEME_INPUT} value={data.numero_interior} onChange={(e) => setData('numero_interior', e.target.value)} maxLength={30} />
                            </Campo>
                            <Campo label="Código postal" error={errors.codigo_postal}>
                                <input className={THEME_INPUT} value={data.codigo_postal} onChange={(e) => setCp(e.target.value)} inputMode="numeric" maxLength={5} pattern="\d{5}" required />
                            </Campo>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Campo label="Colonia" error={errors.colonia}>
                                <input className={THEME_INPUT} value={data.colonia} onChange={(e) => setData('colonia', e.target.value)} required maxLength={255} />
                            </Campo>
                            <Campo label="Municipio" error={errors.municipio}>
                                <input className={THEME_INPUT} value={data.municipio} onChange={(e) => setData('municipio', e.target.value)} required maxLength={255} />
                            </Campo>
                            <Campo label="Ciudad" error={errors.ciudad}>
                                <input className={THEME_INPUT} value={data.ciudad} onChange={(e) => setData('ciudad', e.target.value)} maxLength={255} />
                            </Campo>
                            <Campo label="Estado" error={errors.estado}>
                                <input className={THEME_INPUT} value={data.estado} onChange={(e) => setData('estado', e.target.value)} required maxLength={255} />
                            </Campo>
                        </div>
                        <Campo label="Referencias del domicilio" error={errors.referencias}>
                            <textarea
                                className={THEME_TEXTAREA}
                                value={data.referencias}
                                onChange={(e) => setData('referencias', e.target.value)}
                                maxLength={2000}
                                rows={3}
                                placeholder="Ej. Portón negro, entre calles X y Y, frente al Oxxo…"
                            />
                            <span className="mt-1 block text-[10px] theme-text-muted font-bold">
                                Cómo encontrar el domicilio (fachada, calles, puntos de referencia).
                            </span>
                        </Campo>
                    </section>

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Indicaciones de entrega</h2>
                        <Campo label="Instrucciones para el repartidor" error={errors.indicaciones_entrega}>
                            <textarea
                                className={THEME_TEXTAREA}
                                value={data.indicaciones_entrega}
                                onChange={(e) => setData('indicaciones_entrega', e.target.value)}
                                maxLength={2000}
                                rows={4}
                                placeholder="Ej. Llamar al llegar, dejar con el vecino del 2, horario 10–14 h, no tocar timbre…"
                            />
                            <span className="mt-1 block text-[10px] theme-text-muted font-bold">
                                Indicaciones operativas de la entrega (horario, contacto, quién recibe, restricciones).
                            </span>
                        </Campo>
                    </section>

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Remisión</h2>
                        <Campo label="¿Anexar remisión?" error={errors.anexa_remision}>
                            <select
                                className={THEME_SELECT}
                                value={data.anexa_remision ? '1' : '0'}
                                onChange={(e) => setData('anexa_remision', e.target.value === '1')}
                            >
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                            </select>
                        </Campo>
                    </section>

                    {errors.token && (
                        <p className="text-xs font-bold text-red-600 m-0">{errors.token}</p>
                    )}

                    <button
                        type="submit"
                        disabled={processing || !data.token}
                        className={`${THEME_BTN_PRIMARY} w-full md:w-auto disabled:opacity-60`}
                    >
                        {processing ? 'Guardando…' : 'Guardar dirección'}
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
