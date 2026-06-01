import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, Users, Clock, Settings, AlertTriangle, CalendarDays, Wallet } from 'lucide-react';

const TABS = [
    { id: 'dashboard', label: 'Dashboard', route: 'rh.index', icon: LayoutDashboard, permiso: 'rh.ver' },
    { id: 'colaboradores', label: 'Colaboradores', route: 'rh.colaboradores.index', icon: Users, permiso: 'rh.ver' },
    { id: 'horas_extra', label: 'Horas Extra', route: 'rh.horas_extra.index', icon: Clock, permiso: 'rh.horas_extra.ver' },
    { id: 'deducciones', label: 'Deducciones', route: 'rh.deducciones.index', icon: AlertTriangle, permiso: 'rh.incidencias.ver' },
    { id: 'prestamos', label: 'Préstamos', route: 'rh.prestamos.index', icon: Wallet, permiso: 'rh.prestamos.ver' },
    { id: 'periodo_pago', label: 'Periodo Pago', route: 'rh.periodo_pago.index', icon: CalendarDays, permiso: 'rh.ver' },
    { id: 'configuracion', label: 'Configuración', route: 'rh.configuracion', icon: Settings, permiso: 'rh.configurar' },
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
        return currentUrl.startsWith('/rh/deducciones') || currentUrl.startsWith('/rh/incidencias');
    }
    if (tab.id === 'prestamos') {
        return currentUrl.startsWith('/rh/prestamos-pagos-fijos');
    }
    if (tab.id === 'periodo_pago') {
        return currentUrl.startsWith('/rh/periodo-pago');
    }
    if (tab.id === 'configuracion') {
        return currentUrl.startsWith('/rh/configuracion');
    }
    return false;
}

export default function RhSubNav() {
    const { url, props } = usePage();
    const permisos = props.auth?.user?.permissions || [];
    const roles = props.auth?.user?.roles || [];

    const can = (permiso) => permisos.includes(permiso) || roles.includes('Super Admin');

    const visibles = TABS.filter((tab) => can(tab.permiso));

    if (visibles.length <= 1) return null;

    return (
        <nav className="flex flex-wrap gap-2 p-1 rounded-2xl theme-element border theme-border">
            {visibles.map((tab) => {
                const activa = tabActiva(url, tab);
                const Icon = tab.icon;
                return (
                    <Link
                        key={tab.id}
                        href={route(tab.route)}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all ${
                            activa ? 'text-white shadow-sm' : 'theme-text-muted hover:theme-text-main'
                        }`}
                        style={activa ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        <Icon className="w-3.5 h-3.5" />
                        {tab.label}
                    </Link>
                );
            })}
        </nav>
    );
}
