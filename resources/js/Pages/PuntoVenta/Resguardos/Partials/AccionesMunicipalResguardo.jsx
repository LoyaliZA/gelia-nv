import React, { useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { Download, FileText, RefreshCw, Truck } from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import {
    BTN_SECONDARY,
    BORDE_AVISO,
    BORDE_EXITO,
    RESGUARDOS_FILTRO_LABEL,
    TEXTO_EXITO,
    TEXTO_PELIGRO,
    THEME_INPUT,
    formatearFechaOperativa,
} from './resguardosStyles';
import {
    claveIdempotenciaSalidaTerminal,
    mensajeErrorSalidaTerminal,
    MAX_MOTIVO_REGENERACION,
    MAX_RECEPTOR_TERMINAL,
} from './salidaTerminalResguardoUtils';

const BTN_PRIMARIO = `${THEME_BTN_PRIMARY} inline-flex items-center justify-center gap-2 min-h-[44px] px-5 text-[10px] font-black uppercase tracking-widest`;
const BTN_SECUNDARIO = `${BTN_SECONDARY} inline-flex items-center justify-center gap-2 min-h-[44px] px-5 text-[10px] font-black uppercase tracking-widest no-underline`;

function Encabezado({ icono: Icono, titulo }) {
    return (
        <div className="flex items-center gap-2">
            <Icono className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">{titulo}</h3>
        </div>
    );
}

function recargar(onAccionExito) {
    if (onAccionExito) {
        onAccionExito();
        return;
    }
    router.reload({ only: ['resguardo', 'timeline', 'permisos'] });
}

/** Salida del paquete municipal a la terminal: registro ya hecho o formulario para registrarla. */
export function SeccionSalidaTerminalResguardo({ resguardo, onAccionExito, soloLectura = false }) {
    const [receptor, setReceptor] = useState('');
    const [enviando, setEnviando] = useState(false);
    const [error, setError] = useState(null);
    const [requiereActualizar, setRequiereActualizar] = useState(false);
    const [registrada, setRegistrada] = useState(null);
    const claveRef = useRef(null);

    const salida = resguardo.salida_terminal || registrada;

    if (salida) {
        return (
            <div className={`${geliaCardClass()} p-5 md:p-6 space-y-2 border ${BORDE_EXITO}`}>
                <Encabezado icono={Truck} titulo="Salida a terminal" />
                <p className={`text-sm font-semibold ${TEXTO_EXITO}`}>
                    {`Salida a terminal registrada · ${salida.receptor} · ${formatearFechaOperativa(salida.at)}`}
                </p>
            </div>
        );
    }

    if (soloLectura || !resguardo.puede_salida_terminal) {
        return null;
    }

    const receptorLimpio = receptor.trim();
    const bloqueado = enviando || receptorLimpio === '';

    const cambiarReceptor = (valor) => {
        setReceptor(valor);
        // Otro texto es otro intento: una clave nueva evita reutilizar la de un envío distinto.
        claveRef.current = null;
        setError(null);
        setRequiereActualizar(false);
    };

    const enviar = async (evento) => {
        evento.preventDefault();
        if (bloqueado) {
            return;
        }
        claveRef.current = claveRef.current || claveIdempotenciaSalidaTerminal(resguardo.id);
        setEnviando(true);
        setError(null);
        setRequiereActualizar(false);

        try {
            const { data } = await axios.put(route('punto_venta.resguardos.salida_terminal', resguardo.id), {
                version: resguardo.version,
                idempotency_key: claveRef.current,
                receptor: receptorLimpio,
            }, { headers: { Accept: 'application/json' } });
            claveRef.current = null;
            setRegistrada(data?.resguardo?.salida_terminal || null);
            recargar(onAccionExito);
        } catch (err) {
            setError(mensajeErrorSalidaTerminal(err));
            const status = err?.response?.status;
            setRequiereActualizar(status === 409 || Boolean(err?.response?.data?.errors?.version));
        } finally {
            setEnviando(false);
        }
    };

    return (
        <form onSubmit={enviar} className={`${geliaCardClass()} p-5 md:p-6 space-y-4`} noValidate>
            <Encabezado icono={Truck} titulo="Salida a terminal" />
            <label htmlFor={`receptor-terminal-${resguardo.id}`} className="block space-y-1.5">
                <span className={RESGUARDOS_FILTRO_LABEL}>Quién recibe en la terminal</span>
                <input
                    id={`receptor-terminal-${resguardo.id}`}
                    type="text"
                    value={receptor}
                    onChange={(e) => cambiarReceptor(e.target.value)}
                    maxLength={MAX_RECEPTOR_TERMINAL}
                    required
                    disabled={enviando}
                    autoComplete="off"
                    aria-invalid={error ? 'true' : undefined}
                    aria-describedby={error ? `receptor-terminal-error-${resguardo.id}` : undefined}
                    className={`${THEME_INPUT} min-h-[44px]`}
                />
            </label>
            {error && (
                <div
                    id={`receptor-terminal-error-${resguardo.id}`}
                    role="alert"
                    className={`rounded-2xl border ${BORDE_AVISO} p-3 flex flex-col sm:flex-row sm:items-center gap-3`}
                >
                    <p className="text-sm theme-text-main m-0 flex-1">{error}</p>
                    {requiereActualizar && (
                        <button
                            type="button"
                            onClick={() => {
                                // Tras refrescar, el siguiente envío es un intento nuevo con otra clave.
                                claveRef.current = null;
                                recargar(onAccionExito);
                            }}
                            className={`${BTN_SECONDARY} inline-flex items-center justify-center gap-2 min-h-[44px]`}
                        >
                            <RefreshCw className="w-4 h-4 shrink-0" /> Actualizar datos
                        </button>
                    )}
                </div>
            )}
            <div className="flex flex-wrap justify-end">
                <button type="submit" disabled={bloqueado} aria-disabled={bloqueado} className={`${BTN_PRIMARIO} w-full sm:w-auto disabled:opacity-50`}>
                    {enviando ? 'Registrando salida…' : 'Registrar salida a terminal'}
                </button>
            </div>
        </form>
    );
}

const ERROR_CARATULA = 'No se pudo generar la carátula. Intente de nuevo; si continúa, avise a Sistemas.';

/** Carátula del envío municipal: generar, descargar o regenerar según lo que el servidor permita. */
export function SeccionCaratulaMunicipalResguardo({ caratula, onAccionExito }) {
    const [pidiendoMotivo, setPidiendoMotivo] = useState(false);
    const [motivo, setMotivo] = useState('');
    const [enviando, setEnviando] = useState(false);
    const [error, setError] = useState(null);

    if (!caratula || !(caratula.puede_generar || caratula.puede_descargar || caratula.puede_regenerar)) {
        return null;
    }

    const existe = Boolean(caratula.id);
    const motivoLimpio = motivo.trim();
    const estado = existe
        ? `Versión ${caratula.version}${caratula.generada_at ? ` · generada ${formatearFechaOperativa(caratula.generada_at)}` : ''}`
        : 'Aún no hay carátula para este envío.';

    const lanzar = (nombreRuta, datos, alTerminar) => {
        setEnviando(true);
        setError(null);
        router.post(route(nombreRuta, caratula.pedido_id), datos, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const fallo = page?.props?.flash?.error;
                if (fallo) {
                    setError(fallo);
                    return;
                }
                alTerminar?.();
                recargar(onAccionExito);
            },
            onError: (errores) => {
                setError(Object.values(errores || {}).flat()[0] || ERROR_CARATULA);
            },
            // Una respuesta sin Inertia (5xx) o sin red se muestra en línea; false evita el modal de Inertia.
            onHttpException: () => {
                setError(ERROR_CARATULA);
                return false;
            },
            onNetworkError: () => {
                setError(ERROR_CARATULA);
                return false;
            },
            onFinish: () => setEnviando(false),
        });
    };

    const generar = () => lanzar('control_pedidos.tienda.municipal.caratula.generar', {});

    const regenerar = (evento) => {
        evento.preventDefault();
        if (enviando || motivoLimpio === '') {
            return;
        }
        lanzar('control_pedidos.tienda.municipal.caratula.regenerar', { motivo_regeneracion: motivoLimpio }, () => {
            setPidiendoMotivo(false);
            setMotivo('');
        });
    };

    return (
        <div className={`${geliaCardClass()} p-5 md:p-6 space-y-4`}>
            <Encabezado icono={FileText} titulo="Carátula del envío" />
            {pidiendoMotivo ? (
                <form onSubmit={regenerar} className="space-y-4" noValidate>
                    <p className="text-sm theme-text-muted">{estado}</p>
                    <label htmlFor={`motivo-caratula-${caratula.id}`} className="block space-y-1.5">
                        <span className={RESGUARDOS_FILTRO_LABEL}>Motivo de la regeneración</span>
                        <input
                            id={`motivo-caratula-${caratula.id}`}
                            type="text"
                            value={motivo}
                            onChange={(e) => setMotivo(e.target.value)}
                            maxLength={MAX_MOTIVO_REGENERACION}
                            required
                            disabled={enviando}
                            autoComplete="off"
                            className={`${THEME_INPUT} min-h-[44px]`}
                        />
                    </label>
                    <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => { setPidiendoMotivo(false); setMotivo(''); setError(null); }}
                            disabled={enviando}
                            className={`${BTN_SECUNDARIO} w-full sm:w-auto disabled:opacity-50`}
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={enviando || motivoLimpio === ''}
                            className={`${BTN_PRIMARIO} w-full sm:w-auto disabled:opacity-50`}
                        >
                            {enviando ? 'Regenerando…' : 'Confirmar regeneración'}
                        </button>
                    </div>
                </form>
            ) : (
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p className="text-sm theme-text-muted">{estado}</p>
                    <div className="flex flex-col sm:flex-row sm:justify-end gap-2">
                        {!existe && caratula.puede_generar && (
                            <button type="button" onClick={generar} disabled={enviando} className={`${BTN_PRIMARIO} w-full sm:w-auto disabled:opacity-50`}>
                                {enviando ? 'Generando…' : 'Generar carátula'}
                            </button>
                        )}
                        {existe && caratula.puede_descargar && (
                            <a
                                href={route('control_pedidos.tienda.municipal.caratula.pdf', [caratula.pedido_id, caratula.id])}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={`${BTN_SECUNDARIO} w-full sm:w-auto`}
                            >
                                <Download className="w-4 h-4 shrink-0" /> Descargar carátula
                            </a>
                        )}
                        {existe && caratula.puede_regenerar && (
                            <button type="button" onClick={() => setPidiendoMotivo(true)} className={`${BTN_SECUNDARIO} w-full sm:w-auto`}>
                                <RefreshCw className="w-4 h-4 shrink-0" /> Regenerar carátula
                            </button>
                        )}
                    </div>
                </div>
            )}

            {error && (
                <p role="alert" className={`text-sm ${TEXTO_PELIGRO}`}>{error}</p>
            )}
        </div>
    );
}
