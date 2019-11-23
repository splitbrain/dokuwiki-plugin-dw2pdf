<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Schopf <pschopf@gmail.com>
 * @author Juliano Marconi Lanigra <juliano.marconi@gmail.com>
 */
$lang['pagesize']              = 'O formato da página, suportado pelo mPDF. Normalmente <code>A4</code> ou <code>carta</code>.';
$lang['orientation']           = 'A orientação da página.';
$lang['orientation_o_portrait'] = 'Retrato';
$lang['orientation_o_landscape'] = 'Paisagem';
$lang['font-size']             = 'O tamanho da fonte do texto normal em pontos.';
$lang['doublesided']           = 'O documento em frente e verso começa com página ímpar e possui pares de páginas pares e ímpares. O documento de lado único possui apenas páginas ímpares.';
$lang['toc']                   = 'Adicionar um índice gerado automaticamente em PDF (observação: Podem ser adicionadas páginas em branco devido ao início com uma página ímpar e o índice sempre incluir um número par de páginas. Páginas do índice não possuem números de página)';
$lang['toclevels']             = 'Defina o nível superior e a profundidade máxima que serão adicionados ao índice. Níveis padrão de índice da wiki <a href="#config___toptoclevel">toptoclevel</a> and <a href="#config___maxtoclevel">maxtoclevel</a> são usados. Formato: <code><i>&lt;top&gt;</i>-<i>&lt;max&gt;</i></code>';
$lang['maxbookmarks']          = 'Quantos níveis de seções devem ser usados nos marcadores PDF? <small>(0=nenhum, 5=todos)</small>';
$lang['template']              = 'Qual tema deve ser usado para formatar os PDFs?';
$lang['output']                = 'Como o PDF deve ser apresentado ao usuário?';
$lang['output_o_browser']      = 'Mostrar no navegador';
$lang['output_o_file']         = 'Fazer o download do PDF';
$lang['usecache']              = 'Os PDFs devem ser armazenados em cache? Imagens incorporadas não serão checadas pelo ACL. Desabilite se isso é um problema de segurança para você.';
$lang['usestyles']             = 'Você pode gerar uma lista de plugins separadas por vírgula nos quais <code>style.css</code> ou <code>screen.css</code> devem ser usados para gerar o PDF. O padrão é usar somente <code>print.css</code> e <code>pdf.css</code>.';
$lang['qrcodesize']            = 'Tamanho do QR code incorporado (em pixels <code><i>largura</i><b>x</b><i>altura</i></code>). Deixe vazio para desabilitar';
$lang['showexportbutton']      = 'Mostrar botão de exportação de PDF (quando suportado pelo tema)';
