import React, { useEffect, useMemo } from 'react';
import { THEME_INPUT, THEME_LABEL } from '../../../utils/geliaTheme';
import SelectorCatalogoFiscal from '../../../Components/Facturas/SelectorCatalogoFiscal';
import {
    esRegimenSueldosSalarios,
    usoCfdiParaRegimen,
} from '../../../utils/reglasCatalogosFiscales';
import { CAMPOS_DATOS_FISCALES } from './camposFacturaErrores';
import { estadoWrapCampoFactura, estiloWrapCampoFactura } from './wrapCampoFactura';

const INPUT_ERROR = '!border-red-500 focus:!border-red-500 focus:!ring-red-500/30';

const ETIQUETAS = Object.fromEntries(CAMPOS_DATOS_FISCALES.map((c) => [c.clave, c.etiqueta]));

export default function FormularioDatosFiscalesInline({
    valores,
    onChange,
    catalogos = { regimen_fiscal: [], uso_cfdi: [] },
    camposVisibles = null,
    camposIncorrectos = [],
    factura = null,
    errors = {},
}) {
    const visibles = camposVisibles?.length
        ? CAMPOS_DATOS_FISCALES.filter((c) => camposVisibles.includes(c.clave))
        : CAMPOS_DATOS_FISCALES;

    const usoBloqueado = esRegimenSueldosSalarios(valores.regimen_fiscal);

    useEffect(() => {
        const forzado = usoCfdiParaRegimen(valores.regimen_fiscal);
        if (forzado && valores.uso_factura !== forzado) {
            onChange({ ...valores, uso_factura: forzado });
        }
    }, [valores.regimen_fiscal]); // eslint-disable-line react-hooks/exhaustive-deps

    const opcionesPorCampo = useMemo(() => ({
        regimen_fiscal: catalogos.regimen_fiscal || [],
        uso_factura: catalogos.uso_cfdi || [],
    }), [catalogos]);

    const setCampo = (key, val) => onChange({ ...valores, [key]: val });

    return (
        <div className="space-y-3">
            {visibles.map(({ clave }) => {
                const label = ETIQUETAS[clave];
                const opciones = opcionesPorCampo[clave];
                const estadoWrap = estadoWrapCampoFactura(clave, camposIncorrectos, factura);
                const wrapStyle = estiloWrapCampoFactura(estadoWrap);
                const tieneError = Boolean(errors[clave]);

                return (
                    <div key={clave} className="space-y-1" style={wrapStyle}>
                        <label className={`${THEME_LABEL} ${tieneError ? '!text-red-500' : ''}`}>
                            {label}
                            {estadoWrap === 'corregido' && (
                                <span className="ml-2 normal-case tracking-normal font-black" style={{ color: '#4ade80' }}>
                                    Corregido
                                </span>
                            )}
                        </label>
                        {opciones?.length ? (
                            <SelectorCatalogoFiscal
                                opciones={opciones}
                                value={valores[clave] || ''}
                                onChange={(codigo) => {
                                    if (clave === 'regimen_fiscal') {
                                        const forzado = usoCfdiParaRegimen(codigo);
                                        onChange({
                                            ...valores,
                                            regimen_fiscal: codigo,
                                            ...(forzado ? { uso_factura: forzado } : {}),
                                        });
                                        return;
                                    }
                                    setCampo(clave, codigo);
                                }}
                                disabled={clave === 'uso_factura' && usoBloqueado}
                                invalid={tieneError}
                            />
                        ) : (
                            <input
                                type={clave === 'correo_electronico' ? 'email' : 'text'}
                                value={valores[clave] || ''}
                                onChange={(e) => {
                                    let val = e.target.value;
                                    if (clave === 'correo_electronico') val = val.toLowerCase();
                                    if (clave === 'telefono') val = val.replace(/\D/g, '').slice(0, 10);
                                    if (clave === 'rfc') val = val.toUpperCase().replace(/[^A-ZÑ&0-9]/gi, '').slice(0, 13);
                                    if (clave === 'codigo_postal') val = val.replace(/\D/g, '').slice(0, 5);
                                    setCampo(clave, val);
                                }}
                                className={`${THEME_INPUT} ${tieneError ? INPUT_ERROR : ''}`}
                            />
                        )}
                        {tieneError && <p className="text-xs text-red-500 font-bold m-0">{errors[clave]}</p>}
                    </div>
                );
            })}
        </div>
    );
}
