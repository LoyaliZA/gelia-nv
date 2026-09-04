import { useCallback, useRef, useState } from 'react';
import axios from 'axios';
import {
    armarPayloadAltaTurno,
    claveIdempotenciaAltaTurno,
    mensajeErrorAltaTurno,
    renovarClaveIdempotenciaAltaTurno,
    validarFormularioAltaTurno,
} from './altaTurnoUtils';

export default function useAltaTurno({ sesionId = 'actual', onExito } = {}) {
    const [enviando, setEnviando] = useState(false);
    const [error, setError] = useState(null);
    const [turnoCreado, setTurnoCreado] = useState(null);
    const envioBloqueado = useRef(false);
    const idempotencyRef = useRef(claveIdempotenciaAltaTurno(sesionId));

    const renovarIdempotencia = useCallback(() => {
        idempotencyRef.current = renovarClaveIdempotenciaAltaTurno(sesionId);
    }, [sesionId]);

    const enviar = useCallback(async ({
        modo,
        cliente = null,
        nombreLlamado = '',
        prioridadAdultoMayor = false,
        prioridadDiscapacidad = false,
    }) => {
        if (envioBloqueado.current || enviando) {
            return { duplicado: true };
        }

        const erroresLocales = validarFormularioAltaTurno({ modo, cliente, nombreLlamado });
        if (Object.keys(erroresLocales).length > 0) {
            setError(Object.values(erroresLocales)[0]);
            return { validacion: erroresLocales };
        }

        envioBloqueado.current = true;
        setEnviando(true);
        setError(null);

        const payload = armarPayloadAltaTurno({
            idempotencyKey: idempotencyRef.current,
            clienteId: modo === 'cliente' ? cliente?.id : null,
            nombreLlamado: modo === 'visitante' ? String(nombreLlamado || '').trim() : null,
            prioridadAdultoMayor,
            prioridadDiscapacidad,
        });

        try {
            const { data } = await axios.post(route('punto_venta.turnos.store'), payload, {
                headers: { Accept: 'application/json' },
            });

            const turno = data?.turno ?? null;
            setTurnoCreado(turno);
            renovarIdempotencia();
            envioBloqueado.current = false;
            onExito?.(turno);

            return { ok: true, turno };
        } catch (err) {
            const mensaje = mensajeErrorAltaTurno(err);
            setError(mensaje);

            if (!err?.response) {
                envioBloqueado.current = false;
            }

            return { error: mensaje, status: err?.response?.status };
        } finally {
            setEnviando(false);
        }
    }, [enviando, renovarIdempotencia, onExito]);

    const reiniciar = useCallback(() => {
        setTurnoCreado(null);
        setError(null);
        envioBloqueado.current = false;
        renovarIdempotencia();
    }, [renovarIdempotencia]);

    return {
        enviar,
        enviando,
        error,
        turnoCreado,
        setError,
        reiniciar,
        idempotencyKey: idempotencyRef.current,
    };
}
