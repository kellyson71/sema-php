# Módulos do Painel Administrativo

O painel administrativo (`/admin`) concentrada toda a inteligência de gestão da SEMA.

## Principais Páginas e Funções

### Dashboard (`index.php`)

Visão geral com atalhos e contadores de processos pendentes, em análise e urgências.

### Requerimentos (`requerimentos.php`)

- **Lista Global**: Filtros por status, tipo de alvará e busca por protocolo/nome.
- **Ações em Massa**: Seleção múltipla para alteração de status simultânea.

### Visualizar Requerimento (`visualizar_requerimento.php`)

- **Central de Comando**: Visualização de todos os dados do requerente, proprietário e endereço.
- **Gestão de Documentos**: Visualização e download de anexos.
- **Histórico**: Timeline completa de todas as interações no processo.
- **Ações Administrativas**: Indeferir, arquivar, alterar status e enviar e-mails.

### Estatísticas (`estatisticas.php`)

- Fluxo de tempo médio entre estados.
- Gráficos de eficiência mensal.
- Top 5 processos mais rápidos e mais lentos.

### Denúncias (`denuncias.php`)

Gestão das infrações ambientais registradas no município.

### Administradores (`administradores.php`)

Gestão de contas de acesso:

- **Admin**: Acesso total.
- **Secretário**: Módulo de assinatura e revisão.
- **Operador**: Análise técnica e movimentação de processos.

### Logs de E-mail (`logs_email.php`)

Auditoria de todas as comunicações enviadas pelo servidor, essencial para depurar falhas de notificação.

## Segurança e Acesso

- **Login**: Protegido por hash e opcionalmente 2FA.
- **Sessões**: O tempo de inatividade desconecta o usuário automaticamente por segurança.
