import React from 'react';
import { UserCheck } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import TarjetaVendedorGerencia from './TarjetaVendedorGerencia';

export default function ListaEquipoGerencia({
    equipo = [],
    servidorAt,
    puedeGestionar = false,
    motivosPausa = [],
    onActualizado,
    onConflicto,
    onError,
}) {
    if (!equipo.length) {
        return (
            <div className={`${geliaCardClass()} p-5 text-center`}>
                <UserCheck className="w-8 h-8 mx-auto theme-text-muted" aria-hidden />
                <p className="text-sm font-semibold theme-text-muted m-0 mt-2">
                    No hay personas de ventas asignadas a esta sucursal.
                </p>
            </div>
        );
    }

    return (
        <ul
            className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 m-0 p-0 list-none"
            aria-label="Equipo en sucursal"
        >
            {equipo.map((persona) => (
                <li key={persona.id} className="min-w-0">
                    <TarjetaVendedorGerencia
                        persona={persona}
                        servidorAt={servidorAt}
                        puedeGestionar={puedeGestionar}
                        motivosPausa={motivosPausa}
                        onActualizado={onActualizado}
                        onConflicto={onConflicto}
                        onError={onError}
                    />
                </li>
            ))}
        </ul>
    );
}
