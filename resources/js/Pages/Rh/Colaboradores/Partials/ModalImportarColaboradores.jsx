import React, { useMemo, useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useForm, usePage } from '@inertiajs/react';
import { X, UploadCloud, FileSpreadsheet, CheckCircle2, Database } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_BTN_PRIMARY } from '@/utils/geliaTheme';
import ImportacionResumenModal from '@/Components/Almacenes/ImportacionResumenModal';

const COLUMNAS = [
    { key: 'nombre', label: 'nombre', requerido: true },
    { key: 'apellido_paterno', label: 'apellido_paterno', requerido: false },
    { key: 'apellido_materno', label: 'apellido_materno', requerido: false },
    { key: 'departamento', label: 'departamento', requerido: true, nota: 'Nombre exacto del catálogo' },
    { key: 'area', label: 'area', requerido: false, nota: 'Debe pertenecer al departamento' },
    { key: 'puesto', label: 'puesto', requerido: true, nota: 'Nombre exacto del catálogo' },
    { key: 'turno', label: 'turno', requerido: true, nota: 'Nombre exacto del catálogo' },
    { key: 'salario_diario', label: 'salario_diario', requerido: true, nota: 'Monto diario (se convierte al periodo)' },
    { key: 'bono_productividad', label: 'bono_productividad', requerido: false, nota: 'Diario; default 0' },
    { key: 'bono_puntualidad', label: 'bono_puntualidad', requerido: false, nota: 'Diario; default 0' },
    { key: 'horas_laboradas_oficiales', label: 'horas_laboradas_oficiales', requerido: true, nota: '0.5 a 24' },
    { key: 'activo', label: 'activo', requerido: false, nota: '1/0 o si/no; default activo' },
];

function ListaCatalogo({ titulo, items, vacio }) {
    return (
        <div>
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1.5 m-0">{titulo}</p>
            {items.length === 0 ? (
                <p className="text-[10px] font-bold text-amber-600 dark:text-amber-400 m-0">{vacio}</p>
            ) : (
                <ul className="text-[10px] theme-text-muted font-bold leading-relaxed space-y-0.5 m-0 p-0 max-h-28 overflow-y-auto custom-scrollbar">
                    {items.map((item) => (
                        <li key={item.key} className="theme-text-main">
                            {item.label}
                            {item.extra ? (
                                <span className="theme-text-muted font-normal"> — {item.extra}</span>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function ModalImportarColaboradores({
    onClose,
    departamentos = [],
    puestos = [],
    turnos = [],
}) {
    const { flash } = usePage().props;
    const [dragActive, setDragActive] = useState(false);
    const [resumen, setResumen] = useState(null);
    const importForm = useForm({ archivo: null });

    useEffect(() => {
        if (flash?.reporte_importacion_colaboradores) {
            setResumen(flash.reporte_importacion_colaboradores);
        }
    }, [flash]);

    const catalogosUi = useMemo(() => {
        const deptoItems = departamentos.map((d) => ({
            key: `depto-${d.id}`,
            label: d.nombre,
        }));

        const areaItems = [];
        departamentos.forEach((d) => {
            (d.areas || []).forEach((a) => {
                areaItems.push({
                    key: `area-${a.id}`,
                    label: a.nombre,
                    extra: d.nombre,
                });
            });
        });

        const puestoItems = puestos.map((p) => ({
            key: `puesto-${p.id}`,
            label: p.nombre,
        }));

        const turnoItems = turnos.map((t) => ({
            key: `turno-${t.id}`,
            label: t.nombre,
        }));

        return { deptoItems, areaItems, puestoItems, turnoItems };
    }, [departamentos, puestos, turnos]);

    const descargarPlantilla = () => {
        window.location.href = route('rh.colaboradores.plantilla_importacion');
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!importForm.data.archivo) return;
        importForm.post(route('rh.colaboradores.importar'), {
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
                className={`${THEME_MODAL_SHELL} max-w-xl w-full text-left modal-pop`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex justify-between items-center gap-3 p-5 md:p-6 border-b theme-border shrink-0">
                    <h2 className="text-lg md:text-xl font-black italic uppercase theme-text-main m-0 flex items-center gap-2 leading-tight">
                        <FileSpreadsheet className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        Importar colaboradores
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

                    <form id="importar-colaboradores-form" onSubmit={handleSubmit} className="space-y-4">
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

                    <div className="mt-2 pt-4 border-t theme-border space-y-4">
                        <div className="flex items-center gap-2 text-amber-500 dark:text-amber-400">
                            <Database className="w-4 h-4" />
                            <p className="text-[9px] font-black uppercase tracking-widest italic m-0">Cómo importar_</p>
                        </div>
                        <ol className="text-[10px] theme-text-muted font-bold leading-relaxed space-y-1 list-decimal list-inside m-0 p-0">
                            <li>Descarga la plantilla (hoja Datos + hoja Catalogos con opciones vigentes).</li>
                            <li>Llena la hoja Datos sin renombrar las columnas.</li>
                            <li>Usa exactamente los nombres de departamento, área, puesto y turno de la hoja Catalogos.</li>
                            <li>Sube el archivo: se crearán solo filas válidas; las omitidas aparecen en el resumen.</li>
                        </ol>

                        <div>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2">Columnas soportadas</p>
                            <ul className="text-[10px] theme-text-muted font-bold leading-relaxed space-y-1 m-0 p-0">
                                {COLUMNAS.map((col) => (
                                    <li key={col.key}>
                                        <strong className="theme-text-main" style={{ color: 'var(--color-primario)' }}>{col.label}</strong>
                                        {col.requerido ? ' (Requerido)' : ' (Opcional)'}
                                        {col.nota ? ` — ${col.nota}` : ''}
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="space-y-3 p-3 rounded-xl theme-element border theme-border">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-main m-0">
                                Catálogos disponibles (actuales)
                            </p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <ListaCatalogo
                                    titulo="Departamentos"
                                    items={catalogosUi.deptoItems}
                                    vacio="No hay departamentos activos"
                                />
                                <ListaCatalogo
                                    titulo="Áreas"
                                    items={catalogosUi.areaItems}
                                    vacio="No hay áreas registradas"
                                />
                                <ListaCatalogo
                                    titulo="Puestos"
                                    items={catalogosUi.puestoItems}
                                    vacio="No hay puestos activos"
                                />
                                <ListaCatalogo
                                    titulo="Turnos"
                                    items={catalogosUi.turnoItems}
                                    vacio="No hay turnos activos"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="gelia-modal-footer p-5 md:p-6">
                    <button
                        type="submit"
                        form="importar-colaboradores-form"
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
