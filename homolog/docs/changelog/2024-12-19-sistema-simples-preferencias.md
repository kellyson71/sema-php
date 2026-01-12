# Sistema Simples de Preferências UI - 2024-12-19

## Mudança de Arquitetura
- **Antes**: Sistema baseado em SQL com tabela `preferencias_ui` e PDO
- **Depois**: Sistema simples baseado em arquivo JSON

## Arquivos Modificados

### `admin/ajax/salvar_preferencia_ui.php`
- Removido sistema PDO e conexão com banco de dados
- Implementado armazenamento em arquivo JSON (`temp/preferencias_ui.json`)
- Mantida mesma interface de API (POST com `preferencia` e `pagina`)
- Captura automática de IP, User Agent e timestamp

### `admin/preferencias_ui.php`
- Removido sistema PDO e queries SQL
- Implementado leitura direta do arquivo JSON
- Mantida mesma interface visual com estatísticas e tabela detalhada
- Adicionado tratamento para arquivo vazio

## Vantagens da Nova Abordagem
1. **Simplicidade**: Sem necessidade de banco de dados
2. **Portabilidade**: Funciona em qualquer ambiente
3. **Manutenção**: Fácil backup (apenas um arquivo)
4. **Performance**: Leitura/escrita direta em arquivo

## Estrutura do Arquivo JSON
```json
[
  {
    "preferencia": "like|dislike",
    "pagina": "visualizar_requerimento",
    "ip_usuario": "127.0.0.1",
    "user_agent": "Mozilla/5.0...",
    "data_resposta": "2024-12-19 10:30:00"
  }
]
```

## Localização
- Arquivo de dados: `temp/preferencias_ui.json`
- Criação automática do diretório se não existir
- Permissões: 755 para diretório, arquivo com permissões padrão

## Compatibilidade
- Mantida mesma funcionalidade JavaScript no frontend
- Mesma estrutura de resposta JSON da API
- Mesma interface visual para administradores
