import React, { useEffect, useState } from 'react';
import { Loader2, Package, UserRound } from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import { titularResguardo, etiquetaRetiroResguardo } from './resguardosUtils';
import { ChipEvidenciasBultosEmpaque } from './ModalEvidenciasBultosEmpaque';
import BusquedaProductoResguardo from './BusquedaProductoResguardo';
import BotonesCapturaEvidencia from './BotonesCapturaEvidencia';
import PanelFaltantesResguardo from './PanelFaltantesResguardo';
import useToastAlCambiar from '../../../../hooks/useToastAlCambiar';

function CampoSoloLectura({ label, value }) {
    return (
        <div className="space-y-1">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
            <p className="text-sm font-bold theme-text-main m-0 break-words">{value || '—'}</p>
        </div>
    );
}

export default function FormularioConfirmarRecepcionResguardo({
    resguardo,
    flujoCompleto = false,
    enviando = false,
    progreso = 0,
    error = null,
    onEnviar,
}) {
    const cantidadBultos = resguardo?.cantidad_bultos_esperada ?? 0;
    const bultosCedis = resguardo?.bultos_empaque_cedis || [];
    const titular = titularResguardo(resguardo);
    const etiquetaRetiro = etiquetaRetiroResguardo(resguardo);
    const registroManual = resguardo?.registro_manual;
    const corrigePiezas = Boolean(flujoCompleto && registroManual);
    const usosEvidencia = new Set((registroManual?.evidencias || []).map((item) => item.uso));
    const [piezas, setPiezas] = useState([]);
    const [errorPiezas, setErrorPiezas] = useState(null);
    const [archivoTicket, setArchivoTicket] = useState(null);
    const [fotoPaquete, setFotoPaquete] = useState(null);
    const [motivoOmision, setMotivoOmision] = useState('');

    useEffect(() => {
        const iniciales = (registroManual?.piezas || []).map((linea) => ({
            producto_id: linea.producto_id,
            sku: linea.sku,
            descripcion: linea.descripcion,
            cantidad: linea.cantidad || 1,
            nota: linea.nota || '',
            estado_fisico: linea.estado_fisico || 'bueno',
            unica_pieza: Boolean(linea.unica_pieza),
            mejor_ejemplar: Boolean(linea.mejor_ejemplar),
            evidencia_ids: (linea.evidencia_ids || (linea.evidencias || []).map((foto) => foto.id)).filter(Boolean),
        }));
        setPiezas(iniciales);
    }, [resguardo?.id, registroManual]);

    useToastAlCambiar(error, 'error');

    const faltaTicket = Boolean(registroManual) && !usosEvidencia.has('ticket') && !archivoTicket;
    const faltaPaquete = Boolean(registroManual) && !usosEvidencia.has('paquete') && !fotoPaquete;
    const faltaPiezas = corrigePiezas && piezas.length < 1;
    const hayFaltantes = faltaPiezas || faltaTicket || faltaPaquete;

    const confirmarEnvio = async (e) => {
        e.preventDefault();
        const motivo = motivoOmision.trim();
        if (hayFaltantes && motivo.length < 8) {
            setErrorPiezas('Indique el motivo para omitir los datos que faltan, o regístrelos ahora.');
            return;
        }
        setErrorPiezas(null);
        const omisiones = [];
        if (faltaPiezas) omisiones.push({ campo: 'piezas', motivo });
        if (faltaTicket) omisiones.push({ campo: 'ticket', motivo });
        if (faltaPaquete) omisiones.push({ campo: 'foto_paquete', motivo });
        const payload = {};
        if (corrigePiezas && piezas.length > 0) {
            payload.piezas = piezas.map((linea) => ({
                producto_id: linea.producto_id,
                cantidad: Number.parseInt(linea.cantidad, 10) || 1,
                nota: String(linea.nota || '').trim() || null,
                estado_fisico: linea.estado_fisico || 'bueno',
                unica_pieza: Boolean(linea.unica_pieza),
                mejor_ejemplar: Boolean(linea.mejor_ejemplar),
                evidencia_ids: linea.evidencia_ids || [],
            }));
        }
        if (omisiones.length > 0) payload.omisiones = omisiones;
        if (archivoTicket) payload.archivoTicket = archivoTicket;
        if (fotoPaquete) payload.fotoPaquete = fotoPaquete;
        await onEnviar?.(payload);
    };

    return (
        <form onSubmit={confirmarEnvio} className="space-y-5">
            <div className={`${geliaCardClass()} p-5 space-y-4`}>
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Resumen del resguardo</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <CampoSoloLectura label="Folio" value={resguardo.snapshot_folio || `#${resguardo.id}`} />
                    <CampoSoloLectura label="Cliente" value={titular} />
                    <CampoSoloLectura label="Quién retira" value={etiquetaRetiro} />
                    <CampoSoloLectura label="Bultos esperados" value={String(cantidadBultos)} />
                </div>
            </div>

            {bultosCedis.length > 0 && (
                <div className={`${geliaCardClass()} p-5 space-y-3`}>
                    <div className="flex items-center gap-2">
                        <Package className="w-4 h-4 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Bultos registrados en CEDIS</h2>
                    </div>
                    <ChipEvidenciasBultosEmpaque
                        bultos={bultosCedis}
                        folio={resguardo.snapshot_folio}
                    />
                </div>
            )}

            <div className={`${geliaCardClass()} p-4 flex items-start gap-3 border border-[color-mix(in_srgb,var(--color-info)_35%,transparent)]`}>
                <UserRound className="w-5 h-5 text-[var(--color-info)] shrink-0 mt-0.5" />
                <p className="text-sm theme-text-muted m-0">
                    {corrigePiezas
                        ? 'Confirme la llegada y ajuste las piezas si el conteo no coincide. Ticket y foto del paquete ya capturados se reutilizan.'
                        : 'Solo confirma que el paquete llegó a sucursal. El recepcionista revisará bultos y piezas después.'}
                </p>
            </div>

            {hayFaltantes && (
                <PanelFaltantesResguardo
                    detalle="Registre las piezas o las imágenes que faltan, u omítalas con un motivo para que el pedido continúe."
                    motivo={motivoOmision}
                    onMotivo={setMotivoOmision}
                    error={errorPiezas}
                    deshabilitado={enviando}
                >
                    {faltaTicket && (
                        <div className="space-y-2">
                            <p className="text-xs font-semibold theme-text-main m-0">Ticket</p>
                            <BotonesCapturaEvidencia
                                onAgregar={(archivos) => setArchivoTicket(archivos?.[0] || null)}
                                deshabilitado={enviando}
                                camaraMultiple={false}
                                galeriaMultiple={false}
                                acceptGaleria="image/*,application/pdf"
                                etiquetaGaleria="Archivo"
                            />
                        </div>
                    )}
                    {faltaPaquete && (
                        <div className="space-y-2">
                            <p className="text-xs font-semibold theme-text-main m-0">Foto del paquete</p>
                            <BotonesCapturaEvidencia
                                onAgregar={(archivos) => setFotoPaquete(archivos?.[0] || null)}
                                deshabilitado={enviando}
                                camaraMultiple={false}
                                galeriaMultiple={false}
                            />
                        </div>
                    )}
                </PanelFaltantesResguardo>
            )}

            {corrigePiezas && (
                <div className={`${geliaCardClass()} p-5 space-y-3`}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Piezas del pedido</h2>
                    <BusquedaProductoResguardo
                        lineas={piezas}
                        onAgregar={(producto) => setPiezas((prev) => {
                            const existente = prev.find((linea) => linea.producto_id === producto.id);
                            if (existente) {
                                return prev.map((linea) => (
                                    linea.producto_id === producto.id
                                        ? { ...linea, cantidad: Math.min(9999, Number(linea.cantidad) + 1) }
                                        : linea
                                ));
                            }
                            return [...prev, {
                                producto_id: producto.id,
                                sku: producto.sku,
                                descripcion: producto.descripcion,
                                cantidad: 1,
                                nota: '',
                            }];
                        })}
                        onQuitar={(productoId) => setPiezas((prev) => prev.filter((linea) => linea.producto_id !== productoId))}
                        onCambiarCantidad={(productoId, cantidad) => setPiezas((prev) => prev.map((linea) => (
                            linea.producto_id === productoId ? { ...linea, cantidad } : linea
                        )))}
                        onCambiarNota={(productoId, nota) => setPiezas((prev) => prev.map((linea) => (
                            linea.producto_id === productoId ? { ...linea, nota } : linea
                        )))}
                        deshabilitado={enviando}
                    />
                </div>
            )}

            <div className="flex flex-col sm:flex-row gap-2">
                <button
                    type="submit"
                    disabled={enviando}
                    className={`${THEME_BTN_PRIMARY} flex-1 min-h-[48px] inline-flex items-center justify-center gap-2`}
                >
                    {enviando ? (
                        <>
                            <Loader2 className="w-4 h-4 animate-spin" />
                            Confirmando… {progreso > 0 ? `${progreso}%` : ''}
                        </>
                    ) : (
                        'Confirmar recepción'
                    )}
                </button>
            </div>
        </form>
    );
}
