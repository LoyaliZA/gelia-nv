# Envío municipal

- Propósito: registrar terminal, consignatario y condiciones del transporte; consultar mercancía antes del avance comercial.
- Ubicación: `routes/web/modules/control-pedidos/core.php`; `app/Services/ControlPedidos/Municipal/`; `app/Support/ControlPedidos/{ConsultaMunicipal,AlcanceConsultaMunicipal}.php`; conversión M-02 en `app/Support/ControlPedidos/Conversion/` y `app/Services/ControlPedidos/Conversion/`.
- Frontend: `resources/js/Pages/ControlPedidos/Municipal/`, `Partials/ModalFormPedido.jsx` en `intencion=conversion`; pruebas: `MunicipalOrdinarioM01Test`, `ConversionMotorATest`, `ConversionModalidadM02Test`, `ResguardoMunicipalOla1Test`, `ResguardoMunicipalOla2Test`.
- Esquema: `database/migrations/2026_11_14_120000_create_cliente_expediente_tables.php`; solicitud persistida en JSON del pedido.

## Estado actual
- [verificado] Regla definitiva de terminal: activa + categoría Local/Regional; Sin logística no la determina: `app/Models/ControlPedidos/CatalogoPaqueteriaPedido.php:81`.
- [verificado] La referencia destino es `catalogo_paqueteria_id`; se conserva `municipio_destino` histórico sin usarlo como terminal: `gestion-pedidos-docs/M01_CONTINUIDAD_REGISTRO_Y_CONSULTA_CODEX.md:72`.
- [verificado] Enviar consulta guarda automáticamente la captura común y municipal antes de enviarla: `resources/js/Pages/ControlPedidos/Municipal/FormularioRegistrarMunicipal.jsx:104`.
- [verificado] Primer envío requiere cliente, departamento, folio Wizerp, soporte activo y consignatario vigente: `app/Services/ControlPedidos/Municipal/EnviarConsultaMunicipalService.php:55`.
- [verificado] Resguardo de envío: intención temprana en `resguardo_envio` (solicitud guardada, consulta puede seguir abierta). Exhibiciones tras cierre. Enviar a auditoría con interruptor on exige mercancía cubierta. M-04 vincula remisiones pagadas (`custodia_local` / `custodia_cedis` / `resguardo_envio`) sin encender el resguardo. Integración local no usa `pendiente_traslado`.

## Reglas que no se rompen
- [verificado] Costo diferido permanece nulo; PAGADO/POR_COBRAR es condición de terminal, no verificación de ingreso. Captura validada en `ValidarCapturaMunicipal`; el alta M-01 sigue bloqueando pagado/remisionado. GET de conversión hidrata el overlay desde `finanzas_municipales` + cargo nuevo impago; diferido sigue nulo y `transporte_desconocido`, no cero cubierto.
- [verificado] Respuesta no autoriza pago/remisión: exigir cierre municipal vigente y sin bloqueo. Conversión Tienda→Municipal no relaja esa barrera: consulta heredada abierta bloquea exhibiciones nuevas hasta cerrar con `modalidad_esperada=municipal` y `version_consulta`; el cargo histórico no cubre el transporte: `PoliticaFinancieraMunicipal.php`; caso 10 de `ConversionModalidadM02Test`.
- [verificado] No refrescar silenciosamente el snapshot si se guarda el mismo consignatario: `ValidarCapturaMunicipal::expediente`.
- [verificado] M-02 P0: Tienda→Municipal sobre el mismo pedido solo antes del primer cierre (`ConsultaPedidoYaCerradaAlgunaVez`). Reserva por `instancia_id` con `Cache::add`. Cerrar consulta exige contexto; conversión confirmada sin `modalidad_esperada=municipal` → 409. Heredada+`enviada_at` no hace unset de `revision_disponibilidad`.
- [verificado] Tienda responde con permiso municipal y sucursal operable; CEDIS mantiene departamentos: `app/Support/ControlPedidos/AlcanceConsultaMunicipal.php:20`.
- [verificado] Resguardo municipal: la intención no exige pago; enviar a auditoría sí (mensaje canónico). M-09 exige mercancía y transporte auditados más fecha; M-10 acumula con flete pendiente/diferido y cajas N/A (`numero_cajas` nulo). Liberación auxiliar exige continuación y conjunto listo; conserva pivote/`pedido_principal_id`/cobros. Crédito Wizerp no habilita el resguardo. `puede_reconducir` es falso tras despacho/enviado. `es_resguardo` no se limpia si hay otra retención: `PoliticaResguardoEnvio.php`.

## Dependencias con otros módulos
- [Consultas](CONSULTAS_DISPONIBILIDAD.md), [Auditoría](AUDITORIA_PAGOS.md); expediente privado de Clientes.
- [verificado] Almacén PDV dirige consulta a Tienda; otro destino a CEDIS. Esto no crea traslado: `app/Services/ControlPedidos/Municipal/EnviarConsultaMunicipalService.php:43`.
- [verificado] Los reportes deben distinguir transporte desconocido de gratuito/cubierto: `gestion-pedidos-docs/M01_AUDITORIA_CODEX_DETALLADA.md:65`.

## Cómo probar
- [PRUEBAS](../PRUEBAS.md): `MunicipalOrdinarioM01Test`, `ConversionMotorATest`, `ConversionModalidadM02Test`, `ResguardoMunicipalOla1Test`, `ResguardoMunicipalOla2Test`; terminales, expediente, diferido, conversión P0, resguardo M-04/M-09/M-10.

## Puntos blandos
- [verificado] El refinamiento que exigía Sin logística está sustituido por Local/Regional; no restaurarlo: `gestion-pedidos-docs/M01_CONTINUIDAD_REGISTRO_Y_CONSULTA_CODEX.md:66`.
- [verificado] Fases `PESAJE_*` también transportan consulta municipal; no exigir pesaje nacional por su nombre: `app/Services/ControlPedidos/Municipal/EnviarConsultaMunicipalService.php:78`.
- [verificado] Carátula definitiva, logística municipal completa y regularización P-01 no quedan acreditadas por el cierre actual: `gestion-pedidos-docs/M01_REFINAMIENTO_CAPTURA_MUNICIPAL_CODEX.md:137`.

- [verificado] M-02 abre el modal original de registro en modo conversión; no hay wizard de pasos. La reserva vive en `ModalFormPedido` y confirma por `/conversion-modalidad`.

- Última verificación: 2026-11-19, working tree resguardo municipal M-04 a M-10.
