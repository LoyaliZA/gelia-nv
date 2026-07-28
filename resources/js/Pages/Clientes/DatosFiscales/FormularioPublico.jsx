import React, { useEffect, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Sun, Moon } from 'lucide-react';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_INPUT,
} from '../../../utils/geliaTheme';
import { aplicarTemaPublico, leerTemaPublicoInicial } from '../../../utils/aplicarTemaPublico';
import SelectorCatalogoFiscal from '../../../Components/Facturas/SelectorCatalogoFiscal';
import {
    esRegimenSueldosSalarios,
    usoCfdiParaRegimen,
    USO_SIN_EFECTOS_FISCALES,
    normalizarRfc,
    errorRfc,
    normalizarRazonSocial,
    normalizarRazonSocialAlEscribir,
} from '../../../utils/reglasCatalogosFiscales';

const LABEL = 'mb-1.5 block text-[9px] font-black uppercase tracking-widest theme-text-muted';
const INPUT_WARN = '!border-orange-500 focus:!border-orange-500 focus:!ring-orange-500/30';
const CAMPO_WARN = 'rounded-xl border-2 border-orange-500 bg-orange-500/5 p-3';
const CAMPO_OK = 'rounded-xl border-2 border-transparent p-3';

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
    branding = null,
}) {
    const initial = { token: token || '' };
    campos.forEach((c) => {
        initial[c.clave] = '';
    });

    const { data, setData, post, processing, errors, setError, clearErrors } = useForm(initial);
    const [isDarkMode, setIsDarkMode] = useState(true);

    const usoBloqueado = esRegimenSueldosSalarios(data.regimen_fiscal);
    const tieneUso = campos.some((c) => c.clave === 'uso_factura');

    useEffect(() => {
        const isDark = leerTemaPublicoInicial();
        setIsDarkMode(isDark);
        aplicarTemaPublico(isDark);
    }, []);

    useEffect(() => {
        if (!tieneUso) return;
        const forzado = usoCfdiParaRegimen(data.regimen_fiscal);
        if (forzado && data.uso_factura !== forzado) {
            setData('uso_factura', forzado);
        }
    }, [data.regimen_fiscal, data.uso_factura, tieneUso, setData]);

    const toggleTheme = () => {
        const next = !isDarkMode;
        setIsDarkMode(next);
        aplicarTemaPublico(next);
    };

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
            if (campo.clave === 'rfc') {
                const msgRfc = errorRfc(val);
                if (msgRfc) {
                    setError('rfc', msgRfc);
                    ok = false;
                }
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
            className="relative min-h-screen px-4 py-10 md:py-14"
            style={{ backgroundColor: 'var(--bg-app, #0a0a0a)' }}
        >
            <Head title="Datos fiscales" />
            <button
                type="button"
                onClick={toggleTheme}
                className="absolute top-4 right-4 md:top-6 md:right-6 z-10 p-2.5 md:p-3 rounded-2xl theme-element border theme-border theme-text-muted hover:text-[var(--color-primario)] transition-all hover:scale-105 outline-none shadow-sm"
                title={isDarkMode ? 'Modo claro' : 'Modo oscuro'}
                aria-label={isDarkMode ? 'Activar modo claro' : 'Activar modo oscuro'}
            >
                {isDarkMode ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
            </button>
            <div className={`mx-auto max-w-3xl ${geliaCardClass()} p-6 md:p-10`}>
                {branding?.url_claro ? (
                    <div className="flex justify-center mb-6">
                        <img
                            src={isDarkMode
                                ? (branding.url_oscuro || branding.url_claro)
                                : branding.url_claro}
                            alt={branding.alt || branding.departamento || 'Logo'}
                            className="h-24 md:h-28 w-auto max-w-[340px] object-contain"
                        />
                    </div>
                ) : (
                    <p className="text-[10px] font-black uppercase tracking-[0.35em] m-0 text-center" style={{ color: 'var(--color-primario)' }}>
                        Gelia NV
                    </p>
                )}
                <h1 className="mt-2 text-3xl md:text-4xl font-black italic tracking-tighter uppercase theme-text-main m-0 text-center">
                    Datos fiscales
                </h1>
                <p className="mt-3 text-sm theme-text-muted m-0 text-center">
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
                                    <div
                                        key={campo.clave}
                                        className={`${spanAncho ? 'md:col-span-2' : ''} ${tieneError ? CAMPO_WARN : CAMPO_OK}`}
                                    >
                                        <label className={`${LABEL} ${tieneError ? 'text-orange-500' : ''}`}>
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
                                                className={`${THEME_INPUT} ${tieneError ? INPUT_WARN : ''}`}
                                                value={data[campo.clave] || ''}
                                                onChange={(e) => {
                                                    let val = e.target.value;
                                                    if (campo.clave === 'rfc') val = normalizarRfc(val);
                                                    if (campo.clave === 'codigo_postal') val = val.replace(/\D/g, '').slice(0, 5);
                                                    if (campo.clave === 'correo_electronico') val = val.toLowerCase();
                                                    if (campo.clave === 'telefono') val = val.replace(/\D/g, '').slice(0, 10);
                                                    if (campo.clave === 'nombre_razon_social') val = normalizarRazonSocialAlEscribir(val);
                                                    setData(campo.clave, val);
                                                    if (errors[campo.clave]) clearErrors(campo.clave);
                                                }}
                                                onBlur={() => {
                                                    if (campo.clave === 'nombre_razon_social') {
                                                        setData('nombre_razon_social', normalizarRazonSocial(data.nombre_razon_social));
                                                        return;
                                                    }
                                                    if (campo.clave === 'correo_electronico') {
                                                        const val = String(data.correo_electronico || '').trim();
                                                        if (val && !esCorreoValido(val)) {
                                                            setError('correo_electronico', 'Ingrese un correo electrónico válido.');
                                                        }
                                                        return;
                                                    }
                                                    if (campo.clave === 'rfc') {
                                                        const val = String(data.rfc || '').trim();
                                                        if (!val) return;
                                                        const msgRfc = errorRfc(val);
                                                        if (msgRfc) setError('rfc', msgRfc);
                                                    }
                                                }}
                                                required
                                                minLength={campo.clave === 'rfc' ? 12 : undefined}
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
                                            <p className="mt-1 text-xs text-orange-600 dark:text-orange-400 font-bold m-0">{errors[campo.clave]}</p>
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
