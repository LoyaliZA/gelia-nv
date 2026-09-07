import { useCallback, useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import {
    canalesEfectivosPdv,
    dispatchPdvAlertasPrefsChanged,
    estadoWebPushPdv,
    guardarSilencioTerminalPdv,
    leerSilencioTerminalPdv,
    mergePdvAlertasPrefsUsuario,
    PDV_ALERTAS_PREFS_EVENT,
    resolvePdvAlertasPrefsUsuario,
} from '@/utils/pdvAlertasPrefs';

function urlPreferenciasAlertasPdv() {
    try {
        if (typeof route === 'function' && route().has?.('punto_venta.preferencias_alertas')) {
            return route('punto_venta.preferencias_alertas');
        }
    } catch {
        /* ziggy */
    }
    return '/punto-venta/preferencias-alertas';
}

export default function usePdvAlertasPrefs({ temaVisual = {}, webpush = {} } = {}) {
    const [prefsUsuario, setPrefsUsuario] = useState(() => resolvePdvAlertasPrefsUsuario(temaVisual));
    const [silencioTerminal, setSilencioTerminal] = useState(() => leerSilencioTerminalPdv());
    const [guardando, setGuardando] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        setPrefsUsuario(resolvePdvAlertasPrefsUsuario(temaVisual));
    }, [temaVisual]);

    useEffect(() => {
        const handler = (event) => {
            if (event?.detail?.pdv_alertas_prefs) {
                setPrefsUsuario(mergePdvAlertasPrefsUsuario(event.detail.pdv_alertas_prefs));
            }
            if (typeof event?.detail?.silencioTerminal === 'boolean') {
                setSilencioTerminal(event.detail.silencioTerminal);
            }
        };

        window.addEventListener(PDV_ALERTAS_PREFS_EVENT, handler);
        return () => window.removeEventListener(PDV_ALERTAS_PREFS_EVENT, handler);
    }, []);

    const canalesEfectivos = useMemo(
        () => canalesEfectivosPdv(prefsUsuario, silencioTerminal),
        [prefsUsuario, silencioTerminal],
    );

    const estadoPush = useMemo(
        () => estadoWebPushPdv(prefsUsuario, webpush),
        [prefsUsuario, webpush],
    );

    const persistirUsuario = useCallback(async (siguientePrefs) => {
        const normalizado = mergePdvAlertasPrefsUsuario(siguientePrefs);
        setPrefsUsuario(normalizado);
        setGuardando(true);
        setError(null);

        try {
            const { data } = await axios.put(urlPreferenciasAlertasPdv(), normalizado);
            const guardado = mergePdvAlertasPrefsUsuario(data?.pdv_alertas_prefs ?? normalizado);
            setPrefsUsuario(guardado);
            dispatchPdvAlertasPrefsChanged({ pdv_alertas_prefs: guardado });
            return guardado;
        } catch (err) {
            setPrefsUsuario(resolvePdvAlertasPrefsUsuario(temaVisual));
            setError(err?.response?.data?.message || 'No se pudieron guardar las preferencias.');
            throw err;
        } finally {
            setGuardando(false);
        }
    }, [temaVisual]);

    const actualizarCanal = useCallback(async (canal, valor) => {
        const siguiente = {
            ...prefsUsuario,
            canales: {
                ...prefsUsuario.canales,
                [canal]: Boolean(valor),
            },
        };
        return persistirUsuario(siguiente);
    }, [persistirUsuario, prefsUsuario]);

    const actualizarTono = useCallback(async (tonoId) => {
        return persistirUsuario({
            ...prefsUsuario,
            tono_id: tonoId,
        });
    }, [persistirUsuario, prefsUsuario]);

    const alternarSilencioTerminal = useCallback((valor = null) => {
        setSilencioTerminal((actual) => {
            const siguiente = typeof valor === 'boolean' ? valor : !actual;
            guardarSilencioTerminalPdv(siguiente);
            return siguiente;
        });
    }, []);

    return {
        prefsUsuario,
        silencioTerminal,
        canalesEfectivos,
        estadoPush,
        guardando,
        error,
        actualizarCanal,
        actualizarTono,
        alternarSilencioTerminal,
        persistirUsuario,
    };
}
