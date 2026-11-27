import React from 'react';
import { PanelLeft } from 'lucide-react';
import SidebarLayoutPicker from '../../Pages/Profile/Partials/SidebarLayoutPicker';
import { AjustesBloque } from './AjustesPrimitivas';

export default function AjustesNavegacion({ ajustes }) {
    const { estado, acciones } = ajustes;
    return (
        <div className="gelia-ajustes__panel">
            <AjustesBloque icon={PanelLeft} title="Navegación_" hint="Estilo del menú lateral y su comportamiento">
                <SidebarLayoutPicker
                    value={estado.layout}
                    onChange={acciones.cambiarLayout}
                    mobileLayout={estado.mobileLayout}
                    onMobileChange={acciones.cambiarLayoutMovil}
                    sidebarMode={estado.sidebarMode}
                    onSidebarModeChange={acciones.cambiarModoSidebar}
                    fixedPosition={estado.fixedPosition}
                    onFixedPositionChange={acciones.cambiarPosicionFija}
                />
            </AjustesBloque>
        </div>
    );
}
