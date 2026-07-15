import BandejaSolicitudes from '../../Clientes/Direcciones/BandejaSolicitudes';

export default function BandejaSolicitudesAuxiliar(props) {
    const clienteId = props.filtros?.cliente_id;
    const backHref = clienteId
        ? route('control_pedidos.direcciones.cliente', clienteId)
        : route('control_pedidos.direcciones.index');
    const backLabel = clienteId ? 'Volver a direcciones del cliente' : 'Volver a Direcciones';

    return (
        <BandejaSolicitudes
            {...props}
            rutaBase={props.rutaBase || 'control_pedidos.direcciones.solicitudes'}
            eyebrow="Gestión Pedidos · Direcciones"
            backHref={backHref}
            backLabel={backLabel}
        />
    );
}
