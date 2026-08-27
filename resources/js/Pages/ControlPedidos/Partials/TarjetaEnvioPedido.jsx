import React, { useState } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { THEME_INPUT } from '../../../utils/geliaTheme';
import { THEME_LABEL, etiquetaEnvio, formatearMoneda } from './pedidosBmaStyles';
import InputMoneda from './InputMoneda';

/**
 * Tarjeta reutilizable de un envío (caja) del pedido.
 * Identidad = uuid_operativo; el índice visual es solo etiqueta.
 */
export default function TarjetaEnvioPedido({
    caja,
    indice = 0,
    abiertoInicial = false,
    modo = 'lectura', // lectura | costos | pesaje
    costos = null,
    onCostosChange = null,
    documentos = [],
    onVerDoc = null,
    bloqueado = false,
    chip = null,
    incompleto = false,
    /** false = modo legado: no mostrar costos por caja (evita "—" leído como incompleto). */
    mostrarCostos = true,
}) {
    const [abierto, setAbierto] = useState(Boolean(abiertoInicial));
    const uuid = caja?.uuid_operativo || caja?.client_uuid || '';
    const retirado = caja?.estado_operativo === 'retirada';
    const recolectada = caja?.estatus_recoleccion === 'recolectada';

    const costoEnvio = costos?.costo_envio ?? caja?.costo_envio ?? '';
    const costoSeguro = costos?.costo_seguro ?? caja?.costo_seguro ?? '';
    const costoAdicional = costos?.costo_adicional ?? caja?.costo_adicional ?? '';
    const conceptoAdicional = costos?.concepto_adicional ?? caja?.concepto_adicional ?? '';
    const verCostos = modo === 'costos' || (modo === 'lectura' && mostrarCostos);

    const setCampo = (campo, valor) => {
        if (!onCostosChange || bloqueado) return;
        onCostosChange({
            uuid_operativo: uuid,
            costo_envio: costoEnvio,
            costo_seguro: costoSeguro,
            costo_adicional: costoAdicional,
            concepto_adicional: conceptoAdicional,
            [campo]: valor,
        });
    };

    return (
        <div
            className={`rounded-xl border theme-border theme-element overflow-hidden ${retirado ? 'opacity-60' : ''}`}
            data-uuid={uuid || undefined}
        >
            <button
                type="button"
                onClick={() => setAbierto((v) => !v)}
                className="w-full flex items-center justify-between gap-2 p-4 text-left outline-none"
            >
                <div className="min-w-0">
                    <p className="text-sm font-black theme-text-main m-0 truncate">
                        {etiquetaEnvio(indice, caja)}
                        {retirado ? ' · Retirado' : ''}
                        {recolectada ? ' · Recolectado' : ''}
                    </p>
                    <p className="text-[10px] font-bold theme-text-muted m-0 mt-0.5 truncate">
                        {caja?.tipo_caja?.nombre || 'Sin tipo'}
                        {caja?.peso_cobrado_kg != null ? ` · ${caja.peso_cobrado_kg} kg cobrados` : ''}
                    </p>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    {incompleto && (
                        <span className="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-300">
                            Incompleto
                        </span>
                    )}
                    {chip}
                    {abierto ? <ChevronUp className="w-4 h-4 theme-text-muted" /> : <ChevronDown className="w-4 h-4 theme-text-muted" />}
                </div>
            </button>

            {abierto && (
                <div className="px-4 pb-4 space-y-3 border-t theme-border pt-3">
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                        <p className="m-0 theme-text-muted font-bold">Largo: <span className="theme-text-main">{caja?.largo != null ? `${caja.largo} cm` : '—'}</span></p>
                        <p className="m-0 theme-text-muted font-bold">Ancho: <span className="theme-text-main">{caja?.ancho != null ? `${caja.ancho} cm` : '—'}</span></p>
                        <p className="m-0 theme-text-muted font-bold">Alto: <span className="theme-text-main">{caja?.alto != null ? `${caja.alto} cm` : '—'}</span></p>
                        <p className="m-0 theme-text-muted font-bold">Real: <span className="theme-text-main">{caja?.peso_real_kg != null ? `${caja.peso_real_kg} kg` : '—'}</span></p>
                        <p className="m-0 theme-text-muted font-bold">Vol.: <span className="theme-text-main">{caja?.peso_volumetrico_kg != null ? `${caja.peso_volumetrico_kg} kg` : '—'}</span></p>
                        <p className="m-0 theme-text-muted font-bold">Cobrado: <span className="theme-text-main">{caja?.peso_cobrado_kg != null ? `${caja.peso_cobrado_kg} kg` : '—'}</span></p>
                    </div>

                    {verCostos && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className={`${THEME_LABEL} mb-1 block`}>Costo envío</label>
                                {modo === 'costos' && !bloqueado ? (
                                    <InputMoneda value={costoEnvio ?? ''} onChange={(v) => setCampo('costo_envio', v)} className="w-full py-2" />
                                ) : (
                                    <p className="text-sm font-bold theme-text-main m-0">{formatearMoneda(costoEnvio)}</p>
                                )}
                            </div>
                            <div>
                                <label className={`${THEME_LABEL} mb-1 block`}>Costo seguro</label>
                                {modo === 'costos' && !bloqueado ? (
                                    <InputMoneda value={costoSeguro ?? ''} onChange={(v) => setCampo('costo_seguro', v)} className="w-full py-2" />
                                ) : (
                                    <p className="text-sm font-bold theme-text-main m-0">{formatearMoneda(costoSeguro)}</p>
                                )}
                            </div>
                            <div>
                                <label className={`${THEME_LABEL} mb-1 block`}>Costo adicional</label>
                                {modo === 'costos' && !bloqueado ? (
                                    <InputMoneda value={costoAdicional ?? ''} onChange={(v) => setCampo('costo_adicional', v)} className="w-full py-2" />
                                ) : (
                                    <p className="text-sm font-bold theme-text-main m-0">{formatearMoneda(costoAdicional)}</p>
                                )}
                            </div>
                            <div>
                                <label className={`${THEME_LABEL} mb-1 block`}>Concepto adicional</label>
                                {modo === 'costos' && !bloqueado ? (
                                    <input
                                        type="text"
                                        value={conceptoAdicional || ''}
                                        onChange={(e) => setCampo('concepto_adicional', e.target.value)}
                                        className={`${THEME_INPUT} w-full py-2`}
                                        maxLength={128}
                                    />
                                ) : (
                                    <p className="text-sm font-bold theme-text-main m-0">{conceptoAdicional || '—'}</p>
                                )}
                            </div>
                        </div>
                    )}

                    {documentos.length > 0 && (
                        <div className="space-y-1">
                            <p className="text-[9px] font-black uppercase theme-text-muted m-0">Documentos</p>
                            {documentos.map((d) => (
                                <button
                                    key={d.id}
                                    type="button"
                                    onClick={() => onVerDoc?.(d)}
                                    className="block text-xs font-bold underline theme-text-main outline-none"
                                >
                                    {d.nombre_original || d.tipo || `Doc ${d.id}`}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
