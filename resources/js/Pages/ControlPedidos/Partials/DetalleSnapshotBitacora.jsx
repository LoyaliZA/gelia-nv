import React from 'react';
import { Paperclip } from 'lucide-react';
import { formatearMoneda } from './pedidosBmaStyles';
import { MiniaturaDocumento } from './ModalVistaPreviaDocumento';

const FILA = ({ label, value }) => (
    <div className="flex justify-between gap-3 text-xs">
        <span className="theme-text-muted font-bold shrink-0">{label}</span>
        <span className="theme-text-main font-bold text-right tabular-nums">{value ?? '—'}</span>
    </div>
);

function bloqueFinanciero(fin) {
    if (!fin || typeof fin !== 'object') return null;
    return (
        <div className="space-y-1.5">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Montos registrados</p>
            <FILA label="Folio remisión" value={fin.folio_remision || fin.folio} />
            <FILA label="Total mercancía" value={formatearMoneda(fin.total_mercancia)} />
            <FILA label="Costo envío" value={formatearMoneda(fin.costo_envio)} />
            <FILA label="Seguro" value={fin.aplica_seguro ? formatearMoneda(fin.costo_seguro) : formatearMoneda(0)} />
            <FILA label="Saldo a favor" value={formatearMoneda(fin.saldo_a_favor)} />
            <FILA label="Total a cobrar" value={formatearMoneda(fin.total_a_cobrar)} />
        </div>
    );
}

function bloqueCobertura(cob) {
    if (!cob || typeof cob !== 'object') return null;
    return (
        <div className="space-y-1.5">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Cobertura de pago</p>
            <FILA label="Total a cubrir" value={formatearMoneda(cob.total_a_cubrir)} />
            <FILA label="Pagos válidos" value={formatearMoneda(cob.pagos_validos)} />
            <FILA label="Diferencia" value={formatearMoneda(cob.diferencia)} />
            <FILA label="Estado" value={cob.cobertura} />
        </div>
    );
}

function bloqueExhibicion(ex) {
    if (!ex || typeof ex !== 'object') return null;
    return (
        <div className="space-y-1.5">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Exhibición de pago</p>
            <FILA label="Número" value={ex.numero != null ? `#${ex.numero}` : null} />
            <FILA label="Monto" value={formatearMoneda(ex.monto)} />
            <FILA label="Forma" value={ex.forma_label || ex.forma_pago} />
            <FILA label="Banco" value={ex.banco} />
            <FILA label="Referencia" value={ex.referencia} />
            <FILA label="Revisión" value={ex.estado_revision} />
        </div>
    );
}

function bloqueError(err) {
    if (!err || typeof err !== 'object') return null;
    const etiquetas = Array.isArray(err.campos_etiquetas) ? err.campos_etiquetas.join(', ') : null;
    return (
        <div className="space-y-1.5">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Error reportado</p>
            {etiquetas && <FILA label="Campos" value={etiquetas} />}
            {err.detalle && (
                <p className="text-xs theme-text-main font-bold m-0 mt-1">{err.detalle}</p>
            )}
        </div>
    );
}

function bloqueCambios(cambios) {
    if (!cambios || typeof cambios !== 'object') return null;
    return (
        <div className="space-y-1.5">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Cambios</p>
            {cambios.monto_anterior != null && (
                <FILA label="Monto anterior" value={formatearMoneda(cambios.monto_anterior)} />
            )}
            {cambios.monto_nuevo != null && (
                <FILA label="Monto nuevo" value={formatearMoneda(cambios.monto_nuevo)} />
            )}
        </div>
    );
}

/**
 * Detalle expandible del snapshot_json de bitácora.
 */
export default function DetalleSnapshotBitacora({ snapshot = null, onVerArchivo = null }) {
    const data = snapshot && typeof snapshot === 'object' ? snapshot : null;
    if (!data) {
        return (
            <p className="text-[10px] theme-text-muted font-bold italic m-0">
                Sin captura de datos en este movimiento (registros anteriores al enriquecimiento).
            </p>
        );
    }

    const archivos = Array.isArray(data.archivos) ? data.archivos : [];

    return (
        <div className="space-y-4 pt-3 border-t theme-border">
            {bloqueFinanciero(data.financiero)}
            {bloqueExhibicion(data.exhibicion)}
            {bloqueCobertura(data.cobertura)}
            {bloqueError(data.error)}
            {bloqueCambios(data.cambios)}
            {archivos.length > 0 && (
                <div className="space-y-2">
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Archivos</p>
                    <div className="flex flex-wrap gap-2">
                        {archivos.map((arch, i) => {
                            const doc = {
                                id: `snap-${i}-${arch.ruta}`,
                                url: arch.ruta ? `/storage/${arch.ruta}` : null,
                                nombre_original: arch.nombre || arch.tipo || 'Archivo',
                                mime_type: arch.mime_type,
                                tipo: arch.tipo,
                            };
                            return (
                                <div key={doc.id} className="flex items-center gap-2">
                                    {doc.url && onVerArchivo ? (
                                        <MiniaturaDocumento documento={doc} onVer={() => onVerArchivo(doc)} />
                                    ) : null}
                                    <button
                                        type="button"
                                        onClick={() => doc.url && onVerArchivo?.(doc)}
                                        className="inline-flex items-center gap-1 text-[10px] font-bold uppercase outline-none"
                                        style={{ color: 'var(--color-primario)' }}
                                    >
                                        <Paperclip className="w-3 h-3" />
                                        {arch.nombre || arch.tipo}
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
