<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
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
$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$files = $_FILES;

$arraySetor = explode("/", $uri);
$setor = $arraySetor[2];

$foundApi = array_filter( explode("/", $uri));

if (in_array("api",$foundApi)) {
    array_shift($foundApi);
    if (array_key_exists($foundApi[0], $routes)) {
        array_shift($foundApi);    
        $data = $routes[$setor](strtolower($method), $foundApi[0], $params, $body, $files);
        header('Content-Type: application/json');

        $json = json_encode($data);

        if ($json === false) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Erro ao converter para JSON',
                'json_error' => json_last_error_msg(),
                'data_preview' => mb_convert_encoding(print_r($data, true), 'UTF-8')
            ]);
            exit;
        }

        echo $json;

    }
} else {
    return [
        "status" => 403,
        "data" => "Não há caminho para API, verifique!",
    ];
}
    