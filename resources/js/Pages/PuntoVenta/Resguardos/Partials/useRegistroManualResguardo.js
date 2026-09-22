import { useCallback, useRef, useState } from 'react';
import axios from 'axios';

const STORAGE_KEY = 'pdv.resguardos.registro_manual.idempotency';

function claveIdempotenciaRegistroManual() {
    try {
        const existente = sessionStorage.getItem(STORAGE_KEY);
        if (existente) {
            return existente;
        }
    } catch {
        // ponytail: sessionStorage no disponible; se genera clave en memoria.
    }

    const clave = `pdv:man:${Date.now()}:${Math.random().toString(36).slice(2, 10)}`;
    try {
        sessionStorage.setItem(STORAGE_KEY, clave);
    } catch {
        // ignore
    }

    return clave;
}

function limpiarClaveIdempotenciaRegistroManual() {
    try {
        sessionStorage.removeItem(STORAGE_KEY);
    } catch {
        // ignore
    }
}

function mensajeErrorRegistroManual(err) {
    const data = err?.response?.data;
    if (data?.errors && typeof data.errors === 'object') {
        const primero = Object.values(data.errors).flat()[0];
        if (primero) {
            return String(primero);
        }
    }

    return data?.message || err?.message || 'No se pudo registrar el resguardo.';
}

function payloadAFormData(payload, idempotencyKey) {
    const formData = new FormData();
    formData.append('idempotency_key', idempotencyKey);

    Object.entries(payload || {}).forEach(([clave, valor]) => {
        if (valor === null || valor === undefined || valor === '') {
            return;
        }
        if (clave === 'piezas' && Array.isArray(valor)) {
            valor.forEach((linea, indice) => {
                formData.append(`piezas[${indice}][producto_id]`, String(linea.producto_id));
                formData.append(`piezas[${indice}][cantidad]`, String(linea.cantidad));
            });
            return;
        }
        if (valor instanceof File) {
            formData.append(clave, valor);
            return;
        }
        if (typeof valor === 'boolean') {
            formData.append(clave, valor ? '1' : '0');
            return;
        }
        formData.append(clave, String(valor));
    });

    return formData;
}

export default function useRegistroManualResguardo({ onExito } = {}) {
    const [enviando, setEnviando] = useState(false);
    const [error, setError] = useState(null);
    const bloqueado = useRef(false);
    const idempotenciaRef = useRef(claveIdempotenciaRegistroManual());

    const renovarIdempotencia = useCallback(() => {
        limpiarClaveIdempotenciaRegistroManual();
        idempotenciaRef.current = claveIdempotenciaRegistroManual();
    }, []);

    const registrar = useCallback(async (payload) => {
        if (bloqueado.current || enviando) {
            return { duplicado: true };
        }

        bloqueado.current = true;
        setEnviando(true);
        setError(null);

        try {
            const { data } = await axios.post(
                route('punto_venta.resguardos.store'),
                payloadAFormData(payload, idempotenciaRef.current),
                { headers: { Accept: 'application/json' } },
            );

            renovarIdempotencia();
            bloqueado.current = false;
            onExito?.(data);

            return { ok: true, data };
        } catch (err) {
            const mensaje = mensajeErrorRegistroManual(err);
            setError(mensaje);
            bloqueado.current = false;

            return {
                error: mensaje,
                validacion: err?.response?.data?.errors || null,
                status: err?.response?.status,
            };
        } finally {
            setEnviando(false);
        }
    }, [enviando, onExito, renovarIdempotencia]);

    return {
        enviando,
        error,
        registrar,
        setError,
    };
}
