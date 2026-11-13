import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import {
    armarFormDataEntrega,
    claveIdempotenciaEntrega,
    comprimirEvidenciasEntrega,
    dataUrlAFichero,
    limpiarClaveIdempotenciaEntrega,
    MENSAJE_FOTO_ENTREGA_GRANDE,
    mensajeErrorEntrega,
    validarFormularioEntrega,
    esConflictoVersion,
} from './entregaResguardoUtils';
import { abrirDetalleResguardoModal } from './resguardoDetalleModalBridge';

export default function useEntregaResguardo({
    resguardoId,
    versionInicial,
    metodoValidacion,
    modoModal = false,
    onExitoModal,
    onRecargarFormulario,
}) {
    const [enviando, setEnviando] = useState(false);
    const [preparando, setPreparando] = useState(false);
    const [progreso, setProgreso] = useState(0);
    const [error, setError] = useState(null);
    const [exito, setExito] = useState(false);
    const [resguardoResultado, setResguardoResultado] = useState(null);
    const versionRef = useRef(versionInicial);
    const envioBloqueado = useRef(false);
    const idempotencyRef = useRef(claveIdempotenciaEntrega(resguardoId));

    useEffect(() => {
        if (versionInicial) {
            versionRef.current = versionInicial;
        }
    }, [versionInicial]);

    const enviar = useCallback(async ({
        relacion,
        nombreQuienRetira,
        observaciones,
        firmaDataUrl,
        evidencias,
        bultoIds,
    }) => {
        if (envioBloqueado.current || enviando) {
            return { duplicado: true };
        }

        const erroresLocales = validarFormularioEntrega({
            relacion,
            nombreQuienRetira,
            tieneFirma: Boolean(firmaDataUrl),
            bultoIds,
        });
        if (Object.keys(erroresLocales).length > 0) {
            setError(Object.values(erroresLocales)[0]);
            return { validacion: erroresLocales };
        }

        envioBloqueado.current = true;
        setEnviando(true);
        setPreparando(true);
        setProgreso(0);
        setError(null);

        let evidenciasListas = evidencias;
        try {
            evidenciasListas = await comprimirEvidenciasEntrega(evidencias);
        } catch (err) {
            const mensaje = err?.message === MENSAJE_FOTO_ENTREGA_GRANDE
                ? err.message
                : MENSAJE_FOTO_ENTREGA_GRANDE;
            setError(mensaje);
            envioBloqueado.current = false;
            setPreparando(false);
            setEnviando(false);
            return { error: mensaje };
        }

        setPreparando(false);

        const firma = dataUrlAFichero(firmaDataUrl);
        const form = armarFormDataEntrega({
            version: versionRef.current,
            idempotencyKey: idempotencyRef.current,
            relacion,
            nombreQuienRetira,
            metodoValidacion,
            observaciones,
            firma,
            evidencias: evidenciasListas,
            bultoIds,
        });

        try {
            const { data } = await axios.put(route('punto_venta.resguardos.entrega', resguardoId), form, {
                headers: { Accept: 'application/json' },
                onUploadProgress: (event) => {
                    if (!event.total) return;
                    setProgreso(Math.round((event.loaded / event.total) * 100));
                },
            });

            if (data?.resguardo?.version) {
                versionRef.current = data.resguardo.version;
            }

            setProgreso(100);
            setExito(true);
            setResguardoResultado(data?.resguardo || null);
            limpiarClaveIdempotenciaEntrega(resguardoId);
            if (modoModal) {
                onExitoModal?.({ ok: true, resguardo: data?.resguardo, fase: 'exito' });
            }
            return { ok: true, resguardo: data?.resguardo };
        } catch (err) {
            const mensaje = mensajeErrorEntrega(err);
            setError(mensaje);

            if (err?.response?.status === 409) {
                envioBloqueado.current = true;
            } else if (!esConflictoVersion(err)) {
                envioBloqueado.current = false;
            }

            return {
                error: mensaje,
                conflictoVersion: esConflictoVersion(err),
                status: err?.response?.status,
            };
        } finally {
            setPreparando(false);
            setEnviando(false);
        }
    }, [enviando, metodoValidacion, resguardoId, modoModal, onExitoModal]);

    const irADetalle = useCallback(() => {
        if (modoModal) {
            onExitoModal?.({ accion: 'detalle', resguardoId });
        }
        abrirDetalleResguardoModal(resguardoId);
    }, [modoModal, onExitoModal, resguardoId]);

    const irABandeja = useCallback(() => {
        if (modoModal) {
            onExitoModal?.({ accion: 'bandeja' });
        }
        router.visit(route('punto_venta.resguardos.index', { bandeja: 'en_custodia' }));
    }, [modoModal, onExitoModal]);

    const recargarFormulario = useCallback(() => {
        if (modoModal && onRecargarFormulario) {
            onRecargarFormulario();
        } else {
            router.reload({ only: ['resguardo', 'puede_entregar', 'motivo_no_entregable', 'catalogos'] });
        }
        envioBloqueado.current = false;
        setError(null);
        setExito(false);
        setResguardoResultado(null);
    }, [modoModal, onRecargarFormulario]);

    return {
        enviar,
        enviando,
        preparando,
        progreso,
        error,
        exito,
        resguardoResultado,
        setError,
        irADetalle,
        irABandeja,
        recargarFormulario,
        idempotencyKey: idempotencyRef.current,
    };
}
