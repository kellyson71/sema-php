<?php
/**
 * DocumentBuilder.php — Renderiza documentos HTML a partir de definições PHP
 *
 * Cada tipo de documento é um array PHP em admin/templates/definitions/.
 * O builder percorre os blocos e chama os Components correspondentes,
 * gerando HTML com estilos inline prontos para TCPDF e Summernote.
 *
 * Uso:
 *   $builder = new DocumentBuilder();
 *   $html = $builder->render('alvara_construcao');
 *   // O HTML retornado ainda contém {{variáveis}} — o ParecerService preenche depois.
 */

require_once __DIR__ . '/Components.php';

class DocumentBuilder
{
    private string $definitionsPath;

    public function __construct(?string $definitionsPath = null)
    {
        $this->definitionsPath = $definitionsPath ?? __DIR__ . '/../definitions';
    }

    /**
     * Renderiza um documento a partir de seu nome de definição.
     *
     * @param string $nome Nome do arquivo em definitions/ (sem .php)
     * @return string HTML completo com {{variáveis}} para preenchimento
     * @throws Exception Se a definição não existir
     */
    public function render(string $nome): string
    {
        $arquivo = $this->definitionsPath . '/' . basename($nome) . '.php';

        if (!file_exists($arquivo)) {
            throw new \Exception("Definição de documento não encontrada: {$nome}");
        }

        $definicao = require $arquivo;

        if (!is_array($definicao) || empty($definicao['blocos'])) {
            throw new \Exception("Definição inválida em: {$nome}");
        }

        return $this->renderBlocos($definicao['blocos']);
    }

    /**
     * Lista todas as definições disponíveis.
     *
     * @return array ['nome' => ['label' => '...', 'descricao' => '...', ...], ...]
     */
    public function listarDefinicoes(): array
    {
        $lista = [];
        $arquivos = glob($this->definitionsPath . '/*.php');

        foreach ($arquivos as $arq) {
            $nome = basename($arq, '.php');
            $def  = require $arq;
            $lista[$nome] = [
                'label'     => $def['label'] ?? $nome,
                'descricao' => $def['descricao'] ?? '',
                'icone'     => $def['icone'] ?? 'fa-file-signature',
                'badge'     => $def['badge'] ?? 'Documento',
            ];
        }

        return $lista;
    }

    /**
     * Verifica se existe uma definição para o nome dado.
     */
    public function existeDefinicao(string $nome): bool
    {
        return file_exists($this->definitionsPath . '/' . basename($nome) . '.php');
    }

    /**
     * Renderiza uma lista de blocos em HTML.
     */
    private function renderBlocos(array $blocos): string
    {
        $html = '';

        foreach ($blocos as $bloco) {
            $tipo = $bloco['tipo'] ?? '';

            switch ($tipo) {
                case 'titulo':
                    $html .= Components::titulo(
                        $bloco['texto'],
                        $bloco['subtexto'] ?? ''
                    );
                    break;

                case 'subtitulo':
                    $html .= Components::subtitulo($bloco['texto']);
                    break;

                case 'secao':
                    $html .= Components::secao($bloco['texto']);
                    break;

                case 'tabela':
                    $html .= Components::tabela(
                        $bloco['linhas'],
                        $bloco['label_width'] ?? 30
                    );
                    break;

                case 'texto':
                    $html .= Components::texto($bloco['conteudo']);
                    break;

                case 'paragrafo':
                    $html .= Components::paragrafo($bloco['texto']);
                    break;

                case 'paragrafos':
                    $html .= Components::paragrafos($bloco['textos']);
                    break;

                case 'condicionantes':
                    $html .= Components::condicionantes(
                        $bloco['itens'],
                        $bloco['titulo'] ?? 'CONDICIONANTES:'
                    );
                    break;

                case 'data_local':
                    $html .= Components::dataLocal($bloco['data'] ?? '{{data_atual}}');
                    break;

                case 'assinatura':
                    $html .= Components::assinatura(
                        $bloco['nome'] ?? 'VICENTE DE PAULA FERNANDES',
                        $bloco['cargo'] ?? 'SECRETÁRIO MUNICIPAL DE MEIO AMBIENTE – SEMA.<br>PORTARIA 010/2025'
                    );
                    break;

                case 'dados_inline':
                    $html .= Components::dadosInline($bloco['dados']);
                    break;

                case 'html':
                    // Bloco de HTML livre (para casos especiais)
                    $html .= $bloco['conteudo'];
                    break;

                default:
                    // Tipo desconhecido — ignora silenciosamente
                    break;
            }
        }

        return $html;
    }
}
