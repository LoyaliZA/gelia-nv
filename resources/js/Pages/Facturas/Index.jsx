import React, { useState, useCallback, useMemo, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Receipt, Plus, Download, Database, Loader2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import FiltrosFacturas from './Partials/FiltrosFacturas';
import TarjetaFactura from './Partials/TarjetaFactura';
import ModalFormFactura from './Partials/ModalFormFactura';
import ModalResponderFactura from './Partials/ModalResponderFactura';
import ModalExpedienteFactura from './Partials/ModalExpedienteFactura';
import { BTN_PRIMARY, BTN_SECONDARY } from './Partials/facturasStyles';
import { filtrarFacturasPorTab, idEstadoPorNombre } from './Partials/facturasFiltros';
import GeliaPageShell from '../../Components/GeliaPageShell';
import { geliaCardClass, GELIA_LISTADO_GRID } from '../../utils/geliaTheme';
import { puedePermiso } from '../../utils/permisos';
import { recargarModuloInertia } from '../../utils/recargarModuloInertia';
import useSolicitudRealtime from '../../hooks/useSolicitudRealtime';

const PROPS_LISTADO = ['facturas', 'metricas', 'filtros'];

const OPCIONES_LISTADO = {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    showProgress: false,
    only: PROPS_LISTADO,
};

export default function Index({ auth, facturas, metricas, filtros, vendedores, estados = [] }) {
    const puedeCrear = puedePermiso(auth, 'facturas.crear');
    const puedeExportar = puedePermiso(auth, 'facturas.exportar');
    const puedeDatosFiscales = puedePermiso(auth, 'facturas.gestionar_datos_fiscales');
    const idRespondida = idEstadoPorNombre(estados, 'Respondida');
    const idIncorrecta = idEstadoPorNombre(estados, 'Incorrecta');

    const [tabActiva, setTabActiva] = useState(filtros.tab || 'TODAS');
    const [listaCargando, setListaCargando] = useState(false);
    const [modalForm, setModalForm] = useState({ abierto: false, modoEdicion: false, factura: null });
    const [modalRespuesta, setModalRespuesta] = useState({ abierto: false, factura: null, estadoId: null, modo: 'emitir' });
    const [modalExpediente, setModalExpediente] = useState({ abierto: false, factura: null });

    useEffect(() => {
        setTabActiva(filtros.tab || 'TODAS');
    }, [filtros.tab]);

    useSolicitudRealtime('solicitudes.facturas', '.solicitud-factura.actualizada', PROPS_LISTADO, auth);

    const paramsBase = useCallback(
        (extra = {}) => {
            const p = {
                q: filtros.q || undefined,
                vendedor_id: filtros.vendedor_id || undefined,
                tab: tabActiva,
                page: 1,
                ...extra,
            };
            return Object.fromEntries(
                Object.entries(p).filter(([, v]) => v !== '' && v !== null && v !== undefined)
            );
        },
        [filtros.q, filtros.vendedor_id, tabActiva]
    );

    const recargarListado = useCallback(
        (extra = {}) => {
            router.get(route('facturas.index'), paramsBase(extra), {
                ...OPCIONES_LISTADO,
                onStart: () => setListaCargando(true),
                onFinish: () => setListaCargando(false),
            });
        },
        [paramsBase]
    );

    /** Pestaña: estado local al instante; sincroniza listado en segundo plano sin recargar toda la página. */
    const cambiarTab = useCallback(
        (tab) => {
            setTabActiva(tab);
            router.get(route('facturas.index'), paramsBase({ tab, page: 1 }), {
                ...OPCIONES_LISTADO,
                onStart: () => setListaCargando(true),
                onFinish: () => setListaCargando(false),
            });
        },
        [paramsBase]
    );

    const irAPagina = useCallback(
        (pagina) => {
            if (pagina < 1 || pagina > (facturas?.last_page || 1)) return;
            router.get(
                route('facturas.index'),
                paramsBase({ page: pagina }),
                {
                    ...OPCIONES_LISTADO,
                    preserveScroll: true,
                    onStart: () => setListaCargando(true),
                    onFinish: () => setListaCargando(false),
                }
            );
        },
        [paramsBase, facturas?.last_page]
    );

    const recargarTrasAccion = useCallback(() => {
        recargarModuloInertia(PROPS_LISTADO);
    }, []);

    const verificar = (factura) => {
        router.put(route('facturas.verificar', factura.id), {}, {
            preserveScroll: true,
            onSuccess: recargarTrasAccion,
        });
    };

    const eliminar = useCallback((factura) => {
        const motivo = window.prompt('Motivo de la eliminación (mínimo 5 caracteres):');
        if (motivo !== null) {
            if (motivo.trim().length >= 5) {
                router.delete(route('facturas.destroy', factura.id), {
                    data: { motivo },
                    preserveScroll: true,
                    onSuccess: recargarTrasAccion,
                });
            } else {
                alert('El motivo debe tener al menos 5 caracteres.');
            }
        }
    }, [recargarTrasAccion]);

    const listaServidor = facturas?.data || [];

    /** Vista optimista: filtra la página actual mientras llega la pestaña correcta del servidor. */
    const listaVisible = useMemo(() => {
        if (!listaCargando || (filtros.tab || 'TODAS') === tabActiva) {
            return listaServidor;
        }
        return filtrarFacturasPorTab(listaServidor, tabActiva);
    }, [listaServidor, tabActiva, listaCargando, filtros.tab]);

    const exportParams = useMemo(
        () => ({ ...filtros, tab: tabActiva }),
        [filtros, tabActiva]
    );

    const cardHeader = geliaCardClass('p-6 md:p-10 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-6');
    const cardMetricas = geliaCardClass('p-5 md:p-6 flex items-center gap-4 min-w-0');

    return (
        <AppLayout>
            <Head title="Solicitudes de Facturas" />

            <GeliaPageShell className="space-y-6 md:space-y-8">
                <header className={cardHeader}>
                    <div className="min-w-0">
                        <div className="flex items-center gap-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>
                                Fiscal
                            </p>
                        </div>
                        <h1 className="text-2xl sm:text-3xl md:text-5xl font-black italic uppercase tracking-tighter theme-text-main m-0 leading-none">
                            Control de <span style={{ color: 'var(--color-primario)' }}>Facturas</span>
                        </h1>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                            Solicitudes de facturación y expediente fiscal
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 w-full lg:w-auto shrink-0">
                        {puedeDatosFiscales && (
                            <Link href={route('facturas.datos_fiscales.index')} className={BTN_SECONDARY}>
                                <Database className="w-4 h-4 shrink-0" /> Datos fiscales
                            </Link>
                        )}
                        {puedeExportar && (
                            <a href={route('facturas.exportar', exportParams)} className={BTN_SECONDARY}>
                                <Download className="w-4 h-4 shrink-0" /> Exportar
                            </a>
                        )}
                        {puedeCrear && (
                            <button type="button" onClick={() => setModalForm({ abierto: true, modoEdicion: false, factura: null })} className={BTN_PRIMARY}>
                                <Plus className="w-4 h-4 shrink-0" /> Nueva solicitud
                            </button>
                        )}
                    </div>
                </header>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-6 min-w-0">
                    {[
                        { label: 'Pendientes', value: metricas?.pendientes ?? 0, color: '#f59e0b' },
                        { label: 'Respondidas hoy', value: metricas?.respondidas_hoy ?? 0, accent: true },
                        { label: 'Incorrectas', value: metricas?.incorrectas ?? 0, color: '#ef4444' },
                    ].map(({ label, value, color, accent }) => (
                        <div key={label} className={cardMetricas}>
                            <div className="p-3 rounded-2xl theme-element border theme-border shrink-0">
                                <Receipt className="w-6 h-6" style={{ color: accent ? 'var(--color-primario)' : color }} />
                            </div>
                            <div className="min-w-0">
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
                                <p className="text-2xl font-black theme-text-main m-0 tabular-nums leading-tight mt-1">{value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                <FiltrosFacturas
                    filtros={filtros}
                    vendedores={vendedores}
                    tabActiva={tabActiva}
                    onTabChange={cambiarTab}
                    onAplicarFiltros={recargarListado}
                    listaCargando={listaCargando}
                />

                {listaVisible.length === 0 && !listaCargando ? (
                    <div className={`${geliaCardClass()} text-center py-14 md:py-16 px-6`}>
                        <Receipt className="w-10 h-10 mx-auto mb-3 theme-text-muted opacity-50" />
                        <p className="text-sm font-black italic uppercase theme-text-main m-0">Sin solicitudes</p>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                            No hay facturas en «{tabActiva.toLowerCase()}» con los filtros actuales
                        </p>
                    </div>
                ) : (
                    <>
                        <div className={`${geliaCardClass('overflow-hidden')} relative transition-opacity duration-200 ${listaCargando ? 'opacity-60' : ''}`}>
                            {listaCargando && (
                                <div className="absolute inset-0 z-10 flex items-center justify-center bg-[var(--theme-surface-bg)]/50 backdrop-blur-[2px] pointer-events-none rounded-[inherit]">
                                    <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} aria-hidden />
                                    <span className="sr-only">Actualizando listado</span>
                                </div>
                            )}
                            <div className="p-4 md:p-6 border-b theme-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                    {listaCargando && (filtros.tab || 'TODAS') !== tabActiva
                                        ? `Mostrando coincidencias en esta página · cargando «${tabActiva.toLowerCase()}»…`
                                        : facturas?.total != null
                                          ? `${facturas.total.toLocaleString('es-MX')} solicitudes`
                                          : `${listaVisible.length} en esta página`}
                                </p>
                            </div>
                            <div className="p-4 md:p-6">
                                <div className={GELIA_LISTADO_GRID}>
                                    {listaVisible.map((f) => (
                                        <TarjetaFactura
                                            key={f.id}
                                            factura={f}
                                            auth={auth}
                                            onVerExpediente={(factura) => setModalExpediente({ abierto: true, factura })}
                                            onAprobar={(factura) => setModalRespuesta({ abierto: true, factura, estadoId: idRespondida, modo: 'emitir' })}
                                            onReportar={(factura) => setModalRespuesta({ abierto: true, factura, estadoId: idIncorrecta, modo: 'reportar' })}
                                            onVerificar={verificar}
                                            onEliminar={eliminar}
                                            onReparar={(factura) => setModalForm({ abierto: true, modoEdicion: true, factura })}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                        {!listaCargando && listaVisible.length > 0 && (
                            <GeliaPaginacion paginator={facturas} onIrAPagina={irAPagina} />
                        )}
                    </>
                )}
            </GeliaPageShell>
            {modalRespuesta.abierto && (
                <ModalResponderFactura
                    onClose={() => setModalRespuesta({ abierto: false, factura: null, estadoId: null, modo: 'emitir' })}
                    factura={modalRespuesta.factura}
                    estadoId={modalRespuesta.estadoId}
                    modo={modalRespuesta.modo}
                    onExito={recargarTrasAccion}
                />
            )}
            {modalExpediente.abierto && (
                <ModalExpedienteFactura
                    onClose={() => setModalExpediente({ abierto: false, factura: null })}
                    factura={modalExpediente.factura}
                    puedeActualizarCliente={puedeDatosFiscales}
                />
            )}
            {modalForm.abierto && (
                <ModalFormFactura
                    key={modalForm.modoEdicion ? `reparar-${modalForm.factura?.id}` : 'nueva'}
                    onClose={() => setModalForm({ abierto: false, modoEdicion: false, factura: null })}
                    onExito={recargarTrasAccion}
                    modoEdicion={modalForm.modoEdicion}
                    facturaAEditar={modalForm.factura}
                />
            )}
        </AppLayout>
    );
}
