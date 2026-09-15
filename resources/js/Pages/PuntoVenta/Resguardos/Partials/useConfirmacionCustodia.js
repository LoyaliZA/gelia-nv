import { useCallback, useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';

export default function useConfirmacionCustodia({ resguardoId, versionInicial }) {
    const [enviando, setEnviando] = useState(false);
    const [error, setError] = useState(null);
    const [exito, setExito] = useState(false);
    const versionRef = useRef(versionInicial);
    const idempotencyRef = useRef(`pdv:custodia:${resguardoId}:${Date.now()}`);

    const enviar = useCallback(async ({ almacenId, folios, evidencias = [] }) => {
        if (!almacenId || !folios?.length) {
            setError('Selecciona almacén y al menos un bulto.');
            return;
        }

        setEnviando(true);
        setError(null);

        const form = new FormData();
        form.append('version', String(versionRef.current));
        form.append('idempotency_key', idempotencyRef.current);
        form.append('almacen_id', String(almacenId));
        folios.forEach((folio, indice) => form.append(`folios[${indice}]`, folio));
        evidencias.forEach((archivo, indice) => form.append(`evidencias[${indice}]`, archivo));

        try {
            const { data } = await axios.put(route('punto_venta.resguardos.custodia', resguardoId), form, {
                headers: { Accept: 'application/json' },
            });
            if (data?.resguardo?.version) {
                versionRef.current = data.resguardo.version;
            }
            setExito(true);
            idempotencyRef.current = `pdv:custodia:${resguardoId}:${Date.now()}`;
        } catch (err) {
            const mensaje = err?.response?.data?.message
                || Object.values(err?.response?.data?.errors || {})?.flat?.()?.[0]
                || 'No se pudo confirmar la custodia.';
            setError(mensaje);
            if (err?.response?.status === 422 && err?.response?.data?.errors?.version) {
                router.reload({ only: ['resguardo', 'admite_confirmacion_custodia'] });
            }
        } finally {
            setEnviando(false);
        }
    }, [resguardoId]);

    return { enviar, enviando, error, exito };
}
