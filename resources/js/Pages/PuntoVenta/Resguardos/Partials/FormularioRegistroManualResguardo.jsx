import React, { useEffect, useState } from 'react';
import { FileText, Loader2 } from 'lucide-react';
import {
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
    THEME_SELECT,
} from '../../../../utils/geliaTheme';
import BusquedaClienteResguardo from './BusquedaClienteResguardo';
import BotonesCapturaEvidencia from './BotonesCapturaEvidencia';
import useToastAlCambiar from '../../../../hooks/useToastAlCambiar';

function AdjuntoRegistroManual({
    etiqueta,
    archivo,
    preview,
    error,
    esDocumento = false,
    deshabilitado,
    onSeleccionar,
    acceptGaleria,
    etiquetaGaleria,
}) {
    return (
        <div className="space-y-2">
            <p className={`${THEME_LABEL} m-0`}>{etiqueta}</p>
            <BotonesCapturaEvidencia
                onAgregar={(archivos) => onSeleccionar(archivos?.[0] || null)}
                deshabilitado={deshabilitado}
                etiquetaGaleria={etiquetaGaleria}
                camaraMultiple={false}
                galeriaMultiple={false}
                acceptGaleria={acceptGaleria}
            />
            {archivo && (
                <div className="rounded-2xl border theme-border overflow-hidden">
                    {preview && !esDocumento ? (
                        <img src={preview} alt={etiqueta} className="w-full h-32 object-cover" />
                    ) : (
                        <div className="flex items-center gap-2 px-3 py-3 min-h-[44px]">
                            <FileText className="w-4 h-4 shrink-0 theme-text-muted" aria-hidden />
                            <span className="text-xs font-semibold theme-text-main truncate">{archivo.name}</span>
                        </div>
                    )}
                </div>
            )}
            {error && (
                <p className="text-xs font-semibold theme-text-peligro m-0">{error}</p>
            )}
        </div>
    );
}

export default function FormularioRegistroManualResguardo({
    enviando = false,
    error = null,
    origenes = [],
    onEnviar,
    onCancelar,
}) {
    const [cliente, setCliente] = useState(null);
    const [folio, setFolio] = useState('');
    const [origenId, setOrigenId] = useState('');
    const [cantidadBultos, setCantidadBultos] = useState('1');
    const [archivoTicket, setArchivoTicket] = useState(null);
    const [fotoPaquete, setFotoPaquete] = useState(null);
    const [previewTicket, setPreviewTicket] = useState(null);
    const [previewPaquete, setPreviewPaquete] = useState(null);
    const [erroresLocales, setErroresLocales] = useState({});

    useToastAlCambiar(error, 'error');

    const ticketEsDocumento = Boolean(archivoTicket && (archivoTicket.type === 'application/pdf' || /\.pdf$/i.test(archivoTicket.name || '')));
    const evidenciaLista = Boolean(archivoTicket && fotoPaquete);

    useEffect(() => () => {
        if (previewTicket) URL.revokeObjectURL(previewTicket);
        if (previewPaquete) URL.revokeObjectURL(previewPaquete);
    }, [previewTicket, previewPaquete]);

    const asignarTicket = (archivo) => {
        if (previewTicket) URL.revokeObjectURL(previewTicket);
        setArchivoTicket(archivo);
        setPreviewTicket(archivo && archivo.type?.startsWith('image/') ? URL.createObjectURL(archivo) : null);
    };

    const asignarPaquete = (archivo) => {
        if (previewPaquete) URL.revokeObjectURL(previewPaquete);
        setFotoPaquete(archivo);
        setPreviewPaquete(archivo && archivo.type?.startsWith('image/') ? URL.createObjectURL(archivo) : null);
    };

    const enviar = async (event) => {
        event.preventDefault();
        const errores = {};
        if (!cliente?.id) {
            errores.cliente_id = 'Seleccione una cuenta de cliente.';
        }
        const folioLimpio = folio.trim();
        if (!folioLimpio) {
            errores.folio = 'Indique el folio del resguardo.';
        }
        if (!origenId) {
            errores.origen_id = 'Seleccione el área de origen.';
        }
        const bultos = Number.parseInt(cantidadBultos, 10);
        if (!Number.isInteger(bultos) || bultos < 1) {
            errores.cantidad_bultos_esperada = 'Indique al menos un bulto o bolsa.';
        }
        if ((archivoTicket && !fotoPaquete) || (!archivoTicket && fotoPaquete)) {
            errores.archivo_ticket = 'Adjunte ticket y foto del paquete juntos, o déjelos para después.';
        }
        if (Object.keys(errores).length > 0) {
            setErroresLocales(errores);
            return;
        }

        setErroresLocales({});
        const payload = {
            cliente_id: cliente.id,
            folio: folioLimpio,
            origen_id: Number.parseInt(origenId, 10),
            cantidad_bultos_esperada: bultos,
            archivo_ticket: archivoTicket,
            foto_paquete: fotoPaquete,
        };

        await onEnviar?.(payload);
    };

    return (
        <form onSubmit={enviar} className="space-y-4">
            <BusquedaClienteResguardo
                clienteSeleccionado={cliente}
                onSeleccionar={setCliente}
                onLimpiar={() => setCliente(null)}
                deshabilitado={enviando}
            />
            {erroresLocales.cliente_id && (
                <p className="text-xs font-semibold theme-text-peligro m-0">{erroresLocales.cliente_id}</p>
            )}

            <div className="space-y-2">
                <label className={THEME_LABEL} htmlFor="folio-resguardo-manual">
                    Folio
                </label>
                <input
                    id="folio-resguardo-manual"
                    type="text"
                    value={folio}
                    onChange={(event) => setFolio(event.target.value)}
                    disabled={enviando}
                    maxLength={64}
                    placeholder="Folio o remisión"
                    className={`${THEME_INPUT} min-h-[44px]`}
                />
                {erroresLocales.folio && (
                    <p className="text-xs font-semibold theme-text-peligro m-0">{erroresLocales.folio}</p>
                )}
            </div>

            <div className="space-y-2">
                <label className={THEME_LABEL} htmlFor="origen-resguardo-manual">
                    Área de origen
                </label>
                <div className="theme-field-with-icon theme-field-with-icon--has-trailing relative">
                    <select
                        id="origen-resguardo-manual"
                        value={origenId}
                        onChange={(event) => setOrigenId(event.target.value)}
                        disabled={enviando}
                        className={`${THEME_SELECT} min-h-[44px]`}
                    >
                        <option value="">Seleccione unidad de origen</option>
                        {origenes.map((origen) => (
                            <option key={origen.id} value={origen.id}>{origen.nombre}</option>
                        ))}
                    </select>
                </div>
                {erroresLocales.origen_id && (
                    <p className="text-xs font-semibold theme-text-peligro m-0">{erroresLocales.origen_id}</p>
                )}
            </div>

            <div className="space-y-2">
                <label className={THEME_LABEL} htmlFor="bultos-resguardo-manual">
                    Bultos / bolsas a recibir
                </label>
                <input
                    id="bultos-resguardo-manual"
                    type="number"
                    min={1}
                    max={500}
                    value={cantidadBultos}
                    onChange={(event) => setCantidadBultos(event.target.value)}
                    disabled={enviando}
                    className={`${THEME_INPUT} min-h-[44px]`}
                />
                {erroresLocales.cantidad_bultos_esperada && (
                    <p className="text-xs font-semibold theme-text-peligro m-0">{erroresLocales.cantidad_bultos_esperada}</p>
                )}
            </div>

            <div className="space-y-2">
                <p className={`${THEME_LABEL} m-0`}>Evidencia del flujo</p>
                <p className="text-xs theme-text-muted m-0">
                    Ticket y foto del paquete se reutilizan en recepción y custodia. Puede guardar sin ellas y completarlas en la tarjeta.
                </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <AdjuntoRegistroManual
                    etiqueta="Foto o archivo del ticket"
                    archivo={archivoTicket}
                    preview={previewTicket}
                    error={erroresLocales.archivo_ticket}
                    esDocumento={ticketEsDocumento}
                    deshabilitado={enviando}
                    onSeleccionar={asignarTicket}
                    acceptGaleria="image/*,application/pdf"
                    etiquetaGaleria="Archivo"
                />
                <AdjuntoRegistroManual
                    etiqueta="Foto del paquete"
                    archivo={fotoPaquete}
                    preview={previewPaquete}
                    error={erroresLocales.foto_paquete}
                    deshabilitado={enviando}
                    onSeleccionar={asignarPaquete}
                    acceptGaleria="image/*"
                    etiquetaGaleria="Galería"
                />
            </div>

            <div className="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                <button
                    type="button"
                    onClick={onCancelar}
                    disabled={enviando}
                    className={`${THEME_BTN_SECONDARY} min-h-[44px]`}
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    disabled={enviando}
                    className={`${THEME_BTN_PRIMARY} min-h-[44px] inline-flex items-center justify-center gap-2 text-[10px] font-black uppercase tracking-widest`}
                >
                    {enviando && <Loader2 className="w-4 h-4 animate-spin" aria-hidden />}
                    {evidenciaLista ? 'Dejar en recepción' : 'Guardar pendiente'}
                </button>
            </div>
        </form>
    );
}
