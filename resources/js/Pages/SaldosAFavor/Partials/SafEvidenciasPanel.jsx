import React from 'react';
import { ExternalLink, FileText, Image as ImageIcon, Receipt } from 'lucide-react';
import { LABEL_ESTADO_REV, fmtFecha, fmtMoneda } from './safStyles';

const esImagen = (item) => {
    const mime = String(item?.mime_type || '').toLowerCase();
    if (mime.startsWith('image/')) return true;
    const probe = `${item?.nombre_original || ''} ${item?.url || ''} ${item?.ruta_archivo || ''}`.toLowerCase();
    return /\.(jpe?g|png|webp|gif)(\?|$)/.test(probe);
};

function ArchivoCard({ href, titulo, meta, imagen }) {
    if (!href) {
        return (
            <div className="rounded-xl border theme-border theme-element p-3 text-[10px] theme-text-muted font-semibold">
                {titulo}
                {meta ? <div className="mt-0.5">{meta}</div> : null}
                <div className="mt-1">Sin archivo adjunto</div>
            </div>
        );
    }

    return (
        <a
            href={href}
            target="_blank"
            rel="noreferrer"
            className="block rounded-xl border theme-border theme-element overflow-hidden hover:border-[var(--color-primario)] transition-colors"
        >
            {imagen ? (
                <div className="bg-black/5 dark:bg-white/5 aspect-[4/3] flex items-center justify-center overflow-hidden">
                    <img src={href} alt={titulo} className="max-h-40 w-full object-contain" />
                </div>
            ) : (
                <div className="aspect-[4/3] max-h-28 flex items-center justify-center gap-2 theme-text-muted">
                    <FileText className="w-6 h-6" />
                    <span className="text-[10px] font-black uppercase tracking-widest">Abrir archivo</span>
                </div>
            )}
            <div className="px-3 py-2 flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-main m-0 truncate">{titulo}</p>
                    {meta ? <p className="text-[10px] theme-text-muted font-semibold m-0 mt-0.5">{meta}</p> : null}
                </div>
                <ExternalLink className="w-3.5 h-3.5 shrink-0 theme-text-muted" />
            </div>
        </a>
    );
}

/**
 * Panel de contexto para acciones SAF: resumen del crédito + recibos de pago + evidencias.
 */
export default function SafEvidenciasPanel({ credito }) {
    if (!credito) return null;

    const recibos = credito.recibos_pago || [];
    const evidencias = credito.evidencias || [];
    const sinArchivos = recibos.length === 0 && evidencias.length === 0;

    return (
        <div className="rounded-2xl border theme-border theme-element p-3 space-y-3">
            <div>
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Contexto del saldo</p>
                <div className="mt-2 grid grid-cols-2 gap-2 text-[10px]">
                    <div>
                        <span className="theme-text-muted font-bold">Motivo</span>
                        <div className="font-black theme-text-main">{credito.motivo?.nombre || '—'}</div>
                    </div>
                    <div>
                        <span className="theme-text-muted font-bold">Pedido</span>
                        <div className="font-black theme-text-main">{credito.pedido_origen_folio || '—'}</div>
                    </div>
                    <div>
                        <span className="theme-text-muted font-bold">Original</span>
                        <div className="font-black theme-text-main">{fmtMoneda(credito.monto_original)}</div>
                    </div>
                    <div>
                        <span className="theme-text-muted font-bold">Disponible</span>
                        <div className="font-black theme-text-main">{fmtMoneda(credito.monto_disponible)}</div>
                    </div>
                    <div>
                        <span className="theme-text-muted font-bold">Emisión</span>
                        <div className="font-semibold">{fmtFecha(credito.fecha_generacion)}</div>
                    </div>
                    <div>
                        <span className="theme-text-muted font-bold">Doc. origen</span>
                        <div className="font-semibold truncate">{credito.documento_origen || '—'}</div>
                    </div>
                </div>
            </div>

            <div>
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 inline-flex items-center gap-1.5">
                    <Receipt className="w-3.5 h-3.5" /> Comprobantes de pago
                </p>
                {recibos.length === 0 ? (
                    <p className="text-[10px] theme-text-muted font-semibold m-0 mt-2">Sin recibos de exhibición ligados al pedido origen.</p>
                ) : (
                    <div className="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        {recibos.map((r) => {
                            const forma = r.forma_pago_label || r.forma_pago || 'Pago';
                            const rev = LABEL_ESTADO_REV[r.estado_revision] || r.estado_revision || '';
                            const meta = [
                                fmtMoneda(r.monto),
                                forma,
                                r.banco_nombre,
                                r.referencia ? `Ref. ${r.referencia}` : null,
                                r.fecha_pago ? fmtFecha(r.fecha_pago) : null,
                                rev,
                            ]
                                .filter(Boolean)
                                .join(' · ');
                            return (
                                <ArchivoCard
                                    key={`recibo-${r.id}`}
                                    href={r.url}
                                    titulo={r.nombre_original || `Recibo ${fmtMoneda(r.monto)}`}
                                    meta={meta}
                                    imagen={esImagen(r)}
                                />
                            );
                        })}
                    </div>
                )}
            </div>

            <div>
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 inline-flex items-center gap-1.5">
                    <ImageIcon className="w-3.5 h-3.5" /> Evidencias del saldo
                </p>
                {evidencias.length === 0 ? (
                    <p className="text-[10px] theme-text-muted font-semibold m-0 mt-2">Sin evidencias adjuntas al crédito.</p>
                ) : (
                    <div className="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        {evidencias.map((e) => (
                            <ArchivoCard
                                key={`ev-${e.id}`}
                                href={e.url}
                                titulo={e.nombre_original || 'Evidencia'}
                                meta={e.created_at ? fmtFecha(e.created_at) : null}
                                imagen={esImagen(e)}
                            />
                        ))}
                    </div>
                )}
            </div>

            {sinArchivos && (
                <p className="text-[10px] font-bold text-amber-700 dark:text-amber-400 m-0">
                    No hay comprobante ni evidencia para contrastar. Resuelve solo si el contexto operativo lo permite.
                </p>
            )}
        </div>
    );
}
