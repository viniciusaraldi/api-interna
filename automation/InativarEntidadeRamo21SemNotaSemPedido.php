<?php

require_once __DIR__ . "/../config/init.php";

// A ideia foi solicitada pela Rebeca/Michele do Comercial, Chamado #10607
// Filtrar clientes com o ramo de atividade 21 que não tiveram nenhuma nota entre 6 meses e não possuem nenhum pedido pendente.
// Aos que entrarem na lógica, desativar e alterar a data de próxima analise para a data que foi atualizado.

class InativarEntidadeRamo21SemNotaSemPedido extends DB {
    public function __construct() {
        parent::__construct();
        $datas = $this->conn(self::getCodcli());
        foreach ($datas as $data) {
            $this->conn(self::atualizaDataPrevAnaliseEntidade($data['CODCLI']));
            $this->conn(self::setHistorico($data['CODCLI']));

        }
    }
    function logError($message, $dados = null) {
        $errorFile = __DIR__ . '/../errors/automation/InativarEntidadeRamo21SemNotaSemPedido/error-' . date('Y-m-d') . '.log';
        $file = fopen($errorFile, 'a+');
        fwrite($file, "Erro: $message\n");
        if ($dados) {
            fwrite($file, "Dados: " . json_encode($dados, JSON_UNESCAPED_UNICODE) . "\n");
        }
        fclose($file);
    }
    private static function getCodcli () {
        return "SELECT DISTINCT e.codcli, e.nome FROM ENTIDADE_001 e
            WHERE e.SIT_CLI = '21'
            AND E.ATIVO = 'S'
            AND e.CODCLI NOT IN (SELECT DISTINCT n.codcli FROM NOTA_002 n WHERE n.DT_EMISSAO BETWEEN (CURRENT_DATE - (30*6)) AND CURRENT_DATE)
            and e.codcli not in (SELECT DISTINCT p.CODCLI FROM PEDIDO_001 p INNER JOIN PED_ITEN_001 pi2 ON pi2.NUMERO = p.NUMERO WHERE pi2.qtde > 0 AND p.nota is NULL)
        ";
    }
    private static function atualizaDataPrevAnaliseEntidade($id) {
        $dataCorrente = date("Y-m-d");
        return "UPDATE ENTIDADE_001 SET dt_prev_analise = '$dataCorrente', ativo = 'N' where codcli = '$id'";
    }
    private static function setHistorico ($id) {
        $dataCorrente = date("Y-m-d");
        return "INSERT INTO ENTID_OBS_001 (NUMERO, CODCLI, DATA, CERTIFICADO, USUARIO, OBSERVACAO) VALUES (0,'$id' ,'$dataCorrente' ,0,'AUTOMACAO', 'CLIENTE DESATIVADO POR CONTA DE NÃO TER NENHUM NOTA FATURADO DURANTE SEIS MESES E NÃO POSSUIR NENHUM PEDIDO PENDENTE')";
    }
    private function conn($sql) {
        try {
            $stmt = $this->getConn()->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            var_dump($data);
            return $data;
        } catch (PDOException $e) {
            $this->logError('Erro no sql ou conexão com Banco de dados', $e->getMessage());
        } catch (Exception $err) {
            $this->logError('Erro no servidor ou com programa com erro', $err->getMessage());
        }
    }
}


new InativarEntidadeRamo21SemNotaSemPedido();