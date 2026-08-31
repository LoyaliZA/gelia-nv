import React, { useState } from 'react';
import { ImageIcon } from 'lucide-react';
import ModalVisorArchivo from '@/Components/ModalVisorArchivo';
import { exhibicionesConEvidencia, payloadVisorEnIndice } from '@/utils/visorEvidenciasReporte';
import {
    fmtMxn,
    SEM_BADGE,
    META_DETALLE,
    IMPORTE_FIN,
    tonoRevisionEstado,
} from './pagosPedidosStyles';
import { AccionesAdminExhibicion } from './AccionesAdminPagos';

const TH = 'text-xs font-semibold uppercase tracking-wide theme-text-main/75 py-3 pr-2 text-left';
const TD = 'text-xs md:text-[13px] theme-text-main py-3 pr-2 align-middle';
const TR = 'border-b border-[color-mix(in_srgb,var(--theme-border)_85%,transparent)]';
const META = `block ${META_DETALLE} leading-snug mt-0.5`;
const BTN_VOUCHER =
    'inline-flex items-center justify-center gap-1 min-h-9 px-2 rounded-lg border theme-border theme-element text-[11px] font-semibold theme-text-main hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-colors disabled:opacity-40 disabled:cursor-not-allowed';

function Indicadores({ ex }) {
    const items = [];
    if (ex.reportado_posteriormente) items.push({ label: 'Reportado posteriormente', cls: SEM_BADGE.advertencia });
    if (ex.posible_duplicado) items.push({ label: 'Posible duplicado', cls: SEM_BADGE.advertencia });
    if (ex.sin_voucher) items.push({ label: 'Sin voucher', cls: SEM_BADGE.neutro });
    if (ex.con_saf_relacionado) items.push({ label: 'Con SAF relacionado', cls: SEM_BADGE.info });
    if (items.length === 0) return <span className={META}>—</span>;

    return (
        <div className="flex flex-wrap gap-1">
            {items.map((i) => (
                <span key={i.label} className={i.cls}>{i.label}</span>
            ))}
        </div>
    );
}

export default function TablaExhibicionesVouchers({ exhibiciones = [], auth, onRecargarLista }) {
    const [visorIdx, setVisorIdx] = useState(null);
    const conEvidencia = exhibicionesConEvidencia(exhibiciones);
    const visor = payloadVisorEnIndice(exhibiciones, visorIdx);

    if (!exhibiciones.length) {
        return <p className="text-xs theme-text-muted m-0">Sin exhibiciones en este grupo.</p>;
    }

    return (
        <div className="min-w-0">
            <div className="overflow-x-auto -mx-1 px-1">
                <table className="w-full min-w-[76rem] text-left border-collapse">
                    <thead>
                        <tr className="border-b theme-border">
                            <th className={TH}>Exhibición</th>
                            <th className={TH}>Pedido</th>
                            <th className={TH}>Remisión</th>
                            <th className={TH}>Cliente</th>
                            <th className={TH}>Monto</th>
                            <th className={TH}>Forma</th>
                            <th className={TH}>Banco</th>
                            <th className={TH}>Referencia</th>
                            <th className={TH}>Movimiento</th>
                            <th className={TH}>Reporte</th>
                            <th className={TH}>Validación</th>
                            <th className={TH}>Estado</th>
                            <th className={TH}>Indicadores</th>
                            <th className={TH}>Admin</th>
                            <th className={`${TH} pr-0`}>Voucher</th>
                        </tr>
                    </thead>
                    <tbody>
                        {exhibiciones.map((ex) => (
                            <tr key={ex.id} className={TR}>
                                <td className={`${TD} font-mono tabular-nums`}>#{ex.numero_exhibicion}</td>
                                <td className={`${TD} font-mono text-xs`}>{ex.folio_pedido || '—'}</td>
                                <td className={`${TD} font-mono text-xs`}>{ex.folio_remision || '—'}</td>
                                <td className={TD}>
                                    <span className="block truncate max-w-[10rem]">{ex.cliente?.nombre || '—'}</span>
                                </td>
                                <td className={`${TD} ${IMPORTE_FIN}`}>{fmtMxn(ex.monto)}</td>
                                <td className={TD}>{ex.forma_pago_label || '—'}</td>
                                <td className={TD}>{ex.banco || '—'}</td>
                                <td className={TD}>
                                    <span className="font-medium tabular-nums">{ex.referencia || '—'}</span>
                                    {ex.reportado_por && <span className={META}>Reportado por: {ex.reportado_por}</span>}
                                </td>
                                <td className={TD}>{ex.fecha_pago_label}</td>
                                <td className={TD}>{ex.capturado_at_label}</td>
                                <td className={TD}>
                                    <span className="block">{ex.validado_at_label}</span>
                                    {ex.validado_por && <span className={META}>Validado por: {ex.validado_por}</span>}
                                </td>
                                <td className={TD}>
                                    <span className={`font-medium ${tonoRevisionEstado(ex.estado_revision)}`}>
                                        {ex.estado_validacion_label}
                                    </span>
                                    {ex.observaciones && <span className={META}>{ex.observaciones}</span>}
                                </td>
                                <td className={TD}><Indicadores ex={ex} /></td>
                                <td className={TD}>
                                    <AccionesAdminExhibicion
                                        auth={auth}
                                        cierreId={ex.cierre_id}
                                        exhibicion={ex}
                                        pedidoTieneError={Boolean(ex.admin_pedido_error)}
                                        onRecargarLista={onRecargarLista}
                                    />
                                </td>
                                <td className={`${TD} pr-0`}>
                                    <button
                                        type="button"
                                        className={BTN_VOUCHER}
                                        disabled={!ex.evidencia?.url}
                                        onClick={() => {
                                            const idx = conEvidencia.findIndex((item) => item.id === ex.id);
                                            if (idx >= 0) setVisorIdx(idx);
                                        }}
                                    >
                                        <ImageIcon className="w-4 h-4" aria-hidden />
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <ModalVisorArchivo
                abierto={visorIdx != null}
                onCerrar={() => setVisorIdx(null)}
                url={visor?.url}
                mimeType={visor?.mimeType}
                titulo={visor?.titulo}
                subtitulo={visor?.subtitulo}
                metadatos={visor?.metadatos}
                indiceActual={visorIdx ?? 0}
                totalItems={conEvidencia.length}
                onAnterior={() => setVisorIdx((i) => Math.max(0, (i ?? 0) - 1))}
                onSiguiente={() => setVisorIdx((i) => Math.min(conEvidencia.length - 1, (i ?? 0) + 1))}
            />
        </div>
    );
}
