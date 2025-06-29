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

    /**
     * Função para tratar comando de SQL, column significa uma coluna especifica (se selecionado é necessário colocar colocar allData 1), allData siginifica todas as colunas (necessário deixar o column entre aspas sem conteudo dentro, ou seja, '');
     * @param string $sql
     * @param string $column
     * @param bool $allData
     */
    private function getSql($sql, $column = '', $allData = 0) {
        $stmt = $this->getConn()->prepare($sql);
        $stmt->execute();
        if (!$stmt->execute()) {
            return Response::json([
                'success' => false,
                'messagem' => "Houve algum problema ao preparar o comando",
                'sql' => $sql,
            ], 403);
        }

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $allData == 0 ? (
            $column == '' ? $result[0] : $result[0][strtolower($column)]
            ) : ['colunas' => $result, 'linhas' => $stmt->rowCount()];
    }

    /**
     * Função para incluir na tabela
     * @param mixed $id
     * @param string $amostra
     * @param string $prototipo
     * @param string $pontosMedicao
     * @param mixed $tolerancia
     * @param mixed $toleranciaMin
     * @param mixed $toleranciaMax
     * @param string $tamanhos
     * @param string $valorTamanhos
     * @param bool $status
     * @param string $valorTamanhosReal
     * @return array|array{amostra: mixed, id: mixed, maxima: mixed, minima: mixed, ponto_medicao: mixed, prototipo: mixed, rowsAffected: mixed, status: mixed, tamanhos: mixed, tolerancia: mixed, valorTamanhos: mixed, valorTamanhosReal: mixed}
     */
    private function insertAmostra($id, $amostra, $prototipo, $pontosMedicao, $tolerancia, $toleranciaMin, $toleranciaMax, $tamanhos, $valorTamanhos, $valorTamanhosReal,  $status = 0) {
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

    /**
     * Função para verificar se existe ou não as colunas selecionadas
     * @param array $data
     * @return bool
     */
    private static function isSetAmostraDatas(array $data) {
        return isset($data['prototipo'],$data['pontos_medicao'],$data['tolerancia'], $data['tolerancia_min'], $data['tolerancia_max'], $data['tamanhos'], $data['valor_tamanhos'],$data['valor_tamanhos_real']);
    }

    /**
     * Função para verificar se existe o caminho das imagens das amostras do prototipoa
     * @param string $path
     * @param string $prototipo
     * @param string $amostra
     * @return void
     */
    private static function existsPathIamgeAmostra($path, $prototipo, $amostra) {
        $fullPath = str_replace("\\", '/', realpath(__DIR__ . "/../public")) . $path . $prototipo . "/";

        if (file_exists($fullPath)) {
            if (file_exists($fullPath . "/$amostra")) {
                return;
            } else {
                mkdir($fullPath . "/$amostra", 0777, true);
                return;
            }
        } else {
            mkdir($fullPath, 0777, true);
            mkdir($fullPath . $amostra, 0777, true);
            return;
        }
    }

    /**
     * Verifica se existe o prototipo
     * @param string $prototipo
     * @return bool
     */
    private function getPrototipo(string $prototipo) {
        return $this->getSql("SELECT * FROM PRODUTO_001 WHERE PROTOTIPO = '$prototipo'", 'prototipo', 0) == '' ? false : true;
    }

    /**
     * Summary of get
     * @param array $path
     */
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

    /**
     * Summary of post
     * @param array $path
     */
    public function post(array $path) {
        try {
            $props = [
                'setAmostra', 
                'setFotosAmostra', 
                'setTesteUso',
                'setStatus',
                'setMedicaoAmostra',
                'setDuplicaAmostra'
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

    /**
     * Summary of put
     * @param array $path
     */
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
    
    /**
     * Summary of delete
     * @param array $path
     */
    public function delete(array $path) {
        try{ 
            $props = [
                'deleteAmostra'
            ];
            $funcao = $path[0];
            $body = $path[2];

            return in_array($funcao, $props) ? $this->{$funcao}($body) : Response::json([
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

    /**
     * Retorna o prototipo, e as amostras de mesmo prototipo
     * @return array
     */
    private function getPrototipoPorAmostra () {
        return $this->getSql("SELECT  DISTINCT PROTOTIPO, string_agg(amostra::text, ',') AS amostra FROM (SELECT distinct prototipo, amostra FROM $this->tableDb order by prototipo, amostra) GROUP BY prototipo", '', 1);
    }

    /**
     * Busca as Imagens do prototipo e da amostra
     * @param array $data
     * @return array
     */
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

    /**
     * Busca as amostras do prototipo, com/sem filtrar pela amostra
     * @param array $data
     * @return array
     */
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

    /**
     * Busca a faixa do prototipo
     * @param array $amostra
     * @return mixed
     */
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

    /**
     * Seta status (reprovado para 0 e aprovado como 1) na amostra, ao aprovado será incluido no banco
     * @param mixed $data
     * @return array
     */
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

    /**
     * Inclui o teste de uso para cada amostra e para cada prototipo
     * @param mixed $data
     * @return array
     */
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

    /**
     * Inclui imagens e/ou a observação da amostra
     * @param array $data
     * @param array $files
     * @return array
     */
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

    /**
     * Inclui a amostra na tabela no banco
     * @param array $datas
     * @return array
     */
    private function setAmostra(array $datas) {
        try {
            $prototipo = $this->getPrototipo($datas[0]['prototipo']) ? $datas[0]['prototipo'] : 0;
            $amostra = (int) $this->getSql("SELECT cast(COALESCE(max(amostra),0) as numeric) amostra FROM $this->tableDb where prototipo = '$prototipo'", 'amostra', 1)['colunas'][0]['AMOSTRA'] + 1;
            $info = [];
            
            $id = (int) $this->getSql("SELECT cast(COALESCE(max(id),1) as numeric) id FROM $this->tableDb", 'id', 1)['colunas'][0]['ID'];

            foreach ($datas as $data) {
                if (is_array($data) && $this->isSetAmostraDatas($data) && $this->getPrototipo($data['prototipo'])) {
                    $info[] = $this->insertAmostra($id, $amostra, $data['prototipo'],$data['pontos_medicao'],$data['tolerancia'], $data['tolerancia_min'], $data['tolerancia_max'], $data['tamanhos'], $data['valor_tamanhos'], $data['valor_tamanhos_real']);
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
                                'valor_tamanhos_real' => $data['valor_tamanhos_real'],
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

    /**
     * Adiciona um ponto de medicão a uma amostra já existente
     * @param array $data
     * @return array
     */
    private function setMedicaoAmostra(array $data) {
        try {
            $prototitpo = $this->getAmostra([
                        'prototipo' => isset($data['prototipo']) ? $data['prototipo'] : '',
                        'amostra' => isset($data['amostra']) ? $data['amostra'] : ''
                    ]);

            $info = [
                "prototipo" => $data['prototipo'],
                "amostra" => $data['amostra'],
                "pontos_medicao" => $data['pontos_medicao'],
                "tolerancia" => $data['tolerancia'],
                "tolerancia_min" => $data['tolerancia_min'],
                "tolerancia_max" => $data['tolerancia_max'],
                "valor_tamanhos" => $data['valor_tamanhos'],
                "valor_tamanhos_real" => $data['valor_tamanhos_real'],
                "tamanhos" => $data['tamanhos']
            ]; 

            if ($prototitpo['messagem']['linhas'] == 0) return Response::json([
                    'message' => "Não foi encontrado a amostra ou o prototipo informado!",
                    'data' => $info
                ], 404);
                    
            if (!$this->isSetAmostraDatas($data)) return Response::json([
                    'message' => "Há parametros faltando para incrementar o ponto de medição",
                    'data' => $info
                ], 404);

            $id = (int) $this->getSql("SELECT cast(COALESCE(max(id),1) as numeric) id FROM $this->tableDb", 'id', 1)['colunas'][0]['ID'];

            $insertPontoMedicaoAmostra = $this->insertAmostra(
                $id + 1,
                $data['amostra'],
                $data['prototipo'],
                $data['pontos_medicao'],
                $data['tolerancia'],
                $data['tolerancia_min'],
                $data['tolerancia_max'],
                $data['tamanhos'],
                $data['valor_tamanhos'],
                $data['valor_tamanhos_real']                
            );

            return Response::json([
                'message' => 'Inserido ponto de medição com sucesso na amostra ' . $data['amostra'] . ' do prototipo ' . $data['prototipo'] . '!',
                'data' => $insertPontoMedicaoAmostra,
            ]);

        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Duplica amostra para um novo prototipo com os mesmos dados
     * @param array $data
     * @return array
     */
    private function setDuplicaAmostra(array $data) {
        try {
            $prototitpo = $this->getAmostra([
                'prototipo' => isset($data['prototipo']) ? $data['prototipo'] : '',
                'amostra' => isset($data['amostra']) ? $data['amostra'] : ''
            ]);

            if ($prototitpo['messagem']['linhas'] == 0) return Response::json([
                'message' => "Não foi encontrado a amostra ou o prototipo informado!",
                'data' => [
                    'prototipo' => $data['prototipo'],
                    'amostra' => $data['amostra']
                ]
            ], 404);

            $infoNovaAmostra = [];

            $faixa = '';

            $tam = $this->getFaixaPrototipo(['prototipo' => $data['novo_prototipo']]);

            foreach ($tam['colunas'] as $value) {
                $faixa .= $value['TAMANHO'] . ",";
            }
            
            $id = (int) $this->getSql("SELECT cast(COALESCE(max(id),1) as numeric) id FROM $this->tableDb", 'id', 1)['colunas'][0]['ID'];
            $infos = $prototitpo['messagem']['colunas'];
            foreach ($infos as $value) {
                $id++;
                $infoNovaAmostra[] = [
                    'id' => $id,
                    'tamanhos' => substr($faixa, 0, -1),
                    'valor_tamanhos' => $value['VALOR_TAMANHOS'],
                    'valor_tamanhos_real' => $value['VALOR_TAMANHOS_REAL'],
                    'prototipo' => $data['novo_prototipo'],
                    'pontos_medicao' => $value['PONTOS_MEDICAO'],
                    'tolerancia' => (int) $value['TOLERANCIA'],
                    'tolerancia_min' => (int) $value['TOLERANCIA_MIN'],
                    'tolerancia_max' => (int) $value['TOLERANCIA_MAX'],
                    'amostra' => (int) $this->getSql("SELECT cast(COALESCE(max(amostra),0) as numeric) amostra FROM $this->tableDb where prototipo = '{$data['novo_prototipo']}'", 'amostra', 1)['colunas'][0]['AMOSTRA'] + 1,
                ];
            }

            $response = [];
            foreach ($infoNovaAmostra as $value) {
                $response[] = $this->insertAmostra(
                    $value['id'],
                    $value['amostra'],
                    $value['prototipo'],
                    $value['pontos_medicao'],
                    $value['tolerancia'],
                    $value['tolerancia_min'],
                    $value['tolerancia_max'],
                    $value['tamanhos'],
                    $value['valor_tamanhos'],
                    $value['valor_tamanhos_real']
                );
            }

            return Response::json([
                'message' => 'Amostra duplicata com sucesso!',
                'data' => $response,
            ]);
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'messagem' => "Error: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Edita os dados da amostra já incluida na banco
     * @param array $data
     * @return array
     */
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

    /**
     * Exclui a amostra no banco e as imagens gravadas na pasta
     * @param array $datas
     * @return array
     */
    private function deleteAmostra(array $datas) {
        try {
            $prototitpo = $this->getAmostra(['prototipo' => isset($datas['prototipo']) ? $datas['prototipo'] : '', 'amostra' => isset($datas['amostra']) ? $datas['amostra'] : '']);

            if ($prototitpo && $prototitpo['messagem']['linhas'] > 0) {
                // glob = Retorna os arquivos ou pastas dentro do path informado.
                $amostras = glob(__DIR__ . self::$pathfile . $datas['prototipo'] . '/' . $datas['amostra'] . '/*');
                foreach ($amostras as $value) {
                    unlink($value);
                }
                rmdir(__DIR__ . self::$pathfile . $datas['prototipo'] . '/' . $datas['amostra']);
                
                
                $sql = "DELETE FROM $this->tableDb where prototipo = '{$datas['prototipo']}' and amostra = '{$datas['amostra']}' ";
                $prototipoExcluido = $this->getSql($sql);
                 
                return Response::json([
                    'message' => 'Deletado prototipo com sucesso!',
                    'prototipo' => $datas['prototipo'],
                    'data' => $prototipoExcluido,
                ], 200);
            } else {
                return Response::json([
                    'message' => 'Prototipo ou amostra não encontrado nas amostras!',
                    'prototipo' => $datas['prototipo'],
                    'amostra' => $datas['amostra'],
                ], 404);
            }
        } catch (PDOException $e) {
            return Response::json([
                'message' => "Error: {$e->getMessage()}",
                'line' => $e->getLine(),
                'code' => $e->getCode()
            ], 500);
        } catch (Exception $e) {
            return Response::json([
                'message' => "Error: {$e->getMessage()}",
                'line' => $e->getLine(),
                'code' => $e->getCode()
            ], 500);
        }
    }

}
