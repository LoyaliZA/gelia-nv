import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Globe, Key, Shield, History, FileText, Layers } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { geliaCardClass } from '../../utils/geliaTheme';
import TabAplicaciones from './Partials/ApiExterna/TabAplicaciones';
import TabRecursosCampos from './Partials/ApiExterna/TabRecursosCampos';
import TabPermisosApp from './Partials/ApiExterna/TabPermisosApp';
import TabAuditoria from './Partials/ApiExterna/TabAuditoria';
import TabDocumentacion from './Partials/ApiExterna/TabDocumentacion';

export default function ApiExterna({
    auth,
    puedeGestionar = true,
    aplicaciones = [],
    recursos = [],
    auditorias = [],
    documentacion = {},
    filtros = {},
    baseUrl = '',
    credenciales_nuevas = null,
    secret_regenerado = null,
}) {
    const [tabActiva, setTabActiva] = useState(puedeGestionar ? 'aplicaciones' : 'auditoria');
    const activeCardClass = geliaCardClass('relative z-10');

    const tabs = [
        ...(puedeGestionar ? [
            { id: 'aplicaciones', label: 'Aplicaciones', icon: Key },
            { id: 'recursos', label: 'Recursos y Campos', icon: Layers },
            { id: 'permisos', label: 'Permisos por App', icon: Shield },
            { id: 'documentacion', label: 'Documentación', icon: FileText },
        ] : []),
        { id: 'auditoria', label: 'Auditoría', icon: History },
    ];

    return (
        <AppLayout auth={auth}>
            <Head title="API Externa | GELIANV" />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">
                <header className={`${activeCardClass} p-8 md:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-6`}>
                    <div className="space-y-3">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-1.5 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted drop-shadow-sm">
                                Integraciones Externas_
                            </span>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0">
                            API <span style={{ color: 'var(--color-primario)' }}>EXTERNA</span>
                        </h1>
                        <p className="text-sm theme-text-muted flex items-center gap-2">
                            <Globe className="w-4 h-4" />
                            Base URL: <code className="text-xs">{baseUrl}</code>
                        </p>
                    </div>
                </header>

                <div className={`${activeCardClass} p-2 flex flex-wrap gap-2`}>
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setTabActiva(tab.id)}
                            className={`flex-1 flex items-center justify-center gap-2 py-4 rounded-[1.5rem] text-[10px] font-black uppercase tracking-widest transition-all outline-none min-w-[140px] ${tabActiva === tab.id ? 'text-white shadow-lg' : 'theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5'}`}
                            style={tabActiva === tab.id ? { backgroundColor: 'var(--color-primario)' } : {}}
                        >
                            <tab.icon className="w-4 h-4" /> {tab.label}
                        </button>
                    ))}
                </div>

                <section className={`${activeCardClass} overflow-hidden p-6 md:p-8`}>
                    {tabActiva === 'aplicaciones' && (
                        <TabAplicaciones
                            aplicaciones={aplicaciones}
                            credenciales_nuevas={credenciales_nuevas}
                            secret_regenerado={secret_regenerado}
                        />
                    )}
                    {tabActiva === 'recursos' && <TabRecursosCampos recursos={recursos} />}
                    {tabActiva === 'permisos' && (
                        <TabPermisosApp aplicaciones={aplicaciones} recursos={recursos} />
                    )}
                    {tabActiva === 'auditoria' && (
                        <TabAuditoria auditorias={auditorias} aplicaciones={aplicaciones} filtros={filtros} />
                    )}
                    {tabActiva === 'documentacion' && (
                        <TabDocumentacion documentacion={documentacion} baseUrl={baseUrl} />
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
