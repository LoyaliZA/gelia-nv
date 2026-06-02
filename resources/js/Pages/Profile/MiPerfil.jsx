import React, { useState, useEffect, useRef, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import {
    User, Mail, Smartphone, Camera,
    Save, ShieldCheck, Upload, X, Trash2, AlertTriangle, Check, XCircle,
    Lock, KeyRound, CalendarDays, Building2, MapPin, ChevronDown, Eye, EyeOff,
    Settings2, Monitor, LogOut, Sparkles, AtSign, Shield, PenTool,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';
import GeliaLogo from '../../Components/GeliaLogo';
import { geliaCardClass } from '../../utils/geliaTheme';
import {
    compressImageToWebp,
    validateImageSource,
    MAX_SOURCE_IMAGE_BYTES,
} from '../../utils/compressImage';

const MAX_PROFILE_PHOTO_BYTES = 2048 * 1024;

function profileRoute(routeName, fallbackPath) {
    if (typeof route === 'function') {
        try {
            return route(routeName);
        } catch {
            // Ziggy desactualizado
        }
    }
    return fallbackPath;
}

export default function MiPerfil({ perfilUsuario = {}, sesiones = [], sesiones_soportadas = true }) {
    const { auth, flash } = usePage().props;
    const usuario = { ...auth?.user, ...perfilUsuario };
    const roles = usuario?.roles || [];

    const fileInputRef = useRef(null);

    const [isAvatarModalOpen, setIsAvatarModalOpen] = useState(false);
    const [imagePreview, setImagePreview] = useState(
        usuario?.foto_perfil ? `/storage/${usuario.foto_perfil}` : null
    );
    const [avatarLoadFailed, setAvatarLoadFailed] = useState(false);
    const initialChar = usuario?.name ? usuario.name.charAt(0).toUpperCase() : 'U';

    const [saveStatus, setSaveStatus] = useState(null);
    const [fileAlert, setFileAlert] = useState(null);
    const [isCompressing, setIsCompressing] = useState(false);

    const [showSensitive, setShowSensitive] = useState(false);
    const [showInstitutional, setShowInstitutional] = useState(false);
    const [showSessions, setShowSessions] = useState(true);
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);
    const [revokingSessions, setRevokingSessions] = useState(false);

    // Firma digital para responsables de Activos (TI)
    const [showSignature, setShowSignature] = useState(false);
    const [dibujandoFirma, setDibujandoFirma] = useState(false);
    const [firmaTrazada, setFirmaTrazada] = useState(false);
    const [firmaPreview, setFirmaPreview] = useState(
        usuario?.firma_ruta ? `/storage/${usuario.firma_ruta}` : null
    );
    const signatureCanvasRef = useRef(null);
    const lastSigPosRef = useRef({ x: 0, y: 0, time: 0, width: 3.5 });

    const puedeFirmarComoResponsable = 
        roles.includes('Super Admin') || 
        roles.includes('Administrador') || 
        usuario?.permissions?.includes('activos.asignar') ||
        auth?.user?.permissions?.includes('activos.asignar');

    useEffect(() => {
        if (!showSignature) return;
        const canvas = signatureCanvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.strokeStyle = '#1e3a8a';
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }, [showSignature]);

    const confirmarTrazoFirma = () => {
        const canvas = signatureCanvasRef.current;
        if (!canvas) return;
        const dataUrl = canvas.toDataURL('image/png');
        setData((prev) => ({ ...prev, firma: dataUrl, remove_firma: false }));
        setFirmaPreview(dataUrl);
        setFirmaTrazada(true);
    };

    const limpiarTrazoFirma = () => {
        const canvas = signatureCanvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        setFirmaTrazada(false);
        setData((prev) => ({ ...prev, firma: null }));
    };

    const obtenerCoordenadasFirma = (e, canvas) => {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: ((clientX - rect.left) / rect.width) * canvas.width,
            y: ((clientY - rect.top) / rect.height) * canvas.height
        };
    };

    const iniciarDibujoFirma = (e) => {
        if (e.cancelable) e.preventDefault();
        const canvas = signatureCanvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const pos = obtenerCoordenadasFirma(e, canvas);

        lastSigPosRef.current = {
            x: pos.x,
            y: pos.y,
            time: Date.now(),
            width: 3.5
        };

        ctx.beginPath();
        ctx.arc(pos.x, pos.y, 1.75, 0, 2 * Math.PI);
        ctx.fillStyle = '#1e3a8a';
        ctx.fill();

        setDibujandoFirma(true);
    };

    const dibujarFirma = (e) => {
        if (!dibujandoFirma) return;
        if (e.cancelable) e.preventDefault();
        const canvas = signatureCanvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const pos = obtenerCoordenadasFirma(e, canvas);

        const lastPos = lastSigPosRef.current;
        const dx = pos.x - lastPos.x;
        const dy = pos.y - lastPos.y;
        const dist = Math.sqrt(dx * dx + dy * dy);

        if (dist === 0) return;

        const now = Date.now();
        const dt = Math.max(1, now - lastPos.time);
        const velocity = dist / dt;

        const MIN_WIDTH = 1.0;
        const MAX_WIDTH = 4.5;
        const MAX_VELOCITY = 2.0;

        const targetWidth = Math.max(
            MIN_WIDTH,
            Math.min(MAX_WIDTH, MAX_WIDTH - (velocity / MAX_VELOCITY) * (MAX_WIDTH - MIN_WIDTH))
        );

        const currentWidth = lastPos.width * 0.7 + targetWidth * 0.3;

        ctx.beginPath();
        ctx.moveTo(lastPos.x, lastPos.y);
        ctx.lineTo(pos.x, pos.y);
        ctx.strokeStyle = '#1e3a8a';
        ctx.lineWidth = currentWidth;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();

        lastSigPosRef.current = {
            x: pos.x,
            y: pos.y,
            time: now,
            width: currentWidth
        };

        setFirmaTrazada(true);
    };

    const detenerDibujoFirma = () => {
        setDibujandoFirma(false);
    };

    const removerFirmaPerfil = () => {
        setData((prev) => ({ ...prev, firma: null, remove_firma: true }));
        setFirmaPreview(null);
        setFirmaTrazada(false);
    };

    const otrasSesiones = sesiones.filter((s) => !s.es_actual);

    const { data, setData, post, processing, recentlySuccessful, errors, transform } = useForm({
        name: usuario?.name ? usuario.name.trim() : '',
        email: usuario?.email ? usuario.email.trim() : '',
        apellido_paterno: usuario?.apellido_paterno ? usuario.apellido_paterno.trim() : '',
        apellido_materno: usuario?.apellido_materno ? usuario.apellido_materno.trim() : '',
        telefono: usuario?.telefono ? usuario.telefono.trim() : '',
        fecha_nacimiento: usuario?.fecha_nacimiento || '',
        password: '',
        password_confirmation: '',
        foto_perfil: null,
        remove_foto: false,
        firma: null,
        remove_firma: false,
    });

    useEffect(() => {
        if (isAvatarModalOpen) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = '';
        return () => { document.body.style.overflow = ''; };
    }, [isAvatarModalOpen]);

    useEffect(() => {
        setAvatarLoadFailed(false);
    }, [imagePreview]);

    useEffect(() => {
        if (usuario?.foto_perfil && !data.foto_perfil) {
            setImagePreview(`/storage/${usuario.foto_perfil}`);
        }
    }, [usuario?.foto_perfil, data.foto_perfil]);

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

            setData((prev) => ({ ...prev, foto_perfil: compressed, remove_foto: false }));
            setAvatarLoadFailed(false);
            setImagePreview(URL.createObjectURL(compressed));
        } catch (err) {
            const message = err?.message === 'COMPRESS_TOO_LARGE'
                ? 'La foto sigue siendo muy grande después de optimizarla. Prueba con una imagen de menor resolución.'
                : 'No se pudo procesar la foto de perfil. Verifica que sea una imagen válida.';
            showFileAlert('Error al procesar', message);
            setData((prev) => ({ ...prev, foto_perfil: null }));
            setImagePreview(usuario?.foto_perfil ? `/storage/${usuario.foto_perfil}` : null);
        } finally {
            setIsCompressing(false);
            if (fileInputRef.current) fileInputRef.current.value = '';
        }
    };

    const handleRemovePhoto = () => {
        setData((prev) => ({ ...prev, foto_perfil: null, remove_foto: true }));
        setImagePreview(null);
        setAvatarLoadFailed(false);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const cerrarOtrasSesiones = () => {
        if (!sesiones_soportadas || otrasSesiones.length === 0) return;
        if (!window.confirm(`¿Cerrar sesión en ${otrasSesiones.length} dispositivo(s) o navegador(es)?`)) return;

        setRevokingSessions(true);
        router.delete(profileRoute('profile.sessions.destroy-others', '/perfil/sesiones/otras'), {
            preserveScroll: true,
            onFinish: () => setRevokingSessions(false),
        });
    };

    const submitMiPerfil = (e) => {
        if (e) e.preventDefault();
        setSaveStatus(null);

        const temaVisual = auth?.tema_visual || {};

        transform((formData) => ({
            ...formData,
            password: formData.password || '',
            password_confirmation: formData.password_confirmation || '',
            tema_visual: JSON.stringify(temaVisual),
        }));

        post(route('profile.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                router.reload({
                    only: ['auth', 'perfilUsuario'],
                    preserveScroll: true,
                    preserveState: true,
                });
                setSaveStatus('success');
                if (fileInputRef.current) fileInputRef.current.value = '';
                setIsAvatarModalOpen(false);
                setTimeout(() => setSaveStatus(null), 4000);
            },
            onError: () => setSaveStatus('error'),
        });
    };

    const activeCardClass = geliaCardClass('relative z-10');
    const formZoneClass = 'theme-form-zone p-6 sm:p-8 grid grid-cols-1 md:grid-cols-2 gap-6 transition-colors';
    const formZoneClassWide = 'theme-form-zone mt-3 p-6 sm:p-8 grid grid-cols-1 md:grid-cols-2 gap-6 transition-colors';
    const formZoneClassInst = 'theme-form-zone mt-3 p-6 sm:p-8 grid grid-cols-1 md:grid-cols-3 gap-6 transition-colors';

    return (
        <AppLayout>
            <Head title="Mi Perfil | GELIANV" />
            <GeliaLoader isVisible={processing || isCompressing} message={isCompressing ? 'Optimizando imagen_' : 'Guardando cambios_'} />

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
                        onClick={(ev) => ev.stopPropagation()}
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
                                    ? 'Tus datos de perfil han sido guardados correctamente.'
                                    : 'No se pudieron guardar los cambios. Revisa los campos e intenta de nuevo.'}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setSaveStatus(null)}
                            className={`relative z-10 w-full py-4 rounded-2xl text-white font-black uppercase tracking-widest text-[11px] transition-all hover:scale-105 shadow-lg outline-none
                                ${saveStatus === 'success' ? 'opacity-90 hover:opacity-100' : 'bg-red-500 hover:bg-red-600'}`}
                            style={saveStatus === 'success' ? { backgroundColor: 'var(--color-primario)' } : {}}
                        >
                            {saveStatus === 'success' ? 'Continuar_' : 'Entendido_'}
                        </button>
                        <button
                            type="button"
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
                        onClick={(ev) => ev.stopPropagation()}
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

                {flash?.success && (
                    <div className={`${geliaCardClass('p-4 md:p-5')} border-[var(--color-primario)]/30 bg-[var(--color-primario)]/5`}>
                        <p className="text-sm font-bold m-0" style={{ color: 'var(--color-primario)' }}>{flash.success}</p>
                    </div>
                )}

                {/* --- HEADER: Protocolo de identidad --- */}
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
                                    <div className="w-8 h-1.5 rounded-full mr-3" style={{ backgroundColor: 'var(--color-primario)' }} />
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
                                <h3 className="text-sm font-black theme-text-main uppercase tracking-widest">Guardar cambios_</h3>
                                <p className="text-[11px] font-bold theme-text-muted mt-1 leading-tight max-w-xl">
                                    Actualiza tus datos personales y pulsa Guardar para que queden registrados en tu cuenta.
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-col items-center md:items-end shrink-0 w-full md:w-auto">
                            {recentlySuccessful && <span className="text-xs font-bold text-green-500 fade-up drop-shadow-sm mb-2">¡Cambios guardados exitosamente!</span>}
                            <button type="button" onClick={submitMiPerfil} disabled={processing} className="w-full md:w-auto py-4 px-10 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] transition-all hover:scale-105 shadow-xl hover:shadow-2xl flex justify-center items-center gap-3 disabled:opacity-60 disabled:cursor-not-allowed disabled:scale-100 outline-none border border-black/10" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Save className="w-5 h-5" /> {processing ? 'Guardando...' : 'Guardar cambios'}
                            </button>
                        </div>
                    </div>
                </header>

                {/* --- SECCIÓN: Ajustes de cuenta --- */}
                <section className={`${activeCardClass} p-8 md:p-10 space-y-8`}>
                    <div className="flex items-center gap-3">
                        <Settings2 className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">Ajustes de Cuenta_</h2>
                    </div>

                    <div>
                        <p className="text-[10px] font-black uppercase tracking-widest mb-4 ml-1 drop-shadow-sm" style={{ color: 'var(--color-primario)' }}>
                            Datos Personales
                        </p>
                        <div className={formZoneClass}>
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Nombre(s)</label>
                                <div className="relative">
                                    <User className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                    <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                                        className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                        onFocus={(e) => { e.target.style.borderColor = 'var(--color-primario)'; }} onBlur={(e) => { e.target.style.borderColor = ''; }} />
                                </div>
                                {errors.name && <p className="text-xs text-red-500 m-0 mt-1 px-2">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Apellido Paterno</label>
                                <div className="relative">
                                    <User className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                    <input type="text" value={data.apellido_paterno} onChange={(e) => setData('apellido_paterno', e.target.value)}
                                        className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                        onFocus={(e) => { e.target.style.borderColor = 'var(--color-primario)'; }} onBlur={(e) => { e.target.style.borderColor = ''; }} />
                                </div>
                                {errors.apellido_paterno && <p className="text-xs text-red-500 m-0 mt-1 px-2">{errors.apellido_paterno}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Apellido Materno</label>
                                <div className="relative">
                                    <User className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                    <input type="text" value={data.apellido_materno} onChange={(e) => setData('apellido_materno', e.target.value)}
                                        className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                        onFocus={(e) => { e.target.style.borderColor = 'var(--color-primario)'; }} onBlur={(e) => { e.target.style.borderColor = ''; }} />
                                </div>
                                {errors.apellido_materno && <p className="text-xs text-red-500 m-0 mt-1 px-2">{errors.apellido_materno}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Teléfono</label>
                                <div className="relative">
                                    <Smartphone className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                    <input type="text" value={data.telefono} onChange={(e) => setData('telefono', e.target.value)}
                                        className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                        onFocus={(e) => { e.target.style.borderColor = 'var(--color-primario)'; }} onBlur={(e) => { e.target.style.borderColor = ''; }} />
                                </div>
                                {errors.telefono && <p className="text-xs text-red-500 m-0 mt-1 px-2">{errors.telefono}</p>}
                            </div>
                        </div>
                    </div>

                    <div>
                        <button
                            type="button"
                            onClick={() => setShowSensitive((v) => !v)}
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
                            <div className={formZoneClassWide}>
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Fecha de Nacimiento</label>
                                    <div className="relative">
                                        <CalendarDays className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                        <input type="date" value={data.fecha_nacimiento} onChange={(e) => setData('fecha_nacimiento', e.target.value)}
                                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                            onFocus={(e) => { e.target.style.borderColor = 'var(--color-primario)'; }} onBlur={(e) => { e.target.style.borderColor = ''; }} />
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
                                            onChange={(e) => setData('password', e.target.value)}
                                            placeholder="Dejar vacío para no cambiar"
                                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                            onFocus={(e) => { e.target.style.borderColor = 'var(--color-primario)'; }} onBlur={(e) => { e.target.style.borderColor = ''; }}
                                        />
                                        <button type="button" onClick={() => setShowPassword((v) => !v)}
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
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                            placeholder="Repite la nueva contraseña"
                                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                            onFocus={(e) => { e.target.style.borderColor = 'var(--color-primario)'; }} onBlur={(e) => { e.target.style.borderColor = ''; }}
                                        />
                                        <button type="button" onClick={() => setShowConfirm((v) => !v)}
                                            className="absolute right-4 top-1/2 -translate-y-1/2 theme-text-muted hover:theme-text-main transition-colors outline-none">
                                            {showConfirm ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                                        </button>
                                    </div>
                                    {errors.password_confirmation && <p className="text-xs text-red-500 m-0 mt-1 px-2">{errors.password_confirmation}</p>}
                                </div>
                            </div>
                        )}
                    </div>

                    <div>
                        <button
                            type="button"
                            onClick={() => setShowInstitutional((v) => !v)}
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
                            <div className={formZoneClassInst}>
                                {usuario?.username && (
                                    <div className="space-y-2">
                                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Usuario de acceso</label>
                                        <div className="relative">
                                            <AtSign className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                            <input type="text" value={usuario.username} readOnly
                                                className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-muted text-sm font-bold cursor-not-allowed opacity-60 shadow-sm" />
                                        </div>
                                    </div>
                                )}

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

                                {roles.length > 0 && (
                                    <div className="space-y-2 md:col-span-3">
                                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Roles en el sistema</label>
                                        <div className="flex flex-wrap gap-2 p-4 theme-surface border theme-border rounded-xl">
                                            {roles.map((rol) => (
                                                <span
                                                    key={rol}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest"
                                                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 12%, transparent)', color: 'var(--color-primario)' }}
                                                >
                                                    <Shield className="w-3 h-3" />
                                                    {rol}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Firma Digital (Responsables de Activos) */}
                    {puedeFirmarComoResponsable && (
                        <div>
                            <button
                                type="button"
                                onClick={() => setShowSignature((v) => !v)}
                                className={`w-full flex items-center justify-between px-5 py-4 rounded-2xl border transition-all duration-200 outline-none group
                                    ${showSignature
                                        ? 'border-[var(--color-primario)]/40 bg-[var(--color-primario)]/5'
                                        : 'theme-border theme-surface hover:border-[var(--color-primario)]/30'
                                    }`}
                            >
                                <div className="flex items-center gap-3">
                                    <div className="p-2 rounded-xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                                        <PenTool className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                                    </div>
                                    <div className="text-left">
                                        <span className="text-sm font-black theme-text-main uppercase tracking-widest block leading-tight">Firma del Responsable (TI)</span>
                                        <span className="text-[10px] font-bold theme-text-muted uppercase tracking-widest">Para firmar responsivas de entrega de activos</span>
                                    </div>
                                </div>
                                <ChevronDown className={`w-5 h-5 theme-text-muted transition-transform duration-300 ${showSignature ? 'rotate-180' : ''}`} />
                            </button>

                            {showSignature && (
                                <div className={`${formZoneClassWide} items-start`}>
                                    <div className="space-y-4">
                                        <div>
                                            <span className="text-[10px] font-black uppercase theme-text-muted tracking-widest block mb-2">Mi Firma Registrada</span>
                                            {firmaPreview ? (
                                                <div className="relative inline-block border theme-border rounded-xl p-2 bg-white dark:bg-slate-900">
                                                    <img src={firmaPreview} className="max-h-[85px] max-w-[220px] object-contain block" alt="Firma digital" />
                                                    <button
                                                        type="button"
                                                        onClick={removerFirmaPerfil}
                                                        className="absolute -top-2 -right-2 p-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors cursor-pointer"
                                                        title="Eliminar firma"
                                                    >
                                                        <Trash2 className="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                            ) : (
                                                <p className="text-xs theme-text-muted italic m-0">No tienes una firma registrada en tu perfil.</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-3">
                                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1 block">Registrar / Dibujar Nueva Firma</label>
                                        <div className="border theme-border rounded-xl bg-white dark:bg-slate-900 p-1 relative overflow-hidden">
                                            <canvas
                                                ref={signatureCanvasRef}
                                                width={450}
                                                height={180}
                                                className="w-full h-[150px] touch-none cursor-crosshair bg-white dark:bg-slate-950 rounded-lg"
                                                onMouseDown={iniciarDibujoFirma}
                                                onMouseMove={dibujarFirma}
                                                onMouseUp={detenerDibujoFirma}
                                                onMouseLeave={detenerDibujoFirma}
                                                onTouchStart={iniciarDibujoFirma}
                                                onTouchMove={dibujarFirma}
                                                onTouchEnd={detenerDibujoFirma}
                                            />
                                            {!firmaTrazada && (
                                                <div className="absolute inset-0 flex items-center justify-center pointer-events-none opacity-30 select-none">
                                                    <p className="text-[10px] text-slate-400 font-bold uppercase tracking-wider flex items-center gap-1">
                                                        <PenTool className="w-3.5 h-3.5" /> Dibuja aquí para actualizar
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex gap-2 justify-end">
                                            <button
                                                type="button"
                                                onClick={limpiarTrazoFirma}
                                                className="px-3 py-1.5 rounded-lg border theme-border theme-text-muted hover:theme-text-main text-[10px] font-black uppercase cursor-pointer"
                                                disabled={!firmaTrazada}
                                            >
                                                Limpiar
                                            </button>
                                            <button
                                                type="button"
                                                onClick={confirmarTrazoFirma}
                                                className="px-4 py-1.5 bg-blue-600 text-white rounded-lg text-[10px] font-black uppercase cursor-pointer"
                                                disabled={!firmaTrazada}
                                            >
                                                Confirmar trazo
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="w-full pt-6 border-t border-zinc-200/60 dark:border-zinc-700/50 flex flex-col md:flex-row items-center justify-between gap-6">
                        <p className="text-[11px] font-bold theme-text-muted m-0 text-center md:text-left leading-tight max-w-xl">
                            Los cambios en datos personales y sensibles se aplican al guardar.
                        </p>
                        <div className="flex flex-col items-center md:items-end shrink-0 w-full md:w-auto">
                            {recentlySuccessful && (
                                <span className="text-xs font-bold text-green-500 fade-up drop-shadow-sm mb-2">
                                    ¡Cambios guardados exitosamente!
                                </span>
                            )}
                            <button
                                type="button"
                                onClick={submitMiPerfil}
                                disabled={processing}
                                className="w-full md:w-auto py-4 px-10 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] transition-all hover:scale-105 shadow-xl hover:shadow-2xl flex justify-center items-center gap-3 disabled:opacity-60 disabled:cursor-not-allowed disabled:scale-100 outline-none border border-black/10"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Save className="w-5 h-5" />
                                {processing ? 'Guardando...' : 'Guardar cambios'}
                            </button>
                        </div>
                    </div>
                </section>

                {/* --- SECCIÓN: Información de sesión --- */}
                <section className={`${activeCardClass} p-8 md:p-10 space-y-6`}>
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <Monitor className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                            <div>
                                <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                                    Información de Sesión_
                                </h2>
                                <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">
                                    Dispositivos donde has iniciado sesión
                                </p>
                            </div>
                        </div>
                        {sesiones_soportadas && otrasSesiones.length > 0 && (
                            <button
                                type="button"
                                onClick={cerrarOtrasSesiones}
                                disabled={revokingSessions}
                                className="w-full sm:w-auto py-3 px-5 rounded-2xl text-[10px] font-black uppercase tracking-widest theme-element border border-red-300 dark:border-red-500/40 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all flex items-center justify-center gap-2 disabled:opacity-50 outline-none"
                            >
                                <LogOut className="w-4 h-4" />
                                {revokingSessions ? 'Cerrando...' : 'Cerrar otras sesiones'}
                            </button>
                        )}
                    </div>

                    {!sesiones_soportadas ? (
                        <p className="text-sm font-bold theme-text-muted m-0 p-4 theme-form-zone rounded-2xl">
                            El listado de sesiones requiere el almacenamiento en base de datos (<code className="text-xs">SESSION_DRIVER=database</code>).
                        </p>
                    ) : (
                        <>
                            <button
                                type="button"
                                onClick={() => setShowSessions((v) => !v)}
                                className={`w-full flex items-center justify-between px-5 py-4 rounded-2xl border transition-all duration-200 outline-none
                                    ${showSessions
                                        ? 'border-[var(--color-primario)]/40 bg-[var(--color-primario)]/5'
                                        : 'theme-border theme-surface hover:border-[var(--color-primario)]/30'
                                    }`}
                            >
                                <span className="text-sm font-black theme-text-main uppercase tracking-widest">
                                    {sesiones.length} sesión{sesiones.length !== 1 ? 'es' : ''} registrada{sesiones.length !== 1 ? 's' : ''}
                                </span>
                                <ChevronDown className={`w-5 h-5 theme-text-muted transition-transform duration-300 ${showSessions ? 'rotate-180' : ''}`} />
                            </button>

                            {showSessions && (
                                <div className="space-y-3">
                                    {sesiones.length === 0 ? (
                                        <p className="text-sm font-bold theme-text-muted m-0 px-2">
                                            No hay sesiones activas registradas para tu cuenta.
                                        </p>
                                    ) : (
                                        sesiones.map((sesion) => (
                                            <div
                                                key={sesion.id}
                                                className={`flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl border transition-all theme-element
                                                    ${sesion.es_actual ? 'border-[var(--color-primario)]/50 shadow-md' : 'theme-border'}`}
                                            >
                                                <div className="flex items-start gap-4 min-w-0">
                                                    <div
                                                        className="p-3 rounded-2xl shrink-0"
                                                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 12%, transparent)' }}
                                                    >
                                                        {sesion.dispositivo === 'Móvil' || sesion.dispositivo === 'Tablet'
                                                            ? <Smartphone className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                                            : <Monitor className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                                        }
                                                    </div>
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-black theme-text-main m-0 truncate">
                                                            {sesion.resumen}
                                                        </p>
                                                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">
                                                            IP: {sesion.ip_address || 'No disponible'} · {sesion.last_activity_fecha}
                                                        </p>
                                                        <p className="text-[10px] font-bold theme-text-muted mt-1 m-0">
                                                            Última actividad: {sesion.last_activity_humano}
                                                        </p>
                                                    </div>
                                                </div>
                                                {sesion.es_actual && (
                                                    <span
                                                        className="shrink-0 self-start sm:self-center px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest text-white"
                                                        style={{ backgroundColor: 'var(--color-primario)' }}
                                                    >
                                                        Este dispositivo
                                                    </span>
                                                )}
                                            </div>
                                        ))
                                    )}
                                </div>
                            )}
                        </>
                    )}
                </section>

                {/* --- Accesos rápidos --- */}
                <section className={`${activeCardClass} p-8 md:p-10`}>
                    <div className="flex items-center gap-3 mb-6">
                        <Sparkles className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">
                            Más opciones_
                        </h2>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Link
                            href={profileRoute('profile.preferencias', '/perfil/preferencias')}
                            className="flex items-center gap-4 p-5 rounded-2xl theme-element border theme-border hover:border-[var(--color-primario)] transition-all outline-none group"
                        >
                            <Settings2 className="w-8 h-8 shrink-0 group-hover:scale-110 transition-transform" style={{ color: 'var(--color-primario)' }} />
                            <div>
                                <span className="text-sm font-black theme-text-main uppercase tracking-widest block">Preferencias</span>
                                <span className="text-[10px] font-bold theme-text-muted">Apariencia, alertas y navegación</span>
                            </div>
                        </Link>
                        <Link
                            href={profileRoute('profile.novedades', '/perfil/novedades')}
                            className="flex items-center gap-4 p-5 rounded-2xl theme-element border theme-border hover:border-[var(--color-primario)] transition-all outline-none group"
                        >
                            <Sparkles className="w-8 h-8 shrink-0 group-hover:scale-110 transition-transform" style={{ color: 'var(--color-primario)' }} />
                            <div>
                                <span className="text-sm font-black theme-text-main uppercase tracking-widest block">Novedades</span>
                                <span className="text-[10px] font-bold theme-text-muted">Actualizaciones de la plataforma</span>
                            </div>
                        </Link>
                    </div>
                </section>
            </div>

            {isAvatarModalOpen && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl transition-opacity animate-fade-in" onClick={() => setIsAvatarModalOpen(false)}>
                    <div className="w-full max-w-sm theme-surface theme-border border shadow-2xl rounded-[2.5rem] p-8 flex flex-col items-center space-y-6 relative modal-pop" onClick={(ev) => ev.stopPropagation()}>
                        <button type="button" onClick={() => setIsAvatarModalOpen(false)} className="absolute top-5 right-5 p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none">
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
                            <button type="button" onClick={() => fileInputRef.current?.click()} className="flex-1 py-3 px-4 theme-element border theme-border rounded-2xl text-xs font-bold theme-text-main transition-transform hover:scale-105 shadow-sm flex items-center justify-center gap-2 outline-none">
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
        </AppLayout>
    );
}
