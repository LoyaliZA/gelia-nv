import React, { useRef } from 'react';
import { router } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';
import { BTN_PRIMARY, BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';

export default function PanelImportExport({ onAlerta }) {
    const inputRef = useRef(null);

    const exportarPlantilla = () => {
        window.location.href = route('control_pedidos.delegado.exportar');
    };

    const manejarArchivo = (e) => {
        const archivo = e.target.files?.[0];
        if (!archivo) return;

        router.post(route('control_pedidos.delegado.importar'), { archivo }, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                const mensaje = errors.archivo || 'No se pudo importar el archivo.';
                onAlerta?.({ abierto: true, tipo: 'error', titulo: 'Error de importación', mensaje });
            },
        });

        e.target.value = '';
    };

    return (
        <div className="flex flex-col sm:flex-row gap-3">
            <button
                type="button"
                onClick={exportarPlantilla}
                className={`${BTN_SECONDARY} flex items-center gap-2 outline-none`}
            >
                <Download className="w-4 h-4" /> Descargar plantilla CSV
            </button>
            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                className={`${BTN_PRIMARY} flex items-center gap-2 outline-none`}
            >
                <Upload className="w-4 h-4" /> Subir guías completadas
            </button>
            <input
                ref={inputRef}
                type="file"
                accept=".csv,.txt,.xlsx,.xls"
                className="hidden"
                onChange={manejarArchivo}
            />
        </div>
    );
}
