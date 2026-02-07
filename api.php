<?php
/**
 * API - AJAX endpoints for Credit + Short Term Debt
 * + Bank Management
 */

require_once 'CreditManager.php';

header('Content-Type: application/json; charset=utf-8');

$manager = new CreditManager('credits_data.dat');

// GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            $bankId = $_GET['bankId'] ?? null;
            $credits = $manager->getCreditsByType('credit', $bankId);
            $shortTerms = $manager->getCreditsByType('short', $bankId);
            
            // Get bank limit
            $bank = $bankId ? $manager->getBankById($bankId) : null;
            $bankLimit = $bank ? ($bank['limit'] ?? 2250) : 2250;
             
            // Credits HTML
            $creditsHtml = '';
            if (empty($credits)) {
                $creditsHtml = '<div class="empty-state"><p class="empty-text">Heç bir kredit yoxdur</p></div>';
            } else {
                foreach ($credits as $credit) {
                    $monthlyPayment = $credit['totalAmount'] / $credit['monthCount'];
                    $paidCount = count(array_filter($credit['payments'], function($p) { return $p['paid']; }));
                    $progress = ($paidCount / $credit['monthCount']) * 100;
                    
                    $creditsHtml .= '<div class="loan-card" data-id="' . htmlspecialchars($credit['id']) . '">
                        <div class="loan-header">
                            <div class="loan-info">
                                <h3 class="loan-title">' . htmlspecialchars($credit['bankName']) . '</h3>
                                <p class="loan-meta">' . $credit['monthCount'] . ' ay · ₼' . number_format($monthlyPayment, 2) . '/ay</p>
                            </div>
                            <div class="loan-amount">
                                <span class="amount-value">₼' . number_format($credit['totalAmount'], 2) . '</span>
                            </div>
                        </div>
                        <div class="loan-progress">
                            <div class="progress-info">
                                <span class="progress-label">' . $paidCount . '/' . $credit['monthCount'] . ' ödənilib</span>
                                <span class="progress-percent">' . number_format($progress, 0) . '%</span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width: ' . $progress . '%"></div>
                            </div>
                        </div>
                        <div class="loan-actions">
                            <button class="btn-secondary btn-sm" onclick="openPaymentsModal(\'' . htmlspecialchars($credit['id']) . '\')">Ödənişlər</button>
                            <button class="btn-text btn-sm" onclick="deleteCredit(\'' . htmlspecialchars($credit['id']) . '\')">Sil</button>
                        </div>
                    </div>';
                }
            }
            
            // Short Term Debts HTML
            $shortTermHtml = '';
            if (empty($shortTerms)) {
                $shortTermHtml = '<div class="empty-state"><p class="empty-text">Heç bir qısa müddətli borc yoxdur</p></div>';
            } else {
                foreach ($shortTerms as $debt) {
                    $isOverdue = !$debt['paid'] && strtotime($debt['dueDate']) < time();
                    
                    $shortTermHtml .= '<div class="debt-card ' . ($debt['paid'] ? 'paid' : ($isOverdue ? 'overdue' : '')) . '" data-id="' . htmlspecialchars($debt['id']) . '">
                        <div class="debt-check" onclick="toggleShortTermPayment(\'' . htmlspecialchars($debt['id']) . '\')">' . ($debt['paid'] ? '✓' : '') . '</div>
                        <div class="debt-info">
                            <div class="debt-header">
                                <span class="debt-category">' . htmlspecialchars($debt['category']) . '</span>
                                <span class="debt-amount">₼' . number_format($debt['amount'], 2) . '</span>
                            </div>' .
                            (!empty($debt['description']) ? '<p class="debt-description">' . htmlspecialchars($debt['description']) . '</p>' : '') . '
                            <div class="debt-dates">
                                <span class="debt-date">Xərc: ' . date('d.m.Y', strtotime($debt['expenseDate'])) . '</span>
                                <span class="debt-due ' . ($isOverdue ? 'overdue' : '') . '">Son: ' . date('d.m.Y', strtotime($debt['dueDate'])) . '</span>
                            </div>
                        </div>
                        <button class="btn-text btn-sm" onclick="deleteCredit(\'' . htmlspecialchars($debt['id']) . '\')">Sil</button>
                    </div>';
                }
            }
            
            $stats = $manager->getStatistics($bankId);
            
            echo json_encode([
                'success' => true,
                'credits' => $credits,
                'creditsHtml' => $creditsHtml,
                'shortTerms' => $shortTerms,
                'shortTermHtml' => $shortTermHtml,
                'stats' => $stats,
                'bankLimit' => $bankLimit
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID required'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $credit = $manager->getCreditById($id);
            if ($credit) {
                echo json_encode(['success' => true, 'credit' => $credit], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => 'Not found'], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'banks':
            $banks = $manager->getAllBanks();
            $banksHtml = '';
            
            foreach ($banks as $bank) {
                $usage = $manager->getBankUsage($bank['id']);
                $limit = isset($bank['limit']) ? $bank['limit'] : 2250;
                $usagePercent = $limit > 0 ? min(($usage / $limit) * 100, 100) : 0;
                $isDefault = isset($bank['isDefault']) && $bank['isDefault'];
                $remaining = $limit - $usage;
                
                $progressClass = $usagePercent > 80 ? 'danger' : ($usagePercent > 50 ? 'warning' : '');
                
                $banksHtml .= '<div class="bank-item ' . ($isDefault ? 'bank-default' : '') . '" data-id="' . htmlspecialchars($bank['id']) . '">
                    <div class="bank-icon">🏦</div>
                    <div class="bank-info">
                        <div class="bank-name-row">
                            <span class="bank-name">' . htmlspecialchars($bank['name']) . '</span>
                            ' . ($isDefault ? '<span class="bank-badge">Default</span>' : '') . '
                        </div>
                        <div class="bank-limit-info">
                            <span class="bank-usage">₼' . number_format($usage, 2) . ' / ₼' . number_format($limit, 2) . '</span>
                            <span class="bank-remaining ' . $progressClass . '">(Qalan: ₼' . number_format($remaining, 2) . ')</span>
                        </div>
                        <div class="bank-progress-bar">
                            <div class="bank-progress ' . $progressClass . '" style="width: ' . $usagePercent . '%"></div>
                        </div>
                    </div>
                    <div class="bank-actions">
                        <button class="bank-view-btn" onclick="selectBank(\'' . htmlspecialchars($bank['id']) . '\')" title="Bax">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                        ' . (!$isDefault ? '<button class="bank-delete" onclick="deleteBank(\'' . htmlspecialchars($bank['id']) . '\')">×</button>' : '') . '
                    </div>
                </div>';
            }
            
            echo json_encode([
                'success' => true,
                'banks' => $banks,
                'banksHtml' => $banksHtml
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'bank':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID required'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $bank = $manager->getBankById($id);
            if ($bank) {
                echo json_encode(['success' => true, 'bank' => $bank], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => 'Not found'], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
    }
}
// POST
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $action = $input['action'] ?? null;
    
    switch ($action) {
        case 'addCredit':
            $result = $manager->addCredit($input);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'addShortTerm':
            $result = $manager->addShortTermDebt($input);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'togglePayment':
            $id = $input['id'] ?? null;
            $monthIndex = $input['monthIndex'] ?? null;
            
            if (!$id || $monthIndex === null) {
                echo json_encode(['success' => false, 'message' => 'ID and monthIndex required'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $result = $manager->togglePayment($id, intval($monthIndex));
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'toggleShortTermPayment':
            $id = $input['id'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID required'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $result = $manager->toggleShortTermPayment($id);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'delete':
            $id = $input['id'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID required'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $result = $manager->deleteCredit($id);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'addBank':
            $result = $manager->addBank($input);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'updateBank':
            $id = $input['id'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID required'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $result = $manager->updateBank($id, $input);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'deleteBank':
            $id = $input['id'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID required'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $result = $manager->deleteBank($id);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
    }
}
