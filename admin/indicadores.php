<?php
// ============================================
// admin/indicadores.php
// Proxy público para consultar el tipo de cambio del dólar en el DOF
// GET /admin/indicadores.php?fechaInicio=YYYY-MM-DD&fechaFin=YYYY-MM-DD
// ============================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/config.php';

$fechaInicio = $_GET['fechaInicio'] ?? '';
$fechaFin    = $_GET['fechaFin']    ?? '';

if (empty($fechaInicio) || empty($fechaFin)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Las fechas de inicio y fin son obligatorias.'
    ]);
    exit;
}

// Validar formato YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Formato de fecha inválido. Utilice YYYY-MM-DD.'
    ]);
    exit;
}

// Convertir YYYY-MM-DD a DD-MM-YYYY
$dateStart = DateTime::createFromFormat('Y-m-d', $fechaInicio);
$dateEnd   = DateTime::createFromFormat('Y-m-d', $fechaFin);

if (!$dateStart || !$dateEnd) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'No se pudieron procesar las fechas.'
    ]);
    exit;
}

// Validación lógica: la fecha de inicio no debe ser mayor a la de fin
if ($dateStart > $dateEnd) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'La fecha de inicio no puede ser posterior a la fecha de fin.'
    ]);
    exit;
}

$startFormatted = $dateStart->format('d-m-Y');
$endFormatted   = $dateEnd->format('d-m-Y');

// El código 158 corresponde al Tipo de Cambio del Dólar en el SIDOF
$url = "https://sidof.segob.gob.mx/dof/sidof/indicadores/158/" . urlencode($startFormatted) . "/" . urlencode($endFormatted);

$ctx = stream_context_create([
    'http' => [
        'timeout' => 8,
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    curl_close($ch);
}

// Si la API oficial respondió correctamente, guardamos en base de datos para respaldo y respondemos
if ($response !== false) {
    $data = json_decode($response, true);
    if (isset($data['ListaIndicadores']) && is_array($data['ListaIndicadores'])) {
        // Guardado silencioso
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("
                INSERT INTO `tipo_cambio` (`fecha`, `valor`)
                VALUES (:fecha, :valor)
                ON DUPLICATE KEY UPDATE `valor` = :valor_update
            ");
            
            foreach ($data['ListaIndicadores'] as $item) {
                if (isset($item['fecha'], $item['valor'])) {
                    $d = DateTime::createFromFormat('d-m-Y', $item['fecha']);
                    if ($d) {
                        $stmt->execute([
                            ':fecha' => $d->format('Y-m-d'),
                            ':valor' => floatval($item['valor']),
                            ':valor_update' => floatval($item['valor'])
                        ]);
                    }
                }
            }
        } catch (Exception $dbEx) {
            // Ignorar errores de guardado para no afectar respuesta
        }
    }
    
    echo $response;
    exit;
}

// --- FALLBACK: Si el DOF se cayó o dio error, consultamos nuestro respaldo en la base de datos ---
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT `fecha`, `valor` FROM `tipo_cambio`
        WHERE `fecha` BETWEEN :start AND :end
        ORDER BY `fecha` ASC
    ");
    $stmt->execute([
        ':start' => $fechaInicio,
        ':end' => $fechaFin
    ]);
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $lista = [];
    foreach ($rows as $row) {
        $d = DateTime::createFromFormat('Y-m-d', $row['fecha']);
        $fechaFormatted = $d ? $d->format('d-m-Y') : $row['fecha'];
        
        $lista[] = [
            'codIndicador' => 0,
            'codTipoIndicador' => 158,
            'fecha' => $fechaFormatted,
            'valor' => $row['valor']
        ];
    }
    
    echo json_encode([
        'messageCode' => 200,
        'response' => 'OK (BackUp Local)',
        'ListaIndicadores' => $lista,
        'TotalIndicadores' => count($lista)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;

} catch (Exception $dbEx) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'No se pudo obtener respuesta del DOF ni del respaldo local.'
    ]);
    exit;
}
