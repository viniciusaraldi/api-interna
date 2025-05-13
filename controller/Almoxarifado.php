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
            $qualidade = '';
            $operacao = '';
            $qtdeMatMov = '';
            $user = '';

            $mapa = [
                'qtde' => &$qtde,
                'codigo' => &$codigo,
                'cor' => &$cor,
                'deposito' => &$deposito,
                'lote' => &$lote,
                'qualidade' => &$qualidade,
                'operacao' => &$operacao,
                'qtdeMatMov' => &$qtdeMatMov,
                'user' => &$user,
            ];

            foreach ($infoQueryDivida as $value) {
                $pilhaInfo = explode("=",$value);
                if (isset($pilhaInfo[0], $pilhaInfo[1]) && isset($mapa[$pilhaInfo[0]])) {
                        $mapa[$pilhaInfo[0]] = $pilhaInfo[1];
                }
            }

            switch($funcao) {
                case 'updateAtualizaEstoque':
                    return $this->{$funcao}($qtde, $codigo, $cor, $deposito, $lote, $qualidade, $operacao,  $qtdeMatMov, $user);
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

    protected function getInventario(string $dataInicial, string $dataFinal, string $deposito, string $subGrupo, int $posicaoEstoque, int $qualidade = 1): array {
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
            // valida qualidade
            $queryQualidade = "AND MI.QUALIDADE = $qualidade";
           
            $inventario = parent::getConn()->prepare("
                SELECT mm.CODIGO, M.DESCRICAO, m.SUB_GRUPO, M.LOCAL, mm.COR, mm.DEPOSITO, mi.QUALIDADE,
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
                {$queryQualidade}
                AND mm.DT_MOVTO BETWEEN '{$dataInicial}' AND '{$dataFinal}' 
                AND MM.TP_MOV <> 'TRANSF. DEPOSITO'
                {$condicaoGrupo}
                GROUP BY mm.CODIGO, mm.COR, mm.DEPOSITO, mi.QUALIDADE,mm.PRECO,MM.LOTE,M.DESCRICAO,M.LOCAL, M.SUB_GRUPO
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

    protected function updateAtualizaEstoque(float $qtde, string $codigo = '', string $cor = '', string $deposito = '', string $lote = '', int $qualidade = 0, string $operacao = '', float $qtdeMatMov = 0, string $user = '') {
        if ($codigo == '' || $cor == '' || $deposito == '' || $lote == '' || $qualidade == 0 || $operacao == '0' || $operacao == '' || $qtdeMatMov == 0 || $user == '') {
            return [
                'response' => "Não é possivel atualizar o Material: {$codigo} - Cor: {$cor} - Deposito: {$deposito} - Lote: {$lote} - Qualidade: {$qualidade} - Operação: {$operacao} - Movimentado: {$qtdeMatMov} - Usuario: {$user}, pois algum campo está vazio, confira!",
                'status' => 404,
            ];
        }
        
        try {
            $materialEncontrado = parent::getConn()->prepare("SELECT CODIGO, COR, DEPOSITO, LOTE,QTDE FROM mat_iten_001 m WHERE CODIGO = :codigo AND COR = :cor AND DEPOSITO = :deposito AND LOTE = :lote AND QUALIDADE = :qualidade");

            $materialEncontrado->bindParam(':codigo', $codigo);
            $materialEncontrado->bindParam(':cor', $cor);
            $materialEncontrado->bindParam(':deposito', $deposito);
            $materialEncontrado->bindParam(':lote', $lote);
            $materialEncontrado->bindParam(':qualidade', $qualidade);

            $materialEncontrado->execute();

            $infoMaterialEncontrado = $materialEncontrado->fetchAll(PDO::FETCH_ASSOC);
            
            if (isset($infoMaterialEncontrado) && count($infoMaterialEncontrado) > 0) {
                $responseMovMat = $this->insertMovimentacaoMaterial(
                    date("Y-m-d"),
                    $codigo,
                    $cor,
                    $deposito,
                    $operacao,
                    "Feito: $user",
                    'Mov. por Ger. de Inventario',
                    $lote,
                    'Mov. por Ger. de Inventario',
                    $qualidade,
                    $qtde,
                    $user
                );

                if (!is_int($responseMovMat) && $responseMovMat <= 0) {
                    return [
                        'status' => 400,
                        'message' => $responseMovMat,
                    ];
                }

                $info = parent::getConn()->prepare("UPDATE mat_iten_001 SET QTDE = {$qtde} WHERE CODIGO = :codigo AND COR = :cor AND DEPOSITO = :deposito AND LOTE = :lote AND QUALIDADE = :qualidade");

                $info->bindParam(':codigo', $codigo);
                $info->bindParam(':cor', $cor);
                $info->bindParam(':deposito', $deposito);
                $info->bindParam(':lote', $lote);
                $info->bindParam(':qualidade', $qualidade);

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

    private function insertMovimentacaoMaterial($dt_movto, $codigo, $cor, $deposito, $operacao, $descricao, $tp_mov, $lote, $obs, $qualidade, $qtde, $user) {
        try {
            $stmt = parent::getConn()->prepare("
                INSERT INTO MAT_MOV_001 (DT_MOVTO, CODIGO, COR, DEPOSITO, OPERACAO, DESCRICAO, TP_MOV, LOTE, OBS, QUALIDADE, QTDE) VALUES 
                (:dt_movto, :codigo, :cor, :deposito, :operacao, :descricao, :tp_mov, :lote, :obs, :qualidade, :qtde);
            ");
            $descricao = "Feito: $user";
    
            $stmt->bindParam('dt_movto', $dt_movto);
            $stmt->bindValue(':codigo', $codigo);
            $stmt->bindValue(':cor', $cor);
            $stmt->bindValue(':deposito', $deposito);
            $stmt->bindValue(':operacao', $operacao);
            $stmt->bindValue(':descricao', $descricao);
            $stmt->bindValue(':tp_mov', 'Mov. por Ger. de Inventario');
            $stmt->bindValue(':lote', $lote);
            $stmt->bindValue(':obs', 'Mov. por Ger. de Inventario');
            $stmt->bindValue(':qualidade', $qualidade);
            $stmt->bindValue(':qtde', $qtde);
        
            $stmt->execute();
    
            $rowsExecuted = $stmt->rowCount();
            
            return $rowsExecuted;

        } catch (PDOException $e) {
            return $e->getMessage();
        }

    }
}