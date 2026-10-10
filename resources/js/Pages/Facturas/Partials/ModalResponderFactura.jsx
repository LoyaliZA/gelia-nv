import AdjuntoRespuesta, { useAdjuntoRespuesta } from '@/Components/Solicitudes/AdjuntoRespuesta';
import SolicitudDialog from '@/Components/Solicitudes/SolicitudDialog';
import React, { useEffect, useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { X, CheckCircle2, AlertOctagon, Upload, Send, Wrench } from 'lucide-react';
import {
    ACCENT,
    BTN_PRIMARY,
    BTN_SECONDARY,
    BTN_DANGER,
    TAB_ACTIVO_PELIGRO,
    TAB_ACTIVO_PRIMARIO,
    CAMPO_SELECCIONADO_PELIGRO,
    CAMPO_CHECKBOX_LABEL,
    INPUT_ERROR,
    TEXTO_ERROR,
} from './facturasStyles';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_LABEL } from '../../../utils/geliaTheme';
import ZonaAdjuntoPdf from './ZonaAdjuntoPdf';
import FormularioDatosFiscalesInline from './FormularioDatosFiscalesInline';
import { GRUPOS_CAMPOS_ERROR_FACTURA, esCampoFiscalError } from './camposFacturaErrores';
import { archivoExcedeLimite } from './limitesAdjuntosFactura';

function GridCamposError({ grupos, camposSel, esError, onToggle }) {
    return (
        <div className="space-y-4">
            {grupos.map((grupo) => (
                <div key={grupo.id}>
                    <p className={`${THEME_LABEL} mb-2`}>{grupo.titulo}</p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        {grupo.campos.map(({ clave, etiqueta }) => {
                            const seleccionado = camposSel.includes(clave);
                            return (
                                <label
                                    key={clave}
                                    className={`${CAMPO_CHECKBOX_LABEL} ${
                                        esError && seleccionado ? CAMPO_SELECCIONADO_PELIGRO : ''
                                    }`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={seleccionado}
                                        onChange={() => onToggle(clave)}
                                        className="rounded border theme-border shrink-0"
                                        style={{ accentColor: esError ? 'var(--color-peligro)' : 'var(--color-primario)' }}
                                    />
                                    <span className="theme-text-main">{etiqueta}</span>
                                </label>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function ModalResponderFactura({
    onClose,
    factura,
    estadoId,
    modo = 'emitir',
    catalogos = { regimen_fiscal: [], uso_cfdi: [] },
    onExito,
}) {
    const esAprobacion = modo === 'emitir';
    const esError = modo === 'reportar';
    const [subModo, setSubModo] = useState('reportar');
    const [pdfs, setPdfs] = useState([]);
    const [camposSel, setCamposSel] = useState([]);
    const [generarEnlace, setGenerarEnlace] = useState(true);
    const [archivoFiscal, setArchivoFiscal] = useState(null);
    const [fiscales, setFiscales] = useState(() => ({ ...(factura?.datos_fiscales || {}) }));
    const [procesandoCorregir, setProcesandoCorregir] = useState(false);
    const [enviandoFactura, setEnviandoFactura] = useState(false);
    const [erroresEmision, setErroresEmision] = useState({});

    const { data, setData, post, processing, errors, transform, isDirty } = useForm({
        catalogo_estado_solicitud_id: estadoId,
        motivo: '',
        campos_incorrectos: [],
        generar_enlace_fiscal: true,
        factura_xml: null,
        evidencia_error: null,
        _method: 'put',
    });


    const toggleCampo = (clave) => {
        setCamposSel((prev) => (
            prev.includes(clave) ? prev.filter((c) => c !== clave) : [...prev, clave]
        ));
    };

    const hayFiscalesMarcados = useMemo(
        () => camposSel.some(esCampoFiscalError),
        [camposSel]
    );

    useEffect(() => {
        if (hayFiscalesMarcados) setGenerarEnlace(true);
    }, [hayFiscalesMarcados]);

    const attachment = useAdjuntoRespuesta(data.evidencia_error, (file) => setData('evidencia_error', file));
    const cerrar = () => onClose();

    const enviarReporte = (e) => {
        e.preventDefault();
        if (camposSel.length === 0) return;

        transform((d) => ({
            ...d,
            campos_incorrectos: camposSel,
            generar_enlace_fiscal: hayFiscalesMarcados && generarEnlace ? '1' : '0',
        }));

        post(route('facturas.actualizar_estado', factura.id), {
            forceFormData: true,
            onSuccess: () => {
                onExito?.();
                cerrar();
            },
        });
    };

    const enviarCorreccion = (e) => {
        e.preventDefault();
        setProcesandoCorregir(true);

        const form = new FormData();
        form.append('_method', 'put');
        if (data.motivo) form.append('motivo', data.motivo);
        if (data.razon_social) form.append('razon_social', data.razon_social);

        const fiscalesMarcados = camposSel.filter(esCampoFiscalError);
        fiscalesMarcados.forEach((clave) => {
            if (fiscales[clave]) form.append(`datos_fiscales[${clave}]`, fiscales[clave]);
        });
        if (camposSel.includes('archivo_fiscal') && archivoFiscal) {
            form.append('archivo_fiscal', archivoFiscal);
        }
        camposSel.forEach((c) => form.append('campos_corregidos[]', c));

        router.post(route('facturas.corregir', factura.id), form, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                onExito?.();
                cerrar();
            },
            onFinish: () => setProcesandoCorregir(false),
        });
    };

    const titulo = esError
        ? (subModo === 'corregir' ? 'Corregir factura' : 'Reportar error en factura')
        : 'Emitir factura';

    return (
        <SolicitudDialog onClose={cerrar} busy={processing || procesandoCorregir || enviandoFactura || attachment.busy} title="Responder solicitud de factura" dirty={isDirty || pdfs.length > 0 || camposSel.length > 0 || !!archivoFiscal}>
            <div
                onPaste={attachment.onPaste}
                className={`${THEME_MODAL_SHELL} max-w-4xl w-full flex flex-col text-left`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="flex items-center gap-3 min-w-0">
                        {esError
                            ? <AlertOctagon className="w-7 h-7 shrink-0" style={{ color: 'var(--color-peligro)' }} />
                            : <CheckCircle2 className="w-7 h-7 shrink-0" style={{ color: ACCENT }} />}
                        <div className="min-w-0">
                            <h2 className="text-lg font-black italic theme-text-main uppercase m-0 leading-tight">{titulo}</h2>
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">{factura.folio}</p>
                        </div>
                    </div>
                    <button type="button" data-dialog-close onClick={cerrar} className="p-2 theme-text-muted hover:theme-text-main rounded-full hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none shrink-0" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {esError && (
                    <div className="px-5 md:px-6 pt-4 flex gap-2 shrink-0">
                        <button
                            type="button"
                            className={`${BTN_SECONDARY} flex-1 !py-2 text-[9px] ${subModo === 'reportar' ? TAB_ACTIVO_PELIGRO : ''}`}
                            onClick={() => setSubModo('reportar')}
                        >
                            Reportar a vendedora
                        </button>
                        <button
                            type="button"
                            className={`${BTN_SECONDARY} flex-1 !py-2 text-[9px] ${subModo === 'corregir' ? TAB_ACTIVO_PRIMARIO : ''}`}
                            onClick={() => setSubModo('corregir')}
                        >
                            <Wrench className="w-3.5 h-3.5 inline mr-1" />
                            Corregir ahora
                        </button>
                    </div>
                )}

                <form
                    onSubmit={esAprobacion ? (e) => {
                        e.preventDefault();
                        if (enviandoFactura) return;
                        setEnviandoFactura(true);
                        setErroresEmision({});
                        const formData = new FormData();
                        formData.append('_method', 'put');
                        formData.append('catalogo_estado_solicitud_id', estadoId);
                        if (data.motivo) formData.append('motivo', data.motivo);
                        pdfs.forEach((f) => formData.append('factura_pdfs[]', f));
                        if (data.factura_xml) formData.append('factura_xml', data.factura_xml);
                        router.post(route('facturas.actualizar_estado', factura.id), formData, {
                            forceFormData: true,
                            onSuccess: () => { onExito?.(); cerrar(); },
                            onError: setErroresEmision,
                            onFinish: () => setEnviandoFactura(false),
                        });
                    } : (subModo === 'corregir' ? enviarCorreccion : enviarReporte)}
                    className="flex flex-col flex-1 min-h-0"
                >
                    <div className="gelia-modal-body p-5 md:p-6 space-y-6">
                    <dl className="gelia-respuesta-contexto">
                        <div><dt>Razón social</dt><dd>{factura.razon_social || 'Sin razón social'}</dd></div>
                        <div><dt>RFC</dt><dd>{factura.datos_fiscales?.rfc || 'Sin RFC registrado'}</dd></div>
                    </dl>
                    {Object.values(erroresEmision).map((mensaje, index) => <p key={index} role="alert" className="text-sm theme-text-peligro">{mensaje}</p>)}
                    {esError && (
                        <div className="space-y-2">
                            {esError && (
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                                    {subModo === 'corregir' ? 'Seleccione qué corrige' : 'Seleccione los campos con error'}
                                </p>
                            )}
                            <GridCamposError
                                grupos={GRUPOS_CAMPOS_ERROR_FACTURA}
                                camposSel={camposSel}
                                esError={esError}
                                onToggle={toggleCampo}
                            />
                            {esError && subModo === 'reportar' && camposSel.length === 0 && (
                                <p className={`text-xs font-bold m-0 ${TEXTO_ERROR}`}>Seleccione al menos un campo.</p>
                            )}
                        </div>
                    )}

                    {esError && subModo === 'reportar' && hayFiscalesMarcados && (
                        <label className="flex items-center gap-2 text-xs font-bold theme-text-main cursor-pointer">
                            <input
                                type="checkbox"
                                checked={generarEnlace}
                                onChange={(e) => setGenerarEnlace(e.target.checked)}
                                style={{ accentColor: 'var(--color-primario)' }}
                            />
                            Generar enlace de corrección fiscal para el cliente
                        </label>
                    )}

                    {esError && subModo === 'corregir' && camposSel.some(esCampoFiscalError) && (
                        <FormularioDatosFiscalesInline
                            valores={fiscales}
                            onChange={setFiscales}
                            catalogos={catalogos}
                            camposVisibles={camposSel.filter(esCampoFiscalError)}
                        />
                    )}

                    {esError && subModo === 'corregir' && camposSel.includes('archivo_fiscal') && (
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Excel fiscal</label>
                            <input
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={(e) => {
                                    const f = e.target.files?.[0];
                                    if (f && archivoExcedeLimite(f)) {
                                        e.target.value = '';
                                        return;
                                    }
                                    setArchivoFiscal(f || null);
                                }}
                                className="text-xs"
                            />
                        </div>
                    )}

                    {esError && subModo === 'corregir' && camposSel.includes('razon_social') && (
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Razón social</label>
                            <input
                                type="text"
                                defaultValue={factura.razon_social || ''}
                                onChange={(e) => setData('razon_social', e.target.value)}
                                className="w-full p-3 theme-surface border theme-border rounded-xl text-sm font-bold"
                            />
                        </div>
                    )}

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                                Observaciones (opcional)
                            </label>
                            <textarea id="factura-respuesta-observaciones" name="motivo" autoComplete="off"
                                rows={4}
                                value={data.motivo}
                                onChange={(e) => setData('motivo', e.target.value)}
                                disabled={processing || enviandoFactura || procesandoCorregir}
                                className={`w-full p-4 theme-surface border ${errors.motivo ? INPUT_ERROR : 'theme-border'} rounded-xl theme-text-main text-sm font-bold outline-none resize-none`}
                            />
                            {errors.motivo && <p className={`text-xs font-bold ${TEXTO_ERROR}`}>{errors.motivo}</p>}
                            {errors.campos_incorrectos && <p className={`text-xs font-bold ${TEXTO_ERROR}`}>{errors.campos_incorrectos}</p>}
                        </div>

                        <div className="space-y-4">
                            {esAprobacion && (
                                <>
                                    <ZonaAdjuntoPdf archivos={pdfs} onChange={setPdfs} error={errors.factura_pdfs || erroresEmision.factura_pdfs} disabled={enviandoFactura} />
                                    <div className="space-y-2">
                                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">XML CFDI (opcional)</label>
                                        <label className="gelia-adjunto-control flex flex-col items-center justify-center border-2 border-dashed theme-border rounded-2xl p-4 cursor-pointer hover:border-[var(--color-primario)] transition-colors">
                                            <Upload className="w-6 h-6 theme-text-muted mb-1" />
                                            <span className="text-[10px] font-bold theme-text-muted uppercase">
                                                {data.factura_xml ? data.factura_xml.name : 'Adjuntar XML'}
                                            </span>
                                            <input
                                                type="file"
                                                className="sr-only"
                                                aria-label="Adjuntar XML de la factura"
                                                disabled={enviandoFactura}
                                                accept=".xml,application/xml,text/xml"
                                                onChange={(e) => {
                                                    const f = e.target.files?.[0];
                                                    if (f && archivoExcedeLimite(f)) { setErroresEmision({ factura_xml: 'El XML excede 5 MB. Selecciona un archivo más pequeño.' }); return; }
                                                    setErroresEmision({});
                                                    setData('factura_xml', f || null);
                                                }}
                                            />
                                        </label>
                                    </div>
                                </>
                            )}

                            {esError && subModo === 'reportar' && <AdjuntoRespuesta file={data.evidencia_error} attachment={attachment} error={errors.evidencia_error} disabled={processing || enviandoFactura} />}


                        </div>
                    </div>
                    </div>
                    <div className="gelia-modal-footer gelia-workflow-actions">
                        <button type="button" data-dialog-close disabled={processing || procesandoCorregir || enviandoFactura || attachment.busy} className={BTN_SECONDARY}>Cancelar</button>
                            <button
                                type="submit"
                                disabled={processing || procesandoCorregir || enviandoFactura || attachment.busy || (esError && subModo === 'reportar' && camposSel.length === 0) || (esAprobacion && pdfs.length === 0)}
                                className={`${esError && subModo === 'reportar' ? BTN_DANGER : BTN_PRIMARY} !py-3 disabled:opacity-50`}
                            >
                                <Send className="w-4 h-4 inline mr-2" />
                                {processing || procesandoCorregir || enviandoFactura ? 'Guardando…' : esAprobacion ? 'Enviar factura' : subModo === 'reportar' ? 'Enviar correcciones a ventas' : 'Guardar correcciones'}
                            </button>
                    </div>
                </form>
            </div>
        </SolicitudDialog>
    );
}
