import { router } from '@inertiajs/react';
import axios from 'axios';
import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { recargarModuloInertia } from '@/utils/recargarModuloInertia';
import { invalidarPrecargas } from './compartido';

const PROPS = {
    acumulado: ['acumulado', 'totales', 'resumen'],
    productividad: ['productividad'],
    periodos: ['periodos', 'equipo', 'periodos_opciones'],
};

export const ProteccionEdiciones = createContext(null);

export function useProtegerEdicion(pendiente) {
    const registrar = useContext(ProteccionEdiciones);
    useEffect(() => pendiente && registrar ? registrar() : undefined, [pendiente, registrar]);
}

export default function useActualizacionComisiones({ estado, areaId, tab, activo, ocupado }) {
    const [observado, setObservado] = useState(null);
    const ediciones = useRef(new Set());
    const cancelarRecarga = useRef(null);
    const cancelarEstado = useRef(null);
    const recibido = useRef(estado);
    const contextoVista = useRef(estado?.contexto);
    const revisionVista = useRef(estado?.revision);
    const actualizar = useRef(null);
    const cancelar = useCallback(() => {
        cancelarEstado.current?.();
        cancelarRecarga.current?.();
    }, []);
    const confirmarSalida = useCallback(() => {
        cancelar();
        return !ediciones.current.size || window.confirm('Hay cambios sin guardar. ¿Descartar los cambios y continuar?');
    }, [cancelar]);

    const registrarEdicion = useCallback(() => {
        const edicion = Symbol();
        ediciones.current.add(edicion);
        cancelarRecarga.current?.();
        return () => {
            ediciones.current.delete(edicion);
            if (!ediciones.current.size) actualizar.current?.();
        };
    }, []);

    useEffect(() => {
        if (!estado) return;
        if (estado.revision !== recibido.current?.revision || estado.contexto !== recibido.current?.contexto) invalidarPrecargas();
        recibido.current = estado;
        revisionVista.current = estado.revision;
        contextoVista.current = estado.contexto;
        setObservado(null);
    }, [estado]);

    useEffect(() => {
        if (!activo || !estado?.revision || ocupado) return undefined;
        let cerrado = false;
        let consulta;
        let recargando = false;

        const consultar = async () => {
            if (document.visibilityState !== 'visible' || !navigator.onLine || consulta || recargando) return;
            consulta = new AbortController();
            const consultada = window.location.href;
            const cancelarConsultaEstado = () => consulta?.abort();
            cancelarEstado.current = cancelarConsultaEstado;
            try {
                const { data } = await axios.get(route('gestion_interna.comisiones_empaque.sincronizacion.estado'), {
                    params: { area_id: areaId || undefined },
                    signal: consulta.signal,
                });
                if (cerrado || consulta.signal.aborted || window.location.href !== consultada) return;
                if (data.revision !== recibido.current?.revision || data.contexto !== recibido.current?.contexto) invalidarPrecargas();
                recibido.current = data;
                setObservado(data);
                const permisosCambiaron = Boolean(data.contexto && data.contexto !== contextoVista.current);
                if (!permisosCambiaron && (data.revision === revisionVista.current || data.estado === 'en_curso')) return;
                if ((!PROPS[tab] && !permisosCambiaron) || ediciones.current.size) return;
                const props = [...(PROPS[tab] || []), 'sincronizacion'];
                if (permisosCambiaron) props.push('areas', 'sin_areas', 'puede', 'periodos_opciones');

                recargando = true;
                recargarModuloInertia([...new Set(props)], {
                    preserveErrors: true,
                    preserveUrl: true,
                    onCancelToken: (token) => { cancelarRecarga.current = token.cancel; },
                    onSuccess: (pagina) => { revisionVista.current = pagina.props.sincronizacion?.revision || data.revision; },
                    onHttpException: ({ status }) => {
                        if ([401, 403].includes(status)) setObservado({ estado: 'sin_acceso' });
                        return false;
                    },
                    onNetworkError: () => false,
                    onFinish: () => { recargando = false; cancelarRecarga.current = null; },
                });
            } catch (error) {
                if (!cerrado && [401, 403].includes(error.response?.status)) {
                    invalidarPrecargas();
                    setObservado({ estado: 'sin_acceso' });
                }
            } finally {
                consulta = null;
                if (cancelarEstado.current === cancelarConsultaEstado) cancelarEstado.current = null;
            }
        };
        actualizar.current = consultar;
        const intervalo = window.setInterval(consultar, 25000);
        document.addEventListener('visibilitychange', consultar);
        window.addEventListener('online', consultar);
        window.addEventListener('focus', consultar);
        return () => {
            cerrado = true;
            actualizar.current = null;
            consulta?.abort();
            cancelarRecarga.current?.();
            window.clearInterval(intervalo);
            document.removeEventListener('visibilitychange', consultar);
            window.removeEventListener('online', consultar);
            window.removeEventListener('focus', consultar);
        };
    // La revisión de referencia se actualiza con cada respuesta de Inertia.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [activo, areaId, tab, ocupado, Boolean(estado?.revision)]);

    return {
        observado: observado || estado,
        pendiente: Boolean(observado?.revision && observado.revision !== revisionVista.current),
        registrarEdicion,
        confirmarSalida,
        comprobar: () => actualizar.current?.(),
        cancelarRecarga: cancelar,
    };
}
