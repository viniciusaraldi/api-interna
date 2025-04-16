<?php

class Response {

    public static function json(array $data, int $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');

        return $data;
    } 
}