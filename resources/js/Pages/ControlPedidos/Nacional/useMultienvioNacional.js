import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';

const CABECERAS = { headers: { Accept: 'application/json' } };
const CAMPOS = ['folio_remision', 'total_mercancia', 'revision', 'envios', 'multienvio'];

/** 422 por campo del controlador de Multienvío -> { campo: mensaje }. Otros estados -> { general }. */
export function erroresMultienvio(fallo, reserva) {
    const estado = fallo?.response?.status;
    const datos = fallo?.response?.data;
    if (estado === 422 && datos?.errors) {
        const salida = {};
        Object.entries(datos.errors).forEach(([campo, mensajes]) => {
            const clave = CAMPOS.includes(campo) ? campo : (campo.split('.').pop() || 'multienvio');
            const texto = Array.isArray(mensajes) ? mensajes[0] : mensajes;
            if (texto && !salida[clave]) salida[clave] = String(texto);
        });
        if (Object.keys(salida).length) return salida;
    }
    if (estado === 403) return { general: 'Solo la vendedora del pedido puede configurar el multienvío.' };

    return { general: (estado && estado < 500 && datos?.message) || reserva };
}

/**
 * Lectura y mutaciones del Multienvío de un pedido nacional. Siempre reenvía la `revision` vigente.
 * `clave` cambia cuando el pedido cambia por fuera (refresco en vivo, cierre de consulta): vuelve a leer.
 */
export default function useMultienvioNacional({ pedidoId, habilitado, clave = '', onConfirmado }) {
    const [datos, setDatos] = useState(null);
    const [cargando, setCargando] = useState(false);
    const [errores, setErrores] = useState({});
    const [ocupado, setOcupado] = useState('');
    const [avisoRevision, setAvisoRevision] = useState('');
    const vivo = useRef(true);
    const lectura = useRef(0);
    const confirmando = useRef(false);
    const datosRef = useRef(null);
    datosRef.current = datos;
    const alConfirmar = useRef(onConfirmado);
    alConfirmar.current = onConfirmado;

    useEffect(() => () => { vivo.current = false; }, []);

    const recargar = useCallback(async () => {
        if (!pedidoId) return null;
        const turno = ++lectura.current;
        setCargando(true);
        try {
            const respuesta = await axios.get(`/control-pedidos/${pedidoId}/multienvio`, CABECERAS);
            const leido = respuesta?.data ?? null;
            if (vivo.current && turno === lectura.current && leido) {
                setDatos(leido);
                setErrores((previos) => (previos.carga ? {} : previos));
            }

            return leido;
        } catch (fallo) {
            if (vivo.current && turno === lectura.current) {
                setErrores({ carga: erroresMultienvio(fallo, 'No se pudo revisar el multienvío.').general || 'No se pudo revisar el multienvío.' });
            }

            return null;
        } finally {
            if (vivo.current && turno === lectura.current) setCargando(false);
        }
    }, [pedidoId]);

    useEffect(() => {
        if (habilitado) recargar();
    }, [habilitado, clave, recargar]);

    const adoptar = (leido) => {
        // Una lectura pendiente más antigua no debe pisar la respuesta de una mutación.
        lectura.current += 1;
        setCargando(false);
        if (leido) setDatos(leido);

        return leido;
    };

    const fallar = async (fallo, reserva) => {
        const lista = erroresMultienvio(fallo, reserva);
        if (lista.revision) {
            setAvisoRevision('Las cajas de la consulta cambiaron desde la última lectura. Revisa la información actualizada antes de continuar; lo que capturaste se conserva.');
            await recargar();
            delete lista.revision;
        }
        setErrores(lista);

        return null;
    };

    const decidir = async (decision) => {
        if (ocupado || !datosRef.current) return null;
        setOcupado(decision);
        setErrores({});
        try {
            const { data } = await axios.post(`/control-pedidos/${pedidoId}/multienvio/decision`, {
                decision,
                revision: datosRef.current.evaluacion?.revision,
            }, CABECERAS);
            setAvisoRevision('');

            return adoptar(data);
        } catch (fallo) {
            return fallar(fallo, 'No se registró la decisión. Intenta de nuevo.');
        } finally {
            if (vivo.current) setOcupado('');
        }
    };

    /** Guarda el borrador. No marca `ocupado`: la captura sigue editable mientras se guarda. */
    const guardarBorrador = async (envios) => {
        try {
            const { data } = await axios.put(`/control-pedidos/${pedidoId}/multienvio/borrador`, { envios }, CABECERAS);
            setErrores((previos) => (previos.folio_remision || previos.envios || previos.general ? {} : previos));

            return { ok: true, datos: adoptar(data) };
        } catch (fallo) {
            await fallar(fallo, 'No se guardó el borrador. Intenta de nuevo.');

            return { ok: false };
        }
    };

    const confirmar = async (antes) => {
        if (confirmando.current || !datosRef.current) return null;
        confirmando.current = true;
        setOcupado('confirmar');
        setErrores({});
        try {
            if (antes) {
                const previo = await antes();
                if (previo === false) return null;
            }
            const { data } = await axios.post(`/control-pedidos/${pedidoId}/multienvio/confirmar`, {
                revision: datosRef.current.evaluacion?.revision,
            }, CABECERAS);
            setAvisoRevision('');
            adoptar(data);
            if (['activo', 'regularizacion_pendiente'].includes(data?.grupo?.estado)) await alConfirmar.current?.(data);

            return data;
        } catch (fallo) {
            return fallar(fallo, 'No se confirmó el multienvío. Intenta de nuevo.');
        } finally {
            confirmando.current = false;
            if (vivo.current) setOcupado('');
        }
    };

    return {
        datos, cargando, cargado: datos !== null, errores, ocupado, avisoRevision,
        recargar, decidir, guardarBorrador, confirmar,
        limpiarError: (campo) => setErrores((previos) => {
            if (!previos[campo]) return previos;
            const { [campo]: _omitido, ...resto } = previos;

            return resto;
        }),
        descartarAviso: () => setAvisoRevision(''),
    };
}
