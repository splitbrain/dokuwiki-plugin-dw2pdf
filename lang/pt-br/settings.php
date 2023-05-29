<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Daniel Dias Rodrigues <danieldiasr@gmail.com>
 * @author Schopf <pschopf@gmail.com>
 * @author Juliano Marconi Lanigra <juliano.marconi@gmail.com>
 */
$lang['pagesize']              = 'O formato de página como suportado pelo mPDF. Normalmente <code>A4</code> ou <code>carta</code>.';
$lang['orientation']           = 'A orientação da página.';
$lang['orientation_o_portrait'] = 'Retrato';
$lang['orientation_o_landscape'] = 'Paisagem';
$lang['font-size']             = 'O tamanho da fonte para texto normal em pontos.';
$lang['doublesided']           = 'O documento frente e verso começa a adicionar páginas ímpares e tem pares de páginas pares e ímpares. O documento de um lado tem apenas páginas ímpares.';
$lang['toc']                   = 'Adicionar um índice gerado automaticamente em PDF (observação: É possível adicionar páginas em branco devido ao início de uma página ímpar e o Índice sempre incluir número par de páginas. As páginas do Índice não possuem números de página)';
$lang['toclevels']             = 'Definir o nível superior e a profundidade máxima que são adicionados ao índice. Níveis padrões <a href="#config___toptoclevel">toptoclevel</a> e <a href="#config___maxtoclevel">maxtoclevel</a> são usados. Formato: <code><i>&lt;top&gt;</i>-<i>&lt;max&gt;</i></code>';
$lang['headernumber']          = 'Ativar cabeçalhos numerados';
$lang['maxbookmarks']          = 'Quantos níveis de seções devem ser usados nos marcadores PDF? <small>(0=none, 5=all)</small>';
$lang['template']              = 'Qual modelo deve ser usado para formatar os PDFs?';
$lang['output']                = 'Como o PDF deve ser apresentado ao usuário?';
$lang['output_o_browser']      = 'Mostrar no navegador';
$lang['output_o_file']         = 'Baixar o PDF';
$lang['usecache']              = 'Os PDFs devem ser armazenados em cache? Imagens incorporadas não serão checadas com ACL posteriormente, desative se isso é um problema de segurança para você.';
$lang['usestyles']             = 'Você pode gerar uma lista de plugins separadas por vírgula nos quais <code>style.css</code> ou <code>screen.css</code> devem ser usadas para a gerar um PDF.';
$lang['qrcodescale']           = 'Escala de tamanho do código QR incorporado. Vazio ou <code>0</code> para desativar.';
$lang['showexportbutton']      = 'Mostrar botão de exportação de PDF (se suportado pelo modelo)';
