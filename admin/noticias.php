<?php
// ============================================
// api/noticias.php
// Endpoint público: devuelve las noticias en JSON
// GET https://antal24.com/api/noticias.php
// ============================================

// Cabeceras CORS para que Astro pueda hacer fetch
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: no-cache, must-revalidate');

require_once dirname(__DIR__) . '/admin/config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query(
        "SELECT * FROM noticias
         WHERE publicado = 1
         ORDER BY creado_en DESC"
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Transformar separador ||| en array de párrafos
    // y tags en array
    $noticias = array_map(function ($row) {
        return [
            'id'          => (int)$row['id'],
            'categoria_es'=> $row['categoria_es'],
            'categoria_en'=> $row['categoria_en'],
            'titulo_es'   => $row['titulo_es'],
            'titulo_en'   => $row['titulo_en'],
            'contenido_es'=> explode('|||', $row['contenido_es']),
            'contenido_en'=> explode('|||', $row['contenido_en']),
            'tags_es'     => array_map('trim', explode(',', $row['tags_es'])),
            'tags_en'     => array_map('trim', explode(',', $row['tags_en'])),
            'imagen' => $row['imagen']
                            ? SITE_URL . '/' . ltrim($row['imagen'], '/')
                            : null,
            'fecha_es'    => $row['fecha_es'],
            'fecha_en'    => $row['fecha_en'],
            'creado_en'   => $row['creado_en'],
        ];
    }, $rows);

    echo json_encode([
        'ok'      => true,
        'total'   => count($noticias),
        'noticias'=> $noticias,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Error al obtener noticias.',
    ]);
}