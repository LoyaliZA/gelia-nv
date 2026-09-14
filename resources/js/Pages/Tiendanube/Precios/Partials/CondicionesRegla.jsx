import React from 'react';
import { GELIA_BTN_OUTLINE, GELIA_FIELDSET_LEGEND } from '../../../../utils/geliaTheme';

const OPERADORES_RANGO = ['entre'];

export default function CondicionesRegla({ condiciones = [], metadatos, listas = [], onChange, errores = {} }) {
    const campos = metadatos?.campos_condicion || [];
    const operadores = metadatos?.operadores || [];

    const agregar = () => {
        onChange([
            ...condiciones,
            {
                campo: 'precio_normal_actual',
                operador: '<',
                valor: '',
                valor_hasta: '',
                inclusivo_desde: true,
                inclusivo_hasta: true,
                lista_id: '',
            },
        ]);
    };

    const actualizar = (idx, patch) => {
        onChange(condiciones.map((c, i) => (i === idx ? { ...c, ...patch } : c)));
    };

    const quitar = (idx) => {
        onChange(condiciones.filter((_, i) => i !== idx));
    };

    return (
        <fieldset className="space-y-3">
            <legend className={GELIA_FIELDSET_LEGEND}>
                Condiciones (todas deben cumplirse)
            </legend>
            {condiciones.length === 0 && (
                <p className="text-sm theme-text-muted m-0">Sin condiciones: aplica a todas las variantes seleccionadas.</p>
            )}
            {condiciones.map((cond, idx) => {
                const esRango = OPERADORES_RANGO.includes(cond.operador);
                const requiereValor = !['tiene_valor', 'no_tiene_valor'].includes(cond.operador);
                const esLista = cond.campo === 'lista_referencia';
                const err = errores[`condicion_${idx}`];

                return (
                    <div key={idx} className="border theme-border rounded-xl p-3 space-y-2">
                        <div className="grid md:grid-cols-2 gap-2">
                            <label className="block text-xs theme-text-main">
                                Campo
                                <select
                                    className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                                    value={cond.campo}
                                    onChange={(e) => actualizar(idx, { campo: e.target.value, lista_id: '' })}
                                >
                                    {campos.map((o) => (
                                        <option key={o.value} value={o.value}>{o.label}</option>
                                    ))}
                                </select>
                            </label>
                            <label className="block text-xs theme-text-main">
                                Operador
                                <select
                                    className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                                    value={cond.operador}
                                    onChange={(e) => actualizar(idx, { operador: e.target.value })}
                                >
                                    {operadores.map((o) => (
                                        <option key={o.value} value={o.value}>{o.label}</option>
                                    ))}
                                </select>
                            </label>
                        </div>
                        {esLista && (
                            <label className="block text-xs theme-text-main">
                                Lista
                                <select
                                    className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                                    value={cond.lista_id || ''}
                                    onChange={(e) => actualizar(idx, { lista_id: e.target.value })}
                                >
                                    <option value="">Seleccione lista</option>
                                    {listas.map((l) => (
                                        <option key={l.id} value={l.id}>{l.nombre}</option>
                                    ))}
                                </select>
                            </label>
                        )}
                        {requiereValor && !esRango && (
                            <label className="block text-xs theme-text-main">
                                Valor
                                <input
                                    type="text"
                                    inputMode="decimal"
                                    className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                                    value={cond.valor || ''}
                                    onChange={(e) => actualizar(idx, { valor: e.target.value })}
                                    placeholder="Ej. 100"
                                    aria-invalid={!!err}
                                />
                            </label>
                        )}
                        {esRango && (
                            <div className="grid md:grid-cols-2 gap-2">
                                <label className="block text-xs theme-text-main">
                                    Desde (inclusivo)
                                    <input
                                        type="text"
                                        inputMode="decimal"
                                        className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                                        value={cond.valor || ''}
                                        onChange={(e) => actualizar(idx, { valor: e.target.value })}
                                    />
                                </label>
                                <label className="block text-xs theme-text-main">
                                    Hasta (inclusivo)
                                    <input
                                        type="text"
                                        inputMode="decimal"
                                        className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                                        value={cond.valor_hasta || ''}
                                        onChange={(e) => actualizar(idx, { valor_hasta: e.target.value })}
                                    />
                                </label>
                            </div>
                        )}
                        {err && <p className="text-xs text-red-600 m-0" role="alert">{err}</p>}
                        <button
                            type="button"
                            onClick={() => quitar(idx)}
                            className={`${GELIA_BTN_OUTLINE} text-[10px]`}
                        >
                            Quitar condición
                        </button>
                    </div>
                );
            })}
            <button
                type="button"
                onClick={agregar}
                className={GELIA_BTN_OUTLINE}
            >
                Agregar condición
            </button>
        </fieldset>
    );
}
