import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Users,
    Clock,
    Settings,
    AlertTriangle,
    CalendarDays,
    Wallet,
    LogOut,
    FileSpreadsheet,
    Coins,
    TimerReset,
} from 'lucide-react';
import RhSectionLabel from './RhSectionLabel';

const TABS = [
    { id: 'dashboard', label: 'Dashboard', route: 'rh.index', icon: LayoutDashboard, permiso: 'rh.ver', group: 'principal' },
    { id: 'colaboradores', label: 'Colaboradores', route: 'rh.colaboradores.index', icon: Users, permiso: 'rh.ver', group: 'principal' },
    { id: 'horas_extra', label: 'Horas Extra', route: 'rh.horas_extra.index', icon: Clock, permiso: 'rh.horas_extra.ver', group: 'principal' },
    { id: 'deducciones', label: 'Deducciones', route: 'rh.deducciones.incidencias.index', icon: AlertTriangle, permiso: 'rh.incidencias.ver', group: 'principal' },
    { id: 'incidencias_gerente', label: 'Mis Incidencias', route: 'rh.incidencias_gerente.index', icon: AlertTriangle, permiso: 'rh.incidencias.gerente.ver', group: 'principal' },
    { id: 'prestamos', label: 'Préstamos', route: 'rh.prestamos.index', icon: Wallet, permiso: 'rh.prestamos.ver', group: 'nomina' },
    { id: 'salidas_personales', label: 'Salidas', route: 'rh.salidas_personales.index', icon: LogOut, permiso: 'rh.salidas_personales.ver', group: 'nomina' },
    { id: 'banco_tiempo', label: 'Banco Tiempo', route: 'rh.banco_tiempo.index', icon: TimerReset, permiso: 'rh.banco_tiempo.ver', group: 'nomina' },
    { id: 'consolidado_deducciones', label: 'Pre-Nómina', route: 'rh.consolidado_deducciones.index', icon: FileSpreadsheet, permiso: 'rh.ver', group: 'nomina' },
    { id: 'consolidado_horas_extra', label: 'Consolidado HE', route: 'rh.consolidado_horas_extra.index', icon: Coins, permiso: 'rh.ver', group: 'nomina' },
    { id: 'periodo_pago', label: 'Periodo Pago', route: 'rh.periodo_pago.index', icon: CalendarDays, permiso: 'rh.ver', group: 'nomina' },
    { id: 'configuracion', label: 'Configuración', route: 'rh.configuracion', icon: Settings, permiso: 'rh.configurar', group: 'ajustes' },
];

const GROUPS = [
    { id: 'principal', label: 'Operación' },
    { id: 'nomina', label: 'Nómina' },
    { id: 'ajustes', label: 'Ajustes' },
];

function tabActiva(currentUrl, tab) {
    if (tab.id === 'dashboard') {
        return currentUrl === '/rh' || currentUrl === '/rh/';
    }
    if (tab.id === 'colaboradores') {
        return currentUrl.startsWith('/rh/colaboradores');
    }
    if (tab.id === 'horas_extra') {
        return currentUrl.startsWith('/rh/horas-extra');
    }
    if (tab.id === 'deducciones') {
        return currentUrl.startsWith('/rh/deducciones');
    }
    if (tab.id === 'incidencias_gerente') {
        return currentUrl.startsWith('/rh/incidencias-gerente');
    }
    if (tab.id === 'prestamos') {
        return currentUrl.startsWith('/rh/prestamos-pagos-fijos');
    }
    if (tab.id === 'salidas_personales') {
        return currentUrl.startsWith('/rh/salidas-personales');
    }
    if (tab.id === 'banco_tiempo') {
        return currentUrl.startsWith('/rh/banco-tiempo');
    }
    if (tab.id === 'consolidado_deducciones') {
        return currentUrl.startsWith('/rh/consolidado-deducciones');
    }
    if (tab.id === 'consolidado_horas_extra') {
        return currentUrl.startsWith('/rh/consolidado-horas-extra');
    }
    if (tab.id === 'periodo_pago') {
        return currentUrl.startsWith('/rh/periodo-pago');
    }
    if (tab.id === 'configuracion') {
        return currentUrl.startsWith('/rh/configuracion');
    }
    return false;
}

function renderTabLink(tab, url) {
    const activa = tabActiva(url, tab);
    const Icon = tab.icon;
    return (
        <Link
            key={tab.id}
            href={route(tab.route)}
            className={`inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all shrink-0 ${
                activa ? 'text-white shadow-sm' : 'theme-text-muted hover:theme-text-main hover:bg-black/[0.03] dark:hover:bg-white/[0.04]'
            }`}
            style={activa ? { backgroundColor: 'var(--color-primario)' } : {}}
        >
            <Icon className="w-3.5 h-3.5 shrink-0" />
            {tab.label}
        </Link>
    );
}

export default function RhSubNav() {
    const { url, props } = usePage();
    const permisos = props.auth?.user?.permissions || [];
    const roles = props.auth?.user?.roles || [];

    const can = (permiso) => permisos.includes(permiso) || roles.includes('Super Admin');

    const visibles = TABS.filter((tab) => can(tab.permiso));

    if (visibles.length <= 1) return null;

    const tabsByGroup = GROUPS.map((group) => ({
        ...group,
        tabs: visibles.filter((tab) => tab.group === group.id),
    })).filter((g) => g.tabs.length > 0);

    return (
        <nav aria-label="Sub-navegación Recursos Humanos" className="overflow-x-auto custom-scrollbar pb-1 -mx-1 px-1">
            <div className="flex flex-col lg:flex-row lg:flex-wrap lg:items-end gap-5 lg:gap-6 min-w-min">
                {tabsByGroup.map((group, index) => (
                    <div
                        key={group.id}
                        className={`flex flex-col min-w-0 shrink-0 ${
                            index > 0 ? 'lg:pl-6 lg:border-l theme-border' : ''
                        }`}
                    >
                        {group.label && <RhSectionLabel>{group.label}</RhSectionLabel>}
                        <div
                            className={`gelia-rh-subnav-tabs flex flex-wrap gap-1.5 p-2 rounded-2xl theme-element border theme-border ${
                                group.label ? 'gelia-rh-subnav-tabs--with-label' : ''
                            }`}
                        >
                            {group.tabs.map((tab) => renderTabLink(tab, url))}
                        </div>
                    </div>
                ))}
            </div>
        </nav>
    );
}
