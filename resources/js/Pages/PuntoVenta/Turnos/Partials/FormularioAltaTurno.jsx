import React, { useMemo, useState } from 'react';
import { Loader2, Ticket } from 'lucide-react';
import useToastAlCambiar from '../../../../hooks/useToastAlCambiar';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import BusquedaClienteTurno from './BusquedaClienteTurno';
import {
    buscarTurnoActivoClienteEnBandeja,
    formularioListoParaEnviar,
    mensajeClienteYaEnCola,
    mensajeSucursalSinAltas,
} from './altaTurnoUtils';
import {
    BTN_SEGMENTO,
    BTN_SEGMENTO_ACTIVO,
    BTN_SEGMENTO_INACTIVO,
    THEME_INPUT,
} from './turnosStyles';

export default function FormularioAltaTurno({
    permisos = {},
    catalogos = {},
    bandeja = null,
    sucursalDia = null,
    enviando = false,
    onEnviar,
    onMostrarError,
}) {
    const [modo, setModo] = useState('cliente');
    const [cliente, setCliente] = useState(null);
    const [nombreLlamado, setNombreLlamado] = useState('');
    const [prioridadAdultoMayor, setPrioridadAdultoMayor] = useState(false);
    const [prioridadDiscapacidad, setPrioridadDiscapacidad] = useState(false);

    const puedeMarcarPrioridad = Boolean(permisos.marcar_prioridad);
    const puedeRepresentante = Boolean(permisos.alta_representante);

    const turnoDuplicado = useMemo(() => {
        const clienteId = modo === 'cliente' || (modo === 'visitante' && cliente?.id)
            ? cliente?.id
            : null;
        return clienteId ? buscarTurnoActivoClienteEnBandeja(bandeja, clienteId) : null;
    }, [modo, cliente, bandeja]);

    const avisoSucursal = mensajeSucursalSinAltas(sucursalDia);

    useToastAlCambiar(avisoSucursal, 'info');

    const puedeEnviar = useMemo(() => formularioListoParaEnviar({
        modo,
        cliente,
        nombreLlamado,
        bandeja,
        sucursalDia,
        puedeRepresentante,
    }), [modo, cliente, nombreLlamado, bandeja, sucursalDia, puedeRepresentante]);

    const cambiarModo = (nuevoModo) => {
        setModo(nuevoModo);
        setCliente(null);
        setNombreLlamado('');
        setPrioridadAdultoMayor(false);
        setPrioridadDiscapacidad(false);
    };

    const seleccionarCliente = (clienteSeleccionado) => {
        setCliente(clienteSeleccionado);

        const turnoExistente = buscarTurnoActivoClienteEnBandeja(bandeja, clienteSeleccionado?.id);
        if (turnoExistente) {
            onMostrarError?.(mensajeClienteYaEnCola(turnoExistente));
        }
    };

    const enviar = (event) => {
        event.preventDefault();
        onEnviar?.({
            modo,
            cliente,
            nombreLlamado,
            prioridadAdultoMayor: puedeMarcarPrioridad ? prioridadAdultoMayor : false,
            prioridadDiscapacidad: puedeMarcarPrioridad ? prioridadDiscapacidad : false,
        });
    };

    return (
        <form onSubmit={enviar} className={`${geliaCardClass()} p-5 space-y-5`} data-recepcion-turno-form>
            <div className="flex flex-wrap gap-2" role="tablist" aria-label="Tipo de persona">
                <button
                    type="button"
                    role="tab"
                    aria-selected={modo === 'cliente'}
                    onClick={() => cambiarModo('cliente')}
                    disabled={enviando}
                    className={`${BTN_SEGMENTO} ${modo === 'cliente' ? BTN_SEGMENTO_ACTIVO : BTN_SEGMENTO_INACTIVO}`}
                >
                    Cliente registrado
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected={modo === 'visitante'}
                    onClick={() => cambiarModo('visitante')}
                    disabled={enviando}
                    className={`${BTN_SEGMENTO} ${modo === 'visitante' ? BTN_SEGMENTO_ACTIVO : BTN_SEGMENTO_INACTIVO}`}
                >
                    Visitante
                </button>
            </div>

            {modo === 'cliente' ? (
                <BusquedaClienteTurno
                    clienteSeleccionado={cliente}
                    onSeleccionar={seleccionarCliente}
                    onLimpiar={() => setCliente(null)}
                    deshabilitado={enviando}
                />
            ) : null}

            {modo === 'visitante' ? (
                <div className="space-y-4">
                    <div className="space-y-2">
                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted" htmlFor="nombre-llamado-turno">
                            Nombre del visitante
                        </label>
                        <input
                            id="nombre-llamado-turno"
                            type="text"
                            value={nombreLlamado}
                            onChange={(event) => setNombreLlamado(event.target.value)}
                            disabled={enviando}
                            placeholder="Nombre que se mostrará en la pantalla de turnos"
                            className={`${THEME_INPUT} min-h-[44px]`}
                            autoComplete="name"
                            maxLength={255}
                        />
                        <p className="text-xs theme-text-muted m-0">
                            En sala y en el tablero se anuncia este nombre, no el del titular del número.
                        </p>
                    </div>
                    {puedeRepresentante ? (
                        <div className="space-y-2 rounded-2xl border theme-border theme-element px-4 py-3">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                Número de cliente usado (opcional)
                            </p>
                            <p className="text-xs theme-text-muted m-0">
                                Si la persona entró con el número de un cliente titular, búscalo aquí para ligar el turno a esa cuenta.
                            </p>
                            <BusquedaClienteTurno
                                clienteSeleccionado={cliente}
                                onSeleccionar={seleccionarCliente}
                                onLimpiar={() => setCliente(null)}
                                deshabilitado={enviando}
                            />
                        </div>
                    ) : (
                        <p className="text-xs theme-text-muted m-0">
                            Para registrar visitantes con número de cliente titular, solicita el permiso de alta con cliente.
                        </p>
                    )}
                </div>
            ) : null}

            <div className="rounded-2xl border theme-border theme-element px-4 py-3">
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Servicio</p>
                <p className="text-sm font-bold theme-text-main m-0 mt-1">{catalogos.servicio || 'Ventas'}</p>
            </div>

            {puedeMarcarPrioridad && (
                <fieldset className="space-y-3 border-0 p-0 m-0">
                    <legend className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">
                        Prioridad autorizada
                    </legend>
                    <label className="flex items-center gap-3 min-h-[44px] cursor-pointer">
                        <input
                            type="checkbox"
                            checked={prioridadAdultoMayor}
                            onChange={(event) => setPrioridadAdultoMayor(event.target.checked)}
                            disabled={enviando}
                            className="w-5 h-5 rounded border theme-border"
                        />
                        <span className="text-sm font-semibold theme-text-main">Adulto mayor</span>
                    </label>
                    <label className="flex items-center gap-3 min-h-[44px] cursor-pointer">
                        <input
                            type="checkbox"
                            checked={prioridadDiscapacidad}
                            onChange={(event) => setPrioridadDiscapacidad(event.target.checked)}
                            disabled={enviando}
                            className="w-5 h-5 rounded border theme-border"
                        />
                        <span className="text-sm font-semibold theme-text-main">Discapacidad / movilidad</span>
                    </label>
                </fieldset>
            )}

            <button
                type="submit"
                disabled={enviando || Boolean(turnoDuplicado) || !puedeEnviar}
                className={`${THEME_BTN_PRIMARY} w-full min-h-[48px] inline-flex items-center justify-center gap-2`}
                aria-label="Registrar turno de ventas"
            >
                {enviando ? (
                    <>
                        <Loader2 className="w-4 h-4 animate-spin" aria-hidden />
                        Registrando…
                    </>
                ) : (
                    <>
                        <Ticket className="w-4 h-4" aria-hidden />
                        Registrar turno
                    </>
                )}
            </button>
        </form>
    );
}
