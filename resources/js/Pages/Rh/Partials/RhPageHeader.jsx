import React from 'react';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import ModalAyudaFactores from './ModalAyudaFactores';

/**
 * Encabezado estándar del módulo RH (tipografía GeliaTituloCard / Perfil).
 */
export default function RhPageHeader({
    title,
    titleHighlight,
    description,
    icon,
    aside,
    showGuia = true,
    className = '',
}) {
    const asideContent = (
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 w-full md:w-auto">
            {aside}
            {showGuia && <ModalAyudaFactores variant="outline" />}
        </div>
    );

    return (
        <GeliaTituloCard
            eyebrow="Módulo RH_"
            title={title}
            titleHighlight={titleHighlight}
            description={description}
            icon={icon}
            aside={showGuia || aside ? asideContent : null}
            className={className}
        />
    );
}
