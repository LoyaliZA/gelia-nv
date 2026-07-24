import React, { useRef, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { UploadCloud, Download, CheckSquare, Info, Copy, Check } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';
import { geliaCardClass } from '../../utils/geliaTheme';

export default function Avisos({ auth }) {
    const [procesando, setProcesando] = useState(false);
    const [errorMsg, setErrorMsg] = useState(null);
    const [successMsg, setSuccessMsg] = useState(null);
    const [resultados, setResultados] = useState([]);
    const [copiadoSku, setCopiadoSku] = useState(null);
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
        setResultados([]);
    };

    const armarFormData = (descargar = false) => {
        const formData = new FormData();
        formData.append('orden_compra', data.orden_compra);
        formData.append('aviso_mercancia', data.aviso_mercancia);
        if (descargar) formData.append('descargar', '1');
        return formData;
    };

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const procesarSolicitud = async (e) => {
        e.preventDefault();
        if (!data.orden_compra || !data.aviso_mercancia) {
            setErrorMsg('Sube ambos archivos requeridos.');
            return;
        }

        setErrorMsg(null);
        setSuccessMsg(null);
        setResultados([]);
        setProcesando(true);

        try {
            const response = await fetch(route('funciones.avisos.procesar'), {
                method: 'POST',
                body: armarFormData(false),
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
            });

            const resData = await response.json();

            if (!response.ok) {
                if (resData.errors) {
                    setErrorMsg(Object.values(resData.errors).flat().join(' | '));
                } else {
                    const meta = resData.meta
                        ? ` (Aviso: ${resData.meta.avisos_cargados} UPCs · OC: ${resData.meta.filas_compra} filas)`
                        : '';
                    setErrorMsg((resData.error || 'Error en el servidor al procesar el archivo.') + meta);
                }
                return;
            }

            setResultados(resData.data || []);
            setSuccessMsg(`Cruce exitoso: ${resData.count} coincidencias.`);
            setTimeout(() => setSuccessMsg(null), 5000);
        } catch (error) {
            console.error(error);
            setErrorMsg('Error: ' + error.message);
        } finally {
            setProcesando(false);
        }
    };

    const descargarExcel = async () => {
        if (!data.orden_compra || !data.aviso_mercancia) return;

        setErrorMsg(null);
        setProcesando(true);

        try {
            const response = await fetch(route('funciones.avisos.procesar'), {
                method: 'POST',
                body: armarFormData(true),
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                const resData = await response.json();
                setErrorMsg(resData.error || 'No se pudo generar el Excel.');
                return;
            }

            const blob = await response.blob();
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;

            const contentDisposition = response.headers.get('Content-Disposition');
            let fileName = 'Aviso-Mercancia-Cruzado.xlsx';
            if (contentDisposition) {
                const fileNameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
                if (fileNameMatch?.length === 2) fileName = fileNameMatch[1];
            }

            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(downloadUrl);
        } catch (error) {
            console.error(error);
            setErrorMsg('Error: ' + error.message);
        } finally {
            setProcesando(false);
        }
    };

    const copiarSku = async (sku) => {
        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(sku);
            } else {
                const ta = document.createElement('textarea');
                ta.value = sku;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
            setCopiadoSku(sku);
            setTimeout(() => setCopiadoSku(null), 2000);
        } catch {
            setErrorMsg('No se pudo copiar el SKU.');
        }
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Cruce de Aviso de Mercancía" />
            <GeliaLoader isVisible={procesando} message="Cruzando Archivos_" />

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header
                    className={`${geliaCardClass()} p-6 md:p-10 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 border-b-[4px] border-[var(--color-primario)]`}
                >
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p
                                className="text-[10px] font-black uppercase tracking-[0.3em]"
                                style={{ color: 'var(--color-primario)' }}
                            >
                                Herramientas
                            </p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            AVISO DE <span style={{ color: 'var(--color-primario)' }}>MERCANCÍA</span>
                        </h1>
                        <p className="theme-text-muted mt-2 text-sm font-medium">
                            Validación automática de SKUs entre Orden de Compra y Aviso de Drive.
                        </p>
                    </div>
                </header>

                <div className={`${geliaCardClass()} p-6 border-l-4`} style={{ borderLeftColor: 'var(--color-primario)' }}>
                    <h3 className="text-sm font-black mb-3 flex items-center gap-2" style={{ color: 'var(--color-primario)' }}>
                        <Info className="w-5 h-5" />
                        Instrucciones del Módulo
                    </h3>
                    <ul className="list-disc list-inside text-xs theme-text-muted space-y-1.5 font-medium ml-2">
                        <li>Sube la Orden de Compra exportada desde Wizerp (Excel o CSV).</li>
                        <li>
                            Sube el Aviso de Mercancía (Drive) con columnas UPC, VENDEDOR y CLIENTE (la fecha
                            inicial puede venir sin encabezado).
                        </li>
                        <li>El sistema cruzará ambos archivos, mostrará la tabla y permitirá descargar Excel.</li>
                    </ul>
                </div>

                {errorMsg && (
                    <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-sm font-bold animate-fade-in flex items-center gap-2">
                        <Info className="w-5 h-5 shrink-0" /> {errorMsg}
                    </div>
                )}
                {successMsg && (
                    <div className="p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-500 text-sm font-bold animate-fade-in flex items-center gap-2">
                        <CheckSquare className="w-5 h-5" /> {successMsg}
                    </div>
                )}

                <form
                    onSubmit={procesarSolicitud}
                    className={`${geliaCardClass()} p-6 md:p-8 flex flex-col items-center gap-6 max-w-4xl mx-auto`}
                >
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 w-full">
                        <div
                            className={`w-full border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all ${data.orden_compra ? 'border-blue-500 bg-blue-500/5' : 'theme-border hover:border-blue-500 hover:bg-black/5 dark:hover:bg-white/5'}`}
                            onClick={() => fileInputRef1.current?.click()}
                        >
                            <input
                                type="file"
                                ref={fileInputRef1}
                                className="hidden"
                                accept=".xls,.xlsx,.csv,.txt"
                                onChange={handleFileChange('orden_compra')}
                            />
                            <UploadCloud
                                className={`w-12 h-12 mx-auto mb-4 ${data.orden_compra ? 'text-blue-500' : 'theme-text-muted'}`}
                            />
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
                                accept=".xls,.xlsx,.csv,.txt"
                                onChange={handleFileChange('aviso_mercancia')}
                            />
                            <UploadCloud
                                className={`w-12 h-12 mx-auto mb-4 ${data.aviso_mercancia ? 'text-cyan-500' : 'theme-text-muted'}`}
                            />
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
                        disabled={procesando || !data.orden_compra || !data.aviso_mercancia}
                        className={`w-full mt-4 py-4 rounded-xl font-black uppercase tracking-widest text-sm transition-all flex justify-center items-center gap-2 shadow-lg hover:shadow-xl
                            ${!data.orden_compra || !data.aviso_mercancia ? 'opacity-50 cursor-not-allowed theme-surface theme-text-muted border theme-border' : 'text-white hover:scale-[1.02]'}`}
                        style={data.orden_compra && data.aviso_mercancia ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        <CheckSquare className="w-5 h-5" />
                        {procesando ? 'Procesando...' : 'Cruzar e Identificar Mercancía'}
                    </button>
                </form>

                {resultados.length > 0 && (
                    <div className={`${geliaCardClass()} p-4 md:p-6 space-y-4`}>
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h2 className="text-lg md:text-xl font-black uppercase theme-text-main m-0 flex items-center gap-3">
                                    <span className="w-2 h-6 rounded" style={{ backgroundColor: 'var(--color-primario)' }} />
                                    Mercancía encontrada
                                </h2>
                                <p className="text-xs theme-text-muted font-medium mt-1">
                                    {resultados.length} coincidencias entre Orden de Compra y Aviso
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={descargarExcel}
                                disabled={procesando}
                                className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider text-white shadow-md hover:opacity-90 transition-opacity"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Download className="w-4 h-4" />
                                Descargar Excel
                            </button>
                        </div>

                        <div className="overflow-x-auto rounded-xl border theme-border">
                            <table className="w-full text-left text-sm">
                                <thead className="theme-element text-[10px] font-black uppercase tracking-wider theme-text-muted">
                                    <tr>
                                        <th className="px-4 py-3 text-center w-14">Copiar</th>
                                        <th className="px-4 py-3 w-40">SKU / UPC</th>
                                        <th className="px-4 py-3">Descripción</th>
                                        <th className="px-4 py-3 text-center w-28">Piezas</th>
                                        <th className="px-4 py-3">Vendedor</th>
                                        <th className="px-4 py-3">Clientes en espera</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y theme-border">
                                    {resultados.map((fila, idx) => (
                                        <tr key={`${fila.SKU}-${idx}`} className="theme-text-main hover:bg-black/5 dark:hover:bg-white/5">
                                            <td className="px-4 py-3 text-center">
                                                <button
                                                    type="button"
                                                    title="Copiar SKU"
                                                    onClick={() => copiarSku(fila.SKU)}
                                                    className="p-2 rounded-lg border theme-border theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-colors"
                                                >
                                                    {copiadoSku === fila.SKU ? (
                                                        <Check className="w-4 h-4 text-emerald-500" />
                                                    ) : (
                                                        <Copy className="w-4 h-4" />
                                                    )}
                                                </button>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-[var(--color-primario)] font-bold tracking-tight">
                                                {fila.SKU}
                                            </td>
                                            <td className="px-4 py-3 max-w-md truncate" title={fila['Descripción']}>
                                                {fila['Descripción']}
                                            </td>
                                            <td className="px-4 py-3 text-center font-black text-emerald-600 dark:text-emerald-400">
                                                {fila['Piezas Recibidas']}
                                            </td>
                                            <td className="px-4 py-3 text-xs font-medium max-w-[10rem] truncate" title={fila['Vendedor Asignado']}>
                                                {fila['Vendedor Asignado']}
                                            </td>
                                            <td className="px-4 py-3 text-xs theme-text-muted max-w-sm truncate" title={fila['Clientes en Espera']}>
                                                {fila['Clientes en Espera']}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
