<?php

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . "/../http/Response.php";

class Financeiro extends DB {
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

    private function errorFile ($message) {
        $errorFile = __DIR__ . "/../errors/financeiro/error-ao-exportar-pedidos-portmontt-" . date('Y-m-d') . '.log';
        $file = fopen($errorFile, 'a+');
        fwrite($file, "$message");
        fclose($file);
    }

    public function post($path) {
        try {
            $funcao = $path[0];
            $queryString = $path[2];
            switch($funcao) {
                case 'insertDuplicataCartao':
                    return $this->{$funcao}($queryString);
                default:
                    return Response::json([
                       'messagem' => 'Não foi encontrado a rota ' . $path[0],
                    ], 404);
            }
        } catch (Exception $e) {
            return Response::json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Metodo de chamado a API
    public function insertDuplicataCartao($datas) {
        try {
            $dataI = $datas['data_inicial'];
            $dataF = $datas['data_final'];
            
            // retorna o array como qtdeVezes, nsu, fatura... 
            $dados = $this->getPedidosPortmont($dataI, $dataF);
            $pedidosManuais = [];
            $pedidosSucesso = [];
            
            foreach ($dados as $value) {
                $cliente = $value['cliente'];
                $qtdeVezes = $value['quantidade_vezes'] ?? null;
                $nsu = $value['nsu'] ?? null;
                $fatura = $value['numero_nota'];
                $duplicata = $value['duplicata'];
                $pedido = $value['pedido_sisplan'];


                $parcelas = $value['pagamentos'] ?? null;

                // Busca o último número para cadastro de numero e acrescenta mais um;
                $numero = $this->getUltimoNumeroReceberCartao();

                if (!$qtdeVezes && !$nsu && !$parcelas) {
                    // Pedidos que não tem payments em Belluno ou que estão pagos em outra forma de pagamentos em vez de card
                    $pedidosManuais[] = isset($value['pedido_manual_erro']) ? $value['pedido_manual_erro'] : null; 
                } else {
                    for ($num = 0; $num < $qtdeVezes; $num++) {
                        $dataVencimentoDuplicata = (new DateTime($parcelas[$num]['credit_date']))->format('Y-m-d');

                        $lancamento = $this->setNovaSequencia();      
    
                        // Inserindo Duplicata de cartão no receber
                        $this->insertReceberCartao(
                            $cliente,
                            $nsu,
                            $dataVencimentoDuplicata,
                            $fatura,
                            "0" . $numero . "C/" . ($num + 1),
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
                
            }
            return Response::json([
                'status' => 200,
                'pedidos_manuais' => $pedidosManuais,
                'pedidos_sucessos' => $pedidosSucesso,
                'message' => 'Programa Finalizado',
            ], 200);
        } catch (Exception $e) {
            return Response::json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Pega o último número para incluir uma duplicata de cartão no receber
    private function getUltimoNumeroReceberCartao() {
        try {
            $sql = "select proximo from codigos_003 where tabela='RECEBER' and campo='NUMERO'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $num = $stmt->fetch(PDO::FETCH_ASSOC);
    
            $proximoNumero = $num['PROXIMO'] + 1;
    
            $stmt1 = $this->conn->prepare("update codigos_003 set proximo = :proximo where tabela='RECEBER' and campo='NUMERO'");
            $stmt1->bindParam(':proximo', $proximoNumero, PDO::PARAM_INT);
            $stmt1->execute();
    
            return $num['PROXIMO'];
        } catch (Exception $e) {
            $this->errorFile("Função getUltimoNumeroReceberCartao\n" .$e->getMessage() . "\nLinha: " . $e->getLine() . "\n-------------------***------------\n");
        }
    }

    // Insere duplicata de Cartão no receber
    private function insertReceberCartao($cliente, $cartaoNSU, $dataVencimento, $fatura, $numero, $obs, $parcela, $pedido, $valorOriginal, $valorDuplicata, $valorPago, $valor2, $duplicata, $lancamento) {
        try {
            $dataCorrente = date("Y-m-d");
            $sql = "INSERT INTO RECEBER_003 (SITUACAO,ALIQUOTA, DESC_PROGRAMADO, DESPESAS, dt_comrec, frete, BANCO, BANCO_CH, BORDERO, CARTAO_NSU, CLASSE, CODCLI, CODCLI_ORIG, com1, com2, com3, com4, CONTA_CHEQUE, DT_DIGITA, DT_EMISSAO, DT_ENVIO, DT_PREVISAO, DT_STATUS, DT_VENCTO, ORIGINAL, EMP_ID, fatura, HISTORICO, moeda, numero, obs, parcela, pedido, STATUS, VAL_ORIGIN, VALOR, VALOR_PAGO, valor2, juros, desconto, val_dev, lancamento) VALUES ('',0, 0, 0, '$dataCorrente', 0, '912', '912', '0', '$cartaoNSU', '1001', '467602', '467602', 0, 0, 0, 0, '1', '$dataCorrente', '$dataCorrente', '1899-12-30', '$dataCorrente', '1899-12-30', '$dataVencimento', '$dataCorrente', 3, '$fatura', '0001', '15', '$numero', '$obs', '$parcela', '$pedido', 'DUPL', $valorOriginal, $valorDuplicata, $valorPago, $valor2, 0, 0, 0,$lancamento);";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();


            if ($stmt->rowCount() > 0) {
                // Conforme for criando a duplicata de cartão no receber, vai jogando a baixa no receber da duplicata do cliente
                $this->setContabil($cliente, $dataCorrente, $lancamento, $duplicata, $valor2, $fatura);
                $this->insertBaixaDuplCliente($numero, $duplicata, $valor2,  4041.59, $lancamento);
            } else {
                // Caso ocorra algum erro
                $this->errorFile('Houve algum problema ao inserir a duplicata de cartão no sistema:\nnumero: $numero \n');
            }
        } catch (Exception $e) {
            $this->errorFile("Função insertReceberCartao\n" .$e->getMessage() . "\nLinha: " . $e->getLine() . "\n-------------------***------------\n");
        }
    }

    // Cria o lançamento contabil
    private function setContabil($cliente, $dataCorrente, $lancamento, $duplicata, $valorParcela, $fatura) {
        try {
            $contaCliente = $this->getContaCreditoCliente($cliente);
            $nomeCliente = $this->getNomeCliente($cliente);

            $sqlContabil = "INSERT INTO contabil_003 (codcli, conta_c, conta_d, data, data_lan, emp_id, lancamento, numero, observacao, operacao, ordem, tipo, valor, fatura, filial, particip_c, particip_d, docto) VALUES ('$cliente', '$contaCliente', '1605','$dataCorrente', '$dataCorrente', 3, $lancamento, '$duplicata','Nosso recebimento Dpl.NR.$duplicata de $nomeCliente', '-', 1, 'RR', $valorParcela, $fatura, 3, '$cliente', '$cliente', '$fatura');";
            
            $stmt = $this->conn->prepare($sqlContabil);
            $stmt->execute();
        } catch (Exception $e) {
            $this->errorFile("Função setContabil\n" .$e->getMessage() . "\nLinha: " . $e->getLine() . "\n-------------------***------------\n");
        }
    }

    private function getNomeCliente($cli) {
        try {
            $sqlNome = "SELECT nome FROM entidade_001 e where e.codcli = '$cli'";
            $stmt = $this->conn->prepare($sqlNome);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $data[0]['NOME'];
        } catch (Exception $e) {
            $this->errorFile("Função getNomeCliente\n" . $e->getMessage() . "\nLinha: " . $e->getLine() . "\n-------------------***------------\n");
        }
    }

    private function getContaCreditoCliente($cli) {
        try {
            $sqlContaCliente = "SELECT con_cli FROM CONT_ENTIDADE_001 e where e.codcli = '$cli'";
            $stmt = $this->conn->prepare($sqlContaCliente);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $data[0]['CON_CLI'];
        } catch (Exception $e) {
            $this->errorFile("Função getContaCreditoCliente\n" . $e->getMessage() . "\nLinha: " . $e->getLine() . "\n-------------------***------------\n");
        }
    }

    // Ao ser criado a duplicata de cartão, aqui essa duplicata será baixada na duplicata da cliente
    private function insertBaixaDuplCliente($numero, $duplicata, $valorParcela, $valorTotal, $lancamento) {
        try {
            $dataCorrente = date("Y-m-d");
            $dT = new DateTime();
            $dateTime = $dT->format("Y-m-d H:i:s.v");
            $obs = 'Baixou com Cartao Dup:' . $numero;

            $sql = "INSERT INTO RECEBERB_003 (classe, COMISSAO, CONTROLE, DESAGIO, desconto, DESCONTO2, DESCONTO3, DESP_COBRANCA, DOCTO, DT_COMISSAO, DT_CONT, dt_pagto, HISTORICO, juros, LANCAMENTO, MOEDA, MOTIVO_DEV, NRCAIXA, NUMERO, obs, PORTADOR, QT_DEV, TAXA_MOEDA, TELA_BAIXA, VAL_DEV, VALOR_PAGO, VALOR2, VALPAGO_MOEDA, VAR_CAMBIAL, DATA_HORA) VALUES ('1001', 'S', '', 0, 0, 0, 0, 0, '', '$dataCorrente', '$dataCorrente', '$dataCorrente', '0001', 0, $lancamento, '15', '', '', '$duplicata', '$obs', '', 0, 0, 'AutomacaoBaixaCartaoBelluno', 0, $valorParcela, $valorTotal, 0, 0, '$dateTime');";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                // incrementa +1 no uúltimo lançamento;
                $this->setUltimoLancamento($lancamento);
                
                // abate o valor da duplicata do cliente
                $this->editAbateReceberCliente($duplicata, $valorParcela);
            } else {
                $this->errorFile("Erro: Houve algum problema ao baixar a duplicata do cliente no sistema:\nnumero do cartão: $numero \nnumero da duplicata: $duplicata\n-------------------***------------\n");
            }
        } catch (Exception $e) {
            $this->errorFile("Função insertBaixaDuplCliente\n" . $e->getMessage() . "\nLinha: " . $e->getLine() . "\n-------------------***------------\n");
        }
    }

    // Abate o valor das parcelas conforme for sendo lançado a duplicata de cartão
    private function editAbateReceberCliente ($duplicata, $valorParcela) {
        try {
            $valorPagoReceberCliente = $this->getValorPagoDuplCliente($duplicata);
            $valorPago = $valorPagoReceberCliente + $valorParcela;
            $sql = "UPDATE RECEBER_003 SET VALOR_PAGO = $valorPago WHERE NUMERO = '$duplicata';";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return;
            } else {
                $this->errorFile("Erro: Houve algum problema ao abater a duplicata do cliente no sistema:\nnumero da duplicata: $duplicata\n-------------------***------------\n");
            }
        } catch (Exception $e) {
            $this->errorFile("Função editAbateReceberCliente\n" . $e->getMessage() . "\nLinha: " . $e->getLine() . "\n-------------------***------------\n");
        }
    }

    // Gera um novo GEN_ID para a tabela lanc_aux_001;
    private function setNovaSequencia() {
        $sql = 'SELECT GEN_ID(G_LANC_AUX_001_SEQUENCIA, 1) AS NOVO FROM RDB$DATABASE';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $genId = $data[0]['NOVO'];

        $sqlLancAux = "INSERT INTO LANC_AUX_001 (SEQUENCIA) VALUES ($genId)";
        $stmtLancAux = $this->conn->prepare($sqlLancAux);
        $stmtLancAux->execute();

        return $genId;
    }

    // Insere na tabela de lancamentos
    private function setUltimoLancamento($lancamento) {
        $data = new DateTime();
        $dateTime = $data->format("Y-m-d H:i:s.v");

        $sqlLancAux = "INSERT INTO LANC_AUX_001 (SEQUENCIA) VALUES ('$lancamento')";
        $stmtLancAux = $this->conn->prepare($sqlLancAux);
        $stmtLancAux->execute();

        $sql = "INSERT INTO lancamento (DATA, LANCAMENTO, TELA, USUARIO) VALUES ('$dateTime', $lancamento,'TfmBaixaReceberLote','AUTO_INTERNO')";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
    }

    // Pega o valor pago na duplicata do cliente
    private function getValorPagoDuplCliente($numero) {
        $sql = "SELECT r.VALOR_PAGO FROM RECEBER_003 r WHERE NUMERO = '$numero'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $data[0]['VALOR_PAGO'];
    }

    // Busca as informações do pedido na portomont, como NSU, TID
    private function getPedidosPortmont($dataInicial, $dataFinal) {
        $data = $this->getPedidosSisplan($dataInicial, $dataFinal);
        $pedidosPortmont = [];
        
        // Busca todos os pedidos dentro da sisplan que tem o número art_cli(numero da portmontt) cadastrado
        array_walk($data, function ($e) use (&$pedidosPortmont) {
            if ($e['PORTMONTT'] != '') {
                // Busca todas as informações do pedido na portmontt
                $apiPortomontt = $this->solPedidoPortmontt($e['PORTMONTT']);

                $pedido = $apiPortomontt['pedidos'][0]['campos_personalizados'][2]['valor'];
                $pedidoSisplan = $e['SISPLAN'];
                $cliente = $e['CLIENTE'];
                $duplicataSisplan = $e['DUPLICATA'];
                $notaSisplan = $e['FATURA'];
                $valorDuplicata = $e['VALOR'];

                $jsonFormatado = json_decode($apiPortomontt['pedidos'][0]['campos_personalizados'][1]['valor_formatado'], true);

                if(is_array($jsonFormatado)) {
                    foreach ($jsonFormatado as $value) {   
                        // Busca informações na belluno pelo id da trasação;
                        $belluno = $this->validaInfoBelluno($value['tid']);
                        $qtdeVezes = $belluno['transaction']['payments'][0]['installments_number'] ?? null;
                        $pagamentos = $belluno['transaction']['payments'][0]['payables'] ?? null;
                        $valorPagoTotalTid = $belluno['transaction']['cart'][0]['unit_value'] ?? null;
                        $tipoPagamento = $belluno['transaction']['payments'][0]['type'] ?? null;
                        
                        if ($valorPagoTotalTid > $valorDuplicata || $tipoPagamento != 'card' || !isset($qtdeVezes) || !isset($pagamentos)) {
                            // Caso a belluno ainda não validar o pagamento, gera o arquivo de erro
                            $this->errorFile("Erro: Não foi possivel buscar as informações de pagamentos como quantidade de vezes e valor das parcelas do pedido:\ndo sispan: $pedidoSisplan;\nda portmontt: $pedido;\n-------------------***------------\n");
                            $pedidosPortmont[] = [
                                'cliente' => $cliente,
                                'pedido_sisplan' => $pedidoSisplan,
                                'pedido_portomontt' => $pedido,
                                'duplicata' => $duplicataSisplan,
                                'numero_nota' => $notaSisplan,
                                'pedido_manual_erro' => "Retorno da belluno:<br>Pedido Sisplan: $pedidoSisplan<br>Pedido Portomontt: $pedido<br>Parcelas: $qtdeVezes<br>Pagamentos: $pagamentos<br>Tipo de Pagamento: $tipoPagamento<br>Valor duplicata: $valorDuplicata<br>Valor Belluno: $valorPagoTotalTid<br>***************",
                            ];
                        } else {   
                            $pedidosPortmont[] = [
                                'cliente' => $cliente,
                                'pedido_sisplan' => $pedidoSisplan,
                                'pedido_portomontt' => $pedido,
                                'duplicata' => $duplicataSisplan,
                                'numero_nota' => $notaSisplan,
                                'nsu' => $value['nsu'],
                                'tid' => $value['tid'],
                                'quantidade_vezes' => $qtdeVezes,
                                'pagamentos' => $pagamentos,
                                'valor_total_cartao_tid' => $valorPagoTotalTid
                            ];
                        }
                    }
                } else {
                    $pedidosPortmont[] = [
                        'cliente' => $cliente,
                                'pedido_sisplan' => $pedidoSisplan,
                                'pedido_portomontt' => $pedido,
                                'duplicata' => $duplicataSisplan,
                                'numero_nota' => $notaSisplan,
                                'pedido_manual_erro' => "Retorno da Portomontt:<br>Erro ao exportar o JSON: $jsonFormatado<br>Sisplan: $pedidoSisplan<br>Portomontt: $pedido<br>***************",
                    ];
                }

            }
        });

        return $pedidosPortmont;
    }
    
    // Busca todos os pedidos que tiveram faturamento e duplicata, a pesquisa é feita em cima da data de emissão da duplciata
    protected function getPedidosSisplan($dataInicial, $dataFinal) {
        $sql = "SELECT RECEBER.CODCLI CLIENTE, PEDIDO.NUMERO SISPLAN, PEDIDO.ART_CLI PORTMONTT, PEDIDO.PED_CLI UOOU, RECEBER.NUMERO DUPLICATA, NOTA.FATURA, RECEBER.VALOR FROM NOTA_003 NOTA
        INNER JOIN PEDIDO_001 PEDIDO ON NOTA.FATURA = PEDIDO.NOTA
        INNER JOIN RECEBER_003 RECEBER ON RECEBER.FATURA = NOTA.FATURA 
        WHERE 1 = 1
        AND PEDIDO.EMP_FAT = '003'
        AND RECEBER.DT_EMISSAO between '$dataInicial' and '$dataFinal'
        AND RECEBER.NUMERO NOT IN (SELECT DISTINCT BAIXA.NUMERO FROM RECEBERB_003 BAIXA WHERE BAIXA.TELA_BAIXA IN ('AutomacaoBaixaCartaoBelluno','TfmBaixaReceberLote'))
        AND RECEBER.NUMERO NOT LIKE '%C/%'
        AND (RECEBER.VALOR - RECEBER.VALOR_PAGO) <> 0
        GROUP BY RECEBER.CODCLI, PEDIDO.NUMERO, PEDIDO.ART_CLI, PEDIDO.PED_CLI, RECEBER.NUMERO, NOTA.FATURA, RECEBER.VALOR
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $data;
    }

    // Pega todas as informações do pedido na maxnivel
    private function solPedidoPortmontt($pedido) {
        try {
            $tk = $this->token();
            $url = 'https://bonfitness.com.br/api/v1/pedidos?campos_personalizados[pedido_portomontt]='.$pedido;
            
            $data = parent::curlApi(
                $url,
                [
                    "Content-type: application/json",
                    "Authorization: $tk",
                ]
                );
            // $init = curl_init();
            // curl_setopt_array($init,[
            //     CURLOPT_URL => $url,
            //     CURLOPT_RETURNTRANSFER => true,
            //     CURLOPT_SSL_VERIFYPEER => false,
            //     CURLOPT_SSL_VERIFYHOST => false,
            //     CURLOPT_HTTPHEADER => [
            //         "Content-type: application/json",
            //         "Authorization: $tk",
            //     ],
            // ]);
            // return json_decode(curl_exec($init), true);
            return $data;
        } catch (Exception $e) {
            return Response::json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Busca informações na belluno pelo id da transação;
    private function validaInfoBelluno($transacao) {
        try {
            $url = 'https://api.belluno.digital/v2/transaction/'.$transacao;

            $data = parent::curlApi(
                $url,
                [
                    "Content-type: application/json",
                    "Authorization: Bearer " . $this->tkb,
                ]
                );

            // $init = curl_init();
            // curl_setopt_array($init,[
            //     CURLOPT_URL => $url,
            //     CURLOPT_RETURNTRANSFER => true,
            //     CURLOPT_SSL_VERIFYPEER => false,
            //     CURLOPT_SSL_VERIFYHOST => false,
            //     CURLOPT_HTTPHEADER => [
            //         "Content-type: application/json",
            //         "Authorization: Bearer " . $this->tkb,
            //     ],
            // ]);
            // return json_decode(curl_exec($init), true);
            return $data;
        } catch (Exception $e) {
            return Response::json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
