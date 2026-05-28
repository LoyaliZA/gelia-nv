import React from 'react';
import { Link } from '@inertiajs/react';
import { Eye, Wrench, ImageIcon } from 'lucide-react';
import { ESTADO_BADGE, ESTADO_LABELS } from './activosFormStyles';

export default function TarjetaActivoMobile({ activo, fotoUrl, tieneMantenimiento }) {
    return (
        <div className="theme-surface rounded-[2rem] border theme-border p-4 shadow-lg flex flex-col gap-3">
            <div className="flex items-start gap-3">
                <div className="w-14 h-14 rounded-xl overflow-hidden border theme-border bg-black/5 shrink-0 flex items-center justify-center">
                    {fotoUrl ? (
                        <img src={fotoUrl} alt="" className="w-full h-full object-cover" />
                    ) : (
                        <ImageIcon className="w-5 h-5 theme-text-muted opacity-40" />
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-[10px] font-mono font-bold theme-text-muted">{activo.folio}</p>
                    <h3 className="text-sm font-black uppercase italic theme-text-main leading-tight truncate">{activo.nombre}</h3>
                    <p className="text-[10px] theme-text-muted mt-0.5">{activo.tipo?.nombre}</p>
                </div>
                <div className="flex flex-col items-end gap-1 shrink-0">
                    <span className={`px-2 py-1 rounded-lg text-[9px] font-black uppercase ${ESTADO_BADGE[activo.estado] || ''}`}>
                        {ESTADO_LABELS[activo.estado] || activo.estado}
                    </span>
                    {tieneMantenimiento && <Wrench className="w-3.5 h-3.5 text-amber-500" />}
                </div>
            </div>

            <div className="grid grid-cols-2 gap-2 text-[10px]">
                <div className="theme-element rounded-xl p-2 border theme-border">
                    <span className="font-black uppercase theme-text-muted block mb-0.5">Marca / Modelo</span>
                    <span className="font-bold theme-text-main">{activo.atributos?.marca || '—'}</span>
                    {activo.atributos?.modelo && <span className="block theme-text-muted">{activo.atributos.modelo}</span>}
                </div>
                <div className="theme-element rounded-xl p-2 border theme-border">
                    <span className="font-black uppercase theme-text-muted block mb-0.5">Pertenece a</span>
                    {activo.responsable ? (
                        <Link href={route('activos.index', { responsable_user_id: activo.responsable.id })} className="font-bold hover:underline truncate block" style={{ color: 'var(--color-primario)' }}>
                            {activo.responsable.name}
                        </Link>
                    ) : (
                        <span className="italic theme-text-muted">Sin asignar</span>
                    )}
                </div>
            </div>

            <Link
                href={route('activos.show', activo.id)}
                className="flex items-center justify-center gap-2 py-2.5 rounded-xl text-[10px] font-black uppercase text-white"
                style={{ backgroundColor: 'var(--color-primario)' }}
            >
                <Eye className="w-3.5 h-3.5" /> Ver detalle
            </Link>
        </div>
    );
}
