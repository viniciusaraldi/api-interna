<?php

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . "/../http/Response.php";

class Financeiro2 extends DB
{
    private $req;
    private $tkb;

    public function __construct() {
        parent::__construct();
        $this->req = require __DIR__ . "/../config/tks.php";
        $this->tkb = $this->tkBAbstract;
    }

    private function token() {
        return ($this->req)();
    }

    public function post($path) {
        try {
            $funcao = $path[0];
            $queryString = $path[2];
            switch($funcao) {
                case 'automacao':
                    return $this->{$funcao}($queryString);
                default:
                    return Response::json([
                       'mensagem' => 'Não foi encontrado a rota ' . $path[0],
                    ], 404);
            }
        } catch (Exception $e) {
            return Response::json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function automacao($data) {
        $data = isset($data['data']) ? $data['data'] : null;
        
        if (! $data) {
            return Response::json([
                'mensagem' => 'Data não informada',
            ]);
        }

        $pedidos = $this->pedidosSisplan($data);
        $infoPedidos = $this->infoPedidos($pedidos);
        $duplicata = $this->duplicata($infoPedidos);

        return Response::json($duplicata, $duplicata['status']);
        
    }

    private function duplicata($infos) {
        try {

            $pedidosManuais = [];
            $pedidosSucesso = [];
    
            foreach ($infos as $value) {
                $cliente = $value['cliente'];
                $qtdeParcelas = $value['quantidade_vezes'] ?? null;
                $nsu = $value['nsu'] ?? null;
                $fatura = $value['numero_nota'];
                $duplicata = $value['duplicata'];
                $pedido = $value['pedido_sisplan'];
                $parcelas = $value['pagamentos'] ?? null;
    
                if ($qtdeParcelas && $nsu && $parcelas) {
                    // Busca o último número para cadastro de numero e acrescenta mais um;
                    $numeroReceberCartao = $this->ultimoNumeroGeradoCartaoReceber();

                    if (!$numeroReceberCartao['success']) {
                        return $numeroReceberCartao;
                    }

                    for ($num = 0; $num < $qtdeParcelas; $num++) {
                        $dataVencimentoDuplicata = (new DateTime($parcelas[$num]['credit_date']))->format('Y-m-d');
        
                        $lancamento = $this->sequenciaLancamento();      
        
                        // Inserindo Duplicata de cartão no receber
                        $this->receberCartao(
                            $cliente,
                            $nsu,
                            $dataVencimentoDuplicata,
                            $fatura,
                            "0" . $numeroReceberCartao['proximo'] . "C/" . ($num + 1),
                            'Baixou da duplicata: ' . $duplicata,
                            $num + 1,
                            $pedido,
                            $parcelas[$num]['value'],
                            $parcelas[$num]['value'],
                            0,
                            $parcelas[$num]['value'],
                            $duplicata,
                            $lancamento
                        );
                    } 
                    $pedidosSucesso[] = $pedido;
                }

                // Pedidos que não tem payments em Belluno ou que estão pagos em outra forma de pagamentos em vez de card
                $pedidosManuais[] = isset($value['pedido_manual_erro']) ? $value['pedido_manual_erro'] : null;     
            }
    
            return [
                'status' => 201,
                'pedidos_manuais' => $pedidosManuais,
                'pedidos_sucessos' => $pedidosSucesso,
                'message' => 'Programa Finalizado',
            ];
        } catch (Exception $e) {
            return [
                'status' => 500,
                'pedidos_manuais' => $pedidosManuais,
                'pedidos_sucessos' => $pedidosSucesso,
                'message' => 'Programa Finalizado',
            ];
        }
}

    private function receberCartao($cliente, $cartaoNSU, $dataVencimento, $fatura, $numero, $obs, $parcela, $pedido, $valorOriginal, $valorDuplicata, $valorPago, $valor2, $duplicata, $lancamento) {
        $dataCorrente = date("Y-m-d");
        $sql = "INSERT INTO RECEBER_001 (SITUACAO,ALIQUOTA, DESC_PROGRAMADO, DESPESAS, dt_comrec, frete, BANCO, BANCO_CH, BORDERO, CARTAO_NSU, CLASSE, CODCLI, CODCLI_ORIG, com1, com2, com3, com4, CONTA_CHEQUE, DT_DIGITA, DT_EMISSAO, DT_ENVIO, DT_PREVISAO, DT_STATUS, DT_VENCTO, ORIGINAL, EMP_ID, fatura, HISTORICO, moeda, numero, obs, parcela, pedido, STATUS, VAL_ORIGIN, VALOR, VALOR_PAGO, valor2, juros, desconto, val_dev, lancamento) VALUES ('',0, 0, 0, '$dataCorrente', 0, '912', '912', '0', '$cartaoNSU', '1001', '467602', '467602', 0, 0, 0, 0, '1', '$dataCorrente', '$dataCorrente', '1899-12-30', '$dataCorrente', '1899-12-30', '$dataVencimento', '$dataCorrente', 3, '$fatura', '0001', '15', '$numero', '$obs', '$parcela', '$pedido', 'DUPL', $valorOriginal, $valorDuplicata, $valorPago, $valor2, 0, 0, 0,$lancamento);";
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                // Conforme for criando a duplicata de cartão no receber, vai jogando a baixa no receber da duplicata do cliente
                $this->contabil($cliente, $dataCorrente, $lancamento, $duplicata, $valor2, $fatura);
                $this->baixaDuplCliente($numero, $duplicata, $valor2,  4041.59, $lancamento);
            }
        } catch (Exception $e) {
            parent::logSisplanConn('3','receberCartao -> ' . $e->getMessage() . " - Linha: " . $e->getLine(), 'AUTO_INTERNO');
        }
    }
    private function baixaDuplCliente($numero, $duplicata, $valorParcela, $valorTotal, $lancamento) {
        $dataCorrente = date("Y-m-d");
        $dT = new DateTime();
        $dateTime = $dT->format("Y-m-d H:i:s.v");
        $obs = 'Baixou com Cartao Dup:' . $numero;

        $sql = "INSERT INTO RECEBERB_001 (classe, COMISSAO, CONTROLE, DESAGIO, desconto, DESCONTO2, DESCONTO3, DESP_COBRANCA, DOCTO, DT_COMISSAO, DT_CONT, dt_pagto, HISTORICO, juros, LANCAMENTO, MOEDA, MOTIVO_DEV, NRCAIXA, NUMERO, obs, PORTADOR, QT_DEV, TAXA_MOEDA, TELA_BAIXA, VAL_DEV, VALOR_PAGO, VALOR2, VALPAGO_MOEDA, VAR_CAMBIAL, DATA_HORA) VALUES ('1001', 'S', '', 0, 0, 0, 0, 0, '', '$dataCorrente', '$dataCorrente', '$dataCorrente', '0001', 0, $lancamento, '15', '', '', '$duplicata', '$obs', '', 0, 0, 'AutomacaoBaixaCartaoBelluno', 0, $valorParcela, $valorTotal, 0, 0, '$dateTime');";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                // incrementa +1 no uúltimo lançamento;
                $this->setUltimoLancamento($lancamento);
                    
                // abate o valor da duplicata do cliente
                $this->abateReceberCliente($duplicata, $valorParcela);
            }
        } catch (Exception $e) {
             parent::logSisplanConn('3','baixaDuplCliente -> ' . $e->getMessage() . " - Linha: " . $e->getLine(), 'AUTO_INTERNO');
        }
    }
    private function abateReceberCliente($duplicata, $valorParcela) {
        try {
            $valorPagoReceberCliente = $this->valorPagoDuplCliente($duplicata);
            $valorPago = $valorPagoReceberCliente + $valorParcela;
            $sql = "UPDATE RECEBER_001 SET VALOR_PAGO = $valorPago WHERE NUMERO = '$duplicata' and emp_id = 3;";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return;
            }
            
            throw new Exception("Erro ao editar duplicata $duplicata com o valor da parcela: R$ {$valorParcela} e com o valor pago: R$ {$valorPago} ", 1);
        } catch (Exception $e) {
            parent::logSisplanConn('3','abateReceberCliente -> ' . $e->getMessage() . " - Linha: " . $e->getLine(), 'AUTO_INTERNO');
        }
    }
    private function valorPagoDuplCliente($numero) {
        $sql = "SELECT r.VALOR_PAGO FROM RECEBER_001 r WHERE NUMERO = '$numero' AND EMP_ID = 3";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $data[0]['valor_pago'];
    }
    private function setUltimoLancamento($lancamento) {
        $data = new DateTime();
        $dateTime = $data->format("Y-m-d H:i:s.v");

        $sql = "INSERT INTO lancamento (DATA, LANCAMENTO, TELA, USUARIO) VALUES ('$dateTime', $lancamento,'TfmBaixaReceberLote','AUTO_INTERNO')";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
    }
    private function contabil($cliente, $dataCorrente, $lancamento, $duplicata, $valorParcela, $fatura) {
        $contaCliente = $this->contaCreditoCliente($cliente);
        $nomeCliente = $this->nomeCliente($cliente);

        $sqlContabil = "INSERT INTO contabil_001 (codcli, conta_c, conta_d, data, data_lan, emp_id, lancamento, numero, observacao, operacao, ordem, tipo, valor, fatura, filial, particip_c, particip_d, docto) VALUES ('$cliente', '$contaCliente', '1605','$dataCorrente', '$dataCorrente', 3, $lancamento, '$duplicata','Nosso recebimento Dpl.NR.$duplicata de $nomeCliente', '-', 1, 'RR', $valorParcela, $fatura, 3, '$cliente', '$cliente', '$fatura');";
        
        $stmt = $this->conn->prepare($sqlContabil);
        $stmt->execute();
    }
    private function contaCreditoCliente($cli) {
        try {
            $sqlContaCliente = "SELECT con_cli FROM CONT_ENTIDADE_001 e where e.codcli = '$cli'";
            $stmt = $this->conn->prepare($sqlContaCliente);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $data[0]['con_cli'];
        } catch (Exception $e) {
            parent::logSisplanConn('3','contaCreditoCliente -> ' . $e->getMessage() . " - Linha: " . $e->getLine(), 'AUTO_INTERNO');
        }
    }
        private function nomeCliente($cli) {
        try {
            $sqlNome = "SELECT nome FROM entidade_001 e where e.codcli = '$cli'";
            $stmt = $this->conn->prepare($sqlNome);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $data[0]['nome'];
        } catch (Exception $e) {
            parent::logSisplanConn('3','nomeCliente -> ' . $e->getMessage() . " - Linha: " . $e->getLine(), 'AUTO_INTERNO');
        }
    }


    private function sequenciaLancamento() {
        $sql = "SELECT last_value FROM lanc_aux_001_sequencia_seq";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $genId = (int) $data[0]['last_value'];

        $lancAux = "INSERT INTO LANC_AUX_001 (SEQUENCIA) VALUES ($genId)";
        $stmtLancAux = $this->conn->prepare($lancAux);
        $stmtLancAux->execute();

        return $genId;
    }

    private function ultimoNumeroGeradoCartaoReceber() {
        try {
            $sql = "select proximo from codigos_001 where tabela='RECEBER' and campo='NUMERO'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $num = $stmt->fetch(PDO::FETCH_ASSOC);

            $proximoNumero = (int) $num['proximo'] + 1;

            $stmt1 = $this->conn->prepare("update codigos_001 set proximo = :proximo where tabela='RECEBER' and campo='NUMERO'");
            $stmt1->bindParam(':proximo', $proximoNumero, PDO::PARAM_INT);
            $stmt1->execute();

            return [
                'success' => true,
                'proximo' => (int) $num['proximo'],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'proximo' => $e->getMessage(),
            ];
        }
    }


    private function infoPedidos ($data) {
        $infos = [];

        // Busca todos os pedidos dentro da sisplan que tem o número art_cli(numero da portmontt) cadastrado
        array_walk($data, function ($e) use (&$infos) {
            if ($e['ped_cli'] != '') {
                // Busca todas as informações do pedido na portmontt
                $apiMaxnivel = $this->pedidoMaxnivel($e['ped_cli']);

                $pedido = $apiMaxnivel['pedidos'][0]['id'];
                $pedidoSisplan = $e['sisplan'];
                $cliente = $e['cliente'];
                $duplicata = $e['duplicata'];
                $nota = $e['fatura'];
                $valorDuplicata = (float) $e['valor'];
                $valorPedido = (float) $apiMaxnivel['pedidos'][0]['valor_total'];

                $belluno = $apiMaxnivel['pedidos'][0]['campos_personalizados'][1];

                if (!isset($belluno['chave']) && $belluno['chave'] !== 'json_belluno') {
                    // Se não encontrar a key "chave" e se o valor "chave" não for json_belluno
                    $infos[] = [
                        'cliente' => $cliente,
                        'pedido_sisplan' => $pedidoSisplan,
                        'pedido_maxnivel' => $pedido,
                        'duplicata' => $duplicata,
                        'numero_nota' => $nota,
                        'pedido_manual_erro' => "Retorno da Portomontt:<br>Erro ao exportar o JSON: Não há registros na Belluno<br>Sisplan: $pedidoSisplan<br>Maxnivel: $pedido<br>***************",
                    ];
                }

                if (isset($belluno['chave']) && $belluno['chave'] == 'json_belluno') {
                    $belluno = json_decode($belluno['valor'], true);
                    
                    foreach ($belluno as $value) {
                        $tid = $value['tid'];

                        $dadosPagamentoPedido = $this->pagamentoBelluno($tid); 

                        $qtdeParcelas = $dadosPagamentoPedido['transaction']['payments'][0]['installments_number'] ?? null;
                        $pagamentos = $dadosPagamentoPedido['transaction']['payments'][0]['payables'] ?? null;
                        $valorPagoTotalTid = $dadosPagamentoPedido['transaction']['cart'][0]['unit_value'] ?? null;
                        $tipoPagamento = $dadosPagamentoPedido['transaction']['payments'][0]['type'] ?? null;

                        // Se valor total da venda for maior que a duplicata OU tipo pagamento for diferente de cartão OU se não existir a quantidade de parcelas OU se não existir os dados de pagamento 
                        if ($valorPagoTotalTid > $valorDuplicata || $tipoPagamento != 'card' || !isset($qtdeParcelas) || !isset($pagamentos)) {
                            // Caso a belluno ainda não validar o pagamento, retorna avisando
                            $infos[] = [
                                'cliente' => $cliente,
                                'pedido_sisplan' => $pedidoSisplan,
                                'pedido_maxnivel' => $pedido,
                                'duplicata' => $duplicata,
                                'numero_nota' => $nota,
                                'pedido_manual_erro' => "Retorno da belluno:<br>Pedido Sisplan: $pedidoSisplan<br>Pedido Maxnivel: $pedido<br>Parcelas: $qtdeParcelas<br>Pagamentos: $pagamentos<br>Tipo de Pagamento: $tipoPagamento<br>Valor duplicata: $valorDuplicata<br>Valor Belluno: $valorPagoTotalTid<br>***************",
                            ];
                        }

                        $infos[] = [
                            'cliente' => $cliente,
                            'pedido_sisplan' => $pedidoSisplan,
                            'pedido_maxnivel' => $pedido,
                            'duplicata' => $duplicata,
                            'numero_nota' => $nota,
                            'nsu' => $value['nsu'],
                            'tid' => $tid,
                            'quantidade_vezes' => $qtdeParcelas,
                            'pagamentos' => $pagamentos,
                            'valor_total_cartao_tid' => $valorPagoTotalTid
                        ];
                    }
                }
            }
        });

        return $infos;
    }

    private function pedidosSisplan($data) {
        $sql = "SELECT RECEBER.CODCLI CLIENTE, PEDIDO.NUMERO SISPLAN, PEDIDO.ART_CLI ART_CLI, PEDIDO.PED_CLI PED_CLI, RECEBER.NUMERO DUPLICATA, NOTA.FATURA, RECEBER.VALOR FROM NOTA_001 NOTA
            INNER JOIN PEDIDO_001 PEDIDO ON NOTA.FATURA = PEDIDO.NOTA
            INNER JOIN RECEBER_001 RECEBER ON RECEBER.FATURA = NOTA.FATURA and RECEBER.EMP_ID = 3
            WHERE 1 = 1
            and NOTA.EMP_ID = 3
            AND PEDIDO.EMP_FAT = '003'
            AND RECEBER.DT_EMISSAO = cast('$data' as date)
            AND RECEBER.NUMERO NOT IN (SELECT DISTINCT BAIXA.NUMERO FROM RECEBERB_001 BAIXA WHERE BAIXA.TELA_BAIXA IN ('AutomacaoBaixaCartaoBelluno','TfmBaixaReceberLote'))
            --AND PEDIDO.NUMERO in ('62879', '62900','62920')
            and pedido.id_tipo in (500000022, 500000021, 500000020)
            AND RECEBER.NUMERO NOT LIKE '%C-3%'
            AND (RECEBER.VALOR - RECEBER.VALOR_PAGO) <> 0
            GROUP BY RECEBER.CODCLI, PEDIDO.NUMERO, PEDIDO.ART_CLI, PEDIDO.PED_CLI, RECEBER.NUMERO, NOTA.FATURA, RECEBER.VALOR
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $info = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $info;
    }

    private function pedidoMaxnivel($numero) {
        $tk = $this->token();
        $url = "https://bonfitness.com.br/api/v1/pedidos?id={$numero}";
        try {
            $data = parent::curlApi(
                $url,
                [
                    "Content-type: application/json",
                    "Authorization: $tk",
                ]
            );

            return $data;
        } catch (Exception $e) {
            return Response::json([
                'mensagem' => $e->getMessage(),
            ], 500);
        }
    }

    // Busca informações na belluno pelo id da transação;
    private function pagamentoBelluno($transacao) {
        try {
            $url = 'https://api.belluno.digital/v2/transaction/'.$transacao;

            $data = parent::curlApi(
                $url,
                [
                    "Content-type: application/json",
                    "Authorization: Bearer " . $this->tkb,
                ]
            );
            return $data;
        } catch (Exception $e) {
            return Response::json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}