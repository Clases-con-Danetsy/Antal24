<?php
// ============================================
// admin/actualizar.php
// Recibe el formulario de edición y actualiza en MySQL
// ============================================

session_start();
require_once 'config.php';

if (!isset($_SESSION['antal_admin']) || $_SESSION['antal_admin'] !== true) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id === 0) {
    header('Location: index.php?ok=error&msg=id_invalido');
    exit;
}

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

function parseParagraphs(string $texto): string {
    $texto    = str_replace("\r\n", "\n", $texto);
    $parrafos = preg_split('/\n\s*\n/', $texto, -1, PREG_SPLIT_NO_EMPTY);
    $parrafos = array_map('trim', $parrafos);
    $parrafos = array_filter($parrafos);
    return implode('|||', $parrafos);
}

$contenido_es = parseParagraphs($_POST['contenido_es'] ?? '');
$contenido_en = parseParagraphs($_POST['contenido_en'] ?? '');

$campos_requeridos = [$categoria_es, $categoria_en, $fecha_es, $fecha_en, $titulo_es, $titulo_en, $contenido_es, $contenido_en];
foreach ($campos_requeridos as $campo) {
    if (empty($campo)) {
        header('Location: index.php?ok=error&msg=campos_vacios');
        exit;
    }
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener imagen actual
    $stmtImg = $pdo->prepare("SELECT imagen FROM noticias WHERE id = ?");
    $stmtImg->execute([$id]);
    $noticia_actual = $stmtImg->fetch(PDO::FETCH_ASSOC);
    if (!$noticia_actual) {
        header('Location: index.php?ok=error&msg=no_encontrado');
        exit;
    }
    $imagen_ruta = $noticia_actual['imagen'];

    // Procesar nueva imagen si se sube
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['imagen'];
        $maxSize = 5 * 1024 * 1024;
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($file['tmp_name']);

        if ($file['size'] > $maxSize) {
            header('Location: index.php?editar=' . $id . '&ok=error&msg=imagen_grande');
            exit;
        }
        if (!in_array($mime, $allowed)) {
            header('Location: index.php?editar=' . $id . '&ok=error&msg=formato_invalido');
            exit;
        }

        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
        $nombre_archivo = 'noticia_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $dir_uploads = dirname(__DIR__) . '/uploads/';
        if (!is_dir($dir_uploads)) mkdir($dir_uploads, 0755, true);
        $destino = $dir_uploads . $nombre_archivo;

        if (move_uploaded_file($file['tmp_name'], $destino)) {
            // Borrar imagen vieja si existe
            if ($imagen_ruta) {
                $vieja = dirname(__DIR__) . '/' . $imagen_ruta;
                if (file_exists($vieja)) @unlink($vieja);
            }
            $imagen_ruta = 'uploads/' . $nombre_archivo;
        }
    }

    $sql = "UPDATE noticias SET
                categoria_es = :cat_es,
                categoria_en = :cat_en,
                titulo_es    = :tit_es,
                titulo_en    = :tit_en,
                contenido_es = :con_es,
                contenido_en = :con_en,
                tags_es      = :tag_es,
                tags_en      = :tag_en,
                imagen       = :imagen,
                publicado    = :pub,
                fecha_es     = :fec_es,
                fecha_en     = :fec_en
            WHERE id = :id";

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
        ':id'     => $id,
    ]);

    header('Location: index.php?ok=actualizado');
    exit;

} catch (PDOException $e) {
    error_log('DB Error en actualizar.php: ' . $e->getMessage());
    header('Location: index.php?ok=error&msg=db');
    exit;
}
