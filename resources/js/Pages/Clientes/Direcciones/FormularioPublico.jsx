import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_INPUT,
    THEME_SELECT,
    THEME_TEXTAREA,
} from '../../../utils/geliaTheme';
import { formatearTelefono, soloDigitos } from '../../../utils/formatoContacto';

const LABEL = 'mb-1.5 block text-sm font-semibold theme-text-main';
const TITULO_SEC = 'text-base font-bold theme-text-main m-0';
const CAMPO = 'min-h-[44px] text-base';
const OBLIGATORIO = 'Complete este campo.';
const ORDEN_CAMPOS = ['nombres_destinatario', 'apellidos_destinatario', 'telefono_destinatario', 'etiqueta_personalizada', 'calle', 'codigo_postal', 'colonia', 'municipio', 'estado', 'referencias'];

/** Errores que se pueden detectar sin servidor. Los mensajes del servidor tienen prioridad al mostrarse. */
function validarLocal(data) {
    const irregular = Boolean(data.domicilio_irregular);
    const vacio = (valor) => !String(valor || '').trim();
    const errores = {};
    if (vacio(data.nombres_destinatario)) errores.nombres_destinatario = OBLIGATORIO;
    if (vacio(data.apellidos_destinatario)) errores.apellidos_destinatario = OBLIGATORIO;
    const telefono = soloDigitos(data.telefono_destinatario);
    if (telefono && telefono.length !== 10) errores.telefono_destinatario = 'Escriba un teléfono de 10 dígitos, por ejemplo 55 1234 5678.';
    if (data.etiqueta_opcion === 'Otro' && vacio(data.etiqueta_personalizada)) errores.etiqueta_personalizada = OBLIGATORIO;
    if (!irregular && vacio(data.calle)) errores.calle = OBLIGATORIO;
    const cp = String(data.codigo_postal || '');
    if ((!irregular || cp) && cp.length !== 5) errores.codigo_postal = 'Escriba un código postal de 5 dígitos.';
    if (!irregular && vacio(data.colonia)) errores.colonia = OBLIGATORIO;
    if (!irregular && vacio(data.municipio)) errores.municipio = OBLIGATORIO;
    if (vacio(data.estado)) errores.estado = OBLIGATORIO;
    if (irregular && String(data.referencias || '').trim().length < 10) errores.referencias = 'Explique cómo ubicar el domicilio (mínimo 10 caracteres).';
    return errores;
}

const ETIQUETAS = ['Casa', 'Trabajo', 'Local', 'Otro'];

const TITULOS = {
    register_first_address: 'Registrar dirección de envío',
    add_address: 'Añadir dirección de envío',
    update_address: 'Actualizar dirección de envío',
};

export default function FormularioPublico({
    token,
    cliente,
    accion_permitida = null,
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
        domicilio_irregular: false,
    });

    const irregular = Boolean(data.domicilio_irregular);
    const titulo = TITULOS[accion_permitida] || 'Dirección de envío';

    const [tocados, setTocados] = useState({});
    const locales = validarLocal(data);
    const errorDe = (campo) => errors[campo] || (tocados[campo] ? locales[campo] : undefined);
    const tocar = (campo) => setTocados((previos) => (previos[campo] ? previos : { ...previos, [campo]: true }));
    // Propiedades comunes de cada entrada: id, valor, validación al salir y estado accesible.
    const entrada = (campo, extras = {}) => ({
        id: `direccion-${campo}`,
        value: data[campo],
        onBlur: () => tocar(campo),
        'aria-invalid': errorDe(campo) ? 'true' : undefined,
        'aria-describedby': errorDe(campo) ? `direccion-${campo}-error` : undefined,
        ...extras,
    });

    const enviar = (e) => {
        e.preventDefault();
        if (!data.token) return;
        const primero = ORDEN_CAMPOS.find((campo) => locales[campo]);
        if (primero) {
            setTocados(Object.fromEntries(ORDEN_CAMPOS.map((campo) => [campo, true])));
            document.getElementById(`direccion-${primero}`)?.focus();
            return;
        }
        post(route('direcciones.publicas.store'), {
            preserveScroll: true,
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
            <Head title={titulo} />
            <div className={`mx-auto max-w-3xl ${geliaCardClass()} p-6 md:p-10`}>
                <p className="text-[10px] font-black uppercase tracking-[0.35em] m-0" style={{ color: 'var(--color-primario)' }}>
                    Gelia NV
                </p>
                <h1 className="mt-2 text-3xl md:text-4xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                    {titulo}
                </h1>
                <p className="mt-3 text-sm theme-text-muted m-0">
                    Complete los datos de entrega. Al guardar, su dirección quedará registrada y este enlace se cerrará.
                </p>

                {cliente && (
                    <div className="mt-5 rounded-xl border theme-border theme-element px-4 py-3 text-sm theme-text-main">
                        Cliente: {cliente.nombre_enmascarado} · Número: {cliente.numero_enmascarado}
                    </div>
                )}

                <form onSubmit={enviar} className="mt-8 space-y-5" noValidate>
                    <input type="hidden" name="token" value={data.token} />

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Destinatario</h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Campo label="Nombre(s)" campo="nombres_destinatario" error={errorDe('nombres_destinatario')}>
                                <input className={`${THEME_INPUT} ${CAMPO}`} {...entrada('nombres_destinatario')} onChange={(e) => setData('nombres_destinatario', e.target.value)} maxLength={120} autoComplete="given-name" />
                            </Campo>
                            <Campo label="Apellidos" campo="apellidos_destinatario" error={errorDe('apellidos_destinatario')}>
                                <input className={`${THEME_INPUT} ${CAMPO}`} {...entrada('apellidos_destinatario')} onChange={(e) => setData('apellidos_destinatario', e.target.value)} maxLength={120} autoComplete="family-name" />
                            </Campo>
                            <Campo label="Teléfono del destinatario" campo="telefono_destinatario" error={errorDe('telefono_destinatario')} ayuda="Opcional. 10 dígitos, por ejemplo 55 1234 5678.">
                                <input className={`${THEME_INPUT} ${CAMPO}`} type="tel" inputMode="tel" autoComplete="tel-national" maxLength={13} {...entrada('telefono_destinatario')} onChange={(e) => setData('telefono_destinatario', formatearTelefono(e.target.value))} />
                            </Campo>
                        </div>
                    </section>

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Etiqueta</h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Campo label="Tipo de dirección" campo="etiqueta_opcion" error={errors.etiqueta_opcion}>
                                <select className={`${THEME_SELECT} ${CAMPO}`} id="direccion-etiqueta_opcion" value={data.etiqueta_opcion} onChange={(e) => setData('etiqueta_opcion', e.target.value)}>
                                    {ETIQUETAS.map((e) => (
                                        <option key={e} value={e}>{e}</option>
                                    ))}
                                </select>
                            </Campo>
                            {data.etiqueta_opcion === 'Otro' && (
                                <Campo label="Etiqueta personalizada" campo="etiqueta_personalizada" error={errorDe('etiqueta_personalizada')}>
                                    <input className={`${THEME_INPUT} ${CAMPO}`} {...entrada('etiqueta_personalizada')} onChange={(e) => setData('etiqueta_personalizada', e.target.value)} maxLength={100} />
                                </Campo>
                            )}
                        </div>
                    </section>

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Domicilio</h2>
                        <label className="flex min-h-[44px] items-start gap-2 text-sm font-semibold theme-text-main">
                            <input
                                type="checkbox"
                                className="mt-1 size-5"
                                checked={irregular}
                                onChange={(e) => setData('domicilio_irregular', e.target.checked)}
                            />
                            <span>
                                Domicilio irregular / sin calle formal
                                <span className="block text-sm font-normal theme-text-muted mt-0.5">
                                    Use esta opción si es “domicilio conocido”, calle sin nombre o datos incompletos. Las referencias pasan a ser obligatorias.
                                </span>
                            </span>
                        </label>
                        <Campo label={irregular ? 'Calle (opcional)' : 'Calle'} campo="calle" error={errorDe('calle')}>
                            <input
                                className={`${THEME_INPUT} ${CAMPO}`}
                                {...entrada('calle')}
                                onChange={(e) => setData('calle', e.target.value)}
                                autoComplete="address-line1"
                                maxLength={255}
                                placeholder={irregular ? 'Ej. Domicilio conocido, sin nombre…' : undefined}
                            />
                        </Campo>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Campo label="No. exterior" campo="numero_exterior" error={errors.numero_exterior}>
                                <input className={`${THEME_INPUT} ${CAMPO}`} id="direccion-numero_exterior" value={data.numero_exterior} onChange={(e) => setData('numero_exterior', e.target.value)} maxLength={30} />
                            </Campo>
                            <Campo label="No. interior" campo="numero_interior" error={errors.numero_interior}>
                                <input className={`${THEME_INPUT} ${CAMPO}`} id="direccion-numero_interior" value={data.numero_interior} onChange={(e) => setData('numero_interior', e.target.value)} maxLength={30} />
                            </Campo>
                            <Campo label={irregular ? 'Código postal (opcional)' : 'Código postal'} campo="codigo_postal" error={errorDe('codigo_postal')}>
                                <input
                                    className={`${THEME_INPUT} ${CAMPO}`}
                                    {...entrada('codigo_postal')}
                                    onChange={(e) => setCp(e.target.value)}
                                    inputMode="numeric"
                                    autoComplete="postal-code"
                                    maxLength={5}
                                />
                            </Campo>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Campo label={irregular ? 'Colonia (opcional)' : 'Colonia'} campo="colonia" error={errorDe('colonia')}>
                                <input className={`${THEME_INPUT} ${CAMPO}`} {...entrada('colonia')} onChange={(e) => setData('colonia', e.target.value)} maxLength={255} />
                            </Campo>
                            <Campo label={irregular ? 'Municipio (o ciudad)' : 'Municipio'} campo="municipio" error={errorDe('municipio')}>
                                <input className={`${THEME_INPUT} ${CAMPO}`} {...entrada('municipio')} onChange={(e) => setData('municipio', e.target.value)} maxLength={255} />
                            </Campo>
                            <Campo label={irregular ? 'Ciudad (o municipio)' : 'Ciudad'} campo="ciudad" error={errors.ciudad}>
                                <input className={`${THEME_INPUT} ${CAMPO}`} id="direccion-ciudad" value={data.ciudad} onChange={(e) => setData('ciudad', e.target.value)} maxLength={255} autoComplete="address-level2" />
                            </Campo>
                            <Campo label="Estado" campo="estado" error={errorDe('estado')}>
                                <input className={`${THEME_INPUT} ${CAMPO}`} {...entrada('estado')} onChange={(e) => setData('estado', e.target.value)} maxLength={255} autoComplete="address-level1" />
                            </Campo>
                        </div>
                        <Campo label={irregular ? 'Referencias del domicilio (obligatorio)' : 'Referencias del domicilio'} campo="referencias" error={errorDe('referencias')}>
                            <textarea
                                className={`${THEME_TEXTAREA} text-base`}
                                {...entrada('referencias')}
                                onChange={(e) => setData('referencias', e.target.value)}
                                maxLength={2000}
                                rows={3}
                                placeholder="Ej. Portón negro, entre calles X y Y, frente al Oxxo, domicilio conocido…"
                            />
                            <span className="mt-1 block text-sm theme-text-muted">
                                {irregular
                                    ? 'Obligatorio: explique cómo ubicar el domicilio (mín. 10 caracteres).'
                                    : 'Cómo encontrar el domicilio (fachada, calles, puntos de referencia).'}
                            </span>
                        </Campo>
                    </section>

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Indicaciones de entrega</h2>
                        <Campo label="Instrucciones para el repartidor" campo="indicaciones_entrega" error={errors.indicaciones_entrega}>
                            <textarea
                                className={`${THEME_TEXTAREA} text-base`}
                                id="direccion-indicaciones_entrega"
                                value={data.indicaciones_entrega}
                                onChange={(e) => setData('indicaciones_entrega', e.target.value)}
                                maxLength={2000}
                                rows={4}
                                placeholder="Ej. Llamar al llegar, dejar con el vecino del 2, horario 10–14 h, no tocar timbre…"
                            />
                            <span className="mt-1 block text-sm theme-text-muted">
                                Indicaciones operativas de la entrega (horario, contacto, quién recibe, restricciones).
                            </span>
                        </Campo>
                    </section>

                    <section className={seccion}>
                        <h2 className={TITULO_SEC}>Nota de compra</h2>
                        <Campo label="¿Deseas que la nota de compra vaya dentro de tu envío?" campo="anexa_remision" error={errors.anexa_remision}>
                            <select
                                className={`${THEME_SELECT} ${CAMPO}`}
                                id="direccion-anexa_remision"
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
                        className={`${THEME_BTN_PRIMARY} min-h-[44px] w-full md:w-auto disabled:opacity-60`}
                    >
                        {processing ? 'Guardando…' : 'Guardar dirección'}
                    </button>
                </form>
            </div>
        </div>
    );
}

function Campo({ label, campo, error, ayuda, children }) {
    return (
        <label className="block">
            <span className={LABEL}>{label}</span>
            {children}
            {ayuda && !error && <span className="mt-1 block text-sm theme-text-muted">{ayuda}</span>}
            {error && <span id={`direccion-${campo}-error`} role="alert" className="mt-1 block text-sm text-red-600">{error}</span>}
        </label>
    );
}
