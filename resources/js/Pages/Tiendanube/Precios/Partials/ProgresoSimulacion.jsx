import React from 'react';

export default function ProgresoSimulacion({ simulacion }) {
    if (!simulacion || simulacion.estado === 'completada') {
        return null;
    }
    const porcentaje = simulacion.porcentaje || 0;
    const enCurso = simulacion.estado === 'en_curso' || simulacion.estado === 'pendiente';

    return (
        <div className="rounded-xl border theme-border px-3 py-2" role="status">
            <p className="text-xs theme-text-main m-0">
                {simulacion.estado === 'error'
                    ? `La simulación no terminó. ${simulacion.error || ''}`
                    : `Simulación en curso (${porcentaje}%). ${simulacion.procesados || 0} de ${simulacion.total || 0} variantes.`}
            </p>
            {enCurso && (
                <div className="mt-2 h-1.5 rounded-full bg-black/10 overflow-hidden">
                    <div className="h-full rounded-full" style={{ width: `${porcentaje}%`, backgroundColor: 'var(--color-primario)' }} />
                </div>
            )}
        </div>
    );
}
