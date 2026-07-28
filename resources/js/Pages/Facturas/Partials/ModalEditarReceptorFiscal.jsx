import React, { useEffect, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import {
    THEME_INPUT,
    THEME_LABEL,
    THEME_TEXTAREA,
    THEME_BTN_PRIMARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '../../../utils/geliaTheme';
import SelectorCatalogoFiscal from '../../../Components/Facturas/SelectorCatalogoFiscal';
import {
    esRegimenSueldosSalarios,
    usoCfdiParaRegimen,
    USO_SIN_EFECTOS_FISCALES,
    normalizarRazonSocial,
    normalizarRazonSocialAlEscribir,
} from '../../../utils/reglasCatalogosFiscales';

const CAMPOS = [
    ['rfc', 'RFC'],
    ['codigo_postal', 'Código postal'],
    ['regimen_fiscal', 'Régimen fiscal'],
    ['correo_electronico', 'Correo electrónico'],
    ['uso_factura', 'Uso de CFDI'],
    ['nombre_razon_social', 'Nombre / razón social'],
    ['telefono', 'Número telefónico'],
];

const INPUT_ERROR = '!border-red-500 focus:!border-red-500 focus:!ring-red-500/30';

function esCorreoValido(valor) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(valor || '').trim());
}

function opcionesParaCampo(catalogos, key, valorActual) {
    const lista = key === 'regimen_fiscal'
        ? (catalogos?.regimen_fiscal || [])
        : key === 'uso_factura'
            ? (catalogos?.uso_cfdi || [])
            : null;

    if (!lista) return null;

    const codigo = String(valorActual || '').trim();
    if (codigo && !lista.some((op) => op.codigo === codigo)) {
        return [{ codigo, nombre: `${codigo} (valor actual)` }, ...lista];
    }

    return lista;
}

export default function ModalEditarReceptorFiscal({
    receptor,
    catalogos = { regimen_fiscal: [], uso_cfdi: [] },
    onClose,
}) {
    const editando = Boolean(receptor);
    const regimenInicial = receptor?.regimen_fiscal || '';
    const usoInicial = usoCfdiParaRegimen(regimenInicial) || receptor?.uso_factura || '';

    const { data, setData, post, put, processing, errors, setError, clearErrors } = useForm({
        rfc: receptor?.rfc || '',
        codigo_postal: receptor?.codigo_postal || '',
        regimen_fiscal: regimenInicial,
        correo_electronico: receptor?.correo_electronico || '',
        uso_factura: usoInicial,
        nombre_razon_social: receptor?.nombre_razon_social || '',
        telefono: receptor?.telefono || '',
        activo: receptor ? Boolean(receptor.activo) : true,
        notas: receptor?.notas || '',
    });

    const usoBloqueado = esRegimenSueldosSalarios(data.regimen_fiscal);

    useEffect(() => {
        const forzado = usoCfdiParaRegimen(data.regimen_fiscal);
        if (forzado && data.uso_factura !== forzado) {
            setData('uso_factura', forzado);
        }
    }, [data.regimen_fiscal, data.uso_factura, setData]);

    const opcionesPorCampo = useMemo(() => ({
        regimen_fiscal: opcionesParaCampo(catalogos, 'regimen_fiscal', data.regimen_fiscal),
        uso_factura: usoBloqueado
            ? (catalogos?.uso_cfdi || []).filter((o) => o.codigo === USO_SIN_EFECTOS_FISCALES)
                .concat(
                    (catalogos?.uso_cfdi || []).some((o) => o.codigo === USO_SIN_EFECTOS_FISCALES)
                        ? []
                        : [{ codigo: USO_SIN_EFECTOS_FISCALES, nombre: 'Sin efectos fiscales' }]
                )
            : opcionesParaCampo(catalogos, 'uso_factura', data.uso_factura),
    }), [catalogos, data.regimen_fiscal, data.uso_factura, usoBloqueado]);

    const guardar = (e) => {
        e.preventDefault();
        clearErrors();

        const correo = String(data.correo_electronico || '').trim();
        if (correo && !esCorreoValido(correo)) {
            setError('correo_electronico', 'Ingrese un correo electrónico válido.');
            return;
        }

        const opciones = { preserveScroll: true, onSuccess: () => onClose() };

        if (editando) {
            put(route('facturas.datos_fiscales.receptores.update', receptor.id), opciones);
        } else {
            post(route('facturas.datos_fiscales.receptores.store'), opciones);
        }
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full flex flex-col text-left`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="min-w-0">
                        <h3 className="text-lg font-black italic uppercase theme-text-main m-0 leading-tight">
                            {editando ? 'Editar receptor' : 'Nuevo receptor'}
                        </h3>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1.5 m-0">
                            {editando ? `${receptor.codigo_interno} — receptor fiscal (tercero)` : 'Receptor fiscal (tercero)'}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none shrink-0"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <form onSubmit={guardar} className="flex flex-col flex-1 min-h-0" noValidate>
                    <div className="gelia-modal-body p-5 md:p-6 space-y-4">
                        {editando && (
                            <div className="space-y-1.5">
                                <label className={THEME_LABEL}>Código interno</label>
                                <input
                                    type="text"
                                    value={receptor.codigo_interno}
                                    disabled
                                    readOnly
                                    className={`${THEME_INPUT} opacity-60 cursor-not-allowed`}
                                />
                            </div>
                        )}

                        {CAMPOS.map(([key, label]) => {
                            const opciones = opcionesPorCampo[key];
                            const esUsoBloqueado = key === 'uso_factura' && usoBloqueado;
                            const tieneError = Boolean(errors[key]);
                            return (
                                <div key={key} className={`space-y-1.5 ${opciones ? 'relative z-10' : ''}`}>
                                    <label className={`${THEME_LABEL} ${tieneError ? '!text-red-500' : ''}`}>{label}</label>
                                    {opciones ? (
                                        <SelectorCatalogoFiscal
                                            opciones={opciones}
                                            value={data[key]}
                                            onChange={(codigo) => {
                                                clearErrors(key);
                                                if (key === 'regimen_fiscal') {
                                                    const forzado = usoCfdiParaRegimen(codigo);
                                                    setData((prev) => ({
                                                        ...prev,
                                                        regimen_fiscal: codigo,
                                                        ...(forzado ? { uso_factura: forzado } : {}),
                                                    }));
                                                    return;
                                                }
                                                setData(key, codigo);
                                            }}
                                            disabled={esUsoBloqueado}
                                            invalid={tieneError}
                                        />
                                    ) : (
                                        <input
                                            type={key === 'correo_electronico' ? 'email' : 'text'}
                                            value={data[key]}
                                            onChange={(e) => {
                                                let val = e.target.value;
                                                if (key === 'correo_electronico') val = val.toLowerCase();
                                                if (key === 'telefono') val = val.replace(/\D/g, '').slice(0, 10);
                                                if (key === 'rfc') val = val.toUpperCase().replace(/[^A-ZÑ&0-9]/gi, '').slice(0, 13);
                                                if (key === 'codigo_postal') val = val.replace(/\D/g, '').slice(0, 5);
                                                if (key === 'nombre_razon_social') val = normalizarRazonSocialAlEscribir(val);
                                                setData(key, val);
                                                if (errors[key]) clearErrors(key);
                                            }}
                                            onBlur={() => {
                                                if (key === 'nombre_razon_social') {
                                                    setData('nombre_razon_social', normalizarRazonSocial(data.nombre_razon_social));
                                                    return;
                                                }
                                                if (key !== 'correo_electronico') return;
                                                const val = String(data.correo_electronico || '').trim();
                                                if (val && !esCorreoValido(val)) {
                                                    setError('correo_electronico', 'Ingrese un correo electrónico válido.');
                                                }
                                            }}
                                            className={`${THEME_INPUT} ${tieneError ? INPUT_ERROR : ''}`}
                                            maxLength={key === 'telefono' ? 10 : key === 'rfc' ? 13 : key === 'codigo_postal' ? 5 : undefined}
                                            inputMode={key === 'telefono' || key === 'codigo_postal' ? 'numeric' : key === 'correo_electronico' ? 'email' : undefined}
                                            autoCapitalize={key === 'correo_electronico' ? 'none' : key === 'rfc' ? 'characters' : undefined}
                                            autoCorrect={key === 'correo_electronico' || key === 'rfc' ? 'off' : undefined}
                                            aria-invalid={tieneError}
                                        />
                                    )}
                                    {esUsoBloqueado && (
                                        <p className="text-[10px] font-bold theme-text-muted m-0">
                                            Fijo por régimen Sueldos y Salarios.
                                        </p>
                                    )}
                                    {tieneError && <p className="text-xs text-red-500 font-bold m-0">{errors[key]}</p>}
                                </div>
                            );
                        })}

                        <div className="space-y-1.5">
                            <label className={THEME_LABEL}>Notas</label>
                            <textarea
                                value={data.notas}
                                onChange={(e) => setData('notas', e.target.value)}
                                rows={3}
                                maxLength={2000}
                                className={THEME_TEXTAREA}
                                placeholder="Notas internas del receptor…"
                            />
                        </div>

                        <label className="flex items-center gap-3 cursor-pointer pt-1">
                            <input
                                type="checkbox"
                                checked={data.activo}
                                onChange={(e) => setData('activo', e.target.checked)}
                                className="w-4 h-4 rounded accent-[var(--color-primario)] cursor-pointer"
                            />
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">
                                Receptor activo
                            </span>
                        </label>
                    </div>
                    <div className="gelia-modal-footer p-5 md:p-6">
                        <button type="submit" disabled={processing} className={`${THEME_BTN_PRIMARY} w-full`}>
                            <Save className="w-4 h-4 shrink-0" />
                            {processing ? 'Guardando…' : editando ? 'Guardar cambios' : 'Crear receptor'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
