import { useEffect, useState } from 'react';
import { THEME_INPUT, THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import { esBooleanoVerdadero, opcionesVisibles } from './configDisplay';
import Interruptor from './Interruptor';

const claseCampo = `${THEME_INPUT} w-full px-3 py-2 text-sm`;

export default function AjusteFila({ config, meta, guardando, aviso, onGuardar }) {
    const valorInicial = meta.control === 'password' ? '' : (config.valor || '');
    const [valor, setValor] = useState(valorInicial);
    const [quitar, setQuitar] = useState(false);

    useEffect(() => {
        setValor(meta.control === 'password' ? '' : (config.valor || ''));
        setQuitar(false);
    }, [config.id, config.valor, meta.control]);

    const sucio = meta.control === 'password'
        ? (valor !== '' || quitar)
        : valor !== (config.valor || '');

    const guardar = () => {
        if (meta.control === 'password') {
            onGuardar(config, quitar ? '' : valor, { borrar_secreto: quitar && valor === '' });
            return;
        }
        onGuardar(config, valor);
    };

    return (
        <div className="py-4 border-b theme-border last:border-b-0">
            <div className={`flex flex-col gap-3 ${meta.control === 'switch' ? 'sm:flex-row sm:items-center sm:justify-between' : ''}`}>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold theme-text-main m-0">{meta.titulo}</p>
                    <p className="text-sm theme-text-muted m-0 mt-1 max-w-prose">{meta.ayuda}</p>
                    <details className="mt-2">
                        <summary className="text-sm theme-text-muted cursor-pointer">Código interno</summary>
                        <p className="text-sm theme-text-muted m-0 mt-1 break-all">{config.clave}</p>
                    </details>
                </div>

                <div className={meta.control === 'switch' ? 'shrink-0' : 'w-full sm:max-w-md'}>
                    {meta.control === 'switch' ? (
                        <Interruptor
                            activo={esBooleanoVerdadero(config.valor)}
                            etiqueta={meta.titulo}
                            disabled={guardando}
                            onChange={(activo) => onGuardar(config, activo ? 'true' : 'false')}
                        />
                    ) : meta.control === 'password' ? (
                        <CampoSecreto
                            config={config}
                            valor={valor}
                            quitar={quitar}
                            onValor={setValor}
                            onQuitar={setQuitar}
                        />
                    ) : meta.control === 'select' ? (
                        <select
                            aria-label={meta.titulo}
                            className={`${THEME_SELECT} w-full px-3 py-2 text-sm`}
                            value={valor}
                            onChange={(e) => setValor(e.target.value)}
                        >
                            {opcionesVisibles(meta, config.valor).map((item) => (
                                <option key={item.value} value={item.value}>{item.label}</option>
                            ))}
                        </select>
                    ) : meta.control === 'area' ? (
                        <textarea
                            aria-label={meta.titulo}
                            className={`${THEME_TEXTAREA} w-full px-3 py-2 text-sm`}
                            rows={4}
                            value={valor}
                            onChange={(e) => setValor(e.target.value)}
                        />
                    ) : (
                        <input
                            aria-label={meta.titulo}
                            type={tipoInput(meta.control)}
                            min={meta.min}
                            max={meta.max}
                            step={meta.paso}
                            className={claseCampo}
                            value={valor}
                            onChange={(e) => setValor(e.target.value)}
                        />
                    )}

                    {meta.control !== 'switch' && (
                        <div className="mt-2 flex items-center gap-3">
                            <button
                                type="button"
                                className="theme-btn-primary theme-btn-primary--compact min-h-11 disabled:opacity-50"
                                disabled={!sucio || guardando}
                                onClick={guardar}
                            >
                                {guardando ? 'Guardando…' : 'Guardar'}
                            </button>
                            {aviso && <EstadoGuardado aviso={aviso} />}
                        </div>
                    )}
                    {meta.control === 'switch' && aviso && (
                        <EstadoGuardado aviso={aviso} />
                    )}
                </div>
            </div>
        </div>
    );
}

function tipoInput(control) {
    if (control === 'number' || control === 'decimal') return 'number';
    if (control === 'time') return 'time';
    if (control === 'date') return 'date';
    return 'text';
}

function CampoSecreto({ config, valor, quitar, onValor, onQuitar }) {
    const tiene = String(config.valor || '') !== '';
    return (
        <div className="space-y-2">
            <input
                aria-label={tiene ? 'Nuevo valor' : 'Valor'}
                type="password"
                autoComplete="new-password"
                className={claseCampo}
                value={quitar ? '' : valor}
                placeholder={tiene ? 'Hay un valor guardado' : 'Sin valor'}
                disabled={quitar}
                onChange={(e) => {
                    onQuitar(false);
                    onValor(e.target.value);
                }}
            />
            {tiene && (
                <label className="flex items-center gap-2 text-sm theme-text-main">
                    <input
                        type="checkbox"
                        checked={quitar}
                        onChange={(e) => {
                            onQuitar(e.target.checked);
                            if (e.target.checked) onValor('');
                        }}
                    />
                    Quitar valor guardado
                </label>
            )}
        </div>
    );
}

function EstadoGuardado({ aviso }) {
    return (
        <p
            className={`text-sm m-0 ${aviso.tipo === 'ok' ? 'theme-text-exito' : 'theme-text-peligro'}`}
            role={aviso.tipo === 'ok' ? 'status' : 'alert'}
        >
            {aviso.texto}
        </p>
    );
}
