# Guía para agentes de GELIA

GELIA es una app interna Laravel + React de operación comercial, logística y administrativa. Las decisiones explícitas más recientes del usuario prevalecen sobre cualquier documento.

## Entorno

- Ejecutar PHP, Artisan, Composer, npm y pruebas con `./vendor/bin/sail`. Si Sail falla, diagnosticar el entorno antes de usar el host.
- Los assets se sirven desde `public/build`. No dejar Vite ni `public/hot`. Compilar con `./vendor/bin/sail npm run build` al terminar un lote que deba verse; no tras cada edición ni como root. Conservar la corrección de `scripts/retain-vite-assets.mjs`.
- Pruebas en servidor, con la caché aislada: `./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test ...`
- Pruebas dirigidas y proporcionales; no repetir suites, builds ni capturas por rutina.
- No mover `.env`, credenciales, base de datos ni documentos de clientes por Git. No hacer commit ni push sin que el usuario lo pida.
- Revisar el estado de Git antes de editar para no pisar trabajo existente.

## Interfaz

Antes de tocar una pantalla leer [DESIGN.md](DESIGN.md) (identidad visual de GELIA) y abrir la vista de referencia que indica. Usar los tokens, primitivas y componentes existentes (`resources/css/gelia/`, `resources/js/Components/`). Evidencia visual: [GUIA_EVIDENCIA_VISUAL.md](GUIA_EVIDENCIA_VISUAL.md).

## Gestión de pedidos y Tienda

Antes de cambiar estos módulos leer, en este orden:

1. [docs/ai/MAPA.md](docs/ai/MAPA.md) y el módulo que corresponda; se mantienen según [.cursor/rules/mantenimiento-contexto.mdc](.cursor/rules/mantenimiento-contexto.mdc).
2. [Tienda — decisiones vigentes](gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md), solo si el cambio toca Tienda.
3. [Guía operativa V3.1](gestion-pedidos-docs/GELIA_GESTION_PEDIDOS_GUIA_OPERATIVA_V3.1_2026-11-21.md). Al corregir un flujo, actualizar su recorrido y la tabla §15 (fecha, regla sustituida, decisión, casos) en el mismo trabajo.

Documentos de detalle, a consultar solo si el cambio los toca: [T-02](gestion-pedidos-docs/TIENDA_T02_IMPLEMENTACION.md), [T-02.1 MD](gestion-pedidos-docs/TIENDA_MD_IMPLEMENTACION.md), [configuración operativa](gestion-pedidos-docs/CONFIGURACION_OPERATIVA_TIENDA.md), [T-03 traslados](gestion-pedidos-docs/TIENDA_T03_IMPLEMENTACION.md), [disponibilidad y Mismo día](gestion-pedidos-docs/DISPONIBILIDAD_ACCIONES_RAPIDAS.md).

Reglas vigentes:

- Conservar los pedidos legados y los flujos municipal y nacional. No restaurar el panel «Decisión del cliente».
- En la interfaz de usuario usar nombres de operación («Cerrar consulta», «Confirmar estado»), nunca códigos de ficha (T-01, T-02…). Los identificadores internos se conservan.
- T-01 (Pago al recoger): se cobra y remisiona en PDV; GELIA no registra exhibición de pago. T-02: transferencia verificada y remisión Wizerp; procedencia, destino, preparación, salida, recepción, custodia y entrega son hechos distintos.
- Los 20 días de T-02 generan seguimiento pendiente; no cancelan la venta ni liberan mercancía pagada. Mismo día (MD) está disponible para todos los departamentos y se solicita en transferencia tras cerrar la consulta.
- El segundo traslado exige autorización de gerencia. Solicitud, autorización, salida y recepción son hechos distintos.
- El cierre de consulta exige resolver faltantes y confirmar, retirar o sustituir lo reportado; no pedir recaptura del conjunto correcto. Sin existencias no admite «Confirmar estado».
- La revisión visual de T-02 y el recorrido con cuentas reales siguen pendientes; las capturas de T-01 no los acreditan. No dar por probado lo que los documentos marcan pendiente.

El historial completo de decisiones anteriores está en [ARCHIVO_DECISIONES_AGENTES.md](gestion-pedidos-docs/ARCHIVO_DECISIONES_AGENTES.md); no leerlo por rutina.
