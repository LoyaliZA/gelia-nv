import React, { useRef, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { UploadCloud, Download, CheckSquare, Info } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';
import { geliaCardClass } from '../../utils/geliaTheme';

export default function LimpiezaArchivos({ auth }) {
    const [procesando, setProcesando] = useState(false);
    const [errorMsg, setErrorMsg] = useState(null);
    const [successMsg, setSuccessMsg] = useState(null);
    const fileInputRef = useRef(null);

    const { data, setData, clearErrors } = useForm({
        archivo_sucio: null,
    });

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        setData('archivo_sucio', file);
        clearErrors('archivo_sucio');
        if (file) {
            setSuccessMsg(`Archivo cargado: ${file.name}`);
            setTimeout(() => setSuccessMsg(null), 3000);
        }
    };

    const procesarSolicitud = async (e) => {
        e.preventDefault();
        if (!data.archivo_sucio) {
            setErrorMsg("Sube el archivo a limpiar");
            return;
        }

        setErrorMsg(null);
        setProcesando(true);

        const formData = new FormData();
        formData.append('archivo_sucio', data.archivo_sucio);

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(route('funciones.limpieza_archivos.procesar'), {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const resData = await response.json();
                if (resData.errors) {
                    setErrorMsg(Object.values(resData.errors).flat().join(' | '));
                } else {
                    setErrorMsg(resData.error || 'Error en el servidor al procesar el archivo.');
                }
                setProcesando(false);
                return;
            }

            const blob = await response.blob();
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = downloadUrl;

            const contentDisposition = response.headers.get('Content-Disposition');
            let fileName = `Archivo-Limpio.csv`;
            if (contentDisposition) {
                const fileNameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
                if (fileNameMatch && fileNameMatch.length === 2) fileName = fileNameMatch[1];
            }

            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            a.remove();
            
            setSuccessMsg("¡Archivo Generado Exitosamente!");
            setTimeout(() => setSuccessMsg(null), 5000);

        } catch (error) {
            console.error(error);
            setErrorMsg("Error: " + error.message);
        } finally {
            setProcesando(false);
        }
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Limpieza de Archivos" />
            <GeliaLoader isVisible={procesando} message="Procesando Archivo_" />

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                
                <header className={`${geliaCardClass()} p-6 md:p-10 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 border-b-[4px] border-[var(--color-primario)]`}>
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Herramientas</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            LIMPIEZA DE <span style={{ color: 'var(--color-primario)' }}>ARCHIVOS</span>
                        </h1>
                        <p className="theme-text-muted mt-2 text-sm font-medium">Remueve apóstrofes y formatea tipos de datos exportados del ERP.</p>
                    </div>
                </header>

                <div className={`${geliaCardClass()} p-6 border-l-4`} style={{ borderLeftColor: 'var(--color-primario)' }}>
                    <h3 className="text-sm font-black mb-3 flex items-center gap-2" style={{ color: 'var(--color-primario)' }}>
                        <Info className="w-5 h-5" />
                        Instrucciones del Módulo
                    </h3>
                    <ul className="list-disc list-inside text-xs theme-text-muted space-y-1.5 font-medium ml-2">
                        <li>Sube el archivo CSV o Excel que deseas procesar.</li>
                        <li>Se removerán todos los apóstrofes (') de cada campo.</li>
                        <li>Se ajustará el formato numérico automáticamente para importaciones.</li>
                    </ul>
                </div>

                {errorMsg && (
                    <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-sm font-bold animate-fade-in flex items-center gap-2">
                        <Info className="w-5 h-5" /> {errorMsg}
                    </div>
                )}
                {successMsg && (
                    <div className="p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-500 text-sm font-bold animate-fade-in flex items-center gap-2">
                        <CheckSquare className="w-5 h-5" /> {successMsg}
                    </div>
                )}

                <form onSubmit={procesarSolicitud} className={`${geliaCardClass()} p-6 md:p-8 flex flex-col items-center gap-6 max-w-3xl mx-auto`}>
                    
                    <div 
                        className={`w-full border-2 border-dashed rounded-2xl p-12 text-center cursor-pointer transition-all ${data.archivo_sucio ? 'border-[var(--color-primario)] bg-[var(--color-primario)]/5' : 'theme-border hover:border-[var(--color-primario)] hover:bg-black/5 dark:hover:bg-white/5'}`}
                        onClick={() => fileInputRef.current?.click()}
                    >
                        <input 
                            type="file" 
                            ref={fileInputRef}
                            className="hidden" 
                            accept=".csv,.txt,.xls,.xlsx"
                            onChange={handleFileChange}
                        />
                        <UploadCloud className={`w-16 h-16 mx-auto mb-4 ${data.archivo_sucio ? 'text-[var(--color-primario)]' : 'theme-text-muted'}`} />
                        <h4 className="text-base font-black uppercase theme-text-main">
                            {data.archivo_sucio ? 'Archivo Seleccionado' : 'Subir Archivo Facturado'}
                        </h4>
                        <p className="text-xs font-bold theme-text-muted mt-2 uppercase">
                            {data.archivo_sucio ? data.archivo_sucio.name : 'Haz clic para seleccionar CSV/Excel'}
                        </p>
                    </div>

                    <button 
                        type="submit" 
                        disabled={procesando || !data.archivo_sucio}
                        className={`w-full py-4 rounded-xl font-black uppercase tracking-widest text-sm transition-all flex justify-center items-center gap-2 shadow-lg hover:shadow-xl
                            ${!data.archivo_sucio ? 'opacity-50 cursor-not-allowed theme-surface theme-text-muted border theme-border' : 'text-white hover:scale-[1.02]'}`}
                        style={data.archivo_sucio ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        <Download className="w-5 h-5" />
                        {procesando ? 'Procesando...' : 'Procesar y Descargar Limpio'}
                    </button>

                </form>

            </div>
        </AppLayout>
    );
}
