import React, { useMemo } from 'react';
import { UserCheck } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import TarjetaVendedorGerencia from './TarjetaVendedorGerencia';
import { particionarEquipoGerencia } from './operacionUtils';

export default function ListaEquipoGerencia({
    equipo = [],
    servidorAt,
    puedeGestionar = false,
    puedeAsignarReatencion = false,
    reatenciones = [],
    motivosPausa = [],
    catalogos = {},
    onActualizado,
    onConflicto,
    onError,
}) {
    const { activos, inactivos } = useMemo(
        () => particionarEquipoGerencia(equipo),
        [equipo],
    );
    const mostrarDivision = activos.length > 0 && inactivos.length > 0;

    const renderTarjeta = (persona) => (
        <li key={persona.id} className="min-w-0">
            <TarjetaVendedorGerencia
                persona={persona}
                servidorAt={servidorAt}
                puedeGestionar={puedeGestionar}
                puedeAsignarReatencion={puedeAsignarReatencion}
                reatenciones={reatenciones}
                catalogos={catalogos}
                motivosPausa={motivosPausa}
                onActualizado={onActualizado}
                onConflicto={onConflicto}
                onError={onError}
            />
        </li>
    );

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
            {activos.map(renderTarjeta)}
            {mostrarDivision ? (
                <li
                    className="col-span-full flex items-center gap-3 py-1 list-none"
                    aria-hidden
                >
                    <span className="h-px flex-1 bg-black/10 dark:bg-white/10" />
                    <span className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted shrink-0">
                        Inactivos hoy
                    </span>
                    <span className="h-px flex-1 bg-black/10 dark:bg-white/10" />
                </li>
            ) : null}
            {inactivos.map(renderTarjeta)}
        </ul>
    );
}
