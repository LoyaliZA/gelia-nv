import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_INPUT,
} from '../../../utils/geliaTheme';

const LABEL = 'mb-1.5 block text-[9px] font-black uppercase tracking-widest theme-text-muted';

export default function FormularioPublico({
    token,
    cliente,
    campos = [],
    destinatario_tipo = 'cliente',
}) {
    const initial = { token: token || '' };
    campos.forEach((c) => {
        initial[c.clave] = '';
    });

    const { data, setData, post, processing, errors } = useForm(initial);

    const enviar = (e) => {
        e.preventDefault();
        if (!data.token) return;
        post(route('datos_fiscales.publicas.store'), { preserveScroll: true });
    };

    const seccion = `${geliaCardClass()} p-4 md:p-5 space-y-4`;

    return (
        <div
            className="min-h-screen px-4 py-10 md:py-14"
            style={{
                background: 'radial-gradient(ellipse at top, color-mix(in srgb, var(--color-primario) 12%, transparent), var(--color-fondo, #f4f4f5) 55%)',
            }}
        >
            <Head title="Datos fiscales" />
            <div className={`mx-auto max-w-3xl ${geliaCardClass()} p-6 md:p-10`}>
                <p className="text-[10px] font-black uppercase tracking-[0.35em] m-0" style={{ color: 'var(--color-primario)' }}>
                    Gelia NV
                </p>
                <h1 className="mt-2 text-3xl md:text-4xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                    Datos fiscales
                </h1>
                <p className="mt-3 text-sm theme-text-muted m-0">
                    Complete la información fiscal solicitada. Al guardar, este enlace se cerrará.
                    {destinatario_tipo === 'tercero'
                        ? ' Los datos se usarán solo para esta factura.'
                        : ' Los datos quedarán registrados en su cuenta.'}
                </p>

                {cliente && (
                    <div className="mt-5 rounded-xl border theme-border theme-element px-4 py-3 text-sm theme-text-main">
                        Cliente: {cliente.nombre_enmascarado} · Número: {cliente.numero_enmascarado}
                    </div>
                )}

                <form onSubmit={enviar} className="mt-8 space-y-5" autoComplete="off">
                    <input type="hidden" name="token" value={data.token} />

                    <section className={seccion}>
                        <h2 className="text-[10px] font-black uppercase tracking-[0.2em] theme-text-main m-0">Información fiscal</h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            {campos.map((campo) => (
                                <div key={campo.clave} className={campo.clave === 'nombre_razon_social' || campo.clave === 'correo_electronico' ? 'md:col-span-2' : ''}>
                                    <label className={LABEL}>{campo.etiqueta}</label>
                                    <input
                                        className={THEME_INPUT}
                                        value={data[campo.clave] || ''}
                                        onChange={(e) => {
                                            let val = e.target.value;
                                            if (campo.clave === 'rfc') val = val.toUpperCase().replace(/\s+/g, '');
                                            if (campo.clave === 'codigo_postal') val = val.replace(/\D/g, '').slice(0, 5);
                                            setData(campo.clave, val);
                                        }}
                                        required
                                        maxLength={campo.clave === 'rfc' ? 13 : campo.clave === 'codigo_postal' ? 5 : 255}
                                        inputMode={campo.clave === 'codigo_postal' || campo.clave === 'telefono' ? 'numeric' : campo.clave === 'correo_electronico' ? 'email' : 'text'}
                                    />
                                    {errors[campo.clave] && (
                                        <p className="mt-1 text-xs text-red-500 m-0">{errors[campo.clave]}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>

                    {errors.token && <p className="text-xs text-red-500 m-0">{errors.token}</p>}

                    <button type="submit" disabled={processing || !data.token} className={`${THEME_BTN_PRIMARY} w-full !py-4 disabled:opacity-50`}>
                        {processing ? 'Guardando…' : 'Enviar datos fiscales'}
                    </button>
                </form>
            </div>
        </div>
    );
}
