import { useCallback, useState } from 'react';
import axios from 'axios';

function mensajeErrorCarga(err) {
    if (err?.response?.status === 403) {
        return 'No tienes permiso para confirmar custodia de este resguardo.';
    }
    if (err?.response?.status === 404) {
        return 'El resguardo no está disponible en tu sucursal activa.';
    }

    return err?.response?.data?.message || 'No se pudo cargar el formulario de custodia.';
}

export default function useFormularioCustodiaResguardo(resguardoId) {
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const [resguardo, setResguardo] = useState(null);
    const [almacenes, setAlmacenes] = useState([]);
    const [catalogos, setCatalogos] = useState({});
    const [admiteConfirmacion, setAdmiteConfirmacion] = useState(false);
    const [motivoNoConfirmacion, setMotivoNoConfirmacion] = useState(null);

    const cargar = useCallback(async () => {
        if (!resguardoId) return null;

        setCargando(true);
        setError(null);

        try {
            const { data } = await axios.get(route('punto_venta.resguardos.custodia.create', resguardoId), {
                headers: { Accept: 'application/json' },
            });

            setResguardo(data?.resguardo || null);
            setAlmacenes(data?.almacenes || []);
            setCatalogos(data?.catalogos || {});
            setAdmiteConfirmacion(Boolean(data?.admite_confirmacion_custodia));
            setMotivoNoConfirmacion(data?.motivo_no_confirmacion_custodia || null);

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
        setAdmiteConfirmacion(false);
        setMotivoNoConfirmacion(null);
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
        admiteConfirmacion,
        motivoNoConfirmacion,
    };
}
