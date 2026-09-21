import { useCallback, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';

const RELOAD_ONLY_RESGUARDOS = ['sucursal_activa', 'resguardos', 'metricas', 'filtros', 'bandeja'];

export default function useEstablecerSucursalActivaPdv({ reloadOnly = null } = {}) {
    const [cambiando, setCambiando] = useState(false);
    const [error, setError] = useState(null);

    const establecer = useCallback(async (sucursalId, { activaId = '' } = {}) => {
        const id = sucursalId != null ? String(sucursalId) : '';
        if (!id || id === String(activaId)) return false;

        setCambiando(true);
        setError(null);

        try {
            await axios.put(route('punto_venta.sucursal_activa.establecer'), {
                sucursal_id: Number(id),
            });

            if (Array.isArray(reloadOnly)) {
                router.reload({ only: reloadOnly });
            } else {
                router.reload();
            }

            return true;
        } catch (err) {
            const status = err?.response?.status;
            if (status === 403 || status === 422) {
                setError('No se pudo cambiar la sucursal activa.');
            } else {
                setError('Ocurrió un error al cambiar la sucursal. Intenta de nuevo.');
            }
            return false;
        } finally {
            setCambiando(false);
        }
    }, [reloadOnly]);

    return {
        cambiando,
        error,
        establecer,
        limpiarError: () => setError(null),
    };
}

export { RELOAD_ONLY_RESGUARDOS };
