import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { 
    Link as LinkIcon, 
    Copy, 
    CheckCircle, 
    Sparkles, 
    ShieldCheck, 
    Clock, 
    ChevronDown,
    Briefcase,
    AlertTriangle,
    Key,
    Check
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Enlaces({ auth }) {
    // 1. Verificamos si el usuario actual tiene permisos de Administrador
    const userRoles = auth?.user?.roles || [];
    const isAdmin = userRoles.includes('Administrador') || userRoles.includes('Super admin (admin)');

    // 2. Diccionario base completo
    const allRolesInfo = {
        'Colaborador': [
            'Ver el listado general de solicitudes'
        ],
        'Grupo: Vendedor': [
            'Crear nuevas solicitudes (cotizaciones)', 
            'Ver el directorio de clientes', 
            'Registrar nuevos clientes en el sistema'
        ],
        'Grupo: Verificador': [
            'Ver detalles de las solicitudes', 
            'Verificar y aprobar procesos operativos'
        ],
        'Gerente': [
            'Ver listado y detalle de solicitudes', 
            'Reportar inconsistencias o errores', 
            'Acceso de lectura a la bitácora de auditoría'
        ],
        'Administrador': [
            'Gestión completa de usuarios (CRUD)', 
            'Gestión de clientes y carga masiva', 
            'Todas las funciones operativas y de auditoría'
        ]
    };

    // 3. FILTRO DE SEGURIDAD (Zero Trust Frontend)
    const rolesInfo = Object.keys(allRolesInfo).reduce((acc, rolName) => {
        if (!isAdmin && (rolName === 'Administrador' || rolName === 'Gerente')) {
            return acc; // Ocultamos opciones superiores a usuarios operativos/gerentes
        }
        acc[rolName] = allRolesInfo[rolName];
        return acc;
    }, {});

    const rolesDisponibles = Object.keys(rolesInfo);

    // 4. Declaración de Estados (Ahora toma el primer rol disponible de forma dinámica)
    const [rolSeleccionado, setRolSeleccionado] = useState(
        rolesDisponibles.includes('Grupo: Vendedor') ? 'Grupo: Vendedor' : rolesDisponibles[0]
    );
    const [enlaceGenerado, setEnlaceGenerado] = useState('');
    const [copiado, setCopiado] = useState(false);
    const [cargando, setCargando] = useState(false);

    const generarEnlace = async () => {
        setCargando(true);
        setEnlaceGenerado('');
        setCopiado(false);

        try {
            // Utilizamos axios para obtener el enlace sin recargar la página de Inertia
            const response = await axios.post(route('admin.enlaces.generar'), { role_name: rolSeleccionado });
            setEnlaceGenerado(response.data.enlace);
        } catch (error) {
            console.error("Error generando el enlace:", error);
            alert("Hubo un error al generar el enlace. Verifica la consola.");
        } finally {
            setCargando(false);
        }
    };

    // FUNCIÓN DE COPIADO ULTRA-ROBUSTA (Funciona en HTTP y HTTPS)
    const copiarAlPortapapeles = async () => {
        if (!enlaceGenerado) return;

        try {
            if (navigator.clipboard && window.isSecureContext) {
                // Modo seguro (HTTPS o localhost)
                await navigator.clipboard.writeText(enlaceGenerado);
            } else {
                // Fallback para HTTP en red local
                const textArea = document.createElement("textarea");
                textArea.value = enlaceGenerado;
                textArea.style.position = "fixed";
                textArea.style.left = "-999999px";
                textArea.style.top = "-999999px";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                document.execCommand('copy');
                textArea.remove();
            }

            setCopiado(true);
            setTimeout(() => setCopiado(false), 3000);
            
        } catch (error) {
            console.error("Error al copiar al portapapeles:", error);
            alert("Tu navegador bloqueó el copiado automático. Por favor, selecciona el texto y cópialo manualmente.");
        }
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Generación de Accesos | GELIANV" />
            
            <div className="max-w-7xl mx-auto p-4 md:p-8 space-y-8">
                
                {/* --- HEADER --- */}
                <div className="animate-page-reveal flex flex-col md:flex-row justify-between items-start md:items-center gap-6 theme-surface border theme-border p-6 md:p-8 rounded-[2rem] md:rounded-[3rem] shadow-sm">
                    <div>
                        <div className="flex items-center space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Protocolo de Seguridad</p>
                        </div>
                        <h1 className="text-3xl md:text-4xl font-black theme-text-main flex items-center gap-3 italic uppercase tracking-tighter m-0">
                            <ShieldCheck className="w-8 h-8 md:w-10 md:h-10 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                            GENERACIÓN DE <span style={{ color: 'var(--color-primario)' }}>ACCESOS</span>
                        </h1>
                        <p className="theme-text-muted text-[10px] font-bold uppercase tracking-widest mt-2 opacity-80 max-w-2xl">
                            Crea enlaces criptográficos seguros para la identidad y el registro de nuevos colaboradores operativos.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    
                    {/* --- PANEL PRINCIPAL --- */}
                    <div className="animate-page-reveal lg:col-span-2 theme-surface border theme-border rounded-[2.5rem] p-6 md:p-10 shadow-sm relative overflow-hidden transition-all duration-300" style={{ animationDelay: '100ms' }}>
                        <div className="absolute -top-10 -right-10 opacity-[0.03] pointer-events-none dark:opacity-[0.02]">
                            <Key className="w-64 h-64" style={{ color: 'var(--color-primario)' }} />
                        </div>

                        <div className="relative z-10 space-y-8">
                            <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-2 border-b theme-border pb-4">
                                <LinkIcon className="w-5 h-5" style={{ color: 'var(--color-primario)' }} /> 
                                Configuración de Enlace
                            </h2>

                            <div className="space-y-6 max-w-xl">
                                <div>
                                    <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1">Seleccionar Nivel de Acceso_</label>
                                    <div className="relative group/select mt-2">
                                        <Briefcase className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                        <select 
                                            value={rolSeleccionado} 
                                            onChange={(e) => setRolSeleccionado(e.target.value)}
                                            className="w-full pl-12 pr-12 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none appearance-none cursor-pointer transition-all focus:ring-2 shadow-sm hover:shadow-md"
                                            style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                            onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'}
                                            onBlur={(e) => e.target.style.borderColor = ''}
                                        >
                                            {rolesDisponibles.map(rol => (
                                                <option key={rol} value={rol} className="theme-option font-bold uppercase">
                                                    {rol}
                                                </option>
                                            ))}
                                        </select>
                                        <div className="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none theme-text-muted group-hover/select:text-[var(--color-primario)] transition-colors">
                                            <ChevronDown className="w-5 h-5" />
                                        </div>
                                    </div>
                                </div>

                                {/* INFO DE PERMISOS */}
                                <div className="p-5 rounded-2xl bg-black/5 dark:bg-white/5 border theme-border">
                                    <h4 className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-3 flex items-center gap-1.5">
                                        <ShieldCheck className="w-3.5 h-3.5" style={{ color: 'var(--color-primario)' }} /> 
                                        Privilegios Asignados Automáticamente:
                                    </h4>
                                    <ul className="space-y-2">
                                        {rolesInfo[rolSeleccionado]?.map((permiso, idx) => (
                                            <li key={idx} className="text-[11px] font-bold theme-text-main flex items-start gap-2.5 leading-tight">
                                                <Check className="w-3.5 h-3.5 mt-0.5 shrink-0 text-emerald-500" />
                                                <span className="opacity-90">{permiso}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>

                                <button 
                                    onClick={generarEnlace} 
                                    disabled={cargando}
                                    className="w-full py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] transition-transform shadow-xl hover:scale-105 disabled:opacity-50 disabled:scale-100 outline-none flex items-center justify-center gap-3"
                                    style={{ backgroundColor: 'var(--color-primario)' }}
                                >
                                    {cargando ? <Clock className="w-4 h-4 animate-spin" /> : <Sparkles className="w-4 h-4" />}
                                    {cargando ? 'Cifrando Token...' : 'Generar Enlace Seguro'}
                                </button>
                            </div>

                            {/* --- CAJA DE RESULTADO --- */}
                            {enlaceGenerado && (
                                <div className="animate-fade-in mt-8 p-6 theme-element border-2 border-dashed theme-border rounded-[2rem] space-y-4 bg-black/5 dark:bg-white/5">
                                    <label className="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest italic" style={{ color: 'var(--color-primario)' }}>
                                        <Key className="w-3 h-3" /> Token Criptográfico Generado_
                                    </label>
                                    
                                    <div className="flex flex-col sm:flex-row items-center gap-3">
                                        <input 
                                            type="text" 
                                            readOnly 
                                            value={enlaceGenerado} 
                                            className="w-full sm:flex-1 p-4 bg-white dark:bg-[#121212] border theme-border rounded-xl theme-text-main font-bold text-xs truncate shadow-inner outline-none focus:ring-1"
                                            style={{ '--tw-ring-color': 'var(--color-primario)' }} 
                                            onClick={(e) => e.target.select()}
                                        />
                                        <button 
                                            onClick={copiarAlPortapapeles} 
                                            className="w-full sm:w-auto px-6 py-4 theme-surface border theme-border rounded-xl transition-all group/copy hover:border-[var(--color-primario)] flex items-center justify-center gap-2 outline-none shadow-sm"
                                            title="Copiar enlace al portapapeles"
                                        >
                                            {copiado ? (
                                                <>
                                                    <CheckCircle className="w-5 h-5 text-emerald-500" />
                                                    <span className="text-[10px] font-black uppercase tracking-widest text-emerald-500 sm:hidden">Copiado</span>
                                                </>
                                            ) : (
                                                <>
                                                    <Copy className="w-5 h-5 theme-text-muted group-hover/copy:text-[var(--color-primario)] transition-colors" />
                                                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted group-hover/copy:text-[var(--color-primario)] transition-colors sm:hidden">Copiar</span>
                                                </>
                                            )}
                                        </button>
                                    </div>
                                    <p className="text-[9px] font-black uppercase text-emerald-600 dark:text-emerald-400 italic flex items-center gap-1.5 mt-2 bg-emerald-500/10 w-fit px-3 py-1.5 rounded-lg border border-emerald-500/20">
                                        <ShieldCheck className="w-3 h-3" /> Válido por 48 horas bajo firma digital.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* --- SIDEBAR INFORMATIVO --- */}
                    <div className="animate-page-reveal lg:col-span-1 space-y-4" style={{ animationDelay: '200ms' }}>
                        <div className="p-6 theme-surface border-[1.5px] theme-border rounded-[2rem] shadow-sm flex flex-col gap-3">
                            <div className="flex items-center gap-2 pb-3 border-b theme-border">
                                <ShieldCheck className="w-5 h-5 theme-text-main" />
                                <h4 className="text-[10px] font-black uppercase tracking-widest theme-text-main">Firma Digital</h4>
                            </div>
                            <p className="text-[11px] theme-text-main leading-relaxed font-bold italic tracking-wide">
                                Estos enlaces utilizan <span className="text-emerald-500 dark:text-emerald-400 font-black">URLs firmadas</span>. Si el enlace es alterado en el navegador, el sistema invalidará el acceso por seguridad.
                            </p>
                        </div>

                        <div className="p-6 theme-surface border-[1.5px] border-amber-500/40 rounded-[2rem] shadow-sm flex flex-col gap-3 relative overflow-hidden">
                            <div className="absolute inset-0 bg-amber-500/5 pointer-events-none"></div>
                            <div className="relative z-10 flex items-center gap-2 pb-3 border-b border-amber-500/20">
                                <Clock className="w-5 h-5 text-amber-500" />
                                <h4 className="text-[10px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-500">Expiración</h4>
                            </div>
                            <p className="relative z-10 text-[11px] theme-text-main leading-relaxed font-bold italic tracking-wide">
                                Al expirar las 48 horas, deberás generar un nuevo token. <span className="font-black text-amber-600 dark:text-amber-500">No se pueden reutilizar</span> enlaces caducados.
                            </p>
                        </div>

                        <div className="p-6 theme-surface border-[1.5px] border-blue-500/40 rounded-[2rem] shadow-sm flex flex-col gap-3 relative overflow-hidden">
                            <div className="absolute inset-0 bg-blue-500/5 pointer-events-none"></div>
                            <div className="relative z-10 flex items-center gap-2 pb-3 border-b border-blue-500/20">
                                <AlertTriangle className="w-5 h-5 text-blue-500" />
                                <h4 className="text-[10px] font-black uppercase tracking-widest text-blue-600 dark:text-blue-500">Uso Único</h4>
                            </div>
                            <p className="relative z-10 text-[11px] theme-text-main leading-relaxed font-bold italic tracking-wide">
                                Comparte este enlace exclusivamente con el colaborador. Cada enlace <span className="font-black text-blue-600 dark:text-blue-500">hereda el rol</span> seleccionado.
                            </p>
                        </div>
                    </div>
                </div>

                <style>{`
                    .theme-option { background-color: white; color: #18181b; font-weight: 700; }
                    .dark .theme-option { background-color: #1A1A1A; color: white; }
                    select { -webkit-appearance: none; -moz-appearance: none; appearance: none; }
                `}</style>
            </div>
        </AppLayout>
    );
}