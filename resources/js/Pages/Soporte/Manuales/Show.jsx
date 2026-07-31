import React, { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, BookOpen } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import GeliaTituloCard from '@/Components/GeliaTituloCard';
import GeliaLogo from '@/Components/GeliaLogo';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, isGlassEffectEnabled } from '@/utils/geliaTheme';
import ControlPedidosManualView from './content/control-pedidos/ControlPedidosManualView';

const CONTENT_BY_SLUG = {
    'control-pedidos': ControlPedidosManualView,
};

const TOC_TOP_GAP = 24;

/** Card del índice sin animate/transform. */
function tocCardClass(extra = '') {
    const solid = isGlassEffectEnabled() ? '' : ' theme-card--solid';
    return `theme-surface theme-card border theme-border${solid} ${extra}`.trim();
}

function buildToc(secciones, tieneErrores, tieneEjemplos) {
    const toc = [
        { id: 'overview', label: 'Resumen', href: '#sec-overview' },
        { id: 'flujo', label: 'Diagrama maestro', href: '#sec-flujo' },
        ...secciones.map((s) => ({ id: s.id, label: s.cargo, href: `#sec-${s.id}` })),
        { id: 'estatus', label: 'Estatus', href: '#sec-estatus' },
    ];
    if (tieneErrores) toc.push({ id: 'errores', label: 'Alertas', href: '#sec-errores' });
    if (tieneEjemplos) toc.push({ id: 'ejemplos', label: 'Ejemplos', href: '#sec-ejemplos' });
    return toc;
}

function tocItemStyle(activo) {
    if (activo) {
        return {
            backgroundColor: 'color-mix(in srgb, var(--color-primario) 12%, transparent)',
            color: 'var(--color-primario)',
            borderColor: 'color-mix(in srgb, var(--color-primario) 40%, transparent)',
        };
    }
    return {
        color: 'var(--theme-text-muted)',
        borderColor: 'var(--theme-border)',
        backgroundColor: 'transparent',
    };
}

/**
 * Índice lateral: al fijarse se porta a document.body para escapar
 * el transform de AppLayout (.animate-page-reveal), que rompe position:fixed.
 */
function ManualTocDesktop({ toc, activo, onSelect, pie }) {
    const slotRef = useRef(null);
    const navRef = useRef(null);
    const [fixed, setFixed] = useState(null);
    const [spacerH, setSpacerH] = useState(0);

    const sync = useCallback(() => {
        const slot = slotRef.current;
        const nav = navRef.current;
        if (!slot || !nav) return;

        const slotCs = window.getComputedStyle(slot);
        const slotRect = slot.getBoundingClientRect();
        const navH = nav.offsetHeight || spacerH || 400;
        const endEl = slot.parentElement?.querySelector('[data-manual-content]');
        const endBottom = endEl
            ? endEl.getBoundingClientRect().bottom
            : Number.POSITIVE_INFINITY;

        // Slot oculto (móvil): no fijar ni portalar — el portal escapa de hidden xl:block.
        const slotHidden = slotCs.display === 'none';
        const willFix = !slotHidden && slotRect.top <= TOC_TOP_GAP;

        if (willFix) {
            let top = TOC_TOP_GAP;
            const maxTop = endBottom - navH - 16;
            if (Number.isFinite(maxTop) && top > maxTop) {
                top = Math.max(8, maxTop);
            }
            setFixed({
                top,
                left: slotRect.left,
                width: Math.max(slotRect.width, 220),
            });
            setSpacerH(navH);
        } else {
            setFixed(null);
            setSpacerH(0);
        }
    }, [spacerH]);

    useLayoutEffect(() => {
        let raf = 0;
        const onScrollOrResize = () => {
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(sync);
        };
        sync();
        window.addEventListener('scroll', onScrollOrResize, { passive: true, capture: true });
        window.addEventListener('resize', onScrollOrResize);
        const ro = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(onScrollOrResize) : null;
        if (ro && slotRef.current?.parentElement) ro.observe(slotRef.current.parentElement);
        return () => {
            cancelAnimationFrame(raf);
            window.removeEventListener('scroll', onScrollOrResize, { capture: true });
            window.removeEventListener('resize', onScrollOrResize);
            ro?.disconnect();
        };
    }, [sync, toc]);

    const navNode = (
        <nav
            ref={navRef}
            aria-label="Índice del manual"
            className={`${tocCardClass('p-4')} ${fixed ? 'shadow-lg' : ''}`.trim()}
            style={{
                maxHeight: 'calc(100dvh - 3rem)',
                overflowY: 'auto',
                transform: 'none',
                color: 'var(--theme-text-main)',
                ...(fixed
                    ? {
                        position: 'fixed',
                        top: fixed.top,
                        left: fixed.left,
                        width: fixed.width,
                        zIndex: 40,
                    }
                    : undefined),
            }}
        >
            <p
                className="text-[10px] font-black uppercase tracking-[0.2em] m-0 mb-3"
                style={{ color: 'var(--theme-text-muted)' }}
            >
                Índice
            </p>
            <ul className="m-0 p-0 list-none space-y-1">
                {toc.map((t) => (
                    <li key={t.id}>
                        <button
                            type="button"
                            onClick={() => onSelect(t.id, t.href)}
                            className="w-full text-left text-xs font-bold px-2.5 py-2 rounded-lg border border-transparent outline-none"
                            style={tocItemStyle(activo === t.id)}
                        >
                            {t.label}
                        </button>
                    </li>
                ))}
            </ul>
            {pie && (
                <p
                    className="text-[10px] m-0 mt-4 leading-relaxed"
                    style={{ color: 'var(--theme-text-muted)' }}
                >
                    {pie}
                </p>
            )}
        </nav>
    );

    return (
        <div ref={slotRef} className="hidden xl:block w-[220px] shrink-0 self-start">
            {spacerH > 0 && <div style={{ height: spacerH }} aria-hidden />}
            {fixed && typeof document !== 'undefined'
                ? createPortal(navNode, document.body)
                : navNode}
        </div>
    );
}

/** Chips móvil: mismo portal a body al fijarse. */
function ManualTocMobile({ toc, activo, onSelect }) {
    const slotRef = useRef(null);
    const barRef = useRef(null);
    const [fixed, setFixed] = useState(null);
    const [spacerH, setSpacerH] = useState(0);

    const sync = useCallback(() => {
        const slot = slotRef.current;
        const bar = barRef.current;
        if (!slot || !bar) return;
        const slotCs = window.getComputedStyle(slot);
        const rect = slot.getBoundingClientRect();
        const h = bar.offsetHeight || spacerH || 56;
        const slotHidden = slotCs.display === 'none';
        const willFix = !slotHidden && rect.top <= TOC_TOP_GAP;

        if (willFix) {
            setFixed({
                top: TOC_TOP_GAP,
                left: rect.left,
                width: rect.width,
            });
            setSpacerH(h);
        } else {
            setFixed(null);
            setSpacerH(0);
        }
    }, [spacerH]);

    useLayoutEffect(() => {
        let raf = 0;
        const tick = () => {
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(sync);
        };
        sync();
        window.addEventListener('scroll', tick, { passive: true, capture: true });
        window.addEventListener('resize', tick);
        return () => {
            cancelAnimationFrame(raf);
            window.removeEventListener('scroll', tick, { capture: true });
            window.removeEventListener('resize', tick);
        };
    }, [sync, toc]);

    const barNode = (
        <nav
            ref={barRef}
            aria-label="Índice del manual"
            className={`${tocCardClass('p-3 overflow-x-auto')} ${fixed ? 'shadow-lg' : ''}`.trim()}
            style={{
                color: 'var(--theme-text-main)',
                ...(fixed
                    ? {
                        position: 'fixed',
                        top: fixed.top,
                        left: fixed.left,
                        width: fixed.width,
                        zIndex: 40,
                        transform: 'none',
                    }
                    : { transform: 'none' }),
            }}
        >
            <div className="flex gap-2 min-w-max">
                {toc.map((t) => (
                    <button
                        key={t.id}
                        type="button"
                        onClick={() => onSelect(t.id, t.href)}
                        className="text-[10px] font-black uppercase tracking-wider px-3 py-2 rounded-lg border shrink-0 outline-none"
                        style={tocItemStyle(activo === t.id)}
                    >
                        {t.label}
                    </button>
                ))}
            </div>
        </nav>
    );

    return (
        <div ref={slotRef} className="xl:hidden">
            {spacerH > 0 && <div style={{ height: spacerH }} aria-hidden />}
            {fixed && typeof document !== 'undefined'
                ? createPortal(barNode, document.body)
                : barNode}
        </div>
    );
}

export default function Show({
    auth,
    manual,
    secciones = [],
    contenido,
    pdf_url: pdfUrl,
    seccion_inicial: seccionInicial,
}) {
    const ContentView = CONTENT_BY_SLUG[manual?.slug];
    const tieneEjemplos = secciones.length > 0;
    const toc = useMemo(
        () => buildToc(secciones, (contenido?.errores || []).length > 0, tieneEjemplos),
        [secciones, contenido, tieneEjemplos]
    );
    const [activo, setActivo] = useState(toc[0]?.id || 'overview');
    const scrollLockRef = useRef(null);

    useEffect(() => {
        if (!seccionInicial) return;
        const el = document.getElementById(`sec-${seccionInicial}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setActivo(seccionInicial);
        }
    }, [seccionInicial]);

    useEffect(() => {
        const ids = toc.map((t) => t.href.replace('#', ''));
        const nodes = ids.map((id) => document.getElementById(id)).filter(Boolean);
        if (nodes.length === 0) return undefined;

        const observer = new IntersectionObserver(
            (entries) => {
                if (scrollLockRef.current) return;
                const visible = entries
                    .filter((e) => e.isIntersecting)
                    .sort((a, b) => b.intersectionRatio - a.intersectionRatio);
                if (visible[0]?.target?.id) {
                    setActivo(visible[0].target.id.replace(/^sec-/, ''));
                }
            },
            { rootMargin: '-20% 0px -55% 0px', threshold: [0, 0.25, 0.5, 1] }
        );
        nodes.forEach((n) => observer.observe(n));
        return () => observer.disconnect();
    }, [toc]);

    const scrollTo = (id, href) => {
        setActivo(id);
        scrollLockRef.current = id;

        const el = document.querySelector(href);
        if (!el) {
            scrollLockRef.current = null;
            return;
        }
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });

        const unlock = () => {
            if (scrollLockRef.current !== id) return;
            scrollLockRef.current = null;
        };

        if ('onscrollend' in window) {
            window.addEventListener('scrollend', unlock, { once: true });
        }
        const started = performance.now();
        const tick = () => {
            if (scrollLockRef.current !== id) return;
            const top = el.getBoundingClientRect().top;
            if (Math.abs(top) < 40 || performance.now() - started > 2500) {
                unlock();
                return;
            }
            requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };

    const pie = `Capítulos según tu cargo: ${secciones.map((s) => s.cargo).join(', ') || '—'}.`;

    return (
        <AppLayout user={auth.user}>
            <Head title={`Manual · ${manual?.titulo || 'Manuales'}`} />

            <GeliaPageShell className="space-y-6 py-6 md:py-10">
                <GeliaTituloCard
                    eyebrow={manual?.modulo}
                    title={manual?.titulo || 'Manual'}
                    description={manual?.descripcion}
                    icon={BookOpen}
                    aside={
                        <div className="flex flex-wrap items-center gap-3">
                            <GeliaLogo variant="sparkle" className="w-12 h-12" />
                            <a href={pdfUrl} className={`${THEME_BTN_PRIMARY} inline-flex items-center gap-2`}>
                                <Download className="w-4 h-4" />
                                Descargar PDF
                            </a>
                            <Link href={route('soporte.manuales.index')} className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2`}>
                                <ArrowLeft className="w-4 h-4" />
                                Manuales
                            </Link>
                        </div>
                    }
                />

                <ManualTocMobile toc={toc} activo={activo} onSelect={scrollTo} />

                <div className="grid grid-cols-1 xl:grid-cols-[220px_minmax(0,1fr)] gap-6 items-start">
                    <ManualTocDesktop
                        toc={toc}
                        activo={activo}
                        onSelect={scrollTo}
                        pie={pie}
                    />

                    <div data-manual-content className={geliaCardClass('p-5 md:p-8 lg:p-10')}>
                        {ContentView ? (
                            <ContentView contenido={contenido} seccionesMeta={secciones} />
                        ) : (
                            <p className="theme-text-muted text-sm">Contenido no disponible.</p>
                        )}
                    </div>
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}
