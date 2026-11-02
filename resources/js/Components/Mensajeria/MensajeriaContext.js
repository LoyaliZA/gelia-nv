import { createContext, useContext } from 'react';

export const MensajeriaContext = createContext(null);

export function useMensajeriaContext() {
    const ctx = useContext(MensajeriaContext);
    if (!ctx) {
        throw new Error('La mensajería no está disponible en esta vista.');
    }
    return ctx;
}
