# Invariantes — Gestión de pedidos

- Aplicar propiedad, alcance de lectura y concurrencia comercial de [Registro](modulos/REGISTRO_PEDIDOS.md#reglas-que-no-se-rompen).
- Mantener ciclos, evidencia, cierre y deduplicación de [Consultas](modulos/CONSULTAS_DISPONIBILIDAD.md#reglas-que-no-se-rompen).
- Conservar terminal, expediente, costo desconocido y barrera comercial de [Municipal](modulos/ENVIO_MUNICIPAL.md#reglas-que-no-se-rompen).
- Mantener cobertura canónica, idempotencia y separación pago/crédito/remisión de [Auditoría](modulos/AUDITORIA_PAGOS.md#reglas-que-no-se-rompen).
- Ejecutar solo la instrucción vigente y respetar inventario/documentación de [Preparación](modulos/PREPARACION_TIENDA.md#reglas-que-no-se-rompen).
- Conservar reporte MD, fecha de remisión y autorización/salida/recepción de [Custodia](modulos/TRANSFERENCIA_CUSTODIA.md#reglas-que-no-se-rompen).
- Mantener bloqueos, excepciones de resguardos y recolección parcial de [CEDIS](modulos/CEDIS_EMPAQUE.md#reglas-que-no-se-rompen).
- Respetar etapa, resguardo y guía del cliente de [Delegado](modulos/DELEGADO_GUIAS.md#reglas-que-no-se-rompen).
- Preservar snapshots, permisos e invalidación de [Direcciones](modulos/DIRECCIONES.md#reglas-que-no-se-rompen).
- Separar resolución física/financiera y vencimientos de [Cancelación](modulos/CANCELACION_SEGUIMIENTO.md#reglas-que-no-se-rompen).
- [verificado] No mostrar códigos de fichas internas en acciones de usuario: `AGENTS.md:47`.
- [verificado] Conservar tema, fuente, acento y evidencia visual GELIA; una captura aislada no acredita integración: `DESIGN.md`; [guía de evidencia](../../GUIA_EVIDENCIA_VISUAL.md).
