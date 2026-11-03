---
name: visual-implementation
description: Implementar y validar una renovación visual elegida o autorizada de una vista SaaS/ERP o ecommerce en GELIA u otro proyecto. Usar para aplicar un prototipo aprobado, ejecutar fases visuales y refinar componentes preservando tokens globales y reglas de negocio. No sustituir la exploración cuando todavía se busca elegir un diseño.
---

# Implementar diseño visual

## Retomar elección y estado actual

Leer instrucciones locales y cambios existentes. Recuperar brief, prototipo, elección y autorización del chat o documentos. No exigir un archivo si la sesión contiene autorización suficiente. Aprobar el diagnóstico no equivale a aprobar un diseño todavía no mostrado.

Si se pide implementar de principio a fin sin elección previa, hacer descubrimiento, elegir dirección fundamentada y continuar dentro de la autorización. Si solo se buscan propuestas, usar `visual-discovery` antes de editar vistas activas.

Revalidar archivos, componentes y tokens contra código vigente; documentar diferencias sin repetir auditoría válida. Leer [verification.md](references/verification.md).

Si no hay contrato previo, construir mapa breve de tokens efectivos y comportamiento: acción → permiso/condición → ruta/efecto → comprobación. Leer CSS, imports, overrides, configuración y componentes compartidos. En GELIA verificar reglas `estilos-gelia.mdc`, `geliaTheme`, `theme-*` y `gelia-estado-vivo`; en otros proyectos usar sus fuentes reales.

## Usar recursos con evidencia

Identificar una necesidad concreta antes de elegir una skill. `frontend-design`, `ui-ux-pro-max`, Impeccable y `web-design-guidelines` son opciones; no descubrirlas ni leerlas todas por defecto. Usar solo los recursos necesarios para el diseño autorizado, siguiendo GUIA_DISENO_GELIA.md y sin volver a explorar estilos incompatibles.

Seguir documentación real para scripts/comandos; no inventar sintaxis. Distinguir lectura de ejecución y revisión propia de auditoría formal. Si falta recurso, continuar y registrar límite. No activar hooks ni instalar paquetes por defecto. Evitar recursión si un recurso remite a esta suite.

## Implementar por unidades comprobables

Dividir alcance por riesgo: estilos/componentes locales, estructura/listado, detalle/formularios, estados y refinamiento; adaptar orden al proyecto. Reutilizar componentes adecuados y extender variantes antes de duplicar sistemas.

Usar tokens/clases semánticas reales. Para variantes faltantes, proponer o aplicar extensión compatible autorizada, revisando consumidores. No tocar variables globales por un ajuste local ni introducir paletas fijas donde hay tokens.

Conservar rutas, permisos, props/API, validaciones, precios, stock, filtros/params, persistencia, polling y efectos operativos pertinentes. No llevar mocks del prototipo a producción. Mantener acciones frecuentes y estados claros según perfil.

No modificar backend, migraciones o integraciones como efecto secundario. Separar funcionalidad nueva y completar lo autorizado. No imponer gates adicionales si la sesión ya autoriza el alcance.

## Verificar resultado real

Aplicar [verification.md](references/verification.md). Ejecutar checks pertinentes descubiertos en el proyecto y revisión visual con herramientas disponibles. En GELIA verificar si existe `npm run check:style`; no asumir comando universal.

Corregir contraste, foco, desbordes, densidad, estados y comportamiento afectados. Revisar diff y consumidores. Agregar tests por riesgo funcional real, no por clases o markup.

## Entregar

Registrar comprobaciones ejecutadas, resultados y pendientes en el área documental del proyecto o `docs/visual-refresh/<vista>/verification.md`. Mostrar capturas/preview de la app real cuando sea posible; no usar imágenes del prototipo como evidencia implementada.

Resumir cambios, validaciones y riesgo material pendiente. No garantizar equivalencia funcional o calidad estética por declaración. No desplegar, publicar ni hacer merge sin autorización correspondiente.
