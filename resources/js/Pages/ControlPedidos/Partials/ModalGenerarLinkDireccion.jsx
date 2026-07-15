import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { Copy, Link2, Search, X } from 'lucide-react';
import {
    THEME_INPUT,
    THEME_SELECT,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '../../../utils/geliaTheme';
import { labelAccionSolicitud, LABELS_ACCION_SOLICITUD } from './DireccionPedidoResumen';

const ACCIONES = Object.keys(LABELS_ACCION_SOLICITUD);

async function copiarAlPortapapeles(texto) {
    if (!texto) return false;
    if (navigator.clipboard?.writeText && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(texto);
            return true;
        } catch {
            return false;
        }
    }
    try {
        const textArea = document.createElement('textarea');
        textArea.value = texto;
        textArea.setAttribute('readonly', '');
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        document.body.appendChild(textArea);
        textArea.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(textArea);
        return ok;
    } catch {
        return false;
    }
}

/**
 * Modal compartido para generar enlace de dirección.
 */
export default function ModalGenerarLinkDireccion({
    abierto,
    onClose,
    clientePreseleccionado = null,
    onEnlaceGenerado = null,
}) {
    const [busqueda, setBusqueda] = useState('');
    const [resultados, setResultados] = useState([]);
    const [buscando, setBuscando] = useState(false);
    const [cliente, setCliente] = useState(null);
    const [accion, setAccion] = useState('register_first_address');
    const [direccionesCliente, setDireccionesCliente] = useState([]);
    const [enviando, setEnviando] = useState(false);
    const [enlaceUrl, setEnlaceUrl] = useState('');
    const [error, setError] = useState('');
    const [copiado, setCopiado] = useState(false);
    const debounce = useRef(null);

    const principal = direccionesCliente.find((d) => d.es_principal) || direccionesCliente[0] || null;
    const tieneDirecciones = direccionesCliente.length > 0;

    useEffect(() => {
        if (!abierto) {
            setBusqueda('');
            setResultados([]);
            setCliente(clientePreseleccionado || null);
            setAccion('register_first_address');
            setDireccionesCliente([]);
            setEnlaceUrl('');
            setError('');
            setCopiado(false);
            setEnviando(false);
            return;
        }
        setCliente(clientePreseleccionado || null);
    }, [abierto, clientePreseleccionado]);

    useEffect(() => {
        if (!abierto || !cliente?.id) {
            setDireccionesCliente([]);
            return;
        }

        axios.get(`/api/clientes/id/${cliente.id}/direccion-envio`)
            .then(({ data }) => {
                const dirs = data?.direcciones || [];
                setDireccionesCliente(dirs);
                const princ = dirs.find((d) => d.es_principal) || dirs[0];
                setAccion(princ ? 'update_address' : 'register_first_address');
            })
            .catch(() => {
                setDireccionesCliente([]);
                setAccion('register_first_address');
            });
    }, [abierto, cliente?.id]);

    useEffect(() => {
        if (!abierto || clientePreseleccionado) return;
        if (debounce.current) clearTimeout(debounce.current);
        if (busqueda.trim().length < 2) {
            setResultados([]);
            return;
        }
        debounce.current = setTimeout(async () => {
            setBuscando(true);
            try {
                const { data } = await axios.get('/api/clientes', { params: { q: busqueda.trim() } });
                setResultados(Array.isArray(data) ? data : (data?.data || []));
            } catch {
                setResultados([]);
            } finally {
                setBuscando(false);
            }
        }, 300);
        return () => clearTimeout(debounce.current);
    }, [busqueda, abierto, clientePreseleccionado]);

    if (!abierto) return null;

    const generarEnlace = async () => {
        if (!cliente?.id) return;

        if (accion === 'update_address' && !principal) {
            setError('Este cliente no tiene dirección verificada para actualizar.');
            return;
        }

        setEnviando(true);
        setError('');

        const payload = { accion };
        if (accion === 'update_address' && principal?.id) {
            payload.direccion_id = principal.id;
        }

        try {
            const { data } = await axios.post(
                route('control_pedidos.enlace_direccion', cliente.id),
                payload,
                { headers: { Accept: 'application/json' } },
            );
            if (data?.url) {
                setEnlaceUrl(data.url);
                onEnlaceGenerado?.();
            } else {
                setError('No se recibió la URL del enlace.');
            }
        } catch (err) {
            const msg = err?.response?.data?.message
                || err?.response?.data?.errors?.accion?.[0]
                || 'No se pudo generar el enlace.';
            setError(msg);
        } finally {
            setEnviando(false);
        }
    };

    const copiar = async () => {
        if (!enlaceUrl) return;
        const ok = await copiarAlPortapapeles(enlaceUrl);
        if (ok) {
            setCopiado(true);
            setTimeout(() => setCopiado(false), 2000);
        }
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 border-b theme-border flex justify-between items-start">
                    <div>
                        <h2 className="text-lg font-black italic uppercase theme-text-main m-0 flex items-center gap-2">
                            <Link2 className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                            Link de dirección
                        </h2>
                        <p className="text-xs theme-text-muted mt-1 m-0">
                            Elija la acción del enlace. El cliente solo verá el formulario, sin decisiones.
                        </p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="p-5 space-y-4">
                    {!cliente && !clientePreseleccionado ? (
                        <div>
                            <label className="block">
                                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Buscar cliente</span>
                                <div className="relative mt-1.5">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                    <input
                                        className={`${THEME_INPUT} pl-9`}
                                        value={busqueda}
                                        onChange={(e) => setBusqueda(e.target.value)}
                                        placeholder="Número o nombre…"
                                        autoFocus
                                    />
                                </div>
                            </label>
                            {(buscando || resultados.length > 0 || busqueda.trim().length >= 2) && (
                                <div className="mt-2 rounded-xl border theme-border max-h-48 overflow-y-auto">
                                    {buscando && (
                                        <p className="p-3 text-xs font-bold theme-text-muted m-0">Buscando…</p>
                                    )}
                                    {!buscando && resultados.length === 0 && (
                                        <p className="p-3 text-xs font-bold theme-text-muted m-0">Sin coincidencias</p>
                                    )}
                                    {resultados.map((c) => (
                                        <button
                                            key={c.id}
                                            type="button"
                                            onClick={() => setCliente(c)}
                                            className="w-full text-left px-3 py-2.5 border-b theme-border last:border-0 hover:bg-black/5 dark:hover:bg-white/5"
                                        >
                                            <span className="text-xs font-black theme-text-main">{c.numero_cliente}</span>
                                            <span className="text-xs theme-text-muted ml-2">{c.nombre}</span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="rounded-xl border theme-border theme-element px-4 py-3">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Cliente</p>
                            <p className="text-sm font-bold theme-text-main m-0 mt-1">
                                {cliente.numero_cliente} · {cliente.nombre}
                            </p>
                            {!clientePreseleccionado && (
                                <button
                                    type="button"
                                    className="mt-2 text-[10px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main"
                                    onClick={() => { setCliente(null); setEnlaceUrl(''); setError(''); }}
                                >
                                    Cambiar cliente
                                </button>
                            )}
                        </div>
                    )}

                    {cliente && !enlaceUrl && (
                        <>
                            <label className="block">
                                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Acción del enlace</span>
                                <select
                                    className={`${THEME_SELECT} mt-1.5 w-full`}
                                    value={accion}
                                    onChange={(e) => { setAccion(e.target.value); setError(''); }}
                                >
                                    {ACCIONES.map((a) => (
                                        <option key={a} value={a}>{labelAccionSolicitud(a)}</option>
                                    ))}
                                </select>
                            </label>

                            {accion === 'update_address' && !tieneDirecciones && (
                                <p className="text-xs font-bold text-amber-700 dark:text-amber-300 m-0">
                                    Este cliente no tiene direcciones verificadas. Use «Registrar primera dirección» o espere a que exista una dirección principal.
                                </p>
                            )}
                            {accion === 'register_first_address' && tieneDirecciones && (
                                <p className="text-xs font-bold text-amber-700 dark:text-amber-300 m-0">
                                    El cliente ya tiene direcciones registradas. Si continúa, el sistema creará una adicional si el cliente envía el formulario.
                                </p>
                            )}

                            {error && (
                                <p className="text-xs font-bold text-red-600 m-0">{error}</p>
                            )}
                            <button
                                type="button"
                                disabled={enviando || (accion === 'update_address' && !principal)}
                                onClick={generarEnlace}
                                className={`${THEME_BTN_PRIMARY} w-full disabled:opacity-60`}
                            >
                                {enviando ? 'Generando…' : 'Generar enlace'}
                            </button>
                        </>
                    )}

                    {enlaceUrl && (
                        <div className="space-y-3">
                            <p className="text-xs font-bold theme-text-main m-0">
                                Enlace listo ({labelAccionSolicitud(accion)}):
                            </p>
                            <div className="flex gap-2">
                                <input
                                    readOnly
                                    className={`${THEME_INPUT} flex-1 text-xs font-mono`}
                                    value={enlaceUrl}
                                />
                                <button type="button" onClick={copiar} className={`${THEME_BTN_SECONDARY} shrink-0 inline-flex items-center gap-1`}>
                                    <Copy className="w-4 h-4" />
                                    {copiado ? 'Copiado' : 'Copiar'}
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>,
        document.body,
    );
}
