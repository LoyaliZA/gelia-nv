# Auditoría y pagos

- Propósito: verificar ingreso y documentos sin confundir cobertura, crédito, remisión ni salida.
- Ubicación: `routes/web/modules/control-pedidos/auditar.php`; `app/Http/Controllers/ControlPedidos/{PedidoBmaAuditoriaController,PedidoBmaSaldosPagosController}.php`.
- Servicios: `app/Services/ControlPedidos/{ValidarPagoPedidoBmaService,GestionarRemisionPedidoBmaService}.php`; `app/Services/SaldosAFavor/`.
- Frontend: `resources/js/Pages/ControlPedidos/Auditar/` (`ModalRevisarPedido`, `SelectorEnviosAuditoria`) y `Partials/{ImportesPagoTienda,SeccionPagosExhibicion}.jsx`.
- Pruebas: `tests/Feature/ControlPedidos/{ImportesPagoTiendaTest,RemisionAuditoriaTest,Q8AccesoPagosSafEvidenciaTest,MultienvioNacionalBandejasTest}.php`; `tests/Feature/SaldosAFavor/`; `SelectorEnviosAuditoria.test.jsx`.

## Estado actual
- [verificado] Registrar exhibición inicia revisión pendiente; no verifica ingreso: `app/Services/SaldosAFavor/RegistrarPagoPedidoBmaService.php:143`.
- [verificado] Cobertura canónica calcula en centavos mercancía, cargos aplicables, SAF y pagos activos; aplica tolerancia: `app/Services/SaldosAFavor/CoberturaPagoPedidoBmaService.php:124`.
- [verificado] Validar pago crea cierre de reporte y resuelve su campo de la cola de errores: `app/Services/ControlPedidos/ValidarPagoPedidoBmaService.php:96`.
- [verificado] M-10 / flete diferido: `assertPagoListoParaAvanzar` y `CoberturaPagoPedidoBmaService::bloqueos` exigen mercancía cubierta y conservan SAF, comprobantes, rechazos y FASE_APROBAR; el pendiente de transporte no se marca cubierto. Con resguardo activo, enviar a auditoría también es solo mercancía (M-09 se valida después): `RegistrarPagoPedidoBmaService.php`.

- [verificado] Nacional 2.0 con PAQ. PENDIENTE permite verificar y remisionar solo mercancía. Al elegir servicio y cotizar, se conservan la remisión y las exhibiciones verificadas. Auxiliar revisa la cobertura de los cargos pendientes. Guía y despacho requieren decisión y cobertura: `CoberturaEnvioNacional` y `CambiarPaqueteriaNacional`.
- [verificado] `detalle()` adjunta `multienvio` con `ResumenGrupoMultienvio::de`. El modal de un miembro es el espacio de trabajo del grupo: selector de envíos, `GET auditar.detalle` al cambiar, aprobación/rechazo/reporte independientes; el grupo no se cierra al aprobar un envío. El pedido ordinario conserva el cierre al aprobar: `ListarPedidosAuditoriaService.php`, `ModalRevisarPedido.jsx`.

## Reglas que no se rompen
- [verificado] Reintento de pago recupera exhibición por clave antes de comprobar versión financiera: `app/Services/SaldosAFavor/RegistrarPagoPedidoBmaService.php:111`.
- [verificado] Mantener índice único por pedido/clave: `database/migrations/2026_10_15_210100_add_idempotency_key_to_pedido_bma_pagos.php:11`.
- [verificado] Versión financiera comprueba revisión e importes confirmados; no reutilizar huella de fila completa: `app/Services/SaldosAFavor/ComprobarVersionFinancieraPago.php:8`.
- [verificado] Caja PDV se resuelve en servidor; preservar forma real y banco permitido: `app/Services/SaldosAFavor/RegistrarPagoPedidoBmaService.php:55`.
- [verificado] Crédito requiere resultado aprobado de Wizerp; tiempo/transferencia no lo sustituyen: `app/Services/ControlPedidos/ValidarPagoPedidoBmaService.php:57`.
- [verificado] Cambiar modalidad guardada exige solicitud y otra persona con permiso específico; no autoautorizar: `app/Services/ControlPedidos/CambioCoberturaTiendaService.php:48`.
- [verificado] Decisión explícita del usuario (2026-11-18): `control_pedidos.tienda.autorizar_cambio_cobertura` corresponde a la gerente y a las auxiliares que se designen. Asignación efectiva por confirmar.
- [verificado] Autorizar compara expediente vigente y bloqueos antes de aplicar; no elimina pagos ni hechos físicos: `app/Services/ControlPedidos/RegistrarCoberturaTiendaService.php:49`.
- [verificado] Número de remisión y PDF son registros separados; sustituir PDF conserva referencia anterior: `app/Services/ControlPedidos/GestionarRemisionPedidoBmaService.php:43` y `:91`.
- Pago al recoger: [Preparación](PREPARACION_TIENDA.md). Fecha de remisión: [Custodia](TRANSFERENCIA_CUSTODIA.md).

## Dependencias con otros módulos
- SAF reserva/conciliación, reportes financieros y bancos por departamento; [Municipal](ENVIO_MUNICIPAL.md) agrega su barrera comercial.
- [verificado] Auditoría revisa exhibiciones y rechaza pagos mediante permiso propio: `routes/web/modules/control-pedidos/auditar.php:7`.

## Cómo probar
- [PRUEBAS](../PRUEBAS.md): importes, referencia duplicada, reintento, sustitución, crédito, cierre/remisión con consulta abierta; `ResguardoMunicipalOla1Test` y `ResguardoMunicipalOla2Test` para mercancía vs flete, M-09 y crédito que no habilita resguardo.

## Puntos blandos
- [verificado] `pagos_validos` del resumen no debe leerse como verificación bancaria; toma pagos activos para cobertura: `app/Services/SaldosAFavor/CoberturaPagoPedidoBmaService.php:46`.
- [verificado] Excedentes desde $10 tienen gestión SAF; menores no se automatizan: `gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md:82`.
- [inferido] Modificar la fórmula solo en React produciría una proyección incompatible con el bloqueo servidor; confirmar con resumen persistido: `app/Services/SaldosAFavor/CoberturaPagoPedidoBmaService.php:78`.
- [verificado] «Cubrir pendiente» al editar suma el monto ya contado de esa exhibición (mercancía + envío + seguro). Si el formulario no trae flete, la proyección toma `totales_envio` o `total_a_cubrir` del resumen; el servidor no cambia: `persistenciaFormularioPedido.js`, `SeccionPagosExhibicion.jsx`.

- Última verificación: 2026-11-27, árbol de trabajo (auditoría de multienvío en el mismo modal).
