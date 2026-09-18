import React, { useMemo, useState } from 'react';
import { Loader2, PackageCheck, Send } from 'lucide-react';
import { geliaCardClass, THEME_BTN_SECONDARY } from '../../../../utils/geliaTheme';
import { BTN_ACCION_MASIVA_GERENTE } from './resguardosStyles';
import { confirmarRecepcionGerente, pasarARecepcionGerente } from './recepcionGerenteApi';

export default function BarraAccionesMasivasGerente({
    resguardos = [],
    idsSeleccionados = [],
    onExito,
    onLimpiarSeleccion,
}) {
    const [procesando, setProcesando] = useState(false);
    const [progreso, setProgreso] = useState(null);
    const [error, setError] = useState(null);

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

    return (
        <div className={`${geliaCardClass()} p-0 sticky bottom-3 z-20 flex flex-col gap-0 overflow-hidden border theme-border shadow-lg`}>
            <div className="px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                <p className="text-sm font-bold theme-text-main m-0">
                    {idsSeleccionados.length} seleccionado{idsSeleccionados.length === 1 ? '' : 's'}
                    {progreso && (
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-2">
                            ({progreso.actual}/{progreso.total})
                        </span>
                    )}
                </p>
                <button
                    type="button"
                    onClick={onLimpiarSeleccion}
                    disabled={procesando}
                    className={`${THEME_BTN_SECONDARY} text-[10px] font-black uppercase tracking-widest min-h-[40px] px-4`}
                >
                    Limpiar
                </button>
            </div>

            <div className="flex flex-col">
                {pendientes.length > 0 && (
                    <button
                        type="button"
                        disabled={procesando}
                        onClick={() => ejecutarLote(pendientes, confirmarRecepcionGerente)}
                        className={`${BTN_ACCION_MASIVA_GERENTE} rounded-none border-b border-white/10`}
                    >
                        {procesando ? <Loader2 className="w-4 h-4 animate-spin" /> : <PackageCheck className="w-4 h-4 stroke-[2]" />}
                        Confirmar recepción ({pendientes.length})
                    </button>
                )}
                {recibidos.length > 0 && (
                    <button
                        type="button"
                        disabled={procesando}
                        onClick={() => ejecutarLote(recibidos, pasarARecepcionGerente)}
                        className={BTN_ACCION_MASIVA_GERENTE}
                    >
                        {procesando ? <Loader2 className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4 stroke-[2]" />}
                        Pasar a recepción ({recibidos.length})
                    </button>
                )}
            </div>

            {error && <p className="text-xs text-red-600 dark:text-red-300 m-0 px-4 py-2 border-t theme-border">{error}</p>}
        </div>
    );
}
