import React from 'react';
import { Users } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { claseBadgeActividad, claseBadgeJornada, etiquetaActividad, etiquetaJornada } from './operacionUtils';

export default function ListaEquipoOperativo({ equipo = [] }) {
    if (!equipo.length) {
        return (
            <div className={`${geliaCardClass()} p-5 text-center`}>
                <Users className="w-8 h-8 mx-auto theme-text-muted" aria-hidden />
                <p className="text-sm font-semibold theme-text-muted m-0 mt-2">
                    No hay personas de ventas asignadas a esta sucursal.
                </p>
            </div>
        );
    }

    return (
        <ul className="space-y-2 m-0 p-0 list-none" aria-label="Disponibilidad del equipo">
            {equipo.map((persona) => (
                <li key={persona.id} className={`${geliaCardClass()} p-4`}>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-sm font-black theme-text-main m-0 truncate">{persona.nombre}</p>
                            <div className="flex flex-wrap gap-2 mt-2">
                                <span className={`inline-flex px-2 py-1 rounded-lg text-[10px] font-black uppercase ${claseBadgeJornada(persona.jornada_estado)}`}>
                                    {etiquetaJornada(persona.jornada_estado || 'CERRADA')}
                                </span>
                                <span className={`inline-flex px-2 py-1 rounded-lg text-[10px] font-black uppercase ${claseBadgeActividad(persona.actividad)}`}>
                                    {etiquetaActividad(persona.actividad)}
                                </span>
                            </div>
                        </div>
                        <span
                            className={`inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase shrink-0 ${
                                persona.disponible
                                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                    : 'bg-slate-500/15 theme-text-muted'
                            }`}
                        >
                            {persona.disponible ? 'Disponible' : 'No disponible'}
                        </span>
                    </div>
                </li>
            ))}
        </ul>
    );
}
