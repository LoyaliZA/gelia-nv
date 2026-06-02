import React, { useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Settings, ChevronRight } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import {
    ADMIN_MODULES,
    adminModuleHref,
    isAdminModuleAllowed,
} from '../../config/adminModules';
import { geliaCardClass, GELIA_ADMIN_HUB_GRID } from '../../utils/geliaTheme';

const MODULE_CARD_CLASS =
    'group theme-element border-2 theme-border rounded-[1.5rem] p-6 sm:p-7 shadow-sm transition-all duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] flex flex-col min-h-[11rem] outline-none hover:border-[var(--color-primario)] hover:shadow-lg hover:-translate-y-0.5 focus-visible:border-[var(--color-primario)] focus-visible:ring-2 focus-visible:ring-[var(--color-primario)]/30';

export default function AdminIndex({ auth }) {
    const can = (permission) =>
        auth?.user?.roles?.includes('Super Admin') ||
        auth?.user?.permissions?.includes(permission);

    const visibleModules = useMemo(
        () => ADMIN_MODULES.filter((item) => isAdminModuleAllowed(item, can)),
        [auth?.user?.permissions, auth?.user?.roles]
    );

    return (
        <AppLayout>
            <Head title="Administración" />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">
                <GeliaTituloCard
                    eyebrow="Panel central_"
                    title="PANEL DE"
                    titleHighlight="ADMINISTRACIÓN"
                    description="Accede a los módulos de configuración del sistema. Solo verás las opciones permitidas para tu rol."
                    icon={Settings}
                />

                {visibleModules.length === 0 ? (
                    <div className={geliaCardClass('p-10 text-center')}>
                        <p className="text-sm font-bold theme-text-muted italic m-0">
                            No tienes módulos de administración asignados.
                        </p>
                    </div>
                ) : (
                    <div className={GELIA_ADMIN_HUB_GRID}>
                        {visibleModules.map((module) => {
                            const Icon = module.icon;
                            return (
                                <Link
                                    key={module.id}
                                    href={adminModuleHref(module)}
                                    className={MODULE_CARD_CLASS}
                                >
                                    <div className="w-12 h-12 theme-element border theme-border rounded-2xl flex items-center justify-center mb-5 transition-colors duration-300 group-hover:border-[var(--color-primario)]">
                                        <Icon
                                            className="w-5 h-5 theme-text-main transition-colors duration-300"
                                            style={{ color: 'var(--color-primario)' }}
                                        />
                                    </div>
                                    <h2 className="text-base sm:text-lg font-black uppercase italic tracking-tighter theme-text-main mb-2">
                                        {module.title}
                                    </h2>
                                    <p className="text-[11px] sm:text-xs font-bold theme-text-muted italic leading-relaxed flex-1">
                                        {module.description}
                                    </p>
                                    <span className="mt-5 inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest theme-text-muted group-hover:theme-text-main transition-colors">
                                        Abrir módulo
                                        <ChevronRight className="w-3.5 h-3.5 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:text-[var(--color-primario)]" />
                                    </span>
                                </Link>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
