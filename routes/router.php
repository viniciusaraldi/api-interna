<?php

$routes = [
    'ping' => function ($method = '', ...$params) {
        return ['status' => 200,'message' => 'Api funcionando corretamente'];
    },
    'comercial' => function ($method, ...$params) {
        require_once __DIR__ . '/../controller/Comercial.php';
        $comercial = new Comercial();
        return $comercial->$method($params);
    },
    'tecido' => function($method, ...$params) {
        require_once __DIR__ . '/../controller/Tecido.php';
        $tecido = new Tecido();
        return $tecido->$method($params);
    },
    'almoxarifado' => function($method, ...$params) {
        require_once __DIR__ . '/../controller/Almoxarifado.php';
        $almoxarifado = new Almoxarifado();
        return $almoxarifado->$method($params);
    },
    'autenticacao' => function($method, ...$params) {
        require_once __DIR__."/../controller/Autenticacao.php";
        $autenticacao = new Autenticacao();
        return $autenticacao->$method($params);
    },
    'financeiro' => function($method, ...$params) {
        require_once __DIR__ . "/../controller/Financeiro.php";
        $financeiro = new Financeiro();
        return $financeiro->$method($params);
    }
];