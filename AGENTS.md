# Guía para agentes de GELIA

## Continuidad de Gestión de pedidos y Tienda

Actualizado el 4 de noviembre de 2026 para continuar el trabajo en otro servidor o chat. Antes de cambiar estos módulos, leer:

1. [Tienda — decisiones vigentes y estado verificado](gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md): referencia operativa principal; las decisiones explícitas posteriores del usuario prevalecen.
2. [Continuidad T-01](gestion-pedidos-docs/CONTINUIDAD_CURSOR_TIENDA_T01.md): implementación anterior, capturas conservadas y verificaciones. Su instrucción de detener trabajo describe un cierre anterior; el usuario autorizó después continuar con T-02.
3. [Implementación T-02](gestion-pedidos-docs/TIENDA_T02_IMPLEMENTACION.md): transferencia, procedencia y destino, bolsas, recepción, custodia, entrega, corrección del build y límites de comprobación.
4. [T-02.1 — TIENDA MD](gestion-pedidos-docs/TIENDA_MD_IMPLEMENTACION.md): consulta a CEDIS y Mismo día al confirmar transferencia, corte configurable (13:00 por defecto), continuación explícita y custodia pagada. Decisión posterior del usuario: MD disponible para todos los departamentos, sin permiso especial MD; conservar las facultades normales por actor y sucursal.

- Comprobar el código y el estado de Git antes de editar. Los cambios de T-01 y T-02 ya fueron implementados; no rehacerlos ni dar por probado lo que los documentos señalan pendiente.
- Conservar la compatibilidad de pedidos legados y los flujos de municipal y nacional. No restaurar el panel retirado «Decisión del cliente» ni exigir recaptura individual para la separación documental aprobada.
- T-01: después de cerrar consulta y guardar Pago al recoger, Ventas envía explícitamente la confirmación al auxiliar. El envío avisa una sola vez, conserva la vigencia y habilita mantener el apartado; no confirma cobro ni separación física. Guardar fuera de horario conserva la modalidad y muestra el bloqueo de confirmación, sin prorrogar.
- T-01 con Pago al recoger se cobra y remisiona en PDV, sin registrar una exhibición de pago en GELIA. T-02 usa transferencia verificada y remisión Wizerp; procedencia, destino, preparación, salida, recepción, custodia y entrega son hechos distintos.
- Los 20 días de T-02 se cuentan desde la emisión de remisión y generan seguimiento pendiente; no cancelan la venta ni liberan automáticamente mercancía pagada. T-02.1 está implementado sobre el recorrido compartido: días y corte configurables (lunes a viernes hasta las 13:00 por defecto), primer reporte original conservado, sin reprogramación automática ni liberación de mercancía pagada. T-03, T-05, T-09 y R-01 mantienen su alcance propio.
- Consulta CEDIS: PanelRespuestaDisponibilidad reutiliza el espacio de Preparación Tienda, visor y galería. Capturar solo excepciones por catálogo/escaneo o descripción manual; conservar la respuesta del conjunto, sin anticipar pago, bolsas o traslado. Véase la adecuación posterior en TIENDA_T02_IMPLEMENTACION.md.
- En el nuevo servidor, comprobar primero el entorno y los archivos disponibles. La revisión visual de T-02 y el recorrido con cuentas reales siguen pendientes; las capturas de T-01 no acreditan T-02.

Ajuste posterior: leer [Configuración operativa y consulta por sucursal](gestion-pedidos-docs/CONFIGURACION_OPERATIVA_TIENDA.md). No restaurar la tercera entrada inicial TIENDA MD ni párrafos del proceso. Mismo día se solicita en transferencia después de cerrar consulta. Las capacidades de consulta se reutilizan de Catálogos → Almacenes, conservando las restricciones piloto.

## Ejecución y comprobaciones

- Ejecutar PHP, Artisan, Composer, npm y pruebas mediante `./vendor/bin/sail`. Si Sail no está disponible, diagnosticar el entorno antes de sustituir sus comandos por los del host.
- Este entorno sirve assets de `public/build`. No dejar Vite ni `public/hot` activos. Compilar mediante `./vendor/bin/sail npm run build` cuando haga falta publicar un lote de cambios; no compilar después de cada edición ni ejecutar npm como root.
- El build conserva assets anteriores y publica el manifiesto después de verificar los archivos. Mantener la corrección de `scripts/retain-vite-assets.mjs` para evitar EACCES al reemplazar archivos creados por otro usuario; no resolverlo abriendo permisos generales.
- En pruebas de servidor, aislar ambos archivos de caché con `./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test ...`. Un prefijo de variable delante de Sail no garantiza su transmisión al contenedor. PHPUnit fuerza SQLite en memoria y caché array cuando no se carga la configuración cacheada del servidor.
- Mantener las comprobaciones dirigidas y proporcionales, conforme a la solicitud del usuario de cuidar la estabilidad del servidor. No repetir suites, builds ni capturas por rutina; ampliar solo ante cambios, fallos o dudas concretas.
- No trasladar `.env`, credenciales, base de datos ni documentos de clientes mediante Git. Su migración se gestiona por separado. No hacer commit ni push sin que el usuario lo solicite.

## Trabajo con interfaz y skills de diseño

Antes de analizar, crear o modificar una interfaz, leer [GUIA_DISENO_GELIA.md](GUIA_DISENO_GELIA.md). Es la guía común para Codex, Cursor y otros agentes que trabajen en este repositorio.

- Partir del lenguaje visual de GELIA y de las decisiones vigentes del usuario. Las skills aportan técnicas; no deciden por su cuenta la personalidad del producto.
- Inspeccionar una referencia existente del mismo tipo de tarea y sus estilos efectivos antes de elegir una skill. La guía contiene las rutas de referencia y los pasos de trabajo.
- Elegir únicamente las skills necesarias para un problema identificado. Impeccable es opcional y su hook automático está desactivado por decisión del 5 de noviembre; no reactivarlo por rutina. La suite local visual-refresh/visual-discovery/visual-implementation fue retirada. Seguir la adaptación de skills de `GUIA_DISENO_GELIA.md`, sin activar cadenas de diseño o movimiento por defecto.
- Evaluar el conjunto: composición, jerarquía, densidad, tipografía, cliente, estados y acciones. Usar tokens correctos o corregir un desbordamiento no demuestra por sí solo que el diseño encaje en GELIA.
- Reutilizar tokens, primitivas, constantes y componentes existentes. Conservar la personalización de tema, fuente y acento.
- Verificar el resultado renderizado a los tamaños pertinentes; informar qué se comprobó y qué sigue pendiente. Una revisión aislada no acredita integración ni funcionamiento de negocio.
- Continuar con las decisiones ya autorizadas. No reiniciar una exploración ni solicitar de nuevo una aprobación existente por una recomendación genérica de una skill.

Estas indicaciones se limitan al trabajo con interfaz. Un cambio de backend no inicia un rediseño. Las instrucciones de la sesión y las reglas del entorno conservan su precedencia.

Ajuste del 5 de noviembre: nunca mostrar códigos de fichas de proceso en botones, avisos o movimientos de usuario; usar nombres de operación. Conservar los identificadores internos para compatibilidad. El retiro de un faltante reportado permite seleccionar su revisión incluso sin SKU, registra la decisión de Ventas y no libera inventario inexistente. Los retiros físicos siguen a cargo del auxiliar. «Ver detalles» del flujo actual de Tienda omite campos heredados de envíos, pesos y domicilio. Véase la depuración visible en TIENDA_T02_IMPLEMENTACION.md y sus capturas con datos ficticios.

Continuación del 5 de noviembre: leer [City Center y cambio de sucursal](gestion-pedidos-docs/TIENDA_T03_IMPLEMENTACION.md) antes de modificar traslados. La petición del usuario exige autorización de gerencia para el segundo traslado; prevalece sobre la propuesta conceptual sin autorización. Evidencia del cliente o anotación «Se notificó un error de captura»; solicitud, autorización, salida y recepción son hechos distintos. El cambio municipal/nacional registra intención pendiente de conexión. No reactivar el bloqueo por nombre de City Center ni convertir solicitudes en movimientos físicos.

Revisión posterior del 5 de noviembre: leer [Acciones rápidas de disponibilidad y Mismo día](gestion-pedidos-docs/DISPONIBILIDAD_ACCIONES_RAPIDAS.md). El cierre ya exige resolver faltantes y confirmar/retirar/sustituir los detalles reportados; no pedir recaptura del conjunto correcto. Sin existencias no admite Confirmar estado. Conservar el historial de ajustes fuera de la captura detallada plegable y las claves de solicitud para evitar duplicados. La reapertura notifica al origen de la consulta y conserva el reporte MD original, pagos y documentos.
