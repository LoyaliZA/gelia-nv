import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { BookOpen, Download, ArrowRight, Package } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import GeliaTituloCard from '@/Components/GeliaTituloCard';
import GeliaLogo from '@/Components/GeliaLogo';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '@/utils/geliaTheme';

export default function Index({ auth, manuales = [] }) {
    return (
        <AppLayout user={auth.user}>
            <Head title="Manuales" />

            <GeliaPageShell className="space-y-8 py-6 md:py-10">
                <GeliaTituloCard
                    eyebrow="Soporte"
                    title="Manuales"
                    titleHighlight="operativos"
                    description="Guías por módulo y cargo. Solo ves los manuales y capítulos autorizados para tu perfil."
                    icon={BookOpen}
                    aside={
                        <div className="flex items-center gap-3">
                            <GeliaLogo variant="sparkle" className="w-14 h-14" />
                        </div>
                    }
                />

                {manuales.length === 0 ? (
                    <div className={geliaCardClass('p-8 text-center')}>
                        <p className="text-sm font-bold theme-text-muted uppercase tracking-widest m-0">
                            No hay manuales disponibles para tus permisos.
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5 md:gap-6">
                        {manuales.map((m) => (
                            <article key={m.slug} className={geliaCardClass('p-6 md:p-8 flex flex-col gap-5')}>
                                <div className="flex items-start gap-4">
                                    <div
                                        className="p-3 rounded-2xl theme-element border theme-border shrink-0"
                                        aria-hidden
                                    >
                                        <Package className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                                    </div>
                                    <div className="min-w-0 space-y-2">
                                        <p className="text-[10px] font-black uppercase tracking-[0.2em] theme-text-muted m-0">
                                            {m.modulo}
                                        </p>
                                        <h2 className="text-xl md:text-2xl font-black italic uppercase tracking-tighter theme-text-main m-0 leading-none">
                                            {m.titulo}
                                        </h2>
                                        <p className="text-sm theme-text-muted m-0 leading-relaxed">
                                            {m.descripcion}
                                        </p>
                                    </div>
                                </div>

                                {m.secciones?.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {m.secciones.map((s) => (
                                            <span
                                                key={s.id}
                                                className="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg theme-element border theme-border theme-text-muted"
                                            >
                                                {s.cargo}
                                            </span>
                                        ))}
                                    </div>
                                )}

                                <div className="flex flex-wrap gap-3 mt-auto pt-2">
                                    <Link
                                        href={m.show_url || route('soporte.manuales.show', m.slug)}
                                        className={`${THEME_BTN_PRIMARY} inline-flex items-center gap-2`}
                                    >
                                        Abrir manual
                                        <ArrowRight className="w-4 h-4" />
                                    </Link>
                                    <a
                                        href={m.pdf_url || route('soporte.manuales.pdf', m.slug)}
                                        className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2`}
                                    >
                                        <Download className="w-4 h-4" />
                                        PDF
                                    </a>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}
