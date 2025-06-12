<?php

$routes = [
    'ping' => function ($method = '', ...$params) { return action('ping', $method, $params); },
    'comercial' => function ($method, ...$params) { return action('comercial', $method, $params); },
    'tecido' => function($method, ...$params) { return action('tecido', $method, $params); },
    'almoxarifado' => function($method, ...$params) { return action('almoxarifado', $method, $params); },
    'autenticacao' => function($method, ...$params) { return action('autenticacao', $method, $params); },
    'financeiro2' => function($method, ...$params) { return action('financeiro2', $method, $params); },
    'financeiro' => function($method, ...$params) { return action('financeiro', $method, $params); },
    'estilo' => function($method, ...$params) { return action('estilo', $method, $params); },
];

function action($rota, $method, $params) {
    require_once __DIR__ . "/../controller/" . ucfirst($rota) . ".php";
    $upperAction = ucfirst($rota);
    $action = new $upperAction();
    return $action->$method($params);
}