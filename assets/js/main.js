// assets/js/main.js

/**
 * Hiển thị Toast thông báo đẹp mắt ở góc màn hình
 */
function showToast(message, type = 'success') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.position = 'fixed';
        toastContainer.style.bottom = '20px';
        toastContainer.style.right = '20px';
        toastContainer.style.zIndex = '1000';
        toastContainer.style.display = 'flex';
        toastContainer.style.flexDirection = 'column';
        toastContainer.style.gap = '10px';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark'}"></i> ${message}`;
    
    // Style trực tiếp để đảm bảo hiển thị đẹp và tránh thiếu class CSS
    toast.style.background = type === 'success' ? 'rgba(0, 255, 170, 0.18)' : 'rgba(255, 62, 108, 0.18)';
    toast.style.border = type === 'success' ? '1px solid #00ffaa' : '1px solid #ff3e6c';
    toast.style.color = '#fff';
    toast.style.padding = '14px 22px';
    toast.style.borderRadius = '10px';
    toast.style.backdropFilter = 'blur(12px)';
    toast.style.boxShadow = '0 8px 32px 0 rgba(0, 0, 0, 0.4)';
    toast.style.minWidth = '240px';
    toast.style.fontSize = '14px';
    toast.style.fontWeight = '500';
    toast.style.display = 'flex';
    toast.style.alignItems = 'center';
    toast.style.gap = '12px';
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(20px)';
    toast.style.transition = 'all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
    
    toastContainer.appendChild(toast);
    
    // Kích hoạt animation xuất hiện
    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
    }, 50);
    
    // Tự động ẩn và xóa toast
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 2700);
}

/**
 * Cập nhật bộ đếm thời gian ngược cho các trận đấu sắp diễn ra
 */
function updateCountdowns() {
    const countdowns = document.querySelectorAll('.countdown-timer');
    countdowns.forEach(timer => {
        const timeStr = timer.getAttribute('data-time');
        if (!timeStr) return;
        
        // Chuyển định dạng ISO/MySQL string sang Object Date an toàn cho mọi trình duyệt
        const matchTime = new Date(timeStr.replace(/-/g, "/")).getTime();
        const now = new Date().getTime();
        const diff = matchTime - now;
        
        // Trừ đi 15 phút (900.000 ms) để hiển thị thời gian khóa dự đoán thực tế
        const lockDiff = diff - 900000;
        
        const card = timer.closest('.match-card');
        
        if (lockDiff <= 0) {
            timer.innerHTML = '<span class="text-danger" style="color: var(--accent-red); font-weight: bold;"><i class="fa-solid fa-lock"></i> Đã khóa dự đoán</span>';
            if (card) {
                // Vô hiệu hóa input và nút bấm dự đoán
                const inputs = card.querySelectorAll('.pred-input');
                inputs.forEach(input => input.disabled = true);
                
                const submitBtn = card.querySelector('.btn-predict');
                if (submitBtn) {
                    submitBtn.remove();
                }
            }
            return;
        }
        
        const hours = Math.floor(lockDiff / (1000 * 60 * 60));
        const minutes = Math.floor((lockDiff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((lockDiff % (1000 * 60)) / 1000);
        
        let displayStr = '';
        if (hours > 0) {
            displayStr += `${hours} giờ `;
        }
        displayStr += `${minutes} phút ${seconds} giây`;
        timer.innerHTML = `<i class="fa-solid fa-clock"></i> Khóa sau: <strong style="color: var(--primary);">${displayStr}</strong>`;
    });
}

// Khởi chạy khi DOM đã sẵn sàng
document.addEventListener('DOMContentLoaded', () => {
    // 1. Chạy đếm ngược
    updateCountdowns();
    setInterval(updateCountdowns, 1000);
    
    // 2. Khởi tạo Bàn phím số dự đoán (Keypad Modal)
    setupNumericKeypad();
    
    // 3. Xử lý click chọn đội trong form dự đoán
    const teamSelectBtns = document.querySelectorAll('.team-select-btn');
    teamSelectBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            if (this.disabled) return;
            
            const form = this.closest('.prediction-form');
            if (!form) return;
            
            // Bỏ chọn tất cả đội khác trong cùng form
            form.querySelectorAll('.team-select-btn').forEach(b => b.classList.remove('selected'));
            
            // Chọn đội hiện tại
            this.classList.add('selected');
            
            // Cập nhật input ẩn
            const teamVal = this.getAttribute('data-team');
            const hiddenInput = form.querySelector('.predicted-team-input');
            if (hiddenInput) {
                hiddenInput.value = teamVal;
            }
            
            // Cập nhật text hiển thị tạm thời
            const teamName = this.querySelector('.team-name').textContent;
            const statusWrapper = form.querySelector('.pred-score-text');
            if (statusWrapper) {
                statusWrapper.innerHTML = `Bạn chọn: <strong style="color: var(--primary); font-size: 15px;">${teamName}</strong>`;
                statusWrapper.style.borderStyle = 'solid';
                statusWrapper.style.borderColor = 'rgba(170, 132, 20, 0.3)';
            }
            
            // Kích hoạt nút submit
            const submitBtn = form.querySelector('.btn-predict');
            if (submitBtn) {
                submitBtn.removeAttribute('disabled');
            }
        });
    });

    // 4. AJAX gửi dự đoán chọn đội lên máy chủ
    const forms = document.querySelectorAll('.prediction-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const matchId = this.getAttribute('data-match-id');
            const teamInput = this.querySelector('.predicted-team-input');
            const submitBtn = this.querySelector('.btn-predict');
            
            if (!teamInput || !teamInput.value) {
                showToast('Vui lòng chọn một đội để dự đoán!', 'error');
                return;
            }
            
            const predictedTeam = teamInput.value.trim();
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu';
            }
            
            const formData = new FormData();
            formData.append('match_id', matchId);
            formData.append('predicted_team', predictedTeam);
            
            // Lấy prefix đường dẫn tương đối
            const pathPrefix = document.body.getAttribute('data-path-prefix') || '';
            
            fetch(`${pathPrefix}api/predict.php`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Cập nhật <i class="fa-solid fa-check"></i>';
                }
                
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Cập nhật trạng thái text hiển thị dự đoán chính thức
                    const statusWrapper = form.querySelector('.pred-score-text');
                    if (statusWrapper) {
                        statusWrapper.innerHTML = `Bạn đã chọn: <strong style="color: var(--accent); font-size: 15px;">${data.team_name}</strong>`;
                        statusWrapper.style.borderStyle = 'solid';
                        statusWrapper.style.borderColor = 'rgba(0, 255, 170, 0.3)';
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Lưu dự đoán <i class="fa-solid fa-floppy-disk"></i>';
                }
                showToast('Lỗi kết nối máy chủ!', 'error');
            });
        });
    });
});

/**
 * Tự động chèn CSS CSS cho bàn phím số
 */
function injectKeypadStyles() {
    if (document.getElementById('keypad-styles')) return;
    const style = document.createElement('style');
    style.id = 'keypad-styles';
    style.innerHTML = `
        .keypad-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .keypad-modal.show {
            opacity: 1;
            pointer-events: auto;
        }
        .keypad-content {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
            border: 1px solid rgba(0,0,0,0.08);
            transform: translateY(20px);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .keypad-modal.show .keypad-content {
            transform: translateY(0);
        }
        .keypad-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .keypad-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            color: var(--text-main);
            text-transform: uppercase;
        }
        .keypad-close {
            background: transparent;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: var(--text-muted);
            line-height: 1;
        }
        .keypad-teams {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .keypad-team {
            width: 40%;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .keypad-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
            margin-bottom: 8px;
        }
        .keypad-team-name {
            font-weight: 700;
            font-size: 14px;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        .keypad-display-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 25px;
        }
        .keypad-display-val {
            width: 75px;
            height: 75px;
            border-radius: 12px;
            background: #f1f3f5;
            border: 2px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            font-weight: 800;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s;
        }
        .keypad-display-val.active {
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 15px rgba(170,132,20,0.25);
        }
        .keypad-divider {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-muted);
        }
        .keypad-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .keypad-btn {
            background: #f8faf9;
            border: 1px solid rgba(0,0,0,0.06);
            border-radius: 12px;
            height: 60px;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .keypad-btn:active, .keypad-btn:hover {
            background: rgba(0, 0, 0, 0.04);
        }
        .keypad-btn.keypad-btn-ok {
            background: var(--primary-grad);
            color: var(--text-dark);
            font-size: 16px;
            font-weight: 800;
        }
        .keypad-btn.keypad-btn-ok:active, .keypad-btn.keypad-btn-ok:hover {
            box-shadow: 0 5px 15px rgba(170,132,20,0.25);
            transform: translateY(-1px);
        }
        .keypad-btn.keypad-btn-del {
            color: var(--accent-red);
            font-size: 15px;
            font-weight: 600;
        }
    `;
    document.head.appendChild(style);
}

/**
 * Cấu hình và tạo bàn phím số
 */
function setupNumericKeypad() {
    injectKeypadStyles();
    
    // Tạo phần tử modal
    let modal = document.getElementById('keypad-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'keypad-modal';
        modal.className = 'keypad-modal';
        modal.innerHTML = `
            <div class="keypad-content">
                <div class="keypad-header">
                    <h3>Dự đoán tỷ số</h3>
                    <button class="keypad-close"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="keypad-teams">
                    <div class="keypad-team" id="keypad-home-team">
                        <img src="" class="keypad-logo" alt="">
                        <span class="keypad-team-name">Đội nhà</span>
                    </div>
                    <div style="font-weight: 800; color: var(--text-muted);">VS</div>
                    <div class="keypad-team" id="keypad-away-team">
                        <img src="" class="keypad-logo" alt="">
                        <span class="keypad-team-name">Đội khách</span>
                    </div>
                </div>
                <div class="keypad-display-wrapper">
                    <div class="keypad-display-val active" id="keypad-val-home">0</div>
                    <div class="keypad-divider">-</div>
                    <div class="keypad-display-val" id="keypad-val-away">0</div>
                </div>
                <div class="keypad-grid">
                    <button class="keypad-btn" data-val="1">1</button>
                    <button class="keypad-btn" data-val="2">2</button>
                    <button class="keypad-btn" data-val="3">3</button>
                    <button class="keypad-btn" data-val="4">4</button>
                    <button class="keypad-btn" data-val="5">5</button>
                    <button class="keypad-btn" data-val="6">6</button>
                    <button class="keypad-btn" data-val="7">7</button>
                    <button class="keypad-btn" data-val="8">8</button>
                    <button class="keypad-btn" data-val="9">9</button>
                    <button class="keypad-btn keypad-btn-del" data-val="del">XÓA</button>
                    <button class="keypad-btn" data-val="0">0</button>
                    <button class="keypad-btn keypad-btn-ok" data-val="ok">XONG</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    const keypadClose = modal.querySelector('.keypad-close');
    const valHome = modal.querySelector('#keypad-val-home');
    const valAway = modal.querySelector('#keypad-val-away');
    const keypadButtons = modal.querySelectorAll('.keypad-btn');
    
    let activeInputType = 'home'; // 'home' hoặc 'away'
    let currentMatchForm = null;
    let targetHomeInput = null;
    let targetAwayInput = null;
    
    // Switch active display val
    valHome.addEventListener('click', () => {
        activeInputType = 'home';
        valHome.classList.add('active');
        valAway.classList.remove('active');
    });
    valAway.addEventListener('click', () => {
        activeInputType = 'away';
        valAway.classList.add('active');
        valHome.classList.remove('active');
    });
    
    // Đóng modal
    const closeModal = () => {
        modal.classList.remove('show');
    };
    keypadClose.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    
    // Logic nút bấm bàn phím
    keypadButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const val = btn.getAttribute('data-val');
            const activeDisplay = activeInputType === 'home' ? valHome : valAway;
            
            if (val === 'ok') {
                // Sao chép kết quả về input của card
                if (targetHomeInput && targetAwayInput) {
                    targetHomeInput.value = valHome.innerHTML === '' ? '0' : valHome.innerHTML;
                    targetAwayInput.value = valAway.innerHTML === '' ? '0' : valAway.innerHTML;
                    
                    // Trigger submit bằng cách giả lập click vào nút submit
                    if (currentMatchForm) {
                        const submitBtn = currentMatchForm.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            submitBtn.click();
                        } else {
                            currentMatchForm.dispatchEvent(new Event('submit', { cancelable: true }));
                        }
                    }
                }
                closeModal();
            } else if (val === 'del') {
                activeDisplay.innerHTML = '';
            } else {
                let currentText = activeDisplay.innerHTML;
                if (currentText === '0' || currentText === '') {
                    activeDisplay.innerHTML = val;
                } else if (currentText.length < 2) { // Tối đa 2 chữ số
                    activeDisplay.innerHTML = currentText + val;
                }
                
                // Tự động nhảy sang ô đội khách nếu ô đội nhà đã điền xong và ô đội khách trống
                if (activeInputType === 'home' && (valAway.innerHTML === '' || valAway.innerHTML === '0')) {
                    setTimeout(() => {
                        activeInputType = 'away';
                        valAway.classList.add('active');
                        valHome.classList.remove('active');
                        valAway.innerHTML = '';
                    }, 150);
                }
            }
        });
    });
    
    // Gắn sự kiện click vào các ô input dự đoán
    const inputs = document.querySelectorAll('.pred-input');
    inputs.forEach(input => {
        // Chuyển sang readonly để ko bị mở bàn phím mặc định của điện thoại
        input.setAttribute('readonly', 'true');
        input.style.cursor = 'pointer';
        
        input.addEventListener('click', function() {
            if (this.disabled) return;
            
            currentMatchForm = this.closest('form');
            if (!currentMatchForm) return;
            
            targetHomeInput = currentMatchForm.querySelector('.home-score-input');
            targetAwayInput = currentMatchForm.querySelector('.away-score-input');
            
            const card = this.closest('.match-card');
            const homeName = card.querySelector('.team-box:first-of-type .team-name').innerHTML;
            const awayName = card.querySelector('.team-box:last-of-type .team-name').innerHTML;
            const homeLogo = card.querySelector('.team-box:first-of-type .team-logo').src;
            const awayLogo = card.querySelector('.team-box:last-of-type .team-logo').src;
            
            // Cập nhật thông tin trận đấu trong modal
            modal.querySelector('#keypad-home-team .keypad-team-name').innerHTML = homeName;
            modal.querySelector('#keypad-home-team .keypad-logo').src = homeLogo;
            modal.querySelector('#keypad-away-team .keypad-team-name').innerHTML = awayName;
            modal.querySelector('#keypad-away-team .keypad-logo').src = awayLogo;
            
            // Cập nhật giá trị số ban đầu
            valHome.innerHTML = targetHomeInput.value !== '' ? targetHomeInput.value : '0';
            valAway.innerHTML = targetAwayInput.value !== '' ? targetAwayInput.value : '0';
            
            // Highlight ô đang được nhấn tương ứng
            if (this.classList.contains('home-score-input')) {
                activeInputType = 'home';
                valHome.classList.add('active');
                valAway.classList.remove('active');
            } else {
                activeInputType = 'away';
                valAway.classList.add('active');
                valHome.classList.remove('active');
            }
            
            modal.classList.add('show');
        });
    });
}

// Chức năng xuất PDF từ một Element HTML
// Hàm hiển thị modal xem trước bản in PDF
window.showPreviewModal = function(htmlContent, filename) {
    // 1. Tạo style sheet nếu chưa tồn tại
    if (!document.getElementById('pdf-preview-modal-styles')) {
        const style = document.createElement('style');
        style.id = 'pdf-preview-modal-styles';
        style.innerHTML = `
            .pdf-preview-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.85);
                z-index: 99999;
                display: flex;
                justify-content: center;
                align-items: center;
                opacity: 0;
                transition: opacity 0.3s ease;
                font-family: 'Be Vietnam Pro', 'Inter', sans-serif;
            }
            .pdf-preview-modal.show {
                opacity: 1;
            }
            .pdf-preview-content-wrapper {
                width: 92%;
                max-width: 900px;
                height: 90%;
                background: #0e1911;
                border: 1px solid #1f3525;
                border-radius: 12px;
                display: flex;
                flex-direction: column;
                box-shadow: 0 15px 40px rgba(0,0,0,0.6);
                transform: translateY(20px);
                transition: transform 0.3s ease;
                overflow: hidden;
            }
            .pdf-preview-modal.show .pdf-preview-content-wrapper {
                transform: translateY(0);
            }
            .pdf-preview-header {
                padding: 15px 20px;
                background: #08100a;
                border-bottom: 1px solid #1f3525;
                display: flex;
                justify-content: space-between;
                align-items: center;
                color: #fff;
            }
            .pdf-preview-title {
                font-size: 15px;
                font-weight: 700;
                margin: 0;
                display: flex;
                align-items: center;
                gap: 8px;
                color: #e5f0ea;
            }
            .pdf-preview-close {
                background: none;
                border: none;
                color: #a0b2a6;
                font-size: 24px;
                cursor: pointer;
                transition: color 0.2s;
                line-height: 1;
            }
            .pdf-preview-close:hover {
                color: #fff;
            }
            .pdf-preview-body {
                flex: 1;
                padding: 20px;
                background: #050a06;
                overflow-y: auto;
                display: flex;
                justify-content: center;
                align-items: flex-start;
            }
            .pdf-preview-paper-container {
                width: 800px;
                background: #ffffff;
                box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                border-radius: 4px;
                box-sizing: border-box;
                overflow: hidden;
            }
            #pdf-preview-modal-paper .pdf-only {
                display: block !important;
            }
            .pdf-preview-footer {
                padding: 15px 20px;
                background: #08100a;
                border-top: 1px solid #1f3525;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .pdf-btn {
                padding: 8px 18px;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                border: none;
                transition: all 0.2s;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                text-decoration: none;
            }
            .pdf-btn-secondary {
                background: #1f3525;
                color: #a0b2a6;
            }
            .pdf-btn-secondary:hover {
                background: #2c4d36;
                color: #fff;
            }
            .pdf-btn-primary {
                background: #aa8414;
                color: #fff;
            }
            .pdf-btn-primary:hover {
                background: #cfa320;
                box-shadow: 0 0 10px rgba(170, 132, 20, 0.4);
            }
        `;
        document.head.appendChild(style);
    }
    
    // 2. Dọn dẹp modal cũ nếu có
    const oldModal = document.getElementById('pdf-preview-modal-el');
    if (oldModal) {
        oldModal.remove();
    }
    
    // 3. Tạo modal mới
    const modal = document.createElement('div');
    modal.id = 'pdf-preview-modal-el';
    modal.className = 'pdf-preview-modal';
    modal.innerHTML = `
        <div class="pdf-preview-content-wrapper">
            <div class="pdf-preview-header">
                <h3 class="pdf-preview-title">
                    <i class="fa-solid fa-eye" style="color: #aa8414;"></i> Xem trước bản in PDF
                </h3>
                <button type="button" class="pdf-preview-close" onclick="closePDFPreview()">&times;</button>
            </div>
            <div class="pdf-preview-body">
                <div id="pdf-preview-modal-paper" class="pdf-preview-paper-container">
                    ${htmlContent}
                </div>
            </div>
            <div class="pdf-preview-footer">
                <button type="button" class="pdf-btn pdf-btn-secondary" onclick="closePDFPreview()">Đóng</button>
                <button type="button" class="pdf-btn pdf-btn-primary" id="pdf-modal-download-btn">
                    <i class="fa-solid fa-file-pdf"></i> Tải file PDF
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Trigger show animation
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
    
    // Bắt sự kiện nút tải file
    document.getElementById('pdf-modal-download-btn').addEventListener('click', function() {
        const paper = document.getElementById('pdf-preview-modal-paper');
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Tạo container tạm thời và đặt ngoài màn hình để html2canvas chụp chuẩn xác
        const tempExportDiv = document.createElement('div');
        tempExportDiv.id = 'pdf-export-temp-container';
        tempExportDiv.style.position = 'absolute';
        tempExportDiv.style.left = '-9999px'; // Giấu sang bên trái ngoài màn hình
        tempExportDiv.style.top = '0';
        tempExportDiv.style.width = '800px';
        tempExportDiv.style.zIndex = '1';
        tempExportDiv.style.background = '#ffffff';
        
        // Thêm rule CSS đặc biệt cho container tạm
        const tempExportStyle = document.createElement('style');
        tempExportStyle.innerHTML = `
            #pdf-export-temp-container .pdf-only {
                display: block !important;
            }
        `;
        
        document.body.appendChild(tempExportStyle);
        document.body.appendChild(tempExportDiv);
        
        // Sao chép nội dung từ khung xem trước sang container tạm
        tempExportDiv.innerHTML = paper.innerHTML;
        
        // Ép hiển thị toàn bộ phần tử cược ẩn bằng cách bỏ class pdf-only và gán display block
        const pdfOnly = tempExportDiv.querySelectorAll('.pdf-only');
        pdfOnly.forEach(el => {
            el.classList.remove('pdf-only');
            el.style.setProperty('display', 'block', 'important');
        });
        
        const opt = {
            margin:       0,
            filename:     filename + '.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { 
                scale: 2, 
                useCORS: true,
                logging: false,
                letterRendering: true,
                backgroundColor: '#ffffff',
                windowWidth: 800,
                scrollY: 0, // Bắt buộc chụp từ tọa độ Y=0 để không bị trắng trang khi user cuộn chuột
                scrollX: 0
            },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        showToast('Đang tạo và tải file PDF...', 'success');
        
        html2pdf().set(opt).from(tempExportDiv).save().then(() => {
            document.body.removeChild(tempExportDiv);
            document.body.removeChild(tempExportStyle);
            closePDFPreview();
        }).catch(err => {
            console.error('Lỗi xuất PDF:', err);
            alert('Có lỗi xảy ra khi xuất PDF!');
            document.body.removeChild(tempExportDiv);
            document.body.removeChild(tempExportStyle);
        });
    });
};

// Hàm đóng modal xem trước
window.closePDFPreview = function() {
    const modal = document.getElementById('pdf-preview-modal-el');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.remove();
        }, 300); // Đợi kết thúc hiệu ứng transition rồi mới xóa khỏi DOM
    }
};

// Chức năng xuất PDF chính có xem trước
window.exportToPDF = function(elementId, filename) {
    // Kiểm tra xem thư viện html2pdf có sẵn không
    if (typeof html2pdf === 'undefined') {
        alert('Thư viện html2pdf chưa được tải hoàn tất hoặc bị chặn! Vui lòng tải lại trang hoặc kiểm tra bộ nhớ đệm trình duyệt.');
        return;
    }
    
    // Kiểm tra các trường hợp đặc biệt để tải dữ liệu độc lập qua AJAX API
    if (elementId === 'pdf-match-predictions-template' || elementId === 'pdf-leaderboard-template') {
        // Tự động điều chỉnh đường dẫn API nếu đang chạy trong thư mục admin/
        const apiPrefix = window.location.pathname.includes('/admin/') ? '../' : '';
        let apiUrl = '';
        
        if (elementId === 'pdf-match-predictions-template') {
            let matchId = '';
            // Ưu tiên lấy từ select dropdown của bộ lọc trên màn hình
            const matchSelect = document.querySelector('select[name="match_id"]');
            if (matchSelect) {
                matchId = matchSelect.value;
            } else {
                // Fallback lấy từ tham số URL
                const urlParams = new URLSearchParams(window.location.search);
                matchId = urlParams.get('match_id');
            }
            // Nếu không tìm thấy, gửi 0 để API tự nhận diện trận đấu mặc định
            if (!matchId) {
                matchId = 0;
            }
            apiUrl = `${apiPrefix}api/get_pdf_template.php?type=match_predictions&match_id=${matchId}`;
        } else {
            apiUrl = `${apiPrefix}api/get_pdf_template.php?type=leaderboard`;
        }
        
        showToast('Đang tải dữ liệu xem trước...', 'success');
        
        fetch(apiUrl)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.showPreviewModal(data.html, filename);
                } else {
                    alert('Lỗi tải dữ liệu xuất PDF: ' + data.message);
                }
            })
            .catch(err => {
                console.error('Lỗi API xuất PDF:', err);
                alert('Không thể kết nối đến máy chủ để xuất PDF!');
            });
            
    } else {
        // Fallback cho các thẻ thông thường có sẵn trong DOM (Ví dụ: danh sách thành viên admin)
        const element = document.getElementById(elementId);
        if (!element) {
            alert('Không tìm thấy dữ liệu để xuất PDF!');
            return;
        }
        window.showPreviewModal(element.innerHTML, filename);
    }
};
