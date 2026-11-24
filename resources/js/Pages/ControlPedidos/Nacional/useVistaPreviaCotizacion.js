import { useEffect, useRef, useState } from 'react';
import axios from 'axios';

const ESPERA_MS = 400;

/**
 * Pide al servidor el desglose de la cotización con el seguro calculado igual que al guardar.
 * `lineas`: [{ uuid_operativo, costo_envio }]. Sin un flete positivo por caja no consulta.
 */
export default function useVistaPreviaCotizacion({ pedidoId, paqueteriaId, zonaId, lineas, aplicaSeguro, activo }) {
    const [datos, setDatos] = useState(null);
    const [cargando, setCargando] = useState(false);
    const turno = useRef(0);
    const clave = JSON.stringify([pedidoId, paqueteriaId, zonaId, lineas, aplicaSeguro, activo]);

    useEffect(() => {
        const actual = ++turno.current;
        const completo = activo && lineas.length > 0 && lineas.every((linea) => linea.costo_envio > 0);
        if (!completo) {
            setDatos(null);
            setCargando(false);
            return undefined;
        }
        setCargando(true);
        const espera = setTimeout(() => {
            axios.post(`/control-pedidos/${pedidoId}/cotizacion-nacional/vista-previa`, {
                catalogo_paqueteria_id: Number(paqueteriaId),
                catalogo_zona_id: zonaId ? Number(zonaId) : null,
                aplica_seguro: Boolean(aplicaSeguro),
                lineas,
            }, { headers: { Accept: 'application/json' } })
                .then(({ data }) => { if (turno.current === actual) setDatos(data); })
                .catch(() => { if (turno.current === actual) setDatos(null); })
                .finally(() => { if (turno.current === actual) setCargando(false); });
        }, ESPERA_MS);
        return () => clearTimeout(espera);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [clave]);

    return { datos, cargando };
}
