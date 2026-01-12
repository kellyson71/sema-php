<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/models.php';

/**
 * Arquivo temporário para adicionar a classe HistoricoAcao ao ambiente
 * Esta classe deve ser integrada ao arquivo models.php corretamente
 */

if (!class_exists('HistoricoAcao')) {
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

        /**
         * Lista ações recentes
         * @param int $limite Limite de resultados
         * @return array Lista de ações
         */
        public function listarRecentes($limite = 10)
        {
            $sql = "SELECT h.*, a.nome as admin_nome FROM {$this->table} h 
                    LEFT JOIN administradores a ON h.admin_id = a.id
                    ORDER BY h.data_acao DESC LIMIT {$limite}";

            return $this->db->query($sql)->fetchAll();
        }
    }
}
