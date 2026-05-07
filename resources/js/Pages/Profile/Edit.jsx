import React, { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import { 
    User, Mail, Smartphone, Camera, 
    Palette, Save, ShieldCheck, Moon, Sun, 
    Image as ImageIcon, Type, Droplet, 
    PanelLeft, BellRing, Settings2, PaintBucket, Layers, Upload, Plus
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

// --- COMPONENTE AUXILIAR RESPONSIVO ---
const SettingsRow = ({ icon: Icon, title, subtitle, children, border = true, stackOnMobile = true }) => (
    <div className={`flex ${stackOnMobile ? 'flex-col sm:flex-row sm:items-center' : 'flex-row items-center'} justify-between gap-4 py-4 px-2 transition-colors hover:bg-black/5 dark:hover:bg-white/5 rounded-xl ${border ? 'border-b theme-border' : ''}`}>
        <div className="flex items-center gap-4 flex-1">
            <div className="p-2.5 theme-element rounded-xl shrink-0">
                <Icon className="w-5 h-5 theme-text-main" />
            </div>
            <div className="flex-1">
                <h3 className="text-sm font-black theme-text-main leading-tight">{title}</h3>
                <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5 leading-tight">{subtitle}</p>
            </div>
        </div>
        <div className={`flex justify-start sm:justify-end ${stackOnMobile ? 'w-full sm:w-auto mt-1 sm:mt-0' : 'shrink-0'}`}>
            {children}
        </div>
    </div>
);

export default function Edit({ auth }) {
    // --- FORMULARIO CON LIMPIEZA DE DATOS ---
    const { data, setData, post, processing } = useForm({
        name: auth.user?.name ? auth.user.name.trim() : '',
        email: auth.user?.email ? auth.user.email.trim() : '',
        apellido_paterno: auth.user?.apellido_paterno ? auth.user.apellido_paterno.trim() : '',
        telefono: auth.user?.telefono ? auth.user.telefono.trim() : '',
        foto_perfil: null
    });

    const accentColors = {
        rosa: '#ec4899', azul: '#3b82f6', verde: '#10b981', amarillo: '#f59e0b'
    };

    const backgroundOptions = [
        'blob', 'blobscene', 'circle', 'layered', 
        'peaks', 'polygon', 'square', 'stacked', 'steps', 'wave'
    ];

    const solidBackgrounds = [
        { name: 'Blanco', hex: '#ffffff' },
        { name: 'Negro', hex: '#000000' },
        { name: 'Gris Oscuro', hex: '#1e293b' }
    ];

    const presets = [
        { name: 'Modo Hacker', modo: 'dark', colorHex: '#10b981', bg: '#000000', font: 'mono' },
        { name: 'GELIA Light', modo: 'light', colorHex: '#3b82f6', bg: 'layered', font: 'inter' },
        { name: 'Cyberpunk', modo: 'dark', colorHex: '#8b5cf6', bg: 'polygon', font: 'poppins' }
    ];

    const fontFamilies = {
        inter: "'Inter', sans-serif",
        montserrat: "'Montserrat', sans-serif",
        poppins: "'Poppins', sans-serif",
        nunito: "'Nunito', sans-serif",
        roboto: "'Roboto', sans-serif",
        mono: "'JetBrains Mono', monospace"
    };

    const [selectedColor, setSelectedColor] = useState(() => {
        if (typeof window !== 'undefined') return localStorage.getItem('theme_color') || auth?.tema_visual?.color_nombre?.toLowerCase() || 'rosa';
        return 'rosa';
    });

    const [isDarkMode, setIsDarkMode] = useState(() => {
        if (typeof window !== 'undefined') return localStorage.getItem('theme') === 'dark';
        return auth?.tema_visual?.modo === 'dark';
    });

    const [selectedBg, setSelectedBg] = useState(() => {
        if (typeof window !== 'undefined') return localStorage.getItem('bg_base') || auth?.tema_visual?.fondo_base || 'none';
        return 'none';
    });

    const [typography, setTypography] = useState(() => {
        if (typeof window !== 'undefined') return localStorage.getItem('theme_font') || auth?.tema_visual?.fuente_principal || 'inter';
        return 'inter';
    });

    const [glassEffect, setGlassEffect] = useState(() => {
        if (typeof window !== 'undefined') return localStorage.getItem('theme_glass') !== 'false';
        return true; 
    });

    const [sidebarLayout, setSidebarLayout] = useState(() => {
        if (typeof window !== 'undefined') return localStorage.getItem('theme_layout') || auth?.tema_visual?.layout_sidebar || 'floating_left';
        return 'floating_left';
    });

    const [notifications, setNotifications] = useState({ sound: true });

    useEffect(() => {
        const syncThemeState = () => {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) setIsDarkMode(savedTheme === 'dark');
        };
        window.addEventListener('theme-changed', syncThemeState);
        return () => window.removeEventListener('theme-changed', syncThemeState);
    }, []);

    useEffect(() => {
        animate('.fade-up', { translateY: [15, 0], opacity: [0, 1] }, { easing: 'easeOutExpo', duration: 600, delay: (el, i) => i * 80 });
    }, []);

    const handleColorChange = (colorValue, isHex = false) => {
        const hex = isHex ? colorValue : accentColors[colorValue];
        setSelectedColor(colorValue); 
        localStorage.setItem('theme_color', colorValue);
        document.documentElement.style.setProperty('--color-primario', hex);
    };

    const handleModeChange = (modo) => {
        const isDark = modo === 'dark';
        setIsDarkMode(isDark);
        const root = document.documentElement;
        isDark ? root.classList.add('dark') : root.classList.remove('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        window.dispatchEvent(new Event('theme-changed'));
    };

    const handleFontChange = (fontValue) => {
        setTypography(fontValue);
        localStorage.setItem('theme_font', fontValue);
        document.documentElement.style.setProperty('--font-principal', fontFamilies[fontValue] || fontFamilies.inter);
    };

    const handleGlassChange = (isActive) => {
        setGlassEffect(isActive);
        localStorage.setItem('theme_glass', isActive);
        const root = document.documentElement;
        isActive ? root.classList.add('glass-active') : root.classList.remove('glass-active');
    };

    const handleLayoutChange = (layout) => {
        setSidebarLayout(layout);
        localStorage.setItem('theme_layout', layout);
        window.dispatchEvent(new Event('theme-changed'));
    };

    const applyBackgroundCSS = (bgValue) => {
        const root = document.documentElement;
        if (bgValue === 'none') {
            root.style.setProperty('--bg-image-pc', 'none');
            root.style.setProperty('--bg-image-movil', 'none');
        } else if (bgValue.startsWith('#')) {
            root.style.setProperty('--bg-image-pc', `linear-gradient(to right, ${bgValue}, ${bgValue})`);
            root.style.setProperty('--bg-image-movil', `linear-gradient(to right, ${bgValue}, ${bgValue})`);
        } else if (bgValue.startsWith('data:image')) {
            root.style.setProperty('--bg-image-pc', `url(${bgValue})`);
            root.style.setProperty('--bg-image-movil', `url(${bgValue})`);
        } else {
            root.style.setProperty('--bg-image-pc', `url('/assets/backgrounds/${bgValue}_pc.svg')`);
            root.style.setProperty('--bg-image-movil', `url('/assets/backgrounds/${bgValue}_movil.svg')`);
        }
    };

    const handleBgChange = (bgValue) => {
        setSelectedBg(bgValue);
        localStorage.setItem('bg_base', bgValue);
        applyBackgroundCSS(bgValue);
    };

    const handleImageUpload = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onloadend = () => handleBgChange(reader.result);
            reader.readAsDataURL(file);
        }
    };

    const applyPreset = (preset) => {
        handleModeChange(preset.modo);
        handleColorChange(preset.colorHex, true);
        handleBgChange(preset.bg);
        if (preset.font) handleFontChange(preset.font);
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Mi Perfil | GELIANV" />

            <div className="max-w-5xl mx-auto p-4 md:p-8 space-y-8">
                
                {/* --- HEADER PERFIL --- */}
                <header className="fade-up theme-surface border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 shadow-sm flex flex-col md:flex-row items-center gap-6">
                    <div className="relative group shrink-0">
                        <div className="w-24 h-24 rounded-full overflow-hidden border-[3px] shadow-lg transition-colors duration-300 bg-white dark:bg-[#1A1A1A]" style={{ borderColor: 'var(--color-primario)' }}>
                            {auth.user?.foto_perfil ? (
                                <img src={`/storage/${auth.user.foto_perfil}`} alt="Perfil" className="w-full h-full object-cover" />
                            ) : (
                                <div className="w-full h-full theme-element flex items-center justify-center">
                                    <User className="w-8 h-8 theme-text-muted" />
                                </div>
                            )}
                        </div>
                        <button type="button" className="absolute bottom-0 right-0 p-2 text-white rounded-full transition-all shadow-md hover:scale-110 z-10" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Camera className="w-3 h-3" />
                        </button>
                    </div>

                    <div className="text-center md:text-left flex-1 space-y-2">
                        <div className="flex items-center justify-center md:justify-start gap-2" style={{ color: 'var(--color-primario)' }}>
                            <ShieldCheck className="w-3 h-3" />
                            <p className="text-[9px] font-black uppercase tracking-widest italic leading-none">Identidad Verificada</p>
                        </div>
                        <h1 className="text-3xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0 p-0 overflow-hidden text-ellipsis whitespace-nowrap">
                            {auth.user?.name ? auth.user.name.trim() : 'Usuario'}<span className="ml-2" style={{ color: 'var(--color-primario)' }}>GELIANV</span>
                        </h1>
                        <p className="theme-text-muted font-bold text-xs">Miembro desde: {auth.user?.created_at ? new Date(auth.user.created_at).toLocaleDateString() : '2026'}</p>
                    </div>

                    <button type="button" className="w-full md:w-auto py-3 px-6 text-white dark:text-black rounded-xl font-black uppercase tracking-widest text-[10px] transition-all hover:scale-105 shadow-md flex justify-center items-center gap-2 shrink-0" style={{ backgroundColor: 'var(--color-primario)' }}>
                        <Save className="w-4 h-4" /> Guardar Cambios
                    </button>
                </header>

                {/* --- SECCIÓN: DATOS DE CUENTA --- */}
                <section className="fade-up theme-surface border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 md:p-8 shadow-sm space-y-6">
                    <h2 className="text-lg font-black italic theme-text-main uppercase tracking-tighter flex items-center gap-2">
                        <Settings2 className="w-5 h-5" style={{ color: 'var(--color-primario)' }} /> Ajustes de Cuenta
                    </h2>
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <label className="text-[9px] font-black uppercase theme-text-muted ml-1">Nombre(s)</label>
                            <div className="relative">
                                {/* z-10 y pointer-events-none obligan al icono a estar arriba del fondo del input y no estorbar el clic */}
                                <User className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                <input type="text" value={data.name} onChange={e => setData('name', e.target.value)} className="w-full px-11 py-3 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-1 focus:ring-transparent transition-all" style={{ '--tw-ring-color': 'var(--color-primario)' }} onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'} onBlur={(e) => e.target.style.borderColor = ''} />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <label className="text-[9px] font-black uppercase theme-text-muted ml-1">Apellido Paterno</label>
                            <div className="relative">
                                <User className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                <input type="text" value={data.apellido_paterno} onChange={e => setData('apellido_paterno', e.target.value)} className="w-full px-11 py-3 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none transition-all" onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'} onBlur={(e) => e.target.style.borderColor = ''} />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <label className="text-[9px] font-black uppercase theme-text-muted ml-1">Email Institucional</label>
                            <div className="relative">
                                <Mail className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                <input type="email" value={data.email} readOnly className="w-full px-11 py-3 theme-element border theme-border rounded-xl theme-text-muted text-sm font-bold cursor-not-allowed opacity-60" />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <label className="text-[9px] font-black uppercase theme-text-muted ml-1">Teléfono</label>
                            <div className="relative">
                                <Smartphone className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                <input type="text" value={data.telefono} onChange={e => setData('telefono', e.target.value)} className="w-full px-11 py-3 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none transition-all" onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'} onBlur={(e) => e.target.style.borderColor = ''} />
                            </div>
                        </div>
                    </div>
                </section>

                {/* --- SECCIÓN: PERSONALIZACIÓN VISUAL --- */}
                <section className="fade-up theme-surface border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 md:p-8 shadow-sm space-y-6">
                    <h2 className="text-lg font-black italic theme-text-main uppercase tracking-tighter flex items-center gap-2">
                        <Palette className="w-5 h-5" style={{ color: 'var(--color-primario)' }} /> Interfaz y Colores
                    </h2>

                    <div className="flex flex-col">
                        
                        <SettingsRow icon={isDarkMode ? Moon : Sun} title="Modo de aplicación" subtitle="Esquema claro u oscuro" stackOnMobile={true}>
                            <div className="gelia-segment w-full sm:w-auto">
                                <button type="button" className="gelia-segment-btn" data-active={!isDarkMode} onClick={() => handleModeChange('light')}>
                                    <Sun className="w-4 h-4" /> Claro
                                </button>
                                <button type="button" className="gelia-segment-btn" data-active={isDarkMode} onClick={() => handleModeChange('dark')}>
                                    <Moon className="w-4 h-4" /> Oscuro
                                </button>
                            </div>
                        </SettingsRow>

                        <SettingsRow icon={PaintBucket} title="Color de énfasis" subtitle="Color primario del sistema" stackOnMobile={true}>
                            <div className="flex flex-wrap gap-3 items-center justify-start sm:justify-end w-full sm:w-auto">
                                {Object.entries(accentColors).map(([name, hex]) => (
                                    <button
                                        key={name} type="button" onClick={() => handleColorChange(name)}
                                        className={`w-6 h-6 sm:w-8 sm:h-8 rounded-full transition-all outline-none ${selectedColor === name ? 'ring-2 ring-offset-2 dark:ring-offset-[#141414]' : 'opacity-50 hover:opacity-100 hover:scale-110'}`}
                                        style={{ backgroundColor: hex, '--tw-ring-color': hex }} title={name}
                                    />
                                ))}
                                
                                <div className="w-[1px] h-6 bg-zinc-300 dark:bg-zinc-700 mx-1"></div>
                                
                                <label className="relative w-8 h-8 rounded-full border-2 border-dashed theme-border flex items-center justify-center cursor-pointer hover:scale-110 transition-all overflow-hidden" title="Elegir color personalizado">
                                    <Plus className="w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                    <input type="color" className="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-20" onChange={(e) => handleColorChange(e.target.value, true)} />
                                </label>
                            </div>
                        </SettingsRow>

                        <SettingsRow icon={Droplet} title="Efectos de transparencia" subtitle="Aplica el efecto traslúcido (Glassmorphism)" stackOnMobile={false}>
                            <button type="button" className="gelia-switch shrink-0" data-active={glassEffect} onClick={() => handleGlassChange(!glassEffect)}>
                                <div className="gelia-switch-thumb" />
                            </button>
                        </SettingsRow>

                        <SettingsRow icon={Type} title="Fuente del sistema" subtitle="Tipografía principal de la interfaz" border={false} stackOnMobile={true}>
                            <select 
                                value={typography} 
                                onChange={(e) => handleFontChange(e.target.value)} 
                                className="w-full sm:w-auto px-4 py-2 text-xs font-bold theme-element border theme-border rounded-xl theme-text-main outline-none cursor-pointer focus:ring-1 focus:ring-transparent transition-all appearance-none" 
                                style={{ '--tw-ring-color': 'var(--color-primario)' }} 
                                onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'} 
                                onBlur={(e) => e.target.style.borderColor = ''}
                            >
                                <option value="inter">Inter (Predeterminada)</option>
                                <option value="montserrat">Montserrat (Corporativa)</option>
                                <option value="poppins">Poppins (Moderna)</option>
                                <option value="nunito">Nunito (Amigable)</option>
                                <option value="roboto">Roboto (Datos/Tablas)</option>
                                <option value="mono">JetBrains Mono (Técnica)</option>
                            </select>
                        </SettingsRow>
                    </div>
                </section>

                {/* --- SECCIÓN: NAVEGACIÓN Y ALERTAS --- */}
                <section className="fade-up theme-surface border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 md:p-8 shadow-sm space-y-6">
                    <h2 className="text-lg font-black italic theme-text-main uppercase tracking-tighter flex items-center gap-2">
                        <PanelLeft className="w-5 h-5" style={{ color: 'var(--color-primario)' }} /> Navegación & Alertas
                    </h2>

                    <div className="flex flex-col">
                        <SettingsRow icon={PanelLeft} title="Disposición del Sidebar" subtitle="Formato lateral (Se aplica en pantallas grandes)" stackOnMobile={true}>
                            <div className="gelia-segment w-full sm:w-auto">
                                <button type="button" onClick={() => handleLayoutChange('fixed')} className="gelia-segment-btn" data-active={sidebarLayout === 'fixed'}>
                                    Fijo
                                </button>
                                <button type="button" onClick={() => handleLayoutChange('floating_left')} className="gelia-segment-btn" data-active={sidebarLayout === 'floating_left'}>
                                    Flot. Izq
                                </button>
                                <button type="button" onClick={() => handleLayoutChange('floating_right')} className="gelia-segment-btn" data-active={sidebarLayout === 'floating_right'}>
                                    Flot. Der
                                </button>
                            </div>
                        </SettingsRow>

                        <SettingsRow icon={BellRing} title="Sonidos de notificación" subtitle="Alertas sonoras del sistema" border={false} stackOnMobile={false}>
                            <button type="button" className="gelia-switch shrink-0" data-active={notifications.sound} onClick={() => setNotifications({ sound: !notifications.sound })}>
                                <div className="gelia-switch-thumb" />
                            </button>
                        </SettingsRow>
                    </div>
                </section>

                {/* --- SECCIÓN: CATALOGO DE FONDOS --- */}
                <section className="fade-up theme-surface border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 md:p-8 shadow-sm space-y-8">
                    <h2 className="text-lg font-black italic theme-text-main uppercase tracking-tighter flex items-center gap-2">
                        <ImageIcon className="w-5 h-5" style={{ color: 'var(--color-primario)' }} /> Fondo de Pantalla
                    </h2>
                    
                    <div className="space-y-3">
                        <p className="text-[10px] font-black uppercase theme-text-muted italic ml-1">Diseños Vectoriales</p>
                        <div className="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                            {backgroundOptions.map((bg) => (
                                <button
                                    key={bg} type="button" onClick={() => handleBgChange(bg)}
                                    className={`relative h-20 rounded-2xl overflow-hidden border-2 transition-all group ${selectedBg === bg ? 'shadow-md scale-[1.02]' : 'theme-border opacity-70 hover:opacity-100'}`}
                                    style={{ borderColor: selectedBg === bg ? 'var(--color-primario)' : '' }} title={bg}
                                >
                                    <img src={`/assets/backgrounds/${bg}_movil.svg`} alt={bg} className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" />
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div className="space-y-3">
                            <p className="text-[10px] font-black uppercase theme-text-muted italic ml-1">Colores Sólidos</p>
                            <div className="flex flex-wrap gap-3">
                                {solidBackgrounds.map((solid) => (
                                    <button
                                        key={solid.name} type="button" onClick={() => handleBgChange(solid.hex)}
                                        className={`w-12 h-12 rounded-2xl border-2 transition-all ${selectedBg === solid.hex ? 'scale-110 shadow-md' : 'theme-border hover:scale-105'}`}
                                        style={{ backgroundColor: solid.hex, borderColor: selectedBg === solid.hex ? 'var(--color-primario)' : '' }} title={solid.name}
                                    />
                                ))}
                                <label className="relative w-12 h-12 rounded-2xl border-2 border-dashed theme-border flex items-center justify-center cursor-pointer hover:scale-105 transition-all overflow-hidden theme-element" title="Elegir color de fondo personalizado">
                                    <Palette className="w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                    <input type="color" className="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-20" onChange={(e) => handleBgChange(e.target.value)} />
                                </label>
                            </div>
                        </div>

                        <div className="space-y-3">
                            <p className="text-[10px] font-black uppercase theme-text-muted italic ml-1">Imagen Personalizada</p>
                            <label className="flex items-center justify-center gap-3 w-full h-12 rounded-2xl border-2 border-dashed theme-border theme-element cursor-pointer hover:border-gray-400 transition-colors">
                                <Upload className="w-4 h-4 theme-text-muted" />
                                <span className="text-xs font-bold theme-text-muted">Subir fotografía (.jpg, .png)</span>
                                <input type="file" accept="image/*" className="hidden" onChange={handleImageUpload} />
                            </label>
                        </div>
                    </div>
                </section>

                {/* --- SECCIÓN: PRESETS DE TEMA --- */}
                <section className="fade-up theme-surface border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 md:p-8 shadow-sm space-y-6">
                    <h2 className="text-lg font-black italic theme-text-main uppercase tracking-tighter flex items-center gap-2">
                        <Layers className="w-5 h-5" style={{ color: 'var(--color-primario)' }} /> Temas Predefinidos
                    </h2>
                    
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {presets.map((preset, idx) => (
                            <button 
                                key={idx} type="button" onClick={() => applyPreset(preset)} 
                                className="p-4 theme-element border-2 theme-border rounded-2xl flex flex-col items-start gap-3 hover:scale-[1.02] transition-all text-left"
                            >
                                <div className="w-full flex justify-between items-center">
                                    <div className="w-4 h-4 rounded-full" style={{ backgroundColor: preset.colorHex }}></div>
                                    <span className="text-[9px] font-black uppercase theme-text-muted">{preset.modo}</span>
                                </div>
                                <div>
                                    <span className="text-xs font-black theme-text-main block">{preset.name}</span>
                                    <span className="text-[10px] font-bold theme-text-muted">Aplicar configuración completa</span>
                                </div>
                            </button>
                        ))}
                    </div>
                </section>

            </div>
        </AppLayout>
    );
}