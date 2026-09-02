import React from 'react';
import { Package } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { formatearFechaOperativa } from './resguardosStyles';
import {
    cantidadBultosPendiente,
    cantidadBultosRecibida,
    esRecepcionComplementaria,
} from './recepcionFisicaUtils';

export default function ResumenRecepcionParcial({ resguardo, catalogos = {} }) {
    const esperada = resguardo.cantidad_bultos_esperada;
    const recibida = cantidadBultosRecibida(resguardo);
    const pendiente = cantidadBultosPendiente(resguardo);
    const complementaria = esRecepcionComplementaria(resguardo);
    const bultosRecibidos = resguardo.bultos_recibidos || [];
    const tiposBulto = catalogos.tipos_bulto || {};

    return (
        <div className="space-y-4">
            <div className={`${geliaCardClass()} p-5 space-y-4`}>
                <div className="flex items-center gap-2">
                    <Package className="w-4 h-4 shrink-0" style={{ color: 'var(--color-primario)' }} />
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        {complementaria ? 'Recepción en curso' : 'Cantidades del resguardo'}
                    </h2>
                </div>
                <div className="grid grid-cols-3 gap-2 sm:gap-3">
                    <CantidadResumen label="Declarados" value={esperada} />
                    <CantidadResumen label="Recibidos" value={recibida} destacado />
                    <CantidadResumen label="Pendientes" value={pendiente} alerta={pendiente > 0} />
                </div>
                {complementaria && pendiente > 0 && (
                    <p className="text-sm theme-text-muted m-0">
                        Registra solo los bultos que llegan en esta visita. Faltan {pendiente} por recibir.
                    </p>
                )}
            </div>

            {bultosRecibidos.length > 0 && (
                <div className={`${geliaCardClass()} p-5 space-y-3`}>
                    <h3 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        Llegadas anteriores
                    </h3>
                    <ol className="space-y-2 m-0 p-0 list-none">
                        {bultosRecibidos.map((bulto) => (
                            <li
                                key={bulto.id || bulto.folio}
                                className="rounded-2xl border theme-border p-3 flex flex-wrap items-center justify-between gap-2"
                            >
                                <div className="min-w-0">
                                    <p className="text-sm font-black theme-text-main m-0 truncate">
                                        {bulto.folio}
                                    </p>
                                    <p className="text-[10px] theme-text-muted m-0">
                                        {tiposBulto[bulto.tipo] || bulto.tipo}
                                    </p>
                                </div>
                                <p className="text-[10px] font-bold theme-text-muted m-0 shrink-0">
                                    {formatearFechaOperativa(bulto.recepcion_at)}
                                </p>
                            </li>
                        ))}
                    </ol>
                </div>
            )}
        </div>
    );
}

function CantidadResumen({ label, value, destacado = false, alerta = false }) {
    const claseValor = alerta
        ? 'text-amber-600 dark:text-amber-300'
        : destacado
            ? 'text-emerald-600 dark:text-emerald-300'
            : 'theme-text-main';

    return (
        <div className="rounded-2xl border theme-border p-3 text-center">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
            <p className={`text-xl font-black tabular-nums m-0 mt-1 ${claseValor}`}>{value}</p>
        </div>
    );
}
