# Mapa — Gestión de pedidos (Logística)

- [Registro y compatibilidad](modulos/REGISTRO_PEDIDOS.md): captura comercial, propiedad y formularios actuales/legados.
- [Consultas y sincronización](modulos/CONSULTAS_DISPONIBILIDAD.md): excepciones, ajustes, cierre y lectura entre actores.
- [Envío municipal](modulos/ENVIO_MUNICIPAL.md): terminal, expediente y transporte conocido/diferido.
- [Auditoría y pagos](modulos/AUDITORIA_PAGOS.md): exhibiciones, SAF, revisión, crédito y remisión.
- [Preparación Tienda](modulos/PREPARACION_TIENDA.md): instrucción vigente, apartado documental y cumplimiento físico.
- [Transferencia y custodia](modulos/TRANSFERENCIA_CUSTODIA.md): Mismo día, sucursales y segundo traslado.
- [CEDIS y empaque](modulos/CEDIS_EMPAQUE.md): grupos, bultos y recolección parcial.
- [Delegado y guías](modulos/DELEGADO_GUIAS.md): rastreo, PDF e importación.
- [Direcciones](modulos/DIRECCIONES.md): catálogo, solicitudes y snapshot del pedido.
- [Cancelación y seguimiento](modulos/CANCELACION_SEGUIMIENTO.md): liberaciones, eliminación y tareas programadas.

- [Invariantes](INVARIANTES.md) · [Glosario](GLOSARIO.md) · [Pruebas](PRUEBAS.md) · [Estado](ESTADO.md).
- [verificado] Entrada de rutas del área: `routes/web/modules/control-pedidos/index.php:3`.
- Alcance: dependencias de PDV, Traspasos, Clientes, SAF y reportes; sus módulos completos quedan fuera de esta auditoría.
- `[verificado]`: lectura de código/documento citado; no implica prueba ejecutada. `[inferido]`: consecuencia razonada; confirmar antes de cambiar comportamiento.
- Las referencias `ruta:línea` parten de la raíz del repositorio. Las decisiones explícitas posteriores del usuario prevalecen.
