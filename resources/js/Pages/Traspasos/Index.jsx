import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Plus, Loader2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import GeliaPageShell from '../../Components/GeliaPageShell';
import { geliaCardClass } from '../../utils/geliaTheme';
import { puedePermiso } from '../../utils/permisos';
import useSolicitudRealtime from '../../hooks/useSolicitudRealtime';
import TarjetaTraspaso from './Partials/TarjetaTraspaso';
import FiltrosTraspasos from './Partials/FiltrosTraspasos';
import ModalFormTraspaso from './Partials/ModalFormTraspaso';
import ModalDetalleTraspaso from './Partials/ModalDetalleTraspaso';
import ModalResponderTraspaso from './Partials/ModalResponderTraspaso';
import ModalBitacoraTraspaso from './Partials/ModalBitacoraTraspaso';

const PROPS_LISTADO = ['traspasos', 'filtros'];

const OPCIONES_LISTADO = {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    showProgress: false,
    only: PROPS_LISTADO,
};

function idEstadoPorNombre(estados, nombre) {
    return estados.find((e) => e.nombre === nombre)?.id ?? null;
}

function fechasDesdeTipo(tipoFecha, fechaInicio, fechaFin) {
    const hoy = new Date();
    const iso = (d) => d.toISOString().slice(0, 10);

    switch (tipoFecha) {
        case 'HOY':
            return { fecha_inicio: iso(hoy), fecha_fin: iso(hoy) };
        case 'AYER': {
            const ayer = new Date(hoy);
            ayer.setDate(ayer.getDate() - 1);
            return { fecha_inicio: iso(ayer), fecha_fin: iso(ayer) };
        }
        case 'SEMANA': {
            const inicio = new Date(hoy);
            const dia = inicio.getDay();
            const diff = dia === 0 ? 6 : dia - 1;
            inicio.setDate(inicio.getDate() - diff);
            return { fecha_inicio: iso(inicio), fecha_fin: iso(hoy) };
        }
        case 'MES': {
            const inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
            return { fecha_inicio: iso(inicio), fecha_fin: iso(hoy) };
        }
        case 'PERSONALIZADO':
            return {
                fecha_inicio: fechaInicio || undefined,
                fecha_fin: fechaFin || undefined,
            };
        default:
            return { fecha_inicio: undefined, fecha_fin: undefined };
    }
}

export default function Index({
    auth,
    traspasos,
    filtros = {},
    vendedores = [],
    almacenes = [],
    horarios = [],
    estados = [],
}) {
    const puedeCrear = puedePermiso(auth, 'traspasos.crear');
    const idRespondida = idEstadoPorNombre(estados, 'Respondida');
    const idIncorrecta = idEstadoPorNombre(estados, 'Incorrecta');

    const [tabActiva, setTabActiva] = useState(filtros.tab || 'TODAS');
    const [listaCargando, setListaCargando] = useState(false);
    const [busqueda, setBusqueda] = useState(filtros.q || filtros.folio || '');
    const [tipoFecha, setTipoFecha] = useState(filtros.tipo_fecha || 'TODAS');
    const [fechaInicio, setFechaInicio] = useState(filtros.fecha_inicio || '');
    const [fechaFin, setFechaFin] = useState(filtros.fecha_fin || '');
    const [filtroVendedor, setFiltroVendedor] = useState(filtros.vendedor_id || '');
    const [filtroAlmacen, setFiltroAlmacen] = useState(filtros.almacen_origen_id || '');

    const [modalForm, setModalForm] = useState(false);
    const [modalDetalle, setModalDetalle] = useState(null);
    const [modalBitacora, setModalBitacora] = useState(null);
    const [modalRespuesta, setModalRespuesta] = useState({
        abierto: false,
        traspaso: null,
        estadoId: null,
        modo: 'responder',
    });

    useEffect(() => {
        setTabActiva(filtros.tab || 'TODAS');
        setBusqueda(filtros.q || filtros.folio || '');
        setTipoFecha(filtros.tipo_fecha || 'TODAS');
        setFechaInicio(filtros.fecha_inicio || '');
        setFechaFin(filtros.fecha_fin || '');
        setFiltroVendedor(filtros.vendedor_id || '');
        setFiltroAlmacen(filtros.almacen_origen_id || '');
    }, [filtros]);

    useSolicitudRealtime('solicitudes.traspasos', '.solicitud-traspaso.actualizada', PROPS_LISTADO, auth);

    const filtrosActivos = useMemo(() => {
        let n = 0;
        if (filtroVendedor) n += 1;
        if (filtroAlmacen) n += 1;
        if (tipoFecha === 'PERSONALIZADO' && (fechaInicio || fechaFin)) n += 1;
        else if (tipoFecha !== 'TODAS' && tipoFecha !== 'PERSONALIZADO') n += 1;
        return n;
    }, [filtroVendedor, filtroAlmacen, tipoFecha, fechaInicio, fechaFin]);

    const paramsBase = useCallback(
        (extra = {}) => {
            const fechas = fechasDesdeTipo(
                extra.tipo_fecha ?? tipoFecha,
                extra.fecha_inicio ?? fechaInicio,
                extra.fecha_fin ?? fechaFin
            );
            const p = {
                q: (extra.q !== undefined ? extra.q : busqueda) || undefined,
                vendedor_id: (extra.vendedor_id !== undefined ? extra.vendedor_id : filtroVendedor) || undefined,
                almacen_origen_id: (extra.almacen_origen_id !== undefined ? extra.almacen_origen_id : filtroAlmacen) || undefined,
                tipo_fecha: (extra.tipo_fecha !== undefined ? extra.tipo_fecha : tipoFecha) || undefined,
                tab: extra.tab ?? tabActiva,
                page: extra.page ?? 1,
                ...fechas,
                ...extra,
            };
            if (p.tipo_fecha === 'TODAS') {
                delete p.tipo_fecha;
                delete p.fecha_inicio;
                delete p.fecha_fin;
            }
            return Object.fromEntries(
                Object.entries(p).filter(([, v]) => v !== '' && v !== null && v !== undefined)
            );
        },
        [busqueda, filtroVendedor, filtroAlmacen, tipoFecha, fechaInicio, fechaFin, tabActiva]
    );

    const recargarListado = useCallback(
        (extra = {}) => {
            if (extra.q !== undefined) setBusqueda(extra.q);
            if (extra.vendedor_id !== undefined) setFiltroVendedor(extra.vendedor_id || '');
            if (extra.almacen_origen_id !== undefined) setFiltroAlmacen(extra.almacen_origen_id || '');
            if (extra.tipo_fecha !== undefined) setTipoFecha(extra.tipo_fecha || 'TODAS');
            if (extra.fecha_inicio !== undefined) setFechaInicio(extra.fecha_inicio || '');
            if (extra.fecha_fin !== undefined) setFechaFin(extra.fecha_fin || '');
            if (extra.tab !== undefined) setTabActiva(extra.tab);

            router.get(route('traspasos.index'), paramsBase(extra), {
                ...OPCIONES_LISTADO,
                onStart: () => setListaCargando(true),
                onFinish: () => setListaCargando(false),
            });
        },
        [paramsBase]
    );

    const limpiarFiltrosAdicionales = useCallback(() => {
        setTipoFecha('TODAS');
        setFechaInicio('');
        setFechaFin('');
        setFiltroVendedor('');
        setFiltroAlmacen('');
        router.get(
            route('traspasos.index'),
            paramsBase({
                tipo_fecha: 'TODAS',
                fecha_inicio: '',
                fecha_fin: '',
                vendedor_id: '',
                almacen_origen_id: '',
                page: 1,
            }),
            {
                ...OPCIONES_LISTADO,
                onStart: () => setListaCargando(true),
                onFinish: () => setListaCargando(false),
            }
        );
    }, [paramsBase]);

    const irAPagina = useCallback(
        (pagina) => {
            if (pagina < 1 || pagina > (traspasos?.last_page || 1)) return;
            router.get(route('traspasos.index'), paramsBase({ page: pagina }), {
                ...OPCIONES_LISTADO,
                onStart: () => setListaCargando(true),
                onFinish: () => setListaCargando(false),
            });
        },
        [paramsBase, traspasos?.last_page]
    );

    const recargarTrasAccion = useCallback(() => {
        router.get(route('traspasos.index'), paramsBase({ page: 1 }), {
            ...OPCIONES_LISTADO,
            onStart: () => setListaCargando(true),
            onFinish: () => setListaCargando(false),
        });
    }, [paramsBase]);

    const verificar = (traspaso) => {
        router.put(route('traspasos.verificar', traspaso.id), {}, {
            preserveScroll: true,
            onSuccess: recargarTrasAccion,
        });
    };

    const eliminar = useCallback((traspaso) => {
        const motivo = window.prompt('Motivo de la eliminación (mínimo 5 caracteres):');
        if (motivo !== null) {
            if (motivo.trim().length >= 5) {
                router.delete(route('traspasos.destroy', traspaso.id), {
                    data: { motivo },
                    preserveScroll: true,
                    onSuccess: recargarTrasAccion,
                });
            } else {
                alert('El motivo debe tener al menos 5 caracteres.');
            }
        }
    }, [recargarTrasAccion]);

    const listaVisible = traspasos?.data || [];

    return (
        <AppLayout>
            <Head title="Traspasos | GELIA" />
            <GeliaPageShell className="space-y-6 md:space-y-8">
                <header className={`${geliaCardClass()} p-6 md:p-10 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6`}>
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>
                                Panel General
                            </p>
                        </div>
                        <h1 className="text-3xl md:text-4xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            Solicitud de <span style={{ color: 'var(--color-primario)' }}>Traspasos</span>
                        </h1>
                    </div>
                    {puedeCrear && (
                        <button
                            type="button"
                            onClick={() => setModalForm(true)}
                            className="flex items-center justify-center gap-2 px-8 py-4 text-white rounded-2xl font-black uppercase tracking-widest text-xs shadow-xl hover:scale-105 transition-all w-full md:w-auto"
                            style={{ backgroundColor: 'var(--color-primario)', fontFamily: 'var(--font-principal)' }}
                        >
                            <Plus className="w-5 h-5" /> Nueva Solicitud
                        </button>
                    )}
                </header>

                <FiltrosTraspasos
                    tabActiva={tabActiva}
                    busqueda={busqueda}
                    tipoFecha={tipoFecha}
                    fechaInicio={fechaInicio}
                    fechaFin={fechaFin}
                    filtroVendedor={filtroVendedor}
                    filtroAlmacen={filtroAlmacen}
                    vendedores={vendedores}
                    almacenes={almacenes}
                    filtrosActivos={filtrosActivos}
                    onCambiarTab={(tab) => recargarListado({ tab, page: 1 })}
                    onAplicarFiltros={(vals) => recargarListado({ ...vals, page: 1 })}
                    onLimpiarAdicionales={limpiarFiltrosAdicionales}
                />

                <div className="relative min-h-[12rem]">
                    {listaCargando && (
                        <div className="absolute inset-0 z-10 flex items-start justify-center pt-16 bg-black/5 dark:bg-black/20 rounded-3xl">
                            <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} />
                        </div>
                    )}
                    {listaVisible.length === 0 ? (
                        <div className="theme-surface rounded-3xl p-10 md:p-12 text-center border theme-border theme-text-muted font-bold text-sm uppercase tracking-wider">
                            No se encontraron solicitudes de traspaso_
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {listaVisible.map((t) => (
                                <TarjetaTraspaso
                                    key={t.id}
                                    traspaso={t}
                                    auth={auth}
                                    onVerDetalle={setModalDetalle}
                                    onBitacora={setModalBitacora}
                                    onResponder={(traspaso) => setModalRespuesta({
                                        abierto: true,
                                        traspaso,
                                        estadoId: idRespondida,
                                        modo: 'responder',
                                    })}
                                    onReportar={(traspaso) => setModalRespuesta({
                                        abierto: true,
                                        traspaso,
                                        estadoId: idIncorrecta,
                                        modo: 'reportar',
                                    })}
                                    onVerificar={verificar}
                                    onEliminar={eliminar}
                                />
                            ))}
                        </div>
                    )}
                </div>

                {listaVisible.length > 0 && (
                    <GeliaPaginacion paginator={traspasos} onIrAPagina={irAPagina} />
                )}
            </GeliaPageShell>

            {modalForm && (
                <ModalFormTraspaso
                    almacenes={almacenes}
                    horarios={horarios}
                    onClose={() => setModalForm(false)}
                    onExito={recargarTrasAccion}
                />
            )}
            {modalDetalle && (
                <ModalDetalleTraspaso traspaso={modalDetalle} onClose={() => setModalDetalle(null)} />
            )}
            {modalBitacora && (
                <ModalBitacoraTraspaso traspaso={modalBitacora} onClose={() => setModalBitacora(null)} />
            )}
            {modalRespuesta.abierto && modalRespuesta.traspaso && (
                <ModalResponderTraspaso
                    traspaso={modalRespuesta.traspaso}
                    estadoId={modalRespuesta.estadoId}
                    modo={modalRespuesta.modo}
                    onClose={() => setModalRespuesta({
                        abierto: false,
                        traspaso: null,
                        estadoId: null,
                        modo: 'responder',
                    })}
                    onExito={recargarTrasAccion}
                />
            )}
        </AppLayout>
    );
}
