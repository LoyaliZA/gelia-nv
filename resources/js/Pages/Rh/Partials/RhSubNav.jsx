import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, Users, Clock, Settings, AlertTriangle } from 'lucide-react';

const TABS = [
    { id: 'dashboard', label: 'Dashboard', route: 'rh.index', icon: LayoutDashboard, permiso: 'rh.ver' },
    { id: 'colaboradores', label: 'Colaboradores', route: 'rh.colaboradores.index', icon: Users, permiso: 'rh.ver' },
    { id: 'horas_extra', label: 'Horas Extra', route: 'rh.horas_extra.index', icon: Clock, permiso: 'rh.horas_extra.ver' },
    { id: 'incidencias', label: 'Incidencias', route: 'rh.incidencias.index', icon: AlertTriangle, permiso: 'rh.incidencias.ver' },
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
    if (tab.id === 'incidencias') {
        return currentUrl.startsWith('/rh/incidencias');
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
