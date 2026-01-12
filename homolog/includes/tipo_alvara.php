<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/models.php';

/**
 * Arquivo temporário para adicionar a classe TipoAlvara ao ambiente
 * Esta classe deve ser integrada ao arquivo models.php corretamente
 */

if (!class_exists('TipoAlvara')) {
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
}
