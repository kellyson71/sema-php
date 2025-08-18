# Sistema de Feedback Local

## Visão Geral
Este sistema coleta feedback dos usuários sobre melhorias na interface e armazena localmente em arquivo JSON.

## Arquivos do Sistema

### 1. `feedback_handler.php`
- **Função**: API para processar feedback
- **Métodos**: POST
- **Ações**:
  - `submit_feedback`: Salva novo feedback
  - `get_stats`: Retorna estatísticas dos feedbacks

### 2. `feedback_data.json`
- **Função**: Armazena todos os feedbacks
- **Formato**: JSON
- **Limite**: 1000 entradas (mais antigas são removidas automaticamente)

### 3. `feedback_data_example.json`
- **Função**: Exemplo da estrutura dos dados
- **Uso**: Referência para desenvolvedores

## Estrutura dos Dados

```json
{
  "id": "identificador_unico",
  "timestamp": "2024-01-15 14:30:25",
  "type": "ui_improvement",
  "rating": "like|dislike",
  "user_agent": "Navegador do usuário",
  "ip": "Endereço IP",
  "page": "Página onde foi dado o feedback",
  "comment": "Comentário opcional"
}
```

## Como Funciona

### 1. Coleta de Feedback
- Usuário vê mensagem tutorial discreta
- Clica em "Gostei" ou "Prefiro antigo"
- Sistema salva automaticamente no JSON local

### 2. Armazenamento
- Feedback é enviado via AJAX para `feedback_handler.php`
- Dados são validados e salvos em `feedback_data.json`
- Sistema mantém limite de 1000 entradas

### 3. Segurança
- Validação de dados de entrada
- Sanitização de informações
- Log de erros para debugging

## Configurações

### Limite de Entradas
```php
$maxFeedbackEntries = 1000; // Máximo de feedbacks armazenados
```

### Arquivo de Dados
```php
$feedbackFile = 'feedback_data.json'; // Caminho do arquivo JSON
```

## Uso da API

### Enviar Feedback
```javascript
fetch('feedback_handler.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        action: 'submit_feedback',
        type: 'ui_improvement',
        rating: 'like',
        page: 'visualizar_requerimento.php',
        comment: 'Comentário opcional'
    })
});
```

### Obter Estatísticas
```javascript
fetch('feedback_handler.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_stats' })
});
```

## Mensagem Tutorial

### Características
- **Discreta**: Tamanho reduzido e opacidade baixa
- **Responsiva**: Adapta-se a dispositivos móveis
- **Interativa**: Hover aumenta opacidade
- **Fechável**: Botão para esconder permanentemente

### Estilos
- Fonte pequena (0.75rem)
- Opacidade 0.85 (hover: 1.0)
- Botões compactos
- Cores suaves

## Manutenção

### Backup
- Faça backup regular do arquivo `feedback_data.json`
- O sistema mantém apenas as 1000 entradas mais recentes

### Monitoramento
- Verifique logs de erro do PHP
- Monitore o tamanho do arquivo JSON
- Analise estatísticas periodicamente

### Limpeza
- O sistema limpa automaticamente entradas antigas
- Pode ser configurado para manter mais ou menos entradas

## Exemplo de Estatísticas

```json
{
  "total": 150,
  "by_type": {
    "ui_improvement": 150
  },
  "by_rating": {
    "like": 120,
    "dislike": 30
  },
  "recent": [
    // Últimos 10 feedbacks
  ]
}
```

## Troubleshooting

### Erro ao Salvar
- Verifique permissões de escrita na pasta
- Confirme se o PHP tem acesso ao diretório
- Verifique logs de erro do PHP

### Arquivo Não Encontrado
- O sistema cria automaticamente se não existir
- Verifique permissões de criação de arquivos

### Dados Corrompidos
- Faça backup antes de qualquer correção
- Verifique sintaxe JSON
- Restaure de backup se necessário
