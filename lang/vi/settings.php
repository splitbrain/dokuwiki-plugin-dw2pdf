<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Thien Hau <thienhau.9a14@gmail.com>
 */
$lang['pagesize']              = 'Định dạng trang được hỗ trợ bởi mPDF. Thường là <code>A4</code> hoặc <code>letter</code>.';
$lang['orientation']           = 'Hướng trang';
$lang['orientation_o_portrait'] = 'Dọc';
$lang['orientation_o_landscape'] = 'Ngang';
$lang['font-size']             = 'Cỡ chữ cho văn bản bình thường theo điểm.';
$lang['doublesided']           = 'Tài liệu hai mặt bắt đầu thêm trang lẻ và có các cặp trang chẵn và lẻ.  Tài liệu một mặt chỉ có các trang lẻ.';
$lang['toc']                   = 'Thêm Mục lục được tạo tự động vào PDF (lưu ý: Có thể thêm các trang trống do bắt đầu tại một trang lẻ và Mục lục luôn bao gồm số trang chẵn, bản thân các trang Mục lục không có số trang)';
$lang['toclevels']             = 'Xác định cấp cao nhất và cấp độ sâu tối đa được thêm vào Mục lục.  Các mức Mục lục mặc định của wiki là <a href="#config___toptoclevel">toptoclevel</a> và <a href="#config___maxtoclevel">maxtoclevel</a> được sử dụng. Định dạng: <code><i>&lt;hàng đầu&gt;</i>-<i>&lt;tối đa&gt;</i></code>';
$lang['maxbookmarks']          = 'Có bao nhiêu cấp độ đầu đề được sử dụng trong dấu trang PDF?<small>(0=không có, 5=tất cả)</small>';
$lang['template']              = 'Chủ đề nào nên được sử dụng để định dạng các tập tin PDF?';
$lang['output']                = 'PDF nên được trình bày cho người dùng như thế nào?';
$lang['output_o_browser']      = 'Xem trong trình duyệt';
$lang['output_o_file']         = 'Tải về PDF';
$lang['usecache']              = 'PDF có nên được lưu trữ?  Sau đó, hình ảnh nhúng sẽ không được ACL kiểm tra, vô hiệu hóa nếu đó là vấn đề bảo mật cho bạn.';
$lang['usestyles']             = 'Bạn có thể đưa ra một danh sách các plugin được phân tách bằng dấu phẩy của <code>style.css</code> hoặc <code>screen.css</code> nên được sử dụng để tạo PDF.  Theo mặc định chỉ có <code>print.css</code> và <code>pdf.css</code> được sử dụng';
$lang['qrcodesize']            = 'Kích thước của mã QR nhúng (tính bằng <code><i>&lt;chiều rộng&gt;</i><b>x</b><i>&lt;chiều cao&gt;</i></code> pixel). Để trống để vô hiệu hóa';
$lang['showexportbutton']      = 'Hiển thị nút xuất PDF (chỉ khi được hỗ trợ bởi chủ đề của bạn)';
