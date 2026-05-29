<?php
// ============================================
// admin/cron_dolar.php
// Script para sincronizar el tipo de cambio del DOF con la base de datos
// Ejecución CLI: php admin/cron_dolar.php [--history]
// Ejecución Web: http://localhost/antal24/admin/cron_dolar.php?history=1
// ============================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Crear tabla si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tipo_cambio` (
        `fecha` DATE PRIMARY KEY,
        `valor` DECIMAL(10, 4) NOT NULL,
        `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Determinar rango de fechas
    $isHistory = false;
    if (php_sapi_name() === 'cli') {
        $args = $argv ?? [];
        $isHistory = in_array('--history', $args);
    } else {
        $isHistory = isset($_GET['history']) && $_GET['history'] == '1';
    }

    $today = new DateTime();
    if ($isHistory) {
        // Desde el 1 de enero de 2026
        $startDate = DateTime::createFromFormat('Y-m-d', '2026-01-01');
    } else {
        // Últimos 15 días por defecto para asegurar continuidad
        $startDate = clone $today;
        $startDate->modify('-15 days');
    }

    $startFormatted = $startDate->format('d-m-Y');
    $endFormatted   = $today->format('d-m-Y');

    // 3. Consultar DOF API con método robusto
    $url = "https://sidof.segob.gob.mx/dof/sidof/indicadores/158/" . urlencode($startFormatted) . "/" . urlencode($endFormatted);

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 12,
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $response = @file_get_contents($url, false, $ctx);

    if ($response === false && function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        curl_close($ch);
    }

    if ($response === false) {
        throw new Exception("No se pudo conectar con el servicio de indicadores del DOF.");
    }

    $data = json_decode($response, true);
    if (!isset($data['ListaIndicadores']) || !is_array($data['ListaIndicadores'])) {
        throw new Exception("Respuesta del DOF inválida o vacía.");
    }

    // 4. Insertar/Actualizar registros en la base de datos
    $stmt = $pdo->prepare("
        INSERT INTO `tipo_cambio` (`fecha`, `valor`)
        VALUES (:fecha, :valor)
        ON DUPLICATE KEY UPDATE `valor` = :valor_update
    ");

    $count = 0;
    foreach ($data['ListaIndicadores'] as $item) {
        if (!isset($item['fecha']) || !isset($item['valor'])) {
            continue;
        }

        // Convertir DD-MM-YYYY a YYYY-MM-DD para la BD
        $d = DateTime::createFromFormat('d-m-Y', $item['fecha']);
        if (!$d) {
            continue;
        }
        $fechaDb = $d->format('Y-m-d');
        $valor = floatval($item['valor']);

        $stmt->execute([
            ':fecha' => $fechaDb,
            ':valor' => $valor,
            ':valor_update' => $valor
        ]);
        $count++;
    }

    echo json_encode([
        'ok' => true,
        'message' => "Sincronización completada exitosamente.",
        'is_history' => $isHistory,
        'rango' => [$startDate->format('Y-m-d'), $today->format('Y-m-d')],
        'registros_procesados' => $count
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
