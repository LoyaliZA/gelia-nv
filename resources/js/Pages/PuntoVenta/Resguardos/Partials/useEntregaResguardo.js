import { useCallback, useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import {
    armarFormDataEntrega,
    claveIdempotenciaEntrega,
    dataUrlAFichero,
    limpiarClaveIdempotenciaEntrega,
    mensajeErrorEntrega,
    validarFormularioEntrega,
    esConflictoVersion,
} from './entregaResguardoUtils';

export default function useEntregaResguardo({ resguardoId, versionInicial, metodoValidacion }) {
    const [enviando, setEnviando] = useState(false);
    const [progreso, setProgreso] = useState(0);
    const [error, setError] = useState(null);
    const [exito, setExito] = useState(false);
    const versionRef = useRef(versionInicial);
    const envioBloqueado = useRef(false);
    const idempotencyRef = useRef(claveIdempotenciaEntrega(resguardoId));

    const enviar = useCallback(async ({
        relacion,
        nombreQuienRetira,
        observaciones,
        firmaDataUrl,
        evidencias,
    }) => {
        if (envioBloqueado.current || enviando) {
            return { duplicado: true };
        }

        const erroresLocales = validarFormularioEntrega({
            relacion,
            nombreQuienRetira,
            tieneFirma: Boolean(firmaDataUrl),
        });
        if (Object.keys(erroresLocales).length > 0) {
            setError(Object.values(erroresLocales)[0]);
            return { validacion: erroresLocales };
        }

        envioBloqueado.current = true;
        setEnviando(true);
        setProgreso(0);
        setError(null);

        const firma = dataUrlAFichero(firmaDataUrl);
        const form = armarFormDataEntrega({
            version: versionRef.current,
            idempotencyKey: idempotencyRef.current,
            relacion,
            nombreQuienRetira,
            metodoValidacion,
            observaciones,
            firma,
            evidencias,
        });

        try {
            const { data } = await axios.put(route('punto_venta.resguardos.entrega', resguardoId), form, {
                headers: { Accept: 'application/json' },
                onUploadProgress: (event) => {
                    if (!event.total) return;
                    setProgreso(Math.round((event.loaded / event.total) * 100));
                },
            });

            if (data?.resguardo?.version) {
                versionRef.current = data.resguardo.version;
            }

            setProgreso(100);
            setExito(true);
            limpiarClaveIdempotenciaEntrega(resguardoId);
            return { ok: true, resguardo: data?.resguardo };
        } catch (err) {
            const mensaje = mensajeErrorEntrega(err);
            setError(mensaje);

            if (err?.response?.status === 409) {
                envioBloqueado.current = true;
            } else if (!esConflictoVersion(err)) {
                envioBloqueado.current = false;
            }

            return {
                error: mensaje,
                conflictoVersion: esConflictoVersion(err),
                status: err?.response?.status,
            };
        } finally {
            setEnviando(false);
        }
    }, [enviando, metodoValidacion, resguardoId]);

    const irADetalle = useCallback(() => {
        router.visit(route('punto_venta.resguardos.show', resguardoId), {
            preserveState: false,
        });
    }, [resguardoId]);

    const irABandeja = useCallback(() => {
        router.visit(route('punto_venta.resguardos.index', { bandeja: 'en_custodia' }));
    }, []);

    const recargarFormulario = useCallback(() => {
        router.reload({ only: ['resguardo', 'puede_entregar', 'motivo_no_entregable', 'catalogos'] });
        envioBloqueado.current = false;
        setError(null);
    }, []);

    return {
        enviar,
        enviando,
        progreso,
        error,
        exito,
        setError,
        irADetalle,
        irABandeja,
        recargarFormulario,
        idempotencyKey: idempotencyRef.current,
    };
}
