<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Aleksandr Selivanov <alexgearbox@yandex.ru>
 * @author Yuriy Skalko <yuriy.skalko@gmail.com>
 * @author Vasilyy Balyasnyy <v.balyasnyy@gmail.com>
 * @author RainbowSpike <1@2.ru>
 */
$lang['pagesize']              = 'Формат страницы, поддерживаемый mPDF. Обычно <code>A4</code> или <code>letter</code>.';
$lang['orientation']           = 'Ориентация страницы.';
$lang['orientation_o_portrait'] = 'Книжная';
$lang['orientation_o_landscape'] = 'Альбомная';
$lang['font-size']             = 'Размер шрифта в пунктах для обычного текста.';
$lang['doublesided']           = 'Двухсторонний документ начинается с нечётной страницы и далее чётные-нечётные пары. Односторонний документ имеет только нечётные страницы.';
$lang['toc']                   = 'Добавить автоматически созданное содержание в PDF. (Замечание: можно добавить пустые страницы, чтобы начиналось с нечётной страницы и содержание всегда включало чётное число страниц. На странице содержания номера не будет.)';
$lang['toclevels']             = 'Определить верхний уровень и максимальное число уровней для включения в содержание. По умолчанию применяются настройки <a href="#config___toptoclevel">toptoclevel</a> и <a href="#config___maxtoclevel">maxtoclevel</a>. Формат: <code><i>&lt;top&gt;</i>-<i>&lt;max&gt;</i></code>';
$lang['headernumber']          = 'Включить нумерованные заголовки';
$lang['maxbookmarks']          = 'Сколько уровней вкладок должно быть использовано для закладок PDF? <small>(0=ничего, 5=все)</small>';
$lang['template']              = 'Какой шаблон должен использоваться для форматирования PDF?';
$lang['output']                = 'Как PDF должен быть представлен пользователю?';
$lang['output_o_browser']      = 'показать в браузере';
$lang['output_o_file']         = 'скачать PDF-файл';
$lang['usecache']              = 'Кэшировать PDF? Встраиваемые изображения не будут проверяться по спискам контроля доступа, поэтому отключите их, если для вас это небезопасно.';
$lang['usestyles']             = 'Вы можете указать разделённый запятыми список плагинов, <code>style.css</code> или <code>screen.css</code> которых должны быть использованы для генерации PDF. По умолчанию используются только <code>print.css</code> и <code>pdf.css</code>.';
$lang['qrcodescale']           = 'Масштаб размера встраиваемого QR-кода. Оставьте пустым или укажите ноль для отключения.';
$lang['showexportbutton']      = 'Показать кнопку экспорта PDF (если поддерживается текущим шаблоном)';
