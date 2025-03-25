<?php

class Response {

    public static function json(array $data, int $status = 200) {
        http_response_code($status);

        return $data;
    } 
}