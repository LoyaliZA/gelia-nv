import { router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useRef, useState } from 'react';

const PROPS = {
    acumulado: ['acumulado', 'totales', 'resumen'],
    productividad: ['productividad'],
};

export default function useActualizacionComisiones({ estado, areaId, tab, activo }) {
    const [observado, setObservado] = useState(null);
    const ediciones = useRef(new Set());
    const cancelarRecarga = useRef(null);
    const revisionVista = useRef(estado?.revision);

    const proteger = (event) => {
        const superficie = event.target.closest?.('form, [role="dialog"]');
        if (superficie && !event.target.hasAttribute?.('data-busqueda-live')) ediciones.current.add(superficie);
        cancelarRecarga.current?.();
    };

    useEffect(() => {
        if (!activo || !estado?.revision) return undefined;
        let cerrado = false;
        let consulta;
        let recargando = false;
        const revisionInicial = estado.revision;
        revisionVista.current = revisionInicial;
        setObservado(null);
        const consultar = async () => {
            if (document.visibilityState !== 'visible' || !navigator.onLine || consulta || recargando) return;
            consulta = new AbortController();
            try {
                const { data } = await axios.get(route('gestion_interna.comisiones_empaque.sincronizacion.estado'), {
                    params: { area_id: areaId || undefined },
                    signal: consulta.signal,
                });
                if (cerrado) return;
                setObservado(data);
                if (data.revision === revisionVista.current || data.estado === 'en_curso') return;
                for (const superficie of ediciones.current) {
                    if (!superficie.isConnected) ediciones.current.delete(superficie);
                }
                if (!PROPS[tab] || ediciones.current.size || document.querySelector('[role="dialog"][aria-modal="true"]')) return;

                recargando = true;
                router.reload({
                    only: [...PROPS[tab], 'sincronizacion'],
                    preserveState: true,
                    preserveScroll: true,
                    onCancelToken: (token) => { cancelarRecarga.current = token.cancel; },
                    onSuccess: (pagina) => { revisionVista.current = pagina.props.sincronizacion?.revision || data.revision; },
                    onFinish: () => { recargando = false; cancelarRecarga.current = null; },
                });
            } catch {
                // El siguiente ciclo vuelve a consultar sin interferir con la captura.
            } finally {
                consulta = null;
            }
        };
        const intervalo = window.setInterval(consultar, 25000);
        document.addEventListener('visibilitychange', consultar);
        window.addEventListener('online', consultar);
        window.addEventListener('focus', consultar);
        return () => {
            cerrado = true;
            consulta?.abort();
            cancelarRecarga.current?.();
            window.clearInterval(intervalo);
            document.removeEventListener('visibilitychange', consultar);
            window.removeEventListener('online', consultar);
            window.removeEventListener('focus', consultar);
        };
    // La revisión de la visita sigue avanzando tras cada recarga parcial.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [activo, areaId, tab, Boolean(estado?.revision)]);

    useEffect(() => router.on?.('success', (event) => {
        const revision = event.detail.page.props.sincronizacion?.revision;
        if (revision) {
            revisionVista.current = revision;
            setObservado(event.detail.page.props.sincronizacion);
        }
    }), []);

    return {
        observado: observado || estado,
        pendiente: Boolean(observado?.revision && observado.revision !== revisionVista.current),
        proteger,
        cancelarRecarga: () => cancelarRecarga.current?.(),
    };
}
