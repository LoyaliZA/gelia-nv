import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Building2, MapPin, ListTree, Tags, Activity, UserCheck, Map, Clock, Percent, TrendingUp, Landmark } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

// Importamos los nuevos sub-componentes (Crea esta carpeta en el siguiente paso)
import TablaDepartamentos from './Partials/Catalogos/TablaDepartamentos';
import TablaAreas from './Partials/Catalogos/TablaAreas';
import TablaProcesos from './Partials/Catalogos/TablaProcesos';
import TablaListas from './Partials/Catalogos/TablaListas';
import TablaEstados from './Partials/Catalogos/TablaEstados';
import TablaTipoClientes from './Partials/Catalogos/TablaTipoClientes';
import TablaZonasEntrega from './Partials/Catalogos/TablaZonasEntrega';
import TablaHorariosEntrega from './Partials/Catalogos/TablaHorariosEntrega';
import TablaPorcentajesEscalonamiento from './Partials/Catalogos/TablaPorcentajesEscalonamiento';
import TablaPorcentajesListado from './Partials/Catalogos/TablaPorcentajesListado';
import TablaBancos from './Partials/Catalogos/TablaBancos';


export default function Catalogos({ auth, procesos, listas, estados, departamentos, areas, tipos_cliente, zonas_entrega, horarios_entrega, porcentajes_escalonamiento = [], porcentajes_listado = [], bancos = [] }) {
    const [tabActiva, setTabActiva] = useState('departamentos');
    const [glassEffect] = useState(() => localStorage.getItem('theme_glass') !== 'false');

    const activeCardClass = `fade-up rounded-[2.5rem] relative z-10 transition-all duration-300 ${glassEffect ? 'theme-surface theme-border border shadow-lg' : 'bg-white dark:bg-[#121212] border border-zinc-200 dark:border-zinc-800 shadow-sm'}`;

    const tabs = [
        { id: 'tipos_cliente', label: 'Tipos Cliente', icon: UserCheck }, // <-- Añadido
        { id: 'departamentos', label: 'Departamentos', icon: Building2 },
        { id: 'areas',         label: 'Áreas',         icon: MapPin },
        { id: 'procesos',      label: 'Procesos',      icon: ListTree },
        { id: 'listas',        label: 'Listas',        icon: Tags },
        { id: 'porcentajes_escalonamiento', label: 'Escalonamiento', icon: TrendingUp },
        { id: 'porcentajes_listado', label: 'Listados', icon: Percent },
        { id: 'estados',       label: 'Estados',       icon: Activity },
        { id: 'bancos',        label: 'Bancos',        icon: Landmark },
        { id: 'zonas_entrega', label: 'Zonas Logísticas', icon: Map },
        { id: 'horarios_entrega', label: 'Horarios Entrega', icon: Clock }
    ];

    return (
        <AppLayout auth={auth}>
            <Head title="Catálogos | GELIANV" />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">

                {/* HEADER */}
                <header className={`${activeCardClass} p-8 md:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-8`}>
                    <div className="space-y-3">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-1.5 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted drop-shadow-sm">
                                Estructura de Datos_
                            </span>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0">
                            GESTIÓN DE <span style={{ color: 'var(--color-primario)' }}>CATÁLOGOS</span>
                        </h1>
                    </div>
                </header>

                {/* TABS SELECTOR */}
                <div className={`${activeCardClass} p-2 flex flex-wrap gap-2`}>
                    {tabs.map((tab) => (
                        <button
                            key={tab.id} onClick={() => setTabActiva(tab.id)}
                            className={`flex-1 flex items-center justify-center gap-2 py-4 rounded-[1.5rem] text-[10px] font-black uppercase tracking-widest transition-all outline-none min-w-[120px] ${tabActiva === tab.id ? 'text-white shadow-lg' : 'theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5'}`}
                            style={tabActiva === tab.id ? { backgroundColor: 'var(--color-primario)' } : {}}
                        >
                            <tab.icon className="w-4 h-4" /> {tab.label}
                        </button>
                    ))}
                </div>

                {/* CONTENEDOR DINÁMICO DE TABLAS */}
                <section className={`${activeCardClass} overflow-hidden`}>
                    {tabActiva === 'departamentos' && <TablaDepartamentos datos={departamentos} />}
                    {tabActiva === 'areas' && <TablaAreas datos={areas} departamentos={departamentos} />}
                    {tabActiva === 'procesos' && <TablaProcesos datos={procesos} />}
                    {tabActiva === 'listas' && <TablaListas datos={listas} />}
                    {tabActiva === 'porcentajes_escalonamiento' && <TablaPorcentajesEscalonamiento datos={porcentajes_escalonamiento} listas={listas} />}
                    {tabActiva === 'porcentajes_listado' && <TablaPorcentajesListado datos={porcentajes_listado} listas={listas} />}
                    {tabActiva === 'estados' && <TablaEstados datos={estados} />}
                    {tabActiva === 'bancos' && <TablaBancos datos={bancos} />}
                    {tabActiva === 'tipos_cliente' && <TablaTipoClientes datos={tipos_cliente} />}
                    {tabActiva === 'zonas_entrega' && <TablaZonasEntrega datos={zonas_entrega} auth={auth} />}
                    {tabActiva === 'horarios_entrega' && <TablaHorariosEntrega datos={horarios_entrega} zonas_entrega={zonas_entrega} auth={auth} />}
                </section>

            </div>
        </AppLayout>
    );
}