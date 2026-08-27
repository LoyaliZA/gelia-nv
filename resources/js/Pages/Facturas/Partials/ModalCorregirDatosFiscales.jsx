import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { X, Link2, Copy, Check, Loader2 } from 'lucide-react';
import { FACTURA_ACCENT, BTN_PRIMARY, BTN_SECONDARY } from './facturasStyles';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';

const CAMPOS_FISCALES = [
    { clave: 'rfc', etiqueta: 'RFC' },
    { clave: 'codigo_postal', etiqueta: 'Código Postal' },
    { clave: 'regimen_fiscal', etiqueta: 'Régimen Fiscal' },
    { clave: 'correo_electronico', etiqueta: 'Correo Electrónico' },
    { clave: 'uso_factura', etiqueta: 'Uso de CFDI' },
    { clave: 'nombre_razon_social', etiqueta: 'Nombre (Razón Social)' },
    { clave: 'telefono', etiqueta: 'Número Telefónico' },
];

async function copiarAlPortapapeles(texto) {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(texto);
            return true;
        }
    } catch {
        /* fallback */
    }
    const ta = document.createElement('textarea');
    ta.value = texto;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    return true;
}

export default function ModalCorregirDatosFiscales({ onClose, factura, onExito }) {
    const [campos, setCampos] = useState(() => {
        const prev = factura?.campos_fiscales_solicitados;
        return Array.isArray(prev) && prev.length > 0 ? prev : CAMPOS_FISCALES.map((c) => c.clave);
    });
    const [enlaceUrl, setEnlaceUrl] = useState(null);
    const [generando, setGenerando] = useState(false);
    const [error, setError] = useState(null);
    const [copiado, setCopiado] = useState(false);

    useEffect(() => {
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = 'unset'; };
    }, []);

    const toggleCampo = (clave) => {
        setCampos((prev) => (
            prev.includes(clave) ? prev.filter((c) => c !== clave) : [...prev, clave]
        ));
    };

    const generar = async () => {
        if (!factura?.id || campos.length === 0) return;
        setGenerando(true);
        setError(null);
        try {
            const { data: res } = await axios.post(route('facturas.enlace_fiscal', factura.id), {
                campos_fiscales: campos,
                accion_formulario: 'update_fields',
            });
            if (res?.url) {
                setEnlaceUrl(res.url);
                const ok = await copiarAlPortapapeles(res.url);
                if (ok) {
                    setCopiado(true);
                    setTimeout(() => setCopiado(false), 2000);
                }
                onExito?.();
            }
        } catch (err) {
            const msg = err?.response?.data?.errors?.enlace?.[0]
                || err?.response?.data?.message
                || 'No se pudo generar el enlace.';
            setError(msg);
        } finally {
            setGenerando(false);
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
                        <h2 className="text-lg font-black italic theme-text-main uppercase tracking-tighter m-0">
                            Corregir datos fiscales_
                        </h2>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">
                            {factura?.folio} · el cliente actualiza campos sin cambiar el estado
                        </p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-xl theme-element border theme-border outline-none" aria-label="Cerrar">
                        <X className="w-4 h-4 theme-text-muted" />
                    </button>
                </div>

                <div className="p-5 md:p-6 space-y-4 overflow-y-auto custom-scrollbar flex-1 min-h-0">
                    <p className="text-xs font-bold theme-text-muted m-0 leading-relaxed">
                        Seleccione los campos a corregir y genere un enlace para que el cliente los actualice.
                        La solicitud permanece en Respondida; PDF/XML emitidos no se modifican.
                    </p>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        {CAMPOS_FISCALES.map(({ clave, etiqueta }) => (
                            <label
                                key={clave}
                                className="flex items-center gap-2 p-3 rounded-xl theme-element border theme-border cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    checked={campos.includes(clave)}
                                    onChange={() => toggleCampo(clave)}
                                    className="w-4 h-4"
                                    style={{ accentColor: FACTURA_ACCENT }}
                                />
                                <span className="text-[10px] font-black uppercase theme-text-main">{etiqueta}</span>
                            </label>
                        ))}
                    </div>

                    {error && (
                        <p className="text-xs font-bold text-red-600 m-0">{error}</p>
                    )}

                    {enlaceUrl && (
                        <div className="p-3 rounded-xl theme-element border theme-border space-y-2">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 flex items-center gap-1">
                                <Link2 className="w-3 h-3" /> Enlace listo para compartir
                            </p>
                            <p className="text-[11px] font-mono theme-text-main break-all m-0">{enlaceUrl}</p>
                            <button
                                type="button"
                                className={`${BTN_SECONDARY} !py-2 !px-3 text-[9px]`}
                                onClick={async () => {
                                    const ok = await copiarAlPortapapeles(enlaceUrl);
                                    if (ok) {
                                        setCopiado(true);
                                        setTimeout(() => setCopiado(false), 2000);
                                    }
                                }}
                            >
                                {copiado ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
                                {copiado ? 'Copiado' : 'Copiar'}
                            </button>
                        </div>
                    )}
                </div>

                <div className="p-5 md:p-6 border-t theme-border flex flex-wrap gap-2 justify-end shrink-0">
                    <button type="button" className={BTN_SECONDARY} onClick={onClose}>Cerrar</button>
                    <button
                        type="button"
                        className={BTN_PRIMARY}
                        disabled={generando || campos.length === 0}
                        onClick={generar}
                    >
                        {generando ? <Loader2 className="w-4 h-4 animate-spin" /> : <Link2 className="w-4 h-4" />}
                        Generar enlace
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
