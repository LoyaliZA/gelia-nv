import React from 'react';
import { THEME_LABEL, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import AvisoOperativoPedido from './AvisoOperativoPedido';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import { TEXTO_HERENCIA, importeConversion } from './conversionModalidad';

export default function PanelConversionModalidad({
    id,
    flags = {},
    captura = {},
    motivo,
    onMotivo,
    errorCaptura,
    onErrorCaptura,
    archivos,
    previews,
    onEvidencias,
    onVer,
    ocupado = false,
}) {
    const herencia = flags?.herencia || {};
    const finanzas = flags?.finanzas || {};
    const fisica = flags?.fisica || {};
    const pendienteTransporte = importeConversion(finanzas.pendiente_transporte);

    return (
        <div className="grid gap-4 min-w-0">
            <AvisoOperativoPedido tono="info" label="Consulta que se conserva">
                {TEXTO_HERENCIA[herencia.caso] || TEXTO_HERENCIA.sin_consulta}
            </AvisoOperativoPedido>
            {herencia.caso === 'parcial' && (
                <p className="text-sm theme-text-aviso m-0">Tras confirmar hará falta una revisión complementaria. La consulta no se declara cerrada.</p>
            )}
            {finanzas.transporte_desconocido || captura?.costo_transporte === 'diferido' ? (
                <AvisoOperativoPedido tono="warning" label="Transporte pendiente">
                    El costo de transporte queda pendiente de determinar. No está cubierto y no se registra como cero.
                </AvisoOperativoPedido>
            ) : pendienteTransporte > 0 ? (
                <AvisoOperativoPedido tono="warning" label="Cargo nuevo de transporte">
                    Pendiente de transporte: {pendienteTransporte.toFixed(2)}. Una validación histórica de mercancía no cubre este cargo.
                </AvisoOperativoPedido>
            ) : (
                <AvisoOperativoPedido tono="info" label="Cargos">
                    No hay un cargo nuevo de transporte por cubrir.
                </AvisoOperativoPedido>
            )}
            {(fisica.pendiente || fisica.condicion === 'custodia_excepcion') && (
                <AvisoOperativoPedido tono="warning" label="Movimiento físico">
                    {fisica.mensaje || 'La conversión comercial no mueve la mercancía. La adecuación física queda como operación posterior.'}
                </AvisoOperativoPedido>
            )}
            <fieldset disabled={ocupado} className="grid gap-4 min-w-0">
                <label className={THEME_LABEL} htmlFor={`${id}-motivo-conversion`}>
                    Motivo del cambio
                    <textarea
                        id={`${id}-motivo-conversion`}
                        className={`${THEME_TEXTAREA} w-full mt-1`}
                        rows={3}
                        maxLength={200}
                        value={motivo}
                        onChange={(evento) => onMotivo(evento.target.value)}
                    />
                    <span className="block text-right text-xs theme-text-muted">{motivo.length} / 200</span>
                </label>
                <GaleriaEvidenciasPedido
                    id={`${id}-evidencias-conversion`}
                    label="Evidencia petición cliente"
                    obligatorio={!errorCaptura}
                    maxArchivos={8}
                    disabled={errorCaptura || ocupado}
                    archivos={archivos}
                    previews={previews}
                    onChange={onEvidencias}
                    onVer={onVer}
                />
                <label className="flex gap-2 items-start text-sm theme-text-main">
                    <input
                        type="checkbox"
                        className="gelia-checkbox"
                        checked={errorCaptura}
                        onChange={(evento) => onErrorCaptura(evento.target.checked)}
                    />
                    Notificar un error de captura
                </label>
                <p className="text-xs theme-text-muted m-0">
                    {errorCaptura
                        ? 'Se registrará que se notificó un error de captura. La evidencia deja de ser obligatoria.'
                        : 'Adjunta la petición del cliente. El cambio conserva el mismo pedido.'}
                </p>
            </fieldset>
        </div>
    );
}
