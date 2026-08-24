import { useCallback, useEffect, useRef, useState } from 'react';
import { AUTOSAVE_LOCAL_MS } from './constantes';

/**
 * Controlador de formulario + autoguardado versionado.
 * Debounce BD viene de formulario_config; local es constante documentada.
 */
export default function useFormularioPedidoBma({
    abierto,
    pedido = null,
    formularioConfig = {},
    onPersistir,
    fingerprint,
}) {
    const debounceBd = Number(formularioConfig.autosave_debounce_ms) || 15000;
    const maxReintentos = Number(formularioConfig.max_reintentos_autosave) || 3;

    const [estadoGuardado, setEstadoGuardado] = useState(null);
    const [progreso, setProgreso] = useState(pedido?.progreso || null);
    const [updatedAt, setUpdatedAt] = useState(pedido?.updated_at || null);
    const [etapaSeleccionada, setEtapaSeleccionada] = useState(pedido?.progreso?.etapa_actual || 'solicitud');
    const reintentosRef = useRef(0);
    const guardandoRef = useRef(false);
    const ultimoFpRef = useRef('');

    useEffect(() => {
        if (!abierto) return;
        setProgreso(pedido?.progreso || null);
        setUpdatedAt(pedido?.updated_at || null);
        if (pedido?.progreso?.etapa_actual) {
            setEtapaSeleccionada(pedido.progreso.etapa_actual);
        }
    }, [abierto, pedido?.id, pedido?.progreso, pedido?.updated_at]);

    const seleccionarEtapa = useCallback((codigo) => {
        const etapa = (progreso?.etapas || []).find((e) => e.codigo === codigo);
        if (!etapa?.editable) return;
        setEtapaSeleccionada(codigo);
    }, [progreso]);

    const persistirConReintento = useCallback(async (payloadBuilder) => {
        if (guardandoRef.current || typeof onPersistir !== 'function') return null;
        guardandoRef.current = true;
        setEstadoGuardado('guardando');
        try {
            const payload = typeof payloadBuilder === 'function' ? payloadBuilder() : payloadBuilder;
            if (updatedAt) {
                payload.updated_at_esperado = updatedAt;
            }
            const res = await onPersistir(payload);
            reintentosRef.current = 0;
            if (res?.updated_at) setUpdatedAt(res.updated_at);
            if (res?.progreso) setProgreso(res.progreso);
            setEstadoGuardado('guardado');
            return res;
        } catch (err) {
            if (err?.response?.status === 409) {
                setEstadoGuardado('error');
                if (err.response.data?.progreso) setProgreso(err.response.data.progreso);
                if (err.response.data?.updated_at) setUpdatedAt(err.response.data.updated_at);
                throw err;
            }
            if (reintentosRef.current < maxReintentos) {
                reintentosRef.current += 1;
                const wait = 500 * (2 ** (reintentosRef.current - 1));
                await new Promise((r) => setTimeout(r, wait));
                guardandoRef.current = false;
                return persistirConReintento(payloadBuilder);
            }
            setEstadoGuardado('error');
            throw err;
        } finally {
            guardandoRef.current = false;
        }
    }, [onPersistir, updatedAt, maxReintentos]);

    return {
        estadoGuardado,
        setEstadoGuardado,
        progreso,
        setProgreso,
        updatedAt,
        setUpdatedAt,
        etapaSeleccionada,
        setEtapaSeleccionada,
        seleccionarEtapa,
        persistirConReintento,
        debounceBd,
        autosaveLocalMs: AUTOSAVE_LOCAL_MS,
        ultimoFpRef,
    };
}
