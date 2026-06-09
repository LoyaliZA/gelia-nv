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
        tipo_entrega: 'inmediata',
        fecha_programada: '',
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
        formData.append('tipo_entrega', data.tipo_entrega);
        if (data.tipo_entrega === 'fecha' && data.fecha_programada) {
            formData.append('fecha_programada', data.fecha_programada);
        }

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

            if (data.tipo_entrega === 'inmediata') {
                setSuccessMsg("¡Plantilla Generada y Enviada Exitosamente!");
                // Trigger download
                const a = document.createElement("a");
                a.href = resData.download_url;
                a.target = "_blank";
                document.body.appendChild(a);
                a.click();
                a.remove();
            } else {
                setSuccessMsg("¡Plantilla Programada Exitosamente!");
            }

            onSuccess(resData.template);
            reset();

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
                    className={`border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer transition-all ${data.existencias ? 'bg-black/5 dark:bg-white/5' : 'theme-border hover:bg-black/5 dark:hover:bg-white/5'}`}
                    style={data.existencias ? { borderColor: 'var(--color-primario)' } : {}}
                    onMouseEnter={(e) => { if(!data.existencias) e.currentTarget.style.borderColor = 'var(--color-primario)'; }}
                    onMouseLeave={(e) => { if(!data.existencias) e.currentTarget.style.borderColor = ''; }}
                    onClick={() => existenciasRef.current?.click()}
                >
                    <input 
                        type="file" 
                        ref={existenciasRef}
                        className="hidden" 
                        accept=".csv,.txt,.xlsx,.xls"
                        onChange={(e) => handleFileChange(e, 'existencias')}
                    />
                    <UploadCloud className={`w-10 h-10 mx-auto mb-3 ${data.existencias ? '' : 'theme-text-muted'}`} style={data.existencias ? { color: 'var(--color-primario)' } : {}} />
                    <h4 className="text-sm font-black uppercase theme-text-main">
                        {data.existencias ? 'Archivo de Existencias' : 'Subir Existencias'}
                    </h4>
                    <p className="text-[10px] font-bold theme-text-muted mt-1 uppercase">
                        {data.existencias ? data.existencias.name : 'Haz clic para seleccionar'}
                    </p>
                </div>

                {/* Upload Precios */}
                <div 
                    className={`border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer transition-all ${data.precios ? 'bg-black/5 dark:bg-white/5' : 'theme-border hover:bg-black/5 dark:hover:bg-white/5'}`}
                    style={data.precios ? { borderColor: 'var(--color-primario)' } : {}}
                    onMouseEnter={(e) => { if(!data.precios) e.currentTarget.style.borderColor = 'var(--color-primario)'; }}
                    onMouseLeave={(e) => { if(!data.precios) e.currentTarget.style.borderColor = ''; }}
                    onClick={() => preciosRef.current?.click()}
                >
                    <input 
                        type="file" 
                        ref={preciosRef}
                        className="hidden" 
                        accept=".csv,.txt,.xlsx,.xls"
                        onChange={(e) => handleFileChange(e, 'precios')}
                    />
                    <UploadCloud className={`w-10 h-10 mx-auto mb-3 ${data.precios ? '' : 'theme-text-muted'}`} style={data.precios ? { color: 'var(--color-primario)' } : {}} />
                    <h4 className="text-sm font-black uppercase theme-text-main">
                        {data.precios ? 'Archivo de Precios' : 'Subir Precios'}
                    </h4>
                    <p className="text-[10px] font-bold theme-text-muted mt-1 uppercase">
                        {data.precios ? data.precios.name : 'Haz clic para seleccionar'}
                    </p>
                </div>

                <div className="flex flex-col gap-3 p-4 theme-surface border theme-border rounded-xl">
                    <span className="text-sm font-black uppercase theme-text-main">Programar Entrega</span>
                    
                    <div className="flex flex-wrap gap-6 mt-2">
                        {[
                            { id: 'inmediata', label: 'Inmediata' },
                            { id: 'manana', label: 'Mañana (7 AM)' },
                            { id: 'fecha', label: 'Fecha Específica (7 AM)' }
                        ].map((opcion) => (
                            <label key={opcion.id} className="flex items-center space-x-2 cursor-pointer group">
                                <input 
                                    type="radio" 
                                    name="tipo_entrega"
                                    value={opcion.id}
                                    checked={data.tipo_entrega === opcion.id}
                                    onChange={(e) => setData('tipo_entrega', e.target.value)}
                                    className="hidden" 
                                />
                                <div className="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all duration-200"
                                     style={{ borderColor: data.tipo_entrega === opcion.id ? 'var(--color-primario)' : '#4b5563' }}
                                >
                                    {data.tipo_entrega === opcion.id && (
                                        <div className="w-2.5 h-2.5 rounded-full animate-fade-in" style={{ backgroundColor: 'var(--color-primario)' }}></div>
                                    )}
                                </div>
                                <span className={`text-xs font-black uppercase transition-colors duration-200 ${data.tipo_entrega === opcion.id ? 'theme-text-main' : 'theme-text-muted group-hover:opacity-80'}`}>
                                    {opcion.label}
                                </span>
                            </label>
                        ))}
                    </div>

                    {data.tipo_entrega === 'fecha' && (
                        <div className="mt-2 animate-fade-in">
                            <input 
                                type="date" 
                                value={data.fecha_programada}
                                onChange={(e) => setData('fecha_programada', e.target.value)}
                                className="w-full px-4 py-2 text-sm theme-surface border theme-border rounded-lg theme-text-main focus:outline-none transition-colors"
                                style={{ backgroundColor: 'var(--color-fondo-secundario)' }}
                                onFocus={(e) => { e.currentTarget.style.borderColor = 'var(--color-primario)'; }}
                                onBlur={(e) => { e.currentTarget.style.borderColor = ''; }}
                                required={data.tipo_entrega === 'fecha'}
                            />
                            <p className="text-[10px] font-bold theme-text-muted mt-2 uppercase">La plantilla se entregará el día seleccionado a las 7:00 AM.</p>
                        </div>
                    )}
                    {data.tipo_entrega === 'manana' && (
                        <p className="text-[10px] font-bold theme-text-muted mt-1 uppercase">La plantilla se entregará mañana a las 7:00 AM.</p>
                    )}
                </div>

                <button 
                    type="submit" 
                    disabled={procesando || !data.existencias || !data.precios}
                    className={`mt-4 py-4 rounded-xl font-black uppercase tracking-widest text-xs transition-all flex justify-center items-center gap-2 shadow-lg hover:shadow-xl
                        ${(!data.existencias || !data.precios) ? 'opacity-50 cursor-not-allowed theme-surface theme-text-muted border theme-border' : 'text-white hover:scale-[1.02]'}`}
                    style={(data.existencias && data.precios) ? { backgroundColor: 'var(--color-primario)' } : {}}
                >
                    <Download className="w-5 h-5" />
                    {procesando ? 'Procesando...' : 'Generar Plantilla'}
                </button>
            </form>
        </div>
    );
}
