import React from 'react';
import { GELIA_BTN_OUTLINE } from '../../../../utils/geliaTheme';

export default function PanelRestauracion({ preview, seleccion, onToggleCampo, onConfirmar, busy, puedeRestaurar }) {
    if (!preview) {
        return null;
    }

    return (
        <div className="border theme-border rounded-xl p-3 space-y-3">
            <h3 className="text-sm font-black uppercase tracking-widest m-0">Restauración</h3>
            {preview.requiere_conciliacion && (
                <p className="text-xs theme-text-muted m-0">
                    Concilié primero. Una descarga o una aprobación sin entrega no alcanza para restaurar.
                </p>
            )}
            {preview.tiene_conflictos && (
                <p className="text-xs m-0">Hay cambios posteriores. La compensación exigirá revisión, no se escribe de inmediato.</p>
            )}
            <div className="space-y-2 max-h-[40vh] overflow-auto">
                {(preview.filas || []).map((fila) => (
                    <article key={fila.variante_id} className="border theme-border rounded-lg p-2 text-sm">
                        <p className="font-semibold m-0">{fila.nombre}</p>
                        <p className="text-xs theme-text-muted m-0">SKU {fila.sku || '—'}</p>
                        {!fila.restaurable && <p className="text-xs m-0">{fila.motivo_bloqueo}</p>}
                        {fila.restaurable && Object.values(fila.campos || {}).map((campo) => (
                            <label key={campo.campo} className="flex items-start gap-2 mt-1">
                                <input
                                    type="checkbox"
                                    checked={!!seleccion[`${fila.variante_id}:${campo.campo}`]}
                                    disabled={!campo.restaurable}
                                    onChange={() => onToggleCampo(fila.variante_id, campo.campo)}
                                />
                                <span>
                                    <span className="uppercase text-[10px] tracking-widest">{campo.campo}</span>
                                    {' '}actual {campo.actual ?? '—'} → restaurar {campo.intencion === 'eliminar' ? 'eliminar' : (campo.restaurar_a ?? '—')}
                                    {!campo.restaurable && <span className="block text-xs theme-text-muted">{campo.explicacion}</span>}
                                    {campo.conflicto && <span className="block text-xs">Conflicto: {campo.explicacion}</span>}
                                </span>
                            </label>
                        ))}
                    </article>
                ))}
            </div>
            {puedeRestaurar && (
                <button type="button" disabled={busy} onClick={onConfirmar} className={GELIA_BTN_OUTLINE}>
                    Crear propuesta de restauración
                </button>
            )}
        </div>
    );
}
