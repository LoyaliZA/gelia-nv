import { useId, useMemo, useState } from 'react';
import { THEME_INPUT, THEME_SELECT } from '../../../utils/geliaTheme';
import Interruptor from './Interruptor';
import {
    escribirRuta,
    filasMapa,
    idsATexto,
    leerRuta,
    listaIds,
    mapaDesdeFilas,
    parseLista,
    parseObjeto,
} from './editoresJson';

const claseCampo = `${THEME_INPUT} w-full px-3 py-2 text-sm`;

function CampoTexto({ id, tipo, valor, onChange, min, max }) {
    const type = tipo === 'time' ? 'time' : tipo === 'number' ? 'number' : 'text';
    return (
        <input
            id={id}
            type={type}
            min={min}
            max={max}
            value={valor ?? ''}
            onChange={(e) => onChange(e.target.value)}
            className={claseCampo}
        />
    );
}

export default function EditorEstructurado({ valorInicial, esquema, onChange }) {
    const baseId = useId();
    const inicial = useMemo(() => (
        esquema.tipoRaiz === 'ids' ? parseLista(valorInicial) : parseObjeto(valorInicial)
    ), [esquema.tipoRaiz, valorInicial]);

    const [objeto, setObjeto] = useState(() => (esquema.tipoRaiz === 'ids' ? {} : inicial));
    const [textoIds, setTextoIds] = useState(() => (
        esquema.tipoRaiz === 'ids'
            ? idsATexto(inicial)
            : ''
    ));
    const [textosRuta, setTextosRuta] = useState(() => textosIniciales(inicial, esquema));
    const [filas, setFilas] = useState(() => filasIniciales(inicial, esquema));

    const publicarObjeto = (siguiente) => {
        setObjeto(siguiente);
        onChange(JSON.stringify(siguiente));
    };

    const cambiarRuta = (ruta, valor) => {
        publicarObjeto(escribirRuta(objeto, ruta, valor));
    };

    if (esquema.tipoRaiz === 'ids') {
        return (
            <div>
                <label className="block text-sm font-semibold theme-text-main mb-1" htmlFor="ids-config">
                    {esquema.titulo}
                </label>
                <p className="text-sm theme-text-muted m-0 mb-2">{esquema.ayuda}</p>
                <input
                    id="ids-config"
                    className={claseCampo}
                    value={textoIds}
                    onChange={(e) => {
                        setTextoIds(e.target.value);
                        onChange(JSON.stringify(listaIds(e.target.value)));
                    }}
                />
            </div>
        );
    }

    if (esquema.tipoRaiz === 'mapa') {
        return (
            <MapaFilas
                filas={filas}
                esquema={esquema}
                onChange={(siguientes) => {
                    setFilas(siguientes);
                    const mapa = mapaDesdeFilas(siguientes, esquema.modo);
                    if (esquema.ruta) {
                        onChange(JSON.stringify(escribirRuta(objeto, esquema.ruta, mapa)));
                    } else {
                        onChange(JSON.stringify(mapa));
                    }
                }}
            />
        );
    }

    return (
        <div className="space-y-4">
            {(esquema.campos || []).map((campo) => {
                const id = `${baseId}-${campo.ruta}`;
                const actual = leerRuta(objeto, campo.ruta);

                if (campo.tipo === 'switch') {
                    return (
                        <div key={campo.ruta} className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-sm font-semibold theme-text-main m-0">{campo.titulo}</p>
                                <p className="text-sm theme-text-muted m-0 mt-1">{campo.ayuda}</p>
                            </div>
                            <Interruptor
                                activo={Boolean(actual)}
                                etiqueta={campo.titulo}
                                onChange={(activo) => cambiarRuta(campo.ruta, activo)}
                            />
                        </div>
                    );
                }

                if (campo.tipo === 'dias') {
                    const elegidos = Array.isArray(actual) ? actual.map((n) => String(n)) : [];
                    return (
                        <fieldset key={campo.ruta}>
                            <legend className="text-sm font-semibold theme-text-main">{campo.titulo}</legend>
                            <p className="text-sm theme-text-muted m-0 mt-1 mb-2">{campo.ayuda}</p>
                            <div className="flex flex-wrap gap-2">
                                {campo.opciones.map((dia) => {
                                    const activo = elegidos.includes(dia.value);
                                    return (
                                        <button
                                            key={dia.value}
                                            type="button"
                                            aria-pressed={activo}
                                            onClick={() => {
                                                const siguiente = activo
                                                    ? elegidos.filter((item) => item !== dia.value)
                                                    : [...elegidos, dia.value];
                                                cambiarRuta(campo.ruta, siguiente.map((n) => parseInt(n, 10)).sort((a, b) => a - b));
                                            }}
                                            className={`min-h-11 min-w-11 px-3 rounded-xl border theme-border text-sm font-semibold ${activo ? 'theme-text-main' : 'theme-text-muted'}`}
                                            style={activo ? { backgroundColor: 'color-mix(in srgb, var(--color-primario) 18%, transparent)' } : undefined}
                                        >
                                            {dia.label}
                                        </button>
                                    );
                                })}
                            </div>
                        </fieldset>
                    );
                }

                if (campo.tipo === 'ids') {
                    return (
                        <div key={campo.ruta}>
                            <label className="block text-sm font-semibold theme-text-main mb-1" htmlFor={id}>{campo.titulo}</label>
                            <p className="text-sm theme-text-muted m-0 mb-2">{campo.ayuda}</p>
                            <input
                                id={id}
                                className={claseCampo}
                                value={textosRuta[campo.ruta] ?? ''}
                                onChange={(e) => {
                                    const texto = e.target.value;
                                    setTextosRuta((prev) => ({ ...prev, [campo.ruta]: texto }));
                                    cambiarRuta(campo.ruta, listaIds(texto));
                                }}
                            />
                        </div>
                    );
                }

                return (
                    <div key={campo.ruta}>
                        <label className="block text-sm font-semibold theme-text-main mb-1" htmlFor={id}>{campo.titulo}</label>
                        <p className="text-sm theme-text-muted m-0 mb-2">{campo.ayuda}</p>
                        {campo.tipo === 'select' ? (
                            <select
                                id={id}
                                className={`${THEME_SELECT} w-full px-3 py-2 text-sm`}
                                value={actual ?? ''}
                                onChange={(e) => cambiarRuta(campo.ruta, e.target.value)}
                            >
                                {opcionesCampo(campo, actual).map((item) => (
                                    <option key={item.value} value={item.value}>{item.label}</option>
                                ))}
                            </select>
                        ) : (
                            <CampoTexto
                                id={id}
                                tipo={campo.tipo}
                                min={campo.min}
                                max={campo.max}
                                valor={actual === null || actual === undefined ? '' : actual}
                                onChange={(texto) => {
                                    if (campo.tipo === 'number') {
                                        cambiarRuta(campo.ruta, texto === '' ? '' : Number(texto));
                                    } else if (campo.tipo === 'time') {
                                        cambiarRuta(campo.ruta, texto === '' ? null : texto);
                                    } else {
                                        cambiarRuta(campo.ruta, texto);
                                    }
                                }}
                            />
                        )}
                    </div>
                );
            })}
        </div>
    );
}

function opcionesCampo(campo, actual) {
    const opciones = [...(campo.opciones || [])];
    const valor = actual === null || actual === undefined ? '' : String(actual);
    if (valor && !opciones.some((item) => item.value === valor)) {
        opciones.push({ value: valor, label: valor });
    }
    return opciones;
}

function textosIniciales(objeto, esquema) {
    const textos = {};
    (esquema.campos || []).forEach((campo) => {
        if (campo.tipo === 'ids') {
            textos[campo.ruta] = idsATexto(leerRuta(objeto, campo.ruta));
        }
    });
    return textos;
}

function filasIniciales(objeto, esquema) {
    if (esquema.tipoRaiz !== 'mapa') {
        return [];
    }
    const origen = esquema.ruta ? leerRuta(objeto, esquema.ruta) : objeto;
    const filas = filasMapa(origen);
    return filas.length ? filas : [{ clave: '', valor: '' }];
}

function MapaFilas({ filas, esquema, onChange }) {
    const actualizar = (indice, patch) => {
        onChange(filas.map((fila, i) => (i === indice ? { ...fila, ...patch } : fila)));
    };

    return (
        <div className="space-y-3">
            <p className="text-sm theme-text-muted m-0">{esquema.ayudaFila}</p>
            {filas.map((fila, indice) => (
                <div key={indice} className="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 items-end">
                    <div>
                        <label className="block text-sm font-semibold theme-text-main mb-1" htmlFor={`mapa-clave-${indice}`}>
                            {esquema.tituloClave}
                        </label>
                        <input
                            id={`mapa-clave-${indice}`}
                            className={claseCampo}
                            value={fila.clave}
                            onChange={(e) => actualizar(indice, { clave: e.target.value })}
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-semibold theme-text-main mb-1" htmlFor={`mapa-valor-${indice}`}>
                            {esquema.tituloValor}
                        </label>
                        <input
                            id={`mapa-valor-${indice}`}
                            className={claseCampo}
                            value={fila.valor}
                            onChange={(e) => actualizar(indice, { valor: e.target.value })}
                        />
                    </div>
                    <button
                        type="button"
                        className="theme-btn-secondary min-h-11 px-3"
                        onClick={() => onChange(filas.filter((_, i) => i !== indice))}
                    >
                        Quitar
                    </button>
                </div>
            ))}
            <button
                type="button"
                className="theme-btn-secondary min-h-11 px-3"
                onClick={() => onChange([...filas, { clave: '', valor: '' }])}
            >
                Agregar
            </button>
        </div>
    );
}
