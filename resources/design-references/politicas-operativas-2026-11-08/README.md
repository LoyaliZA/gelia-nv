# Configuración operativa: bancos y prioridad

Componente real del build publicado, con datos y transporte ficticios. Escritorio 1440×1000 y móvil 390×844, claro/oscuro, cristal activo. `verificacion.json` registra cuatro vistas sin errores JavaScript ni desbordamiento, y payload ficticio verificado para deshabilitación de banco, conservación de departamentos y reordenación.

Las capturas de prioridad muestran Municipales en primer lugar porque documentan la interacción de subir; la configuración real conserva Mismo día primero. La distribución por departamento de estas imágenes también es ficticia; no acredita asignaciones reales Bellaroma/Aromas. Se inspeccionaron bancos en móvil claro y prioridad de escritorio oscuro; tras observar palabras partidas en móvil se separaron las flechas debajo del nombre y se inspeccionó la captura móvil oscura final.

Reproducir: `./vendor/bin/sail node scripts/qa/tienda-recorrido/verificar-politicas-operativas.mjs`. Usa Selenium y un servidor temporal interno en 9193; no requiere dependencias nuevas, cuentas operativas ni escribe configuración real. La persistencia y los permisos se comprueban mediante pruebas HTTP aisladas. El recorrido autenticado queda pendiente de aceptación operativa.
