<?php

require_once __DIR__ . "/../config/init.php";

$req = function () {
    try {
        $url = 'https://bonfitness.com.br/api/v1/auth/token';
        $init = curl_init($url);
        curl_setopt_array(
            $init,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => [
                    "Content-Type" => "application/x-www-form-urlencoded",
                ],
                CURLOPT_POSTFIELDS => [
                    'client_id' => 'Teste_48e494e4da0f8',
                    'client_secret' => '85a2a4b84f373def914e694034eac463e7f68c40',
                    'grant_type' => 'client_credentials',
                ]
            ],
        );
        $dados = json_decode(curl_exec($init), true);
        curl_close($init);
        return $dados['token_type'] . " " . $dados['access_token'] ;
    } catch (Exception $e) {
        return $e->getMessage();
    }
};

function getSaque($tokens) {
    try {
        //data_pedido_maior_igual=2024-02-01&data_pedido_menor_igual=2024-02-28
        $dataCurrent = date("Y-m-d");
        // $url = 'https://bonfitness.com.br/api/v1/solicitacoes-saque?status_id=1&data_pedido='.$dataCurrent;
        $url = 'https://bonfitness.com.br/api/v1/solicitacoes-saque?status_id=1&data_pedido_maior_igual=2024-02-01&data_pedido_menor_igual=2024-02-28';
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
            ],
        );
        if (curl_exec($init) == false) {
            return [
                'status' => 401,
                'message' => 'Erro' . curl_error($init),
            ];
        }
        $dados = json_decode(curl_exec($init), true);
        curl_close($init);
        return [
            'status' => 200,
            'message' => $dados,
        ];
    } catch (Exception $e) {
        return [
            'status' => 500,
            'message' => $e->getMessage(),
        ];
    }
}

function logError($message, $dados = null) {
    $errorFile = __DIR__ . '/../errors/automation/SolicitacaoSaqueBon/error-' . date('Y-m-d') . '.log';
    $file = fopen($errorFile, 'a+');
    fwrite($file, "Erro: $message\n");
    if ($dados) {
        fwrite($file, "Dados: " . json_encode($dados, JSON_UNESCAPED_UNICODE) . "\n");
    }
    fclose($file);
}

class SolicitacaoSaqueBon extends DB {
    public function __construct() {
        parent::__construct();
    }

    public function setSaque($dados) {
        try {
            if ($dados['status'] == 500 || $dados['status'] == 401 || !isset($dados['message']['solicitacoes_saque_transacoes'])) {
                logError("Erro na API ou dados ausentes", $dados);
            } else {
                $db = parent::getConn();
                $solicitacoes = $dados['message']['solicitacoes_saque_transacoes'];
                foreach ($solicitacoes as $value) {
                    $user = $value['distribuidor_id'];
                    $operacao = $value['operacao'];
                    $id_sol = $value['id'];
                    $solicitado = $value['valor_solicitado'];                
                    $taxa = $value['total_taxas'];
                    $valor = $value['valor_a_depositar'];                
                    $banco = $value['banco'];
                    $tipo_conta = $value['tipo_conta'];                
                    $agencia = $value['agencia'];
                    $numero_conta = $value['numero'];
                    $data_sol = $value['data_pedido'];
                    $data_ven = date('Y-m-20', strtotime($data_sol));
    
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
                      DATA_VEN
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
                        '$data_ven'
                    )");
    
                    $setDB->execute();
    
                    if (!$setDB) {
                        logError("Erro ao inserir dados no banco", $value);
                    }
                }
            }
        } catch (Exception $e) {
            return [
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }


}

$db = new SolicitacaoSaqueBon();
$db->setSaque(getSaque($req()));