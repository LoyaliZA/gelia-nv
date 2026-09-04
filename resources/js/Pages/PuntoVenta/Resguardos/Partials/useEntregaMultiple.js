import { useCallback, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import {
    armarFormDataEntregaMultiple,
    claveIdempotenciaEntrega,
    claveIdempotenciaEntregaMultiple,
    dataUrlAFichero,
    limpiarClaveIdempotenciaEntregaMultiple,
    mensajeErrorEntrega,
    validarFormularioEntrega,
    esConflictoVersion,
} from './entregaResguardoUtils';

export default function useEntregaMultiple({ resguardos, metodoValidacion }) {
    const [enviando, setEnviando] = useState(false);
    const [progreso, setProgreso] = useState(0);
    const [error, setError] = useState(null);
    const [exito, setExito] = useState(false);
    const [resultados, setResultados] = useState([]);
    const envioBloqueado = useRef(false);
    const ids = useMemo(() => resguardos.map((item) => item.id), [resguardos]);
    const loteRef = useRef(claveIdempotenciaEntregaMultiple(ids));

    const enviar = useCallback(async (borradores) => {
        if (envioBloqueado.current || enviando) {
            return { duplicado: true };
        }

        for (const resguardo of resguardos) {
            const datos = borradores[resguardo.id];
            const errores = validarFormularioEntrega({
                relacion: datos?.relacion,
                nombreQuienRetira: datos?.nombreQuienRetira,
                tieneFirma: Boolean(datos?.firmaDataUrl),
                bultoIds: datos?.bultoIds,
            });
            if (Object.keys(errores).length > 0) {
                setError(Object.values(errores)[0]);
                return { validacion: errores, resguardoId: resguardo.id };
            }
        }

        envioBloqueado.current = true;
        setEnviando(true);
        setProgreso(0);
        setError(null);

        const entregas = resguardos.map((resguardo) => {
            const datos = borradores[resguardo.id];
            return {
                resguardoId: resguardo.id,
                version: resguardo.version,
                idempotencyKey: `${loteRef.current}:${resguardo.id}`,
                relacion: datos.relacion,
                nombreQuienRetira: datos.nombreQuienRetira,
                metodoValidacion,
                observaciones: datos.observaciones,
                firma: dataUrlAFichero(datos.firmaDataUrl, `firma-${resguardo.id}.png`),
                evidencias: datos.evidencias || [],
                bultoIds: datos.bultoIds || [],
            };
        });

        try {
            const { data } = await axios.post(route('punto_venta.resguardos.entregas_multiples.store'), armarFormDataEntregaMultiple(entregas), {
                headers: { Accept: 'application/json' },
                onUploadProgress: (event) => {
                    if (!event.total) return;
                    setProgreso(Math.round((event.loaded / event.total) * 100));
                },
            });

            setProgreso(100);
            setExito(true);
            setResultados(data?.resguardos || []);
            limpiarClaveIdempotenciaEntregaMultiple(ids);
            return { ok: true, resguardos: data?.resguardos };
        } catch (err) {
            const mensaje = mensajeErrorEntrega(err);
            setError(mensaje);
            if (err?.response?.status !== 409 && !esConflictoVersion(err)) {
                envioBloqueado.current = false;
            }
            return { error: mensaje, status: err?.response?.status };
        } finally {
            setEnviando(false);
        }
    }, [enviando, ids, metodoValidacion, resguardos]);

    const irABandeja = useCallback(() => {
        router.visit(route('punto_venta.resguardos.index', { bandeja: 'en_custodia' }));
    }, []);

    return {
        enviar,
        enviando,
        progreso,
        error,
        exito,
        resultados,
        irABandeja,
        claveItem: (resguardoId) => claveIdempotenciaEntrega(resguardoId),
    };
}
