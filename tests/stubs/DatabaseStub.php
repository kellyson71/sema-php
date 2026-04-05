<?php

/**
 * Stub da classe Database para uso em testes unitários.
 * Não realiza nenhuma conexão com banco de dados real.
 */
class Database
{
    public function query($sql, $params = []) { return null; }
    public function insert($table, $data) { return 1; }
    public function update($table, $data, $where, $params = []) { return 1; }
    public function getRow($table, $where, $params = []) { return false; }
    public function getRows($table, $where = '1', $params = []) { return []; }
    public function delete($table, $where, $params = []) { return 0; }
}
