import React, { useEffect, useMemo, useRef, useState } from 'react';
import { ChevronDown, User, UserCheck } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import TarjetaVendedorGerencia from './TarjetaVendedorGerencia';
import {
    INACTIVIDAD_SECCION_INACTIVOS_MS,
    particionarEquipoGerencia,
    resolverSeccionInactivos,
} from './operacionUtils';

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
                <SeccionInactivos cantidadActivos={activos.length} cantidad={inactivos.length}>
                    {inactivos.map(renderTarjeta)}
                </SeccionInactivos>
            )}
        </div>
    );
}

function SeccionInactivos({ cantidadActivos, cantidad, children }) {
    const [abierta, setAbierta] = useState(cantidadActivos === 0);
    const activosAntes = useRef(cantidadActivos);
    const temporizador = useRef(null);

    const limpiarTemporizador = () => {
        if (temporizador.current) {
            window.clearTimeout(temporizador.current);
            temporizador.current = null;
        }
    };

    const aplicar = (evento, activosAhora = cantidadActivos) => {
        const decision = resolverSeccionInactivos({
            abierta,
            activosAntes: activosAntes.current,
            activosAhora,
            evento,
        });

        if (evento === 'equipo') {
            activosAntes.current = activosAhora;
        }

        setAbierta(decision.abierta);
        if (decision.cancelarTemporizador) {
            limpiarTemporizador();
        }
        if (decision.reiniciarTemporizador) {
            limpiarTemporizador();
            temporizador.current = window.setTimeout(() => {
                const cierre = resolverSeccionInactivos({
                    abierta: true,
                    activosAntes: activosAntes.current,
                    activosAhora: activosAntes.current,
                    evento: 'vencio',
                });
                setAbierta(cierre.abierta);
                temporizador.current = null;
            }, INACTIVIDAD_SECCION_INACTIVOS_MS);
        }
    };

    useEffect(() => () => limpiarTemporizador(), []);

    useEffect(() => {
        aplicar('equipo', cantidadActivos);
        // El temporizador se rearma solo cuando cambia la cantidad de activos.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cantidadActivos]);

    return (
        <section className="space-y-3" aria-labelledby="equipo-inactivos-hoy">
            <button
                type="button"
                className="flex w-full items-center gap-3 text-left"
                aria-expanded={abierta}
                aria-controls="equipo-inactivos-panel"
                onClick={() => aplicar('alternar')}
            >
                <User className="w-5 h-5 theme-text-muted shrink-0" aria-hidden />
                <h2 id="equipo-inactivos-hoy" className="text-sm font-black uppercase tracking-wide theme-text-main m-0 shrink-0">
                    Inactivos hoy
                </h2>
                <span className="text-[10px] font-black tabular-nums theme-text-muted">{cantidad}</span>
                <span className="h-px flex-1" style={{ backgroundColor: 'var(--theme-border)' }} aria-hidden />
                <ChevronDown
                    className={`w-4 h-4 theme-text-muted shrink-0 motion-safe:transition-transform ${abierta ? 'rotate-180' : ''}`}
                    aria-hidden
                />
            </button>
            <div id="equipo-inactivos-panel" hidden={!abierta} onPointerDown={() => aplicar('actividad')}>
                {abierta && (
                    <ul className="m-0 grid list-none grid-cols-1 justify-start gap-4 p-0 sm:grid-cols-[repeat(auto-fill,minmax(18rem,22rem))]">
                        {children}
                    </ul>
                )}
            </div>
        </section>
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
