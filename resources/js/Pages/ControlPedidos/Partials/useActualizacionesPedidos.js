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

export default function useActualizacionesPedidos({ onActualizar, pedidoId = null, pausado = false, onPendiente = null, activo = true, intervaloRespaldo = 0, respaldoAunqueSuscrito = false }) {
    const actual = useRef({ onActualizar, pausado, onPendiente });
    actual.current = { onActualizar, pausado, onPendiente };
    const estaPausado = () => typeof actual.current.pausado === 'function' ? actual.current.pausado() : actual.current.pausado;
    const reanudar = useRef(null);
    const pausadoAhora = estaPausado();
    useEffect(() => {
        if (!activo) return undefined;
        let timeout;
        let cerrado = false;
        let pendiente = false;
        let enVuelo = false;
        let ultimoExito = 0;
        const eventoRespaldo = {};
        const aplazar = () => { pendiente = true; actual.current.onPendiente?.(); };
        const aplicar = (evento) => {
            if (cerrado) return;
            if (estaPausado()) { aplazar(); return; }
            if (enVuelo) { pendiente = true; return; }
            pendiente = false;
            enVuelo = true;
            // Una lectura pendiente usa el mismo camino que eventos y respaldo.
            Promise.resolve().then(() => actual.current.onActualizar?.(evento)).then((resultado) => {
                // El propio respaldo no debe suprimir el siguiente ciclo de 15 s.
                if (resultado !== false && evento !== eventoRespaldo) ultimoExito = Date.now();
            }).catch(() => {
                // El consumidor muestra el error; el respaldo vuelve a intentarlo.
            }).finally(() => {
                enVuelo = false;
                if (!cerrado && pendiente && !estaPausado()) cambio();
            });
        };
        const cambio = (evento = {}) => {
            evento = evento.detail || evento;
            if (pedidoId && evento.pedido_id && Number(evento.pedido_id) !== Number(pedidoId)) return;
            if (estaPausado()) { aplazar(); return; }
            if (enVuelo) { pendiente = true; return; }
            clearTimeout(timeout);
            timeout = setTimeout(() => aplicar(evento), 180);
        };
        reanudar.current = () => { if (pendiente && !estaPausado()) aplicar(); };
        const reconectar = () => { conectar(); cambio(); };
        const visible = () => { if (document.visibilityState === 'visible') cambio(); };
        listeners.add(cambio);
        conectar();
        window.addEventListener('online', reconectar);
        window.addEventListener('focus', cambio);
        window.addEventListener('pedido-bma.actualizado', cambio);
        document.addEventListener('visibilitychange', visible);
        const connection = echo?.connector?.pusher?.connection;
        connection?.bind('connected', reconectar);
        const respaldo = intervaloRespaldo > 0 ? window.setInterval(() => {
            const echoSano = echo?.connector?.pusher?.connection?.state === 'connected' && canal?.subscription?.subscribed;
            if (echoSano && !respaldoAunqueSuscrito) return;
            if (respaldoAunqueSuscrito && Date.now() - ultimoExito < intervaloRespaldo) return;
            if (document.visibilityState === 'visible' && navigator.onLine && !enVuelo) cambio(eventoRespaldo);
        }, intervaloRespaldo) : null;
        return () => {
            cerrado = true;
            reanudar.current = null;
            clearTimeout(timeout);
            window.clearInterval(respaldo);
            listeners.delete(cambio);
            window.removeEventListener('online', reconectar);
            window.removeEventListener('focus', cambio);
            window.removeEventListener('pedido-bma.actualizado', cambio);
            document.removeEventListener('visibilitychange', visible);
            connection?.unbind('connected', reconectar);
            if (listeners.size === 0 && canal) { canal.stopListening('.pedido.actualizado', emitir); echo?.leave('control-pedidos.operaciones'); canal = null; echo = null; }
        };
    }, [pedidoId, activo, intervaloRespaldo, respaldoAunqueSuscrito]);
    useEffect(() => {
        if (!pausadoAhora) reanudar.current?.();
    }, [pausadoAhora]);
}
