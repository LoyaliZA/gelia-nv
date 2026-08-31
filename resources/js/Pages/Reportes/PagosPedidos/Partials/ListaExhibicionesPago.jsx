import React, { useState } from 'react';
import ModalVisorArchivo from '@/Components/ModalVisorArchivo';
import { exhibicionesConEvidencia, payloadVisorEnIndice } from '@/utils/visorEvidenciasReporte';
import { fmtMxn, SECCION_TITULO, BLOQUE_GAP, IMPORTE_FIN, FOLIO_META, CARD_PAD } from './pagosPedidosStyles';
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
import { EtiquetaCoberturaExhibicion } from './EtiquetaEstadoCategorizado';
import { AccionesAdminExhibicion } from './AccionesAdminPagos';

function TarjetaExhibicionMovil({ ex, exhibiciones, conEvidencia, onAbrirIndice, auth, cierreId, pedidoTieneError, onAdminActualizado, onRecargarLista }) {
    return (
        <div className={`${TR_EXH} ${CARD_PAD} rounded-lg border theme-border theme-element space-y-3`}>
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-[11px] font-semibold uppercase theme-text-muted m-0">
                        Exhibición #{ex.numero_exhibicion}
                    </p>
                    <p className={`${IMPORTE_FIN} m-0 mt-1`}>
                        {fmtMxn(ex.monto)}
                    </p>
                    <p className={`${FOLIO_META} m-0 mt-0.5`}>{ex.forma_pago_label}</p>
                </div>
                <EtiquetaCoberturaExhibicion ex={ex} exhibiciones={exhibiciones} />
            </div>
            <CeldaBancoReferencia ex={ex} />
            <CeldaFechasExhibicion ex={ex} />
            <CeldaValidacionExhibicion ex={ex} />
            <div className="pt-3 border-t theme-border">
                <AccionesAdminExhibicion
                auth={auth}
                cierreId={cierreId}
                exhibicion={ex}
                pedidoTieneError={pedidoTieneError}
                onActualizado={onAdminActualizado}
                onRecargarLista={onRecargarLista}
            />
            </div>
            <BotonEvidenciaVoucher ex={ex} conEvidencia={conEvidencia} onAbrirIndice={onAbrirIndice} />
        </div>
    );
}

export default function ListaExhibicionesPago({
    exhibiciones = [],
    auth,
    cierreId,
    pedidoTieneError = false,
    onAdminActualizado,
    onRecargarLista,
}) {
    const [visorIdx, setVisorIdx] = useState(null);
    const conEvidencia = exhibicionesConEvidencia(exhibiciones);
    const visor = payloadVisorEnIndice(exhibiciones, visorIdx);

    if (!exhibiciones.length) {
        return <p className="text-xs md:text-[13px] theme-text-muted m-0">Sin exhibiciones registradas.</p>;
    }

    return (
        <div className={`${BLOQUE_GAP} min-w-0`}>
            <p className={SECCION_TITULO}>Exhibiciones de pago</p>

            <div className="space-y-3 md:hidden">
                {exhibiciones.map((ex) => (
                    <TarjetaExhibicionMovil
                        key={ex.id}
                        ex={ex}
                        exhibiciones={exhibiciones}
                        conEvidencia={conEvidencia}
                        onAbrirIndice={setVisorIdx}
                        auth={auth}
                        cierreId={cierreId}
                        pedidoTieneError={pedidoTieneError}
                        onAdminActualizado={onAdminActualizado}
                        onRecargarLista={onRecargarLista}
                    />
                ))}
            </div>

            <div className="hidden md:block overflow-x-auto -mx-1 px-1">
                <table className="w-full min-w-[44rem] text-left border-collapse">
                    <thead>
                        <tr className="border-b theme-border">
                            <th className={TH_EXH}>#</th>
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
                                <td className={`${TD_EXH} font-mono tabular-nums`}>{ex.numero_exhibicion}</td>
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
                                        cierreId={cierreId}
                                        exhibicion={ex}
                                        pedidoTieneError={pedidoTieneError}
                                        onActualizado={onAdminActualizado}
                                        onRecargarLista={onRecargarLista}
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
