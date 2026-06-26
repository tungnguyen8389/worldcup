<?php
// rules.php
$page_title = "Luật chơi";
require_once __DIR__ . '/includes/header.php';
require_login();
?>

<div class="card fade-in">
    <div class="card-title">
        <i class="fa-solid fa-book-open"></i> Luật Chơi & Cơ Chế Dự Đoán
    </div>
    
    <div style="line-height: 1.8; color: var(--text-muted);">
        <h3 style="color: #fff; margin: 20px 0 10px 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-clock text-primary" style="color: var(--primary);"></i> 1. Thời gian Khóa Dự đoán
        </h3>
        <p>Mỗi trận đấu sẽ tự động khóa chức năng dự đoán <strong>15 phút trước thời điểm bóng lăn</strong> (theo giờ Việt Nam - GMT+7). Sau thời gian này, bạn không thể tạo mới hay thay đổi đội tuyển dự đoán. Dự đoán của tất cả người chơi khác cho trận đấu luôn được hiển thị công khai, minh bạch để mọi người cùng theo dõi.</p>
        
        <h3 style="color: #fff; margin: 30px 0 10px 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-calculator text-primary" style="color: var(--primary);"></i> 2. Cơ chế Dự đoán & Tính điểm (Scoring System)
        </h3>
        <p>Mỗi trận đấu sẽ được Ban tổ chức áp dụng một tỷ lệ <strong>kèo chấp (Handicap)</strong>. Bạn sẽ đưa ra dự đoán bằng cách chọn đội giành chiến thắng sau khi áp dụng tỷ lệ kèo chấp này.</p>
        <p>Điểm số và kết quả thắng/thua được tính dựa trên kết quả chính thức của trận đấu (gồm 90 phút thi đấu chính thức và hiệp phụ nếu có, không tính loạt sút luân lưu 11m):</p>
        
        <div class="table-responsive" style="margin-top: 15px; margin-bottom: 20px;">
            <table style="background: rgba(0,0,0,0.2); border-radius: 8px; overflow: hidden;">
                <thead>
                    <tr style="background: rgba(255,255,255,0.02);">
                        <th>Kết quả dự đoán (sau Handicap)</th>
                        <th style="text-align: center; width: 120px;">Điểm nhận được</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Đoán đúng đội thắng kèo</strong></td>
                        <td style="text-align: center; color: var(--accent); font-weight: bold; font-size: 18px;">+1</td>
                        <td><span style="color: var(--accent); font-weight: bold;">Thắng (Win)</span></td>
                    </tr>
                    <tr>
                        <td><strong>Hòa kèo</strong> (điểm số sau handicap bằng tỷ số đội khách)</td>
                        <td style="text-align: center; color: var(--text-muted); font-weight: bold; font-size: 18px;">0</td>
                        <td><span style="color: var(--text-muted);">Hòa (Draw)</span></td>
                    </tr>
                    <tr>
                        <td><strong>Đoán sai đội thắng kèo</strong></td>
                        <td style="text-align: center; color: var(--accent-red); font-weight: bold; font-size: 18px;">-1</td>
                        <td><span style="color: var(--accent-red); font-weight: bold;">Thua (Loss)</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="background: rgba(255,255,255,0.02); border-left: 3px solid var(--accent); padding: 12px; border-radius: 4px; font-size: 13.5px; margin-bottom: 20px;">
            <strong>Ví dụ minh họa:</strong> Trận đấu giữa <strong>Brazil vs Cameroon</strong>, Brazil chấp <strong>1.5</strong> bàn (Handicap = 1.5). Tỷ số thực tế là Brazil <strong>2 - 1</strong> Cameroon.
            <ul style="margin-left: 20px; margin-top: 5px; list-style-type: disc;">
                <li>Điểm điều chỉnh của Brazil = 2 - 1.5 = 0.5 bàn.</li>
                <li>So sánh: 0.5 (Brazil) &lt; 1 (Cameroon) &rarr; Cameroon thắng kèo.</li>
                <li>Nếu bạn chọn <strong>Cameroon</strong>: Bạn được <strong>+1 điểm</strong>.</li>
                <li>Nếu bạn chọn <strong>Brazil</strong>: Bạn bị <strong>-1 điểm</strong>.</li>
            </ul>
        </div>
        
        <h3 style="color: #fff; margin: 30px 0 10px 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-ranking-star text-primary" style="color: var(--primary);"></i> 3. Tiêu chí Xếp hạng & Phân định khi Bằng điểm
        </h3>
        <p>Bảng xếp hạng chung cuộc và hàng ngày sẽ được ưu tiên sắp xếp theo thứ tự các tiêu chí sau:</p>
        <ol style="margin-left: 20px; margin-top: 10px;">
            <li><strong>Tổng điểm số:</strong> Thành viên có tổng số điểm cao hơn xếp trên.</li>
            <li><strong>Số trận thắng kèo (Win):</strong> Nếu bằng điểm, thành viên có số trận thắng kèo nhiều hơn sẽ xếp trên.</li>
            <li><strong>Thời gian dự đoán sớm hơn:</strong> Nếu tiếp tục bằng nhau về cả 2 tiêu chí trên, thành viên có <strong>tổng thời gian hoàn thành dự đoán sớm hơn</strong> (tổng thời gian tạo/cập nhật cuối cùng của các dự đoán nhỏ hơn) sẽ được xếp hạng cao hơn.</li>
            <li><strong>Tên hiển thị (Nickname):</strong> Nếu tất cả các tiêu chí trên vẫn trùng khớp hoàn hảo, thứ tự xếp hạng sẽ được phân theo bảng chữ cái của Nickname.</li>
        </ol>

        <h3 style="color: #fff; margin: 30px 0 10px 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-user-secret text-primary" style="color: var(--primary);"></i> 4. Chính sách Bảo mật Danh tính (Nickname vs Tên thật)
        </h3>
        <p>Để tăng phần kịch tính, tò mò và tránh các tác động bên ngoài trong suốt giải đấu:</p>
        <ul style="margin-left: 20px; margin-top: 10px; list-style-type: square;">
            <li><strong>Khi đăng ký:</strong> Bạn phải khai báo <strong>Tên thật</strong> và đặt một <strong>Nickname (Biệt danh)</strong> hiển thị công khai.</li>
            <li><strong>Trong suốt giải đấu:</strong> Mọi người chơi khác chỉ nhìn thấy <strong>Nickname</strong> của bạn trên Bảng xếp hạng và trang lịch sử dự đoán. Chỉ có Admin (Ban tổ chức) mới có quyền đối chiếu xem Nickname này là Tên thật nào của thành viên nhóm.</li>
            <li><strong>Sau giải đấu kết thúc:</strong> Khi Admin bật nút công khai danh tính (hoặc kết thúc giải đấu), hệ thống sẽ hiển thị Tên thật của tất cả người chơi ngay bên cạnh Nickname trên bảng xếp hạng để trao giải.</li>
        </ul>
        
        <h3 style="color: #fff; margin: 30px 0 10px 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-triangle-exclamation text-primary" style="color: var(--primary);"></i> 5. Mục đích Giải trí & Miễn trừ Trách nhiệm
        </h3>
        <p>Website này được xây dựng và vận hành chỉ phục vụ mục đích hội nhóm nội bộ tham gia tương tác, giao lưu để tăng thêm không khí vui vẻ và kịch tính cho giải đấu. Hệ thống hoàn toàn <strong>không mang hình thức cá cược, cờ bạc hay ăn thua bằng tiền mặt</strong>.</p>
        <p>Ban tổ chức (Quản trị viên) xin <strong>miễn trừ mọi trách nhiệm pháp lý</strong> nếu phát sinh bất kỳ hoạt động giao dịch, cá cược ăn thua bằng tiền mặt hoặc tài sản nào khác giữa các thành viên ngoài phạm vi quản lý của trang web này. Mọi hành vi lợi dụng nền tảng này vào mục đích cá cược trái quy định đều bị nghiêm cấm.</p>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
