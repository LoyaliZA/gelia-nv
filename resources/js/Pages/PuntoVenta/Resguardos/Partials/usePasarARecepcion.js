import { useCallback, useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';

export default function usePasarARecepcion({ resguardoId, versionInicial, onExito }) {
    const [enviando, setEnviando] = useState(false);
    const [error, setError] = useState(null);
    const versionRef = useRef(versionInicial);
    const idempotencyRef = useRef(`pdv:pasar:${resguardoId}:${Date.now()}`);

    const enviar = useCallback(async () => {
        setEnviando(true);
        setError(null);

        try {
            const { data } = await axios.put(
                route('punto_venta.resguardos.pasar_recepcion', resguardoId),
                {
                    version: versionRef.current,
                    idempotency_key: idempotencyRef.current,
                },
                { headers: { Accept: 'application/json' } },
            );

            if (data?.resguardo?.version) {
                versionRef.current = data.resguardo.version;
            }

            idempotencyRef.current = `pdv:pasar:${resguardoId}:${Date.now()}`;
            onExito?.(data?.resguardo);
            router.reload({ only: ['resguardos', 'resguardo', 'filtros', 'metricas'] });
            return { ok: true, resguardo: data?.resguardo };
        } catch (err) {
            const mensaje = err?.response?.data?.message
                || Object.values(err?.response?.data?.errors || {})?.flat?.()?.[0]
                || 'No se pudo pasar el resguardo a recepción.';
            setError(mensaje);
            return { error: mensaje };
        } finally {
            setEnviando(false);
        }
    }, [onExito, resguardoId]);

    return { enviar, enviando, error };
}
