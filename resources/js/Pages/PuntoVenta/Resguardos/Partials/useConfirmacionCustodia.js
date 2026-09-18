import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';

export default function useConfirmacionCustodia({
    resguardoId,
    versionInicial,
    modoModal = false,
    onExitoModal,
}) {
    const [enviando, setEnviando] = useState(false);
    const [error, setError] = useState(null);
    const [exito, setExito] = useState(false);
    const versionRef = useRef(versionInicial);
    const idempotencyRef = useRef(`pdv:custodia:${resguardoId}:${Date.now()}`);

    useEffect(() => {
        if (versionInicial !== undefined && versionInicial !== null) {
            versionRef.current = versionInicial;
        }
    }, [versionInicial]);

    const enviar = useCallback(async ({ almacenId, bultos, evidencias = [] }) => {
        if (!almacenId || !bultos?.length) {
            setError('Selecciona almacén y al menos un bulto.');
            return;
        }

        const version = Number(versionRef.current);
        if (!Number.isInteger(version) || version < 1) {
            setError('No se pudo leer la versión del resguardo. Cierra y vuelve a abrir el formulario.');
            return;
        }

        setEnviando(true);
        setError(null);

        const form = new FormData();
        form.append('version', String(version));
        form.append('idempotency_key', idempotencyRef.current);
        form.append('almacen_id', String(almacenId));
        bultos.forEach((bulto, indice) => {
            form.append(`bultos[${indice}][folio]`, String(bulto.folio).trim());
            form.append(`bultos[${indice}][tipo]`, bulto.tipo);
            form.append(`bultos[${indice}][condicion]`, bulto.condicion);
            form.append(`bultos[${indice}][piezas]`, String(bulto.piezas));
        });
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
            if (modoModal) {
                onExitoModal?.({ ok: true, resguardo: data?.resguardo, fase: 'exito' });
            }
        } catch (err) {
            const mensaje = err?.response?.data?.message
                || Object.values(err?.response?.data?.errors || {})?.flat?.()?.[0]
                || 'No se pudo confirmar la custodia.';
            setError(mensaje);
            if (err?.response?.status === 422 && err?.response?.data?.errors?.version) {
                router.reload({ only: ['resguardo', 'admite_confirmacion_custodia', 'almacenes', 'catalogos'] });
            }
        } finally {
            setEnviando(false);
        }
    }, [resguardoId, modoModal, onExitoModal]);

    const reiniciarFlujo = useCallback(() => {
        setEnviando(false);
        setError(null);
        setExito(false);
    }, []);

    return { enviar, enviando, error, exito, reiniciarFlujo };
}
