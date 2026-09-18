import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import {
    claveIdempotenciaRecepcion,
    limpiarClaveIdempotenciaRecepcion,
    mensajeErrorRecepcion,
    esConflictoVersion,
} from './recepcionFisicaUtils';

export default function useRecepcionFisica({
    resguardoId,
    versionInicial,
    modoModal = false,
    onExitoModal,
    onRecargarFormulario,
}) {
    const [enviando, setEnviando] = useState(false);
    const [progreso, setProgreso] = useState(0);
    const [error, setError] = useState(null);
    const [exito, setExito] = useState(false);
    const versionRef = useRef(versionInicial);
    const envioBloqueado = useRef(false);
    const idempotencyRef = useRef(claveIdempotenciaRecepcion(resguardoId));

    useEffect(() => {
        if (versionInicial) {
            versionRef.current = versionInicial;
        }
    }, [versionInicial]);

    const renovarIdempotencia = useCallback(() => {
        limpiarClaveIdempotenciaRecepcion(resguardoId);
        idempotencyRef.current = claveIdempotenciaRecepcion(resguardoId);
    }, [resguardoId]);

    const enviar = useCallback(async () => {
        if (envioBloqueado.current || enviando) {
            return { duplicado: true };
        }

        envioBloqueado.current = true;
        setEnviando(true);
        setProgreso(0);
        setError(null);

        try {
            const { data } = await axios.put(
                route('punto_venta.resguardos.recepcion', resguardoId),
                {
                    version: versionRef.current,
                    idempotency_key: idempotencyRef.current,
                },
                { headers: { Accept: 'application/json' } },
            );

            setProgreso(100);
            renovarIdempotencia();
            envioBloqueado.current = false;

            const resguardoActualizado = data?.resguardo;
            if (resguardoActualizado?.version) {
                versionRef.current = resguardoActualizado.version;
            }

            setExito(true);
            if (modoModal) {
                onExitoModal?.({ ok: true, resguardo: resguardoActualizado, fase: 'exito' });
            }
            return { ok: true, resguardo: resguardoActualizado };
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
    }, [enviando, modoModal, onExitoModal, renovarIdempotencia, resguardoId]);

    const irADetalle = useCallback(() => {
        if (modoModal) {
            onExitoModal?.({ accion: 'detalle', resguardoId });
        }
        router.visit(route('punto_venta.resguardos.show', resguardoId));
    }, [modoModal, onExitoModal, resguardoId]);

    const irABandeja = useCallback(() => {
        if (modoModal) {
            onExitoModal?.({ accion: 'bandeja' });
        }
        router.visit(route('punto_venta.resguardos.index', { bandeja: 'por_recibir', paso: 'gerente' }));
    }, [modoModal, onExitoModal]);

    const recargarFormulario = useCallback(() => {
        if (modoModal && onRecargarFormulario) {
            onRecargarFormulario();
        } else {
            router.reload({ only: ['resguardo', 'admite_recepcion', 'motivo_no_recepcion'] });
        }
        envioBloqueado.current = false;
        setError(null);
        setExito(false);
    }, [modoModal, onRecargarFormulario]);

    const reiniciarFlujo = useCallback(() => {
        setExito(false);
        setError(null);
        envioBloqueado.current = false;
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
        reiniciarFlujo,
        idempotencyKey: idempotencyRef.current,
    };
}
