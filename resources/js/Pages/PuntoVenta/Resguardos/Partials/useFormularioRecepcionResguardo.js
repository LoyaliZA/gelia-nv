import { useCallback, useState } from 'react';
import axios from 'axios';

function mensajeErrorCarga(err) {
    if (err?.response?.status === 403) {
        return 'No tienes permiso para recibir este resguardo.';
    }
    if (err?.response?.status === 404) {
        return 'El resguardo no está disponible en tu sucursal activa.';
    }

    return err?.response?.data?.message || 'No se pudo cargar el formulario de recepción.';
}

export default function useFormularioRecepcionResguardo(resguardoId) {
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const [resguardo, setResguardo] = useState(null);
    const [almacenes, setAlmacenes] = useState([]);
    const [catalogos, setCatalogos] = useState({});
    const [admiteRecepcion, setAdmiteRecepcion] = useState(false);
    const [motivoNoRecepcion, setMotivoNoRecepcion] = useState(null);

    const cargar = useCallback(async () => {
        if (!resguardoId) return null;

        setCargando(true);
        setError(null);

        try {
            const { data } = await axios.get(route('punto_venta.resguardos.recepcion.create', resguardoId), {
                headers: { Accept: 'application/json' },
            });

            setResguardo(data?.resguardo || null);
            setAlmacenes(data?.almacenes || []);
            setCatalogos(data?.catalogos || {});
            setAdmiteRecepcion(Boolean(data?.admite_recepcion));
            setMotivoNoRecepcion(data?.motivo_no_recepcion || null);

            return data;
        } catch (err) {
            const mensaje = mensajeErrorCarga(err);
            setError(mensaje);
            return { error: mensaje };
        } finally {
            setCargando(false);
        }
    }, [resguardoId]);

    const reiniciar = useCallback(() => {
        setResguardo(null);
        setAlmacenes([]);
        setCatalogos({});
        setAdmiteRecepcion(false);
        setMotivoNoRecepcion(null);
        setError(null);
    }, []);

    return {
        cargar,
        reiniciar,
        cargando,
        error,
        resguardo,
        almacenes,
        catalogos,
        admiteRecepcion,
        motivoNoRecepcion,
    };
}
