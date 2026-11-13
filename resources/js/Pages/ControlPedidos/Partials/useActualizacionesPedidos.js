import { useEffect, useRef } from 'react';

const listeners = new Set();
let echo;
let canal;
function emitir(evento) { for (const listener of listeners) listener(evento); }
function conectar() {
    if (canal || !window.Echo) return;
    echo = window.Echo;
    canal = echo.private('control-pedidos.operaciones');
    canal.listen('.pedido.actualizado', emitir);
}

export default function useActualizacionesPedidos({ onActualizar, pedidoId = null, pausado = false, onPendiente = null, activo = true, intervaloRespaldo = 0 }) {
    const actual = useRef({ onActualizar, pausado, onPendiente });
    actual.current = { onActualizar, pausado, onPendiente };
    const pendiente = useRef(false);
    const estaPausado = () => typeof actual.current.pausado === 'function' ? actual.current.pausado() : actual.current.pausado;
    useEffect(() => {
        if (!activo) return undefined;
        let timeout;
        const cambio = (evento = {}) => {
            evento = evento.detail || evento;
            if (pedidoId && evento.pedido_id && Number(evento.pedido_id) !== Number(pedidoId)) return;
            if (estaPausado()) { pendiente.current = true; actual.current.onPendiente?.(); return; }
            clearTimeout(timeout);
            timeout = setTimeout(() => { if (estaPausado()) { pendiente.current = true; actual.current.onPendiente?.(); return; } actual.current.onActualizar?.(evento); }, 180);
        };
        const reconectar = () => { conectar(); cambio(); };
        listeners.add(cambio);
        conectar();
        window.addEventListener('online', reconectar);
        window.addEventListener('focus', cambio);
        window.addEventListener('pedido-bma.actualizado', cambio);
        const connection = echo?.connector?.pusher?.connection;
        connection?.bind('connected', reconectar);
        const respaldo = intervaloRespaldo > 0 ? window.setInterval(() => {
            if (echo?.connector?.pusher?.connection?.state === 'connected' && canal?.subscription?.subscribed) return;
            if (document.visibilityState === 'visible' && navigator.onLine) cambio();
        }, intervaloRespaldo) : null;
        return () => {
            clearTimeout(timeout);
            window.clearInterval(respaldo);
            listeners.delete(cambio);
            window.removeEventListener('online', reconectar);
            window.removeEventListener('focus', cambio);
            window.removeEventListener('pedido-bma.actualizado', cambio);
            connection?.unbind('connected', reconectar);
            if (listeners.size === 0 && canal) { canal.stopListening('.pedido.actualizado', emitir); echo?.leave('control-pedidos.operaciones'); canal = null; echo = null; }
        };
    }, [pedidoId, activo, intervaloRespaldo]);
    useEffect(() => {
        if (!estaPausado() && pendiente.current) { pendiente.current = false; actual.current.onActualizar?.(); }
    }, [pausado]);
}
