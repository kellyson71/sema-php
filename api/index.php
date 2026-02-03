<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEMA API - Documentação</title>
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --bg: #f9fafb;
            --surface: #ffffff;
            --text-heading: #111827;
            --text-body: #4b5563;
            --border: #e5e7eb;
            --code-bg: #1f2937;
            --code-text: #e5e7eb;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-body);
            background: var(--bg);
            padding: 2rem;
        }

        .container {
            max-width: 960px;
            margin: 0 auto;
        }

        header {
            margin-bottom: 3rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1.5rem;
        }

        h1 {
            color: var(--text-heading);
            font-size: 2.25rem;
            margin-bottom: 0.5rem;
        }

        h2 {
            color: var(--text-heading);
            font-size: 1.5rem;
            margin-top: 2.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary);
            padding-left: 1rem;
        }

        h3 {
            color: var(--text-heading);
            font-size: 1.125rem;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }

        .endpoint {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
        }

        .badge-post {
            background: #dbeafe;
            color: #1e40af;
        }

        .url {
            font-family: monospace;
            background: var(--border);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            margin-left: 0.5rem;
            color: var(--text-heading);
        }

        .description {
            margin: 1rem 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            font-size: 0.95rem;
        }

        th, td {
            text-align: left;
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        th {
            font-weight: 600;
            color: var(--text-heading);
            background: #f3f4f6;
        }

        code {
            font-family: monospace;
            background: #f3f4f6;
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            color: #c026d3;
            font-size: 0.9em;
        }

        pre {
            background: var(--code-bg);
            color: var(--code-text);
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.9rem;
            margin: 1rem 0;
        }

        .required {
            color: #dc2626;
            font-weight: bold;
            font-size: 0.8em;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>SEMA SÃO MIGUEL - Documentação da API</h1>
            <p>Endpoints para integração com o sistema de licenciamento e protocolo da Secretaria Municipal de Meio Ambiente.</p>
        </header>

        <section id="apis">
            <div class="endpoint">
                <div>
                    <span class="badge badge-post">POST</span>
                    <span class="url">/api/enviar_formulario.php</span>
                </div>
                <h2>Submissão de Requerimento</h2>
                <p class="description">Recebe dados do formulário e arquivos para abertura de novo processo. Gera protocolo único.</p>

                <h3>Headers Recomendados</h3>
                <pre>Content-Type: multipart/form-data</pre>

                <h3>Parâmetros (Body)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Campo</th>
                            <th>Tipo</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>tipo_alvara</code> <span class="required">*</span></td>
                            <td>String</td>
                            <td>Código do tipo de alvará (ex: <code>construcao</code>, <code>licenca_ambiental_unica</code>).</td>
                        </tr>
                        <tr>
                            <td><code>requerente[nome]</code> <span class="required">*</span></td>
                            <td>String</td>
                            <td>Nome completo do requerente.</td>
                        </tr>
                        <tr>
                            <td><code>requerente[email]</code> <span class="required">*</span></td>
                            <td>Email</td>
                            <td>Email para recebimento de notificações.</td>
                        </tr>
                        <tr>
                            <td><code>requerente[cpf_cnpj]</code> <span class="required">*</span></td>
                            <td>String</td>
                            <td>CPF ou CNPJ formatado.</td>
                        </tr>
                        <tr>
                            <td><code>endereco_objetivo</code> <span class="required">*</span></td>
                            <td>String</td>
                            <td>Endereço onde será realizado o serviço.</td>
                        </tr>
                        <tr>
                            <td><code>mesmo_requerente</code></td>
                            <td>Boolean</td>
                            <td><code>true</code> se proprietário for o mesmo, <code>false</code> caso contrário.</td>
                        </tr>
                        <tr>
                            <td><code>doc_{tipo}_{idx}</code></td>
                            <td>File (PDF)</td>
                            <td>Arquivos de documentos (ex: <code>doc_construcao_0</code>). Máx 10MB.</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Exemplo de Resposta (Sucesso 201)</h3>
<pre>{
    "success": true,
    "message": "Requerimento enviado com sucesso.",
    "data": {
        "protocolo": "20250201123456789",
        "requerimento_id": 42,
        "email_enviado": true,
        "arquivos_processados": 3
    }
}</pre>

<h3>Exemplo de Resposta (Erro 400)</h3>
<pre>{
    "success": false,
    "message": "Faltam informações ambientais obrigatórias: Número do CTF, Comprovante de pagamento",
    "data": []
}</pre>
            </div>

            <div class="endpoint">
                <div>
                    <span class="badge badge-post">POST</span>
                    <span class="url">/api/reenviar_confirmacao.php</span>
                </div>
                <h2>Reenvio de Email de Confirmação</h2>
                <p class="description">Dispara novamente o email de confirmação de abertura de processo.</p>

                <h3>Headers</h3>
                <pre>Content-Type: application/json</pre>

                <h3>Parâmetros (JSON Body)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Campo</th>
                            <th>Tipo</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>protocolo</code></td>
                            <td>String</td>
                            <td>Número do protocolo do requerimento. (Obrigatório se ID não for informado)</td>
                        </tr>
                        <tr>
                            <td><code>requerimento_id</code></td>
                            <td>Int</td>
                            <td>ID interno do requerimento. (Alternativa ao protocolo)</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Exemplo de Request</h3>
<pre>{
    "protocolo": "20250201123456789"
}</pre>

                <h3>Exemplo de Resposta (Sucesso 200)</h3>
<pre>{
    "success": true,
    "message": "Email de confirmação enviado com sucesso.",
    "data": {
        "email_destino": "usuario@exemplo.com",
        "protocolo": "20250201123456789"
    }
}</pre>
            </div>
        </section>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> SEMA - Secretaria Municipal de Meio Ambiente. Todos os direitos reservados.</p>
        </footer>
    </div>
</body>
</html>
