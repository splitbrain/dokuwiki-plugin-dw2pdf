<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Petr Kajzar <petr.kajzar@centrum.cz>
 * @author Kamil Nešetřil <kamil.nesetril@volny.cz>
 * @author Jaroslav Lichtblau <jlichtblau@seznam.cz>
 */
$lang['pagesize']              = 'Formát stránky podporovaný mPDF. Obvykle <code>A4</code> nebo <code>letter</code>.';
$lang['orientation']           = 'Orientace stránky.';
$lang['orientation_o_portrait'] = 'Na výšku';
$lang['orientation_o_landscape'] = 'Na šířku';
$lang['font-size']             = 'Velikost fontu normálního písma v bodech.';
$lang['doublesided']           = 'Oboustranný dokument začíná přidáním liché strany a obsahuje páry sudých a lichých stran. Jednostranný dokument obsahuje pouze liché strany.';
$lang['toc']                   = 'Vložit automaticky vytvořený obsah do PDF (poznámka: může způsobit přidání prázdných stránek při začátku na liché straně, obsah je vždy na sudé straně a nemá žádné vlastní číslo strany)';
$lang['toclevels']             = 'Určit horní úroveň a maximální hloubku podúrovní přidaných do obsahu. Výchozí použité úrovně obsahu wiki jsou <a href="#config___toptoclevel">toptoclevel</a> a <a href="#config___maxtoclevel">maxtoclevel</a>. Formát: <code><i>&lt;top&gt;</i>-<i>&lt;max&gt;</i></code>';
$lang['headernumber']          = 'Aktivovat číslované nadpisy';
$lang['maxbookmarks']          = 'Kolik úrovní oddílů by mělo být použito v záložkách PDF? <small>(0=žádná, 5=všechny)</small>';
$lang['template']              = 'Kterou šablonu je třeba použít pro formátování souborů PDF?';
$lang['output']                = 'Jak má být PDF zobrazeno uživateli?';
$lang['output_o_browser']      = 'Zobrazit v prohlížeči';
$lang['output_o_file']         = 'Stáhnout PDF';
$lang['usecache']              = 'Měly by se soubory PDF ukládat do mezipaměti? (Vložené obrázky nebudou kontrolovány ACL, pokud vás to zajímá z hlediska bezpečnosti, vypněte tuto volbu.)';
$lang['usestyles']             = 'Můžete zadat seznam zásuvných modulů oddělených čárkou, z nichž budou styly <code>style.css</code> nebo <code>screen.css</code> použity pro vytvoření PDF. Ve výchozím nastavení jsou použity pouze <code>print.css</code> a <code>pdf.css</code>.';
$lang['qrcodescale']           = 'Měřítko velikosti vloženého kódu QR. Ponechte prázdně nebo vložte <code>0</code> pro vypnutí.';
$lang['showexportbutton']      = 'Zobrazit tlačítko pro export do formátu PDF (pouze pokud je podporováno vaší šablonou vzhledu)';
