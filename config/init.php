<?php

require __DIR__ . "/../http/Response.php";

abstract class DB {
    private $dbName;
    private $path;
    private $user;
    private $password;
    private $port;
    private $schema;
    protected $tkBAbstract;
    private static $file = __DIR__ . "/../.env";
    protected $conn;
    private $pathEstilo;

    private static function loadEnv() {
        if (!file_exists(self::$file)) {
            throw new Exception("O arquivo .env não existe.");
        }
        $linhas = file(self::$file);
        foreach ($linhas as $linha) {
            if (strpos(trim($linha), '#') === 0) {
                continue;
            }
    
            list($nome, $valor) = explode('=', $linha, 2);
            $_ENV[$nome] = trim($valor);
        }
    }

    public function __construct() {
        try {
            $this->loadEnv(); 
            $this->user = $_ENV['SISPLANUSER'];
            $this->password = $_ENV['SISPLANPASS'];
            $this->dbName = $_ENV['SISPLANHOST'];
            $this->port = $_ENV['SISPLANPORT'];
            $this->path = $_ENV['SISPLANPATH'];
            $this->schema = $_ENV['SISPLANSCHEMA'];

            $this->pathEstilo = $_ENV['PATHESTILO'];
    
            $this->tkBAbstract = $_ENV['TKB']; 

            $this->conn = new PDO("pgsql:host={$this->dbName};port={$this->port};dbname={$this->path};user={$this->user};password={$this->password}");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("SET search_path TO {$this->schema};");
        } catch(PDOException $e) {
            throw new Exception("Não foi possivel conectar ao banco, entre em contato com o administrador do servidor!". $e->getMessage());
        }
    }

    protected function getConn(): PDO {
        return $this->conn;
    }

    protected function getPathEstilo(): string {
        return $this->pathEstilo;
    }

    protected function logSisplanConn(string $empresa, string $descricao, $user) {
        try {
            if ($descricao == '' || $empresa == '') {
                return [
                    'response' => "Os parametros select e/ou tabela e/ou condição estão vazios, favor incluir algum campo/tabela/condição para continuar!",
                    'status' => false,
                ];
            }
            $data = date('Y-m-d');
            if (empty($user)) {
                $user = get_current_user();
            }
            $dados = $this->conn->prepare("INSERT INTO LOG (DATA, DESCRICAO, TELA, USUARIO, EMPRESA, CHAVE) VALUES ('{$data}', '{$descricao}', 'AUTO', '{$user}', '_00{$empresa}', 'AUTO')");
            $dados->execute();
            return [
                'response' => $dados,
                'status' => true,
            ];
        } catch (PDOException $e) {
            return [
                'response' => "Erro: {$e->getMessage()}",
                'status' => false,
            ];
        }
    }

    protected function curlApi (string $urlApi, array $headers) {
        try {
            $init = curl_init();
            curl_setopt_array($init,[
                CURLOPT_URL => $urlApi,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            return json_decode(curl_exec($init), true);
        } catch (Exception $e) {
            return Response::json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

} 