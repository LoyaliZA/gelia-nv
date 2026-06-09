import React, { useState, useRef } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Download, UploadCloud, CheckSquare, Info } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';
import { geliaCardClass } from '../../utils/geliaTheme';

const COLUMNAS_DISPONIBLES = ['ID', 'NOMBRE', 'DIRECCION_FISCAL', 'COLONIA_FISCAL', 'MUNICIPIO_FISCAL', 'CP_FISCAL', 'ESTADO_FISCAL', 'PAIS_FISCAL', 'DIRECCION_CONTACTO', 'COLONIA_CONTACTO', 'MUNICIPIO_CONTACTO', 'ESTADO_CONTACTO', 'PAIS_CONTACTO', 'CP_CONTACTO', 'RFC', 'TELEFONO', 'EMAIL', 'LIMITE_CREDITO', 'CREDITO_DISPONIBLE', 'DIAS_CHEQUE_POSTFECHADO', 'DIAS_VENCIMIENTO', 'PARTE_RELACIONAL', 'REGIMEN_FISCAL', 'USO_DE_CFDI', 'GRUPO_DESCUENTO', 'VARIABLE_CONTABLE', 'TAGS', 'TIPO'];

export default function LimpiezaClientes({ auth }) {
    const [procesando, setProcesando] = useState(false);
    const [errorMsg, setErrorMsg] = useState(null);
    const [successMsg, setSuccessMsg] = useState(null);
    const fileInputRef = useRef(null);

    const { data, setData, reset, errors, clearErrors } = useForm({
        clientes: null,
        columnas_clientes: ['ID', 'NOMBRE'],
        incluir_sin_id: true,
        orden_clientes: '',
        filtro_especial: false
    });

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        setData('clientes', file);
        clearErrors('clientes');
        if (file) {
            setSuccessMsg(`Archivo cargado: ${file.name}`);
            setTimeout(() => setSuccessMsg(null), 3000);
        }
    };

    const toggleColumna = (col) => {
        const current = [...data.columnas_clientes];
        if (current.includes(col)) {
            setData('columnas_clientes', current.filter(c => c !== col));
        } else {
            setData('columnas_clientes', [...current, col]);
        }
    };

    const toggleTodasColumnas = () => {
        if (data.columnas_clientes.length === COLUMNAS_DISPONIBLES.length) {
            setData('columnas_clientes', []);
        } else {
            setData('columnas_clientes', [...COLUMNAS_DISPONIBLES]);
        }
    };

    const procesarSolicitud = async (e) => {
        e.preventDefault();
        if (!data.clientes) {
            setErrorMsg("Sube el archivo CSV o TXT de Clientes");
            return;
        }
        if (data.columnas_clientes.length === 0) {
            setErrorMsg("Selecciona al menos una columna para exportar");
            return;
        }

        setErrorMsg(null);
        setProcesando(true);

        const formData = new FormData();
        formData.append('clientes', data.clientes);
        formData.append('columnas_clientes', data.columnas_clientes.join(','));
        formData.append('incluir_sin_id', data.incluir_sin_id ? '1' : '0');
        formData.append('filtro_especial', data.filtro_especial ? '1' : '0');
        if (data.orden_clientes) {
            formData.append('orden_clientes', data.orden_clientes);
        }

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(route('funciones.limpieza-clientes.procesar'), {
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
            let fileName = `CLIENTES-SANITIZADOS.xlsx`;
            if (contentDisposition) {
                const fileNameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
                if (fileNameMatch && fileNameMatch.length === 2) fileName = fileNameMatch[1];
            }

            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            a.remove();
            
            setSuccessMsg("¡Archivo de Clientes Generado Exitosamente!");
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
            <Head title="Limpieza de Clientes" />
            <GeliaLoader isVisible={procesando} message="Procesando Base de Datos_" />

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                
                {/* Cabecera */}
                <header className={`${geliaCardClass()} p-6 md:p-10 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 border-b-[4px] border-[var(--color-primario)]`}>
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Herramientas</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            LIMPIEZA DE <span style={{ color: 'var(--color-primario)' }}>CLIENTES</span>
                        </h1>
                        <p className="theme-text-muted mt-2 text-sm font-medium">Corrección de codificación y formateo de base de datos Wizerp.</p>
                    </div>
                </header>

                {/* Instrucciones */}
                <div className={`${geliaCardClass()} p-6 border-l-4`} style={{ borderLeftColor: 'var(--color-primario)' }}>
                    <h3 className="text-sm font-black mb-3 flex items-center gap-2" style={{ color: 'var(--color-primario)' }}>
                        <Info className="w-5 h-5" />
                        Instrucciones del Módulo
                    </h3>
                    <ol className="list-decimal list-inside text-xs theme-text-muted space-y-1.5 font-medium ml-2">
                        <li>Sube el archivo CSV o TXT crudo de clientes obtenido del sistema Wizerp.</li>
                        <li>Selecciona si deseas incluir o excluir prospectos (clientes sin ID asignado).</li>
                        <li>Selecciona las columnas que requieres exportar para armar tu reporte personalizado.</li>
                        <li>Haz clic en <strong>Procesar Archivo</strong> para descargar el Excel sanitizado con codificación UTF-8.</li>
                    </ol>
                </div>

                {/* Alertas */}
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

                <form onSubmit={procesarSolicitud} className={`${geliaCardClass()} p-6 md:p-8 flex flex-col lg:flex-row gap-8`}>
                    
                    {/* Panel Izquierdo: Configuración */}
                    <div className="w-full lg:w-1/3 flex flex-col gap-6">
                        
                        {/* Upload Area */}
                        <div 
                            className={`border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all ${data.clientes ? 'border-[var(--color-primario)] bg-[var(--color-primario)]/5' : 'theme-border hover:border-[var(--color-primario)] hover:bg-black/5 dark:hover:bg-white/5'}`}
                            onClick={() => fileInputRef.current?.click()}
                        >
                            <input 
                                type="file" 
                                ref={fileInputRef}
                                className="hidden" 
                                accept=".csv,.txt"
                                onChange={handleFileChange}
                            />
                            <UploadCloud className={`w-12 h-12 mx-auto mb-3 ${data.clientes ? 'text-[var(--color-primario)]' : 'theme-text-muted'}`} />
                            <h4 className="text-sm font-black uppercase theme-text-main">
                                {data.clientes ? 'Archivo Seleccionado' : 'Subir CSV Clientes'}
                            </h4>
                            <p className="text-[10px] font-bold theme-text-muted mt-1 uppercase">
                                {data.clientes ? data.clientes.name : 'Haz clic para seleccionar archivo .csv o .txt'}
                            </p>
                        </div>

                        {/* Opciones */}
                        <div className="space-y-4">
                            <label className="flex items-start space-x-3 cursor-pointer group p-4 theme-surface border theme-border rounded-xl hover:border-[var(--color-primario)] transition-all">
                                <input 
                                    type="checkbox" 
                                    checked={data.incluir_sin_id}
                                    onChange={(e) => setData('incluir_sin_id', e.target.checked)}
                                    className="mt-0.5 w-5 h-5 rounded border-gray-300 text-[var(--color-primario)] focus:ring-[var(--color-primario)] cursor-pointer" 
                                />
                                <div className="flex flex-col">
                                    <span className="text-sm font-black uppercase theme-text-main group-hover:text-[var(--color-primario)] transition-colors">Incluir Prospectos</span>
                                    <span className="text-[10px] font-bold theme-text-muted uppercase">Exporta clientes que aún no tienen ID asignado.</span>
                                </div>
                            </label>

                            <label className="flex items-start space-x-3 cursor-pointer group p-4 theme-surface border theme-border rounded-xl hover:border-[var(--color-primario)] transition-all">
                                <input 
                                    type="checkbox" 
                                    checked={data.filtro_especial}
                                    onChange={(e) => setData('filtro_especial', e.target.checked)}
                                    className="mt-0.5 w-5 h-5 rounded border-gray-300 text-[var(--color-primario)] focus:ring-[var(--color-primario)] cursor-pointer" 
                                />
                                <div className="flex flex-col">
                                    <span className="text-sm font-black uppercase theme-text-main group-hover:text-[var(--color-primario)] transition-colors">Filtro Exclusivo</span>
                                    <span className="text-[10px] font-bold theme-text-muted uppercase">Sin Grupo Descuento y Con Tags.</span>
                                </div>
                            </label>

                            <div className="p-4 theme-surface border theme-border rounded-xl">
                                <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Ordenar listado por:</label>
                                <select 
                                    value={data.orden_clientes}
                                    onChange={(e) => setData('orden_clientes', e.target.value)}
                                    className="w-full theme-surface border theme-border rounded-lg p-3 theme-text-main text-sm font-bold focus:border-[var(--color-primario)] focus:ring-1 focus:ring-[var(--color-primario)] outline-none transition-all cursor-pointer"
                                    style={{ backgroundColor: 'var(--color-fondo-secundario, transparent)' }}
                                >
                                    <option value="">Predeterminado (Orden original)</option>
                                    <option value="id_asc">ID (Menor a Mayor)</option>
                                    <option value="id_desc">ID (Mayor a Menor)</option>
                                    <option value="nombre_asc">Nombre (A - Z)</option>
                                    <option value="nombre_desc">Nombre (Z - A)</option>
                                </select>
                            </div>
                        </div>

                        <button 
                            type="submit" 
                            disabled={procesando || !data.clientes}
                            className={`mt-auto py-4 rounded-xl font-black uppercase tracking-widest text-xs transition-all flex justify-center items-center gap-2 shadow-lg hover:shadow-xl
                                ${!data.clientes ? 'opacity-50 cursor-not-allowed theme-surface theme-text-muted border theme-border' : 'text-white hover:scale-[1.02]'}`}
                            style={data.clientes ? { backgroundColor: 'var(--color-primario)' } : {}}
                        >
                            <Download className="w-5 h-5" />
                            {procesando ? 'Procesando...' : 'Procesar Archivo'}
                        </button>

                    </div>

                    {/* Panel Derecho: Columnas */}
                    <div className="w-full lg:w-2/3 border-t lg:border-t-0 lg:border-l theme-border pt-6 lg:pt-0 lg:pl-8 flex flex-col">
                        <div className="flex justify-between items-end mb-6">
                            <div>
                                <h3 className="text-lg font-black uppercase italic tracking-tight theme-text-main">Columnas a Exportar</h3>
                                <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted mt-1">Selecciona los campos que necesitas en el Excel final.</p>
                            </div>
                            <button 
                                type="button" 
                                onClick={toggleTodasColumnas}
                                className="text-[10px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg border theme-border hover:border-[var(--color-primario)] theme-text-main hover:text-[var(--color-primario)] transition-all"
                            >
                                {data.columnas_clientes.length === COLUMNAS_DISPONIBLES.length ? 'Desmarcar Todas' : 'Seleccionar Todas'}
                            </button>
                        </div>

                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 overflow-y-auto custom-scrollbar pr-2 flex-1 max-h-[500px]">
                            {COLUMNAS_DISPONIBLES.map((col) => {
                                const isChecked = data.columnas_clientes.includes(col);
                                return (
                                    <div 
                                        key={col}
                                        onClick={() => toggleColumna(col)}
                                        className={`flex items-center space-x-2 p-3 rounded-xl border cursor-pointer transition-all select-none group
                                            ${isChecked ? 'border-[var(--color-primario)] bg-[var(--color-primario)]/5' : 'theme-surface theme-border hover:border-gray-400 dark:hover:border-gray-500'}`}
                                    >
                                        <div className={`w-4 h-4 rounded flex items-center justify-center transition-colors ${isChecked ? 'bg-[var(--color-primario)]' : 'border border-gray-400 dark:border-gray-500'}`}>
                                            {isChecked && <CheckSquare className="w-3.5 h-3.5 text-white" />}
                                        </div>
                                        <span className={`text-[10px] font-black uppercase tracking-wide truncate ${isChecked ? 'theme-text-main' : 'theme-text-muted group-hover:theme-text-main'}`}>
                                            {col.replace(/_/g, ' ')}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                        {errors.columnas_clientes && (
                            <p className="mt-4 text-[10px] font-bold text-red-500 uppercase tracking-wide">{errors.columnas_clientes}</p>
                        )}
                    </div>
                </form>

            </div>
        </AppLayout>
    );
}
