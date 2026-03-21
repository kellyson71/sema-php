# Arquitetura Técnica - SEMA-PHP

## Stack Tecnológica

O sistema é construído sobre pilhas tradicionais e robustas para facilitar a manutenção em ambientes de hospedagem compartilhada ou servidores simples.

- **Linguagem**: PHP (Procedural e Orientado a Objetos em serviços específicos).
- **Banco de Dados**: MariaDB / MySQL (original) com suporte a SQLite para testes.
- **Frontend**: HTML5, Vanilla CSS, Vanilla JavaScript.
- **Bibliotecas Externas**:
    - **Chart.js**: Gerenciamento de gráficos no painel.
    - **TinyMCE**: Editor de texto rico para pareceres.
    - **Bootstrap**: Grid e componentes UI básicos.
    - **FontAwesome**: Ícones do sistema.

## Estrutura de Diretórios

```text
sema-php/
├── admin/            # Painel administrativo (acesso restrito)
│   ├── ajax/         # Requisições assíncronas
│   └── includes/     # Componentes internos do admin
├── docs/             # Documentação do sistema e SQLs
│   └── contexto/     # Documentação para desenvolvedores (esta pasta)
├── includes/         # Lógica compartilhada (E-mail, DB, Config)
├── assets/           # Arquivos estáticos (Imagens, PDF templates)
├── uploads/          # Armazenamento de documentos enviados (Ignorado no Git)
├── vendor/           # Dependências do Composer
└── index.php         # Ponto de entrada público
```

## Padrões de Código

- **Conexão**: Centralizada no arquivo `admin/conexao.php`.
- **Sessão**: Verificada via `verificaLogin()` em cada página administrativa.
- **Processamento**: Formulários costumam processar dados no mesmo arquivo ou em arquivos com sufixo `_handler.php`.
- **E-mails**: Gerenciados pela classe `EmailService` em `includes/email_service.php`.
