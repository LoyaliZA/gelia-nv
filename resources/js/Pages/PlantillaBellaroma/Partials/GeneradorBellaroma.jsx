import React, { useState, useRef } from 'react';
import { useForm } from '@inertiajs/react';
import { UploadCloud, Download, CheckSquare, Info } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import GeliaLoader from '../../../Components/GeliaLoader';

export default function GeneradorBellaroma({ onSuccess }) {
    const [procesando, setProcesando] = useState(false);
    const [errorMsg, setErrorMsg] = useState(null);
    const [successMsg, setSuccessMsg] = useState(null);
    const existenciasRef = useRef(null);
    const preciosRef = useRef(null);

    const { data, setData, reset } = useForm({
        existencias: null,
        precios: null,
        para_manana: false,
    });

    const handleFileChange = (e, field) => {
        const file = e.target.files[0];
        setData(field, file);
    };

    const procesarSolicitud = async (e) => {
        e.preventDefault();
        if (!data.existencias || !data.precios) {
            setErrorMsg("Sube ambos archivos: Existencias y Precios");
            return;
        }

        setErrorMsg(null);
        setProcesando(true);

        const formData = new FormData();
        formData.append('existencias', data.existencias);
        formData.append('precios', data.precios);
        formData.append('para_manana', data.para_manana ? '1' : '0');

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(route('plantilla_bellaroma.generar'), {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });

            const resData = await response.json();

            if (!response.ok) {
                setErrorMsg(resData.message || 'Error al procesar la plantilla.');
                setProcesando(false);
                return;
            }

            setSuccessMsg("¡Plantilla Generada y Enviada Exitosamente!");
            onSuccess(resData.template);
            reset();
            
            // Trigger download
            const a = document.createElement("a");
            a.href = resData.download_url;
            a.target = "_blank";
            document.body.appendChild(a);
            a.click();
            a.remove();

            setTimeout(() => setSuccessMsg(null), 5000);
        } catch (error) {
            console.error(error);
            setErrorMsg("Error: " + error.message);
        } finally {
            setProcesando(false);
        }
    };

    return (
        <div className={`${geliaCardClass()} p-6 md:p-8 flex flex-col gap-6 relative`}>
            <GeliaLoader isVisible={procesando} message="Generando Plantilla_" />
            
            <h2 className="text-xl font-black uppercase tracking-tight theme-text-main border-b theme-border pb-4">
                Generador de Plantilla
            </h2>

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

            <form onSubmit={procesarSolicitud} className="flex flex-col gap-6">
                
                {/* Upload Existencias */}
                <div 
                    className={`border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer transition-all ${data.existencias ? 'border-[#8a2be2] bg-[#8a2be2]/5' : 'theme-border hover:border-[#8a2be2] hover:bg-black/5 dark:hover:bg-white/5'}`}
                    onClick={() => existenciasRef.current?.click()}
                >
                    <input 
                        type="file" 
                        ref={existenciasRef}
                        className="hidden" 
                        accept=".csv,.txt,.xlsx,.xls"
                        onChange={(e) => handleFileChange(e, 'existencias')}
                    />
                    <UploadCloud className={`w-10 h-10 mx-auto mb-3 ${data.existencias ? 'text-[#8a2be2]' : 'theme-text-muted'}`} />
                    <h4 className="text-sm font-black uppercase theme-text-main">
                        {data.existencias ? 'Archivo de Existencias' : 'Subir Existencias'}
                    </h4>
                    <p className="text-[10px] font-bold theme-text-muted mt-1 uppercase">
                        {data.existencias ? data.existencias.name : 'Haz clic para seleccionar'}
                    </p>
                </div>

                {/* Upload Precios */}
                <div 
                    className={`border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer transition-all ${data.precios ? 'border-[#8a2be2] bg-[#8a2be2]/5' : 'theme-border hover:border-[#8a2be2] hover:bg-black/5 dark:hover:bg-white/5'}`}
                    onClick={() => preciosRef.current?.click()}
                >
                    <input 
                        type="file" 
                        ref={preciosRef}
                        className="hidden" 
                        accept=".csv,.txt,.xlsx,.xls"
                        onChange={(e) => handleFileChange(e, 'precios')}
                    />
                    <UploadCloud className={`w-10 h-10 mx-auto mb-3 ${data.precios ? 'text-[#8a2be2]' : 'theme-text-muted'}`} />
                    <h4 className="text-sm font-black uppercase theme-text-main">
                        {data.precios ? 'Archivo de Precios' : 'Subir Precios'}
                    </h4>
                    <p className="text-[10px] font-bold theme-text-muted mt-1 uppercase">
                        {data.precios ? data.precios.name : 'Haz clic para seleccionar'}
                    </p>
                </div>

                <label className="flex items-start space-x-3 cursor-pointer group p-4 theme-surface border theme-border rounded-xl hover:border-[#8a2be2] transition-all">
                    <input 
                        type="checkbox" 
                        checked={data.para_manana}
                        onChange={(e) => setData('para_manana', e.target.checked)}
                        className="mt-0.5 w-5 h-5 rounded border-gray-300 text-[#8a2be2] focus:ring-[#8a2be2] cursor-pointer" 
                    />
                    <div className="flex flex-col">
                        <span className="text-sm font-black uppercase theme-text-main group-hover:text-[#8a2be2] transition-colors">Fechar para mañana</span>
                        <span className="text-[10px] font-bold theme-text-muted uppercase">El nombre del archivo llevará la fecha de mañana.</span>
                    </div>
                </label>

                <button 
                    type="submit" 
                    disabled={procesando || !data.existencias || !data.precios}
                    className={`mt-4 py-4 rounded-xl font-black uppercase tracking-widest text-xs transition-all flex justify-center items-center gap-2 shadow-lg hover:shadow-xl
                        ${(!data.existencias || !data.precios) ? 'opacity-50 cursor-not-allowed theme-surface theme-text-muted border theme-border' : 'text-white hover:scale-[1.02]'}`}
                    style={(data.existencias && data.precios) ? { backgroundColor: '#8a2be2' } : {}}
                >
                    <Download className="w-5 h-5" />
                    {procesando ? 'Procesando...' : 'Generar Plantilla'}
                </button>
            </form>
        </div>
    );
}
