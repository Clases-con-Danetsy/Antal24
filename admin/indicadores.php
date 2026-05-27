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
        'timeout' => 10,
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
        'ignore_errors' => true
    ]
]);

$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'No se pudo obtener respuesta del servicio oficial del DOF.'
    ]);
    exit;
}

// Retornar directamente la respuesta de la API oficial (JSON)
echo $response;
