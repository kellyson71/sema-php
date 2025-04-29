<?php
require_once 'config.php';

/**
 * Classe para manipulação do banco de dados
 */
class Database
{
    private $conn;

    /**
     * Construtor que estabelece a conexão com o banco de dados
     */
    public function __construct()
    {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Erro de conexão: " . $e->getMessage());
        }
    }

    /**
     * Executa uma consulta SQL
     * @param string $sql A consulta SQL
     * @param array $params Parâmetros para a consulta
     * @return PDOStatement O resultado da consulta
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            die("Erro na consulta: " . $e->getMessage());
        }
    }

    /**
     * Insere dados em uma tabela
     * @param string $table Nome da tabela
     * @param array $data Dados a serem inseridos
     * @return int ID do registro inserido
     */
    public function insert($table, $data)
    {
        $fields = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($data);
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            die("Erro ao inserir dados: " . $e->getMessage());
        }
    }

    /**
     * Atualiza dados em uma tabela
     * @param string $table Nome da tabela
     * @param array $data Dados a serem atualizados
     * @param string $where Condição para atualização
     * @param array $params Parâmetros para a condição
     * @return int Número de registros afetados
     */
    public function update($table, $data, $where, $params = [])
    {
        $fields = [];
        foreach (array_keys($data) as $field) {
            $fields[] = "{$field} = :{$field}";
        }

        $fields = implode(', ', $fields);
        $sql = "UPDATE {$table} SET {$fields} WHERE {$where}";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(array_merge($data, $params));
            return $stmt->rowCount();
        } catch (PDOException $e) {
            die("Erro ao atualizar dados: " . $e->getMessage());
        }
    }

    /**
     * Obtém um único registro
     * @param string $table Nome da tabela
     * @param string $where Condição
     * @param array $params Parâmetros para a condição
     * @return array|false Registro encontrado ou false
     */
    public function getRow($table, $where, $params = [])
    {
        $sql = "SELECT * FROM {$table} WHERE {$where} LIMIT 1";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            die("Erro ao obter registro: " . $e->getMessage());
        }
    }

    /**
     * Obtém múltiplos registros
     * @param string $table Nome da tabela
     * @param string $where Condição
     * @param array $params Parâmetros para a condição
     * @return array Registros encontrados
     */
    public function getRows($table, $where = '1', $params = [])
    {
        $sql = "SELECT * FROM {$table} WHERE {$where}";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            die("Erro ao obter registros: " . $e->getMessage());
        }
    }
}
