import React from 'react';
import { getActivosCardClass } from './activosFormStyles';
import ListadoAlertasActivos from './ListadoAlertasActivos';

export default function PanelAlertas({ alertas = null }) {
    return (
        <div className={getActivosCardClass('p-4 md:p-6 space-y-4')}>
            <p className="text-[10px] font-black uppercase tracking-widest m-0" style={{ color: 'var(--color-primario)' }}>
                Alertas activas
            </p>
            <ListadoAlertasActivos alertas={alertas} />
        </div>
    );
}
