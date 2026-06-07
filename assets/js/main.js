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
    
    // 3. AJAX dự đoán tỷ số
    const forms = document.querySelectorAll('.prediction-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const matchId = this.getAttribute('data-match-id');
            const homeInput = this.querySelector('.home-score-input');
            const awayInput = this.querySelector('.away-score-input');
            const submitBtn = this.querySelector('.btn-predict');
            
            const homeScore = homeInput.value.trim();
            const awayScore = awayInput.value.trim();
            
            if (homeScore === '' || awayScore === '') {
                showToast('Vui lòng điền đầy đủ tỷ số dự đoán!', 'error');
                return;
            }
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu';
            }
            
            const formData = new FormData();
            formData.append('match_id', matchId);
            formData.append('home_score', homeScore);
            formData.append('away_score', awayScore);
            
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
                    
                    // Cập nhật trạng thái text hiển thị dự đoán
                    const statusWrapper = form.closest('.match-card').querySelector('.pred-score-text');
                    if (statusWrapper) {
                        statusWrapper.innerHTML = `Bạn đoán: <strong style="color: var(--accent); font-size: 16px;">${data.home} - ${data.away}</strong>`;
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
window.exportToPDF = function(elementId, filename) {
    const element = document.getElementById(elementId);
    if (!element) {
        alert('Không tìm thấy dữ liệu để xuất PDF!');
        return;
    }
    
    // Kiểm tra xem thư viện html2pdf có sẵn không
    if (typeof html2pdf === 'undefined') {
        alert('Thư viện html2pdf chưa được tải hoàn tất hoặc bị chặn! Vui lòng tải lại trang hoặc kiểm tra bộ nhớ đệm trình duyệt.');
        return;
    }
    
    // Thêm class tạm thời để tối ưu hóa hiển thị khi xuất PDF
    element.classList.add('pdf-exporting');
    
    const opt = {
        margin:       [15, 15, 15, 15],
        filename:     filename + '.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { 
            scale: 2, 
            useCORS: true,
            logging: false,
            letterRendering: true,
            backgroundColor: '#ffffff'
        },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    
    // Thực hiện xuất PDF
    html2pdf().set(opt).from(element).save().then(() => {
        element.classList.remove('pdf-exporting');
    }).catch(err => {
        console.error('Lỗi xuất PDF:', err);
        alert('Có lỗi xảy ra khi xuất PDF!');
        element.classList.remove('pdf-exporting');
    });
};
