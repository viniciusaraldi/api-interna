<?php

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../interface/cleanForJson.php';

class Ping extends DB implements cleanForJson
{
    public function __construct() {
        parent::__construct();
    }
    public function get() {
        $sql = "select * from entidade_001 where codcli = '467369' limit 20;";
        $info = parent::getConn()->prepare($sql);
        $info->execute();
        $test = $info->fetchAll(PDO::FETCH_ASSOC);

        $data = self::cleanForJson($test);

        return $data;
    }

    public function put(){
        $sql = "UPDATE ENTIDADE_001 SET NOME = 'VINICIUS.ARALDI' WHERE CODCLI = '467369'";
        $info = parent::getConn()->prepare($sql);
        $info->execute();

        $data = $info->rowCount();

        return $data;
    }

    public function cleanForJson($data) {
        return array_map(function($item) {
            // Recursivamente limpar arrays
            if (is_array($item)) return self::cleanForJson($item);
            // Transformar objetos DateTime em string
            if ($item instanceof \DateTime) return $item->format('Y-m-d H:i:s');
            // Ignorar closures, resources, objetos não serializáveis
            if (is_object($item) || is_resource($item)) return null;
            return $item;
        }, $data);
    }
}