import { useCallback, useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { normalizarFechaAlConfirmar } from '@/utils/fechaFiltro';

export function calcularRangoFechas(tipo, fInicio, fFin) {
    let inicioCalculado = fInicio;
    let finCalculado = fFin;

    if (tipo !== 'PERSONALIZADO' && tipo !== 'MES_ESPECIFICO' && tipo !== 'TODAS') {
        const hoy = new Date();
        const formatDate = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

        if (tipo === 'HOY') {
            inicioCalculado = finCalculado = formatDate(hoy);
        } else if (tipo === 'AYER') {
            const ayer = new Date(hoy);
            ayer.setDate(ayer.getDate() - 1);
            inicioCalculado = finCalculado = formatDate(ayer);
        } else if (tipo === 'SEMANA') {
            const primerDia = new Date(hoy);
            primerDia.setDate(primerDia.getDate() - (primerDia.getDay() + 6) % 7);
            inicioCalculado = formatDate(primerDia);
            finCalculado = formatDate(hoy);
        } else if (tipo === 'MES_ANTERIOR') {
            inicioCalculado = formatDate(new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1));
            finCalculado = formatDate(new Date(hoy.getFullYear(), hoy.getMonth(), 0));
        } else if (tipo === 'MES') {
            const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
            inicioCalculado = formatDate(primerDiaMes);
            finCalculado = formatDate(hoy);
        }
    } else if (tipo === 'TODAS') {
        inicioCalculado = '';
        finCalculado = '';
    }

    return { inicioCalculado, finCalculado };
}

/**
 * Estado y consulta de filtros compartidos entre Solicitudes y Reportes.
 */
export default function useFiltrosSolicitudesPage({
    filtros = {},
    rutaIndex,
    onInicioConsulta,
    onFinConsulta,
}) {
    const [tabActiva, setTabActiva] = useState(filtros.tab || 'TODAS');
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [filtroVendedor, setFiltroVendedor] = useState(filtros.vendedor_id || '');
    const [filtroMotivo, setFiltroMotivo] = useState(filtros.motivo_incorrecta || '');
    const [filtroLista, setFiltroLista] = useState(filtros.lista_id || '');
    const [filtroTipoCliente, setFiltroTipoCliente] = useState(filtros.tipo_cliente_id || '');
    const [filtroTag, setFiltroTag] = useState(filtros.tag || '');
    const [tipoFecha, setTipoFecha] = useState(filtros.tipo_fecha || 'TODAS');
    const [fechaInicio, setFechaInicio] = useState(filtros.fecha_inicio || '');
    const [fechaFin, setFechaFin] = useState(filtros.fecha_fin || '');

    useEffect(() => {
        setTabActiva(filtros.tab || 'TODAS');
        setBusqueda(filtros.q || '');
        setFiltroVendedor(filtros.vendedor_id || '');
        setFiltroMotivo(filtros.motivo_incorrecta || '');
        setFiltroLista(filtros.lista_id || '');
        setFiltroTipoCliente(filtros.tipo_cliente_id || '');
        setFiltroTag(filtros.tag || '');
        setTipoFecha(filtros.tipo_fecha || 'TODAS');
        setFechaInicio(filtros.fecha_inicio || '');
        setFechaFin(filtros.fecha_fin || '');
    }, [
        filtros.tab,
        filtros.q,
        filtros.vendedor_id,
        filtros.motivo_incorrecta,
        filtros.lista_id,
        filtros.tipo_cliente_id,
        filtros.tag,
        filtros.tipo_fecha,
        filtros.fecha_inicio,
        filtros.fecha_fin,
    ]);

    const filtrosAdicionalesActivos = useMemo(
        () => [filtroLista, filtroTipoCliente, filtroTag, filtroVendedor, filtroMotivo, tipoFecha !== 'TODAS' ? tipoFecha : ''].filter(Boolean).length,
        [filtroLista, filtroTipoCliente, filtroTag, filtroVendedor, filtroMotivo, tipoFecha]
    );

    const construirParams = useCallback(
        (overrides = {}) => {
            const tab = overrides.tab ?? tabActiva;
            const vendedorId = overrides.vendedor_id ?? filtroVendedor;
            const motivo = overrides.motivo_incorrecta ?? filtroMotivo;
            const tipo = overrides.tipo_fecha ?? tipoFecha;
            const q = overrides.q !== undefined ? overrides.q : busqueda;
            const fInicio = overrides.fecha_inicio ?? fechaInicio;
            const fFin = overrides.fecha_fin ?? fechaFin;
            const { inicioCalculado, finCalculado } = calcularRangoFechas(tipo, fInicio, fFin);
            const tabFinal = motivo ? 'INCORRECTAS' : tab;

            return Object.fromEntries(
                Object.entries({
                    tab: tabFinal !== 'TODAS' ? tabFinal : undefined,
                    lista_id: (overrides.lista_id ?? filtroLista) || undefined,
                    tipo_cliente_id: (overrides.tipo_cliente_id ?? filtroTipoCliente) || undefined,
                    tag: (overrides.tag ?? filtroTag) || undefined,
                    vendedor_id: vendedorId || undefined,
                    tipo_fecha: tipo !== 'TODAS' ? tipo : undefined,
                    fecha_inicio: inicioCalculado || undefined,
                    fecha_fin: finCalculado || undefined,
                    motivo_incorrecta: motivo || undefined,
                    q: q?.trim() || undefined,
                    page: overrides.page,
                }).filter(([, v]) => v !== '' && v !== null && v !== undefined)
            );
        },
        [tabActiva, filtroLista, filtroTipoCliente, filtroTag, filtroVendedor, filtroMotivo, tipoFecha, busqueda, fechaInicio, fechaFin]
    );

    const sincronizarEstadoFiltros = useCallback((overrides = {}) => {
        if (overrides.lista_id !== undefined) setFiltroLista(overrides.lista_id);
        if (overrides.tipo_cliente_id !== undefined) setFiltroTipoCliente(overrides.tipo_cliente_id);
        if (overrides.tag !== undefined) setFiltroTag(overrides.tag);
        if (overrides.tab !== undefined) setTabActiva(overrides.tab);
        if (overrides.vendedor_id !== undefined) setFiltroVendedor(overrides.vendedor_id);
        if (overrides.motivo_incorrecta !== undefined) {
            setFiltroMotivo(overrides.motivo_incorrecta);
            if (overrides.motivo_incorrecta) setTabActiva('INCORRECTAS');
        }
        if (overrides.tipo_fecha !== undefined) setTipoFecha(overrides.tipo_fecha);
        if (overrides.q !== undefined) setBusqueda(overrides.q);
        if (overrides.fecha_inicio !== undefined) setFechaInicio(overrides.fecha_inicio);
        if (overrides.fecha_fin !== undefined) setFechaFin(overrides.fecha_fin);
    }, []);

    const aplicarFiltros = useCallback(
        (overrides = {}) => {
            let payload = { ...overrides };

            if (payload.tipo_fecha === 'PERSONALIZADO' || (payload.tipo_fecha === undefined && tipoFecha === 'PERSONALIZADO')) {
                const inicioRaw = payload.fecha_inicio ?? fechaInicio;
                const finRaw = payload.fecha_fin ?? fechaFin;
                const rInicio = normalizarFechaAlConfirmar(inicioRaw);
                const rFin = normalizarFechaAlConfirmar(finRaw);
                if (!rInicio.ok || !rFin.ok) return;
                payload = { ...payload, fecha_inicio: rInicio.valor, fecha_fin: rFin.valor };
            }

            sincronizarEstadoFiltros(payload);
            onInicioConsulta?.();

            router.get(rutaIndex, construirParams(payload), {
                preserveState: true,
                preserveScroll: true,
                showProgress: false,
                onFinish: () => onFinConsulta?.(),
            });
        },
        [
            tipoFecha,
            fechaInicio,
            fechaFin,
            sincronizarEstadoFiltros,
            construirParams,
            rutaIndex,
            onInicioConsulta,
            onFinConsulta,
        ]
    );

    const limpiarFiltrosAdicionales = useCallback(() => {
        const nuevaTab = filtroMotivo ? 'TODAS' : tabActiva;
        aplicarFiltros({
            lista_id: '',
            tipo_cliente_id: '',
            tag: '',
            vendedor_id: '',
            motivo_incorrecta: '',
            tipo_fecha: 'TODAS',
            fecha_inicio: '',
            fecha_fin: '',
            tab: nuevaTab,
        });
    }, [filtroMotivo, tabActiva, aplicarFiltros]);

    const exportParams = useMemo(() => construirParams(), [construirParams]);

    return {
        tabActiva,
        busqueda,
        tipoFecha,
        fechaInicio,
        fechaFin,
        filtroLista,
        filtroTipoCliente,
        filtroTag,
        filtroVendedor,
        filtroMotivo,
        filtrosAdicionalesActivos,
        construirParams,
        exportParams,
        aplicarFiltros,
        limpiarFiltrosAdicionales,
    };
}
