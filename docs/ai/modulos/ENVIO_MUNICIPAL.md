# Envío municipal

- Propósito: registrar terminal, consignatario y condiciones del transporte; consultar mercancía antes del avance comercial.
- Ubicación: `routes/web/modules/control-pedidos/core.php`; `app/Services/ControlPedidos/Municipal/`; `app/Support/ControlPedidos/{ConsultaMunicipal,AlcanceConsultaMunicipal}.php`.
- Frontend: `resources/js/Pages/ControlPedidos/Municipal/`; pruebas: `tests/Feature/ControlPedidos/MunicipalOrdinarioM01Test.php` y tests junto al frontend.
- Esquema: `database/migrations/2026_11_14_120000_create_cliente_expediente_tables.php`; solicitud persistida en JSON del pedido.

## Estado actual
- [verificado] Regla definitiva de terminal: activa + categoría Local/Regional; Sin logística no la determina: `app/Models/ControlPedidos/CatalogoPaqueteriaPedido.php:81`.
- [verificado] La referencia destino es `catalogo_paqueteria_id`; se conserva `municipio_destino` histórico sin usarlo como terminal: `gestion-pedidos-docs/M01_CONTINUIDAD_REGISTRO_Y_CONSULTA_CODEX.md:72`.
- [verificado] Enviar consulta guarda automáticamente la captura común y municipal antes de enviarla: `resources/js/Pages/ControlPedidos/Municipal/FormularioRegistrarMunicipal.jsx:104`.
- [verificado] Primer envío requiere cliente, departamento, folio Wizerp, soporte activo y consignatario vigente: `app/Services/ControlPedidos/Municipal/EnviarConsultaMunicipalService.php:55`.

## Reglas que no se rompen
- [verificado] Costo diferido permanece nulo; PAGADO/POR_COBRAR es condición de terminal, no verificación de ingreso: `app/Services/ControlPedidos/Municipal/RegistrarSolicitudMunicipalService.php:46` y `:88`.
- [verificado] Costo conocido debe ser positivo; diferido y POR_COBRAR dependen de facultades del catálogo: `app/Services/ControlPedidos/Municipal/RegistrarSolicitudMunicipalService.php:50`.
- [verificado] Respuesta no autoriza pago/remisión: exigir cierre municipal vigente y sin bloqueo: `app/Services/ControlPedidos/Municipal/PoliticaFinancieraMunicipal.php:20`.
- [verificado] No refrescar silenciosamente el snapshot si se guarda el mismo consignatario: `app/Services/ControlPedidos/Municipal/RegistrarSolicitudMunicipalService.php:105`.
- [verificado] Tienda responde con permiso municipal y sucursal operable; CEDIS mantiene departamentos: `app/Support/ControlPedidos/AlcanceConsultaMunicipal.php:20`.

## Dependencias con otros módulos
- [Consultas](CONSULTAS_DISPONIBILIDAD.md), [Auditoría](AUDITORIA_PAGOS.md); expediente privado de Clientes.
- [verificado] Almacén PDV dirige consulta a Tienda; otro destino a CEDIS. Esto no crea traslado: `app/Services/ControlPedidos/Municipal/EnviarConsultaMunicipalService.php:43`.
- [verificado] Los reportes deben distinguir transporte desconocido de gratuito/cubierto: `gestion-pedidos-docs/M01_AUDITORIA_CODEX_DETALLADA.md:65`.

## Cómo probar
- [PRUEBAS](../PRUEBAS.md): `MunicipalOrdinarioM01Test`, terminales, expediente histórico, reapertura, diferido y bloqueo comercial.

## Puntos blandos
- [verificado] El refinamiento que exigía Sin logística está sustituido por Local/Regional; no restaurarlo: `gestion-pedidos-docs/M01_CONTINUIDAD_REGISTRO_Y_CONSULTA_CODEX.md:66`.
- [verificado] Fases `PESAJE_*` también transportan consulta municipal; no exigir pesaje nacional por su nombre: `app/Services/ControlPedidos/Municipal/EnviarConsultaMunicipalService.php:78`.
- [verificado] Carátula definitiva, logística municipal completa y regularización P-01 no quedan acreditadas por el cierre actual: `gestion-pedidos-docs/M01_REFINAMIENTO_CAPTURA_MUNICIPAL_CODEX.md:137`.

- Última verificación: 2026-11-18, `develop` @ `c63dd84722c4bf6a318d233177d560aaa2b44f3d`; lectura estática.
