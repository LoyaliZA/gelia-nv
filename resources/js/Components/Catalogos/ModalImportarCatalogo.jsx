import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useForm, usePage } from '@inertiajs/react';
import { X, UploadCloud, FileSpreadsheet, CheckCircle2 } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_BTN_PRIMARY } from '@/utils/geliaTheme';
import InstruccionesImportacion from './InstruccionesImportacion';
import ImportacionResumenModal from '@/Components/Almacenes/ImportacionResumenModal';

export default function ModalImportarCatalogo({ config, onClose }) {
    const { flash } = usePage().props;
    const [dragActive, setDragActive] = useState(false);
    const [resumen, setResumen] = useState(null);
    const importForm = useForm({ archivo: null });

    useEffect(() => {
        if (flash?.reporte_importacion_almacenes) {
            setResumen(flash.reporte_importacion_almacenes);
        }
    }, [flash]);

    const descargarPlantilla = () => {
        window.location.href = route(config.rutaPlantilla);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!importForm.data.archivo) return;
        importForm.post(route(config.rutaImportar), {
            forceFormData: true,
            onSuccess: () => {
                importForm.reset();
            },
        });
    };

    const cerrarResumen = () => {
        setResumen(null);
        onClose();
    };

    if (resumen) {
        return <ImportacionResumenModal data={resumen} onClose={cerrarResumen} />;
    }

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full text-left modal-pop`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex justify-between items-center gap-3 p-5 md:p-6 border-b theme-border shrink-0">
                    <h2 className="text-lg md:text-xl font-black italic uppercase theme-text-main m-0 flex items-center gap-2 leading-tight">
                        <FileSpreadsheet className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        {config.titulo}
                    </h2>
                    <button type="button" onClick={onClose} className="p-2 hover:bg-black/5 dark:hover:bg-white/5 rounded-full shrink-0">
                        <X className="w-5 h-5 theme-text-muted" />
                    </button>
                </div>

                <div className="gelia-modal-body p-5 md:p-6 custom-scrollbar space-y-4">
                    <div className="flex justify-end">
                        <button
                            type="button"
                            onClick={descargarPlantilla}
                            className="px-4 py-2 text-[10px] font-black uppercase tracking-widest theme-element border theme-border rounded-xl hover:shadow-md flex items-center gap-2 theme-text-main"
                        >
                            <FileSpreadsheet className="w-4 h-4" />
                            Descargar plantilla
                        </button>
                    </div>

                    <form id="importar-catalogo-form" onSubmit={handleSubmit} className="space-y-4">
                        <label
                            className="border-2 border-dashed theme-border rounded-2xl p-6 flex flex-col items-center text-center cursor-pointer theme-element hover:shadow-md transition-all"
                            onDragOver={(e) => { e.preventDefault(); setDragActive(true); }}
                            onDragLeave={() => setDragActive(false)}
                            onDrop={(e) => {
                                e.preventDefault();
                                setDragActive(false);
                                if (e.dataTransfer.files?.[0]) {
                                    importForm.setData('archivo', e.dataTransfer.files[0]);
                                }
                            }}
                            style={{ borderColor: dragActive ? 'var(--color-primario)' : undefined }}
                        >
                            <UploadCloud className="w-8 h-8 theme-text-muted mb-2" />
                            <p className="text-xs font-black theme-text-main uppercase m-0">Suelta el archivo aquí</p>
                            <p className="text-[10px] theme-text-muted mt-1 m-0">Formatos: .csv, .xlsx, .xls</p>
                            <span className="text-[9px] font-black uppercase tracking-widest mt-2" style={{ color: 'var(--color-primario)' }}>
                                O examinar archivos
                            </span>
                            <input
                                type="file"
                                className="hidden"
                                accept=".csv,.xlsx,.xls,.txt"
                                onChange={(e) => importForm.setData('archivo', e.target.files[0])}
                            />
                        </label>

                        {importForm.errors.archivo && (
                            <p className="text-red-500 dark:text-red-400 text-[10px] font-bold text-center">{importForm.errors.archivo}</p>
                        )}

                        {importForm.data.archivo && (
                            <div className="flex items-center gap-3 p-3 theme-element border border-emerald-500/30 rounded-xl">
                                <CheckCircle2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                <span className="text-[10px] font-bold theme-text-main truncate">{importForm.data.archivo.name}</span>
                            </div>
                        )}
                    </form>

                    <InstruccionesImportacion columnas={config.columnas} notas={config.notas} />
                </div>

                <div className="gelia-modal-footer p-5 md:p-6">
                    <button
                        type="submit"
                        form="importar-catalogo-form"
                        disabled={importForm.processing || !importForm.data.archivo}
                        className={`${THEME_BTN_PRIMARY} w-full py-3 disabled:opacity-50`}
                    >
                        {importForm.processing ? 'Importando...' : 'Importar archivo'}
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
