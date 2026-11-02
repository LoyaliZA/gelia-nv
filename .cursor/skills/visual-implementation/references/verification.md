# Verificar un rediseño

## Revisión proporcional

Leer scripts del proyecto y ejecutar checks pertinentes: estilos, lint, tipos, build y tests del flujo. Registrar comando/resultado, separando errores previos y nuevos por evidencia. No presentar limitaciones como checks aprobados.

## Inspección visual

Abrir implementación real si hay herramientas y acceso. Elegir escritorio y móvil según breakpoints; partir de 1440 y 390 px y agregar anchos próximos al cambio de layout. Un viewport configurado no equivale a inspección realizada.

Cubrir temas soportados, contenido largo, muchos/pocos resultados, carga, vacío, error y deshabilitado. Añadir urgencia/incidencia en operación y variantes/stock/carrito en ecommerce cuando estén en alcance.

Revisar jerarquía, legibilidad, densidad, tokens; teclado/foco/nombres; color más texto/icono; scroll, overlays, sticky, formularios y teclado móvil; movimiento reducido. Medir contraste si hay herramientas, sin declarar accesibilidad global por inspección superficial. Registrar procedimiento y resultado si se usó Impeccable/Web Design Guidelines.

## Comportamiento

Usar matriz funcional y código vigente. Cubrir rutas, filtros/params, persistencia, permisos y estados relevantes. Preservar foco/edición con polling. En operación revisar faltantes, validaciones y CTAs por estado; en ecommerce variantes, precio/promoción existente, stock y continuidad de carrito/checkout.

No hacer pedidos reales, cobros o acciones irreversibles como QA. Usar fixtures/entorno de prueba o revisión de código y declarar qué no se ejercitó.

Reutilizar tests existentes o añadir tests de comportamiento por riesgo real. Una vez cubierto el alcance, no ampliar checks sin cambios/dudas que lo justifiquen.

## Registro

Crear tabla comprobación → viewport/tema/entorno → ejecutada o pendiente → resultado → evidencia. Documentar consumidores afectados de componentes compartidos, apertura y pendientes concretos. No declarar “todo probado” sin cobertura real.
