<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Eduardo Mozart de Oliveira <eduardomozart182@gmail.com>
 * @author Paulo Schopf <pschopf@gmail.com>
 * @author Rodrigo Pimenta <rodrigo.pimenta@gmail.com>
 */
$lang['pagesize']              = 'Formato da página como suportado pelo mPDF. Geralmente <code>A4</code> ou <code>letter</code> (Carta).';
$lang['orientation']           = 'Orientação da Página';
$lang['orientation_o_portrait'] = 'Retrato';
$lang['orientation_o_landscape'] = 'Paisagem';
$lang['font-size']             = 'Tamanho da fonte para textos normais';
$lang['doublesided']           = 'Um documento frente e verso começa adicionando uma página ímpar e possui pares de páginas pares e ímpares. O documento de lado único tem apenas páginas ímpares.';
$lang['toc']                   = 'Adicionar uma Tabela de Conteúdo (ToC) gerada automaticamente (atenção: pode adicionar páginas em branco se iniciar em uma página ímpar e sempre incluirá no número par de páginas. As páginas ToC em si não tem números de página)';
$lang['toclevels']             = 'Define o nível superior e a profundidade máxima que são adicionados a Tabela de Conteúdo (ToC). Os níveis padrão do wiki <a href="#config___toptoclevel">toptoclevel</a> e <a href="#config___maxtoclevel">maxtoclevel</a> são usados. Formato: <code><i>&lt;top&gt;</i>-<i>&lt;max&gt;</i></code>';
$lang['headernumber']          = 'Ativar cabeçalhos numerados';
$lang['maxbookmarks']          = 'Quantos níveis de seção podem ser utilizados nos bookmarks do PDF? <small>(0=nenhum, 5=todos)</small>';
$lang['template']              = 'Qual modelo será utilizado para formatação dos PDFs?';
$lang['output']                = 'Como o PDF deve ser apresentado ao usuário?';
$lang['output_o_browser']      = 'Mostrar no navegador';
$lang['output_o_file']         = 'Fazer o download do PDF';
$lang['usecache']              = 'Os PDFs devem ser armazenados em cache? Imagens internas não serão checadas pela ACL posteriormente, então deixe desmarcado se deseja esta segurança para você.';
$lang['usestyles']             = 'Você pode fornecer uma lista de plugins, separados por vírgulas, nos quais <code>style.css</code> ou <code>screen.css</code> podem ser utilizados na geração do PDF. Por padrão somente <code>print.css</code> e <code>pdf.css</code> são utilizados.';
$lang['qrcodescale']           = 'Escala de tamanho do código QR embutido. Deixe vazio ou <code>0</code> para desabilitar.';
$lang['showexportbutton']      = 'Mostrar o botão de Exportar para PDF (somente quando suportado pelo template)';
