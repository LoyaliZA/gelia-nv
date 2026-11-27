import { createContext, useContext } from 'react';

/**
 * Permite saltar de un envío a otro del mismo multienvío sin cerrar el modal.
 * Lo provee la página de Ventas; sin proveedor los envíos hermanos se muestran sin acción.
 */
export const CambiarEnvioContext = createContext(null);

export function useCambiarEnvio() {
    return useContext(CambiarEnvioContext);
}
