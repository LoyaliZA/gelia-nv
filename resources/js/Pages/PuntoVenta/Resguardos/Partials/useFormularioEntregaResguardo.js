import { useCallback, useState } from 'react';
import axios from 'axios';

function mensajeErrorCarga(err) {
    if (err?.response?.status === 403) {
        return 'No tienes permiso para entregar este resguardo.';
    }
    if (err?.response?.status === 404) {
        return 'El resguardo no está disponible en tu sucursal activa.';
    }

    return err?.response?.data?.message || 'No se pudo cargar el formulario de entrega.';
}

export default function useFormularioEntregaResguardo(resguardoId) {
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const [resguardo, setResguardo] = useState(null);
    const [catalogos, setCatalogos] = useState({});
    const [puedeEntregar, setPuedeEntregar] = useState(false);
    const [motivoNoEntregable, setMotivoNoEntregable] = useState(null);

    const cargar = useCallback(async () => {
        if (!resguardoId) return null;

        setCargando(true);
        setError(null);

        try {
            const { data } = await axios.get(route('punto_venta.resguardos.entrega.create', resguardoId), {
                headers: { Accept: 'application/json' },
            });

            setResguardo(data?.resguardo || null);
            setCatalogos(data?.catalogos || {});
            setPuedeEntregar(Boolean(data?.puede_entregar));
            setMotivoNoEntregable(data?.motivo_no_entregable || null);

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
        setCatalogos({});
        setPuedeEntregar(false);
        setMotivoNoEntregable(null);
        setError(null);
    }, []);

    return {
        cargar,
        reiniciar,
        cargando,
        error,
        resguardo,
        catalogos,
        puedeEntregar,
        motivoNoEntregable,
    };
}
