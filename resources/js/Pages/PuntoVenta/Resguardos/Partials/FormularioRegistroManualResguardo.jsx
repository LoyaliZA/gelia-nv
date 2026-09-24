import React, { useEffect, useMemo, useState } from 'react';
import { FileText, Loader2 } from 'lucide-react';
import {
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
    THEME_SELECT,
    THEME_TEXTAREA,
} from '../../../../utils/geliaTheme';
import BusquedaClienteResguardo from './BusquedaClienteResguardo';
import BusquedaProductoResguardo from './BusquedaProductoResguardo';
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
    const [cantidadPiezas, setCantidadPiezas] = useState('');
    const [piezas, setPiezas] = useState([]);
    const [observaciones, setObservaciones] = useState('');
    const [enviaTercero, setEnviaTercero] = useState(false);
    const [nombreTercero, setNombreTercero] = useState('');
    const [archivoTicket, setArchivoTicket] = useState(null);
    const [fotoPaquete, setFotoPaquete] = useState(null);
    const [previewTicket, setPreviewTicket] = useState(null);
    const [previewPaquete, setPreviewPaquete] = useState(null);
    const [erroresLocales, setErroresLocales] = useState({});

    useToastAlCambiar(error, 'error');

    const ticketEsDocumento = Boolean(archivoTicket && (archivoTicket.type === 'application/pdf' || /\.pdf$/i.test(archivoTicket.name || '')));
    const piezasDerivadas = useMemo(
        () => piezas.reduce((suma, linea) => suma + (Number.parseInt(linea.cantidad, 10) || 0), 0),
        [piezas],
    );
    const hayLineasPiezas = piezas.length > 0;

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

    const agregarPieza = (producto) => {
        setPiezas((actuales) => {
            const existente = actuales.find((linea) => linea.producto_id === producto.id);
            if (existente) {
                return actuales.map((linea) => (
                    linea.producto_id === producto.id
                        ? { ...linea, cantidad: linea.cantidad + 1 }
                        : linea
                ));
            }

            return [...actuales, {
                producto_id: producto.id,
                sku: producto.sku,
                descripcion: producto.descripcion,
                cantidad: 1,
            }];
        });
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
        if (enviaTercero && !nombreTercero.trim()) {
            errores.envia_otra_persona = 'Indique el nombre de quien retira.';
        }
        if (!archivoTicket) {
            errores.archivo_ticket = 'Adjunte la foto o el archivo del ticket.';
        }
        if (!fotoPaquete) {
            errores.foto_paquete = 'Adjunte la foto del paquete.';
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
            envia_a_otra_persona: enviaTercero,
            envia_otra_persona: enviaTercero ? nombreTercero.trim() : null,
            observaciones: observaciones.trim() || null,
            archivo_ticket: archivoTicket,
            foto_paquete: fotoPaquete,
        };

        if (hayLineasPiezas) {
            payload.piezas = piezas.map((linea) => ({
                producto_id: linea.producto_id,
                cantidad: Number.parseInt(linea.cantidad, 10) || 1,
            }));
        } else {
            const piezasNumero = Number.parseInt(cantidadPiezas, 10);
            if (Number.isInteger(piezasNumero) && piezasNumero > 0) {
                payload.cantidad_piezas = piezasNumero;
            }
        }

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

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                    <label className={THEME_LABEL} htmlFor="piezas-resguardo-manual">
                        Número de piezas
                    </label>
                    <input
                        id="piezas-resguardo-manual"
                        type="number"
                        min={1}
                        max={99999}
                        value={hayLineasPiezas ? String(piezasDerivadas) : cantidadPiezas}
                        onChange={(event) => setCantidadPiezas(event.target.value)}
                        disabled={enviando || hayLineasPiezas}
                        placeholder="Opcional"
                        className={`${THEME_INPUT} min-h-[44px]`}
                    />
                    <p className="text-[10px] font-semibold theme-text-muted m-0">
                        Opcional. Si registra productos, se calcula solo.
                    </p>
                </div>
            </div>

            <div className="space-y-2">
                <p className={`${THEME_LABEL} m-0`}>Piezas del paquete (opcional)</p>
                <BusquedaProductoResguardo
                    lineas={piezas}
                    deshabilitado={enviando}
                    onAgregar={agregarPieza}
                    onQuitar={(productoId) => setPiezas((actuales) => actuales.filter((linea) => linea.producto_id !== productoId))}
                    onCambiarCantidad={(productoId, valor) => {
                        const cantidad = Number.parseInt(valor, 10);
                        setPiezas((actuales) => actuales.map((linea) => (
                            linea.producto_id === productoId
                                ? { ...linea, cantidad: Number.isInteger(cantidad) && cantidad > 0 ? cantidad : 1 }
                                : linea
                        )));
                    }}
                />
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

            <div className="space-y-2">
                <label className={THEME_LABEL} htmlFor="obs-resguardo-manual">
                    Observaciones
                </label>
                <textarea
                    id="obs-resguardo-manual"
                    value={observaciones}
                    onChange={(event) => setObservaciones(event.target.value)}
                    disabled={enviando}
                    maxLength={2000}
                    rows={3}
                    placeholder="Notas opcionales del registro"
                    className={THEME_TEXTAREA}
                />
            </div>

            <label className="flex items-center gap-3 min-h-[44px] cursor-pointer">
                <input
                    type="checkbox"
                    checked={enviaTercero}
                    onChange={(event) => setEnviaTercero(event.target.checked)}
                    disabled={enviando}
                    className="w-5 h-5 rounded border theme-border"
                />
                <span className="text-sm font-semibold theme-text-main">Retira una persona distinta a la titular</span>
            </label>

            {enviaTercero && (
                <div className="space-y-2">
                    <label className={THEME_LABEL} htmlFor="tercero-resguardo-manual">
                        Nombre de quien retira
                    </label>
                    <input
                        id="tercero-resguardo-manual"
                        type="text"
                        value={nombreTercero}
                        onChange={(event) => setNombreTercero(event.target.value)}
                        disabled={enviando}
                        maxLength={255}
                        className={`${THEME_INPUT} min-h-[44px]`}
                    />
                    {erroresLocales.envia_otra_persona && (
                        <p className="text-xs font-semibold theme-text-peligro m-0">{erroresLocales.envia_otra_persona}</p>
                    )}
                </div>
            )}

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
                    Registrar
                </button>
            </div>
        </form>
    );
}
