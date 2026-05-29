import React, { useRef, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import { Palette, Volume2, ImageIcon, Layers, Plus } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../utils/geliaTheme';
import TablaTonos from './Partials/Personalizacion/TablaTonos';
import TablaFondos from './Partials/Personalizacion/TablaFondos';
import TablaTemas from './Partials/Personalizacion/TablaTemas';

const TABS = [
    { id: 'tonos', label: 'Tonos', icon: Volume2 },
    { id: 'fondos', label: 'Fondos', icon: ImageIcon },
    { id: 'temas', label: 'Temas', icon: Layers },
];

const SECCION_META = {
    tonos: {
        titulo: 'Tonos de notificación',
        subtitulo: 'Archivos de audio para alertas del sistema',
        cta: 'Subir tono',
    },
    fondos: {
        titulo: 'Fondos predeterminados',
        subtitulo: 'Vectores del sistema e imágenes para el perfil',
        cta: 'Nuevo fondo',
    },
    temas: {
        titulo: 'Temas predefinidos',
        subtitulo: 'Plantillas visuales disponibles en el perfil',
        cta: 'Nuevo tema',
    },
};

export default function GestorDePersonalizacion({
    seccion = 'tonos',
    catalogo = {},
    conteos = { tonos: 0, fondos: 0, temas: 0 },
    fondos_opciones = [],
}) {
    const abrirModalRef = useRef(null);
    const meta = SECCION_META[seccion] || SECCION_META.tonos;
    const totalSeccion = conteos[seccion] ?? catalogo?.total ?? 0;

    const cambiarSeccion = (id) => {
        if (id === seccion) return;
        router.get(
            route('admin.personalizacion.index'),
            { seccion: id, page: 1 },
            { preserveState: true, replace: true }
        );
    };

    const irAPagina = useCallback(
        (pagina) => {
            if (pagina < 1 || pagina > (catalogo?.last_page || 1)) return;
            router.get(
                route('admin.personalizacion.index'),
                { seccion, page: pagina },
                { preserveState: true, preserveScroll: true }
            );
        },
        [seccion, catalogo?.last_page]
    );

    const cardPrincipal = geliaCardClass('overflow-hidden relative z-10');

    return (
        <AppLayout>
            <Head title="Personalización | GELIANV" />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className={`${geliaCardClass()} p-6 md:p-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6`}>
                    <div className="min-w-0 space-y-2">
                        <div className="flex items-center gap-3">
                            <span className="h-1.5 w-12 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>
                                Experiencia de usuario
                            </p>
                        </div>
                        <h1 className="text-2xl sm:text-3xl md:text-5xl font-black italic uppercase tracking-tighter theme-text-main m-0 leading-none">
                            Personalización <span style={{ color: 'var(--color-primario)' }}>global</span>
                        </h1>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest max-w-2xl leading-relaxed m-0">
                            Tonos, fondos y temas que los colaboradores pueden elegir en su perfil.
                        </p>
                    </div>
                    <div className="p-4 rounded-2xl theme-element border theme-border shrink-0">
                        <Palette className="w-8 h-8" style={{ color: 'var(--color-primario)' }} aria-hidden />
                    </div>
                </header>

                <div className={cardPrincipal}>
                    <nav className="gelia-nav-tabs" aria-label="Secciones del catálogo">
                        {TABS.map((tab) => (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => cambiarSeccion(tab.id)}
                                className="gelia-nav-tab"
                                data-active={seccion === tab.id}
                                aria-current={seccion === tab.id ? 'page' : undefined}
                            >
                                <tab.icon className="w-4 h-4 shrink-0" aria-hidden />
                                {tab.label}
                                <span className="gelia-nav-tab__badge">{conteos[tab.id] ?? 0}</span>
                            </button>
                        ))}
                    </nav>

                    <div className="p-5 md:p-8 border-b theme-border flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div className="min-w-0">
                            <h2 className="text-lg md:text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 leading-tight">
                                {meta.titulo}
                            </h2>
                            <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-1.5 m-0">
                                {meta.subtitulo}
                                {totalSeccion > 0 && (
                                    <span className="block sm:inline sm:ml-2 mt-1 sm:mt-0 opacity-80">
                                        · {totalSeccion.toLocaleString('es-MX')} en catálogo
                                    </span>
                                )}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => abrirModalRef.current?.()}
                            className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact w-full sm:w-auto shrink-0`}
                        >
                            <Plus className="w-4 h-4 shrink-0" aria-hidden />
                            {meta.cta}
                        </button>
                    </div>

                    <div key={seccion} className="personalizacion-reveal">
                        {seccion === 'tonos' && (
                            <TablaTonos catalogo={catalogo} registrarAbrir={abrirModalRef} />
                        )}
                        {seccion === 'fondos' && (
                            <TablaFondos catalogo={catalogo} registrarAbrir={abrirModalRef} />
                        )}
                        {seccion === 'temas' && (
                            <TablaTemas catalogo={catalogo} fondos_opciones={fondos_opciones} registrarAbrir={abrirModalRef} />
                        )}
                    </div>

                    <GeliaPaginacion paginator={catalogo} onIrAPagina={irAPagina} embedded />
                </div>
            </div>
        </AppLayout>
    );
}
