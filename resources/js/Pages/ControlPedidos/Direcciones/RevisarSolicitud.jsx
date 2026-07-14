import RevisarSolicitud from '../../Clientes/Direcciones/RevisarSolicitud';

export default function RevisarSolicitudAuxiliar(props) {
    return (
        <RevisarSolicitud
            {...props}
            rutaBase={props.rutaBase || 'control_pedidos.direcciones.solicitudes'}
        />
    );
}
