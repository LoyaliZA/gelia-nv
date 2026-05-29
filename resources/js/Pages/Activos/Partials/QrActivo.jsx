import React, { useState } from 'react';
import { Copy, Check, Eye, Download } from 'lucide-react';
import ModalVistaPreviaConsulta from './ModalVistaPreviaConsulta';
import { getActivosCardClass, LABEL_CLASS } from './activosFormStyles';
import useDispositivoCampo from './useDispositivoCampo';
import { descargarEtiquetaActivo } from './descargarEtiquetaActivo';

export default function QrActivo({ activo, puedeEditar = false, compacto = false }) {
    const [copiado, setCopiado] = React.useState(false);
    const [previewAbierta, setPreviewAbierta] = useState(false);
    const [descargando, setDescargando] = useState(false);
    const { esMovil } = useDispositivoCampo();

    if (!activo?.id || !activo?.consulta_token) return null;

    const qrSrc = route('activos.qr', activo.id);
    const qrPngSrc = route('activos.qr_png', activo.id);
    const consultaUrl = route('activos.consulta.publica', activo.consulta_token, true);
    const urlEditar = route('activos.show', activo.id);
    const esCompacto = compacto || esMovil;

    const copiarFolio = async () => {
        try {
            await navigator.clipboard.writeText(activo.folio);
            setCopiado(true);
            setTimeout(() => setCopiado(false), 2000);
        } catch {
            // clipboard no disponible
        }
    };

    const descargarEtiqueta = async () => {
        if (descargando) return;
        setDescargando(true);
        try {
            await descargarEtiquetaActivo({
                activo,
                qrPngSrc,
                tipoNombre: activo.tipo?.nombre,
            });
        } catch {
            // fallo al generar imagen
        } finally {
            setDescargando(false);
        }
    };

    return (
        <>
            <div className={`${getActivosCardClass(`${esCompacto ? 'p-3' : 'p-4'} ${esCompacto ? 'flex flex-row items-center gap-3 text-left' : 'flex flex-col items-center gap-3 text-center'}`.trim())} shrink-0`}>
                <div className={`${esCompacto ? 'shrink-0' : ''} p-2 rounded-2xl bg-white border theme-border`}>
                    <img
                        src={qrSrc}
                        alt={`QR ${activo.folio}`}
                        width={esCompacto ? 72 : 120}
                        height={esCompacto ? 72 : 120}
                        className="block"
                    />
                </div>

                <div className={`${esCompacto ? 'flex-1 min-w-0 space-y-1.5' : 'space-y-2'}`}>
                    {!esCompacto && <p className={`${LABEL_CLASS} m-0`}>Consulta rápida</p>}
                    {esCompacto && <p className={`${LABEL_CLASS} m-0 text-left`}>QR consulta</p>}
                    <p className={`text-[10px] theme-text-muted m-0 ${esCompacto ? 'text-left' : ''}`}>
                        Escanea para consultar sin cuenta
                    </p>
                    <button
                        type="button"
                        onClick={copiarFolio}
                        className={`inline-flex items-center gap-1.5 text-[10px] font-black uppercase theme-text-main hover:opacity-80 ${esCompacto ? '' : ''}`}
                    >
                        {copiado ? <Check className="w-3 h-3 text-green-600" /> : <Copy className="w-3 h-3" />}
                        {activo.folio}
                    </button>
                    <button
                        type="button"
                        onClick={() => setPreviewAbierta(true)}
                        className={`inline-flex items-center gap-1.5 text-[10px] font-black uppercase hover:opacity-80 ${esCompacto ? 'block text-left' : 'mx-auto'}`}
                        style={{ color: 'var(--color-primario)' }}
                    >
                        <Eye className="w-3 h-3" />
                        Vista previa consulta
                    </button>
                    <button
                        type="button"
                        onClick={descargarEtiqueta}
                        disabled={descargando}
                        className={`inline-flex items-center gap-1.5 text-[10px] font-black uppercase hover:opacity-80 disabled:opacity-50 ${esCompacto ? 'block text-left' : 'mx-auto'}`}
                        style={{ color: 'var(--color-primario)' }}
                    >
                        <Download className="w-3 h-3" />
                        {descargando ? 'Generando...' : 'Descargar etiqueta'}
                    </button>
                </div>
            </div>

            <ModalVistaPreviaConsulta
                abierto={previewAbierta}
                onCerrar={() => setPreviewAbierta(false)}
                consultaUrl={consultaUrl}
                puedeEditar={puedeEditar}
                urlEditar={urlEditar}
            />
        </>
    );
}
