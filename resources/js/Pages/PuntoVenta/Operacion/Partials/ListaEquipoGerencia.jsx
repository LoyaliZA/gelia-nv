import React, { useMemo } from 'react';
import { User, UserCheck } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import TarjetaVendedorGerencia from './TarjetaVendedorGerencia';
import { particionarEquipoGerencia } from './operacionUtils';

function destinosTransferencia(equipo, personaId) {
    return equipo
        .filter((miembro) => Number(miembro.id) !== Number(personaId) && !miembro.atencion_actual)
        .map((miembro) => ({
            id: miembro.id,
            nombre: miembro.nombre,
            estado_vendedor: miembro.estado_vendedor,
            cronometro: miembro.cronometro ?? null,
        }));
}

export default function ListaEquipoGerencia({
    equipo = [],
    servidorAt,
    puedeGestionar = false,
    puedeTransferirTurno = false,
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

    const renderTarjeta = (persona) => (
        <li key={persona.id} className="min-w-0">
            <TarjetaVendedorGerencia
                persona={persona}
                servidorAt={servidorAt}
                puedeGestionar={puedeGestionar}
                puedeTransferirTurno={puedeTransferirTurno}
                destinosTransferencia={destinosTransferencia(equipo, persona.id)}
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
        <div className="space-y-5">
            {activos.length > 0 && (
                <SeccionEquipo
                    id="equipo-activos-hoy"
                    titulo="Activos hoy"
                    marca={(
                        <span
                            className="h-2.5 w-2.5 rounded-full shrink-0"
                            style={{ backgroundColor: 'var(--color-exito)' }}
                            aria-hidden
                        />
                    )}
                >
                    {activos.map(renderTarjeta)}
                </SeccionEquipo>
            )}
            {inactivos.length > 0 && (
                <SeccionEquipo
                    id="equipo-inactivos-hoy"
                    titulo="Inactivos hoy"
                    marca={<User className="w-5 h-5 theme-text-muted shrink-0" aria-hidden />}
                >
                    {inactivos.map(renderTarjeta)}
                </SeccionEquipo>
            )}
        </div>
    );
}

function SeccionEquipo({ id, titulo, marca, children }) {
    return (
        <section className="space-y-3" aria-labelledby={id}>
            <div className="flex items-center gap-3">
                {marca}
                <h2 id={id} className="text-sm font-black uppercase tracking-wide theme-text-main m-0 shrink-0">
                    {titulo}
                </h2>
                <span className="h-px flex-1" style={{ backgroundColor: 'var(--theme-border)' }} aria-hidden />
            </div>
            <ul className="m-0 grid list-none grid-cols-1 justify-start gap-4 p-0 sm:grid-cols-[repeat(auto-fill,minmax(18rem,22rem))]">
                {children}
            </ul>
        </section>
    );
}
