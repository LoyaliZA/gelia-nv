import React from 'react';
import { Ban, RotateCcw, Tag, TrendingDown } from 'lucide-react';
import AvisoSolicitud from '@/Components/Solicitudes/AvisoSolicitud';
import EtiquetaConIcono from '@/Components/EtiquetaConIcono';
import { CHIP_NEUTRO, claseEtiquetaLista } from './solicitudesStyles';
import { efectosCancelacionSolicitud } from './efectosCancelacionSolicitud';

const money = (valor) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(valor || 0);

const nombreCorto = (nombre) => (nombre || '').split(' ').slice(0, 2).join(' ') || 'Responsable';

function PasoLista({ nombre }) {
    return <span className={claseEtiquetaLista(nombre)}>{nombre}</span>;
}

function FlujoCambio({ origen, destino, etiqueta }) {
    return (
        <div className="gelia-tag-flujo gelia-tag-cancel-preview-flujo" aria-label={etiqueta}>
            <span className="gelia-tag-flujo-paso">{origen}</span>
            <span className="gelia-tag-flujo-escalera" data-tono="peligro">
                <TrendingDown className="gelia-tag-flujo-flecha" aria-hidden="true" />
                <span className="gelia-tag-flujo-escalera-texto">Regresa</span>
            </span>
            <span className="gelia-tag-flujo-paso gelia-tag-flujo-paso--destino">{destino}</span>
        </div>
    );
}

export default function PrevisualizacionCancelacionSolicitud({ solicitud, confirmar = false }) {
    const efectos = efectosCancelacionSolicitud(solicitud);
    const titulo = confirmar
        ? 'Cambios al confirmar la cancelación'
        : 'Cambios que provocará la cancelación';
    const hayCambiosCliente = efectos.cambiaLista || efectos.asignaTag || efectos.revierteBeneficios || efectos.montoOperacional > 0;

    if (!hayCambiosCliente && !efectos.asignaTag && !efectos.cambiaLista) {
        return (
            <AvisoSolicitud tono="neutro" titulo={titulo}>
                El folio se marcará como cancelado. El cliente no cambia porque esta solicitud aún no aplicó beneficios.
            </AvisoSolicitud>
        );
    }

    return (
        <AvisoSolicitud tono={confirmar ? 'peligro' : 'aviso'} titulo={titulo}>
            <div className="gelia-tag-cancel-preview">
                {efectos.cambiaLista && (
                    <div className="gelia-tag-cancel-preview-item">
                        <p className="gelia-tag-cancel-preview-label">
                            {efectos.revierteBeneficios || efectos.muestraRegreso
                                ? `El cliente regresa a ${efectos.listaRegreso}`
                                : `El cliente permanece en ${efectos.listaRegreso}`}
                        </p>
                        <FlujoCambio
                            origen={<PasoLista nombre={efectos.listaSolicitada} />}
                            destino={<PasoLista nombre={efectos.listaRegreso} />}
                            etiqueta={`Lista: de ${efectos.listaSolicitada} a ${efectos.listaRegreso}`}
                        />
                    </div>
                )}
                {efectos.asignaTag && (
                    <div className="gelia-tag-cancel-preview-item">
                        <p className="gelia-tag-cancel-preview-label">El TAG se retira</p>
                        {efectos.tagAnterior && (efectos.revierteBeneficios || efectos.muestraRegreso) ? (
                            <FlujoCambio
                                origen={(
                                    <EtiquetaConIcono icon={Tag} className={CHIP_NEUTRO} iconClassName="h-3 w-3" size={12}>
                                        {nombreCorto(efectos.tagNombre)}
                                    </EtiquetaConIcono>
                                )}
                                destino={(
                                    <EtiquetaConIcono icon={Tag} className={CHIP_NEUTRO} iconClassName="h-3 w-3" size={12}>
                                        {nombreCorto(efectos.tagAnterior)}
                                    </EtiquetaConIcono>
                                )}
                                etiqueta={`TAG: de ${efectos.tagNombre} a ${efectos.tagAnterior}`}
                            />
                        ) : (
                            <EtiquetaConIcono icon={Ban} className={CHIP_NEUTRO} iconClassName="h-3 w-3" size={12}>
                                {efectos.revierteBeneficios || efectos.muestraRegreso
                                    ? `Se retira ${nombreCorto(efectos.tagNombre)}`
                                    : `No se asigna ${nombreCorto(efectos.tagNombre)}`}
                            </EtiquetaConIcono>
                        )}
                    </div>
                )}
                {(efectos.revierteBeneficios || efectos.muestraRegreso) && efectos.montoOperacional > 0 && (
                    <p className="gelia-tag-cancel-preview-monto">
                        <RotateCcw className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                        Se revierte el monto operacional de {money(efectos.montoOperacional)}.
                    </p>
                )}
                {!efectos.revierteBeneficios && !efectos.muestraRegreso && (
                    <p className="text-xs theme-text-muted">
                        El folio se anulará. El cliente permanece como está porque los beneficios aún no se aplicaron.
                    </p>
                )}
            </div>
        </AvisoSolicitud>
    );
}
