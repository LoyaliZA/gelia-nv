import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Eye, Wrench, ImageIcon } from 'lucide-react';
import { ESTADO_BADGE, ESTADO_LABELS, getActivosCardClass } from './activosFormStyles';

export default function TarjetaActivoMobile({ activo, fotoUrl, tieneMantenimiento }) {
    return (
        <Link
            href={route('activos.show', activo.id)}
            prefetch={false}
            className={`${getActivosCardClass('p-4 md:p-5 flex flex-col gap-3 block hover:opacity-95 transition-opacity active:scale-[0.99]')}`}
        >
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
                    <h3 className="text-sm font-black uppercase italic theme-text-main leading-tight">{activo.nombre}</h3>
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
                        <span
                            role="link"
                            tabIndex={0}
                            onClick={(e) => { e.preventDefault(); e.stopPropagation(); router.get(route('activos.index', { responsable_user_id: activo.responsable.id })); }}
                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); router.get(route('activos.index', { responsable_user_id: activo.responsable.id })); } }}
                            className="font-bold truncate block cursor-pointer hover:underline"
                            style={{ color: 'var(--color-primario)' }}
                        >
                            {activo.responsable.name}
                        </span>
                    ) : (
                        <span className="italic theme-text-muted">Sin asignar</span>
                    )}
                </div>
            </div>

            <span className="inline-flex items-center justify-center gap-1 text-[10px] font-black uppercase w-full py-2 rounded-xl border theme-border theme-text-main">
                <Eye className="w-3.5 h-3.5 shrink-0" style={{ color: 'var(--color-primario)' }} /> Ver detalle
            </span>
        </Link>
    );
}
