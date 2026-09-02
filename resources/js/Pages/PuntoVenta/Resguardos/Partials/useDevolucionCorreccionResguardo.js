import { useCallback, useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import {
    armarFormDataCorreccion,
    armarFormDataDevolucion,
    claveIdempotenciaCorreccion,
    claveIdempotenciaDevolucion,
    esConflictoVersionExcepcion,
    limpiarClaveIdempotenciaCorreccion,
    limpiarClaveIdempotenciaDevolucion,
    mensajeErrorCorreccion,
    mensajeErrorDevolucion,
    validarFormularioCorreccion,
    validarFormularioDevolucion,
} from './devolucionCorreccionResguardoUtils';

export default function useDevolucionCorreccionResguardo({ resguardoId, versionInicial }) {
    const [enviandoDevolucion, setEnviandoDevolucion] = useState(false);
    const [enviandoCorreccion, setEnviandoCorreccion] = useState(false);
    const [progreso, setProgreso] = useState(0);
    const [error, setError] = useState(null);
    const [conflictoVersion, setConflictoVersion] = useState(false);
    const [ultimoEvento, setUltimoEvento] = useState(null);

    const versionRef = useRef(versionInicial);
    const devolucionBloqueada = useRef(false);
    const correccionBloqueada = useRef(false);
    const idempotenciaDevolucionRef = useRef(claveIdempotenciaDevolucion(resguardoId));
    const idempotenciaCorreccionRef = useRef(claveIdempotenciaCorreccion(resguardoId));

    const renovarIdempotenciaDevolucion = useCallback(() => {
        limpiarClaveIdempotenciaDevolucion(resguardoId);
        idempotenciaDevolucionRef.current = claveIdempotenciaDevolucion(resguardoId);
    }, [resguardoId]);

    const renovarIdempotenciaCorreccion = useCallback(() => {
        limpiarClaveIdempotenciaCorreccion(resguardoId);
        idempotenciaCorreccionRef.current = claveIdempotenciaCorreccion(resguardoId);
    }, [resguardoId]);

    const recargarDetalle = useCallback(() => {
        router.reload({ only: ['resguardo', 'timeline', 'permisos'] });
        setConflictoVersion(false);
        setError(null);
        devolucionBloqueada.current = false;
        correccionBloqueada.current = false;
    }, []);

    const confirmarDevolucion = useCallback(async (datos) => {
        if (devolucionBloqueada.current || enviandoDevolucion) {
            return { duplicado: true };
        }

        const erroresLocales = validarFormularioDevolucion(datos);
        if (Object.keys(erroresLocales).length > 0) {
            const mensaje = Object.values(erroresLocales)[0];
            setError(mensaje);
            return { validacion: erroresLocales };
        }

        devolucionBloqueada.current = true;
        setEnviandoDevolucion(true);
        setProgreso(0);
        setError(null);
        setUltimoEvento(null);

        const form = armarFormDataDevolucion({
            version: versionRef.current,
            idempotencyKey: idempotenciaDevolucionRef.current,
            ...datos,
        });

        try {
            const { data } = await axios.put(
                route('punto_venta.resguardos.devolucion', resguardoId),
                form,
                {
                    headers: { Accept: 'application/json' },
                    onUploadProgress: (event) => {
                        if (!event.total) return;
                        setProgreso(Math.round((event.loaded / event.total) * 100));
                    },
                },
            );

            renovarIdempotenciaDevolucion();
            devolucionBloqueada.current = false;

            if (data?.resguardo?.version) {
                versionRef.current = data.resguardo.version;
            }

            setUltimoEvento({
                tipo: 'devolucion',
                evento: data?.evento,
                resguardo: data?.resguardo,
            });

            recargarDetalle();
            return { ok: true, data };
        } catch (err) {
            const mensaje = mensajeErrorDevolucion(err);
            setError(mensaje);

            if (esConflictoVersionExcepcion(err)) {
                setConflictoVersion(true);
                devolucionBloqueada.current = true;
            } else if (err?.response?.status !== 409) {
                devolucionBloqueada.current = false;
            }

            return {
                error: mensaje,
                conflictoVersion: esConflictoVersionExcepcion(err),
                status: err?.response?.status,
            };
        } finally {
            setEnviandoDevolucion(false);
        }
    }, [enviandoDevolucion, recargarDetalle, renovarIdempotenciaDevolucion, resguardoId]);

    const aplicarCorreccion = useCallback(async (datos) => {
        if (correccionBloqueada.current || enviandoCorreccion) {
            return { duplicado: true };
        }

        const erroresLocales = validarFormularioCorreccion(datos);
        if (Object.keys(erroresLocales).length > 0) {
            const mensaje = Object.values(erroresLocales)[0];
            setError(mensaje);
            return { validacion: erroresLocales };
        }

        correccionBloqueada.current = true;
        setEnviandoCorreccion(true);
        setProgreso(0);
        setError(null);
        setUltimoEvento(null);

        const form = armarFormDataCorreccion({
            version: versionRef.current,
            idempotencyKey: idempotenciaCorreccionRef.current,
            ...datos,
        });

        try {
            const { data } = await axios.put(
                route('punto_venta.resguardos.correccion', resguardoId),
                form,
                {
                    headers: { Accept: 'application/json' },
                    onUploadProgress: (event) => {
                        if (!event.total) return;
                        setProgreso(Math.round((event.loaded / event.total) * 100));
                    },
                },
            );

            renovarIdempotenciaCorreccion();
            correccionBloqueada.current = false;

            if (data?.resguardo?.version) {
                versionRef.current = data.resguardo.version;
            }

            setUltimoEvento({
                tipo: 'correccion',
                evento: data?.evento,
                resguardo: data?.resguardo,
            });

            recargarDetalle();
            return { ok: true, data };
        } catch (err) {
            const mensaje = mensajeErrorCorreccion(err);
            setError(mensaje);

            if (esConflictoVersionExcepcion(err)) {
                setConflictoVersion(true);
                correccionBloqueada.current = true;
            } else if (err?.response?.status !== 409) {
                correccionBloqueada.current = false;
            }

            return {
                error: mensaje,
                conflictoVersion: esConflictoVersionExcepcion(err),
                status: err?.response?.status,
            };
        } finally {
            setEnviandoCorreccion(false);
        }
    }, [enviandoCorreccion, recargarDetalle, renovarIdempotenciaCorreccion, resguardoId]);

    return {
        enviandoDevolucion,
        enviandoCorreccion,
        progreso,
        error,
        conflictoVersion,
        ultimoEvento,
        confirmarDevolucion,
        aplicarCorreccion,
        recargarDetalle,
        setError,
        setUltimoEvento,
    };
}
