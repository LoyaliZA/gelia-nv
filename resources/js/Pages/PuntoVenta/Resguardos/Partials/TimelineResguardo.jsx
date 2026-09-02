import React from 'react';
import { History } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { formatearFechaOperativa } from './resguardosStyles';

export default function TimelineResguardo({ eventos = [] }) {
    if (eventos.length === 0) {
        return (
            <div className={`${geliaCardClass()} p-6 text-center`}>
                <p className="text-sm theme-text-muted font-bold uppercase tracking-widest m-0">
                    Sin eventos registrados
                </p>
            </div>
        );
    }

    return (
        <div className={`${geliaCardClass()} p-5 md:p-6`}>
            <div className="flex items-center gap-2 mb-5">
                <History className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                    Línea de tiempo
                </h2>
            </div>
            <ol className="space-y-4 m-0 p-0 list-none">
                {eventos.map((evento) => (
                    <li key={evento.id} className="relative pl-6 border-l-2 border-[var(--color-primario)]/25">
                        <span className="absolute left-[-5px] top-1.5 w-2 h-2 rounded-full bg-[var(--color-primario)]" />
                        <p className="text-sm font-black theme-text-main m-0">
                            {evento.tipo_etiqueta}
                        </p>
                        <p className="text-[10px] theme-text-muted font-bold m-0 mt-1">
                            {formatearFechaOperativa(evento.ocurrido_at)}
                            {evento.actor_referencia ? ` · ${evento.actor_referencia}` : ''}
                        </p>
                        {(evento.estado_anterior_etiqueta || evento.estado_nuevo_etiqueta) && (
                            <p className="text-[10px] theme-text-muted m-0 mt-1">
                                {evento.estado_anterior_etiqueta || '—'}
                                {' → '}
                                {evento.estado_nuevo_etiqueta || '—'}
                            </p>
                        )}
                    </li>
                ))}
            </ol>
        </div>
    );
}
