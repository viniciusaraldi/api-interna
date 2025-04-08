<?php

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . "/../http/Response.php";

class Estilo extends DB {

    public static $pathfile = "/../public/fotos/amostras/";
    public static $destPathFile;
    private $tableDb = 'TABF08';
    
    public function __construct() {
        parent::__construct();
        self::$destPathFile = parent::getPathEstilo();
    }

    private function getSql($sql, $column = '', $allData = 0) {
        $stmt = $this->getConn()->prepare($sql);
        $stmt->execute();
        if (!$stmt->execute()) {
            return Response::json([
                'success' => false,
                'messagem' => "Houve algum problema ao preparar o comando",
                'sql' => $sql,
            ], 403);
            exit;
        }

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // if (empty($result)) {
        //     return Response::json([
        //         'success' => false,
        //         'messagem' => "Nenhum resultado encontrado para a query",
        //         'sql' => $sql,
        //     ], 404);
        // }
        
        return $allData == 0 ? (
            $column == '' ? $result[0] : $result[0][strtoupper($column)]
            ) : ['colunas' => $result, 'linhas' => $stmt->rowCount()];
    }
    private function insertAmostra($id, $amostra, $prototipo, $pontosMedicao, $tolerancia, $toleranciaMin, $toleranciaMax, $tamanhos, $valorTamanhos, $status = 0, $valorTamanhosReal) {
        try {
            $data = $this->getSql("INSERT INTO $this->tableDb (id,amostra, prototipo, pontos_Medicao, tolerancia, tolerancia_min, tolerancia_max, tamanhos, valor_tamanhos, status, valor_tamanhos_real) VALUES 
            ($id, $amostra, '$prototipo','$pontosMedicao', $tolerancia,$toleranciaMin, $toleranciaMax,'$tamanhos', '$valorTamanhos', $status, '$valorTamanhosReal')", '', 1);
            return [
                'rowsAffected' => $data['linhas'],
                'id' => $id,
                'amostra' => $amostra,
                'prototipo' => $prototipo,
                'ponto_medicao' => $pontosMedicao,
                'tolerancia' => $tolerancia,
                'minima' => $toleranciaMin,
                'maxima' => $toleranciaMax,
                'tamanhos' => $tamanhos,
                'valorTamanhos' => $valorTamanhos,
                'status' => $status,
                'valorTamanhosReal' => $valorTamanhosReal,
            ];
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        }
    }
    private static function isSetAmostraDatas(array $data) {
        return isset($data['prototipo'],$data['pontos_medicao'],$data['tolerancia'], $data['tolerancia_min'], $data['tolerancia_max'], $data['tamanhos'], $data['valor_tamanhos']);
    }
    private static function existsPathIamgeAmostra($path, $prototipo, $amostra) {
        $fullPath = str_replace("\\", '/', realpath(__DIR__ . "/../public")) . $path . $prototipo . "/";

        if (file_exists($fullPath)) {
            if (file_exists($fullPath . "/$amostra")) {
                return true;
            } else {
                mkdir($fullPath . "/$amostra", 0777, true);
                return true;
            }
        } else {
            mkdir($fullPath, 0777, true);
            $amostra = mkdir($fullPath . $amostra, 0777, true);
        }
    }
    private function getPrototipo(string $prototipo) {
        return $this->getSql("SELECT * FROM PRODUTO_001 WHERE PROTOTIPO = '$prototipo'", 'prototipo', 0) == '' ? false : true;
    }
    public function get(array $path) {
        try {
            $props = [
                'getAmostra',
                'getImagesAmostras',
                'getFaixaPrototipo',
                'getPrototipoPorAmostra',
            ];

            $funcao = $path[0];
            $queryString = $path[1];
            $infoQueryDivida = explode('&', $queryString);

            $prototipo = '';
            $amostra = '';

            $mapa = [
                'prototipo' => $prototipo,
                'amostra' => $amostra,
            ];

            foreach ($infoQueryDivida as $value) {
                $pilhaInfo = explode("=",$value);
                if (isset($pilhaInfo[0], $pilhaInfo[1]) && isset($mapa[$pilhaInfo[0]])) {
                    $mapa[$pilhaInfo[0]] = $pilhaInfo[1];
                }
            }
            
            return in_array($funcao, $props) ? $this->{$funcao}($mapa) : Response::json([
                'success' => false,
                'messagem' => 'Não foi encontrado a rota ' . $funcao,
            ], 403);

        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        }
    }
    public function post(array $path) {
        try {
            $props = [
                'setAmostra', 
                'setFotosAmostra', 
                'setTesteUso',
                'setStatus'
            ];
            $funcao = $path[0];
            $body = $path[2];
            $files = $path[3] ?? null;

            return in_array($funcao, $props) ? ($files == null ? $this->{$funcao}($body) : $this->{$funcao}($body, $files)) : Response::json([
                'success' => false,
                'messagem' => 'Não foi encontrado a rota ' . $funcao,
            ], 403);
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        }
    } 
    public function put(array $path) {
        try {
            $props = [
                'editPontosMedicao', 
            ];
            $funcao = $path[0];
            $body = $path[2];
            $files = $path[3] ?? null;

            return in_array($funcao, $props) ? ($files == null ? $this->{$funcao}($body) : $this->{$funcao}($body, $files)) : Response::json([
                'success' => false,
                'messagem' => 'Não foi encontrado a rota ' . $funcao,
            ], 403);
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        }
    } 
    private function getPrototipoPorAmostra () {
        return $this->getSql("SELECT  DISTINCT PROTOTIPO, list(amostra) AS amostra FROM (SELECT distinct prototipo, amostra FROM $this->tableDb order by prototipo, amostra) GROUP BY prototipo", '', 1);
    }
    private function getImagesAmostras(array $data = []) {
        try {
            list($amostra, $prototipo) = isset($data['amostra'], $data['prototipo']) ? [$data['amostra'],$data['prototipo']] : [null,null];
            $sql = "SELECT  DISTINCT prototipo, amostra, foto_frente, foto_costa, foto_lateral, obs_amostra from $this->tableDb where amostra = $amostra and prototipo = '$prototipo'";
            
            $info = $this->getSql($sql, '', 1);
            if ($info) {
                return Response::json([
                    'success' => true,
                    'messagem' => [
                        'foto_frente' => self::$destPathFile . $prototipo . '/' . $amostra . '/' . $info['colunas'][0]['FOTO_FRENTE'],
                        'foto_costa' => self::$destPathFile . $prototipo . '/' . $amostra . '/' . $info['colunas'][0]['FOTO_COSTA'],
                        'foto_lateral' => self::$destPathFile . $prototipo . '/' . $amostra . '/' . $info['colunas'][0]['FOTO_LATERAL'],
                        'obs_amostra' => $info['colunas'][0]['OBS_AMOSTRA'],
                    ],
                ], 200);
            } else {
                return Response::json([
                    'success' => false,
                    'messagem' => "Arquivo não encontrado!",
                ], 404);
            }
        } catch (PDOException $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        }
    }
    private function getAmostra(array $data = []) {
        try {
            $sql = '';
            if (is_array($data) && $data['prototipo'] && $data['amostra']) {
                $amostra = $data['amostra'];
                $prototipo = $data['prototipo'];
                $sql = "SELECT DISTINCT * FROM $this->tableDb f where F.AMOSTRA = $amostra and F.PROTOTIPO = '$prototipo' order by prototipo, amostra, id";
            } else if (is_array($data) && $data['prototipo']) {
                $prototipo = $data['prototipo'];
                $sql = "SELECT DISTINCT * FROM $this->tableDb f where F.PROTOTIPO = '$prototipo' order by prototipo, amostra, id";
            } else {
                $sql = "SELECT DISTINCT * FROM $this->tableDb f order by prototipo, amostra, id";
            }
            return Response::json([
                'success' => true,
                'messagem' => $this->getSql($sql, '', 1),
            ], 200);
        } catch (PDOException $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        }
    }
    private function getFaixaPrototipo(array $amostra) {
        if (!isset($amostra['prototipo'])) {
            return Response::json([
                'success' => false,
                'messagem' => "Não foi possivel encontrar o prototipo",
            ], 404);
        }

        $prototipo = $amostra['prototipo'];
        $data = $this->getSql("SELECT  DISTINCT '$prototipo' as prototipo, f.faixa, f.posicao, f.tamanho FROM FAIXA_ITEN_001 f WHERE f.FAIXA IN (SELECT faixa FROM produto_001 p WHERE p.PROTOTIPO = '$prototipo')", '', 1);
        return isset($data['colunhas']) ? $data['colunas'] : $data;
    }
    private function setStatus ($data) {
        try {
            $prototipo = $this->getPrototipo($data['prototipo']) ? $data['prototipo'] : 0;
            $amostra = $this->getAmostra(['amostra' => $data['amostra'], 'prototipo' => $prototipo])['messagem']['colunas'];
            $status = (int) $data['status'];
            if (is_integer($status) && $amostra) {
                $numAmostra = $data['amostra'];
                $pcpMedidas = [];

                if ($status == 1) {
                    $sqlAmostra = "SELECT DISTINCT * FROM $this->tableDb where amostra = $numAmostra and prototipo = '$prototipo' ";
                    $dataAmostra = $this->getSql($sqlAmostra, '', 1)['colunas'];
                    $order = 1;

                    foreach ($dataAmostra as $value) {
                        $prototipo = $value['PROTOTIPO'];
                        $pontosMedicao  = $value['PONTOS_MEDICAO'];
                        $tolerancia = $value['TOLERANCIA'];
                        $toleranciaMin = $value['TOLERANCIA_MIN'];
                        $toleranciaMax = $value['TOLERANCIA_MAX'];

                        $tam = explode(",", $value['TAMANHOS']);
                        $valTam = explode(",", $value['VALOR_TAMANHOS']);
                        $valTamReal = explode(",", $value['VALOR_TAMANHOS_REAL']);
                        
                        for ($i = 0; $i < count($tam); $i++) {
                            $sqlPcpMedidas = "INSERT INTO PCP_MEDIDAS_001 (ORDEM, CODIGO, DESCRICAO, TAM, TOLERANCIA, TOLERANCIA_MIN, TOLERANCIA_MAX, TIPO_MEDIDA, DESC_TIPO_MEDIDA, TIPO, MEDIDA, MEDIDA2) 
                                VALUES ('$order', '$prototipo', '$pontosMedicao', '$tam[$i]', $tolerancia, $toleranciaMin, $toleranciaMax, 'F', 'FINAL', 'P', '$valTamReal[$i]', '$valTamReal[$i]')";
                            $rowS = $this->getSql($sqlPcpMedidas, '', 1);
                            $pcpMedidas[] = $rowS;
                        }

                        $order += 1;
                    }
                } else {
                    $sqlAmostra = "DELETE FROM $this->tableDb where amostra = $numAmostra and prototipo = '$prototipo' ";
                    $dataAmostra = $this->getSql($sqlAmostra, '', 1)['linhas'];
                    $pcpMedidas[] = "Linhas removidas" . $dataAmostra;
                }
                $sql = "UPDATE $this->tableDb set status = $status where amostra = $numAmostra and prototipo = '$prototipo' ";
                $row = $this->getSql($sql, '', 1)['linhas'];
                return isset($row) && $row > 0 ? Response::json([
                    'success' => true,
                    'message' => "Atualizado com sucesso",
                    'amostra' => $data['amostra'],
                    'prototipo' => $data['prototipo'],
                    'pcp_medidas' => $pcpMedidas,
                    'status' => $status
                ]) :  Response::json([
                    'success' => false,
                    'message' => "Não foi atualizado o status da amostra por algum motivo, entre em contato com o desenvolvedor",
                    'amostra' => $data['amostra'],
                    'prototipo' => $data['prototipo'],
                    'pcp_medidas' => $pcpMedidas,
                    'status' => $status
                ], 403);
            }
            return Response::json([
                'success' => false,
                'message' => "Não foi encontrado o numero da amostra ou o tipo de status está incorreto!",
                'amostra' => $data['amostra'],
                'prototipo' => $data['prototipo'],
                'status' => $status
            ], 404);
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        }
    }
    private function setTesteUso ($data) {
        try {
            $prototipo = $this->getPrototipo($data['prototipo']) ? $data['prototipo'] : 0;
            $amostra = $this->getAmostra(['amostra' => $data['amostra'], 'prototipo' => $prototipo])['messagem']['colunas'][0];
            $obsTesteUso = $data['obs_teste_uso'];
            $usuario = $data['usuario'];

            $numAmostra = $amostra['AMOSTRA'];

            if (isset($amostra) && $amostra['STATUS'] == '1') {
                return Response::json([
                    'success' => true,
                    'message' => "Atualizado teste de uso da amostra $numAmostra",
                    'linhas_afetadas' => $this->getSql("UPDATE $this->tableDb SET OBS_TESTE_USO = '$obsTesteUso', teste_uso = 1, usuario_status = '$usuario' where amostra = $numAmostra and prototipo = '$prototipo'", '', 1)['linhas'],
                ], 200);  
            }
            return Response::json([
                'success' => false,
                'message' => "Não foi encontrado o numero da amostra ou o status está como REPROVADO",
                'amostra' => $amostra,
            ], 404);        
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        }
    }
    private function setFotosAmostra($data, $files = null) {
        try {
            $obsAmostraRequesition = $data['obs_amostra'] ?? '';
            $amostra = isset($data['amostra']) ? $data['amostra'] : 0;
            $prototipo = $this->getPrototipo($data['prototipo']) ? $data['prototipo'] : false;
            $data = $this->getAmostra(['amostra' => $amostra, 'prototipo' => $prototipo]);
            $obsAmostra = !isset($data['obs_amostra']) || $data['obs_amostra'] == '' || $data['obs_amostra'] == null ? $obsAmostraRequesition : $data['obs_amostra'];
            if ($files == null) {
                $info = $this->getSql("UPDATE $this->tableDb SET OBS_AMOSTRA = '$obsAmostra' where amostra = $amostra and prototipo= '$prototipo'", '', 1);
                return Response::json([
                    'success' => true,
                    'data' => $info,
                ], 200);
            }
            if ($data) {
                $responseImage = [];
                foreach ($files as $key => $file) {
                    $campoFoto = $key;
                    $name = $file['name'];
                    $extension = $file['type'];
                    $tempNome = $file['tmp_name'];
                    if ($extension == "image/png" || $extension == 'image/jpg' || $extension == 'image/jpeg') {
                        self::existsPathIamgeAmostra("/fotos/amostras/", $prototipo, $amostra);

                        if(move_uploaded_file($tempNome, __DIR__ . self::$pathfile . "$prototipo/$amostra/" . basename($name))) {
                            $pathDb = basename($name);
                            $infoFile = $this->getSql("UPDATE $this->tableDb SET $campoFoto = '$pathDb', obs_amostra = '$obsAmostra' where amostra = '$amostra' and prototipo= '$prototipo'");

                            $row = isset($infoFile['linhas']) ? $infoFile['linhas'] : 0;

                            $responseImage[] = [
                                'file' => $name,
                                'caminho' => self::$pathfile .  $prototipo . "/" .  $amostra . "/" . basename($name),
                                'message' => "sucesso ao inserir no servidor",
                                'linhas_afetadas' => $row
                            ];
                        } else {
                            $responseImage[] = [
                                'file' => $name,
                                'caminho' => self::$pathfile . $prototipo . "/" .  $amostra . "/" . basename($name),
                                'message' => "Não inserido no servidor",
                                'linhas_afetadas' => 0,
                            ];
                        }
                    } else {
                        $responseImage[] = [
                            'file' => $name,
                            'extensao' => $extension,
                            'temp_nome' => $tempNome,
                            'message' => "Não inserido no servidor por causa da extensao nao aceita, somente PNG e JPG",
                            'linhas_afetadas' => 0,
                        ];
                    }
                }
                return Response::json([
                    'success' => true,
                    'data' => $responseImage,
                ], 200);
            } else {
                return Response::json([
                    'success' => false,
                    "message" => "Prototipo não encontrado: ". $prototipo,
                ], 404); 
            }
        } catch (PDOException $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        }
    }
    private function setAmostra(array $datas) {
        try {
            $prototipo = $this->getPrototipo($datas[0]['prototipo']) ? $datas[0]['prototipo'] : 0;
            $amostra = (int) $this->getSql("SELECT cast(COALESCE(max(amostra),0) as numeric) amostra FROM $this->tableDb where prototipo = '$prototipo'", 'amostra', 1)['colunas'][0]['AMOSTRA'] + 1;
            $info = [];
            
            $id = (int) $this->getSql("SELECT cast(COALESCE(max(id),1) as numeric) id FROM $this->tableDb", 'id', 1)['colunas'][0]['ID'];

            foreach ($datas as $data) {
                if (is_array($data) && $this->isSetAmostraDatas($data) && $this->getPrototipo($data['prototipo'])) {
                    $info[] = $this->insertAmostra($id, $amostra, $data['prototipo'],$data['pontos_medicao'],$data['tolerancia'], $data['tolerancia_min'], $data['tolerancia_max'], $data['tamanhos'], $data['valor_tamanhos'], 0, $data['valor_tamanhos_real']);
                    $id = (int) $this->getSql("SELECT cast(COALESCE(max(id),1) as numeric) id FROM $this->tableDb", 'id', 1)['colunas'][0]['ID'] + 1;
                } else {
                    if (is_array($data)) {
                        return Response::json([
                            'success' => false,
                            'messagem' => 'Há parametros faltando ou o protótipo está incorreto',
                            'parametros' => [
                                'prototipo' => $data['prototipo'],
                                'pontos_medicao' => $data['pontos_medicao'],
                                'tolerancia' => $data['tolerancia'],
                                'tolerancia_min' => $data['tolerancia_min'],
                                'tolerancia_max' => $data['tolerancia_max'],
                                'tamanhos' => $data['tamanhos'],
                                'valor_tamanhos' => $data['valor_tamanhos'], 
                                ]
                        ], 403);
                    } else {
                        return Response::json([
                            'success' => false,
                            'messagem' => 'Valores incorretos no parametro, é necessario ser um array',
                            'parametros' => $datas
                        ], 403);
                    }
                }
            }
            return Response::json([
                'success' => true,
                'message' => "Amostras cadastradas com sucesso!",
                'data' => $info,
            ], 200);
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        }
    }
    private function editPontosMedicao(array $data) {
        try {
            $prototipo = $this->getPrototipo(isset($data['prototipo']) ? $data['prototipo'] : 0) ? $data['prototipo'] : 0;
            $amostra = isset($this->getAmostra(['amostra' => $data['amostra'], 'prototipo' => $prototipo])['messagem']['colunas']) ? $data['amostra'] : 0;
            if ($prototipo == 0 || $amostra == 0) {
                return Response::json([
                    'success' => false,
                    'messagem' => "Não encontrado o prototipo ou a amostra",
                    'amostra' => $data['amostra'],
                    'prototipo' => $data['prototipo']
                ], 404);
            } 

            $keys = "";
            $condition = "";
            foreach ($data as $key => $value) {
                $keys .= $key . ",";
                
                if ($key !== 'amostra' && $key !== 'prototipo' && $key !== 'id') {
                    if ($key == 'tolerancia' || $key == 'tolerancia_min' || $key == 'tolerancia_max') {
                        $condition .= "$key = $value,";
                    } else {
                        $condition .= "$key = '$value',";
                    }
                }
            }
            $id = $data['id'];

            $condition = rtrim($condition, ",");

            $sql = "UPDATE $this->tableDb SET $condition where amostra = $amostra and prototipo = '$prototipo' and id = $id";
            $verifyColumns = $this->getSql($sql, '', 1);
            return Response::json([
                'success' => true,
                'messagem' => $verifyColumns,
            ]);
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        }
    }
}