import { useCallback, useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import {
    claveIdempotenciaReponerVencido,
    esConflictoVersionReponer,
    limpiarClaveIdempotenciaReponerVencido,
    mensajeErrorReponerVencido,
    validarFormularioReponerVencido,
} from './reponerVencidoResguardoUtils';

export default function useReponerVencidoResguardo({ resguardoId, versionInicial, onExito }) {
    const [enviando, setEnviando] = useState(false);
    const [error, setError] = useState(null);
    const [conflictoVersion, setConflictoVersion] = useState(false);
    const [ultimoEvento, setUltimoEvento] = useState(null);

    const versionRef = useRef(versionInicial);
    const bloqueado = useRef(false);
    const idempotenciaRef = useRef(claveIdempotenciaReponerVencido(resguardoId));

    const renovarIdempotencia = useCallback(() => {
        limpiarClaveIdempotenciaReponerVencido(resguardoId);
        idempotenciaRef.current = claveIdempotenciaReponerVencido(resguardoId);
    }, [resguardoId]);

    const recargar = useCallback(() => {
        if (onExito) {
            onExito();
            return;
        }
        router.reload({ only: ['resguardos', 'metricas', 'filtros'] });
        setConflictoVersion(false);
        setError(null);
        bloqueado.current = false;
    }, [onExito]);

    const recargarDetalle = useCallback(() => {
        router.reload({ only: ['resguardo', 'timeline', 'permisos'] });
        setConflictoVersion(false);
        setError(null);
        bloqueado.current = false;
    }, []);

    const reponerVencido = useCallback(async ({ motivo }) => {
        if (bloqueado.current || enviando) {
            return { duplicado: true };
        }

        const erroresLocales = validarFormularioReponerVencido({ motivo });
        if (Object.keys(erroresLocales).length > 0) {
            const mensaje = Object.values(erroresLocales)[0];
            setError(mensaje);
            return { validacion: erroresLocales };
        }

        bloqueado.current = true;
        setEnviando(true);
        setError(null);
        setUltimoEvento(null);

        try {
            const { data } = await axios.put(
                route('punto_venta.resguardos.reponer_vencido', resguardoId),
                {
                    version: versionRef.current,
                    idempotency_key: idempotenciaRef.current,
                    motivo: String(motivo).trim(),
                },
                { headers: { Accept: 'application/json' } },
            );

            renovarIdempotencia();
            bloqueado.current = false;

            if (data?.resguardo?.version) {
                versionRef.current = data.resguardo.version;
            }

            setUltimoEvento({
                evento: data?.evento,
                resguardo: data?.resguardo,
            });

            if (onExito) {
                onExito(data);
            } else {
                recargarDetalle();
            }

            return { ok: true, data };
        } catch (err) {
            const mensaje = mensajeErrorReponerVencido(err);
            setError(mensaje);

            if (esConflictoVersionReponer(err)) {
                setConflictoVersion(true);
                bloqueado.current = true;
            } else if (err?.response?.status !== 409) {
                bloqueado.current = false;
            }

            return {
                error: mensaje,
                conflictoVersion: esConflictoVersionReponer(err),
                status: err?.response?.status,
            };
        } finally {
            setEnviando(false);
        }
    }, [enviando, onExito, recargarDetalle, renovarIdempotencia, resguardoId]);

    return {
        enviando,
        error,
        conflictoVersion,
        ultimoEvento,
        reponerVencido,
        recargar,
        recargarDetalle,
        setError,
        setUltimoEvento,
    };
}
