// === Bank Kreditləri - Fintech Dashboard ===

const API_URL = 'api.php';

// DOM Elements
const creditForm = document.getElementById('creditForm');
const shortTermForm = document.getElementById('shortTermForm');
const creditsList = document.getElementById('creditsList');
const shortTermList = document.getElementById('shortTermList');
const paymentsModal = document.getElementById('paymentsModal');
const deleteModal = document.getElementById('deleteModal');
const bankModal = document.getElementById('bankModal');
const monthsGrid = document.getElementById('monthsGrid');
const notification = document.getElementById('notification');
const confirmDeleteBtn = document.getElementById('confirmDelete');
const bankForm = document.getElementById('bankForm');
const banksList = document.getElementById('banksList');

// Stats Elements
const totalCreditsEl = document.getElementById('totalCredits');
const totalAmountEl = document.getElementById('totalAmount');
const totalPaidEl = document.getElementById('totalPaid');
const totalRemainingEl = document.getElementById('totalRemaining');
const shortTermRemainingEl = document.getElementById('shortTermRemaining');

// Current delete ID
let deleteId = null;

// Init
document.addEventListener('DOMContentLoaded', () => {
    // Form events
    creditForm.addEventListener('submit', handleCreditSubmit);
    shortTermForm.addEventListener('submit', handleShortTermSubmit);
    confirmDeleteBtn.addEventListener('click', confirmDelete);
    bankForm.addEventListener('submit', handleBankSubmit);

    // Tab switching
    setupTabs();

    // Load banks
    loadBanks();

    // Initialize theme
    initTheme();
});

// Tab setup
function setupTabs() {
    // Form tabs
    document.querySelectorAll('.form-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const tabName = tab.dataset.tab;
            
            document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            tab.classList.add('active');
            document.querySelector(`.tab-content[data-tab="${tabName}"]`).classList.add('active');
        });
    });
    
    // List tabs
    document.querySelectorAll('.list-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const listName = tab.dataset.list;
            
            document.querySelectorAll('.list-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.list-content').forEach(c => c.classList.remove('active'));
            
            tab.classList.add('active');
            document.querySelector(`.list-content[data-list="${listName}"]`).classList.add('active');
        });
    });
}

// Update stats instantly
function updateStats(stats, bankLimit = 2250) {
    totalCreditsEl.textContent = stats.totalCredits;
    totalAmountEl.textContent = `₼${formatMoney(stats.totalAmount)}`;
    totalPaidEl.textContent = `₼${formatMoney(stats.totalPaid)}`;
    totalRemainingEl.textContent = `₼${formatMoney(stats.totalRemaining)}`;
    shortTermRemainingEl.textContent = `₼${formatMoney(stats.shortTermRemaining)}`;
    
    // Update card limit
    const cardLimitUsed = document.getElementById('cardLimitUsed');
    const cardLimitTotal = document.querySelector('.card-limit-total');
    const cardLimitProgress = document.getElementById('cardLimitProgress');
    const cardLimitPercent = document.querySelector('.card-limit-percent');
    const cardLimitRemainingSpan = document.querySelector('.card-limit-remaining');
    
    if (cardLimitUsed) cardLimitUsed.textContent = `₼${formatMoney(stats.cardLimitUsed)}`;
    if (cardLimitTotal) cardLimitTotal.textContent = `₼${formatMoney(bankLimit)}`;
    if (cardLimitProgress) {
        const percent = Math.min((stats.cardLimitUsed / bankLimit) * 100, 100);
        cardLimitProgress.style.width = `${percent}%`;
    }
    if (cardLimitPercent) cardLimitPercent.textContent = `${(stats.cardLimitUsed / bankLimit * 100).toFixed(1)}%`;
    if (cardLimitRemainingSpan) cardLimitRemainingSpan.textContent = `(Qalan: ₼${formatMoney(bankLimit - stats.cardLimitUsed)})`;
}

// Refresh all lists
async function refreshAll() {
    // Get selected bank from cookie
    const match = document.cookie.match(/selectedBankId=([^;]+)/);
    const bankId = match ? match[1] : '';
    
    try {
        const url = bankId
            ? `${API_URL}?action=list&bankId=${bankId}`
            : `${API_URL}?action=list`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            creditsList.innerHTML = data.creditsHtml;
            shortTermList.innerHTML = data.shortTermHtml;
            updateStats(data.stats, data.bankLimit || 2250);
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Filter by bank
function filterByBank() {
    // This function is no longer needed, but kept for compatibility
    refreshAll();
}

// Handle credit form submit
async function handleCreditSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(creditForm);
    const data = {
        action: 'addCredit',
        bankId: formData.get('bankId'),
        bankName: formData.get('bankName'),
        totalAmount: parseFloat(formData.get('totalAmount')),
        monthCount: parseInt(formData.get('monthCount')),
        startDate: formData.get('startDate'),
        notes: formData.get('notes')
    };
    
    if (!data.bankId || !data.bankName || !data.totalAmount || !data.monthCount || !data.startDate) {
        showNotification('Bütün sahələri doldurun', 'error');
        return;
    }
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Kredit əlavə edildi', 'success');
            
            // Əvvəlcə Kreditlər tabına keç
            document.querySelector('.list-tab[data-list="credits"]').click();
            
            // Yeni krediti dərhal DOM-a əlavə et
            const credit = result.credit;
            if (credit) {
                const monthlyPayment = credit.totalAmount / credit.monthCount;
                const paidCount = 0; // Yeni kredit heç ödəniş yoxdur
                const progress = 0;
                
                // Boş vəziyyəti sil əgər varsa
                const emptyState = creditsList.querySelector('.empty-state');
                if (emptyState) {
                    emptyState.remove();
                }
                
                // Yeni kredit kartı HTML-i
                const creditHtml = `
                    <div class="loan-card" data-id="${credit.id}">
                        <div class="loan-header">
                            <div class="loan-info">
                                <h3 class="loan-title">${escapeHtml(credit.bankName)}</h3>
                                <p class="loan-meta">${credit.monthCount} ay · ₼${formatMoney(monthlyPayment)}/ay</p>
                            </div>
                            <div class="loan-amount">
                                <span class="amount-value">₼${formatMoney(credit.totalAmount)}</span>
                            </div>
                        </div>
                        <div class="loan-progress">
                            <div class="progress-info">
                                <span class="progress-label">${paidCount}/${credit.monthCount} ödənilib</span>
                                <span class="progress-percent">${progress}%</span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width: ${progress}%"></div>
                            </div>
                        </div>
                        <div class="loan-actions">
                            <button class="btn-secondary btn-sm" onclick="openPaymentsModal('${credit.id}')">Ödənişlər</button>
                            <button class="btn-text btn-sm" onclick="deleteCredit('${credit.id}')">Sil</button>
                        </div>
                    </div>
                `;
                
                // Siyahının əvvəlinə əlavə et
                creditsList.insertAdjacentHTML('afterbegin', creditHtml);
            }
            
            // Formu sıfırla
            creditForm.reset();
            document.querySelector('#creditForm [name="startDate"]').value = new Date().toISOString().split('T')[0];
            
            // Background-da statistikaları yenilə
            loadBanks();
            refreshAll();
        } else {
            showNotification(result.message || 'Xəta', 'error');
        }
    } catch (error) {
        showNotification('Server xətası', 'error');
    }
}

// Handle short term debt form submit
async function handleShortTermSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(shortTermForm);
    const data = {
        action: 'addShortTerm',
        bankId: formData.get('bankId'),
        category: formData.get('category'),
        amount: parseFloat(formData.get('amount')),
        expenseDate: formData.get('expenseDate'),
        description: formData.get('description')
    };
    
    if (!data.bankId || !data.category || !data.amount || !data.expenseDate) {
        showNotification('Bütün sahələri doldurun', 'error');
        return;
    }
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Borc əlavə edildi', 'success');
            
            // Əvvəlcə tab-ı dəyiş
            document.querySelector('.list-tab[data-list="shortterm"]').click();
            
            // Yeni borcu dərhal DOM-a əlavə et
            const debt = result.debt;
            if (debt) {
                const dueDate = new Date(debt.dueDate);
                const expenseDate = new Date(debt.expenseDate);
                const isOverdue = !debt.paid && dueDate < new Date();
                
                // Boş vəziyyəti sil əgər varsa
                const emptyState = shortTermList.querySelector('.empty-state');
                if (emptyState) {
                    emptyState.remove();
                }
                
                // Yeni borc kartı HTML-i
                const debtHtml = `
                    <div class="debt-card ${debt.paid ? 'paid' : (isOverdue ? 'overdue' : '')}" data-id="${debt.id}">
                        <div class="debt-check" onclick="toggleShortTermPayment('${debt.id}')">${debt.paid ? '✓' : ''}</div>
                        <div class="debt-info">
                            <div class="debt-header">
                                <span class="debt-category">${escapeHtml(debt.category)}</span>
                                <span class="debt-amount">₼${formatMoney(debt.amount)}</span>
                            </div>
                            ${debt.description ? `<p class="debt-description">${escapeHtml(debt.description)}</p>` : ''}
                            <div class="debt-dates">
                                <span class="debt-date">Xərc: ${formatDate(expenseDate)}</span>
                                <span class="debt-due ${isOverdue ? 'overdue' : ''}">Son: ${formatDate(dueDate)}</span>
                            </div>
                        </div>
                        <button class="btn-text btn-sm" onclick="deleteCredit('${debt.id}')">Sil</button>
                    </div>
                `;
                
                // Siyahının əvvəlinə əlavə et
                shortTermList.insertAdjacentHTML('afterbegin', debtHtml);
            }
            
            // Formu sıfırla
            shortTermForm.reset();
            document.querySelector('#shortTermForm [name="expenseDate"]').value = new Date().toISOString().split('T')[0];
            
            // Background-da statistikaları yenilə
            loadBanks();
            refreshAll();
        } else {
            showNotification(result.message || 'Xəta', 'error');
        }
    } catch (error) {
        showNotification('Server xətası', 'error');
    }
}

// Open payments modal
async function openPaymentsModal(id) {
    try {
        const response = await fetch(`${API_URL}?action=get&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            renderPaymentsModal(data.credit);
            paymentsModal.classList.add('active');
            document.body.classList.add('modal-open');
        }
    } catch (error) {
        showNotification('Xəta', 'error');
    }
}

// Render payments modal
function renderPaymentsModal(credit) {
    const monthlyPayment = credit.totalAmount / credit.monthCount;
    const paidCount = credit.payments.filter(p => p.paid).length;
    const paidAmount = paidCount * monthlyPayment;
    const remainingAmount = credit.totalAmount - paidAmount;
    const progress = (paidCount / credit.monthCount) * 100;
    
    document.getElementById('modalBankName').textContent = credit.bankName;
    document.getElementById('modalTotalAmount').textContent = `₼${formatMoney(credit.totalAmount)}`;
    document.getElementById('modalMonthlyPayment').textContent = `₼${formatMoney(monthlyPayment)}`;
    document.getElementById('modalPaidMonths').textContent = `${paidCount}/${credit.monthCount}`;
    document.getElementById('modalPaidAmount').textContent = `₼${formatMoney(paidAmount)}`;
    document.getElementById('modalRemainingAmount').textContent = `₼${formatMoney(remainingAmount)}`;
    document.getElementById('modalProgressBar').style.width = `${progress}%`;
    
    const months = ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'İyn', 'İyl', 'Avq', 'Sen', 'Okt', 'Noy', 'Dek'];
    
    monthsGrid.innerHTML = credit.payments.map((payment, index) => {
        const monthDate = new Date(credit.startDate);
        monthDate.setMonth(monthDate.getMonth() + index);
        
        return `
            <div class="payment-month ${payment.paid ? 'paid' : ''}" onclick="togglePayment('${credit.id}', ${index})">
                <div class="payment-number">Ay ${index + 1}</div>
                <div class="payment-date">${months[monthDate.getMonth()]} ${monthDate.getFullYear()}</div>
                <div class="payment-amount">₼${formatMoney(monthlyPayment)}</div>
                <div class="payment-check">✓</div>
            </div>
        `;
    }).join('');
}

// Toggle credit payment
async function togglePayment(creditId, monthIndex) {
    const months = monthsGrid.querySelectorAll('.payment-month');
    if (months[monthIndex]) {
        months[monthIndex].classList.toggle('paid');
    }
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'togglePayment',
                id: creditId,
                monthIndex: monthIndex
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            openPaymentsModal(creditId);
            refreshAll();
        } else {
            if (months[monthIndex]) {
                months[monthIndex].classList.toggle('paid');
            }
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Toggle short term payment
async function toggleShortTermPayment(id) {
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'toggleShortTermPayment',
                id: id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            refreshAll();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Delete credit - open modal
function deleteCredit(id) {
    deleteId = id;
    deleteModal.classList.add('active');
    document.body.classList.add('modal-open');
}

// Confirm delete
async function confirmDelete() {
    if (!deleteId) return;
    
    const idToDelete = deleteId;
    
    // Dərhal DOM-dan sil (hər iki siyahıdan axtarış)
    const creditCard = creditsList.querySelector(`[data-id="${idToDelete}"]`);
    const debtCard = shortTermList.querySelector(`[data-id="${idToDelete}"]`);
    
    if (creditCard) {
        creditCard.style.opacity = '0.5';
        creditCard.style.transition = 'opacity 0.2s';
    }
    if (debtCard) {
        debtCard.style.opacity = '0.5';
        debtCard.style.transition = 'opacity 0.2s';
    }
    
    // Modalı dərhal bağla
    closeDeleteModal();
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: idToDelete })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // DOM-dan tamamilə sil
            if (creditCard) creditCard.remove();
            if (debtCard) debtCard.remove();
            
            // Siyahılar boşdursa boş vəziyyəti göstər
            if (creditsList.children.length === 0) {
                creditsList.innerHTML = '<div class="empty-state"><p class="empty-text">Heç bir kredit yoxdur</p></div>';
            }
            if (shortTermList.children.length === 0) {
                shortTermList.innerHTML = '<div class="empty-state"><p class="empty-text">Heç bir qısa müddətli borc yoxdur</p></div>';
            }
            
            showNotification('Silindi', 'success');
            
            // Background-da statistikaları yenilə
            loadBanks();
            refreshAll();
        } else {
            // Xəta oldusa elementi geri qaytar
            if (creditCard) creditCard.style.opacity = '1';
            if (debtCard) debtCard.style.opacity = '1';
            showNotification(data.message || 'Xəta', 'error');
        }
    } catch (error) {
        // Xəta oldusa elementi geri qaytar
        if (creditCard) creditCard.style.opacity = '1';
        if (debtCard) debtCard.style.opacity = '1';
        showNotification('Server xətası', 'error');
    }
}

// Close delete modal
function closeDeleteModal() {
    deleteModal.classList.remove('active');
    document.body.classList.remove('modal-open');
    deleteId = null;
}

// Close payments modal
function closePaymentsModal() {
    paymentsModal.classList.remove('active');
    document.body.classList.remove('modal-open');
}

// ===== BANK MANAGEMENT =====

// Open bank modal
function openBankModal() {
    bankModal.classList.add('active');
    document.body.classList.add('modal-open');
    loadBanks();
}

// Close bank modal
function closeBankModal() {
    // Əgər bank yoxdursa, modalı bağlama
    if (bankModal.hasAttribute('data-no-banks') && bankModal.dataset.noBanks === 'true') {
        showNotification('Davam etmək üçün bank əlavə edin', 'error');
        return;
    }

    // Əgər bank var ama seçilməyibsə, modalı bağlama
    const match = document.cookie.match(/selectedBankId=([^;]+)/);
    const bankId = match ? match[1] : '';
    if (!bankId) {
        showNotification('Zəhmət olmasa bank seçin', 'error');
        return;
    }

    bankModal.classList.remove('active');
    document.body.classList.remove('modal-open');
}

// Load banks
async function loadBanks() {
    try {
        const response = await fetch(`${API_URL}?action=banks`);
        const data = await response.json();
        
        if (data.success) {
            banksList.innerHTML = data.banksHtml;
        }
    } catch (error) {
        console.error('Error loading banks:', error);
    }
}

// Handle bank form submit
async function handleBankSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(bankForm);
    const data = {
        action: 'addBank',
        name: formData.get('name'),
        limit: parseFloat(formData.get('limit')) || 2250
    };
    
    if (!data.name) {
        showNotification('Bank adı tələb olunur', 'error');
        return;
    }
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Bank əlavə edildi', 'success');
            
            // Yeni bankın ID-sini cookie-yə yaz və səhifəni yenilə
            const newBankId = result.bank?.id || null;
            if (newBankId) {
                document.cookie = `selectedBankId=${newBankId};path=/;max-age=31536000`;
                setTimeout(() => {
                    window.location.reload();
                }, 300);
            } else {
                // Bank ID yoxdursa, sadəcə yenilə
                setTimeout(() => {
                    window.location.reload();
                }, 300);
            }
        } else {
            // Əgər validasiya xətası varsa, errors massivindəki mesajları göstər
            if (result.errors && result.errors.length > 0) {
                showNotification(result.errors[0], 'error');
            } else {
                showNotification(result.message || 'Xəta', 'error');
            }
        }
    } catch (error) {
        showNotification('Server xətası', 'error');
    }
}

// Select bank - close modal and filter by bank
function selectBank(bankId) {
    // Close bank modal
    closeBankModal();
    
    // Save to cookie
    document.cookie = `selectedBankId=${bankId};path=/;max-age=31536000`;
    
    // Reload page to apply filter
    window.location.reload();
}

// Delete bank
async function deleteBank(id) {
    if (!confirm('Bu bankı silmək istədiyinizə əminsiniz? Banka aid bütün kreditlər və borclar da silinəcək.')) {
        return;
    }
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'deleteBank', id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Bank silindi', 'success');
            
            // Qalan bankları yoxla
            const banksResponse = await fetch(`${API_URL}?action=banks`);
            const banksData = await banksResponse.json();
            
            if (banksData.success && banksData.banks.length > 0) {
                // Hələ də bank var - başqa banka keç
                const match = document.cookie.match(/selectedBankId=([^;]+)/);
                const currentBankId = match ? match[1] : '';
                
                // Əgər silinən bank aktiv idi, başqa banka keç
                if (currentBankId === id) {
                    const nextBank = banksData.banks[0];
                    document.cookie = `selectedBankId=${nextBank.id};path=/;max-age=31536000`;
                }
                
                // Səhifəni yenilə
                setTimeout(() => {
                    window.location.reload();
                }, 300);
            } else {
                // Heç bank qalmadı - cookie-ni sil və səhifəni yenilə
                document.cookie = 'selectedBankId=;path=/;max-age=0';
                setTimeout(() => {
                    window.location.reload();
                }, 300);
            }
        } else {
            showNotification(data.message || 'Xəta', 'error');
        }
    } catch (error) {
        showNotification('Server xətası', 'error');
    }
}

// Show notification
function showNotification(message, type = 'success') {
    notification.className = `notification show ${type}`;
    notification.querySelector('.notification-message').textContent = message;
    setTimeout(() => notification.classList.remove('show'), 3000);
}

// Format money
function formatMoney(amount) {
    return parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Format date
function formatDate(date) {
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}.${month}.${year}`;
}

// ===== THEME MANAGEMENT =====

// Initialize theme on page load
function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    const themeIcon = document.getElementById('themeIcon');

    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        if (themeIcon) themeIcon.textContent = '☀️';
    } else {
        document.documentElement.setAttribute('data-theme', 'light');
        if (themeIcon) themeIcon.textContent = '🌙';
    }
}

// Toggle theme
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const themeIcon = document.getElementById('themeIcon');

    if (currentTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'light');
        localStorage.setItem('theme', 'light');
        if (themeIcon) themeIcon.textContent = '🌙';
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
        if (themeIcon) themeIcon.textContent = '☀️';
    }
}
