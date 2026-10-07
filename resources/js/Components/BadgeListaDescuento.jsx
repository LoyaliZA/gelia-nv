import { Sparkles } from 'lucide-react';

const BADGE_BASE =
    'inline-flex items-center px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-md';

function normalizarNivel(nombreLista) {
    return nombreLista.toUpperCase().replace('MAYOREO ', '').trim();
}

/**
 * Etiqueta visual de lista de descuento (mayoreo, público general, colaboradores, plataformas).
 * @param {string|null|undefined} nombre - Nombre de la lista en catálogo.
 * @param {'sin_lista'|'no_participa'} vacio - Texto cuando no hay nombre.
 */
export default function BadgeListaDescuento({ nombre, vacio = 'sin_lista' }) {
    if (!nombre) {
        if (vacio === 'no_participa') {
            return (
                <span className={`${BADGE_BASE} theme-element border theme-border text-zinc-500`}>
                    Sin lista participante
                </span>
            );
        }

        return (
            <span className={`${BADGE_BASE} theme-element border theme-border text-zinc-500`}>
                Sin Lista
            </span>
        );
    }

    const nivel = normalizarNivel(nombre);

    if (nivel.includes('PLATAFORMA')) {
        return (
            <span className={`${BADGE_BASE} bg-indigo-500/10 text-indigo-600 border border-indigo-500/30`}>
                Plataformas
            </span>
        );
    }

    switch (nivel) {
        case 'BRONCE':
            return (
                <span className={`${BADGE_BASE} bg-[#cd7f32]/10 text-[#cd7f32] border border-[#cd7f32]/30`}>
                    Bronce
                </span>
            );
        case 'PLATA':
            return (
                <span className={`${BADGE_BASE} bg-slate-400/10 text-slate-500 border border-slate-400/30`}>
                    Plata
                </span>
            );
        case 'ORO':
            return (
                <span className={`${BADGE_BASE} bg-yellow-500/10 text-yellow-600 border border-yellow-500/30`}>
                    Oro
                </span>
            );
        case 'DIAMANTE':
            return (
                <span className="relative inline-flex items-center gap-1.5 px-2.5 py-1 bg-gradient-to-r from-cyan-500/10 to-blue-500/10 border border-cyan-400/40 text-[9px] font-black uppercase tracking-widest rounded-md overflow-hidden shadow-[0_0_8px_rgba(34,211,238,0.2)]">
                    <Sparkles className="w-3 h-3 text-cyan-600 dark:text-cyan-300" aria-hidden />
                    <span className="text-cyan-700 dark:text-cyan-300 drop-shadow-sm">Diamante</span>
                    <span
                        className="absolute inset-0 w-[150%] -translate-x-full bg-gradient-to-r from-transparent via-cyan-100/60 dark:via-white/20 to-transparent skew-x-12 animate-[shimmer_3s_infinite_ease-in-out]"
                        aria-hidden
                    />
                </span>
            );
        case 'PUBLICO GENERAL':
            return (
                <span className={`${BADGE_BASE} theme-element theme-border border text-zinc-500`}>
                    Público Gral.
                </span>
            );
        case 'COLABORADORES':
            return (
                <span className={`${BADGE_BASE} bg-purple-500/10 text-purple-600 border border-purple-500/30`}>
                    Colaborador
                </span>
            );
        default:
            return (
                <span className={`${BADGE_BASE} bg-blue-500/10 text-blue-500 border border-blue-500/30`}>
                    {nivel}
                </span>
            );
    }
}
