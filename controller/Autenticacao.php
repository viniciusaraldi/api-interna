<?php 

require_once __DIR__ . '/../config/init.php';
class Autenticacao extends DB {
    protected $pathDB;
    protected $userDB;
    protected $passDB;
    protected $nameDB;
    private $env;
    protected $conn;

    public function __construct() {
        parent::__construct();
        $this->env = $this->loadEnv(__DIR__."/../.env");

        try {
            $this->userDB = $this->env['SQLUSER'];
            $this->passDB = $this->env['SQLPASS'];
            $this->pathDB = $this->env['SQLHOST'];
            $this->nameDB = $this->env['SQLNAME'];

            $cl = new PDO("mysql:host={$this->pathDB};dbname={$this->nameDB}", $this->userDB, $this->passDB);
            $cl->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn = $cl;
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage());
        }
    }

    public function post($path)  {
        try {
            $pathJsonEncode = json_encode($path);
            $pathJD = json_decode($pathJsonEncode, true);
            $funcao = $pathJD[0];

            $usuario = $pathJD[1]['usuario'];
            $senha = $pathJD[1]['senha'];
            $setor = $pathJD[1]['setor'];

            switch ($funcao) {
                case 'login':
                    return $this->{$funcao}($usuario, $setor, $senha);
                default:
                    return [
                        'status' => 404,
                        'messagem' => 'Não foi possivel encontrar a função ' . $funcao
                    ];
            }

        } catch (Exception $e) {
            return [
                "status" => 500,
                "data" => "Error: " . $e->getMessage(),
            ];
        }
    }
    
    public function get($path)  {
        try {
            $funcao = $path[0];
            $queryString = $path[1];
            $infoQueryDivida = explode('&', $queryString);

            $usuario = '';
            $setor = '';

            $mapa = [
                'usuario' => $usuario,
                'setor' => $setor
            ];

            array_map(function ($value) use ($mapa) {
                $pilhaInfo = explode("=", $value);
                if (isset($pilhaInfo[0], $pilhaInfo[1]) && isset($mapa[$pilhaInfo[0]])) {
                    $mapa[$pilhaInfo[0]] = $pilhaInfo[1];
                }
            }, $infoQueryDivida);

            switch($funcao) {
                case 'getUsuario':
                    return $this->{$funcao}($usuario, $setor);
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

    private static function loadEnv($path) {
        if (!file_exists($path)) {
            throw new Exception("O arquivo .env não existe.");
        }
        $env = [];
        $linhas = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($linhas as $linha) {
            if (strpos(trim($linha), '#') === 0) {
                continue;
            }
    
            list($nome, $valor) = explode('=', $linha, 2);
            $env[$nome] = trim($valor);
        }
        return $env;
    }

    protected function buscaUsuario(string $nome, string $setor) {
        try {
            $sql = $this->conn->prepare('SELECT * FROM usuarios where usuario = :usuario and tela = :setor');
            $nomeUpper = strtoupper($nome);
            $sql->bindParam(':usuario', $nomeUpper);
            $sql->bindParam(':setor', $setor);
            $sql->execute();
            $dados = $sql->fetch(PDO::FETCH_ASSOC);
            if (!$dados) {
                return [
                    'status'=>404,
                    'response'=> "Não foi encontrado nenhum usuario com esse nome e setor, verifique!",
                ];
            }
            return [
                'status'=>200,
                'response'=> "Usuario encontrado com sucesso!!",
                'sql' => $dados
            ];
        } catch (Exception $e) {
            return [
                'status'=> 500,
                'response'=> $e->getMessage(),
            ];
        }
    }

    public function login(string $nome, string $setor, string $senhaUsuario) {
        try {
            $usuario = $this->buscaUsuario($nome, $setor);
            if (isset($usuario['sql']) && $usuario['sql']['first_access'] == 0) {
                $first_access = true;
                $senhaHash = password_hash($senhaUsuario, PASSWORD_DEFAULT); 
                $senha = $this->conn->prepare("
                    UPDATE usuarios SET SENHA = :senha, first_access = :first_access  where id = :id;
                ");
                $senha->bindParam(':senha', $senhaHash);
                $senha->bindParam(':first_access', $first_access);
                $senha->bindParam(':id', $usuario['sql']['id']);

                $senha->execute();
                return [
                    'status'=> 200,
                    'response'=> 'Usuario registrado com sucesso!',
                ];
            } else {
                if (password_verify($senhaUsuario, $usuario['sql']['senha']) && $setor == $usuario['sql']['tela']) {
                    return [
                        'status'=> 200,
                        'response'=> 'Usuario logado com sucesso!',
                    ];
                } else {
                    return [
                        'status'=> 404,
                        'response'=> 'Não foi encontrado o usuario!',
                    ];
                }
            }
        } catch (Exception $e) {
            return [
                'status'=> 500,
                'response'=> $e->getMessage(),
            ];
        }
    }

}
