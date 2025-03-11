<?php

require_once __DIR__ . "/../config/init.php";

class Almoxarifado extends DB {
    public function __construct() {
        parent::__construct();
    }

    public function get($path) {
        try {
            $funcao = $path[0];
            $queryString = $path[1];
            $infoQueryDivida = explode('&',$queryString);

            $dataInicial = '';
            $dataFinal = '';
            $deposito = '';
            $subGrupo = '';
            $posicaoEstoque = 0;

            $mapa = [
                'dataInicial' => &$dataInicial,
                'dataFinal' => &$dataFinal,
                'deposito' => &$deposito,
                'subGrupo' => &$subGrupo,
                'posicaoEstoque' => &$posicaoEstoque,
            ];

            foreach ($infoQueryDivida as $value) {
                $pilhaInfo = explode("=",$value);
                if (isset($pilhaInfo[0], $pilhaInfo[1]) && isset($mapa[$pilhaInfo[0]])) {
                    $mapa[$pilhaInfo[0]] = $pilhaInfo[1];
                }
            }

            switch($funcao) {
                case 'getInventario':
                    return $this->{$funcao}($dataInicial, $dataFinal, $deposito, $subGrupo, $posicaoEstoque);
                default:
                    return [
                        'status' => 404,
                        'messagem' => 'Não foi encontrado a rota ' . $path,
                    ];
            }
        } catch (Exception $e) {
            return [
                "status" => 500,
                "data" => "Error: " . $e->getMessage(),
            ];
        }
    }

    public function put(array $path): array {
        try {
            $funcao = $path[0];
            $queryString = $path[1];
            $infoQueryDivida = explode('&',$queryString);

            $qtde = 0;
            $codigo = '';
            $cor = '';
            $deposito = '';
            $lote = '';

            $mapa = [
                'qtde' => &$qtde,
                'codigo' => &$codigo,
                'cor' => &$cor,
                'deposito' => &$deposito,
                'lote' => &$lote,
            ];

            foreach ($infoQueryDivida as $value) {
                $pilhaInfo = explode("=",$value);
                if (isset($pilhaInfo[0], $pilhaInfo[1]) && isset($mapa[$pilhaInfo[0]])) {
                        $mapa[$pilhaInfo[0]] = $pilhaInfo[1];
                }
            }

            switch($funcao) {
                case 'updateAtualizaEstoque':
                    return $this->{$funcao}($qtde, $codigo, $cor, $deposito, $lote);
                default:
                    return [
                        'status' => 404,
                        'messagem' => 'Não foi encontrado a rota ' . $path,
                    ];
            }
        } catch (Exception $e) {
            return [
                "status" => 500,
                "data" => "Error: " . $e->getMessage(),
            ];
        }
    }

    protected function getInventario(string $dataInicial, string $dataFinal, string $deposito, string $subGrupo, int $posicaoEstoque): array {
        if ($dataInicial == '' || $dataFinal == '' || $deposito == '') {
            return [
                'response' => "Não é possivel buscar as informações de movimentação de estoque caso a data inicial e/ou final estejam vazios e o deposito estiver sem um valor atribuido, Data inicial: {$dataInicial} - Data final: {$dataFinal} - Deposito: {$deposito}, confira!",
                'status' => 404,
            ];
        }
        try {   
            // valida sub-grupo material
            if ($subGrupo != '') {
                $filtroGrupo = explode(",", $subGrupo);
                $ajusteFiltro = "'" . implode("','", $filtroGrupo) . "'";
                $condicaoGrupo = "AND MM.DEPOSITO IN ({$ajusteFiltro})";
            } else {
                $condicaoGrupo = "";
            }
            // valida deposito
            if ($deposito != '') {
                $filtroDeposito = explode(",", $deposito);
                $ajusteFiltro = "'" . implode("','", $filtroDeposito) . "'";
                $condicaoDeposito = "MM.DEPOSITO IN ({$ajusteFiltro})";
            }
            // valida posicao do estoque
            if ($posicaoEstoque == 1) {
                $condicaoPosicaoEstoque = "HAVING SUM(MI.QTDE) > 0";
            } else if ($posicaoEstoque == 2) {
                $condicaoPosicaoEstoque = "HAVING SUM(MI.QTDE) < 0";
            } else {
                $condicaoPosicaoEstoque = '';
            }
           
            $inventario = parent::getConn()->prepare("
                SELECT mm.CODIGO, M.DESCRICAO, m.SUB_GRUPO, M.LOCAL, mm.COR, mm.DEPOSITO, 
                SUM(IIF(mm.OPERACAO = 'E', mm.QTDE, mm.QTDE * -1)) AS QTDE_TOTAL_MOVIMENTADO, 
                mm.PRECO, MM.LOTE, ROW_NUMBER() OVER (ORDER BY SUM(IIF(mm.OPERACAO = 'E', mm.QTDE, mm.QTDE * -1)) DESC) AS RANKING, SUM(MI.QTDE) ESTOQUE_REAL, COUNT(*) OVER () AS TOTAL_MATERIALS,
                CASE WHEN ROW_NUMBER() OVER (ORDER BY SUM(IIF(mm.OPERACAO = 'E', mm.QTDE, mm.QTDE * -1)) DESC) <= (COUNT(*) OVER () * 0.1) THEN 'A'
                    WHEN ROW_NUMBER() OVER (ORDER BY SUM(IIF(mm.OPERACAO = 'E', mm.QTDE, mm.QTDE * -1)) DESC) <= (COUNT(*) OVER () * 0.3) THEN 'B'
                    ELSE 'C'
                END AS CATEGORIA
                FROM MAT_MOV_001 mm
                INNER JOIN MATERIAL_001 m ON M.CODIGO = MM.CODIGO
                INNER JOIN MAT_ITEN_001 mi ON MI.CODIGO = MM.CODIGO AND MI.COR = MM.COR AND MI.DEPOSITO = MM.DEPOSITO AND MI.LOTE = MM.LOTE 
                WHERE {$condicaoDeposito} 
                AND mm.DT_MOVTO BETWEEN '{$dataInicial}' AND '{$dataFinal}' 
                AND MM.TP_MOV <> 'TRANSF. DEPOSITO'
                {$condicaoGrupo}
                GROUP BY mm.CODIGO, mm.COR, mm.DEPOSITO, mm.PRECO,MM.LOTE,M.DESCRICAO,M.LOCAL, M.SUB_GRUPO
                {$condicaoPosicaoEstoque}
                ORDER BY QTDE_TOTAL_MOVIMENTADO DESC;
            ");
            $inventario->execute();
            $retorno = $inventario->fetchAll(PDO::FETCH_ASSOC);
            return [
                'response' => $retorno,
                'status' => 200,
            ];
        } catch (Exception $e) {
            return [
                "status" => 500,
                "data" => "Error: " . $e->getMessage(),
            ];
        }
    }

    protected function updateAtualizaEstoque(int $qtde, string $codigo = '', string $cor = '', string $deposito = '', string $lote = '', string $user = '') {
        if ($codigo == '' || $cor == '' || $deposito == '' || $lote == '' || $user = '') {
            return [
                'response' => "Não é possivel atualizar o Material: {$codigo} - Cor: {$cor} - Deposito: {$deposito} - Lote: {$lote} - Usuario: {$user}, pois algum campo está vazio, confira!",
                'status' => 404,
            ];
        }
        try {
            $materialEncontrado = parent::getConn()->prepare("SELECT CODIGO, COR, DEPOSITO, LOTE,QTDE FROM mat_iten_001 m WHERE CODIGO = :codigo AND COR = :cor AND DEPOSITO = :deposito AND LOTE = :lote");

            $materialEncontrado->bindParam(':codigo', $codigo);
            $materialEncontrado->bindParam(':cor', $cor);
            $materialEncontrado->bindParam(':deposito', $deposito);
            $materialEncontrado->bindParam(':lote', $lote);

            $materialEncontrado->execute();

            $infoMaterialEncontrado = $materialEncontrado->fetchAll(PDO::FETCH_ASSOC);
            
            if (isset($infoMaterialEncontrado) && count($infoMaterialEncontrado) > 0) {
                $info = parent::getConn()->prepare("UPDATE mat_iten_001 SET QTDE = {$qtde} WHERE CODIGO = :codigo AND COR = :cor AND DEPOSITO = :deposito AND LOTE = :lote");

                $info->bindParam(':codigo', $codigo);
                $info->bindParam(':cor', $cor);
                $info->bindParam(':deposito', $deposito);
                $info->bindParam(':lote', $lote);

                $info->execute();

                if ($info->rowCount() > 0) {
                    $qtdeMaterialAtualizado = $infoMaterialEncontrado[0]['QTDE'];
                    parent::logSisplanConn("1","Atualizado o Material: {$codigo} - Cor: {$cor} - Deposito: {$deposito} - Lote: {$lote}, qtde original: {$qtdeMaterialAtualizado} para {$qtde}", $user);
                    return [
                        'status' => 200,
                        'response' => 'Atualizado com sucesso!',
                    ];
                } else {
                    return [
                        'status' => 404,
                        'response' => "Não foi possivel atualizar o material, por motivo de {$info}",
                    ];
                }
            } else {
                return [
                    'status' => 404,
                    'response' => "Não foi possivel atualizar o material, pois não foi encontrado!",
                ];
            }
        } catch (Exception $e) {
            return [
                "status" => 500,
                "data" => "Error: " . $e->getMessage(),
            ];
        }
    }
}