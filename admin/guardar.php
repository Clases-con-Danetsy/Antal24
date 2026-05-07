<?php
// ============================================
// admin/guardar.php
// Recibe el formulario y guarda en MySQL
// ============================================

session_start();
require_once 'config.php';

// Solo usuarios autenticados
if (!isset($_SESSION['antal_admin']) || $_SESSION['antal_admin'] !== true) {
    header('Location: index.php');
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// --- Sanitizar inputs de texto ---
function limpia(string $val): string {
    return trim(htmlspecialchars($val, ENT_QUOTES, 'UTF-8'));
}

$categoria_es = limpia($_POST['categoria_es'] ?? '');
$categoria_en = limpia($_POST['categoria_en'] ?? '');
$fecha_es     = limpia($_POST['fecha_es']     ?? '');
$fecha_en     = limpia($_POST['fecha_en']     ?? '');
$titulo_es    = limpia($_POST['titulo_es']    ?? '');
$titulo_en    = limpia($_POST['titulo_en']    ?? '');
$tags_es      = limpia($_POST['tags_es']      ?? '');
$tags_en      = limpia($_POST['tags_en']      ?? '');
$publicado    = isset($_POST['publicado']) ? (int)$_POST['publicado'] : 1;

// Convertir párrafos: doble salto de línea → separador |||
function parseParagraphs(string $texto): string {
    // Normalizar saltos de línea
    $texto = str_replace("\r\n", "\n", $texto);
    // Dividir por doble salto de línea
    $parrafos = preg_split('/\n\s*\n/', $texto, -1, PREG_SPLIT_NO_EMPTY);
    // Limpiar cada párrafo y unir con |||
    $parrafos = array_map('trim', $parrafos);
    $parrafos = array_filter($parrafos); // quitar vacíos
    return implode('|||', $parrafos);
}

$contenido_es = parseParagraphs($_POST['contenido_es'] ?? '');
$contenido_en = parseParagraphs($_POST['contenido_en'] ?? '');

// --- Validación básica ---
$campos_requeridos = [$categoria_es, $categoria_en, $fecha_es, $fecha_en, $titulo_es, $titulo_en, $contenido_es, $contenido_en];
foreach ($campos_requeridos as $campo) {
    if (empty($campo)) {
        header('Location: index.php?ok=error&msg=campos_vacios');
        exit;
    }
}

// --- Manejo de imagen ---
$imagen_ruta = null;

if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $file      = $_FILES['imagen'];
    $maxSize   = 5 * 1024 * 1024; // 5 MB
    $allowed   = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $mime      = $finfo->file($file['tmp_name']);

    if ($file['size'] > $maxSize) {
        header('Location: index.php?ok=error&msg=imagen_grande');
        exit;
    }

    if (!in_array($mime, $allowed)) {
        header('Location: index.php?ok=error&msg=formato_invalido');
        exit;
    }

    // Generar nombre único
    $extension = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ][$mime];

    $nombre_archivo = 'noticia_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

    // Carpeta uploads un nivel arriba de admin/
    $dir_uploads = dirname(__DIR__) . '/uploads/';

    if (!is_dir($dir_uploads)) {
        mkdir($dir_uploads, 0755, true);
    }

    $destino = $dir_uploads . $nombre_archivo;

    if (move_uploaded_file($file['tmp_name'], $destino)) {
        $imagen_ruta = 'uploads/' . $nombre_archivo;
    }
}

// --- Guardar en MySQL ---
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "INSERT INTO noticias
              (categoria_es, categoria_en, titulo_es, titulo_en,
               contenido_es, contenido_en, tags_es, tags_en,
               imagen, publicado, fecha_es, fecha_en)
            VALUES
              (:cat_es, :cat_en, :tit_es, :tit_en,
               :con_es, :con_en, :tag_es, :tag_en,
               :imagen, :pub, :fec_es, :fec_en)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':cat_es' => $categoria_es,
        ':cat_en' => $categoria_en,
        ':tit_es' => $titulo_es,
        ':tit_en' => $titulo_en,
        ':con_es' => $contenido_es,
        ':con_en' => $contenido_en,
        ':tag_es' => $tags_es,
        ':tag_en' => $tags_en,
        ':imagen' => $imagen_ruta,
        ':pub'    => $publicado,
        ':fec_es' => $fecha_es,
        ':fec_en' => $fecha_en,
    ]);

    header('Location: index.php?ok=guardado');
    exit;

} catch (PDOException $e) {
    // En producción no mostrar el error directamente
    error_log('DB Error en guardar.php: ' . $e->getMessage());
    header('Location: index.php?ok=error&msg=db');
    exit;
}