import ReportePdvShell from './ReportePdvShell';
import { TIPO_REPORTE_TURNOS_OPERACION } from './reportesPdvUtils';

export default function TurnosOperacion(props) {
    return <ReportePdvShell {...props} tipo_reporte={props.tipo_reporte || TIPO_REPORTE_TURNOS_OPERACION} />;
}
