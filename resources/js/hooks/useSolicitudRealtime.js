import { useEffect, useRef, useCallback } from 'react';
import { recargarModuloInertia } from '../utils/recargarModuloInertia';

/**
 * Hook genérico para recibir actualizaciones en tiempo real de solicitudes.
 *
 * Escucha un canal privado de Echo y, cuando llega un evento relevante para
 * el usuario actual (según sus permisos y departamentos), recarga las props
 * del listado vía Inertia sin refrescar toda la página.
 *
 * @param {string}   canal          — Nombre del canal privado (ej: 'solicitudes.facturas')
 * @param {string}   evento         — Nombre del evento broadcast (ej: '.solicitud-factura.actualizada')
 * @param {string[]} propsListado   — Props de Inertia a recargar (ej: ['facturas', 'metricas', 'filtros'])
 * @param {object}   auth           — Objeto auth de Inertia (auth.user)
 */
export default function useSolicitudRealtime(canal, evento, propsListado, auth) {
    const authRef = useRef(auth);
    useEffect(() => { authRef.current = auth; });

    const esRelevante = useCallback((payload) => {
        const user = authRef.current?.user;
        if (!user) return false;

        const permisos = user.permissions || [];
        const roles = user.roles || [];
        const departamentoIds = user.departamento_ids || [];

        const esAdmin = roles.includes('Super Admin') || roles.includes('Administrador');

        // Los admins siempre reciben actualizaciones
        if (esAdmin) return true;

        // Usuarios con visibilidad de área (gerentes, verificadores, respondedores)
        // solo reciben eventos de su departamento
        const esGerente = roles.some((r) => String(r).toLowerCase().includes('gerente'));
        const tieneVisibilidadArea = esGerente
            || permisos.includes('facturas.verificar')
            || permisos.includes('facturas.responder')
            || permisos.includes('cancelaciones_cotizaciones.verificar')
            || permisos.includes('cancelaciones_cotizaciones.reportar')
            || permisos.includes('cancelaciones_cotizaciones.cancelar');

        if (tieneVisibilidadArea) {
            // Si el evento tiene departamento, verificar que pertenezca a mis departamentos
            if (payload.departamento_id && departamentoIds.length > 0) {
                return departamentoIds.includes(payload.departamento_id);
            }
            // Sin departamento asignado o sin departamentos del usuario: recargar por seguridad
            return true;
        }

        // Vendedoras: solo reciben eventos de sus propias solicitudes
        if (payload.vendedor_id === user.id) return true;

        return false;
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined' || !window.Echo) return;

        const channel = window.Echo.private(canal);

        channel.listen(evento, (payload) => {
            if (esRelevante(payload)) {
                recargarModuloInertia(propsListado);
            }
        });

        return () => {
            window.Echo.leave(canal);
        };
    }, [canal, evento, propsListado, esRelevante]);
}
