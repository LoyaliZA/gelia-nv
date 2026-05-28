import React, { useState, useEffect, useRef, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import { 
    User, Mail, Smartphone, Camera, 
    Palette, Save, ShieldCheck, Moon, Sun, 
    Image as ImageIcon, Type, Droplet, 
    PanelLeft, BellRing, Settings2, PaintBucket, Layers, Upload, X, Trash2, AlertTriangle, Check, XCircle,
    Lock, KeyRound, CalendarDays, Building2, MapPin, ChevronDown, Eye, EyeOff,
    Minus, Plus, Volume2, Mic, Monitor, Bell
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';
import GeliaLogo from '../../Components/GeliaLogo';
import NotificationBrowserService from '@/Services/NotificationBrowserService';
import {
    readStoredAlertasPrefs,
    persistAlertasPrefsToStorage,
    mergeAlertasPrefs,
    ALERTAS_TIPOS,
    resolveTonoPath,
} from '../../utils/alertasPrefs';
import {
    clampFontScale,
    formatFontScaleLabel,
    applyFontScaleToRoot,
    FONT_SCALE_DEFAULT,
    FONT_SCALE_MIN,
    FONT_SCALE_MAX,
    FONT_SCALE_STEP,
    FONT_SCALE_STORAGE_KEY,
} from '../../utils/fontScale';
import {
    compressImageToWebp,
    validateImageSource,
    MAX_SOURCE_IMAGE_BYTES,
} from '../../utils/compressImage';

const MAX_PROFILE_PHOTO_BYTES = 2048 * 1024;
const MAX_BG_PHOTO_BYTES = 5120 * 1024;

const accentColors = { rosa: '#ec4899', azul: '#3b82f6', verde: '#10b981', amarillo: '#f59e0b' };
const fontFamilies = {
    inter:      "'Inter', sans-serif",
    montserrat: "'Montserrat', sans-serif",
    poppins:    "'Poppins', sans-serif",
    nunito:     "'Nunito', sans-serif",
    roboto:     "'Roboto', sans-serif",
    mono:       "'JetBrains Mono', monospace",
};

function readStoredTheme(temaVisual = {}) {
    if (typeof window === 'undefined') {
        return {
            color: temaVisual?.color_nombre?.toLowerCase() || 'rosa',
            dark: temaVisual?.modo === 'dark',
            bg: temaVisual?.fondo_base || 'none',
            font: temaVisual?.fuente_principal || 'inter',
            scale: clampFontScale(temaVisual?.escala_fuente ?? FONT_SCALE_DEFAULT),
            glass: temaVisual?.efecto_cristal !== false,
            layout: temaVisual?.layout_sidebar || 'floating_left',
            sidebarMode: temaVisual?.sidebar_modo || 'collapsed',
            fixedPosition: temaVisual?.sidebar_posicion_fija || 'left',
        };
    }
    const savedGlass = localStorage.getItem('theme_glass');
    return {
        color: localStorage.getItem('theme_color') || temaVisual?.color_nombre?.toLowerCase() || 'rosa',
        dark: localStorage.getItem('theme')
            ? localStorage.getItem('theme') === 'dark'
            : temaVisual?.modo === 'dark',
        bg: localStorage.getItem('bg_base') || temaVisual?.fondo_base || 'none',
        font: localStorage.getItem('theme_font') || temaVisual?.fuente_principal || 'inter',
        scale: clampFontScale(
            localStorage.getItem(FONT_SCALE_STORAGE_KEY) ?? temaVisual?.escala_fuente ?? FONT_SCALE_DEFAULT
        ),
        glass: savedGlass !== null ? savedGlass === 'true' : temaVisual?.efecto_cristal !== false,
        layout: localStorage.getItem('theme_layout') || temaVisual?.layout_sidebar || 'floating_left',
        sidebarMode: localStorage.getItem('theme_sidebar_mode') || temaVisual?.sidebar_modo || 'collapsed',
        fixedPosition: localStorage.getItem('theme_fixed_position') || temaVisual?.sidebar_posicion_fija || 'left',
    };
}

function captureThemeSnapshot() {
    if (typeof window === 'undefined') return null;
    return {
        theme: localStorage.getItem('theme'),
        theme_color: localStorage.getItem('theme_color'),
        bg_base: localStorage.getItem('bg_base'),
        theme_font: localStorage.getItem('theme_font'),
        [FONT_SCALE_STORAGE_KEY]: localStorage.getItem(FONT_SCALE_STORAGE_KEY),
        theme_glass: localStorage.getItem('theme_glass'),
        theme_layout: localStorage.getItem('theme_layout'),
        theme_sidebar_mode: localStorage.getItem('theme_sidebar_mode'),
        theme_fixed_position: localStorage.getItem('theme_fixed_position'),
    };
}

function restoreThemeSnapshot(snapshot) {
    if (!snapshot || typeof window === 'undefined') return;
    Object.entries(snapshot).forEach(([key, value]) => {
        if (value !== null && value !== undefined) localStorage.setItem(key, value);
        else localStorage.removeItem(key);
    });
    window.dispatchEvent(new Event('theme-changed'));
}

function applyBackgroundCSS(bgValue) {
    const root = document.documentElement;
    root.style.removeProperty('--bg-image-pc');
    root.style.removeProperty('--bg-image-movil');

    if (!bgValue || bgValue === 'none') {
        root.style.setProperty('--bg-image-pc', 'none');
        root.style.setProperty('--bg-image-movil', 'none');
    } else if (bgValue.startsWith('#')) {
        root.style.setProperty('--bg-image-pc', `linear-gradient(to right, ${bgValue}, ${bgValue})`);
        root.style.setProperty('--bg-image-movil', `linear-gradient(to right, ${bgValue}, ${bgValue})`);
    } else if (bgValue.startsWith('data:image') || bgValue.startsWith('/storage')) {
        root.style.setProperty('--bg-image-pc', `url(${bgValue})`);
        root.style.setProperty('--bg-image-movil', `url(${bgValue})`);
    } else {
        root.style.setProperty('--bg-image-pc', `url('/assets/backgrounds/${bgValue}_pc.svg')`);
        root.style.setProperty('--bg-image-movil', `url('/assets/backgrounds/${bgValue}_movil.svg')`);
    }
}

function resolveAccentHex(colorName) {
    if (!colorName) return accentColors.rosa;
    return colorName.startsWith('#') ? colorName : (accentColors[colorName] || accentColors.rosa);
}

// --- COMPONENTE AUXILIAR RESPONSIVO ---
const SettingsRow = ({ icon: Icon, title, subtitle, children, border = true, stackOnMobile = true }) => (
    <div className={`group flex ${stackOnMobile ? 'flex-col sm:flex-row sm:items-center' : 'flex-row items-center'} justify-between gap-4 py-5 px-4 rounded-2xl cursor-default transition-all duration-150
        hover:ring-2 hover:ring-[var(--color-primario)]/40
        ${border ? 'border-b theme-border' : ''}`}>
        <div className="flex items-center gap-4 flex-1">
            <div className="p-3 rounded-2xl shrink-0 transition-transform duration-150 group-hover:scale-110" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                <Icon className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
            </div>
            <div className="flex-1">
                <h3 className="text-[15px] font-black theme-text-main leading-tight">{title}</h3>
                <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-1 leading-tight">{subtitle}</p>
            </div>
        </div>
        <div className={`flex justify-start sm:justify-end ${stackOnMobile ? 'w-full sm:w-auto mt-2 sm:mt-0' : 'shrink-0'}`}>
            {children}
        </div>
    </div>
);

export default function Edit({ tema_visual, perfilUsuario = {} }) {
    const { auth, tonos_alertas = [], catalogo_temas = [], catalogo_fondos = [] } = usePage().props;
    const usuario = { ...auth?.user, ...perfilUsuario };

    const fileInputRef    = useRef(null);
    const bgFileInputRef  = useRef(null);
    const themeSnapshotRef = useRef(null);
    const themeSavedRef    = useRef(false);

    const initialTheme = readStoredTheme(tema_visual);

    const [isAvatarModalOpen, setIsAvatarModalOpen] = useState(false);
    const [isBgModalOpen,     setIsBgModalOpen]     = useState(false);

    const [imagePreview, setImagePreview] = useState(
        usuario?.foto_perfil ? `/storage/${usuario.foto_perfil}` : null
    );
    const [avatarLoadFailed, setAvatarLoadFailed] = useState(false);

    const initialChar = usuario?.name ? usuario.name.charAt(0).toUpperCase() : 'U';

    const [saveStatus, setSaveStatus] = useState(null); // null | 'success' | 'error'
    const [fileAlert, setFileAlert] = useState(null);   // null | { title, message }
    const [isCompressing, setIsCompressing] = useState(false);

    // --- Estados para secciones colapsables ---
    const [showSensitive,     setShowSensitive]     = useState(false);
    const [showInstitutional, setShowInstitutional] = useState(false);
    const [showPassword,      setShowPassword]      = useState(false);
    const [showConfirm,       setShowConfirm]       = useState(false);

    const { data, setData, post, processing, recentlySuccessful, errors, transform } = useForm({
        name:                  usuario?.name                 ? usuario.name.trim()                 : '',
        email:                 usuario?.email                ? usuario.email.trim()                : '',
        apellido_paterno:      usuario?.apellido_paterno     ? usuario.apellido_paterno.trim()     : '',
        apellido_materno:      usuario?.apellido_materno     ? usuario.apellido_materno.trim()     : '',
        telefono:              usuario?.telefono             ? usuario.telefono.trim()             : '',
        fecha_nacimiento:      usuario?.fecha_nacimiento     || '',
        password:              '',
        password_confirmation: '',
        foto_perfil:           null,
        remove_foto:           false,
        archivo_fondo:         null,
        remove_fondo:          false,
        tema_visual:           tema_visual || {},
    });

    const FALLBACK_BACKGROUNDS = ['blob', 'blobscene', 'circle', 'layered', 'peaks', 'polygon', 'square', 'stacked', 'steps', 'wave'];
    const solidBackgrounds  = [
        { name: 'Blanco',      hex: '#ffffff' },
        { name: 'Negro',       hex: '#000000' },
        { name: 'Gris Oscuro', hex: '#1e293b' },
    ];
    const FALLBACK_PRESETS = [
        { name: 'Gelia Signature', modo: 'dark',  colorHex: '#ec4899', colorNombre: 'rosa',  bg: 'blob',    font: 'montserrat', escala: 1, glass: true,  layout: 'floating_left',  sound: true },
        { name: 'GELIA Oasis',     modo: 'light', colorHex: '#10b981', colorNombre: 'verde', bg: 'stacked', font: 'poppins',     escala: 1, glass: false, layout: 'floating_right', sound: true },
        { name: 'CyberTech',       modo: 'dark',  colorHex: '#3b82f6', colorNombre: 'azul',  bg: 'polygon', font: 'mono',        escala: 1, glass: false, layout: 'fixed',          sound: true },
    ];
    const presets = catalogo_temas.length > 0 ? catalogo_temas : FALLBACK_PRESETS;
    const fondosDisponibles = catalogo_fondos.length > 0
        ? catalogo_fondos
        : FALLBACK_BACKGROUNDS.map((slug) => ({
            id: slug,
            slug,
            nombre: slug.charAt(0).toUpperCase() + slug.slice(1),
            tipo: 'vector',
            valor: slug,
            preview: `/assets/backgrounds/${slug}_movil.svg`,
        }));

    const [selectedColor, setSelectedColor] = useState(initialTheme.color);
    const [isDarkMode,    setIsDarkMode]    = useState(initialTheme.dark);
    const [selectedBg,    setSelectedBg]    = useState(initialTheme.bg);
    const [typography,    setTypography]    = useState(initialTheme.font);
    const [fontScale,     setFontScale]     = useState(initialTheme.scale);
    const [glassEffect,   setGlassEffect]   = useState(initialTheme.glass);
    const [sidebarLayout, setSidebarLayout] = useState(initialTheme.layout);
    const [sidebarMode, setSidebarMode] = useState(initialTheme.sidebarMode);
    const [fixedPosition, setFixedPosition] = useState(initialTheme.fixedPosition);
    const [alertasPrefs, setAlertasPrefs] = useState(() => readStoredAlertasPrefs(tema_visual));

    const updateAlertasPrefs = useCallback((updater) => {
        setAlertasPrefs((prev) => {
            const next = typeof updater === 'function' ? updater(prev) : updater;
            persistAlertasPrefsToStorage(next);
            NotificationBrowserService.setPreferences(next);
            return next;
        });
    }, []);

    const toggleCanalAlerta = (canal) => {
        updateAlertasPrefs((prev) => {
            const activo = prev.canales?.[canal] !== false;
            return {
                ...prev,
                canales: { ...prev.canales, [canal]: !activo },
            };
        });
    };

    const toggleTipoAlerta = (tipo) => {
        updateAlertasPrefs((prev) => {
            const activo = prev.tipos?.[tipo] !== false;
            return {
                ...prev,
                tipos: { ...prev.tipos, [tipo]: !activo },
            };
        });
    };

    useEffect(() => {
        NotificationBrowserService.setTonosCatalog(tonos_alertas);
        NotificationBrowserService.setPreferences(alertasPrefs);
    }, [tonos_alertas, alertasPrefs]);

    useEffect(() => {
        if (isAvatarModalOpen || isBgModalOpen) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = '';
        return () => { document.body.style.overflow = ''; };
    }, [isAvatarModalOpen, isBgModalOpen]);

    useEffect(() => {
        setAvatarLoadFailed(false);
    }, [imagePreview]);

    useEffect(() => {
        if (usuario?.foto_perfil && !data.foto_perfil) {
            setImagePreview(`/storage/${usuario.foto_perfil}`);
        }
    }, [usuario?.foto_perfil, data.foto_perfil]);

    useEffect(() => {
        themeSnapshotRef.current = captureThemeSnapshot();
        return () => {
            if (!themeSavedRef.current) restoreThemeSnapshot(themeSnapshotRef.current);
        };
    }, []);

    const validateImageFile = useCallback((file, label) => validateImageSource(file, label), []);

    const showFileAlert = useCallback((title, message) => {
        setFileAlert({ title, message });
    }, []);

    const handleProfileFileChange = async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const error = validateImageFile(file, 'Foto de perfil');
        if (error) {
            showFileAlert('Archivo no permitido', error);
            if (fileInputRef.current) fileInputRef.current.value = '';
            return;
        }

        setIsCompressing(true);
        try {
            const compressed = await compressImageToWebp(file, {
                maxDimension: 800,
                quality: 0.85,
                maxBytes: MAX_PROFILE_PHOTO_BYTES,
            });

            setData(prev => ({ ...prev, foto_perfil: compressed, remove_foto: false }));
            setAvatarLoadFailed(false);
            setImagePreview(URL.createObjectURL(compressed));
        } catch (err) {
            const message = err?.message === 'COMPRESS_TOO_LARGE'
                ? 'La foto sigue siendo muy grande después de optimizarla. Prueba con una imagen de menor resolución.'
                : 'No se pudo procesar la foto de perfil. Verifica que sea una imagen válida.';
            showFileAlert('Error al procesar', message);
            setData(prev => ({ ...prev, foto_perfil: null }));
            setImagePreview(usuario?.foto_perfil ? `/storage/${usuario.foto_perfil}` : null);
        } finally {
            setIsCompressing(false);
            if (fileInputRef.current) fileInputRef.current.value = '';
        }
    };

    const handleRemovePhoto = () => {
        setData(prev => ({ ...prev, foto_perfil: null, remove_foto: true }));
        setImagePreview(null);
        setAvatarLoadFailed(false);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const handleBgFileChange = async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const error = validateImageFile(file, 'Fondo de pantalla');
        if (error) {
            showFileAlert('Archivo no permitido', error);
            if (bgFileInputRef.current) bgFileInputRef.current.value = '';
            return;
        }

        setIsCompressing(true);
        try {
            const compressed = await compressImageToWebp(file, {
                maxDimension: 1920,
                quality: 0.82,
                maxBytes: MAX_BG_PHOTO_BYTES,
            });

            setData(prev => ({ ...prev, archivo_fondo: compressed, remove_fondo: false }));

            const previewUrl = URL.createObjectURL(compressed);
            setSelectedBg(previewUrl);
            applyBackgroundCSS(previewUrl);
            localStorage.setItem('bg_base', previewUrl);
            window.dispatchEvent(new Event('theme-changed'));
        } catch (err) {
            const message = err?.message === 'COMPRESS_TOO_LARGE'
                ? 'El fondo sigue siendo muy grande después de optimizarlo. Prueba con una imagen de menor resolución.'
                : 'No se pudo procesar la imagen de fondo. Verifica que sea una imagen válida.';
            showFileAlert('Error al procesar', message);
            setData(prev => ({ ...prev, archivo_fondo: null }));
        } finally {
            setIsCompressing(false);
            if (bgFileInputRef.current) bgFileInputRef.current.value = '';
        }
    };

    const handleRemoveBg = () => {
        setData(prev => ({ ...prev, archivo_fondo: null, remove_fondo: true }));
        setSelectedBg('none');
        applyBackgroundCSS('none');
        localStorage.setItem('bg_base', 'none');
        window.dispatchEvent(new Event('theme-changed'));
        if (bgFileInputRef.current) bgFileInputRef.current.value = '';
    };

    const persistThemeToStorage = useCallback((overrides = {}) => {
        const theme = {
            modo:   overrides.modo   ?? (isDarkMode ? 'dark' : 'light'),
            color:  overrides.color  ?? selectedColor,
            bg:     overrides.bg     ?? selectedBg,
            font:   overrides.font   ?? typography,
            scale:  overrides.scale  ?? fontScale,
            glass:  overrides.glass  ?? glassEffect,
            layout: overrides.layout ?? sidebarLayout,
            sidebarMode: overrides.sidebarMode ?? sidebarMode,
            fixedPosition: overrides.fixedPosition ?? fixedPosition,
        };

        localStorage.setItem('theme', theme.modo);
        localStorage.setItem('theme_color', theme.color);
        localStorage.setItem('bg_base', theme.bg);
        localStorage.setItem('theme_font', theme.font);
        localStorage.setItem(FONT_SCALE_STORAGE_KEY, String(clampFontScale(theme.scale)));
        localStorage.setItem('theme_glass', String(theme.glass));
        localStorage.setItem('theme_layout', theme.layout);
        localStorage.setItem('theme_sidebar_mode', theme.sidebarMode);
        localStorage.setItem('theme_fixed_position', theme.fixedPosition);
        window.dispatchEvent(new Event('theme-changed'));
    }, [isDarkMode, selectedColor, selectedBg, typography, fontScale, glassEffect, sidebarLayout, sidebarMode, fixedPosition]);

    // --- SUBMIT: Blindado con JSON.stringify ---
    const submitProfile = (e) => {
        if (e) e.preventDefault();
        setSaveStatus(null);

        const configJSON = {
            modo:             isDarkMode ? 'dark' : 'light',
            color_nombre:     selectedColor,
            fondo_base:       data.archivo_fondo ? 'subiendo_archivo' : selectedBg,
            fuente_principal: typography,
            escala_fuente:    fontScale,
            layout_sidebar:   sidebarLayout,
            sidebar_modo:    sidebarMode,
            sidebar_posicion_fija: fixedPosition,
            efecto_cristal:   glassEffect,
            alertas_prefs:    alertasPrefs,
        };

        transform((data) => ({
            ...data,
            password:              data.password || '',
            password_confirmation: data.password_confirmation || '',
            tema_visual:           JSON.stringify(configJSON)
        }));

        post(route('profile.update'), {
            forceFormData:  true,
            preserveScroll: true,
            onSuccess: () => {
                themeSavedRef.current = true;
                const hadBgUpload = Boolean(data.archivo_fondo);

                persistThemeToStorage();

                router.reload({
                    only: ['auth', 'tema_visual', 'perfilUsuario'],
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: (page) => {
                        const tv = page.props.tema_visual || {};
                        if (hadBgUpload && tv.fondo_base) {
                            localStorage.setItem('bg_base', tv.fondo_base);
                            setSelectedBg(tv.fondo_base);
                            applyBackgroundCSS(tv.fondo_base);
                        }
                        const syncedAlertas = mergeAlertasPrefs(tv.alertas_prefs);
                        setAlertasPrefs(syncedAlertas);
                        persistAlertasPrefsToStorage(syncedAlertas);
                        themeSnapshotRef.current = captureThemeSnapshot();
                        window.dispatchEvent(new Event('theme-changed'));
                    },
                });

                setSaveStatus('success');
                if (fileInputRef.current)   fileInputRef.current.value   = '';
                if (bgFileInputRef.current) bgFileInputRef.current.value = '';
                setIsAvatarModalOpen(false);
                setIsBgModalOpen(false);
                setTimeout(() => setSaveStatus(null), 4000);
            },
            onError: (errs) => {
                if (errs?.foto_perfil) {
                    showFileAlert('Foto de perfil', errs.foto_perfil);
                } else if (errs?.archivo_fondo) {
                    showFileAlert('Fondo de pantalla', errs.archivo_fondo);
                }
                setSaveStatus('error');
                setTimeout(() => setSaveStatus(null), 5000);
            },
        });
    };

    const getBackgroundType = (bg) => {
        if (!bg || bg === 'none')                                       return 'Sin Fondo';
        if (bg.startsWith('#'))                                         return 'Color Sólido';
        if (bg.startsWith('data:image') || bg.startsWith('/storage'))  return 'Imagen Personalizada';
        return 'Diseño Vectorial';
    };

    useEffect(() => {
        const syncThemeState = () => {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) setIsDarkMode(savedTheme === 'dark');

            const savedLayout = localStorage.getItem('theme_layout');
            if (savedLayout) setSidebarLayout(savedLayout);
        };
        window.addEventListener('theme-changed', syncThemeState);
        return () => window.removeEventListener('theme-changed', syncThemeState);
    }, []);

    useEffect(() => {
        animate(
            '.fade-up',
            { translateY: [15, 0], opacity: [0, 1] },
            { easing: 'easeOutExpo', duration: 600, delay: (el, i) => i * 80 }
        );
    }, []);

    const handleFontScaleStep = (direction) => {
        setFontScale((prev) => {
            const next = clampFontScale(prev + direction * FONT_SCALE_STEP);
            applyFontScaleToRoot(next);
            localStorage.setItem(FONT_SCALE_STORAGE_KEY, String(next));
            window.dispatchEvent(new Event('theme-changed'));
            return next;
        });
    };

    const handleColorChange = (colorValue, isHex = false) => {
        const stored = isHex ? colorValue : colorValue;
        const hex = resolveAccentHex(stored);
        setSelectedColor(stored);
        document.documentElement.style.setProperty('--color-primario', hex);
        localStorage.setItem('theme_color', stored);
        window.dispatchEvent(new Event('theme-changed'));
    };

    const handleModeChange = (modo) => {
        const isDark = modo === 'dark';
        setIsDarkMode(isDark);
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        window.dispatchEvent(new Event('theme-changed'));
    };

    const handleFontChange = (fontValue) => {
        setTypography(fontValue);
        document.documentElement.style.setProperty('--font-principal', fontFamilies[fontValue] || fontFamilies.inter);
        localStorage.setItem('theme_font', fontValue);
        window.dispatchEvent(new Event('theme-changed'));
    };

    const handleGlassChange = (isActive) => {
        setGlassEffect(isActive);
        const root = document.documentElement;
        isActive ? root.classList.add('glass-active') : root.classList.remove('glass-active');
        localStorage.setItem('theme_glass', String(isActive));
        window.dispatchEvent(new Event('theme-changed'));
    };

    const handleLayoutChange = (layout) => {
        setSidebarLayout(layout);
        localStorage.setItem('theme_layout', layout);
        window.dispatchEvent(new CustomEvent('theme-layout-preview', { detail: { layout } }));
        window.dispatchEvent(new Event('theme-changed'));
    };

    const handleSidebarModeChange = (mode) => {
        setSidebarMode(mode);
        localStorage.setItem('theme_sidebar_mode', mode);
        window.dispatchEvent(new CustomEvent('theme-sidebar-mode-preview', { detail: { mode } }));
        window.dispatchEvent(new Event('theme-changed'));
    };

    const handleFixedPositionChange = (position) => {
        setFixedPosition(position);
        localStorage.setItem('theme_fixed_position', position);
        window.dispatchEvent(new CustomEvent('theme-fixed-position-preview', { detail: { position } }));
        window.dispatchEvent(new Event('theme-changed'));
    };

    const handleBgChange = (bgValue) => {
        setSelectedBg(bgValue);
        setData('remove_fondo', false);
        applyBackgroundCSS(bgValue);
        localStorage.setItem('bg_base', bgValue);
        window.dispatchEvent(new Event('theme-changed'));
    };

    const applyPreset = (preset) => {
        const scale = preset.escala !== undefined ? clampFontScale(preset.escala) : fontScale;
        const isDark = preset.modo === 'dark';
        const color = preset.colorNombre || preset.colorHex;
        const glass = preset.glass !== undefined ? preset.glass : glassEffect;
        const layout = preset.layout !== undefined ? preset.layout : sidebarLayout;
        const font = preset.font !== undefined ? preset.font : typography;

        setIsDarkMode(isDark);
        setSelectedColor(color);
        setSelectedBg(preset.bg);
        setTypography(font);
        setFontScale(scale);
        setGlassEffect(glass);
        setSidebarLayout(layout);
        if (preset.sound !== undefined) {
            updateAlertasPrefs((prev) => ({
                ...prev,
                canales: { ...prev.canales, sonido: preset.sound },
            }));
        }

        document.documentElement.style.setProperty('--color-primario', resolveAccentHex(color));
        document.documentElement.style.setProperty('--font-principal', fontFamilies[font] || fontFamilies.inter);
        applyBackgroundCSS(preset.bg);
        applyFontScaleToRoot(scale);
        glass
            ? document.documentElement.classList.add('glass-active')
            : document.documentElement.classList.remove('glass-active');

        persistThemeToStorage({
            modo: isDark ? 'dark' : 'light',
            color,
            bg: preset.bg,
            font,
            scale,
            glass,
            layout,
            sidebarMode,
            fixedPosition,
        });
        window.dispatchEvent(new CustomEvent('theme-layout-preview', { detail: { layout } }));
        window.dispatchEvent(new CustomEvent('theme-sidebar-mode-preview', { detail: { mode: sidebarMode } }));
        window.dispatchEvent(new CustomEvent('theme-fixed-position-preview', { detail: { position: fixedPosition } }));
    };

    const baseCardClass   = "fade-up theme-surface rounded-[2.5rem] relative z-10 transition-all duration-300";
    const glassCardClass  = "bg-white/75 dark:bg-[#121212]/75 backdrop-blur-[24px] border-[1.5px] border-white/80 dark:border-zinc-700/60 shadow-[0_12px_40px_rgba(0,0,0,0.12)] dark:shadow-[0_12px_40px_rgba(0,0,0,0.6)]";
    const solidCardClass  = "bg-white dark:bg-[#121212] border border-zinc-200 dark:border-zinc-800 shadow-[0_12px_40px_rgba(0,0,0,0.08)] dark:shadow-[0_12px_40px_rgba(0,0,0,0.5)]";
    const activeCardClass = `${baseCardClass} ${glassEffect ? glassCardClass : solidCardClass}`;

    return (
        <AppLayout>
            <Head title="Mi Perfil | GELIANV" />
            <GeliaLoader isVisible={processing || isCompressing} message={isCompressing ? 'Optimizando imagen_' : 'Guardando cambios_'} />

            {/* ---- FEEDBACK CENTRADO (MODAL PREMIUM CON LOGO FLUIDO) ---- */}
            {saveStatus && createPortal(
                <div 
                    className="fixed inset-0 z-[99998] flex items-center justify-center p-4 bg-black/50 backdrop-blur-md animate-fade-in"
                    onClick={() => setSaveStatus(null)}
                >
                    <div
                        className={`relative w-full max-w-sm sm:max-w-md flex flex-col items-center gap-6 p-8 sm:p-12 rounded-[2.5rem] shadow-[0_0_60px_rgba(0,0,0,0.4)] border-2 backdrop-blur-xl animate-fade-in
                            ${saveStatus === 'success'
                                ? 'bg-white dark:bg-[#111] border-[var(--color-primario)]/40'
                                : 'bg-white dark:bg-[#111] border-red-400/40'
                            }`}
                        onClick={e => e.stopPropagation()}
                    >
                        <div className={`absolute inset-0 rounded-[2.5rem] opacity-10 pointer-events-none
                            ${saveStatus === 'success' ? 'bg-[var(--color-primario)]' : 'bg-red-400'}`}
                        />
                        
                        <div className={`relative z-10 w-20 h-20 sm:w-24 sm:h-24 rounded-full flex items-center justify-center shadow-xl
                            ${saveStatus === 'success' ? 'bg-[var(--color-primario)]/15' : 'bg-red-500/15'}`}>
                            {saveStatus === 'success'
                                ? <GeliaLogo variant="fluid-fill" className="w-12 h-12 sm:w-14 sm:h-14 drop-shadow-lg" />
                                : <XCircle className="w-10 h-10 sm:w-12 sm:h-12 text-red-500" />
                            }
                        </div>

                        <div className="relative z-10 text-center space-y-2">
                            <h3 className={`text-xl sm:text-2xl font-black uppercase italic tracking-tighter m-0
                                ${saveStatus === 'success' ? 'text-[var(--color-primario)]' : 'text-red-600 dark:text-red-400'}`}>
                                {saveStatus === 'success' ? '¡Identidad Actualizada!' : 'Algo salió mal'}
                            </h3>
                            <p className="text-sm font-bold theme-text-muted leading-snug">
                                {saveStatus === 'success'
                                    ? 'Tu perfil y configuración visual han sido aplicados correctamente en el sistema.'
                                    : 'No se pudieron guardar los cambios. Revisa los campos e intenta de nuevo.'}
                            </p>
                        </div>

                        <button
                            onClick={() => setSaveStatus(null)}
                            className={`relative z-10 w-full py-4 rounded-2xl text-white font-black uppercase tracking-widest text-[11px] transition-all hover:scale-105 shadow-lg outline-none
                                ${saveStatus === 'success' ? 'opacity-90 hover:opacity-100' : 'bg-red-500 hover:bg-red-600'}`}
                            style={saveStatus === 'success' ? { backgroundColor: 'var(--color-primario)' } : {}}
                        >
                            {saveStatus === 'success' ? 'Continuar_' : 'Entendido_'}
                        </button>

                        <button
                            onClick={() => setSaveStatus(null)}
                            className="absolute top-4 right-4 z-10 p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>
                </div>,
                document.body
            )}

            {fileAlert && createPortal(
                <div
                    className="fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/50 backdrop-blur-md animate-fade-in"
                    onClick={() => setFileAlert(null)}
                >
                    <div
                        className="relative w-full max-w-sm sm:max-w-md flex flex-col items-center gap-6 p-8 sm:p-10 rounded-[2.5rem] shadow-[0_0_60px_rgba(0,0,0,0.4)] border-2 border-red-400/40 bg-white dark:bg-[#111] backdrop-blur-xl animate-fade-in"
                        onClick={e => e.stopPropagation()}
                    >
                        <div className="relative z-10 w-20 h-20 rounded-full flex items-center justify-center shadow-xl bg-red-500/15">
                            <AlertTriangle className="w-10 h-10 text-red-500" />
                        </div>
                        <div className="relative z-10 text-center space-y-2">
                            <h3 className="text-xl font-black uppercase italic tracking-tighter m-0 text-red-600 dark:text-red-400">
                                {fileAlert.title}
                            </h3>
                            <p className="text-sm font-bold theme-text-muted leading-snug">{fileAlert.message}</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setFileAlert(null)}
                            className="relative z-10 w-full py-4 rounded-2xl text-white font-black uppercase tracking-widest text-[11px] transition-all hover:scale-105 shadow-lg outline-none bg-red-500 hover:bg-red-600"
                        >
                            Entendido_
                        </button>
                        <button
                            type="button"
                            onClick={() => setFileAlert(null)}
                            className="absolute top-4 right-4 z-10 p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>
                </div>,
                document.body
            )}

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">

                {/* --- HEADER --- */}
                <header className={`${activeCardClass} p-8 md:p-12 flex flex-col gap-8 md:gap-10`}>
                    <div className="flex flex-col md:flex-row items-center md:items-start gap-8 md:gap-12">
                        <div className="relative flex flex-col items-center gap-2 shrink-0">
                            <div className="relative group shrink-0 cursor-pointer" onClick={() => setIsAvatarModalOpen(true)}>
                                <div className="w-32 h-32 md:w-40 md:h-40 rounded-[2rem] overflow-hidden border-[4px] shadow-2xl transition-transform duration-300 group-hover:scale-105 bg-[var(--color-primario)] flex items-center justify-center" style={{ borderColor: 'var(--color-primario)' }}>
                                    {imagePreview && !avatarLoadFailed ? (
                                        <img
                                            src={imagePreview}
                                            alt="Perfil"
                                            className="w-full h-full object-cover"
                                            onError={() => {
                                                setAvatarLoadFailed(true);
                                                showFileAlert(
                                                    'Imagen no disponible',
                                                    'No se pudo cargar la foto de perfil. Verifica que el archivo exista o sube una nueva imagen.'
                                                );
                                            }}
                                        />
                                    ) : (
                                        <span className="text-5xl md:text-7xl font-black text-white">{initialChar}</span>
                                    )}
                                </div>
                                <button type="button" className="absolute -bottom-2 -right-2 p-3 text-white rounded-2xl transition-all shadow-xl group-hover:scale-110 z-10 outline-none border-4 border-white dark:border-[#121212]" style={{ backgroundColor: 'var(--color-primario)' }}>
                                    <Camera className="w-5 h-5" />
                                </button>
                            </div>
                            {errors.foto_perfil && <p className="text-[10px] text-red-500 font-bold max-w-[120px] text-center m-0 mt-2">{errors.foto_perfil}</p>}
                        </div>

                        <div className="text-center md:text-left flex-1 w-full space-y-4 pt-2">
                            <div>
                                <div className="flex items-center justify-center md:justify-start mb-4">
                                    <div className="w-8 h-1.5 rounded-full mr-3" style={{ backgroundColor: 'var(--color-primario)' }}></div>
                                    <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted drop-shadow-sm">
                                        PROTOCOLO DE IDENTIDAD_
                                    </span>
                                </div>
                                <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0 p-0">
                                    HOLA, <span style={{ color: 'var(--color-primario)' }}>{usuario?.name ? `${usuario.name} ${usuario.apellido_paterno || ''}`.trim() : 'USUARIO'}</span>
                                </h1>
                            </div>
                            <div className="flex items-center justify-center md:justify-start gap-2 theme-text-muted mt-2">
                                <ShieldCheck className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                                <p className="text-xs font-bold tracking-wide m-0">Miembro desde: {usuario?.created_at ? new Date(usuario.created_at).toLocaleDateString() : '2026'}</p>
                            </div>
                        </div>
                    </div>

                    <div className="w-full pt-6 border-t border-zinc-200/60 dark:border-zinc-700/50 flex flex-col md:flex-row items-center justify-between gap-6">
                        <div className="flex items-center gap-4 w-full md:w-auto">
                            <div className="p-3 bg-yellow-500/20 text-yellow-600 dark:text-yellow-400 rounded-2xl shrink-0">
                                <AlertTriangle className="w-6 h-6" />
                            </div>
                            <div>
                                <h3 className="text-sm font-black theme-text-main uppercase tracking-widest">Modo de Vista Previa_</h3>
                                <p className="text-[11px] font-bold theme-text-muted mt-1 leading-tight max-w-xl">
                                    Las selecciones visuales en esta pantalla son solo temporales. Para que los cambios apliquen y permanezcan en tu cuenta de forma definitiva, asegúrate de guardar.
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-col items-center md:items-end shrink-0 w-full md:w-auto">
                            <button type="button" onClick={submitProfile} disabled={processing} className="w-full md:w-auto py-4 px-10 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] transition-all hover:scale-105 shadow-xl hover:shadow-2xl flex justify-center items-center gap-3 disabled:opacity-60 disabled:cursor-not-allowed disabled:scale-100 outline-none border border-black/10" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Save className="w-5 h-5" /> {processing ? 'Guardando...' : 'Guardar cambios'}
                            </button>
                        </div>
                    </div>
                </header>

                {/* --- SECCIÓN DATOS --- */}
                <section className={`${activeCardClass} p-8 md:p-10 space-y-8`}>
                    <div className="flex items-center gap-3">
                        <Settings2 className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">Ajustes de Cuenta_</h2>
                    </div>

                    {/* ZONA 1: Datos personales */}
                    <div>
                        <p className="text-[10px] font-black uppercase tracking-widest mb-4 ml-1 drop-shadow-sm" style={{ color: 'var(--color-primario)' }}>
                            Datos Personales
                        </p>
                        <div className={`border border-dashed rounded-[1.5rem] p-6 sm:p-8 grid grid-cols-1 md:grid-cols-2 gap-6 transition-colors ${glassEffect ? 'border-zinc-300 dark:border-zinc-700 bg-black/5 dark:bg-black/20 shadow-inner' : 'border-zinc-300 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-900/60'}`}>

                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Nombre(s)</label>
                                <div className="relative">
                                    <User className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                    <input type="text" value={data.name} onChange={e => setData('name', e.target.value)}
                                        className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                        onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                </div>
                                {errors.name && <p className="text-xs text-red-500 m-0 mt-1 px-2">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Apellido Paterno</label>
                                <div className="relative">
                                    <User className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                    <input type="text" value={data.apellido_paterno} onChange={e => setData('apellido_paterno', e.target.value)}
                                        className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                        onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                </div>
                                {errors.apellido_paterno && <p className="text-xs text-red-500 m-0 mt-1 px-2">{errors.apellido_paterno}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Apellido Materno</label>
                                <div className="relative">
                                    <User className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                    <input type="text" value={data.apellido_materno} onChange={e => setData('apellido_materno', e.target.value)}
                                        className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                        onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                </div>
                                {errors.apellido_materno && <p className="text-xs text-red-500 m-0 mt-1 px-2">{errors.apellido_materno}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Teléfono</label>
                                <div className="relative">
                                    <Smartphone className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                    <input type="text" value={data.telefono} onChange={e => setData('telefono', e.target.value)}
                                        className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                        onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                </div>
                                {errors.telefono && <p className="text-xs text-red-500 m-0 mt-1 px-2">{errors.telefono}</p>}
                            </div>
                        </div>
                    </div>

                    {/* ZONA 2: Datos sensibles */}
                    <div>
                        <button
                            type="button"
                            onClick={() => setShowSensitive(v => !v)}
                            className={`w-full flex items-center justify-between px-5 py-4 rounded-2xl border transition-all duration-200 outline-none group
                                ${showSensitive
                                    ? 'border-[var(--color-primario)]/40 bg-[var(--color-primario)]/5'
                                    : 'theme-border theme-surface hover:border-[var(--color-primario)]/30'
                                }`}
                        >
                            <div className="flex items-center gap-3">
                                <div className="p-2 rounded-xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                                    <Lock className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                                </div>
                                <div className="text-left">
                                    <span className="text-sm font-black theme-text-main uppercase tracking-widest block leading-tight">Datos Sensibles</span>
                                    <span className="text-[10px] font-bold theme-text-muted uppercase tracking-widest">Contraseña · Fecha de nacimiento</span>
                                </div>
                            </div>
                            <ChevronDown className={`w-5 h-5 theme-text-muted transition-transform duration-300 ${showSensitive ? 'rotate-180' : ''}`} />
                        </button>

                        {showSensitive && (
                            <div className={`mt-3 border border-dashed rounded-[1.5rem] p-6 sm:p-8 grid grid-cols-1 md:grid-cols-2 gap-6 transition-colors ${glassEffect ? 'border-zinc-300 dark:border-zinc-700 bg-black/5 dark:bg-black/20 shadow-inner' : 'border-zinc-300 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-900/60'}`}>

                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Fecha de Nacimiento</label>
                                    <div className="relative">
                                        <CalendarDays className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                        <input type="date" value={data.fecha_nacimiento} onChange={e => setData('fecha_nacimiento', e.target.value)}
                                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                            onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                    </div>
                                    {errors.fecha_nacimiento && <p className="text-xs text-red-500 m-0 mt-1 px-2">{errors.fecha_nacimiento}</p>}
                                </div>

                                <div className="hidden md:block" />

                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Nueva Contraseña</label>
                                    <div className="relative">
                                        <KeyRound className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                        <input
                                            type={showPassword ? 'text' : 'password'}
                                            value={data.password}
                                            onChange={e => setData('password', e.target.value)}
                                            placeholder="Dejar vacío para no cambiar"
                                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                            onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                        <button type="button" onClick={() => setShowPassword(v => !v)}
                                            className="absolute right-4 top-1/2 -translate-y-1/2 theme-text-muted hover:theme-text-main transition-colors outline-none">
                                            {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                                        </button>
                                    </div>
                                    {errors.password && <p className="text-xs text-red-500 m-0 mt-1 px-2">{errors.password}</p>}
                                </div>

                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Confirmar Contraseña</label>
                                    <div className="relative">
                                        <KeyRound className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                        <input
                                            type={showConfirm ? 'text' : 'password'}
                                            value={data.password_confirmation}
                                            onChange={e => setData('password_confirmation', e.target.value)}
                                            placeholder="Repite la nueva contraseña"
                                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                            onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                        <button type="button" onClick={() => setShowConfirm(v => !v)}
                                            className="absolute right-4 top-1/2 -translate-y-1/2 theme-text-muted hover:theme-text-main transition-colors outline-none">
                                            {showConfirm ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                                        </button>
                                    </div>
                                    {errors.password_confirmation && <p className="text-xs text-red-500 m-0 mt-1 px-2">{errors.password_confirmation}</p>}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* ZONA 3: Info institucional */}
                    <div>
                        <button
                            type="button"
                            onClick={() => setShowInstitutional(v => !v)}
                            className={`w-full flex items-center justify-between px-5 py-4 rounded-2xl border transition-all duration-200 outline-none group
                                ${showInstitutional
                                    ? 'border-[var(--color-primario)]/40 bg-[var(--color-primario)]/5'
                                    : 'theme-border theme-surface hover:border-[var(--color-primario)]/30'
                                }`}
                        >
                            <div className="flex items-center gap-3">
                                <div className="p-2 rounded-xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                                    <Building2 className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                                </div>
                                <div className="text-left">
                                    <span className="text-sm font-black theme-text-main uppercase tracking-widest block leading-tight">Información Institucional</span>
                                    <span className="text-[10px] font-bold theme-text-muted uppercase tracking-widest">Solo lectura · Asignada por administración</span>
                                </div>
                            </div>
                            <ChevronDown className={`w-5 h-5 theme-text-muted transition-transform duration-300 ${showInstitutional ? 'rotate-180' : ''}`} />
                        </button>

                        {showInstitutional && (
                            <div className={`mt-3 border border-dashed rounded-[1.5rem] p-6 sm:p-8 grid grid-cols-1 md:grid-cols-3 gap-6 transition-colors ${glassEffect ? 'border-zinc-300 dark:border-zinc-700 bg-black/5 dark:bg-black/20 shadow-inner' : 'border-zinc-300 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-900/60'}`}>

                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Correo Institucional</label>
                                    <div className="relative">
                                        <Mail className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                        <input type="email" value={data.email} readOnly
                                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-muted text-sm font-bold cursor-not-allowed opacity-60 shadow-sm" />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Área</label>
                                    <div className="relative">
                                        <MapPin className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                        <input type="text"
                                            value={usuario?.area?.nombre || 'Sin área asignada'}
                                            readOnly
                                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-muted text-sm font-bold cursor-not-allowed opacity-60 shadow-sm" />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Departamento</label>
                                    <div className="relative">
                                        <Building2 className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                        <input type="text"
                                            value={usuario?.area?.departamento?.nombre || 'Sin departamento'}
                                            readOnly
                                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-muted text-sm font-bold cursor-not-allowed opacity-60 shadow-sm" />
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </section>

                {/* --- SECCIÓN PRESETS --- */}
                <section className={`${activeCardClass} p-8 md:p-10 space-y-8`}>
                    <div className="flex items-center gap-3">
                        <Layers className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                            Temas Predefinidos_
                        </h2>
                    </div>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        {presets.map((preset, idx) => {
                            const isPresetDark = preset.modo === 'dark';
                            return (
                                <button
                                    key={preset.id || preset.slug || idx} type="button" onClick={() => applyPreset(preset)}
                                    className="p-6 rounded-[2rem] flex flex-col items-start gap-4 hover:scale-[1.03] hover:shadow-2xl transition-all text-left shadow-md group relative overflow-hidden border-2"
                                    style={{ backgroundColor: isPresetDark ? '#111113' : '#f8f9fa', borderColor: preset.colorHex + '55' }}
                                >
                                    <div className="absolute -top-6 -right-6 w-24 h-24 rounded-full opacity-20 group-hover:opacity-40 transition-opacity blur-2xl" style={{ backgroundColor: preset.colorHex }} />
                                    <div className="w-full flex justify-between items-center relative z-10">
                                        <div className="w-7 h-7 rounded-full shadow-lg group-hover:scale-110 transition-transform ring-2 ring-white/20" style={{ backgroundColor: preset.colorHex }} />
                                        <span className="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-full" style={{ backgroundColor: preset.colorHex + '22', color: preset.colorHex }}>{preset.modo}</span>
                                    </div>
                                    <div className="relative z-10">
                                        <span className="text-sm font-black block mb-1" style={{ color: isPresetDark ? '#ffffff' : '#111113' }}>{preset.name}</span>
                                        <span className="text-[11px] font-bold" style={{ color: isPresetDark ? 'rgba(255,255,255,0.5)' : 'rgba(0,0,0,0.45)' }}>Aplicar configuración completa</span>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </section>

                {/* --- SECCIÓN PERSONALIZACIÓN VISUAL --- */}
                <section className={`${activeCardClass} p-8 md:p-10 space-y-8`}>
                    <div className="flex items-center gap-3">
                        <Palette className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                            Interfaz y Colores_
                        </h2>
                    </div>

                    <div className="flex flex-col gap-2">
                        <SettingsRow icon={isDarkMode ? Moon : Sun} title="Modo de aplicación" subtitle="Esquema claro u oscuro" stackOnMobile={true}>
                            <div className="gelia-segment w-full sm:w-auto p-1 h-12 shadow-sm">
                                <button type="button" className="gelia-segment-btn" data-active={!isDarkMode} onClick={() => handleModeChange('light')}>
                                    <Sun className="w-4 h-4" /> Claro
                                </button>
                                <button type="button" className="gelia-segment-btn" data-active={isDarkMode} onClick={() => handleModeChange('dark')}>
                                    <Moon className="w-4 h-4" /> Oscuro
                                </button>
                            </div>
                        </SettingsRow>

                        <SettingsRow icon={PaintBucket} title="Color de énfasis" subtitle="Color primario del sistema" stackOnMobile={true}>
                            <div className="flex flex-wrap gap-4 items-center justify-start sm:justify-end w-full sm:w-auto">
                                {Object.entries(accentColors).map(([name, hex]) => (
                                    <button
                                        key={name} type="button" onClick={() => handleColorChange(name)}
                                        className={`w-8 h-8 sm:w-10 sm:h-10 rounded-full transition-all outline-none ${selectedColor === name ? 'ring-4 ring-offset-4 dark:ring-offset-[#141414] shadow-lg scale-110' : 'opacity-40 hover:opacity-100 hover:scale-110 hover:shadow-md'}`}
                                        style={{ backgroundColor: hex, '--tw-ring-color': hex }} title={name}
                                    />
                                ))}
                                <div className="w-[2px] h-8 bg-zinc-300 dark:bg-zinc-700 mx-2 rounded-full"></div>
                                <label className="relative w-10 h-10 rounded-full border-2 border-dashed border-zinc-400 dark:border-zinc-500 flex items-center justify-center cursor-pointer hover:scale-110 hover:shadow-md hover:border-[var(--color-primario)] transition-all overflow-hidden bg-transparent" title="Elegir color personalizado">
                                    <Palette className="w-4 h-4 theme-text-main z-10 pointer-events-none" />
                                    <input type="color" className="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-20" onChange={(e) => handleColorChange(e.target.value, true)} />
                                </label>
                            </div>
                        </SettingsRow>

                        <SettingsRow icon={Droplet} title="Efectos de transparencia" subtitle="Aplica el efecto traslúcido (Glassmorphism)" stackOnMobile={false}>
                            <button type="button" className="gelia-switch shrink-0 scale-125 origin-right shadow-sm" data-active={glassEffect} onClick={() => handleGlassChange(!glassEffect)}>
                                <div className="gelia-switch-thumb shadow-md" />
                            </button>
                        </SettingsRow>

                        <SettingsRow icon={Type} title="Fuente del sistema" subtitle="Tipografía principal de la interfaz" stackOnMobile={true}>
                            <div className="relative w-full sm:w-64">
                                <select
                                    value={typography}
                                    onChange={(e) => handleFontChange(e.target.value)}
                                    className="w-full pl-5 pr-10 py-3 text-sm font-bold theme-surface border border-zinc-300 dark:border-zinc-700 rounded-xl theme-text-main outline-none cursor-pointer focus:ring-2 transition-all appearance-none shadow-sm hover:shadow-md"
                                    style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'}
                                    onBlur={(e)  => e.target.style.borderColor = ''}
                                >
                                    <option value="inter">Inter (Predeterminada)</option>
                                    <option value="montserrat">Montserrat (Corporativa)</option>
                                    <option value="poppins">Poppins (Moderna)</option>
                                    <option value="nunito">Nunito (Amigable)</option>
                                    <option value="roboto">Roboto (Datos/Tablas)</option>
                                    <option value="mono">JetBrains Mono (Técnica)</option>
                                </select>
                                <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                                    <svg className="w-4 h-4 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                        </SettingsRow>

                        <SettingsRow icon={Type} title="Tamaño de letra" subtitle={`${formatFontScaleLabel(FONT_SCALE_MIN)} – ${formatFontScaleLabel(FONT_SCALE_MAX)} · vista previa temporal`} border={false} stackOnMobile={true}>
                            <div className="flex items-center gap-2 w-full sm:w-auto justify-between sm:justify-end">
                                <button
                                    type="button"
                                    onClick={() => handleFontScaleStep(-1)}
                                    disabled={fontScale <= FONT_SCALE_MIN}
                                    className="w-11 h-11 rounded-xl theme-element border theme-border flex items-center justify-center transition-all hover:scale-105 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:scale-100 outline-none shadow-sm"
                                    title="Reducir tamaño"
                                    aria-label="Reducir tamaño de letra"
                                >
                                    <Minus className="w-5 h-5 theme-text-main" />
                                </button>
                                <span className="min-w-[4.5rem] text-center text-sm font-black theme-text-main tabular-nums px-2">
                                    {formatFontScaleLabel(fontScale)}
                                </span>
                                <button
                                    type="button"
                                    onClick={() => handleFontScaleStep(1)}
                                    disabled={fontScale >= FONT_SCALE_MAX}
                                    className="w-11 h-11 rounded-xl theme-element border theme-border flex items-center justify-center transition-all hover:scale-105 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:scale-100 outline-none shadow-sm"
                                    title="Aumentar tamaño"
                                    aria-label="Aumentar tamaño de letra"
                                >
                                    <Plus className="w-5 h-5 theme-text-main" />
                                </button>
                            </div>
                        </SettingsRow>
                    </div>
                </section>

                {/* --- SECCIÓN NAVEGACIÓN --- */}
                <section className={`${activeCardClass} p-8 md:p-10 space-y-8`}>
                    <div className="flex items-center gap-3">
                        <PanelLeft className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                            Navegación & Alertas_
                        </h2>
                    </div>

                    <div className="flex flex-col gap-2">
                        <SettingsRow icon={PanelLeft} title="Disposición del Sidebar" subtitle="Formato lateral en pantallas grandes" stackOnMobile={true}>
                            <div className="gelia-segment w-full sm:w-auto p-1 h-12 shadow-sm">
                                <button type="button" onClick={() => handleLayoutChange('fixed')} className="gelia-segment-btn px-6" data-active={sidebarLayout === 'fixed'}>
                                    Fijo
                                </button>
                                <button type="button" onClick={() => handleLayoutChange('floating_left')} className="gelia-segment-btn px-6" data-active={sidebarLayout === 'floating_left'}>
                                    Flot. Izq
                                </button>
                                <button type="button" onClick={() => handleLayoutChange('floating_right')} className="gelia-segment-btn px-6" data-active={sidebarLayout === 'floating_right'}>
                                    Flot. Der
                                </button>
                            </div>
                        </SettingsRow>

                        {sidebarLayout === 'fixed' && (
                            <SettingsRow icon={PanelLeft} title="Posición barra fija" subtitle="Como la barra de tareas: borde de pantalla" stackOnMobile={true}>
                                <div className="gelia-segment w-full sm:w-auto p-1 h-12 shadow-sm flex-wrap sm:flex-nowrap">
                                    <button type="button" onClick={() => handleFixedPositionChange('left')} className="gelia-segment-btn px-4 sm:px-5" data-active={fixedPosition === 'left'}>
                                        Izquierda
                                    </button>
                                    <button type="button" onClick={() => handleFixedPositionChange('right')} className="gelia-segment-btn px-4 sm:px-5" data-active={fixedPosition === 'right'}>
                                        Derecha
                                    </button>
                                    <button type="button" onClick={() => handleFixedPositionChange('top')} className="gelia-segment-btn px-4 sm:px-5" data-active={fixedPosition === 'top'}>
                                        Arriba
                                    </button>
                                    <button type="button" onClick={() => handleFixedPositionChange('bottom')} className="gelia-segment-btn px-4 sm:px-5" data-active={fixedPosition === 'bottom'}>
                                        Abajo
                                    </button>
                                </div>
                            </SettingsRow>
                        )}

                        <SettingsRow icon={PanelLeft} title="Estado del Sidebar" subtitle="Contraído al pasar el mouse o siempre desplegado" stackOnMobile={true}>
                            <div className="gelia-segment w-full sm:w-auto p-1 h-12 shadow-sm">
                                <button type="button" onClick={() => handleSidebarModeChange('collapsed')} className="gelia-segment-btn px-5 sm:px-6" data-active={sidebarMode === 'collapsed'}>
                                    Contraída
                                </button>
                                <button type="button" onClick={() => handleSidebarModeChange('expanded')} className="gelia-segment-btn px-5 sm:px-6" data-active={sidebarMode === 'expanded'}>
                                    Desplegada
                                </button>
                            </div>
                        </SettingsRow>

                        <SettingsRow icon={Volume2} title="Timbres de alerta" subtitle="Reproducir sonido al recibir alertas" stackOnMobile={false}>
                            <button type="button" className="gelia-switch shrink-0 scale-125 origin-right shadow-sm" data-active={alertasPrefs.canales.sonido} onClick={() => toggleCanalAlerta('sonido')}>
                                <div className="gelia-switch-thumb shadow-md" />
                            </button>
                        </SettingsRow>

                        <SettingsRow icon={Mic} title="Texto a voz" subtitle="Leer alertas en voz alta" stackOnMobile={false}>
                            <button type="button" className="gelia-switch shrink-0 scale-125 origin-right shadow-sm" data-active={alertasPrefs.canales.voz} onClick={() => toggleCanalAlerta('voz')}>
                                <div className="gelia-switch-thumb shadow-md" />
                            </button>
                        </SettingsRow>

                        <SettingsRow icon={Monitor} title="Notificaciones de escritorio" subtitle="Alertas nativas del sistema operativo" stackOnMobile={false}>
                            <button type="button" className="gelia-switch shrink-0 scale-125 origin-right shadow-sm" data-active={alertasPrefs.canales.escritorio} onClick={() => toggleCanalAlerta('escritorio')}>
                                <div className="gelia-switch-thumb shadow-md" />
                            </button>
                        </SettingsRow>

                        <SettingsRow icon={Bell} title="Notificaciones GeliaNV" subtitle="Toasts flotantes en la aplicación" stackOnMobile={false}>
                            <button type="button" className="gelia-switch shrink-0 scale-125 origin-right shadow-sm" data-active={alertasPrefs.canales.app} onClick={() => toggleCanalAlerta('app')}>
                                <div className="gelia-switch-thumb shadow-md" />
                            </button>
                        </SettingsRow>

                        <SettingsRow icon={Volume2} title="Tono de notificación" subtitle="Sonido al recibir una alerta" stackOnMobile={true}>
                            <div className="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                                <select
                                    value={alertasPrefs.tono_id}
                                    onChange={(e) => updateAlertasPrefs((prev) => ({ ...prev, tono_id: e.target.value }))}
                                    className="w-full sm:w-52 px-4 py-2.5 rounded-xl text-xs font-bold theme-element border theme-border theme-text-main outline-none focus:border-[var(--color-primario)]"
                                >
                                    {(tonos_alertas.length > 0 ? tonos_alertas : [{ id: 'default', nombre: 'Campana clásica' }]).map((tono) => (
                                        <option key={tono.id} value={tono.id}>{tono.nombre}</option>
                                    ))}
                                </select>
                                <button
                                    type="button"
                                    onClick={() => NotificationBrowserService.previewTone(resolveTonoPath(tonos_alertas, alertasPrefs.tono_id))}
                                    className="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest theme-element border theme-border theme-text-main hover:border-[var(--color-primario)] transition-colors whitespace-nowrap"
                                >
                                    Probar tono
                                </button>
                            </div>
                        </SettingsRow>

                        <div className="pt-4 border-t theme-border space-y-3">
                            <p className="text-[11px] font-black uppercase theme-text-muted tracking-widest ml-1">Tipos de alerta a recibir</p>
                            <p className="text-[10px] font-bold theme-text-muted ml-1 leading-relaxed">Desactivar un tipo silencia sonido, voz, escritorio y toasts. El historial en el Centro de Alertas se conserva.</p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                {Object.entries(ALERTAS_TIPOS).map(([tipo, label]) => (
                                    <div key={tipo} className="flex items-center justify-between gap-3 p-3 rounded-xl theme-element border theme-border">
                                        <span className="text-[10px] font-bold theme-text-main leading-snug">{label}</span>
                                        <button type="button" className="gelia-switch shrink-0 scale-110 origin-right" data-active={alertasPrefs.tipos[tipo] !== false} onClick={() => toggleTipoAlerta(tipo)}>
                                            <div className="gelia-switch-thumb shadow-md" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </section>

                {/* --- SECCIÓN FONDOS --- */}
                <section className={`${activeCardClass} p-8 md:p-10 space-y-8`}>
                    <div className="flex items-center gap-3">
                        <ImageIcon className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                            Fondo de Pantalla_
                        </h2>
                    </div>

                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <p className="text-[11px] font-black uppercase theme-text-muted tracking-widest ml-1 drop-shadow-sm">Fondos del catálogo</p>
                            <span className="text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-full border theme-border theme-text-main opacity-80">
                                Actual: {getBackgroundType(selectedBg)}
                            </span>
                        </div>
                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                            {fondosDisponibles.map((fondo) => {
                                const bgValue = fondo.valor;
                                const previewSrc = fondo.preview || (fondo.tipo === 'vector'
                                    ? `/assets/backgrounds/${fondo.valor}_movil.svg`
                                    : fondo.valor);
                                return (
                                <button
                                    key={fondo.id || fondo.slug} type="button" onClick={() => handleBgChange(bgValue)}
                                    className={`relative h-24 rounded-2xl overflow-hidden border-[3px] transition-all duration-300 group ${selectedBg === bgValue ? 'shadow-2xl scale-105 ring-2 ring-offset-2 dark:ring-offset-[#141414]' : 'border-transparent opacity-60 hover:opacity-100 hover:shadow-lg hover:-translate-y-1'}`}
                                    style={{ '--tw-ring-color': 'var(--color-primario)', borderColor: selectedBg === bgValue ? 'var(--color-primario)' : '' }} title={fondo.nombre}
                                >
                                    <img src={previewSrc} alt={fondo.nombre} className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" />
                                </button>
                                );
                            })}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-10 pt-4">
                        <div className="space-y-4">
                            <p className="text-[11px] font-black uppercase theme-text-muted tracking-widest ml-1 drop-shadow-sm">Colores Sólidos</p>
                            <div className="flex flex-wrap gap-4">
                                {solidBackgrounds.map((solid) => (
                                    <button
                                        key={solid.name} type="button" onClick={() => handleBgChange(solid.hex)}
                                        className={`w-14 h-14 rounded-2xl border-[3px] transition-all duration-300 ${selectedBg === solid.hex ? 'scale-110 shadow-2xl ring-2 ring-offset-2 dark:ring-offset-[#141414]' : 'border-transparent opacity-60 hover:opacity-100 hover:shadow-lg hover:-translate-y-1'}`}
                                        style={{ backgroundColor: solid.hex, '--tw-ring-color': 'var(--color-primario)', borderColor: selectedBg === solid.hex ? 'var(--color-primario)' : '' }} title={solid.name}
                                    />
                                ))}
                                <label className={`relative w-14 h-14 rounded-2xl border-[3px] border-dashed flex items-center justify-center cursor-pointer hover:scale-110 hover:shadow-lg transition-all overflow-hidden theme-element ${glassEffect ? 'border-zinc-400 dark:border-zinc-500 bg-white/50 dark:bg-black/30' : 'border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-900'}`} title="Elegir color de fondo personalizado">
                                    <Palette className="w-6 h-6 theme-text-main z-10 pointer-events-none" />
                                    <input type="color" className="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-20" onChange={(e) => handleBgChange(e.target.value)} />
                                </label>
                            </div>
                        </div>

                        <div className="space-y-4">
                            <p className="text-[11px] font-black uppercase theme-text-muted tracking-widest ml-1 drop-shadow-sm">Imagen Personalizada</p>
                            <button
                                type="button"
                                onClick={() => setIsBgModalOpen(true)}
                                className={`flex items-center justify-center gap-3 w-full h-14 rounded-2xl border-[3px] border-dashed cursor-pointer hover:border-zinc-500 dark:hover:border-zinc-400 transition-all hover:shadow-md hover:-translate-y-0.5 outline-none ${glassEffect ? 'border-zinc-300 dark:border-zinc-700 bg-white/40 dark:bg-black/20' : 'border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900'}`}
                            >
                                <Upload className="w-5 h-5 theme-text-main drop-shadow-sm" />
                                <span className="text-sm font-bold theme-text-main uppercase tracking-widest drop-shadow-sm">Subir (.jpg, .png)</span>
                            </button>
                        </div>
                    </div>
                </section>

                {/* --- BANNER FOOTER --- */}
                <div className={`${activeCardClass} p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6`}>
                    <div className="flex items-center gap-4 w-full md:w-auto">
                        <div className="p-3 bg-yellow-500/20 text-yellow-600 dark:text-yellow-400 rounded-2xl shrink-0">
                            <AlertTriangle className="w-6 h-6" />
                        </div>
                        <div>
                            <h3 className="text-sm font-black theme-text-main uppercase tracking-widest">Modo de Vista Previa_</h3>
                            <p className="text-[11px] font-bold theme-text-muted mt-1 leading-tight max-w-xl">
                                Las selecciones visuales en esta pantalla son solo temporales. Para que los cambios apliquen y permanezcan en tu cuenta de forma definitiva, asegúrate de guardar.
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-col items-center md:items-end shrink-0 w-full md:w-auto">
                        {recentlySuccessful && <span className="text-xs font-bold text-green-500 fade-up drop-shadow-sm mb-2">¡Cambios Guardados Exitosamente!</span>}
                        <button type="button" onClick={submitProfile} disabled={processing} className="w-full md:w-auto py-4 px-10 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] transition-all hover:scale-105 shadow-xl hover:shadow-2xl flex justify-center items-center gap-3 disabled:opacity-60 disabled:cursor-not-allowed disabled:scale-100 outline-none border border-black/10" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Save className="w-5 h-5" /> {processing ? 'Guardando...' : 'Guardar cambios'}
                        </button>
                    </div>
                </div>

            </div>

            {/* =========================================
                PORTALS DE MODALES (AVATAR Y FONDO)
                ========================================= */}
            {isAvatarModalOpen && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl transition-opacity animate-fade-in" onClick={() => setIsAvatarModalOpen(false)}>
                    <div className="w-full max-w-sm theme-surface theme-border border shadow-2xl rounded-[2.5rem] p-8 flex flex-col items-center space-y-6 relative modal-pop" onClick={e => e.stopPropagation()}>
                        <button onClick={() => setIsAvatarModalOpen(false)} className="absolute top-5 right-5 p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none">
                            <X className="w-5 h-5" />
                        </button>
                        <h3 className="text-lg font-black uppercase italic tracking-tighter theme-text-main m-0">Foto de Perfil_</h3>
                        <div className="w-36 h-36 rounded-full overflow-hidden border-4 shadow-lg flex items-center justify-center bg-[var(--color-primario)] shrink-0" style={{ borderColor: 'var(--color-primario)' }}>
                            {imagePreview && !avatarLoadFailed ? (
                                <img
                                    src={imagePreview}
                                    alt="Preview"
                                    className="w-full h-full object-cover"
                                    onError={() => {
                                        setAvatarLoadFailed(true);
                                        showFileAlert(
                                            'Imagen no disponible',
                                            'No se pudo cargar la vista previa de la foto de perfil.'
                                        );
                                    }}
                                />
                            ) : (
                                <span className="text-5xl font-black text-white">{initialChar}</span>
                            )}
                        </div>
                        <input type="file" ref={fileInputRef} className="hidden" accept="image/jpeg,image/png,image/jpg,image/webp,image/gif" onChange={handleProfileFileChange} />
                        <p className="text-[10px] font-bold theme-text-muted text-center m-0">
                            Hasta {Math.round(MAX_SOURCE_IMAGE_BYTES / (1024 * 1024))} MB · se optimiza a WebP
                        </p>
                        <div className="flex w-full gap-3">
                            <button type="button" onClick={() => fileInputRef.current.click()} className="flex-1 py-3 px-4 theme-element border theme-border rounded-2xl text-xs font-bold theme-text-main transition-transform hover:scale-105 shadow-sm flex items-center justify-center gap-2 outline-none">
                                <Upload className="w-4 h-4" /> Subir Foto
                            </button>
                            <button type="button" onClick={handleRemovePhoto} className="flex-1 py-3 px-4 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-600 rounded-2xl text-xs font-bold transition-transform hover:scale-105 shadow-sm flex items-center justify-center gap-2 outline-none">
                                <User className="w-4 h-4" /> Sin Foto
                            </button>
                        </div>
                        <button type="button" onClick={() => setIsAvatarModalOpen(false)} className="w-full py-4 rounded-full text-white font-black uppercase tracking-widest text-[11px] transition-transform hover:scale-105 shadow-md flex justify-center items-center gap-2 outline-none m-0" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Check className="w-5 h-5" /> Listo
                        </button>
                    </div>
                </div>,
                document.body
            )}

            {isBgModalOpen && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl transition-opacity animate-fade-in" onClick={() => setIsBgModalOpen(false)}>
                    <div className="w-full max-w-2xl theme-surface theme-border border shadow-2xl rounded-[2.5rem] p-8 md:p-10 flex flex-col space-y-6 relative modal-pop" onClick={e => e.stopPropagation()}>
                        <button onClick={() => setIsBgModalOpen(false)} className="absolute top-5 right-5 p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none">
                            <X className="w-5 h-5" />
                        </button>
                        <h3 className="text-lg font-black uppercase italic tracking-tighter theme-text-main m-0">Fondo Personalizado_</h3>
                        <div className="w-full aspect-video rounded-3xl overflow-hidden border-4 shadow-lg flex items-center justify-center bg-zinc-900 shrink-0 theme-border relative">
                            {selectedBg && selectedBg !== 'none' && !selectedBg.startsWith('#') ? (
                                <img src={selectedBg.startsWith('data:image') || selectedBg.startsWith('/storage') ? selectedBg : `/assets/backgrounds/${selectedBg}_movil.svg`} alt="Preview" className="w-full h-full object-cover" />
                            ) : selectedBg && selectedBg.startsWith('#') ? (
                                <div className="w-full h-full" style={{ backgroundColor: selectedBg }}></div>
                            ) : (
                                <ImageIcon className="w-12 h-12 text-zinc-700" />
                            )}
                            <span className="bg-black/60 text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest absolute top-4 left-4 backdrop-blur-md">
                                {getBackgroundType(selectedBg)}
                            </span>
                        </div>
                        <input type="file" ref={bgFileInputRef} className="hidden" accept="image/jpeg,image/png,image/jpg,image/webp,image/gif" onChange={handleBgFileChange} />
                        <p className="text-[10px] font-bold theme-text-muted text-center m-0">
                            Hasta {Math.round(MAX_SOURCE_IMAGE_BYTES / (1024 * 1024))} MB · se optimiza a WebP
                        </p>
                        <div className="flex w-full gap-3">
                            <button type="button" onClick={() => bgFileInputRef.current.click()} className="flex-1 py-3 px-4 theme-element border theme-border rounded-2xl text-xs font-bold theme-text-main transition-transform hover:scale-105 shadow-sm flex items-center justify-center gap-2 outline-none">
                                <Upload className="w-4 h-4" /> Subir Imagen
                            </button>
                            <button type="button" onClick={handleRemoveBg} className="flex-1 py-3 px-4 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-600 rounded-2xl text-xs font-bold transition-transform hover:scale-105 shadow-sm flex items-center justify-center gap-2 outline-none">
                                <Trash2 className="w-4 h-4" /> Sin Fondo
                            </button>
                        </div>
                        <button type="button" onClick={() => setIsBgModalOpen(false)} className="w-full py-4 rounded-full text-white font-black uppercase tracking-widest text-[11px] transition-transform hover:scale-105 shadow-md flex justify-center items-center gap-2 outline-none m-0" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Check className="w-5 h-5" /> Listo
                        </button>
                    </div>
                </div>,
                document.body
            )}
        </AppLayout>
    );
}