import React, { useState } from 'react';
import ModalVisorArchivo from '@/Components/ModalVisorArchivo';
import { exhibicionesConEvidencia, payloadVisorEnIndice } from '@/utils/visorEvidenciasReporte';
import {
    TH_EXH,
    TD_EXH,
    TD_EXH_MULTILINEA,
    TR_EXH,
    CeldaPago,
    CeldaBancoReferencia,
    CeldaFechasExhibicion,
    CeldaValidacionExhibicion,
    CeldaCoberturaExhibicion,
    BotonEvidenciaVoucher,
} from './CeldasExhibicionTabla';
import { AccionesAdminExhibicion } from './AccionesAdminPagos';
import { NOMBRE_CLIENTE, FOLIO_META } from './pagosPedidosStyles';

export default function TablaExhibicionesVouchers({ exhibiciones = [], auth, onActualizado }) {
    const [visorIdx, setVisorIdx] = useState(null);
    const conEvidencia = exhibicionesConEvidencia(exhibiciones);
    const visor = payloadVisorEnIndice(exhibiciones, visorIdx);

    if (!exhibiciones.length) {
        return <p className="text-xs theme-text-muted m-0">Sin exhibiciones en este grupo.</p>;
    }

    return (
        <div className="min-w-0">
            <div className="overflow-x-auto -mx-1 px-1">
                <table className="w-full min-w-[64rem] text-left border-collapse">
                    <thead>
                        <tr className="border-b theme-border">
                            <th className={TH_EXH}>Exhibición</th>
                            <th className={TH_EXH}>Pedido</th>
                            <th className={TH_EXH}>Remisión</th>
                            <th className={TH_EXH}>Cliente</th>
                            <th className={TH_EXH}>Pago</th>
                            <th className={TH_EXH}>Banco y referencia</th>
                            <th className={TH_EXH}>Fechas</th>
                            <th className={TH_EXH}>Validación del pago</th>
                            <th className={TH_EXH}>Cobertura</th>
                            <th className={TH_EXH}>Revisión administrativa</th>
                            <th className={`${TH_EXH} pr-0`}>Evidencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        {exhibiciones.map((ex) => (
                            <tr key={ex.id} className={TR_EXH}>
                                <td className={`${TD_EXH} font-mono tabular-nums`}>#{ex.numero_exhibicion}</td>
                                <td className={`${TD_EXH} font-mono ${FOLIO_META}`}>{ex.folio_pedido || '—'}</td>
                                <td className={`${TD_EXH} font-mono ${FOLIO_META}`}>{ex.folio_remision || '—'}</td>
                                <td className={TD_EXH}>
                                    <span className={`block truncate max-w-[10rem] ${NOMBRE_CLIENTE}`}>{ex.cliente?.nombre || '—'}</span>
                                </td>
                                <td className={TD_EXH}>
                                    <CeldaPago ex={ex} />
                                </td>
                                <td className={TD_EXH}>
                                    <CeldaBancoReferencia ex={ex} />
                                </td>
                                <td className={TD_EXH_MULTILINEA}>
                                    <CeldaFechasExhibicion ex={ex} />
                                </td>
                                <td className={TD_EXH_MULTILINEA}>
                                    <CeldaValidacionExhibicion ex={ex} />
                                </td>
                                <td className={TD_EXH}>
                                    <CeldaCoberturaExhibicion ex={ex} exhibiciones={exhibiciones} />
                                </td>
                                <td className={TD_EXH_MULTILINEA}>
                                    <AccionesAdminExhibicion
                                        auth={auth}
                                        cierreId={ex.cierre_id}
                                        exhibicion={ex}
                                        pedidoTieneError={Boolean(ex.admin_pedido_error)}
                                        onActualizado={onActualizado}
                                    />
                                </td>
                                <td className={`${TD_EXH} pr-0`}>
                                    <BotonEvidenciaVoucher ex={ex} conEvidencia={conEvidencia} onAbrirIndice={setVisorIdx} />
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
