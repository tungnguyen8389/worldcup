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
        <p>Mỗi trận đấu sẽ tự động khóa chức năng dự đoán <strong>15 phút trước thời điểm bóng lăn</strong> (theo giờ Việt Nam - GMT+7). Sau thời gian này, bạn không thể tạo mới hay thay đổi tỷ số đã chọn. Đồng thời, dự đoán của tất cả người chơi khác cho trận đấu đó sẽ được hiển thị công khai để mọi người cùng kiểm tra chéo.</p>
        
        <h3 style="color: #fff; margin: 30px 0 10px 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-calculator text-primary" style="color: var(--primary);"></i> 2. Cơ chế Tính điểm (Scoring System)
        </h3>
        <p>Điểm số của mỗi lượt chơi sẽ được tự động tính toán sau khi trận đấu kết thúc dựa trên kết quả chính thức (trong 90 phút thi đấu chính thức và hiệp phụ, không tính loạt sút luân lưu 11m):</p>
        
        <div class="table-responsive" style="margin-top: 15px; margin-bottom: 20px;">
            <table style="background: rgba(0,0,0,0.2); border-radius: 8px; overflow: hidden;">
                <thead>
                    <tr style="background: rgba(255,255,255,0.02);">
                        <th>Kết quả dự đoán</th>
                        <th style="text-align: center;">Điểm cộng</th>
                        <th>Ví dụ (Kết quả thực tế: 2 - 1)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Đoán trúng tỷ số chính xác</strong> (Perfect Match)</td>
                        <td style="text-align: center; color: var(--accent); font-weight: bold; font-size: 18px;">+<?php echo htmlspecialchars(get_setting('point_exact_score', 3)); ?></td>
                        <td>Dự đoán: <strong>2 - 1</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Đoán trúng đội thắng & đúng hiệu số</strong> (Goal Difference)</td>
                        <td style="text-align: center; color: var(--primary); font-weight: bold; font-size: 18px;">+<?php echo htmlspecialchars(get_setting('point_goal_difference', 2)); ?></td>
                        <td>Dự đoán: <strong>3 - 2</strong> (Cùng hiệu số cách biệt +1)</td>
                    </tr>
                    <tr>
                        <td><strong>Đoán trúng kết quả chung cuộc</strong> (Outcome)</td>
                        <td style="text-align: center; color: #fff; font-weight: bold; font-size: 18px;">+<?php echo htmlspecialchars(get_setting('point_correct_outcome', 1)); ?></td>
                        <td>Dự đoán: <strong>1 - 0</strong> hoặc <strong>3 - 0</strong> (Đúng đội thắng nhưng sai tỷ số & hiệu số)</td>
                    </tr>
                    <tr>
                        <td><strong>Đoán sai hoàn toàn</strong> (Incorrect)</td>
                        <td style="text-align: center; color: var(--accent-red); font-weight: bold; font-size: 18px;">0</td>
                        <td>Dự đoán: <strong>1 - 1</strong> hoặc <strong>0 - 2</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h3 style="color: #fff; margin: 30px 0 10px 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-user-secret text-primary" style="color: var(--primary);"></i> 3. Chính sách Bảo mật Danh tính (Nickname vs Tên thật)
        </h3>
        <p>Để tăng phần kịch tính, tò mò và tránh các tác động bên ngoài trong suốt giải đấu:</p>
        <ul style="margin-left: 20px; margin-top: 10px; list-style-type: square;">
            <li><strong>Khi đăng ký:</strong> Bạn phải khai báo <strong>Tên thật</strong> và đặt một <strong>Nickname (Biệt danh)</strong> hiển thị công khai.</li>
            <li><strong>Trong suốt giải đấu:</strong> Mọi người chơi khác chỉ nhìn thấy <strong>Nickname</strong> của bạn trên Bảng xếp hạng và trang lịch sử dự đoán. Chỉ có Admin (Ban tổ chức) mới có quyền đối chiếu xem Nickname này là Tên thật nào của thành viên nhóm.</li>
            <li><strong>Sau giải đấu kết thúc:</strong> Khi Admin bật nút công khai danh tính (hoặc kết thúc giải đấu), hệ thống sẽ hiển thị Tên thật của tất cả người chơi ngay bên cạnh Nickname trên bảng xếp hạng để trao giải.</li>
        </ul>
        
        <h3 style="color: #fff; margin: 30px 0 10px 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-arrows-rotate text-primary" style="color: var(--primary);"></i> 4. Đồng bộ Lịch thi đấu & Kết quả
        </h3>
        <p>Lịch thi đấu và kết quả các trận đấu được cập nhật tự động từ máy chủ API bóng đá quốc tế. Hệ thống sẽ quét và tính điểm tự động cho các dự đoán sau mỗi loạt đấu hàng ngày. Trong trường hợp xảy ra sự cố API, Ban tổ chức sẽ tiến hành cập nhật thủ công để đảm bảo cuộc chơi diễn ra bình thường.</p>
        
        <h3 style="color: #fff; margin: 30px 0 10px 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-ranking-star text-primary" style="color: var(--primary);"></i> 5. Tiêu chí Xếp hạng & Phân định khi Bằng điểm
        </h3>
        <p>Bảng xếp hạng chung cuộc và hàng ngày sẽ được ưu tiên sắp xếp theo thứ tự các tiêu chí sau:</p>
        <ol style="margin-left: 20px; margin-top: 10px;">
            <li><strong>Tổng điểm số:</strong> Thành viên có tổng số điểm cao hơn xếp trên.</li>
            <li><strong>Số trận đoán trúng tỷ số chính xác:</strong> Nếu bằng điểm, thành viên có số trận đoán đúng tỷ số chính xác tuyệt đối nhiều hơn sẽ xếp trên.</li>
            <li><strong>Thời gian dự đoán sớm hơn:</strong> Nếu tiếp tục bằng nhau về cả 2 tiêu chí trên, thành viên có <strong>tổng thời gian hoàn thành dự đoán sớm hơn</strong> (tổng thời gian tạo/cập nhật cuối cùng của các dự đoán nhỏ hơn) sẽ được xếp hạng cao hơn.</li>
            <li><strong>Tên hiển thị (Nickname):</strong> Nếu tất cả các tiêu chí trên vẫn trùng khớp hoàn hảo, thứ tự xếp hạng sẽ được phân theo bảng chữ cái của Nickname.</li>
        </ol>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
