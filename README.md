# Private Area Endpoint para WordPress

Plugin de WordPress que añade a la API REST un endpoint para **listar los archivos privados de un cliente**, pensado para consumirlos desde una app externa. Trabaja con los datos del plugin **WP Customer Area**.

## Endpoint

`GET /wp-json/custom/v1/listar-privados?user_id=<id>`

- `user_id` (obligatorio): id del usuario de WordPress.
- Devuelve los posts con archivos privados cuyo propietario es ese usuario (título, contenido, archivo y fecha).
- Errores: `400` si falta `user_id` y `404` si el usuario no existe.

## Instalación

1. Copia `Private-area-endpoint.php` en `wp-content/plugins/private-area-endpoint/`.
2. Actívalo en **Plugins**.
3. Necesita el plugin **WP Customer Area** (usa sus metadatos `cuar_*`).

## Tecnologías

PHP y la API REST de WordPress.

## Autor

Sergio Bernabé
