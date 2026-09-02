import { useCallback, useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import {
    armarFormDataRecepcion,
    claveIdempotenciaRecepcion,
    limpiarClaveIdempotenciaRecepcion,
    mensajeErrorRecepcion,
    validarFormularioRecepcion,
    esConflictoVersion,
} from './recepcionFisicaUtils';

export default function useRecepcionFisica({ resguardoId, versionInicial }) {
    const [enviando, setEnviando] = useState(false);
    const [progreso, setProgreso] = useState(0);
    const [error, setError] = useState(null);
    const [exito, setExito] = useState(false);
    const versionRef = useRef(versionInicial);
    const envioBloqueado = useRef(false);
    const idempotencyRef = useRef(claveIdempotenciaRecepcion(resguardoId));

    const enviar = useCallback(async ({
        almacenId,
        bultos,
        evidencias,
        cantidadEsperada,
    }) => {
        if (envioBloqueado.current || enviando) {
            return { duplicado: true };
        }

        const erroresLocales = validarFormularioRecepcion({
            almacenId,
            bultos,
            cantidadEsperada,
        });
        if (Object.keys(erroresLocales).length > 0) {
            setError(Object.values(erroresLocales)[0]);
            return { validacion: erroresLocales };
        }

        envioBloqueado.current = true;
        setEnviando(true);
        setProgreso(0);
        setError(null);

        const form = armarFormDataRecepcion({
            version: versionRef.current,
            idempotencyKey: idempotencyRef.current,
            almacenId,
            bultos,
            evidencias,
        });

        try {
            await axios.put(route('punto_venta.resguardos.recepcion', resguardoId), form, {
                headers: { Accept: 'application/json' },
                onUploadProgress: (event) => {
                    if (!event.total) return;
                    setProgreso(Math.round((event.loaded / event.total) * 100));
                },
            });

            setProgreso(100);
            setExito(true);
            limpiarClaveIdempotenciaRecepcion(resguardoId);
            return { ok: true };
        } catch (err) {
            const mensaje = mensajeErrorRecepcion(err);
            setError(mensaje);

            if (err?.response?.status === 409) {
                envioBloqueado.current = true;
            } else if (!esConflictoVersion(err)) {
                envioBloqueado.current = false;
            }

            return { error: mensaje, conflictoVersion: esConflictoVersion(err), status: err?.response?.status };
        } finally {
            setEnviando(false);
        }
    }, [enviando, resguardoId]);

    const irADetalle = useCallback(() => {
        router.visit(route('punto_venta.resguardos.show', resguardoId));
    }, [resguardoId]);

    const irABandeja = useCallback(() => {
        router.visit(route('punto_venta.resguardos.index', { bandeja: 'en_custodia' }));
    }, []);

    const recargarFormulario = useCallback(() => {
        router.reload({ only: ['resguardo', 'puede_recibir', 'almacenes'] });
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
