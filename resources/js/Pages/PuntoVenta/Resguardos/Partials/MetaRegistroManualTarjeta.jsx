import React from 'react';
import { UserRound } from 'lucide-react';
import { TONO_PRIMARIO } from './resguardosStyles';

const ETIQUETA_AREA_ORIGEN =
    `inline-flex max-w-full px-2 py-1 rounded-lg text-[9px] font-black uppercase tracking-wide truncate ${TONO_PRIMARIO}`;

export default function MetaRegistroManualTarjeta({ resguardo }) {
    const registro = resguardo?.registro_manual;
    if (!registro) {
        if (!resguardo?.sucursal?.nombre) {
            return null;
        }

        return (
            <span className={`shrink-0 ${ETIQUETA_AREA_ORIGEN} max-w-[40%]`} title={resguardo.sucursal.nombre}>
                {resguardo.sucursal.nombre}
            </span>
        );
    }

    const areaOrigen = registro.departamento_nombre || registro.origen_nombre || null;
    const sucursal = resguardo.sucursal?.nombre || null;
    const registradoPor = registro.registrado_por || null;

    return (
        <div className="shrink-0 flex flex-col items-end gap-1.5 min-w-0 max-w-[48%]">
            {sucursal && (
                <p
                    className="text-[9px] font-semibold theme-text-muted m-0 truncate max-w-full text-right"
                    title={`Sucursal: ${sucursal}`}
                >
                    {sucursal}
                </p>
            )}
            {areaOrigen && (
                <span className={ETIQUETA_AREA_ORIGEN} title={`Área de origen: ${areaOrigen}`}>
                    {areaOrigen}
                </span>
            )}
            {registradoPor && (
                <p
                    className="inline-flex items-center justify-end gap-1 min-w-0 max-w-full text-[9px] font-medium theme-text-muted m-0"
                    title={`Registrado por ${registradoPor}`}
                >
                    <UserRound className="w-3 h-3 shrink-0 opacity-70" aria-hidden />
                    <span className="truncate">{registradoPor}</span>
                </p>
            )}
        </div>
    );
}
