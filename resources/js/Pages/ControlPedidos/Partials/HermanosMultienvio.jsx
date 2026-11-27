import React from 'react';
import { CheckCircle2, Clock, Receipt, Truck } from 'lucide-react';

function estado(hermano) {
    if (['ENVIADO', 'ENTREGADO'].includes(hermano.estatus_fase)) return { Icono: Truck, texto: 'Enviado' };
    if (hermano.tiene_guia) return { Icono: Receipt, texto: 'Con guía' };
    if (hermano.pago_validado && hermano.tiene_remision) return { Icono: CheckCircle2, texto: 'Remisionado' };
    if (hermano.pago_validado) return { Icono: CheckCircle2, texto: 'Pago validado' };

    return { Icono: Clock, texto: 'Pago por validar' };
}

const dinero = (v) => Number(v || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

/** Aviso informativo: la misma referencia de pago aparece en varios envíos del grupo (transferencia compartida). */
export function AvisoComprobanteCompartido({ pedido }) {
    const avisos = pedido?.multienvio?.comprobantes_compartidos || [];
    if (avisos.length === 0) return null;

    return (
        <div data-aviso-comprobante-compartido role="note" className="rounded-xl border theme-border theme-element px-3 py-2 text-sm theme-text-main">
            {avisos.map((a) => (
                <p key={a.referencia} className="m-0">
                    La referencia {a.referencia} también está en {a.envios.filter((n) => n !== pedido.multienvio.orden).map((n) => `Envío ${n}`).join(', ')}.
                    {' '}Suma de las partes registradas: {dinero(a.suma)}. Verifica solo el importe de este envío.
                </p>
            ))}
        </div>
    );
}

/**
 * Envíos hermanos de un Multienvío en una bandeja: icono + texto por envío, solo los que el usuario puede ver.
 * Con `onAbrir` cada chip filtra la bandeja por el folio de ese envío; sin él es informativo.
 */
export default function HermanosMultienvio({ pedido, onAbrir = null, className = '' }) {
    const grupo = pedido?.multienvio;
    const hermanos = (grupo?.hermanos || []).filter((h) => h.id !== pedido.id);
    if (hermanos.length === 0) return null;
    const ordenados = [...hermanos].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0));

    return (
        <ul data-hermanos-multienvio aria-label="Otros envíos del multienvío" className={`m-0 flex list-none flex-wrap gap-1.5 p-0 ${className}`}>
            {ordenados.map((h) => {
                const { Icono, texto } = estado(h);
                const referencia = h.folio_remision || h.folio;
                const contenido = (
                    <>
                        <Icono className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                        <span className="font-bold">Envío {h.orden}</span>
                        <span className="theme-text-muted">{texto}</span>
                    </>
                );
                const clases = 'inline-flex items-center gap-1 rounded-md border theme-border px-2 py-1 text-xs theme-text-main';

                return (
                    <li key={h.id}>
                        {onAbrir ? (
                            <button type="button" className={`${clases} min-h-9`} title={`Buscar ${referencia}`}
                                onClick={(e) => { e.stopPropagation(); onAbrir(referencia); }}>
                                {contenido}
                            </button>
                        ) : (
                            <span className={clases} title={referencia}>{contenido}</span>
                        )}
                    </li>
                );
            })}
            {grupo?.parcial && <li className="text-xs theme-text-muted self-center">Solo envíos a tu alcance</li>}
        </ul>
    );
}
