import { useCallback, useRef, useState } from 'react';
import axios from 'axios';
import {
    armarFormDataIncidencia,
    armarPayloadResolucion,
    claveIdempotenciaIncidencia,
    claveIdempotenciaResolucion,
    esConflictoVersionIncidencia,
    limpiarClaveIdempotenciaIncidencia,
    limpiarClaveIdempotenciaResolucion,
    mensajeErrorIncidencia,
    mensajeErrorResolucion,
    validarFormularioIncidencia,
    validarFormularioResolucion,
} from './incidenciasResguardoUtils';

export default function useIncidenciasResguardo({
    resguardoId,
    versionInicial,
    incidenciasIniciales = [],
    timelineInicial = [],
}) {
    const [incidencias, setIncidencias] = useState(incidenciasIniciales);
    const [timeline, setTimeline] = useState(timelineInicial);
    const [enviandoRegistro, setEnviandoRegistro] = useState(false);
    const [enviandoResolucion, setEnviandoResolucion] = useState(false);
    const [progreso, setProgreso] = useState(0);
    const [error, setError] = useState(null);
    const [errorResolucionId, setErrorResolucionId] = useState(null);
    const [conflictoVersion, setConflictoVersion] = useState(false);

    const versionRef = useRef(versionInicial);
    const registroBloqueado = useRef(false);
    const resolucionBloqueada = useRef({});
    const idempotenciaRegistroRef = useRef(claveIdempotenciaIncidencia(resguardoId));
    const idempotenciaResolucionRef = useRef({});

    const renovarIdempotenciaRegistro = useCallback(() => {
        limpiarClaveIdempotenciaIncidencia(resguardoId);
        idempotenciaRegistroRef.current = claveIdempotenciaIncidencia(resguardoId);
    }, [resguardoId]);

    const renovarIdempotenciaResolucion = useCallback((incidenciaId) => {
        limpiarClaveIdempotenciaResolucion(incidenciaId);
        idempotenciaResolucionRef.current[incidenciaId] = claveIdempotenciaResolucion(incidenciaId);
    }, []);

    const claveResolucion = useCallback((incidenciaId) => {
        if (!idempotenciaResolucionRef.current[incidenciaId]) {
            idempotenciaResolucionRef.current[incidenciaId] = claveIdempotenciaResolucion(incidenciaId);
        }
        return idempotenciaResolucionRef.current[incidenciaId];
    }, []);

    const aplicarDetalle = useCallback((payload) => {
        if (payload?.resguardo?.incidencias) {
            setIncidencias(payload.resguardo.incidencias);
        }
        if (payload?.resguardo?.version) {
            versionRef.current = payload.resguardo.version;
        }
        if (payload?.timeline) {
            setTimeline(payload.timeline);
        }
        setConflictoVersion(false);
    }, []);

    const recargarDetalle = useCallback(async () => {
        const { data } = await axios.get(route('punto_venta.resguardos.show', resguardoId), {
            headers: { Accept: 'application/json' },
        });
        aplicarDetalle(data);
        registroBloqueado.current = false;
        resolucionBloqueada.current = {};
        setError(null);
        setErrorResolucionId(null);
        return data;
    }, [aplicarDetalle, resguardoId]);

    const registrar = useCallback(async (datos) => {
        if (registroBloqueado.current || enviandoRegistro) {
            return { duplicado: true };
        }

        const erroresLocales = validarFormularioIncidencia(datos);
        if (Object.keys(erroresLocales).length > 0) {
            const mensaje = Object.values(erroresLocales)[0];
            setError(mensaje);
            return { validacion: erroresLocales };
        }

        registroBloqueado.current = true;
        setEnviandoRegistro(true);
        setProgreso(0);
        setError(null);
        setErrorResolucionId(null);

        const form = armarFormDataIncidencia({
            version: versionRef.current,
            idempotencyKey: idempotenciaRegistroRef.current,
            ...datos,
        });

        try {
            const { data } = await axios.post(
                route('punto_venta.resguardos.incidencias.store', resguardoId),
                form,
                {
                    headers: { Accept: 'application/json' },
                    onUploadProgress: (event) => {
                        if (!event.total) return;
                        setProgreso(Math.round((event.loaded / event.total) * 100));
                    },
                },
            );

            renovarIdempotenciaRegistro();
            registroBloqueado.current = false;

            if (data?.resguardo?.version) {
                versionRef.current = data.resguardo.version;
            }

            await recargarDetalle();
            return { ok: true, incidencia: data?.incidencia };
        } catch (err) {
            const mensaje = mensajeErrorIncidencia(err);
            setError(mensaje);

            if (esConflictoVersionIncidencia(err)) {
                setConflictoVersion(true);
                registroBloqueado.current = true;
            } else if (err?.response?.status !== 409) {
                registroBloqueado.current = false;
            }

            return {
                error: mensaje,
                conflictoVersion: esConflictoVersionIncidencia(err),
                status: err?.response?.status,
            };
        } finally {
            setEnviandoRegistro(false);
        }
    }, [enviandoRegistro, recargarDetalle, renovarIdempotenciaRegistro, resguardoId]);

    const resolver = useCallback(async (incidencia, motivoResolucion) => {
        const incidenciaId = incidencia.id;

        if (resolucionBloqueada.current[incidenciaId] || enviandoResolucion) {
            return { duplicado: true };
        }

        const erroresLocales = validarFormularioResolucion({ motivoResolucion });
        if (Object.keys(erroresLocales).length > 0) {
            const mensaje = Object.values(erroresLocales)[0];
            setErrorResolucionId(incidenciaId);
            setError(mensaje);
            return { validacion: erroresLocales };
        }

        resolucionBloqueada.current[incidenciaId] = true;
        setEnviandoResolucion(true);
        setError(null);
        setErrorResolucionId(incidenciaId);

        const payload = armarPayloadResolucion({
            version: versionRef.current,
            incidenciaVersion: incidencia.version,
            idempotencyKey: claveResolucion(incidenciaId),
            motivoResolucion,
        });

        try {
            const { data } = await axios.put(
                route('punto_venta.resguardos.incidencias.resolver', [resguardoId, incidenciaId]),
                payload,
                { headers: { Accept: 'application/json' } },
            );

            renovarIdempotenciaResolucion(incidenciaId);
            resolucionBloqueada.current[incidenciaId] = false;

            if (data?.resguardo?.version) {
                versionRef.current = data.resguardo.version;
            }

            await recargarDetalle();
            return { ok: true, incidencia: data?.incidencia };
        } catch (err) {
            const mensaje = mensajeErrorResolucion(err);
            setError(mensaje);
            setErrorResolucionId(incidenciaId);

            if (esConflictoVersionIncidencia(err)) {
                setConflictoVersion(true);
                resolucionBloqueada.current[incidenciaId] = true;
            } else if (err?.response?.status !== 409) {
                resolucionBloqueada.current[incidenciaId] = false;
            }

            return {
                error: mensaje,
                conflictoVersion: esConflictoVersionIncidencia(err),
                status: err?.response?.status,
            };
        } finally {
            setEnviandoResolucion(false);
        }
    }, [claveResolucion, enviandoResolucion, recargarDetalle, renovarIdempotenciaResolucion, resguardoId]);

    return {
        incidencias,
        timeline,
        enviandoRegistro,
        enviandoResolucion,
        progreso,
        error,
        errorResolucionId,
        conflictoVersion,
        registrar,
        resolver,
        recargarDetalle,
        setError,
    };
}
