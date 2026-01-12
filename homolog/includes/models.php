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
     * @param int $admin_id ID do administrador que realizou a alteração (opcional)
     * @return int Número de linhas afetadas
     */
    public function atualizarStatus($id, $status, $observacoes = null, $admin_id = null)
    {
        $dados = [
            'status' => $status,
            'data_atualizacao' => date('Y-m-d H:i:s')
        ];

        if ($observacoes !== null) {
            $dados['observacoes'] = $observacoes;
        }

        if ($admin_id !== null) {
            $dados['admin_id'] = $admin_id;
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
     */    public function contarPorStatus()
    {
        $sql = "SELECT status, COUNT(*) as total FROM {$this->table} GROUP BY status";
        $resultados = $this->db->query($sql)->fetchAll();

        $contagem = [];
        foreach ($resultados as $resultado) {
            $contagem[$resultado['status']] = $resultado['total'];
        }

        return $contagem;
    }
    /**
     * Conta requerimentos por mês
     * @param int $mes Mês (1-12)
     * @param int $ano Ano (ex: 2023)
     * @return int Número de requerimentos no mês/ano
     */
    public function contarPorMes($mes, $ano)
    {
        $primeiroDia = sprintf('%04d-%02d-01', $ano, $mes);
        $ultimoDia = date('Y-m-t', strtotime($primeiroDia));

        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE data_envio BETWEEN :primeiro AND :ultimo";
        $resultado = $this->db->query($sql, [
            'primeiro' => $primeiroDia,
            'ultimo' => $ultimoDia
        ])->fetch();

        return $resultado['total'];
    }
    /**
     * Lista requerimentos com base em filtros
     * @param array $filtros Filtros a serem aplicados ['status', 'tipo_alvara_id', 'busca', 'data_inicio', 'data_fim']
     * @param int $limite Limite de resultados por página
     * @param int $offset Offset para paginação
     * @return array Lista de requerimentos com dados do requerente
     */
    public function listarComFiltros($filtros, $limite = null, $offset = 0)
    {
        $where = [];
        $params = [];

        // Filtro por status
        if (!empty($filtros['status'])) {
            $where[] = "r.status = :status";
            $params['status'] = $filtros['status'];
        }

        // Filtro por tipo de alvará
        if (!empty($filtros['tipo_alvara_id'])) {
            $where[] = "r.tipo_alvara = :tipo_alvara";
            $params['tipo_alvara'] = $filtros['tipo_alvara_id'];
        }

        // Filtro por busca (protocolo ou requerente)
        if (!empty($filtros['busca'])) {
            $where[] = "(r.protocolo LIKE :busca OR req.nome LIKE :busca_nome)";
            $params['busca'] = '%' . $filtros['busca'] . '%';
            $params['busca_nome'] = '%' . $filtros['busca'] . '%';
        }

        // Filtro por data de início
        if (!empty($filtros['data_inicio'])) {
            $where[] = "r.data_envio >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'] . ' 00:00:00';
        }

        // Filtro por data final
        if (!empty($filtros['data_fim'])) {
            $where[] = "r.data_envio <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'] . ' 23:59:59';
        }

        // Montar cláusula WHERE
        $whereClause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        // Montar SQL com JOIN para buscar dados do requerente
        $sql = "SELECT r.*, req.nome as nome_requerente, req.cpf_cnpj 
                FROM {$this->table} r 
                LEFT JOIN requerentes req ON r.requerente_id = req.id
                {$whereClause} 
                ORDER BY r.data_envio DESC";

        if ($limite !== null) {
            $sql .= " LIMIT {$limite} OFFSET {$offset}";
        }

        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * Conta requerimentos por tipo de alvará
     * @param int $tipoAlvaraId ID do tipo de alvará
     * @return int Número de requerimentos do tipo
     */
    public function contarPorTipoAlvara($tipoAlvaraId)
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE tipo_alvara = :tipo_id";
        $resultado = $this->db->query($sql, ['tipo_id' => $tipoAlvaraId])->fetch();

        return $resultado['total'];
    }
    /**
     * Calcula o tempo médio de processamento por status
     * @param string $status Status do requerimento
     * @return float Tempo médio em dias
     */
    public function calcularTempoMedioPorStatus($status)
    {
        if ($status == 'analise') {
            // Para requerimentos em análise, calcula o tempo desde a data de envio até hoje
            $sql = "SELECT AVG(DATEDIFF(NOW(), data_envio)) as media FROM {$this->table} 
                    WHERE status = 'analise'";
            $resultado = $this->db->query($sql)->fetch();
        } else {
            // Para requerimentos aprovados/rejeitados, calcula o tempo entre envio e atualização
            $sql = "SELECT AVG(DATEDIFF(data_atualizacao, data_envio)) as media FROM {$this->table} 
                    WHERE status = :status";
            $resultado = $this->db->query($sql, ['status' => $status])->fetch();
        }

        return round($resultado['media'] ?? 0, 1);
    }
    /**
     * Conta requerimentos com base em filtros
     * @param array $filtros Filtros a serem aplicados ['status', 'tipo_alvara_id', 'busca', 'data_inicio', 'data_fim']
     * @return int Total de requerimentos que correspondem aos filtros
     */
    public function contarComFiltros($filtros)
    {
        $where = [];
        $params = [];

        // Filtro por status
        if (!empty($filtros['status'])) {
            $where[] = "r.status = :status";
            $params['status'] = $filtros['status'];
        }

        // Filtro por tipo de alvará
        if (!empty($filtros['tipo_alvara_id'])) {
            $where[] = "r.tipo_alvara = :tipo_alvara";
            $params['tipo_alvara'] = $filtros['tipo_alvara_id'];
        }

        // Filtro por busca (protocolo ou requerente)
        if (!empty($filtros['busca'])) {
            $where[] = "(r.protocolo LIKE :busca OR req.nome LIKE :busca_nome)";
            $params['busca'] = '%' . $filtros['busca'] . '%';
            $params['busca_nome'] = '%' . $filtros['busca'] . '%';
        }

        // Filtro por data de início
        if (!empty($filtros['data_inicio'])) {
            $where[] = "r.data_envio >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'] . ' 00:00:00';
        }

        // Filtro por data final
        if (!empty($filtros['data_fim'])) {
            $where[] = "r.data_envio <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'] . ' 23:59:59';
        }

        // Montar cláusula WHERE
        $whereClause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        // Montar SQL para contagem com JOIN para buscar dados do requerente
        $sql = "SELECT COUNT(*) as total 
                FROM {$this->table} r
                LEFT JOIN requerentes req ON r.requerente_id = req.id
                {$whereClause}";
        $resultado = $this->db->query($sql, $params)->fetch();

        return $resultado['total'];
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

    /**
     * Lista todos os documentos
     * @return array Lista de documentos
     */
    public function listar()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY id ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Busca um documento pelo ID
     * @param int $id ID do documento
     * @return array|false Dados do documento ou false
     */
    public function buscarPorId($id)
    {
        return $this->db->getRow($this->table, 'id = :id', ['id' => $id]);
    }

    /**
     * Atualiza um documento
     * @param int $id ID do documento
     * @param array $dados Novos dados
     * @return int Número de linhas afetadas
     */
    public function atualizar($id, $dados)
    {
        return $this->db->update($this->table, $dados, 'id = :id', ['id' => $id]);
    }

    /**
     * Insere um novo documento
     * @param array $dados Dados do documento
     * @return int ID do documento inserido
     */
    public function inserir($dados)
    {
        return $this->db->insert($this->table, $dados);
    }

    /**
     * Exclui um documento
     * @param int $id ID do documento
     * @return int Número de linhas afetadas
     */
    public function excluir($id)
    {
        return $this->db->delete($this->table, 'id = :id', ['id' => $id]);
    }
}

/**
 * Modelo para Documento Administrativo
 */
class DocumentoAdmin extends Model
{
    protected $table = 'documentos_admin';

    /**
     * Lista todos os documentos
     * @return array Lista de documentos
     */
    public function listar()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY nome ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Busca um documento pelo ID
     * @param int $id ID do documento
     * @return array|false Dados do documento ou false
     */
    public function buscarPorId($id)
    {
        return $this->db->getRow($this->table, 'id = :id', ['id' => $id]);
    }

    /**
     * Atualiza um documento
     * @param int $id ID do documento
     * @param array $dados Novos dados
     * @return int Número de linhas afetadas
     */
    public function atualizar($id, $dados)
    {
        return $this->db->update($this->table, $dados, 'id = :id', ['id' => $id]);
    }

    /**
     * Insere um novo documento
     * @param array $dados Dados do documento
     * @return int ID do documento inserido
     */
    public function inserir($dados)
    {
        return $this->db->insert($this->table, $dados);
    }

    /**
     * Exclui um documento
     * @param int $id ID do documento
     * @return int Número de linhas afetadas
     */
    public function excluir($id)
    {
        return $this->db->delete($this->table, 'id = :id', ['id' => $id]);
    }

    /**
     * Busca documentos por tipo de alvará
     * @param int $tipo_alvara_id ID do tipo de alvará
     * @return array Lista de documentos
     */
    public function buscarPorTipoAlvara($tipo_alvara_id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE FIND_IN_SET(:tipo_id, tipos_alvara) > 0 ORDER BY nome ASC";
        return $this->db->query($sql, ['tipo_id' => $tipo_alvara_id])->fetchAll();
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

    /**
     * Lista todos os administradores
     * @return array Lista de administradores
     */
    public function listar()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY nome ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Verifica se um email já existe
     * @param string $email Email a verificar
     * @return bool True se o email já existe
     */
    public function emailExiste($email)
    {
        $admin = $this->db->getRow($this->table, 'email = :email', ['email' => $email]);
        return $admin !== false;
    }

    /**
     * Atualiza um administrador
     * @param int $id ID do administrador
     * @param array $dados Novos dados
     * @return int Número de linhas afetadas
     */
    public function atualizar($id, $dados)
    {
        // Se houver senha, faz o hash
        if (isset($dados['senha']) && !empty($dados['senha'])) {
            $dados['senha'] = password_hash($dados['senha'], PASSWORD_DEFAULT);
        }

        return $this->db->update($this->table, $dados, 'id = :id', ['id' => $id]);
    }

    /**
     * Insere um novo administrador
     * @param array $dados Dados do administrador
     * @return int ID do administrador inserido
     */
    public function inserir($dados)
    {
        // Faz o hash da senha
        if (isset($dados['senha']) && !empty($dados['senha'])) {
            $dados['senha'] = password_hash($dados['senha'], PASSWORD_DEFAULT);
        }

        $dados['ativo'] = $dados['ativo'] ?? 1;
        $dados['data_cadastro'] = date('Y-m-d H:i:s');

        return $this->db->insert($this->table, $dados);
    }

    /**
     * Exclui um administrador
     * @param int $id ID do administrador
     * @return int Número de linhas afetadas
     */
    public function excluir($id)
    {
        return $this->db->delete($this->table, 'id = :id', ['id' => $id]);
    }
}

/**
 * Modelo para Histórico de Ações
 */
class HistoricoAcao extends Model
{
    protected $table = 'historico_acoes';

    /**
     * Registra uma nova ação no histórico
     * @param array $dados Dados da ação
     * @return int ID da ação registrada
     */
    public function registrar($dados)
    {
        $dados['data_acao'] = date('Y-m-d H:i:s');
        return $this->db->insert($this->table, $dados);
    }

    /**
     * Busca ações por requerimento
     * @param int $requerimento_id ID do requerimento
     * @return array Lista de ações
     */
    public function buscarPorRequerimento($requerimento_id)
    {
        $sql = "SELECT h.*, a.nome as admin_nome FROM {$this->table} h 
                LEFT JOIN administradores a ON h.admin_id = a.id
                WHERE h.requerimento_id = :requerimento_id 
                ORDER BY h.data_acao DESC";

        return $this->db->query($sql, ['requerimento_id' => $requerimento_id])->fetchAll();
    }
}

/**
 * Modelo para Tipo de Alvará
 */
class TipoAlvara extends Model
{
    protected $table = 'tipos_alvara';

    /**
     * Lista todos os tipos de alvará
     * @return array Lista de tipos de alvará
     */
    public function listar()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY nome ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Busca um tipo de alvará pelo ID
     * @param int $id ID do tipo de alvará
     * @return array|false Dados do tipo de alvará ou false
     */
    public function buscarPorId($id)
    {
        return $this->db->getRow($this->table, 'id = :id', ['id' => $id]);
    }

    /**
     * Atualiza um tipo de alvará
     * @param int $id ID do tipo de alvará
     * @param array $dados Novos dados
     * @return int Número de linhas afetadas
     */
    public function atualizar($id, $dados)
    {
        return $this->db->update($this->table, $dados, 'id = :id', ['id' => $id]);
    }

    /**
     * Insere um novo tipo de alvará
     * @param array $dados Dados do tipo de alvará
     * @return int ID do tipo de alvará inserido
     */
    public function inserir($dados)
    {
        return $this->db->insert($this->table, $dados);
    }

    /**
     * Exclui um tipo de alvará
     * @param int $id ID do tipo de alvará
     * @return int Número de linhas afetadas
     */
    public function excluir($id)
    {
        return $this->db->delete($this->table, 'id = :id', ['id' => $id]);
    }

    /**
     * Verifica se um tipo de alvará está em uso por algum requerimento
     * @param int $id ID do tipo de alvará
     * @return bool True se estiver em uso
     */
    public function verificarTipoEmUso($id)
    {
        $sql = "SELECT COUNT(*) as total FROM requerimentos WHERE tipo_alvara = :id";
        $resultado = $this->db->query($sql, ['id' => $id])->fetch();
        return $resultado['total'] > 0;
    }
}

/**
 * Modelo para Configurações do Sistema
 */
class Configuracao extends Model
{
    protected $table = 'configuracoes';

    /**
     * Lista todas as configurações
     * @return array Lista de configurações
     */
    public function listarTodas()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY categoria ASC, nome ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Busca uma configuração pelo ID
     * @param int $id ID da configuração
     * @return array|false Dados da configuração ou false
     */
    public function buscarPorId($id)
    {
        return $this->db->getRow($this->table, 'id = :id', ['id' => $id]);
    }

    /**
     * Busca uma configuração pela chave
     * @param string $chave Chave da configuração
     * @return array|false Dados da configuração ou false
     */
    public function buscarPorChave($chave)
    {
        return $this->db->getRow($this->table, 'chave = :chave', ['chave' => $chave]);
    }

    /**
     * Atualiza uma configuração
     * @param int $id ID da configuração
     * @param array $dados Novos dados
     * @return int Número de linhas afetadas
     */
    public function atualizar($id, $dados)
    {
        return $this->db->update($this->table, $dados, 'id = :id', ['id' => $id]);
    }

    /**
     * Obtém o valor de uma configuração pela chave
     * @param string $chave Chave da configuração
     * @param mixed $valorPadrao Valor padrão caso não exista
     * @return mixed Valor da configuração
     */
    public function obterValor($chave, $valorPadrao = null)
    {
        $config = $this->buscarPorChave($chave);
        return $config ? $config['valor'] : $valorPadrao;
    }
}

/**
 * Modelo para Usuários do Sistema
 */
class Usuario extends Model
{
    protected $table = 'usuarios';

    /**
     * Autentica um usuário
     * @param string $email Email
     * @param string $senha Senha
     * @return array|false Dados do usuário ou false
     */
    public function autenticar($email, $senha)
    {
        $usuario = $this->db->getRow($this->table, 'email = :email AND ativo = 1', ['email' => $email]);

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            // Atualiza o último acesso
            $this->db->update($this->table, ['ultimo_acesso' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $usuario['id']]);
            return $usuario;
        }

        return false;
    }

    /**
     * Busca um usuário pelo ID
     * @param int $id ID do usuário
     * @return array|false Dados do usuário ou false
     */
    public function buscarPorId($id)
    {
        return $this->db->getRow($this->table, 'id = :id', ['id' => $id]);
    }

    /**
     * Lista todos os usuários
     * @return array Lista de usuários
     */
    public function listar()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY nome ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Verifica se um email já existe
     * @param string $email Email a verificar
     * @return bool True se o email já existe
     */
    public function emailExiste($email)
    {
        $usuario = $this->db->getRow($this->table, 'email = :email', ['email' => $email]);
        return $usuario !== false;
    }

    /**
     * Atualiza um usuário
     * @param int $id ID do usuário
     * @param array $dados Novos dados
     * @return int Número de linhas afetadas
     */
    public function atualizar($id, $dados)
    {
        // Se houver senha, faz o hash
        if (isset($dados['senha']) && !empty($dados['senha'])) {
            $dados['senha'] = password_hash($dados['senha'], PASSWORD_DEFAULT);
        }

        return $this->db->update($this->table, $dados, 'id = :id', ['id' => $id]);
    }

    /**
     * Insere um novo usuário
     * @param array $dados Dados do usuário
     * @return int ID do usuário inserido
     */
    public function inserir($dados)
    {
        // Faz o hash da senha
        if (isset($dados['senha']) && !empty($dados['senha'])) {
            $dados['senha'] = password_hash($dados['senha'], PASSWORD_DEFAULT);
        }

        $dados['ativo'] = $dados['ativo'] ?? 1;
        $dados['data_cadastro'] = date('Y-m-d H:i:s');

        return $this->db->insert($this->table, $dados);
    }
}