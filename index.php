<?php
/**
 * Bank Kreditləri Nəzarət Sistemi
 * Kredit + Qısa Müddətli Borc + Bank Nəzarəti
 */

require_once 'CreditManager.php';

$creditManager = new CreditManager('credits_data.dat');
$banks = $creditManager->getAllBanks();
$hasBanks = count($banks) > 0;

// Get selected bank from cookie
$selectedBankId = isset($_COOKIE['selectedBankId']) ? $_COOKIE['selectedBankId'] : '';
$selectedBank = null;
$bankLimit = 2250; // Default limit

// Modal açıq olmalıdır? (Bank yoxdursa VƏ YA bank var amma cookie yoxdursa)
$showBankModal = !$hasBanks || ($hasBanks && empty($selectedBankId));

if ($selectedBankId && $hasBanks) {
    foreach ($banks as $bank) {
        if ($bank['id'] === $selectedBankId) {
            $selectedBank = $bank;
            $bankLimit = isset($bank['limit']) ? $bank['limit'] : 2250;
            break;
        }
    }
}
$credits = $creditManager->getCreditsByType('credit', $selectedBankId ?: null);
$shortTermDebts = $creditManager->getCreditsByType('short', $selectedBankId ?: null);
$stats = $creditManager->getStatistics($selectedBankId ?: null);
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kredit Nəzarəti</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app">
        <!-- Header -->
        <header class="app-header">
            <div class="container">
                <div class="header-content">
                    <div class="header-left">
                        <h1 class="app-title">Kredit Nəzarəti</h1>
                        <p class="app-subtitle">Kreditlər və kart borclarını izləyin</p>
                    </div>
                    <div class="header-right">
                        <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Qaranlıq rejim">
                            <span id="themeIcon">🌙</span>
                        </button>
                        <button class="btn-secondary" onclick="openBankModal()">
                            <span>🏦</span> <?php echo $selectedBank ? htmlspecialchars($selectedBank['name']) : 'Banklar'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Stats Bar -->
        <div class="stats-bar" <?php if ($showBankModal) echo 'style="display:none"'; ?>>
            <div class="container">
                <div class="stats-grid stats-grid-main">
                    <div class="stat-item">
                        <span class="stat-label">Aktiv Kreditlər</span>
                        <span class="stat-value" id="totalCredits"><?php echo $stats['totalCredits']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Kredit Məbləği</span>
                        <span class="stat-value" id="totalAmount">₼<?php echo number_format($stats['totalAmount'], 2, '.', ','); ?></span>
                    </div>
                    <div class="stat-item stat-positive">
                        <span class="stat-label">Ödənilmiş</span>
                        <span class="stat-value" id="totalPaid">₼<?php echo number_format($stats['totalPaid'], 2, '.', ','); ?></span>
                    </div>
                    <div class="stat-item stat-negative">
                        <span class="stat-label">Kredit Qalıq</span>
                        <span class="stat-value" id="totalRemaining">₼<?php echo number_format($stats['totalRemaining'], 2, '.', ','); ?></span>
                    </div>
                    <div class="stat-item stat-warning">
                        <span class="stat-label">Qısa Müddət Borc</span>
                        <span class="stat-value" id="shortTermRemaining">₼<?php echo number_format($stats['shortTermRemaining'], 2, '.', ','); ?></span>
                    </div>
                </div>
                
                <!-- Kart Limiti Bar -->
                <div class="card-limit-section">
                    <div class="card-limit-info">
                        <span class="card-limit-label">Kart Limiti İstifadəsi</span>
                        <div class="card-limit-amount">
                            <span id="cardLimitUsed">₼<?php echo number_format($stats['cardLimitUsed'], 2, '.', ','); ?></span>
                            <span class="card-limit-divider">/</span>
                            <span class="card-limit-total">₼<?php echo number_format($bankLimit, 2); ?></span>
                            <span class="card-limit-remaining">(Qalan: ₼<?php echo number_format($bankLimit - $stats['cardLimitUsed'], 2, '.', ','); ?>)</span>
                        </div>
                    </div>
                    <div class="card-limit-bar">
                        <div class="card-limit-progress" id="cardLimitProgress" style="width: <?php echo min(($stats['cardLimitUsed'] / $bankLimit) * 100, 100); ?>%"></div>
                    </div>
                    <span class="card-limit-percent"><?php echo number_format(($stats['cardLimitUsed'] / $bankLimit) * 100, 1); ?>%</span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="app-main" <?php if ($showBankModal) echo 'style="display:none"'; ?>>
            <div class="container">
                <div class="content-grid">
                    <!-- Add Form Section -->
                    <div class="form-section">
                        <!-- Tabs -->
                        <div class="form-tabs">
                            <button class="form-tab active" data-tab="credit">Yeni Kredit</button>
                            <button class="form-tab" data-tab="short">Qısa Müddətli Borc</button>
                        </div>
                        
                        <!-- Kredit Form -->
                        <form id="creditForm" class="loan-form tab-content active" data-tab="credit">
                            <input type="hidden" name="bankId" id="creditBankSelect" value="<?php echo htmlspecialchars($selectedBankId ?: 'bank_default_1'); ?>">
                            <div class="form-field">
                                <label class="field-label">Başlıq / Açıqlama</label>
                                <input type="text" name="bankName" class="field-input" placeholder="Kredit adı" required>
                            </div>
                            <div class="form-row">
                                <div class="form-field">
                                    <label class="field-label">Məbləğ (₼)</label>
                                    <input type="number" name="totalAmount" class="field-input" placeholder="0.00" step="0.01" min="0" required>
                                </div>
                                <div class="form-field">
                                    <label class="field-label">Müddət</label>
                                    <select name="monthCount" class="field-input" required>
                                        <option value="">Seç</option>
                                        <option value="3">3 ay</option>
                                        <option value="6">6 ay</option>
                                        <option value="9">9 ay</option>
                                        <option value="12">12 ay</option>
                                        <option value="18">18 ay</option>
                                        <option value="24">24 ay</option>
                                        <option value="36">36 ay</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-field">
                                <label class="field-label">Başlama Tarixi</label>
                                <input type="date" name="startDate" class="field-input" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-field">
                                <label class="field-label">Qeyd</label>
                                <textarea name="notes" class="field-input field-textarea" rows="2" placeholder="Əlavə məlumat..."></textarea>
                            </div>
                            <button type="submit" class="btn-primary">Kredit Əlavə Et</button>
                        </form>
                        
                        <!-- Qısa Müddətli Borc Form -->
                        <form id="shortTermForm" class="loan-form tab-content" data-tab="short">
                            <input type="hidden" name="bankId" id="shortBankSelect" value="<?php echo htmlspecialchars($selectedBankId ?: 'bank_default_1'); ?>">
                            <div class="form-info-box">
                                <span class="info-icon">ℹ️</span>
                                <span>63 günə qədər faizsiz güzəşt - xərcləmə tarixindən növbəti ayın son gününədək ödəyin</span>
                            </div>
                            <div class="form-field">
                                <label class="field-label">Kategoriya</label>
                                <select name="category" class="field-input" required>
                                    <option value="">Seç</option>
                                    <option value="Alış-veriş">Alış-veriş</option>
                                    <option value="Restoran">Restoran / Yemək</option>
                                    <option value="Nəqliyyat">Nəqliyyat</option>
                                    <option value="Kommunal">Kommunal xidmətlər</option>
                                    <option value="Sağlamlıq">Sağlamlıq</option>
                                    <option value="Əyləncə">Əyləncə</option>
                                    <option value="Digər">Digər</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <div class="form-field">
                                    <label class="field-label">Məbləğ (₼)</label>
                                    <input type="number" name="amount" class="field-input" placeholder="0.00" step="0.01" min="0" required>
                                </div>
                                <div class="form-field">
                                    <label class="field-label">Xərc Tarixi</label>
                                    <input type="date" name="expenseDate" class="field-input" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="form-field">
                                <label class="field-label">Açıqlama</label>
                                <textarea name="description" class="field-input field-textarea" rows="2" placeholder="Nə üçün xərclənib..."></textarea>
                            </div>
                            <button type="submit" class="btn-primary">Borc Əlavə Et</button>
                        </form>
                    </div>

                    <!-- List Section -->
                    <div class="list-section">
                         
                        <!-- List Tabs -->
                        <div class="list-tabs">
                            <button class="list-tab active" data-list="credits">Kreditlər</button>
                            <button class="list-tab" data-list="shortterm">Qısa Borclar</button>
                        </div>
                        
                        <!-- Kreditlər List -->
                        <div class="loan-list list-content active" id="creditsList" data-list="credits">
                            <?php if (empty($credits)): ?>
                            <div class="empty-state">
                                <p class="empty-text">Heç bir kredit yoxdur</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($credits as $credit): ?>
                                    <?php
                                        $monthlyPayment = $credit['totalAmount'] / $credit['monthCount'];
                                        $paidCount = count(array_filter($credit['payments'], function($p) { return $p['paid']; }));
                                        $progress = ($paidCount / $credit['monthCount']) * 100;
                                    ?>
                                    <div class="loan-card" data-id="<?php echo htmlspecialchars($credit['id']); ?>">
                                        <div class="loan-header">
                                            <div class="loan-info">
                                                <h3 class="loan-title"><?php echo htmlspecialchars($credit['bankName']); ?></h3>
                                                <p class="loan-meta"><?php echo $credit['monthCount']; ?> ay · ₼<?php echo number_format($monthlyPayment, 2); ?>/ay</p>
                                            </div>
                                            <div class="loan-amount">
                                                <span class="amount-value">₼<?php echo number_format($credit['totalAmount'], 2); ?></span>
                                            </div>
                                        </div>
                                        <div class="loan-progress">
                                            <div class="progress-info">
                                                <span class="progress-label"><?php echo $paidCount; ?>/<?php echo $credit['monthCount']; ?> ödənilib</span>
                                                <span class="progress-percent"><?php echo number_format($progress, 0); ?>%</span>
                                            </div>
                                            <div class="progress-track">
                                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="loan-actions">
                                            <button class="btn-secondary btn-sm" onclick="openPaymentsModal('<?php echo htmlspecialchars($credit['id']); ?>')">Ödənişlər</button>
                                            <button class="btn-text btn-sm" onclick="deleteCredit('<?php echo htmlspecialchars($credit['id']); ?>')">Sil</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Qısa Müddətli Borclar List -->
                        <div class="loan-list list-content" id="shortTermList" data-list="shortterm">
                            <?php if (empty($shortTermDebts)): ?>
                            <div class="empty-state">
                                <p class="empty-text">Heç bir qısa müddətli borc yoxdur</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($shortTermDebts as $debt): ?>
                                    <?php
                                        $isOverdue = !$debt['paid'] && strtotime($debt['dueDate']) < time();
                                    ?>
                                    <div class="debt-card <?php echo $debt['paid'] ? 'paid' : ($isOverdue ? 'overdue' : ''); ?>" data-id="<?php echo htmlspecialchars($debt['id']); ?>">
                                        <div class="debt-check" onclick="toggleShortTermPayment('<?php echo htmlspecialchars($debt['id']); ?>')">
                                            <?php if ($debt['paid']): ?>✓<?php endif; ?>
                                        </div>
                                        <div class="debt-info">
                                            <div class="debt-header">
                                                <span class="debt-category"><?php echo htmlspecialchars($debt['category']); ?></span>
                                                <span class="debt-amount">₼<?php echo number_format($debt['amount'], 2); ?></span>
                                            </div>
                                            <?php if (!empty($debt['description'])): ?>
                                            <p class="debt-description"><?php echo htmlspecialchars($debt['description']); ?></p>
                                            <?php endif; ?>
                                            <div class="debt-dates">
                                                <span class="debt-date">Xərc: <?php echo date('d.m.Y', strtotime($debt['expenseDate'])); ?></span>
                                                <span class="debt-due <?php echo $isOverdue ? 'overdue' : ''; ?>">Son: <?php echo date('d.m.Y', strtotime($debt['dueDate'])); ?></span>
                                            </div>
                                        </div>
                                        <button class="btn-text btn-sm" onclick="deleteCredit('<?php echo htmlspecialchars($debt['id']); ?>')">Sil</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Payments Modal -->
    <div class="modal" id="paymentsModal">
        <div class="modal-overlay" onclick="closePaymentsModal()"></div>
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Aylıq Ödənişlər</h3>
                <button class="modal-close" onclick="closePaymentsModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="modal-summary">
                    <div class="summary-item">
                        <span class="summary-label">Kredit</span>
                        <span class="summary-value" id="modalBankName"></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Ümumi</span>
                        <span class="summary-value" id="modalTotalAmount"></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Aylıq</span>
                        <span class="summary-value" id="modalMonthlyPayment"></span>
                    </div>
                </div>
                <div class="modal-progress">
                    <div class="progress-stats">
                        <span class="progress-stat"><strong id="modalPaidMonths">0/0</strong> tamamlandı</span>
                        <span class="progress-stat">Ödənilib: <strong id="modalPaidAmount">₼0</strong></span>
                        <span class="progress-stat">Qalıq: <strong id="modalRemainingAmount">₼0</strong></span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" id="modalProgressBar"></div>
                    </div>
                </div>
                <div class="payments-grid" id="monthsGrid"></div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-overlay" onclick="closeDeleteModal()"></div>
        <div class="modal-dialog modal-sm">
            <div class="modal-header">
                <h3 class="modal-title">Silmək</h3>
                <button class="modal-close" onclick="closeDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p class="delete-message">Bu elementi silmək istədiyinizə əminsiniz?</p>
                <p class="delete-warning">Bu əməliyyat geri alına bilməz.</p>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeDeleteModal()">Ləğv Et</button>
                <button class="btn-danger" id="confirmDelete">Sil</button>
            </div>
        </div>
    </div>

    <!-- Bank Management Modal -->
    <div class="modal <?php if ($showBankModal) echo 'active'; ?>" id="bankModal" <?php if (!$hasBanks) echo 'data-no-banks="true"'; ?>>
        <div class="modal-overlay" onclick="closeBankModal()"></div>
        <div class="modal-dialog modal-md">
            <div class="modal-header">
                <h3 class="modal-title">🏦 Bank Nəzarəti</h3>
                <button class="modal-close" onclick="closeBankModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="bankForm" class="bank-form">
                    <div class="form-row">
                        <div class="form-field" style="flex: 1">
                            <label class="field-label">Bank Adı</label>
                            <input type="text" name="name" class="field-input" placeholder="Bank adı" required>
                        </div>
                        <div class="form-field" style="flex: 1">
                            <label class="field-label">Limit (₼)</label>
                            <input type="number" name="limit" class="field-input" placeholder="2250" step="0.01" min="0" value="2250" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Bank Əlavə Et</button>
                </form>
                
                <div class="banks-list" id="banksList">
                    <!-- Banks will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <div class="notification" id="notification">
        <span class="notification-message"></span>
    </div>

    <script>
        // Bank seçilməyibsə (bank yoxdursa VƏ YA cookie yoxdursa), modalı avtomatik açıq tut
        <?php if ($showBankModal): ?>
            document.addEventListener('DOMContentLoaded', () => {
                const bankModal = document.getElementById('bankModal');
                if (bankModal) {
                    bankModal.classList.add('active');
                    document.body.classList.add('modal-open');
                    <?php if (!$hasBanks): ?>
                    // Heç bank yoxdursa, overlay-i bloklə
                    const overlay = bankModal.querySelector('.modal-overlay');
                    if (overlay) {
                        overlay.style.pointerEvents = 'none';
                    }
                    <?php endif; ?>
                }
            });
        <?php endif; ?>
    </script>
    <script src="script.js"></script>
</body>
</html>
