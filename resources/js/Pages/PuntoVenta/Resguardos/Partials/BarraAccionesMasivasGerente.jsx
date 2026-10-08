import React, { useMemo, useState } from 'react';
import { Loader2, PackageCheck, Send } from 'lucide-react';
import { THEME_BTN_SECONDARY } from '../../../../utils/geliaTheme';
import { BTN_ACCION_MASIVA_GERENTE } from './resguardosStyles';
import { confirmarRecepcionGerente, pasarARecepcionGerente } from './recepcionGerenteApi';
import useToastAlCambiar from '../../../../hooks/useToastAlCambiar';
import BarraStickySeleccionResguardo from './BarraStickySeleccionResguardo';

export default function BarraAccionesMasivasGerente({
    resguardos = [],
    idsSeleccionados = [],
    puedeConfirmarLlegada = false,
    puedeEnviarACustodia = false,
    onExito,
    onLimpiarSeleccion,
    onSeleccionarPagina,
    paginaSeleccionada = false,
}) {
    const [procesando, setProcesando] = useState(false);
    const [progreso, setProgreso] = useState(null);
    const [error, setError] = useState(null);

    useToastAlCambiar(error, 'error');

    const seleccionados = useMemo(
        () => resguardos.filter((r) => idsSeleccionados.includes(r.id)),
        [resguardos, idsSeleccionados],
    );

    const pendientes = seleccionados.filter((r) => r.estado === 'pendiente_recepcion');
    const recibidos = seleccionados.filter((r) => r.estado === 'recibido');

    const ejecutarLote = async (items, accion) => {
        if (!items.length || procesando) return;

        setProcesando(true);
        setError(null);
        let fallos = 0;

        for (let i = 0; i < items.length; i += 1) {
            setProgreso({ actual: i + 1, total: items.length });
            const resultado = await accion(items[i]);
            if (!resultado.ok) {
                fallos += 1;
            }
        }

        setProcesando(false);
        setProgreso(null);

        if (fallos > 0) {
            setError(`${fallos} de ${items.length} no se procesaron. Revisa el listado e intenta de nuevo.`);
        }

        onLimpiarSeleccion?.();
        onExito?.();
    };

    if (idsSeleccionados.length === 0) {
        return null;
    }

    const meta = progreso
        ? `Procesando ${progreso.actual} de ${progreso.total}…`
        : 'Acciones masivas en la selección actual';

    return (
        <BarraStickySeleccionResguardo
            titulo={`${idsSeleccionados.length} seleccionado${idsSeleccionados.length === 1 ? '' : 's'}`}
            meta={meta}
            acciones={(
                <>
                    {onSeleccionarPagina && (
                        <button
                            type="button"
                            onClick={onSeleccionarPagina}
                            disabled={procesando || paginaSeleccionada}
                            className={`${THEME_BTN_SECONDARY} text-xs font-semibold min-h-[44px] px-3 sm:px-4`}
                        >
                            <span className="hidden sm:inline">Seleccionar página</span>
                            <span className="sm:hidden">Página</span>
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={onLimpiarSeleccion}
                        disabled={procesando}
                        className={`${THEME_BTN_SECONDARY} text-xs font-semibold min-h-[44px] px-3 sm:px-4`}
                    >
                        Limpiar
                    </button>
                </>
            )}
        >
            {puedeConfirmarLlegada && pendientes.length > 0 && (
                <button
                    type="button"
                    disabled={procesando}
                    onClick={() => ejecutarLote(pendientes, confirmarRecepcionGerente)}
                    className={BTN_ACCION_MASIVA_GERENTE}
                >
                    {procesando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <PackageCheck className="w-4 h-4 stroke-[2]" aria-hidden />}
                    Confirmar recepción ({pendientes.length})
                </button>
            )}
            {puedeEnviarACustodia && recibidos.length > 0 && (
                <button
                    type="button"
                    disabled={procesando}
                    onClick={() => ejecutarLote(recibidos, pasarARecepcionGerente)}
                    className={BTN_ACCION_MASIVA_GERENTE}
                >
                    {procesando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <Send className="w-4 h-4 stroke-[2]" aria-hidden />}
                    Pasar a recepción ({recibidos.length})
                </button>
            )}
        </BarraStickySeleccionResguardo>
    );
}
