# Mensajería — 6 de noviembre de 2026

Solicitud: modernizar el módulo, diferenciar superficies y reparar controles, conservando fuente, tema y acento de GELIA. Referencia del usuario: mensajería moderna, similar a WhatsApp, sin convertir todo el módulo en una superficie negra.

Se separan lista, encabezados, fondo de conversación y compositor. La selección usa el acento, los avatares sin fotografía muestran iniciales y los archivos compartidos incorporan iconos y estados vacíos. El panel de archivos se superpone cuando el área real del chat es estrecha, incluso en tablet o en la mensajería lateral. Los stickers quedan en una bandeja desplazable; el tablero distingue la transparencia y evita guardar imágenes vacías o duplicar el guardado.

El cajón profesional estaba debajo de la barra superior móvil: esta tapaba su encabezado y botón de cierre. Se ajustaron sus capas y se restaura el foco al botón que lo abrió. La página usa el acceso global a navegación, sin repetirlo dentro de la lista.

Los envíos fallidos conservan el texto y muestran el error. Se evita enviar dos veces mientras la primera petición está pendiente y borrar texto nuevo escrito durante el envío. Los archivos descartan respuestas antiguas al cambiar de categoría y permiten reintentar; los stickers limpian errores al recargar. Nuevo chat y grupo muestran errores del servidor y admiten Escape. La grabación avisa cuando el navegador carece de acceso al micrófono y selecciona un formato admitido.

La migración existente `2026_11_06_120000_add_stickers_to_mensajeria.php` estaba pendiente. Se ejecutó únicamente esa migración, después de validar la prueba de stickers, y se comprobó que quedó aplicada. No se ejecutaron migraciones ajenas ni se alteraron mensajes existentes.

## Comprobación

- Sail: build publicado y manifiesto verificado; sin Vite ni `public/hot`.
- Vitest: 7 pruebas dirigidas en MensajeInput, ChatDrivePanel y BandejaStickers; conservación del borrador, duplicados, composición IME, Escape, errores y peticiones fuera de orden.
- PHP: `tests/Feature/Mensajeria/StickersMensajeriaTest.php`, 1 prueba, 12 comprobaciones; SQLite en memoria y cachés de configuración/rutas aisladas.
- `scripts/qa/verificar-mensajeria.mjs`: render del componente real y AppLayout con assets publicados; servidor de transporte ficticio. Tamaños 1440×1000, 430×932, 390×844, 820×900 y 844×430; claro y oscuro. Clics en nuevo chat/grupo, filtros, navegación móvil y su cierre, adjuntos/Escape, archivos y stickers. Envío de texto simulado y rechazo de sticker vacío. Sin errores de navegador, desbordamiento horizontal ni desplazamiento de la página; compositor visible.
- Capturas adjuntas revisadas visualmente. Usuarios y mensajes ficticios; no se enviaron mensajes a colaboradores.

No acredita entrega en tiempo real entre cuentas, permisos de cuentas particulares, descarga de documentos reales, uso de cámara/micrófono físico ni funcionamiento de red fuera de esta comprobación. La grabación depende del permiso del navegador y de un contexto seguro. La migración y las pruebas del servidor sí se ejecutaron en el entorno de Sail indicado.
