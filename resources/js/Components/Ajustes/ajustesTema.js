import {
    clampFontScale,
    applyFontScaleToRoot,
    FONT_SCALE_DEFAULT,
    FONT_SCALE_STORAGE_KEY,
} from '../../utils/fontScale';
import {
    clampContentMaxRem,
    clampContentPaddingRem,
    normalizeDensityMode,
    applyContentDensityToRoot,
    CONTENT_DENSITY_DEFAULT,
    CONTENT_MAX_REM_DEFAULT,
    CONTENT_PADDING_REM_DEFAULT,
    CONTENT_DENSITY_STORAGE_KEY,
    CONTENT_MAX_REM_STORAGE_KEY,
    CONTENT_PADDING_REM_STORAGE_KEY,
} from '../../utils/contentDensity';
import { FONDO_SISTEMA, applyFondoPantalla, normalizeFondoBase } from '../../utils/fondoPantalla';
import {
    DEFAULT_SIDEBAR_MOBILE_LAYOUT,
    ensureProfessionalSidebarDefaultOnce,
    isProfessionalSidebarLayout,
    resolveSidebarLayout,
} from '../../config/sidebarLayouts';

export const ACENTOS = { rosa: '#ec4899', azul: '#3b82f6', verde: '#10b981', amarillo: '#f59e0b' };

export const FUENTES = {
    inter: "'Inter', sans-serif",
    montserrat: "'Montserrat', sans-serif",
    poppins: "'Poppins', sans-serif",
    nunito: "'Nunito', sans-serif",
    roboto: "'Roboto', sans-serif",
    mono: "'JetBrains Mono', monospace",
};

export const FUENTES_OPCIONES = [
    ['inter', 'Inter (Predeterminada)'],
    ['montserrat', 'Montserrat (Corporativa)'],
    ['poppins', 'Poppins (Moderna)'],
    ['nunito', 'Nunito (Amigable)'],
    ['roboto', 'Roboto (Datos/Tablas)'],
    ['mono', 'JetBrains Mono (Técnica)'],
];

export function resolverAcento(nombre) {
    if (!nombre) return ACENTOS.rosa;
    return nombre.startsWith('#') ? nombre : (ACENTOS[nombre] || ACENTOS.rosa);
}

/** Tema efectivo del usuario: localStorage (vista previa) → configuración guardada → defaults. */
export function leerTemaAlmacenado(temaVisual = {}) {
    if (typeof window === 'undefined') {
        return {
            color: temaVisual?.color_nombre?.toLowerCase() || 'rosa',
            dark: temaVisual?.modo === 'dark',
            bg: normalizeFondoBase(temaVisual?.fondo_base) || FONDO_SISTEMA,
            font: temaVisual?.fuente_principal || 'mono',
            scale: clampFontScale(temaVisual?.escala_fuente ?? FONT_SCALE_DEFAULT),
            glass: temaVisual?.efecto_cristal !== false,
            layout: resolveSidebarLayout(null, temaVisual?.layout_sidebar),
            mobileLayout: temaVisual?.layout_sidebar_mobile || DEFAULT_SIDEBAR_MOBILE_LAYOUT,
            sidebarMode: temaVisual?.sidebar_modo || 'collapsed',
            fixedPosition: temaVisual?.sidebar_posicion_fija || 'left',
            contentDensity: normalizeDensityMode(temaVisual?.densidad_contenido ?? CONTENT_DENSITY_DEFAULT),
            contentMaxRem: clampContentMaxRem(temaVisual?.contenido_max_rem ?? CONTENT_MAX_REM_DEFAULT),
            contentPaddingRem: clampContentPaddingRem(temaVisual?.contenido_padding_rem ?? CONTENT_PADDING_REM_DEFAULT),
        };
    }
    ensureProfessionalSidebarDefaultOnce();
    const savedGlass = localStorage.getItem('theme_glass');
    return {
        color: localStorage.getItem('theme_color') || temaVisual?.color_nombre?.toLowerCase() || 'rosa',
        dark: localStorage.getItem('theme')
            ? localStorage.getItem('theme') === 'dark'
            : temaVisual?.modo === 'dark',
        bg: normalizeFondoBase(localStorage.getItem('bg_base') || temaVisual?.fondo_base) || FONDO_SISTEMA,
        font: localStorage.getItem('theme_font') || temaVisual?.fuente_principal || 'mono',
        scale: clampFontScale(
            localStorage.getItem(FONT_SCALE_STORAGE_KEY) ?? temaVisual?.escala_fuente ?? FONT_SCALE_DEFAULT
        ),
        glass: savedGlass !== null ? savedGlass === 'true' : temaVisual?.efecto_cristal !== false,
        layout: resolveSidebarLayout(localStorage.getItem('theme_layout'), temaVisual?.layout_sidebar),
        mobileLayout: localStorage.getItem('theme_layout_mobile')
            || temaVisual?.layout_sidebar_mobile
            || DEFAULT_SIDEBAR_MOBILE_LAYOUT,
        sidebarMode: localStorage.getItem('theme_sidebar_mode') || temaVisual?.sidebar_modo || 'collapsed',
        fixedPosition: localStorage.getItem('theme_fixed_position') || temaVisual?.sidebar_posicion_fija || 'left',
        contentDensity: normalizeDensityMode(
            localStorage.getItem(CONTENT_DENSITY_STORAGE_KEY) ?? temaVisual?.densidad_contenido ?? CONTENT_DENSITY_DEFAULT
        ),
        contentMaxRem: clampContentMaxRem(
            localStorage.getItem(CONTENT_MAX_REM_STORAGE_KEY) ?? temaVisual?.contenido_max_rem ?? CONTENT_MAX_REM_DEFAULT
        ),
        contentPaddingRem: clampContentPaddingRem(
            localStorage.getItem(CONTENT_PADDING_REM_STORAGE_KEY) ?? temaVisual?.contenido_padding_rem ?? CONTENT_PADDING_REM_DEFAULT
        ),
    };
}

const CLAVES_TEMA = [
    'theme', 'theme_color', 'bg_base', 'theme_font', FONT_SCALE_STORAGE_KEY, 'theme_glass',
    'theme_layout', 'theme_layout_mobile', 'theme_sidebar_mode', 'theme_fixed_position',
    CONTENT_DENSITY_STORAGE_KEY, CONTENT_MAX_REM_STORAGE_KEY, CONTENT_PADDING_REM_STORAGE_KEY,
];

export function capturarInstantaneaTema(storage = typeof window !== 'undefined' ? window.localStorage : null) {
    if (!storage) return null;
    return Object.fromEntries(CLAVES_TEMA.map((clave) => [clave, storage.getItem(clave)]));
}

export function restaurarInstantaneaTema(instantanea, storage = typeof window !== 'undefined' ? window.localStorage : null) {
    if (!instantanea || !storage) return;
    Object.entries(instantanea).forEach(([clave, valor]) => {
        if (valor !== null && valor !== undefined) storage.setItem(clave, valor);
        else storage.removeItem(clave);
    });
}

/**
 * Pinta el tema en el documento sin pasar por React.
 * Las vistas previas escriben directo en :root, así que al descartar hay que repintarlas.
 */
export function aplicarTemaAlDocumento(tema, root = typeof document !== 'undefined' ? document.documentElement : null) {
    if (!root) return;
    root.style.setProperty('--color-primario', resolverAcento(tema.color));
    root.style.setProperty('--font-principal', FUENTES[tema.font] || FUENTES.inter);
    applyFondoPantalla(root, normalizeFondoBase(tema.bg));
    applyFontScaleToRoot(tema.scale);
    root.classList.toggle('glass-active', Boolean(tema.glass));
    applyContentDensityToRoot({
        densidad_contenido: tema.contentDensity,
        contenido_max_rem: tema.contentMaxRem,
        contenido_padding_rem: tema.contentPaddingRem,
    });
}

/** Carga útil `tema_visual` que valida y fusiona ProfileController::update. */
export function construirTemaVisual(estado, { archivoFondo = false } = {}) {
    return {
        modo: estado.dark ? 'dark' : 'light',
        color_nombre: estado.color,
        fondo_base: archivoFondo ? 'subiendo_archivo' : estado.bg,
        fuente_principal: estado.font,
        escala_fuente: estado.scale,
        layout_sidebar: estado.layout,
        layout_sidebar_mobile: isProfessionalSidebarLayout(estado.layout) ? 'mobile_topbar' : estado.mobileLayout,
        sidebar_modo: estado.sidebarMode,
        sidebar_posicion_fija: estado.fixedPosition,
        efecto_cristal: estado.glass,
        densidad_contenido: estado.contentDensity,
        contenido_max_rem: estado.contentMaxRem,
        contenido_padding_rem: estado.contentPaddingRem,
        alertas_prefs: estado.alertas,
    };
}

/** Huella estable de lo editable: sirve para saber si hay cambios sin guardar. */
export function huellaAjustes(estado) {
    return JSON.stringify(construirTemaVisual(estado));
}
