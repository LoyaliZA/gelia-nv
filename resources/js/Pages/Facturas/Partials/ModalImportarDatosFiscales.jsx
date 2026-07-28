import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useForm, usePage } from '@inertiajs/react';
import { X, UploadCloud, FileSpreadsheet, CheckCircle2, AlertTriangle, FileWarning } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';

const CONFIG = {
    clientes: {
        titulo: 'Importar datos fiscales de clientes',
        plantillaRoute: 'facturas.datos_fiscales.plantilla_clientes',
        importarRoute: 'facturas.datos_fiscales.importar_clientes',
        flashKey: 'reporte_importacion_datos_fiscales',
        primeraColumna: 'NUMERO CLIENTE',
        hint: 'La hoja Datos debe traer NUMERO CLIENTE para localizar al cliente existente.',
    },
    receptores: {
        titulo: 'Importar receptores fiscales',
        plantillaRoute: 'facturas.datos_fiscales.plantilla_receptores',
        importarRoute: 'facturas.datos_fiscales.importar_receptores',
        flashKey: 'reporte_importacion_receptores',
        primeraColumna: 'NOMBRE (RAZON SOCIAL)',
        hint: 'No lleva código interno: se genera automáticamente al crear cada receptor.',
    },
};

function Resumen({ stats }) {
    const creados = stats.creados ?? null;
    const actualizados = stats.actualizados ?? 0;
    const omitidos = stats.omitidos ?? 0;
    const errores = stats.errores ?? [];

    return (
        <div className="space-y-4">
            <div className={`grid ${creados !== null ? 'grid-cols-3' : 'grid-cols-2'} gap-3`}>
                {creados !== null && (
                    <div className="theme-element border theme-border rounded-xl p-3 text-center">
                        <p className="text-2xl font-black theme-text-main m-0">{creados}</p>
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mt-1">Creados</p>
                    </div>
                )}
                <div className="theme-element border theme-border rounded-xl p-3 text-center">
                    <p className="text-2xl font-black theme-text-main m-0">{actualizados}</p>
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mt-1">Actualizados</p>
                </div>
                <div className="theme-element border theme-border rounded-xl p-3 text-center">
                    <p className={`text-2xl font-black m-0 ${omitidos > 0 ? 'text-amber-600 dark:text-amber-400' : 'theme-text-main'}`}>{omitidos}</p>
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mt-1">Omitidos</p>
                </div>
            </div>

            {errores.length > 0 && (
                <div className="space-y-2">
                    <div className="flex items-center gap-2 text-amber-500 dark:text-amber-400">
                        <AlertTriangle className="w-4 h-4 shrink-0" />
                        <p className="text-[9px] font-black uppercase tracking-widest m-0">
                            {errores.length} fila(s) con errores
                        </p>
                    </div>
                    <ul className="max-h-40 overflow-y-auto space-y-1 m-0 p-0 list-none">
                        {errores.slice(0, 20).map((msg, i) => (
                            <li key={i} className="flex items-start gap-2 text-[10px] theme-text-muted font-bold px-2 py-1.5 theme-element rounded-lg border theme-border">
                                <FileWarning className="w-3.5 h-3.5 shrink-0 mt-0.5 text-amber-500" />
                                {msg}
                            </li>
                        ))}
                        {errores.length > 20 && (
                            <li className="text-[10px] theme-text-muted italic px-2">
                                … y {errores.length - 20} errores más.
                            </li>
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}

export default function ModalImportarDatosFiscales({ tipo = 'clientes', onClose }) {
    const cfg = CONFIG[tipo] || CONFIG.clientes;
    const { flash } = usePage().props;
    const [dragActive, setDragActive] = useState(false);
    const [resumen, setResumen] = useState(null);
    const importForm = useForm({ archivo: null });

    useEffect(() => {
        if (flash?.[cfg.flashKey]) {
            setResumen(flash[cfg.flashKey]);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash]);

    const descargarPlantilla = () => {
        window.location.href = route(cfg.plantillaRoute);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!importForm.data.archivo) return;
        importForm.post(route(cfg.importarRoute), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                importForm.reset();
            },
        });
    };

    if (resumen) {
        return createPortal(
            <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
                <div
                    className={`${THEME_MODAL_SHELL} max-w-lg w-full max-h-[90vh] overflow-y-auto text-left modal-pop`}
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="p-6 border-b theme-border flex justify-between items-center sticky top-0 theme-surface z-10">
                        <h2 className="text-xl font-black italic uppercase theme-text-main m-0 flex items-center gap-2">
                            <CheckCircle2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                            Resumen de importación
                        </h2>
                        <button type="button" onClick={onClose} className="p-2 hover:bg-black/5 dark:hover:bg-white/5 rounded-full">
                            <X className="w-5 h-5 theme-text-muted" />
                        </button>
                    </div>
                    <div className="p-6 space-y-4">
                        <Resumen stats={resumen} />
                        <button
                            type="button"
                            onClick={onClose}
                            className="w-full py-3 text-[11px] font-black uppercase rounded-xl theme-element border theme-border theme-text-main"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>,
            document.body
        );
    }

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-xl w-full text-left modal-pop`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex justify-between items-center gap-3 p-5 md:p-6 border-b theme-border shrink-0">
                    <h2 className="text-lg md:text-xl font-black italic uppercase theme-text-main m-0 flex items-center gap-2 leading-tight">
                        <FileSpreadsheet className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        {cfg.titulo}
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

                    <form id={`importar-datos-fiscales-${tipo}-form`} onSubmit={handleSubmit} className="space-y-4">
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
                                accept=".csv,.xlsx,.xls"
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

                    <div className="mt-2 pt-4 border-t theme-border space-y-3">
                        <div className="flex items-center gap-2 text-amber-500 dark:text-amber-400">
                            <FileSpreadsheet className="w-4 h-4" />
                            <p className="text-[9px] font-black uppercase tracking-widest italic m-0">Cómo importar_</p>
                        </div>
                        <ol className="text-[10px] theme-text-muted font-bold leading-relaxed space-y-1 list-decimal list-inside m-0 p-0">
                            <li>Descarga la plantilla (hoja Datos + hoja Catalogos con los códigos vigentes).</li>
                            <li>Llena la hoja Datos sin renombrar las columnas, empezando por <strong className="theme-text-main">{cfg.primeraColumna}</strong>.</li>
                            <li>Régimen fiscal y Uso de factura aceptan el código SAT o el nombre exacto de la hoja Catalogos.</li>
                            <li>{cfg.hint}</li>
                            <li>Sube el archivo: se procesan solo filas válidas; las omitidas aparecen en el resumen.</li>
                        </ol>
                    </div>
                </div>

                <div className="gelia-modal-footer p-5 md:p-6">
                    <button
                        type="submit"
                        form={`importar-datos-fiscales-${tipo}-form`}
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
