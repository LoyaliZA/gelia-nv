import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { geliaCardClass } from '../../utils/geliaTheme';
import { FileSpreadsheet, Settings } from 'lucide-react';
import GeneradorBellaroma from './Partials/GeneradorBellaroma';
import HistorialBellaroma from './Partials/HistorialBellaroma';
import ConfiguracionBellaroma from './Partials/ConfiguracionBellaroma';

export default function Index({ auth, templatesHoy, templatesHistorial, notifiedUserIds, users, permisos }) {
    const [showConfig, setShowConfig] = useState(false);
    const [localTemplatesHoy, setLocalTemplatesHoy] = useState(templatesHoy);

    const onTemplateGenerado = (nuevoTemplate) => {
        setLocalTemplatesHoy([nuevoTemplate, ...localTemplatesHoy]);
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Plantilla Bellaroma" />

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                {/* Cabecera */}
                <header className={`${geliaCardClass()} p-6 md:p-10 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 border-b-[4px] border-[#8a2be2]`}>
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: '#8a2be2' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: '#8a2be2' }}>Gestión Interna</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            PLANTILLA <span style={{ color: '#8a2be2' }}>BELLAROMA</span>
                        </h1>
                        <p className="theme-text-muted mt-2 text-sm font-medium">Generación de lista de precios estructurada para Bellaroma.</p>
                    </div>
                    
                    {permisos.configurar && (
                        <button 
                            onClick={() => setShowConfig(true)}
                            className="px-6 py-3 rounded-xl border border-gray-300 dark:border-gray-700 hover:border-[#8a2be2] hover:text-[#8a2be2] font-black text-xs tracking-widest uppercase transition-all flex items-center gap-2"
                        >
                            <Settings className="w-4 h-4" /> Configuración
                        </button>
                    )}
                </header>

                <div className="flex flex-col lg:flex-row gap-6 lg:gap-8">
                    {/* Generador (Formulario) */}
                    {permisos.generar && (
                        <div className="w-full lg:w-1/2">
                            <GeneradorBellaroma onSuccess={onTemplateGenerado} />
                        </div>
                    )}

                    {/* Historial */}
                    {permisos.visualizar && (
                        <div className={`w-full ${permisos.generar ? 'lg:w-1/2' : ''} flex flex-col gap-6`}>
                            <HistorialBellaroma 
                                titulo="Generadas Hoy" 
                                templates={localTemplatesHoy} 
                                permisos={permisos}
                            />
                            <HistorialBellaroma 
                                titulo="Historial Anterior" 
                                templates={templatesHistorial} 
                                permisos={permisos}
                            />
                        </div>
                    )}
                </div>
            </div>

            {/* Modal Configuración */}
            {showConfig && permisos.configurar && (
                <ConfiguracionBellaroma 
                    onClose={() => setShowConfig(false)} 
                    users={users} 
                    notifiedUserIds={notifiedUserIds} 
                />
            )}
        </AppLayout>
    );
}
