import React, { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ChevronDown, Sparkles } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import PresenciaService from '@/Services/PresenciaService';
import {
    catalogoDesdeAuth,
    normalizarCatalogoPresencia,
    PRESENCIA_ESTADOS,
    presenciaDesdeSlug,
} from '@/constants/presenciaEstados';
import { textoPresencia } from '@/utils/presenciaUsuario';

export default function SelectorEstadoPresencia({
    presenciaInicial = null,
    catalogoInicial = null,
    compact = false,
    onCambio,
}) {
    const { props: { auth } } = usePage();
    const [abierto, setAbierto] = useState(false);
    const [presencia, setPresencia] = useState(presenciaInicial || auth?.presencia || null);
    const [catalogo, setCatalogo] = useState(() => {
        if (catalogoInicial) {
            return normalizarCatalogoPresencia(catalogoInicial);
        }
        return catalogoDesdeAuth(auth);
    });
    const [guardando, setGuardando] = useState(false);
    const [error, setError] = useState(null);
    const [menuPos, setMenuPos] = useState({ top: 0, left: 0, width: 224 });
    const ref = useRef(null);
    const menuRef = useRef(null);

    useEffect(() => {
        if (presenciaInicial) setPresencia(presenciaInicial);
    }, [presenciaInicial]);

    useEffect(() => {
        setCatalogo(catalogoDesdeAuth(auth));
    }, [auth?.presencia_catalogo]);

    useEffect(() => {
        PresenciaService.catalogo()
            .then((data) => {
                setCatalogo(normalizarCatalogoPresencia(data?.estados));
            })
            .catch(() => {
                setCatalogo((prev) => (prev?.length ? prev : [...PRESENCIA_ESTADOS]));
            });
    }, []);

    useEffect(() => {
        const handler = (event) => {
            const nueva = event.detail;
            if (!nueva) return;
            const esPropio = !nueva.user_id || Number(nueva.user_id) === Number(auth?.user?.id);
            if (esPropio) setPresencia(nueva);
        };
        window.addEventListener('gelia-presencia-propia', handler);
        return () => window.removeEventListener('gelia-presencia-propia', handler);
    }, [auth?.user?.id]);

    const posicionarMenu = useCallback(() => {
        const btn = ref.current;
        if (!btn) return;
        const rect = btn.getBoundingClientRect();
        const ancho = 224;
        let left = rect.right - ancho;
        left = Math.max(8, Math.min(left, window.innerWidth - ancho - 8));
        let top = rect.bottom + 6;
        const altoEstimado = menuRef.current?.offsetHeight ?? 320;
        if (top + altoEstimado > window.innerHeight - 8) {
            top = Math.max(8, rect.top - altoEstimado - 6);
        }
        setMenuPos({ top, left, width: ancho });
    }, []);

    useLayoutEffect(() => {
        if (!abierto) return undefined;
        posicionarMenu();
        const onResize = () => posicionarMenu();
        window.addEventListener('resize', onResize);
        window.addEventListener('scroll', onResize, true);
        return () => {
            window.removeEventListener('resize', onResize);
            window.removeEventListener('scroll', onResize, true);
        };
    }, [abierto, catalogo.length, posicionarMenu]);

    useEffect(() => {
        if (!abierto) return undefined;

        const onClickFuera = (e) => {
            if (ref.current?.contains(e.target) || menuRef.current?.contains(e.target)) return;
            setAbierto(false);
        };
        const onKey = (e) => {
            if (e.key === 'Escape') setAbierto(false);
        };

        const timer = window.setTimeout(() => {
            document.addEventListener('click', onClickFuera, true);
        }, 0);

        document.addEventListener('keydown', onKey);

        return () => {
            window.clearTimeout(timer);
            document.removeEventListener('click', onClickFuera, true);
            document.removeEventListener('keydown', onKey);
        };
    }, [abierto]);

    const aplicarEstado = useCallback(async (estado, opciones = {}) => {
        const modo = opciones.modo ?? 'manual';
        const optimista = {
            ...presenciaDesdeSlug(estado),
            user_id: auth?.user?.id,
            modo,
        };

        setPresencia(optimista);
        setGuardando(true);
        setError(null);

        try {
            const payload = {
                estado,
                modo,
                duracion_minutos: opciones.duracion_minutos ?? 0,
                automatizar: opciones.automatizar,
            };
            const nueva = await PresenciaService.actualizar(payload);
            const conUser = { ...nueva, user_id: nueva.user_id ?? auth?.user?.id };
            setPresencia(conUser);
            onCambio?.(conUser);
            window.dispatchEvent(new CustomEvent('gelia-presencia-propia', { detail: conUser }));
            setAbierto(false);
        } catch (err) {
            const msg = err?.response?.data?.message
                || err?.response?.data?.errors
                || err?.message
                || 'No se pudo guardar el estado.';
            setError(typeof msg === 'object' ? Object.values(msg).flat().join(' ') : String(msg));
            setAbierto(true);
        } finally {
            setGuardando(false);
        }
    }, [onCambio, auth?.user?.id]);

    const activarAutomatico = useCallback(async () => {
        setGuardando(true);
        setError(null);
        try {
            const nueva = await PresenciaService.actualizar({
                modo: 'automatico',
                automatizar: true,
            });
            const conUser = { ...nueva, user_id: nueva.user_id ?? auth?.user?.id };
            setPresencia(conUser);
            onCambio?.(conUser);
            window.dispatchEvent(new CustomEvent('gelia-presencia-propia', { detail: conUser }));
            setAbierto(false);
        } catch (err) {
            setError(err?.response?.data?.message || err?.message || 'No se pudo activar el modo automático.');
            setAbierto(true);
        } finally {
            setGuardando(false);
        }
    }, [onCambio, auth?.user?.id]);

    const etiqueta = textoPresencia(presencia) || 'Estado';

    const menu = abierto && typeof document !== 'undefined'
        ? createPortal(
            <div
                ref={menuRef}
                className="gelia-presencia-menu fixed z-[9999] rounded-xl theme-surface-solid border theme-border shadow-xl py-1 overflow-y-auto max-h-[min(70vh,22rem)]"
                style={{
                    top: menuPos.top,
                    left: menuPos.left,
                    width: menuPos.width,
                }}
                role="menu"
            >
                <p className="px-3 py-2 text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 border-b theme-border">
                    Tu estado
                </p>

                {(catalogo.length > 0 ? catalogo : PRESENCIA_ESTADOS).map((item) => (
                    <button
                        key={item.slug}
                        type="button"
                        role="menuitem"
                        disabled={guardando}
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            const esRuta = item.slug === 'en_ruta_venta';
                            aplicarEstado(item.slug, {
                                modo: 'manual',
                                duracion_minutos: esRuta ? 480 : 60,
                            });
                        }}
                        className={`w-full flex items-center gap-2 px-3 py-2.5 text-left text-xs font-bold theme-text-main hover:theme-element transition-colors disabled:opacity-50 ${
                            presencia?.estado === item.slug ? 'bg-[var(--color-primario)]/10' : ''
                        }`}
                    >
                        <span aria-hidden>{item.emoji}</span>
                        <span>
                            {item.etiqueta}
                            {item.slug === 'en_ruta_venta' ? ' (hoy)' : ''}
                        </span>
                    </button>
                ))}

                <div className="border-t theme-border my-1" />

                <button
                    type="button"
                    role="menuitem"
                    disabled={guardando}
                    onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        activarAutomatico();
                    }}
                    className="w-full flex items-center gap-2 px-3 py-2 text-left text-[10px] font-bold theme-text-main hover:theme-element disabled:opacity-50"
                >
                    <Sparkles className="w-3.5 h-3.5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                    Automático (horarios)
                </button>

                {error && (
                    <p className="px-3 py-2 m-0 text-[10px] font-bold text-red-600 border-t theme-border">
                        {error}
                    </p>
                )}
            </div>,
            document.body
        )
        : null;

    return (
        <div ref={ref} className={`relative shrink-0 z-[45] ${compact ? '' : 'min-w-0'}`}>
            <button
                type="button"
                onClick={() => setAbierto((v) => !v)}
                disabled={guardando}
                className={`gelia-presencia-selector-btn flex items-center gap-1.5 rounded-full border theme-border theme-element theme-text-main outline-none hover:border-[var(--color-primario)] transition-colors ${
                    compact ? 'px-2 py-1 text-[10px]' : 'px-3 py-1.5 text-xs'
                } ${abierto ? 'border-[var(--color-primario)]' : ''}`}
                title="Cambiar tu estado"
                aria-expanded={abierto}
                aria-haspopup="menu"
            >
                <span aria-hidden>{presencia?.emoji || '🟢'}</span>
                <span className="font-bold truncate max-w-[8rem]">{etiqueta}</span>
                <ChevronDown className={`w-3.5 h-3.5 shrink-0 transition-transform ${abierto ? 'rotate-180' : ''}`} />
            </button>
            {menu}
        </div>
    );
}
