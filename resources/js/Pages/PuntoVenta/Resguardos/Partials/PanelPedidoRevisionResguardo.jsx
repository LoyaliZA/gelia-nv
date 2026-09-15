import React, { useState } from 'react';
import SeccionRevisionFisicaPedido from '../../../ControlPedidos/Partials/SeccionRevisionFisicaPedido';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from '../../../ControlPedidos/Partials/ModalVistaPreviaDocumento';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { ChipEvidenciasBultosEmpaque } from './ModalEvidenciasBultosEmpaque';

export default function PanelPedidoRevisionResguardo({
    resguardo,
    pedidoRevision = null,
    tituloRevision = 'Productos revisados y entregados',
    className = '',
}) {
    const [docPreview, setDocPreview] = useState(null);
    const pedido = pedidoRevision || resguardo?.pedido_revision;
    const bultosEmpaque = resguardo?.bultos_empaque_cedis || [];
    const folio = resguardo?.snapshot_folio || resguardo?.pedido?.folio || '';
    const evidenciasApartado = (pedido?.documentos || []).filter((doc) => doc.tipo === 'evidencia_apartado');

    if (!pedido && bultosEmpaque.length === 0) {
        return null;
    }

    return (
        <div className={`${geliaCardClass()} p-5 md:p-6 space-y-4 ${className}`}>
            <div className="space-y-1">
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                    Contenido del pedido
                </h2>
                <p className="text-xs theme-text-muted m-0">
                    Evidencias del empaque y piezas revisadas en CEDIS, igual que en la vista de ventas.
                </p>
            </div>

            {bultosEmpaque.length > 0 && (
                <div className="space-y-2">
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                        Evidencias del pedido (bultos CEDIS)
                    </p>
                    <ChipEvidenciasBultosEmpaque bultos={bultosEmpaque} folio={folio} />
                </div>
            )}

            {pedido && (
                <SeccionRevisionFisicaPedido
                    pedido={pedido}
                    onVerDoc={setDocPreview}
                    titulo={tituloRevision}
                    puedeAtender={false}
                />
            )}

            {evidenciasApartado.length > 0 && (
                <div className="space-y-2">
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                        Evidencia de apartado
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {evidenciasApartado.map((doc) => (
                            <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                        ))}
                    </div>
                </div>
            )}

            <ModalVistaPreviaDocumento
                abierto={Boolean(docPreview)}
                documento={docPreview}
                onClose={() => setDocPreview(null)}
            />
        </div>
    );
}
