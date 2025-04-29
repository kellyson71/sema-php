<?php
require_once 'database.php';

/**
 * Classe base para modelos
 */
abstract class Model
{
    protected $db;
    protected $table;

    public function __construct()
    {
        $this->db = new Database();
    }
}

/**
 * Modelo para Requerente
 */
class Requerente extends Model
{
    protected $table = 'requerentes';

    /**
     * Cria um novo requerente
     * @param array $dados Dados do requerente
     * @return int ID do requerente criado
     */
    public function criar($dados)
    {
        return $this->db->insert($this->table, $dados);
    }

    /**
     * Busca um requerente pelo ID
     * @param int $id ID do requerente
     * @return array|false Dados do requerente ou false
     */
    public function buscarPorId($id)
    {
        return $this->db->getRow($this->table, 'id = :id', ['id' => $id]);
    }

    /**
     * Busca um requerente pelo CPF/CNPJ
     * @param string $cpf_cnpj CPF ou CNPJ do requerente
     * @return array|false Dados do requerente ou false
     */
    public function buscarPorCpfCnpj($cpf_cnpj)
    {
        return $this->db->getRow($this->table, 'cpf_cnpj = :cpf_cnpj', ['cpf_cnpj' => $cpf_cnpj]);
    }
}

/**
 * Modelo para Proprietário
 */
class Proprietario extends Model
{
    protected $table = 'proprietarios';

    /**
     * Cria um novo proprietário
     * @param array $dados Dados do proprietário
     * @return int ID do proprietário criado
     */
    public function criar($dados)
    {
        return $this->db->insert($this->table, $dados);
    }

    /**
     * Busca um proprietário pelo ID
     * @param int $id ID do proprietário
     * @return array|false Dados do proprietário ou false
     */
    public function buscarPorId($id)
    {
        return $this->db->getRow($this->table, 'id = :id', ['id' => $id]);
    }
}

/**
 * Modelo para Requerimento
 */
class Requerimento extends Model
{
    protected $table = 'requerimentos';

    /**
     * Cria um novo requerimento
     * @param array $dados Dados do requerimento
     * @return int ID do requerimento criado
     */
    public function criar($dados)
    {
        return $this->db->insert($this->table, $dados);
    }

    /**
     * Busca um requerimento pelo protocolo
     * @param string $protocolo Número do protocolo
     * @return array|false Dados do requerimento ou false
     */
    public function buscarPorProtocolo($protocolo)
    {
        return $this->db->getRow($this->table, 'protocolo = :protocolo', ['protocolo' => $protocolo]);
    }

    /**
     * Busca um requerimento pelo ID
     * @param int $id ID do requerimento
     * @return array|false Dados do requerimento ou false
     */
    public function buscarPorId($id)
    {
        return $this->db->getRow($this->table, 'id = :id', ['id' => $id]);
    }

    /**
     * Atualiza o status de um requerimento
     * @param int $id ID do requerimento
     * @param string $status Novo status
     * @param string $observacoes Observações sobre a atualização (opcional)
     * @return int Número de linhas afetadas
     */
    public function atualizarStatus($id, $status, $observacoes = null)
    {
        $dados = ['status' => $status];

        if ($observacoes !== null) {
            $dados['observacoes'] = $observacoes;
        }

        return $this->db->update($this->table, $dados, 'id = :id', ['id' => $id]);
    }

    /**
     * Lista todos os requerimentos
     * @param int $limite Limite de resultados (opcional)
     * @param string $ordem Coluna de ordenação
     * @param string $direcao Direção da ordenação (ASC ou DESC)
     * @return array Lista de requerimentos
     */
    public function listar($limite = null, $ordem = 'data_envio', $direcao = 'DESC')
    {
        if ($limite) {
            $sql = "SELECT * FROM {$this->table} ORDER BY {$ordem} {$direcao} LIMIT {$limite}";
            return $this->db->query($sql)->fetchAll();
        } else {
            $sql = "SELECT * FROM {$this->table} ORDER BY {$ordem} {$direcao}";
            return $this->db->query($sql)->fetchAll();
        }
    }

    /**
     * Busca requerimentos por tipo de alvará
     * @param string $tipo Tipo de alvará
     * @return array Lista de requerimentos
     */
    public function buscarPorTipo($tipo)
    {
        return $this->db->getRows($this->table, 'tipo_alvara = :tipo', ['tipo' => $tipo]);
    }

    /**
     * Conta o total de requerimentos
     * @return int Total de requerimentos
     */
    public function contarTotal()
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $resultado = $this->db->query($sql)->fetch();
        return $resultado['total'];
    }

    /**
     * Conta requerimentos por status
     * @return array Array associativo com contagem por status
     */
    public function contarPorStatus()
    {
        $sql = "SELECT status, COUNT(*) as total FROM {$this->table} GROUP BY status";
        $resultados = $this->db->query($sql)->fetchAll();

        $contagem = [];
        foreach ($resultados as $resultado) {
            $contagem[strtolower($resultado['status'])] = $resultado['total'];
        }

        return $contagem;
    }
}

/**
 * Modelo para Documento
 */
class Documento extends Model
{
    protected $table = 'documentos';

    /**
     * Cria um novo documento
     * @param array $dados Dados do documento
     * @return int ID do documento criado
     */
    public function criar($dados)
    {
        return $this->db->insert($this->table, $dados);
    }

    /**
     * Busca documentos por requerimento
     * @param int $requerimento_id ID do requerimento
     * @return array Lista de documentos
     */
    public function buscarPorRequerimento($requerimento_id)
    {
        return $this->db->getRows($this->table, 'requerimento_id = :requerimento_id', ['requerimento_id' => $requerimento_id]);
    }
}

/**
 * Modelo para Administrador
 */
class Administrador extends Model
{
    protected $table = 'administradores';

    /**
     * Autentica um administrador
     * @param string $email Email
     * @param string $senha Senha
     * @return array|false Dados do administrador ou false
     */
    public function autenticar($email, $senha)
    {
        $admin = $this->db->getRow($this->table, 'email = :email AND ativo = 1', ['email' => $email]);

        if ($admin && password_verify($senha, $admin['senha'])) {
            // Atualiza o último acesso
            $this->db->update($this->table, ['ultimo_acesso' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $admin['id']]);
            return $admin;
        }

        return false;
    }

    /**
     * Busca um administrador pelo ID
     * @param int $id ID do administrador
     * @return array|false Dados do administrador ou false
     */
    public function buscarPorId($id)
    {
        return $this->db->getRow($this->table, 'id = :id', ['id' => $id]);
    }
}

/**
 * Modelo para Histórico de Ações
 */
class HistoricoAcao extends Model
{
    protected $table = 'historico_acoes';

    /**
     * Registra uma nova ação
     * @param array $dados Dados da ação
     * @return int ID da ação registrada
     */
    public function registrar($dados)
    {
        return $this->db->insert($this->table, $dados);
    }

    /**
     * Busca ações por requerimento
     * @param int $requerimento_id ID do requerimento
     * @return array Lista de ações
     */
    public function buscarPorRequerimento($requerimento_id)
    {
        $sql = "SELECT h.*, a.nome as admin_nome
                FROM {$this->table} h
                LEFT JOIN administradores a ON h.admin_id = a.id
                WHERE h.requerimento_id = :requerimento_id
                ORDER BY h.data_acao DESC";

        return $this->db->query($sql, ['requerimento_id' => $requerimento_id])->fetchAll();
    }
}
