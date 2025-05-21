# SEMA-PHP

Sistema de gerenciamento para a Secretaria Municipal de Meio Ambiente.

## Estrutura do Projeto

- `/admin/` - Arquivos relacionados ao painel administrativo
- `/assets/` - Recursos estáticos (imagens, CSS, etc.)
  - `/assets/css/` - Arquivos CSS
  - `/assets/img/` - Arquivos de imagem
  - `/assets/SEMA/` - Recursos específicos da SEMA
- `/consultar/` - Arquivos relacionados à consulta de documentos
- `/database/` - Scripts SQL e configurações do banco de dados
- `/includes/` - Arquivos PHP reutilizáveis (funções, modelos, etc.)
- `/php/` - Scripts PHP adicionais
- `/uploads/` - Diretório para arquivos enviados pelos usuários

## Configuração

Para executar este projeto localmente:

1. Configure o servidor web (como Apache/Nginx) apontando para este diretório
2. Importe os scripts SQL na pasta `/database/`
3. Configure o arquivo `includes/config.php` com as credenciais do banco de dados
4. Execute o script `setup_db.php` para configurar o banco de dados
5. Acesse o sistema através do navegador

## Módulos Principais

- **Admin**: Gerenciamento administrativo do sistema
- **Consulta**: Interface pública para consulta de documentos
- **Requerimentos**: Gerenciamento de solicitações e documentos 