# Gestor de cargas en segundo plano

El seguimiento compartido vive en `GeliaBackgroundTasks` y se monta desde el layout. La primera operación nueva es la carga masiva de Clientes. La carga de imágenes de TiendaNube usa el mismo gestor.

## Trabajador

Hace falta el servicio `queue` de Sail o de producción:

```text
php artisan queue:work --tries=3 --timeout=630
```

La conexión es `database` y la cola es `default`. No hace falta el perfil de colas de TiendaNube.

El proceso anota un latido en caché (`gelia.queue.heartbeat`) en cada ciclo. Si ese latido tiene más de 45 segundos, o si `QUEUE_CONNECTION=sync` fuera de las pruebas, GELIA no encola la importación: el archivo queda recibido o la revisión queda pendiente, y la pantalla lo indica.

Después de publicar este código hay que reiniciar el trabajador para que cargue el latido:

```text
./vendor/bin/sail artisan queue:restart
```

## Activar la carga de Clientes

1. Confirmar que el contenedor `queue` está en marcha.
2. Reiniciar el trabajador.
3. En Clientes, abrir Carga masiva, elegir el archivo y revisar.
4. El modal muestra la revisión paginada. Confirmar solo si no hay bloqueo.
5. Se puede minimizar el seguimiento, cambiar de módulo o recargar. El procesamiento sigue en el trabajador.

Mientras el archivo se transfiere, la página tiene que seguir abierta. Cuando el estado pasa a «Archivo recibido», el servidor ya puede continuar sin esa pestaña.

En el navegador solo se guardan el módulo y el identificador de la operación. No se guarda el archivo ni datos de clientes.

## Límites

- WooCommerce, Almacén y Cobranza conservan sus widgets.
- Un análisis largo sigue siendo el mismo recorrido de revisión; solo deja de ocupar la petición HTTP.
- La confirmación vuelve a comprobar archivo, catálogo y clientes antes de escribir.
- `retry_after` de la cola sigue en 90 segundos. Los tramos y el candado por importación evitan una segunda escritura; no cambian esa configuración.
