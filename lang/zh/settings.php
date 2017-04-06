<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Mofe <mofe@me.com>
 * @author oott123 <ip.192.168.1.1@qq.com>
 * @author maddie <2934784245@qq.com>
 */
$lang['pagesize']              = '网页格式支持的根据。通常<代码> A4 >代码>或<代码>字母> >。';
$lang['orientation']           = '页面定位';
$lang['orientation_o_portrait'] = '模型';
$lang['orientation_o_landscape'] = '景观';
$lang['font-size']             = '点中正常文本的字体大小';
$lang['doublesided']           = '双面文件开始添加奇数页，并有偶数对奇数页，单面文件只有奇数页。';
$lang['toc']                   = '添加一个自动生成的目录PDF（注：由于开始在奇数页和TOC总是包括在页，可以添加空白页，偶数TOC页面本身没有页面编号）';
$lang['toclevels']             = '定义添加到TOC的顶层和最大级别深度。默认维基TOC水平< a href =“# config___toptoclevel”> toptoclevel </a>和< a href =“# config___maxtoclevel”> maxtoclevel </a>使用。格式：<代码> < > > & >；顶部& GT；< > > > > & >；最大值';
$lang['maxbookmarks']          = 'PDF 文件中需要有多少层书签？<small>（0：没有；5：全部）</small>';
$lang['template']              = '使用哪个模板的格式来排版PDF的内容？';
$lang['output']                = '怎样显示PDF文件？';
$lang['output_o_browser']      = '在浏览器中显示';
$lang['output_o_file']         = '下载PDF到本地';
$lang['usecache']              = '开启 PDF 文件缓存？内嵌的图片将不会受 ACL 权限限制。如果你担心安全问题，请将其禁用。';
$lang['usestyles']             = 'PDF 生成过程中的 <code>style.css</code> 或者 <code>screen.css</code> ，以英文逗号,分割。默认只有<code>print.css</code> 和 <code>pdf.css</code> 被使用。';
$lang['qrcodesize']            = '二维码尺寸，留空禁用。（<code><i>宽</i><b>x</b><i>高</i></code> 单位：像素）';
$lang['showexportbutton']      = '显示导出 PDF 按钮。（只有在模版支持并在白名单里的时候才能使用）';
