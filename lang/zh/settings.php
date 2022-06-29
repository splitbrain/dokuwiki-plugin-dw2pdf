<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author piano <linyixuan2019@hotmail.com>
 * @author nero <dreamfox225@hotmail.com>
 * @author Mofe <mofe@me.com>
 * @author oott123 <ip.192.168.1.1@qq.com>
 */
$lang['pagesize']              = 'mPDF 支持的页面格式。通常是 <code>A4</code> 或 <code>letter</code>。';
$lang['orientation']           = '页面方向。';
$lang['orientation_o_portrait'] = '竖向';
$lang['orientation_o_landscape'] = '横向';
$lang['font-size']             = '普通文本的字体大小（以磅为单位）。';
$lang['doublesided']           = '双面文档开始添加奇数页，并有偶数页和奇数页对。单面文档只有奇数页';
$lang['toc']                   = '在 PDF 中添加自动生成的目录（注意：由于从奇数页开始可以添加空白页，并且 ToC 始终包含偶数页，ToC 页面本身没有页码）';
$lang['toclevels']             = '定义添加到 ToC 的顶层和最大层深度。默认 wiki ToC 级别 <a href="#config___toptoclevel">toptoclevel</a> and <a href="#config___maxtoclevel">maxtoclevel</a> 已用过。
格式：<code><i>&lt;top&gt;</i>-<i>&lt;max&gt;</i></code>';
$lang['maxbookmarks']          = 'PDF 文件中需要有多少层书签？<small>（0：没有；5：全部）</small>';
$lang['template']              = '使用哪个模板的格式来排版PDF的内容？';
$lang['output']                = '怎样显示PDF文件？';
$lang['output_o_browser']      = '在浏览器中显示';
$lang['output_o_file']         = '下载PDF到本地';
$lang['usecache']              = '开启 PDF 文件缓存？内嵌的图片将不会受 ACL 权限限制。如果你担心安全问题，请将其禁用。';
$lang['usestyles']             = 'PDF 生成过程中的 <code>style.css</code> 或者 <code>screen.css</code> ，以英文逗号,分割。默认只有<code>print.css</code> 和 <code>pdf.css</code> 被使用。';
$lang['qrcodesize']            = '二维码尺寸，留空禁用。（<code><i>宽</i><b>x</b><i>高</i></code> 单位：像素）';
$lang['showexportbutton']      = '显示导出 PDF 按钮。（只有在模版支持并在白名单里的时候才能使用）';
