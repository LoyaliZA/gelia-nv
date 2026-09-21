import { useEffect, useRef } from 'react';
import { emitirToastGelia } from '../../../../utils/geliaToast';
import { avisoSucursalOperacion, firmaAvisoSucursal } from './operacionUtils';

export default function useAvisoSucursalToast(estado) {
    const ultimaFirmaRef = useRef(null);

    useEffect(() => {
        const firma = firmaAvisoSucursal(estado);
        if (!firma || firma === ultimaFirmaRef.current) {
            return;
        }

        ultimaFirmaRef.current = firma;
        const aviso = avisoSucursalOperacion(estado);
        if (!aviso) {
            return;
        }

        emitirToastGelia(aviso.mensaje, aviso.tipo);
    }, [estado]);
}
