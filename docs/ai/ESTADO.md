# Estado — Gestión de pedidos

- Última verificación: 2026-11-18, `develop` @ `c63dd847` más cambios locales de etapa 3; pruebas dirigidas, sin commit.
- [verificado] Etapa 3 extrae editor y núcleo; Municipal queda montado y Nacional en contrato. Etapas 4–6 siguen pendientes: `gestion-pedidos-docs/CONSULTAS_COMPARTIDAS_IMPLEMENTACION_Y_AUTOEVALUACION.md`.
- Entrada para agentes: [MAPA](MAPA.md); reglas exigibles: [INVARIANTES](INVARIANTES.md); aceptación y limitaciones: [PRUEBAS](PRUEBAS.md).

## Decisiones vigentes
- Tienda documental/Pago al recoger (T-01): [Preparación](modulos/PREPARACION_TIENDA.md).
- Transferencia/custodia (T-02), Mismo día (T-02.1), sucursal/City Center (T-03): [Transferencia](modulos/TRANSFERENCIA_CUSTODIA.md).
- Municipal ordinario (M-01), terminal y transporte: [Municipal](modulos/ENVIO_MUNICIPAL.md).
- Responsables del permiso de cambio de cobertura: [Auditoría y pagos](modulos/AUDITORIA_PAGOS.md#reglas-que-no-se-rompen).
- Fuente extensa de Tienda: [decisiones](../../gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md), con [configuración operativa](../../gestion-pedidos-docs/CONFIGURACION_OPERATIVA_TIENDA.md) y [actualizaciones de consulta](../../gestion-pedidos-docs/DISPONIBILIDAD_ACCIONES_RAPIDAS.md).
- Fuente municipal vigente: [continuidad](../../gestion-pedidos-docs/M01_CONTINUIDAD_REGISTRO_Y_CONSULTA_CODEX.md); sincronización y alcance: [corte](../../gestion-pedidos-docs/M01_CORTE_ETAPAS_1_2.md).
- Requisitos conceptuales: [análisis V02.1](../../gestion-pedidos-docs/Analisis_de_Requerimientos_Gestion_de_pedidos_V02.1_Dudas_resueltas.md); no prueban implementación ni sustituyen decisiones posteriores.

## Pendientes
- Municipal con recorrido físico CEDIS→PDV, carátula y salida a terminal implementado (sin recorrido real ni MySQL); pendientes en [Municipal](modulos/ENVIO_MUNICIPAL.md#recorrido-físico-compartido-2026-11-21). Nacional sigue fuera: [Consultas](modulos/CONSULTAS_DISPONIBILIDAD.md), [Transferencia](modulos/TRANSFERENCIA_CUSTODIA.md) y [Municipal](modulos/ENVIO_MUNICIPAL.md).
- [verificado] Regularización de transporte diferido (P-01) queda fuera del alcance acreditado: `gestion-pedidos-docs/M01_AUDITORIA_CODEX_DETALLADA.md:146`.
- Por confirmar en despliegue: flags, catálogos, permisos efectivos, scheduler, workers y disponibilidad de evidencias; no se consultaron datos operativos ni secretos.
- La fecha de esta auditoría acredita lectura de fuentes, no ejecución de las pruebas descritas en informes anteriores.
