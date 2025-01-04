<?php
/*
Plugin Name: Private Area Endpoint
Description: Crea un endpoint y proporciona datos de WP Customer Area para apps externas
Version: 1.0
Author: Sergio Bernabé
*/

// Registra una ruta personalizada para la API REST
function custom_rest_api_route() {
    register_rest_route('custom/v1', '/listar-privados', array(
        'methods' => 'GET',
        'callback' => 'listar_archivos_privados',
        'args' => array(
            'user_id' => array(
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
}

add_action('rest_api_init', 'custom_rest_api_route');

// Callback para listar archivos privados
function listar_archivos_privados($request) {
    // Obtiene el ID de usuario proporcionado en la solicitud GET
    $user_id = $request->get_param('user_id');
    
    if (empty($user_id)) {
        // Si no se proporciona un ID de usuario, devuelve un error
        return new WP_Error('missing_user_id', 'El parámetro user_id es obligatorio.', array('status' => 400));
    }

    // Verifica si el ID de usuario es válido
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return new WP_Error('invalid_user_id', 'El ID de usuario no es válido.', array('status' => 404));
    }

    global $wpdb;

    // Consulta SQL para buscar todos los posts que tienen archivos privados
    $query_archivos = $wpdb->prepare("
        SELECT p.ID, p.post_title, p.post_content, m.meta_value, p.post_date
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
        WHERE p.ID IN (
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'cuar_owner_queryable'
            AND meta_value LIKE %s
        )
        AND (m.meta_key = 'cuar_private_file_file' OR m.meta_key IS NULL)
    ", '|usr_' . $user_id . '|');

    $results_archivos = $wpdb->get_results($query_archivos);

    $archivos_privados = array();

    foreach ($results_archivos as $result) {
        $enlace_descarga = obtener_url_descarga($result);
        $nombre_archivo = isset($result->meta_value) ? obtener_nombre_archivo_desde_cadena_serializada($result->meta_value) : '';
        
        $archivo_privado = array(
            'ID' => $result->ID,
            'titulo' => $result->post_title,
            'contenido' => $result->post_content,
            'fecha_creacion' => $result->post_date,
            'nombre_archivo' => $nombre_archivo,  // Añadido nombre del archivo
            'enlace_descarga' => $enlace_descarga,
        );

        $archivos_privados[] = $archivo_privado;
    }

    // Consulta SQL para buscar todos los posts privados que no tienen archivos
    $query_sin_archivos = $wpdb->prepare("
        SELECT p.ID, p.post_title, p.post_content, p.post_date
        FROM {$wpdb->posts} p
        WHERE p.ID IN (
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'cuar_owner_queryable'
            AND meta_value LIKE %s
        )
        AND p.ID NOT IN (
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'cuar_private_file_file'
        )
    ", '|usr_' . $user_id . '|');

    $results_sin_archivos = $wpdb->get_results($query_sin_archivos);

    $posts_sin_archivos = array();

    foreach ($results_sin_archivos as $result) {
        $post_sin_archivos = array(
            'ID' => $result->ID,
            'titulo' => $result->post_title,
            'contenido' => $result->post_content,
            'fecha_creacion' => $result->post_date,
        );

        $posts_sin_archivos[] = $post_sin_archivos;
    }

    // Combina los resultados de archivos privados y posts sin archivos
    $resultado_final = array(
        'archivos_privados' => $archivos_privados,
        'posts_sin_archivos' => $posts_sin_archivos,
    );

    return rest_ensure_response($resultado_final);
}


// Función personalizada para obtener el nombre del archivo desde la cadena serializada
function obtener_nombre_archivo_desde_cadena_serializada($meta_value) {
    $pattern = '/"file";s:\d+:"(.*?)";/';
    if (preg_match($pattern, $meta_value, $matches)) {
        return $matches[1];
    }
    return '';
}

//funcion para obtener el enlace de descarga de los archivos privados
function obtener_url_descarga($result) {
    $post_id = $result->ID;
    
    //extrae año mes y dia de la fecha
    $fecha_creacion = strtotime($result->post_date); 
    $ano = date('Y', $fecha_creacion);
    $mes = date('m', $fecha_creacion);
    $dia = date('d', $fecha_creacion);

    //extrae el titulo
    $titulo = $result->post_title;

    if (empty($titulo)) {
        // Si el título está vacío, usa el ID del post
        $titulo = $post_id;
    } else {
        // Reemplaza espacios en blanco con guiones
        $titulo = str_replace(' ', '-', $titulo);
    }

    // Obtén el número de archivo de wp_postmeta
    global $wpdb;
    $meta_query = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'cuar_private_file_file'",
        $post_id
    ));

    // Asegúrate de que haya un resultado válido
    $numero_archivo = $meta_query ? obtener_numero_archivo_desde_cadena_serializada($meta_query) : '';

    // Construye la URL de descarga completa
    $url_descarga = "/customer-area/archivos/mis-archivos/{$ano}/{$mes}/{$dia}/{$titulo}/download/{$numero_archivo}";

    return $url_descarga;
}

function obtener_numero_archivo_desde_cadena_serializada($meta_value) {
    $pattern = '/a:1:{s:32:"(.*?)";/';
    if (preg_match($pattern, $meta_value, $matches)) {
        return $matches[1];
    }
    return '';
}
