<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author weiqi <yuweiqi_001@126.com>
 * @author lioujheyu <lioujheyu@gmail.com>
 */
$lang['pagesize']              = '由 mPDF 支援的頁面格式。通常為 <code>A4</code> 或 <code>letter</code>。';
$lang['orientation']           = '頁面方向';
$lang['orientation_o_portrait'] = '直式';
$lang['orientation_o_landscape'] = '橫式';
$lang['font-size']             = '正常文字的字型大小（以點為單位）。';
$lang['doublesided']           = '雙面文件從奇數頁開始編排，且包含成對的偶數頁與奇數頁。單面文件僅包含奇數頁。';
$lang['toc']                   = '在PDF中添加自動生成的目錄（註：由於目錄需從奇數頁開始且始終包含在偶數頁上，可能產生空白頁；目錄頁本身不帶頁碼）';
$lang['toclevels']             = '定義添加至目錄的頂層級與最大層級深度。預設使用維基目錄層級 <a href="#config___toptoclevel">toptoclevel</a> 與 <a href="#config___maxtoclevel">maxtoclevel</a>。格式：<code><i>&lt;頂層&gt;</i>-<i>&lt;最大層級&gt;</i></code>';
$lang['headernumber']          = '啟用編號標題';
$lang['maxbookmarks']          = 'PDF 書籤應使用多少層級？<small>(0=無，5=全部)</small>';
$lang['template']              = '應使用哪種範本來格式化PDF檔案？';
$lang['output']                = 'PDF將以何種方式呈現在使用者之前？';
$lang['output_o_browser']      = '以瀏覽器開啟';
$lang['output_o_file']         = '下載PDF檔案';
$lang['usecache']              = '是否應將PDF檔案快取？若啟用此功能，嵌入的圖片將不會經過存取控制清單檢查，若您對此有安全顧慮，請關閉此功能。';
$lang['usestyles']             = '您可以提供以逗號分隔的插件清單，其中應使用 <code>style.css</code> 或 <code>screen.css</code> 進行 PDF 生成。預設僅使用 <code>print.css</code> 和 <code>pdf.css</code>。';
$lang['qrcodescale']           = '嵌入式QR碼的尺寸縮放。若為空值或<code>0</code>則停用此功能。';
$lang['showexportbutton']      = '顯示輸出PDF按鈕 (只在模板支援時才顯示)';
