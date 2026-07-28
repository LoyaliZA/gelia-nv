import React, { useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_INPUT,
} from '../../../utils/geliaTheme';
import SelectorCatalogoFiscal from '../../../Components/Facturas/SelectorCatalogoFiscal';
import {
    esRegimenSueldosSalarios,
    usoCfdiParaRegimen,
    USO_SIN_EFECTOS_FISCALES,
} from '../../../utils/reglasCatalogosFiscales';

const LABEL = 'mb-1.5 block text-[9px] font-black uppercase tracking-widest theme-text-muted';
const INPUT_ERROR = '!border-red-500 focus:!border-red-500 focus:!ring-red-500/30';

function opcionesCatalogo(catalogos, clave) {
    if (clave === 'regimen_fiscal') return catalogos?.regimen_fiscal || [];
    if (clave === 'uso_factura') return catalogos?.uso_cfdi || [];
    return null;
}

function esCorreoValido(valor) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(valor || '').trim());
}

export default function FormularioPublico({
    token,
    cliente,
    campos = [],
    destinatario_tipo = 'cliente',
    catalogos = { regimen_fiscal: [], uso_cfdi: [] },
}) {
    const initial = { token: token || '' };
    campos.forEach((c) => {
        initial[c.clave] = '';
    });

    const { data, setData, post, processing, errors, setError, clearErrors } = useForm(initial);

    const usoBloqueado = esRegimenSueldosSalarios(data.regimen_fiscal);
    const tieneUso = campos.some((c) => c.clave === 'uso_factura');

    useEffect(() => {
        if (!tieneUso) return;
        const forzado = usoCfdiParaRegimen(data.regimen_fiscal);
        if (forzado && data.uso_factura !== forzado) {
            setData('uso_factura', forzado);
        }
    }, [data.regimen_fiscal, data.uso_factura, tieneUso, setData]);

    const enviar = (e) => {
        e.preventDefault();
        if (!data.token) return;

        clearErrors();
        let ok = true;

        for (const campo of campos) {
            const val = String(data[campo.clave] ?? '').trim();
            if (!val) {
                setError(campo.clave, 'Este campo es obligatorio.');
                ok = false;
                continue;
            }
            if (campo.clave === 'correo_electronico' && !esCorreoValido(val)) {
                setError(campo.clave, 'Ingrese un correo electrónico válido.');
                ok = false;
            }
            if (campo.clave === 'telefono' && !/^\d{1,10}$/.test(val)) {
                setError(campo.clave, 'El teléfono solo admite dígitos (máximo 10).');
                ok = false;
            }
            if (campo.clave === 'codigo_postal' && !/^\d{5}$/.test(val)) {
                setError(campo.clave, 'El código postal debe tener 5 dígitos.');
                ok = false;
            }
        }

        if (!ok) return;

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

                <form onSubmit={enviar} className="mt-8 space-y-5" autoComplete="off" noValidate>
                    <input type="hidden" name="token" value={data.token} />

                    <section className={seccion}>
                        <h2 className="text-[10px] font-black uppercase tracking-[0.2em] theme-text-main m-0">Información fiscal</h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            {campos.map((campo) => {
                                const opciones = opcionesCatalogo(catalogos, campo.clave);
                                const esSelect = Array.isArray(opciones);
                                const spanAncho = campo.clave === 'nombre_razon_social'
                                    || campo.clave === 'correo_electronico'
                                    || campo.clave === 'regimen_fiscal'
                                    || campo.clave === 'uso_factura';
                                const esUsoBloqueado = campo.clave === 'uso_factura' && usoBloqueado;
                                const tieneError = Boolean(errors[campo.clave]);

                                return (
                                    <div key={campo.clave} className={spanAncho ? 'md:col-span-2' : ''}>
                                        <label className={`${LABEL} ${tieneError ? 'text-red-500' : ''}`}>
                                            {campo.etiqueta}
                                        </label>
                                        {esSelect ? (
                                            <SelectorCatalogoFiscal
                                                opciones={
                                                    esUsoBloqueado
                                                        ? (opciones.filter((o) => o.codigo === USO_SIN_EFECTOS_FISCALES).length
                                                            ? opciones.filter((o) => o.codigo === USO_SIN_EFECTOS_FISCALES)
                                                            : [{ codigo: USO_SIN_EFECTOS_FISCALES, nombre: 'Sin efectos fiscales' }])
                                                        : opciones
                                                }
                                                value={data[campo.clave] || ''}
                                                onChange={(codigo) => {
                                                    clearErrors(campo.clave);
                                                    if (campo.clave === 'regimen_fiscal') {
                                                        const forzado = usoCfdiParaRegimen(codigo);
                                                        if (forzado && tieneUso) {
                                                            setData((prev) => ({
                                                                ...prev,
                                                                regimen_fiscal: codigo,
                                                                uso_factura: forzado,
                                                            }));
                                                            clearErrors('uso_factura');
                                                            return;
                                                        }
                                                    }
                                                    setData(campo.clave, codigo);
                                                }}
                                                required
                                                disabled={esUsoBloqueado}
                                                invalid={tieneError}
                                            />
                                        ) : (
                                            <input
                                                type={campo.clave === 'correo_electronico' ? 'email' : 'text'}
                                                className={`${THEME_INPUT} ${tieneError ? INPUT_ERROR : ''}`}
                                                value={data[campo.clave] || ''}
                                                onChange={(e) => {
                                                    let val = e.target.value;
                                                    if (campo.clave === 'rfc') val = val.toUpperCase().replace(/[^A-ZÑ&0-9]/gi, '').slice(0, 13);
                                                    if (campo.clave === 'codigo_postal') val = val.replace(/\D/g, '').slice(0, 5);
                                                    if (campo.clave === 'correo_electronico') val = val.toLowerCase();
                                                    if (campo.clave === 'telefono') val = val.replace(/\D/g, '').slice(0, 10);
                                                    setData(campo.clave, val);
                                                    if (errors[campo.clave]) clearErrors(campo.clave);
                                                }}
                                                onBlur={() => {
                                                    if (campo.clave !== 'correo_electronico') return;
                                                    const val = String(data.correo_electronico || '').trim();
                                                    if (val && !esCorreoValido(val)) {
                                                        setError('correo_electronico', 'Ingrese un correo electrónico válido.');
                                                    }
                                                }}
                                                required
                                                maxLength={
                                                    campo.clave === 'rfc' ? 13
                                                        : campo.clave === 'codigo_postal' ? 5
                                                            : campo.clave === 'telefono' ? 10
                                                                : 255
                                                }
                                                inputMode={campo.clave === 'codigo_postal' || campo.clave === 'telefono' ? 'numeric' : campo.clave === 'correo_electronico' ? 'email' : 'text'}
                                                autoCapitalize={campo.clave === 'correo_electronico' ? 'none' : campo.clave === 'rfc' ? 'characters' : undefined}
                                                autoCorrect={campo.clave === 'correo_electronico' || campo.clave === 'rfc' ? 'off' : undefined}
                                                aria-invalid={tieneError}
                                            />
                                        )}
                                        {esUsoBloqueado && (
                                            <p className="mt-1 text-[10px] font-bold theme-text-muted m-0">
                                                Bloqueado por Regimen fiscal: {data.regimen_fiscal}.
                                            </p>
                                        )}
                                        {tieneError && (
                                            <p className="mt-1 text-xs text-red-500 font-bold m-0">{errors[campo.clave]}</p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    {errors.token && <p className="text-xs text-red-500 font-bold m-0">{errors.token}</p>}

                    <button type="submit" disabled={processing || !data.token} className={`${THEME_BTN_PRIMARY} w-full !py-4 disabled:opacity-50`}>
                        {processing ? 'Guardando…' : 'Enviar datos fiscales'}
                    </button>
                </form>
            </div>
        </div>
    );
}
