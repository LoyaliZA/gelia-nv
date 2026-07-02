import React, { useRef, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { UploadCloud, Download, CheckSquare, Info } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';
import { geliaCardClass } from '../../utils/geliaTheme';

export default function Avisos({ auth }) {
    const [procesando, setProcesando] = useState(false);
    const [errorMsg, setErrorMsg] = useState(null);
    const [successMsg, setSuccessMsg] = useState(null);
    const fileInputRef1 = useRef(null);
    const fileInputRef2 = useRef(null);

    const { data, setData, clearErrors } = useForm({
        orden_compra: null,
        aviso_mercancia: null,
    });

    const handleFileChange = (field) => (e) => {
        const file = e.target.files[0];
        setData(field, file);
        clearErrors(field);
    };

    const procesarSolicitud = async (e) => {
        e.preventDefault();
        if (!data.orden_compra || !data.aviso_mercancia) {
            setErrorMsg("Sube ambos archivos requeridos.");
            return;
        }

        setErrorMsg(null);
        setProcesando(true);

        const formData = new FormData();
        formData.append('orden_compra', data.orden_compra);
        formData.append('aviso_mercancia', data.aviso_mercancia);

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(route('funciones.avisos.procesar'), {
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
            let fileName = `Aviso-Mercancia-Cruzado.xlsx`;
            if (contentDisposition) {
                const fileNameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
                if (fileNameMatch && fileNameMatch.length === 2) fileName = fileNameMatch[1];
            }

            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            a.remove();
            
            setSuccessMsg("¡Archivos Cruzados Exitosamente!");
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
            <Head title="Cruce de Aviso de Mercancía" />
            <GeliaLoader isVisible={procesando} message="Cruzando Archivos_" />

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                
                <header className={`${geliaCardClass()} p-6 md:p-10 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 border-b-[4px] border-[var(--color-primario)]`}>
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Herramientas</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            AVISO DE <span style={{ color: 'var(--color-primario)' }}>MERCANCÍA</span>
                        </h1>
                        <p className="theme-text-muted mt-2 text-sm font-medium">Validación automática de SKUs entre Orden de Compra y Aviso de Drive.</p>
                    </div>
                </header>

                <div className={`${geliaCardClass()} p-6 border-l-4`} style={{ borderLeftColor: 'var(--color-primario)' }}>
                    <h3 className="text-sm font-black mb-3 flex items-center gap-2" style={{ color: 'var(--color-primario)' }}>
                        <Info className="w-5 h-5" />
                        Instrucciones del Módulo
                    </h3>
                    <ul className="list-disc list-inside text-xs theme-text-muted space-y-1.5 font-medium ml-2">
                        <li>Sube la Orden de Compra exportada desde Wizerp (Excel o CSV).</li>
                        <li>Sube el Aviso de Mercancía (Drive) descargado en Excel o CSV.</li>
                        <li>El sistema cruzará y validará automáticamente ambos archivos.</li>
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

                <form onSubmit={procesarSolicitud} className={`${geliaCardClass()} p-6 md:p-8 flex flex-col items-center gap-6 max-w-4xl mx-auto`}>
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 w-full">
                        <div 
                            className={`w-full border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all ${data.orden_compra ? 'border-blue-500 bg-blue-500/5' : 'theme-border hover:border-blue-500 hover:bg-black/5 dark:hover:bg-white/5'}`}
                            onClick={() => fileInputRef1.current?.click()}
                        >
                            <input 
                                type="file" 
                                ref={fileInputRef1}
                                className="hidden" 
                                accept=".xls,.xlsx,.csv"
                                onChange={handleFileChange('orden_compra')}
                            />
                            <UploadCloud className={`w-12 h-12 mx-auto mb-4 ${data.orden_compra ? 'text-blue-500' : 'theme-text-muted'}`} />
                            <h4 className="text-sm font-black uppercase theme-text-main">
                                {data.orden_compra ? 'Archivo Seleccionado' : '1. Orden de Compra'}
                            </h4>
                            <p className="text-[10px] font-bold theme-text-muted mt-2 uppercase">
                                {data.orden_compra ? data.orden_compra.name : 'Excel o CSV (Wizerp)'}
                            </p>
                        </div>

                        <div 
                            className={`w-full border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all ${data.aviso_mercancia ? 'border-cyan-500 bg-cyan-500/5' : 'theme-border hover:border-cyan-500 hover:bg-black/5 dark:hover:bg-white/5'}`}
                            onClick={() => fileInputRef2.current?.click()}
                        >
                            <input 
                                type="file" 
                                ref={fileInputRef2}
                                className="hidden" 
                                accept=".xls,.xlsx,.csv"
                                onChange={handleFileChange('aviso_mercancia')}
                            />
                            <UploadCloud className={`w-12 h-12 mx-auto mb-4 ${data.aviso_mercancia ? 'text-cyan-500' : 'theme-text-muted'}`} />
                            <h4 className="text-sm font-black uppercase theme-text-main">
                                {data.aviso_mercancia ? 'Archivo Seleccionado' : '2. Aviso de Mercancía'}
                            </h4>
                            <p className="text-[10px] font-bold theme-text-muted mt-2 uppercase">
                                {data.aviso_mercancia ? data.aviso_mercancia.name : 'Excel o CSV (Drive)'}
                            </p>
                        </div>
                    </div>

                    <button 
                        type="submit" 
                        disabled={procesando || (!data.orden_compra || !data.aviso_mercancia)}
                        className={`w-full mt-4 py-4 rounded-xl font-black uppercase tracking-widest text-sm transition-all flex justify-center items-center gap-2 shadow-lg hover:shadow-xl
                            ${(!data.orden_compra || !data.aviso_mercancia) ? 'opacity-50 cursor-not-allowed theme-surface theme-text-muted border theme-border' : 'text-white hover:scale-[1.02]'}`}
                        style={(data.orden_compra && data.aviso_mercancia) ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        <Download className="w-5 h-5" />
                        {procesando ? 'Procesando...' : 'Cruzar e Identificar Mercancía'}
                    </button>

                </form>

            </div>
        </AppLayout>
    );
}
