<?php

require_once __DIR__ . '/../config/init.php';

class Comercial extends DB {
    public function __construct() {
        parent::__construct();
    }
    public function get($path) {
        try {
            $funcao = $path[0];
            $queryString = $path[1];
            switch($funcao) {
                case 'getEstoqueLocal':
                    return $this->{$funcao}();
                case 'getEstoquePorTam':
                    return $this->{$funcao}($queryString);
                case 'getEstoqueProduto':
                    return $this->{$funcao}($queryString);
                default:
                    return [
                        'status' => 404,
                        'messagem' => 'Não foi encontrado a rota ' . $path[0],
                    ];
            }
        } catch (Exception $e) {
            return [
                "status" => 500,
                "data" => "Error: " . $e->getMessage(),
            ];
        }
    }

    protected function getEstoqueLocal() {
        try {
            $info = parent::getConn()->prepare("
                SELECT T.CODIGO,
                T.COD_INTEGRA,
                CAST(SUM(T.QUANTIDADE - T.QTDE_PEDPEND) AS INTEGER) AS QTDE_LIQ,
                CAST(SUM(T.QUANTIDADE) AS INTEGER) AS QTDE_BRUTA
                FROM (
                SELECT fv.CODIGO, fv.COD_INTEGRA, EST.TIPO, EST.COR, EST.TAM, SUM(EST.QUANTIDADE) QUANTIDADE, EST.LOTE, 
                COALESCE((SELECT UDF_NVL(SUM(PED_ITEN.QTDE)) QTDE 
                                    FROM PED_ITEN_001 PED_ITEN 
                                    LEFT JOIN PEDIDO_001 PEDIDO ON PEDIDO.NUMERO = PED_ITEN.NUMERO 
                                    WHERE PED_ITEN.CODIGO = EST.CODIGO AND PED_ITEN.COR = EST.COR AND PED_ITEN.TAM = EST.TAM AND PED_ITEN.QUALIDADE = EST.TIPO AND PED_ITEN.DEPOSITO = EST.DEPOSITO AND PEDIDO.STATUS IN ('15', '26', '28') ), 0
                                ) AS QTDE_PEDPEND,
                EST.DEPOSITO
                FROM FSIS_VINCULO fv
                INNER JOIN PA_ITEN_001 est ON est.CODIGO = fv.CODIGO AND est.tipo = '1' AND est.DEPOSITO = '2427'
                WHERE FV.TABELA = 'PRODUTO'
                GROUP BY fv.CODIGO, fv.COD_INTEGRA, est.CODIGO, EST.TIPO, EST.COR, EST.TAM, EST.LOTE,QTDE_PEDPEND,EST.DEPOSITO
                ) T
                WHERE 1 = 1
                GROUP BY T.CODIGO, T.COD_INTEGRA
            ");
            $info->execute();
            $estoqueTotal = $info->fetchAll(PDO::FETCH_ASSOC);
            return [
                'response' => $estoqueTotal,
                'status' => 200,
            ];
        } catch (Exception $e) {
            return [
                'response' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }

    protected function getEstoquePorTam ($produto) {
        try {
            $listaProdutos = $produto != null ? $this->queryProdutoUri($produto) : '0';
            $info = parent::getConn()->prepare("
                SELECT 
                T.CODIGO,
                CAST(SUM(T.QUANTIDADE - T.QTDE_PEDPEND) AS INTEGER) AS QTDE_LIQ,
                CAST(SUM(T.QUANTIDADE) AS INTEGER) AS QTDE_BRUTA,
                T.TAM
                FROM (
                    SELECT
                        PA.CODIGO,
                        PA.TIPO,
                        PA.COR,
                        PA.TAM,
                        SUM(PA.QUANTIDADE) QUANTIDADE,
                        PA.LOTE,
                        COALESCE((SELECT UDF_NVL(SUM(PED_ITEN.QTDE)) QTDE 
                            FROM PED_ITEN_001 PED_ITEN 
                            LEFT JOIN PEDIDO_001 PEDIDO ON PEDIDO.NUMERO = PED_ITEN.NUMERO 
                            WHERE PED_ITEN.CODIGO = PA.CODIGO AND PED_ITEN.COR = PA.COR AND PED_ITEN.TAM = PA.TAM AND PED_ITEN.QUALIDADE = PA.TIPO AND PED_ITEN.DEPOSITO = PA.DEPOSITO AND PEDIDO.STATUS IN ('15', '26', '28') ), 0
                        ) AS QTDE_PEDPEND,
                        PA.DEPOSITO
                    FROM PA_ITEN_001 PA
                    WHERE 1 = 1
                    AND PA.LOTE = '000000'
                    AND PA.TIPO = '1'
                    AND PA.CODIGO LIKE '2427____'
                    AND PA.DEPOSITO = '2427'
                    AND PA.CODIGO in ({$listaProdutos})
                    GROUP BY PA.CODIGO, PA.COR, PA.TAM, PA.TIPO, PA.DEPOSITO, PA.LOTE
                    ORDER BY PA.CODIGO, PA.COR 
                ) T
                WHERE 1 = 1
                GROUP BY T.CODIGO, T.TAM
                ORDER BY T.TAM
            ");
            $info->execute();
            $estoqueTotal = $info->fetchAll(PDO::FETCH_ASSOC);
            return [
                'response' => $estoqueTotal,
                'status' => 200,
            ];
        } catch (Exception $e) {
            return [
                'response' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }

    protected function getEstoqueProduto ($produto) {
        try {
            $listaProdutos = $produto != null ? $this->queryProdutoUri($produto) : '0';
            $info = parent::getConn()->prepare("
                SELECT T.CODIGO,
                T.COD_INTEGRA,
                CAST(SUM(T.QUANTIDADE - T.QTDE_PEDPEND) AS INTEGER) AS QTDE_LIQ,
                CAST(SUM(T.QUANTIDADE) AS INTEGER) AS QTDE_BRUTA
                FROM (
                SELECT fv.CODIGO, fv.COD_INTEGRA, EST.TIPO, EST.COR, EST.TAM, SUM(EST.QUANTIDADE) QUANTIDADE, EST.LOTE, 
                COALESCE((SELECT UDF_NVL(SUM(PED_ITEN.QTDE)) QTDE 
                                    FROM PED_ITEN_001 PED_ITEN 
                                    LEFT JOIN PEDIDO_001 PEDIDO ON PEDIDO.NUMERO = PED_ITEN.NUMERO 
                                    WHERE PED_ITEN.CODIGO = EST.CODIGO AND PED_ITEN.COR = EST.COR AND PED_ITEN.TAM = EST.TAM AND PED_ITEN.QUALIDADE = EST.TIPO AND PED_ITEN.DEPOSITO = EST.DEPOSITO AND PEDIDO.STATUS IN ('15', '26', '28') ), 0
                                ) AS QTDE_PEDPEND,
                EST.DEPOSITO
                FROM FSIS_VINCULO fv
                INNER JOIN PA_ITEN_001 est ON est.CODIGO = fv.CODIGO AND est.tipo = '1' AND est.DEPOSITO = '2427'
                WHERE FV.TABELA = 'PRODUTO'
                AND FV.CODIGO in ({$listaProdutos})
                GROUP BY fv.CODIGO, fv.COD_INTEGRA, est.CODIGO, EST.TIPO, EST.COR, EST.TAM, EST.LOTE,QTDE_PEDPEND,EST.DEPOSITO
                ) T
                WHERE 1 = 1
                GROUP BY T.CODIGO, T.COD_INTEGRA
            ");
            $info->execute();
            $estoqueTotal = $info->fetchAll(PDO::FETCH_ASSOC);
            return [
                'response' => $estoqueTotal,
                'status' => 200,
            ];
        } catch (Exception $e) {
            return [
                'response' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }

    private static function queryProdutoUri(string $produtos): string {
        $pilhaProdutos = explode(",", (explode("=", $produtos)[1]));
        $listaProdutos = '';
        foreach ($pilhaProdutos as $key => $produto) {
            $produtoTratado = trim($produto);
            if (($key+1) == count($pilhaProdutos)) {
                $listaProdutos .= "'{$produtoTratado}'";
            } else {
                $listaProdutos .= "'{$produtoTratado}',";
            }
        }
        return $listaProdutos;
    }

}