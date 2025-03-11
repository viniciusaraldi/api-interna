<?php

require_once __DIR__ . '/../config/init.php';

class Tecido extends DB {
    public function __construct() {
        parent::__construct();
    }

    public function get(array $path): array {
        try {
            $funcao = $path[0];
            $queryString = $path[1];
            $infoQueryDivida = explode('&',$queryString);

            $ordemCompra = '';
            $material = '';
            $corMaterial = '';
            $empresa = 0;
            $loteNovo = '';
            $user = '';

            $mapa = [
                'ordemCompra' => &$ordemCompra,
                'material' => &$material,
                'corMaterial' => &$corMaterial,
                'empresa' => &$empresa,
                'loteNovo' => &$loteNovo,
                'user' => &$user,
            ];

            foreach ($infoQueryDivida as $value) {
                $pilhaInfo = explode("=",$value);
                if (isset($pilhaInfo[0], $pilhaInfo[1]) && isset($mapa[$pilhaInfo[0]])) {
                    $mapa[$pilhaInfo[0]] = $pilhaInfo[1];
                }
            }

            switch($funcao) {
                case 'getLaudo':
                    return $queryString ? $this->{$funcao}($material, $corMaterial, $ordemCompra) : [
                        'status' => 404,
                        'messagem' => 'Você precisa informar os parametros na rota: ' . $funcao,
                    ];
                case 'GetCoIten':
                    return $queryString ? $this->{$funcao}($empresa, $ordemCompra, $material, $corMaterial) : [
                        'status' => 404,
                        'messagem' => 'Você precisa informar os parametros na rota: ' . $funcao,
                    ];
                default:
                    return [
                        'status' => 404,
                        'messagem' => 'Não foi encontrado a rota ' . $funcao,
                    ];
            }
        } catch (Exception $e) {
            return [
                "status" => 500,
                "data" => "Error: " . $e->getMessage(),
            ];
        }
    } 

    public function put($path): array {
        try {
            $funcao = $path[0];
            $queryString = $path[2];
            // $infoQueryDivida = explode('&',$queryString);

            $ordemCompra = $queryString['ordemCompra'];
            $material = $queryString['material'];
            $corMaterial = $queryString['corMaterial'];
            $empresa = $queryString['empresa'];
            $loteNovo = $queryString['loteNovo'];
            $user = $queryString['user'];

            // $mapa = [
            //     'ordemCompra' => &$ordemCompra,
            //     'material' => &$material,
            //     'corMaterial' => &$corMaterial,
            //     'empresa' => &$empresa,
            //     'loteNovo' => &$loteNovo,
            //     'user' => &$user,
            // ];

            // foreach ($queryString as $value) {
            //     $pilhaInfo = explode("=",$value);
            //     if (isset($pilhaInfo[0], $pilhaInfo[1]) && isset($mapa[$pilhaInfo[0]])) {
            //         $mapa[$pilhaInfo[0]] = $pilhaInfo[1];
            //     }
            // }

            switch($funcao) {
                case 'updateLaudo':
                    return $this->{$funcao}($loteNovo, $empresa, $ordemCompra, $material, $corMaterial, $user);
                case 'updateCoIten':
                    return $this->{$funcao}($loteNovo, $empresa, $ordemCompra, $material, $corMaterial, $user);
                default:
                    return [
                        'status' => 404,
                        'messagem' => 'Não foi encontrado a rota ' . $funcao,
                    ];
            }
        } catch (Exception $e) {
            return [
                "status" => 500,
                "data" => "Error: " . $e->getMessage(),
            ];
        }
    }
    protected function getLaudo(string $material, string $corMaterial, string $ordemCompra): array {
        try {
            $dados = parent::getConn()->prepare("
                SELECT OB, ITEM, COR FROM LAUDO_001 WHERE OB = :ordemCompra AND ITEM = :material AND COR = :corMaterial
            ");
            $dados->bindParam(':ordemCompra', $ordemCompra, PDO::PARAM_STR);
            $dados->bindParam(':material', $material, PDO::PARAM_STR);
            $dados->bindParam(':corMaterial', $corMaterial, PDO::PARAM_STR);
            $dados->execute();

            $info = $dados->fetchAll(PDO::FETCH_ASSOC);
            return [
                'response' => $info,
                'status' => 200,
            ];
        } catch (Exception $e) {
            return [
                "status" => 500,
                "data" => "Error: " . $e->getMessage(),
            ];
        }
    }

    protected function getCoIten(int $empresa, string $ordemCompra, string $material, string $corMaterial): array {
        try {
            $dados = parent::getConn()->prepare("
                SELECT * FROM CO_ITEN_00{$empresa} WHERE NUMERO = :ordemCompra AND CODIGO = :material AND COR = :corMaterial
            ");
            $dados->bindParam(':ordemCompra', $ordemCompra, PDO::PARAM_STR);
            $dados->bindParam(':material', $material, PDO::PARAM_STR);
            $dados->bindParam(':corMaterial', $corMaterial, PDO::PARAM_STR);
            $dados->execute();

            $info = $dados->fetchAll(PDO::FETCH_ASSOC);
            return [
                'response' => $info,
                'status' => 200,
            ];
        } catch (Exception $e) {
            return [
                "status" => 500,
                "data" => "Error: " . $e->getMessage(),
            ];
        }
    }

    protected function updateLaudo ($loteNovo, $empresa, $ordemCompra, $material, $corMaterial, $user): array {
        try {
            $dados = parent::getConn()->prepare("
                UPDATE LAUDO_001 SET LOTE = :loteNovo WHERE OB = :ordemCompra AND ITEM = :material AND COR = :corMaterial
            ");
            $dados->bindParam(':loteNovo', $loteNovo, PDO::PARAM_STR);
            $dados->bindParam(':ordemCompra', $ordemCompra, PDO::PARAM_STR);
            $dados->bindParam(':material', $material, PDO::PARAM_STR);
            $dados->bindParam(':corMaterial', $corMaterial, PDO::PARAM_STR);
            $dados->execute();

            $info = $dados->rowCount();
            if ($info) {
                parent::logSisplanConn(strval($empresa), "Alterado Ordem de Compra da Empresa {$empresa}, Ordem de Compra: {$ordemCompra}", $user);
            }
            return [
                'response' => $info,
                'status' => 200,
            ];
        } catch (Exception $e) {
            return [
                "status" => 500,
                "data" => "Error: " . $e->getMessage(),
            ];
        }
    }

    protected function updateCoIten (string $loteNovo, int $empresa, string $ordemCompra, string $material, string $corMaterial,string $user): array {
        try {
            $dados = parent::getConn()->prepare("
                UPDATE CO_ITEN_00{$empresa} SET LOTE = :loteNovo WHERE numero = :ordemCompra AND codigo = :material AND cor = :corMaterial
            ");
            $dados->bindParam(':loteNovo', $loteNovo, PDO::PARAM_STR);
            $dados->bindParam(':ordemCompra', $ordemCompra, PDO::PARAM_STR);
            $dados->bindParam(':material', $material, PDO::PARAM_STR);
            $dados->bindParam(':corMaterial', $corMaterial, PDO::PARAM_STR);
            $dados->execute();

            $info = $dados->rowCount();
            if ($info) {
                parent::logSisplanConn(strval($empresa), "Alterado Ordem de Compra da Empresa {$empresa}, Ordem de Compra: {$ordemCompra}", $user);
            }
            return [
                'response' => $info,
                'status' => 200,
            ];
        } catch (Exception $e) {
            return [
                "status" => 500,
                "data" => "Error: " . $e->getMessage(),
            ];
        }
    }
}