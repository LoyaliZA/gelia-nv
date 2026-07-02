import React, { useRef, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { UploadCloud, Download, CheckSquare, Info } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';
import { geliaCardClass } from '../../utils/geliaTheme';

export default function Transacciones({ auth }) {
    const [procesando, setProcesando] = useState(false);
    const [errorMsg, setErrorMsg] = useState(null);
    const [successMsg, setSuccessMsg] = useState(null);
    const fileInputRef = useRef(null);

    const { data, setData, clearErrors } = useForm({
        archivo_transacciones: null,
    });

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        setData('archivo_transacciones', file);
        clearErrors('archivo_transacciones');
        if (file) {
            setSuccessMsg(`Archivo cargado: ${file.name}`);
            setTimeout(() => setSuccessMsg(null), 3000);
        }
    };

    const procesarSolicitud = async (e) => {
        e.preventDefault();
        if (!data.archivo_transacciones) {
            setErrorMsg("Sube el archivo de Transacciones");
            return;
        }

        setErrorMsg(null);
        setProcesando(true);

        const formData = new FormData();
        formData.append('archivo_transacciones', data.archivo_transacciones);

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(route('funciones.transacciones.procesar'), {
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
            let fileName = `Transacciones-Limpias.xlsx`;
            if (contentDisposition) {
                const fileNameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
                if (fileNameMatch && fileNameMatch.length === 2) fileName = fileNameMatch[1];
            }

            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            a.remove();
            
            setSuccessMsg("¡Archivo de Transacciones Generado Exitosamente!");
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
            <Head title="Depuración de Transacciones" />
            <GeliaLoader isVisible={procesando} message="Procesando Transacciones_" />

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                
                <header className={`${geliaCardClass()} p-6 md:p-10 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 border-b-[4px] border-[var(--color-primario)]`}>
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Herramientas</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            DEPURACIÓN DE <span style={{ color: 'var(--color-primario)' }}>TRANSACCIONES</span>
                        </h1>
                        <p className="theme-text-muted mt-2 text-sm font-medium">Limpia los saltos de línea y devuelve el formato correcto.</p>
                    </div>
                </header>

                <div className={`${geliaCardClass()} p-6 border-l-4`} style={{ borderLeftColor: 'var(--color-primario)' }}>
                    <h3 className="text-sm font-black mb-3 flex items-center gap-2" style={{ color: 'var(--color-primario)' }}>
                        <Info className="w-5 h-5" />
                        Instrucciones del Módulo
                    </h3>
                    <ul className="list-disc list-inside text-xs theme-text-muted space-y-1.5 font-medium ml-2">
                        <li>Sube el archivo Excel de Transacciones.</li>
                        <li>El sistema eliminará los saltos de línea internos en cada celda que rompen el formato.</li>
                        <li>El archivo resultante estará listo para ser importado o analizado.</li>
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
                        className={`w-full border-2 border-dashed rounded-2xl p-12 text-center cursor-pointer transition-all ${data.archivo_transacciones ? 'border-[var(--color-primario)] bg-[var(--color-primario)]/5' : 'theme-border hover:border-[var(--color-primario)] hover:bg-black/5 dark:hover:bg-white/5'}`}
                        onClick={() => fileInputRef.current?.click()}
                    >
                        <input 
                            type="file" 
                            ref={fileInputRef}
                            className="hidden" 
                            accept=".xls,.xlsx,.csv"
                            onChange={handleFileChange}
                        />
                        <UploadCloud className={`w-16 h-16 mx-auto mb-4 ${data.archivo_transacciones ? 'text-[var(--color-primario)]' : 'theme-text-muted'}`} />
                        <h4 className="text-base font-black uppercase theme-text-main">
                            {data.archivo_transacciones ? 'Archivo Seleccionado' : 'Subir Archivo de Transacciones'}
                        </h4>
                        <p className="text-xs font-bold theme-text-muted mt-2 uppercase">
                            {data.archivo_transacciones ? data.archivo_transacciones.name : 'Haz clic para seleccionar .xls o .xlsx'}
                        </p>
                    </div>

                    <button 
                        type="submit" 
                        disabled={procesando || !data.archivo_transacciones}
                        className={`w-full py-4 rounded-xl font-black uppercase tracking-widest text-sm transition-all flex justify-center items-center gap-2 shadow-lg hover:shadow-xl
                            ${!data.archivo_transacciones ? 'opacity-50 cursor-not-allowed theme-surface theme-text-muted border theme-border' : 'text-white hover:scale-[1.02]'}`}
                        style={data.archivo_transacciones ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        <Download className="w-5 h-5" />
                        {procesando ? 'Procesando...' : 'Procesar y Descargar Limpio'}
                    </button>

                </form>

            </div>
        </AppLayout>
    );
}
