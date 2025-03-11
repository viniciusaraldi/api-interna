<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Tratar requisições OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Resposta bem-sucedida para o preflight
    exit;
}

require_once './routes/router.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$params = $_SERVER['QUERY_STRING'];
$body = json_decode(file_get_contents('php://input'), true);

$arraySetor = explode("/", $uri);
$setor = $arraySetor[3];

$foundApi = array_filter( explode("/", $uri));

if (in_array("api",$foundApi)) {
    array_shift($foundApi);
    array_shift($foundApi);
    if (array_key_exists($foundApi[0], $routes)) {
        array_shift($foundApi);     
        echo json_encode($routes[$setor](strtolower($method), $foundApi[0], $params, $body));
    }
} else {
    return [
        "status" => 401,
        "data" => "Não há caminho para API, verifique!",
    ];
}
    