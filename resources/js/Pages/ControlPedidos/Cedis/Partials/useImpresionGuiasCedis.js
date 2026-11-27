import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import useActualizacionesPedidos from '../../Partials/useActualizacionesPedidos';

export default function useImpresionGuiasCedis(activa) {
    const [abierto, setAbierto] = useState(false);
    const [tab, setTab] = useState('pendientes');
    const [busqueda, setBusqueda] = useState('');
    const [folio, setFolio] = useState('');
    const [pendientes, setPendientes] = useState(null);
    const [listado, setListado] = useState(null);
    const [error, setError] = useState(false);
    const solicitud = useRef(null);

    const actualizar = useCallback(async () => {
        if (!activa) return false;
        solicitud.current?.abort();
        const controller = new AbortController();
        solicitud.current = controller;
        try {
            const { data } = await axios.get(route('control_pedidos.cedis.impresion_guias.index'), {
                params: abierto ? { tab, q: folio || undefined } : { resumen: 1 },
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            if (controller.signal.aborted) return false;
            setPendientes(data.pendientes);
            if (abierto) setListado({ tab, folio, pedidos: data.pedidos });
            setError(false);
            return true;
        } catch {
            if (!controller.signal.aborted) setError(true);
            return false;
        }
    }, [activa, abierto, tab, folio]);

    useEffect(() => {
        const timer = setTimeout(() => setFolio(busqueda.trim()), 180);
        return () => clearTimeout(timer);
    }, [busqueda]);

    useEffect(() => {
        actualizar();
        return () => solicitud.current?.abort();
    }, [actualizar]);

    useActualizacionesPedidos({
        activo: activa,
        onActualizar: actualizar,
        intervaloRespaldo: 15000,
        respaldoAunqueSuscrito: abierto,
    });

    useEffect(() => {
        if (!activa || !window.Echo) return;
        const evento = '.cedis.impresion-guias.actualizada';
        const canal = window.Echo.private('control-pedidos.operaciones');
        canal.listen(evento, actualizar);
        return () => canal.stopListening(evento, actualizar);
    }, [activa, actualizar]);

    return {
        abierto, setAbierto, tab, setTab, busqueda, setBusqueda, pendientes, error, actualizar,
        pedidos: listado?.tab === tab && listado.folio === busqueda.trim() ? listado.pedidos : null,
    };
}
