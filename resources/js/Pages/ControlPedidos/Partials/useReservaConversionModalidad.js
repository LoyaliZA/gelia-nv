import { useEffect, useRef, useState } from 'react';
import { uuidSolicitud } from '../../../utils/uuidSolicitud';
import {
    LATIDO_CONVERSION_MS,
    clienteHttpConversion,
    flagsConversionDe,
    mensajeErrorConversion,
    soltarReservaConversion,
    soltarReservaKeepalive,
} from './conversionModalidad';

export { LATIDO_CONVERSION_MS };

export default function useReservaConversionModalidad({
    activo = false,
    pedidoId = null,
    flagsIniciales = null,
    onActualizar = null,
}) {
    const instanciaRef = useRef(null);
    const reservaServidorRef = useRef(false);
    const confirmadaRef = useRef(false);
    const claveRef = useRef(null);
    const [flags, setFlags] = useState(flagsIniciales);
    const [reservaOk, setReservaOk] = useState(false);
    const [error, setError] = useState('');
    const [aviso, setAviso] = useState('');

    useEffect(() => {
        if (!activo || !pedidoId) return undefined;
        let vigente = true;
        const instancia = uuidSolicitud();
        instanciaRef.current = instancia;
        reservaServidorRef.current = false;
        confirmadaRef.current = false;
        claveRef.current = uuidSolicitud();
        setFlags(flagsIniciales);
        setError('');
        setAviso('');
        setReservaOk(false);

        const adquirir = async () => {
            try {
                await clienteHttpConversion().post(`/control-pedidos/${pedidoId}/conversion-modalidad/reserva`, { instancia_id: instancia }, { headers: { Accept: 'application/json' } });
                if (!vigente) {
                    soltarReservaConversion(pedidoId, instancia);
                    return;
                }
                reservaServidorRef.current = true;
                setReservaOk(true);
                onActualizar?.();
            } catch (e) {
                if (!vigente) return;
                if (e?.response?.status === 409) {
                    setError(mensajeErrorConversion(e, 'Hay un cambio de modalidad en curso'));
                    return;
                }
                if (e?.response?.status === 404) {
                    setAviso('La reserva aún no está disponible. El cierre de consulta no se bloqueará en otras sesiones hasta que el servicio esté publicado.');
                    setReservaOk(true);
                } else {
                    setError(mensajeErrorConversion(e, 'No se pudo reservar el cambio de modalidad.'));
                    return;
                }
            }
            try {
                const { data } = await clienteHttpConversion().get(`/control-pedidos/${pedidoId}/conversion-modalidad`, { headers: { Accept: 'application/json' } });
                if (vigente) setFlags(flagsConversionDe(data) || flagsIniciales || null);
            } catch {
                if (vigente && !flagsIniciales) {
                    setAviso((actual) => actual || 'No se pudieron leer las condiciones del cambio. Se muestran los destinos conocidos.');
                }
            }
        };
        adquirir();

        const latido = window.setInterval(() => {
            const actual = instanciaRef.current;
            if (!actual || !vigente || !reservaServidorRef.current) return;
            clienteHttpConversion().post(`/control-pedidos/${pedidoId}/conversion-modalidad/reserva/latido`, { instancia_id: actual }, { headers: { Accept: 'application/json' } })
                .catch((e) => {
                    if (!vigente) return;
                    if (e?.response?.status === 409) {
                        setError(mensajeErrorConversion(e, 'Hay un cambio de modalidad en curso'));
                        setReservaOk(false);
                    }
                });
        }, LATIDO_CONVERSION_MS);

        const alSalir = () => {
            if (confirmadaRef.current) return;
            soltarReservaConversion(pedidoId, instanciaRef.current);
            soltarReservaKeepalive(pedidoId, instanciaRef.current);
        };
        window.addEventListener('beforeunload', alSalir);
        return () => {
            vigente = false;
            window.clearInterval(latido);
            window.removeEventListener('beforeunload', alSalir);
            if (!confirmadaRef.current) soltarReservaConversion(pedidoId, instanciaRef.current);
            instanciaRef.current = null;
        };
    }, [activo, pedidoId]);

    return {
        flags,
        setFlags,
        reservaOk,
        error,
        setError,
        aviso,
        setAviso,
        instanciaId: () => instanciaRef.current,
        claveIdempotencia: () => claveRef.current,
        marcarConfirmada: () => {
            confirmadaRef.current = true;
            reservaServidorRef.current = false;
        },
    };
}
