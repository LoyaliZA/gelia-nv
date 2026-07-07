import React, { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { geliaCardClass } from '../../utils/geliaTheme';
import { Settings, ClipboardList, AlertTriangle } from 'lucide-react';
import GeneradorSync from './Partials/GeneradorSync';
import SincronizarCatalogo from './Partials/SincronizarCatalogo';
import PanelInstruccionesExportacion from './Partials/PanelInstruccionesExportacion';
import HistorialTemplates from './Partials/HistorialTemplates';
import TablaProductos from './Partials/TablaProductos';
import ModalConfiguracion from './Partials/ModalConfiguracion';
import PanelProcesosFantasma from './Partials/PanelProcesosFantasma';
import { startWooSyncTracking } from '../../utils/woocommerceSyncTracker';

export default function Index({
    auth, templatesHoy, templatesHistorial, configuracion, margenes, productos,
    filters, users, permisos, procesoActivo, procesosFantasma,
}) {
    const [tab, setTab] = useState('precios');
    const [showConfig, setShowConfig] = useState(false);

    useEffect(() => {
        if (procesoActivo?.id) {
            startWooSyncTracking(procesoActivo.id);
        }
    }, [procesoActivo?.id]);

    const tabs = [
        { id: 'precios', label: 'Sincronizar Precios' },
        { id: 'catalogo', label: 'Estructura Catálogo' },
    ];

    return (
        <AppLayout auth={auth}>
            <Head title="Sincronizar Precios" />

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className={`${geliaCardClass()} p-6 md:p-10 flex flex-col md:flex-row items-center justify-between gap-4 border-b-[4px]`} style={{ borderColor: 'var(--color-primario)' }}>
                    <div className="text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>WooCommerce</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            SINCRONIZAR <span style={{ color: 'var(--color-primario)' }}>PRECIOS</span>
                        </h1>
                        <p className="theme-text-muted mt-2 text-sm">Catálogo, precios Wizerp y envío a la tienda en línea.</p>
                    </div>
                    <div className="flex flex-wrap gap-2 justify-center">
                        {permisos.auditoria && (
                            <Link href={route('woocommerce.auditoria')} className="px-4 py-2 rounded-xl border theme-border bg-white dark:bg-zinc-900 text-[10px] font-black uppercase tracking-widest flex items-center gap-2 theme-text-main hover:bg-gray-50 dark:hover:bg-zinc-800 hover:border-[var(--color-primario)] transition-all">
                                <ClipboardList className="w-4 h-4" /> Auditoría
                            </Link>
                        )}
                        <Link href={route('woocommerce.alertas')} className="px-4 py-2 rounded-xl border theme-border bg-white dark:bg-zinc-900 text-[10px] font-black uppercase tracking-widest flex items-center gap-2 theme-text-main hover:bg-gray-50 dark:hover:bg-zinc-800 hover:border-[var(--color-primario)] transition-all">
                            <AlertTriangle className="w-4 h-4" /> Alertas
                        </Link>
                        {permisos.configurar && (
                            <button onClick={() => setShowConfig(true)} className="px-4 py-2 rounded-xl border theme-border bg-white dark:bg-zinc-900 text-[10px] font-black uppercase tracking-widest flex items-center gap-2 theme-text-main hover:bg-gray-50 dark:hover:bg-zinc-800 hover:border-[var(--color-primario)] transition-all">
                                <Settings className="w-4 h-4" /> Configuración
                            </button>
                        )}
                    </div>
                </header>

                <PanelProcesosFantasma procesosFantasma={procesosFantasma} permisos={permisos} />

                <div className="inline-flex gap-1 p-1.5 rounded-2xl bg-white dark:bg-zinc-900 border theme-border shadow-sm">
                    {tabs.map((t) => (
                        <button key={t.id} onClick={() => setTab(t.id)}
                            className={`px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all ${tab === t.id ? 'text-white shadow-md' : 'theme-text-muted hover:bg-zinc-100 dark:hover:bg-zinc-800'}`}
                            style={tab === t.id ? { backgroundColor: 'var(--color-primario)' } : {}}>
                            {t.label}
                        </button>
                    ))}
                </div>

                {tab === 'precios' && (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <GeneradorSync permisos={permisos} configuracion={configuracion} margenes={margenes} />
                        <HistorialTemplates templatesHoy={templatesHoy} templatesHistorial={templatesHistorial} permisos={permisos} />
                    </div>
                )}

                {tab === 'catalogo' && (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div className="space-y-6">
                            <PanelInstruccionesExportacion />
                            <SincronizarCatalogo permisos={permisos} configuracion={configuracion} />
                        </div>
                        <TablaProductos productos={productos} filters={filters} permisos={permisos} configuracion={configuracion} />
                    </div>
                )}

                {tab === 'precios' && <TablaProductos productos={productos} filters={filters} permisos={permisos} configuracion={configuracion} />}
            </div>

            {showConfig && (
                <ModalConfiguracion configuracion={configuracion} margenes={margenes} users={users} onClose={() => { setShowConfig(false); window.location.reload(); }} />
            )}
        </AppLayout>
    );
}
