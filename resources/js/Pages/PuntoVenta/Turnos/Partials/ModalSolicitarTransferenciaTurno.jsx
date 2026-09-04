import React from 'react';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';

export default function ModalSolicitarTransferenciaTurno({ abierto, onClose }) {
    return (
        <ModalConfirmarAccion
            abierto={abierto}
            titulo="Solicitar transferencia"
            mensaje="La transferencia debe autorizarla gerencia en piso y elegir la persona destino. Acude con quien tenga permiso de transferir turnos."
            etiquetaConfirmar="Entendido"
            variante="default"
            onClose={onClose}
            onConfirm={onClose}
        />
    );
}
