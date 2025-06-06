<?php

require_once __DIR__ . "/../config/init.php";
require_once __DIR__ . "/../http/Response.php";

$req = require_once __DIR__ . "/../config/tks.php";

class SolicitacaoSaqueBon extends DB {
    public function __construct() {
        parent::__construct();
    }

    private static function logError($message, $dados = null) {
        $errorFile = __DIR__ . '/../errors/automation/SolicitacaoSaqueBon/error-' . date('Y-m-d') . '.log';
        $file = fopen($errorFile, 'a+');
        fwrite($file, "Erro: $message\n");
        if ($dados) {
            fwrite($file, "Dados: " . json_encode($dados, JSON_UNESCAPED_UNICODE) . "\n");
        }
        fclose($file);
    }

    public function setSaque($dados) {
        try {
            if ($dados['status'] != 200 || !isset($dados['message'])) {
                SolicitacaoSaqueBon::logError("Erro na API ou dados ausentes", $dados);
                return 'Erro na Api ou dados ausentes';
            } 
            
            $db = parent::getConn();
            $solicitacoes = $dados['message'];
            
            foreach ($solicitacoes as $value) {
                if ($value['status_id'] != '1') {
                    SolicitacaoSaqueBon::logError('status diferente de pendente!', $value);
                } 

                $user = $value['distribuidor_id'];
                $operacao = $value['operacao'];
                $id_sol = $value['id'];
                $solicitado = $value['valor_solicitado'];                
                $taxa = $value['total_taxas'];
                $valor = $value['valor_a_depositar'];                
                $banco = $value['banco'];
                $tipo_conta = $value['tipo_conta'];                
                $agencia = $value['conta_bancaria']['tipo_chave_pix'] !== null 
                    ? $value['conta_bancaria']['tipo_chave_pix']
                    : trim($value['agencia']);

                $numero_conta = $value['conta_bancaria']['tipo_chave_pix'] !== null
                    ? $value['conta_bancaria']['chave_pix'] 
                    : $value['numero'];

                $data_sol = $value['data_pedido'];
                $data_ven = date('Y-m-20', strtotime($data_sol));

                $nome = $value['conta_bancaria']['nome'];
                $cpf_cnpj = $value['conta_bancaria']['cnpj'] == null ? $value['conta_bancaria']['cpf'] : $value['conta_bancaria']['cnpj'];
                
                $setDB = $db->prepare("INSERT INTO TABF06 (
                    ID_USER,
                    OPERACAO,
                    ID_SOL,
                    SOLICITADO,
                    TAXA,
                    VALOR,
                    BANCO,
                    TIPO_CONTA,
                    AGENCIA,
                    NUMERO_CONTA,
                    DATA_SOL,
                    DATA_VEN,
                    NOME,
                    CPF_CNPJ
                ) VALUES (
                    '$user',
                    '$operacao',
                    $id_sol,
                    $solicitado,
                    $taxa,
                    $valor,
                    '$banco',
                    '$tipo_conta',
                    '$agencia',
                    '$numero_conta',
                    '$data_sol',
                    '$data_ven',
                    '$nome',
                    '$cpf_cnpj'
                )");
                    
                $setDB->execute();
                
            }
        } catch (Exception $e) {
            return [
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    public static function getSaque($tokens) {
        try {
            //data_pedido_maior_igual=2024-02-01&data_pedido_menor_igual=2024-02-28
            $dateFirst = date("Y-m-01");
            $dateLast = date("Y-m-t");
            // $url = 'https://bonfitness.com.br/api/v1/solicitacoes-saque?status_id=1&data_pedido='.$dataCurrent;
            $url = "https://bonfitness.com.br/api/v1/solicitacoes-saque?data_pedido__maior_igual={$dateFirst}&data_pedido__menor_igual={$dateLast}";
            $init = curl_init();
            curl_setopt_array(
                $init,
                [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => [
                        "Content-type: application/json",
                        "Authorization: $tokens",
                    ],
                    CURLOPT_ENCODING => 'UTF-8',
                ],
            );

            $dados = json_decode(curl_exec($init), true);
            curl_close($init);

            for ($i=0; $i < count($dados['solicitacoes_saque_transacoes']); $i++) { 
                $solicitacoes = $dados['solicitacoes_saque_transacoes'][$i];
                $dados['solicitacoes_saque_transacoes'][$i]['conta_bancaria'] = SolicitacaoSaqueBon::getContaBancariasDistribuidor($solicitacoes['distribuidor_id'], $tokens);
            }

            return [
                'status' => 200,
                'message' => $dados['solicitacoes_saque_transacoes'],
            ];
        } catch (Exception $e) {
            return [
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    private static function getContaBancariasDistribuidor ($id, $tokens) {
        $url = 'https://bonfitness.com.br/api/v1/distribuidor-conta-bancaria?distribuidor=' . $id;
        $init = curl_init();
        curl_setopt_array(
            $init,
            [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    "Content-type: application/json",
                    "Authorization: $tokens",
                ],
                CURLOPT_ENCODING => 'UTF-8',
            ],
        );

        $dados = json_decode(curl_exec($init), true);
        $idContaBancaria = 0;
        $keyArray = 0;

        if (count($dados['distribuidor_conta_bancaria']) > 1) {
            for ($i=0; $i < count($dados['distribuidor_conta_bancaria']); $i++) { 
                if ($dados['distribuidor_conta_bancaria'][$i]['id'] > $idContaBancaria) {
                    $idContaBancaria = strval($dados['distribuidor_conta_bancaria'][$i]['id']);
                    $keyArray = $i;
                }
            }
        }

        return $dados['distribuidor_conta_bancaria'][$keyArray];
    }


}

$db = new SolicitacaoSaqueBon();
$dados = $db::getSaque($req());
$saques = $db->setSaque($dados);