import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Receipt, Plus, Download, Database, FileSpreadsheet } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import FiltrosFacturas from './Partials/FiltrosFacturas';
import TarjetaFactura from './Partials/TarjetaFactura';
import ModalFormFactura from './Partials/ModalFormFactura';
import ModalResponderFactura from './Partials/ModalResponderFactura';
import ModalExpedienteFactura from './Partials/ModalExpedienteFactura';
import { ACCENT, BTN_PRIMARY, BTN_SECONDARY } from './Partials/facturasStyles';
import { geliaCardClass } from '../../utils/geliaTheme';

export default function Index({ auth, facturas, metricas, filtros, vendedores }) {
    const permisos = auth?.user?.permissions || [];
    const puedeCrear = permisos.includes('facturas.crear');
    const puedeExportar = permisos.includes('facturas.exportar');
    const puedeDatosFiscales = permisos.includes('facturas.gestionar_datos_fiscales');

    const [tabActiva, setTabActiva] = useState(filtros.tab || 'TODAS');
    const [modalCrear, setModalCrear] = useState(false);
    const [modalRespuesta, setModalRespuesta] = useState({ abierto: false, factura: null, estadoId: null });
    const [modalExpediente, setModalExpediente] = useState({ abierto: false, factura: null });

    const cambiarTab = (tab) => {
        setTabActiva(tab);
        router.get(route('facturas.index'), { ...filtros, tab }, { preserveState: true, replace: true });
    };

    const verificar = (factura) => {
        router.put(route('facturas.verificar', factura.id), {}, { preserveScroll: true });
    };

    const lista = facturas?.data || [];

    return (
        <AppLayout>
            <Head title="Solicitudes de Facturas" />

            <div className="space-y-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <div className="p-3 rounded-2xl theme-element border theme-border">
                                <Receipt className="w-8 h-8" style={{ color: ACCENT }} />
                            </div>
                            <div>
                                <h1 className="text-3xl font-black italic theme-text-main uppercase tracking-tighter m-0">
                                    Control de <span style={{ color: ACCENT }}>Facturas</span>_
                                </h1>
                                <p className="text-xs font-bold theme-text-muted mt-1">Módulo especializado en solicitudes fiscales</p>
                            </div>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {puedeDatosFiscales && (
                            <Link href={route('facturas.datos_fiscales.index')} className={BTN_SECONDARY}>
                                <Database className="w-4 h-4" /> Datos Fiscales
                            </Link>
                        )}
                        {puedeExportar && (
                            <a href={route('facturas.exportar', filtros)} className={BTN_SECONDARY}>
                                <Download className="w-4 h-4" /> Exportar
                            </a>
                        )}
                        {puedeCrear && (
                            <button type="button" onClick={() => setModalCrear(true)} className={BTN_PRIMARY} style={{ backgroundColor: ACCENT }}>
                                <Plus className="w-4 h-4" /> Nueva Solicitud
                            </button>
                        )}
                    </div>
                </header>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    {[
                        { label: 'Pendientes', value: metricas?.pendientes ?? 0, icon: FileSpreadsheet, color: '#f59e0b' },
                        { label: 'Respondidas hoy', value: metricas?.respondidas_hoy ?? 0, icon: Receipt, accent: true },
                        { label: 'Incorrectas', value: metricas?.incorrectas ?? 0, icon: Receipt, color: '#ef4444' },
                    ].map(({ label, value, icon: Icon, color, accent }) => (
                        <div key={label} className={geliaCardClass('rounded-2xl p-5 flex items-center gap-4')}>
                            <div className="p-3 rounded-xl theme-element border theme-border">
                                <Icon className="w-6 h-6" style={{ color: accent ? ACCENT : color }} />
                            </div>
                            <div>
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
                                <p className="text-2xl font-black theme-text-main m-0 tabular-nums">{value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                <FiltrosFacturas
                    filtros={filtros}
                    vendedores={vendedores}
                    tabActiva={tabActiva}
                    onTabChange={cambiarTab}
                />

                {lista.length === 0 ? (
                    <div className={`text-center py-16 ${geliaCardClass('rounded-2xl')}`}>
                        <Receipt className="w-12 h-12 mx-auto mb-4 theme-text-muted opacity-40" />
                        <p className="text-sm font-bold theme-text-muted">No hay solicitudes de factura en esta vista.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        {lista.map(f => (
                            <TarjetaFactura
                                key={f.id}
                                factura={f}
                                auth={auth}
                                onVerExpediente={(factura) => setModalExpediente({ abierto: true, factura })}
                                onAprobar={(factura) => setModalRespuesta({ abierto: true, factura, estadoId: 2 })}
                                onReportar={(factura) => setModalRespuesta({ abierto: true, factura, estadoId: 4 })}
                                onVerificar={verificar}
                            />
                        ))}
                    </div>
                )}

                {facturas?.links && <GeliaPaginacion paginacion={facturas} />}
            </div>

            {modalCrear && <ModalFormFactura onClose={() => setModalCrear(false)} />}
            {modalRespuesta.abierto && (
                <ModalResponderFactura
                    onClose={() => setModalRespuesta({ abierto: false, factura: null, estadoId: null })}
                    factura={modalRespuesta.factura}
                    estadoId={modalRespuesta.estadoId}
                />
            )}
            {modalExpediente.abierto && (
                <ModalExpedienteFactura
                    onClose={() => setModalExpediente({ abierto: false, factura: null })}
                    factura={modalExpediente.factura}
                />
            )}
        </AppLayout>
    );
}
