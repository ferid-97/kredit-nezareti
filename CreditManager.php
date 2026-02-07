<?php
/**
 * Bank Kreditləri Nəzarət Sistemi - Backend Class
 * İki növ: Kredit (uzunmüddətli) və Qısa müddətli borc (kart xərcləri)
 * + Bank nəzarəti (limit ilə)
 */

class CreditManager {
    private $dataFile;
    private $banksFile;
    
    public function __construct($dataFile, $banksFile = 'banks_data.dat') {
        $this->dataFile = $dataFile;
        $this->banksFile = $banksFile;
        $this->initializeFiles();
    }
    
    private function initializeFiles() {
        if (!file_exists($this->dataFile)) {
            $this->saveData([]);
        }
        if (!file_exists($this->banksFile)) {
            $this->saveBanks($this->getDefaultBanks());
        }
    }
    
    private function getDefaultBanks() {
        // Artıq default bank yaratmırıq - istifadəçi özü əlavə etməlidir
        return [];
    }
    
    private function loadData() {
        if (!file_exists($this->dataFile)) {
            return [];
        }
        
        $content = file_get_contents($this->dataFile);
        if (empty($content)) {
            return [];
        }
        
        $decoded = base64_decode($content);
        $data = unserialize($decoded);
        
        return is_array($data) ? $data : [];
    }
    
    private function saveData($data) {
        $serialized = serialize($data);
        $encoded = base64_encode($serialized);
        
        return file_put_contents($this->dataFile, $encoded, LOCK_EX) !== false;
    }
    
    private function loadBanks() {
        if (!file_exists($this->banksFile)) {
            return $this->getDefaultBanks();
        }
        
        $content = file_get_contents($this->banksFile);
        if (empty($content)) {
            return $this->getDefaultBanks();
        }
        
        $decoded = base64_decode($content);
        $data = unserialize($decoded);
        
        return is_array($data) ? $data : $this->getDefaultBanks();
    }
    
    private function saveBanks($banks) {
        $serialized = serialize($banks);
        $encoded = base64_encode($serialized);
        
        return file_put_contents($this->banksFile, $encoded, LOCK_EX) !== false;
    }
    
    private function generateId() {
        return uniqid('credit_', true);
    }
    
    // ===== BANK METHODS =====
    
    /**
     * Bütün bankları əldə et
     */
    public function getAllBanks() {
        $banks = $this->loadBanks();
        
        usort($banks, function($a, $b) {
            // Default bank həmişə sonda
            if (isset($a['isDefault']) && $a['isDefault']) return 1;
            if (isset($b['isDefault']) && $b['isDefault']) return -1;
            return strtotime($b['createdAt']) - strtotime($a['createdAt']);
        });
        
        return $banks;
    }
    
    /**
     * ID-yə görə bank tap
     */
    public function getBankById($id) {
        $banks = $this->loadBanks();
        
        foreach ($banks as $bank) {
            if ($bank['id'] === $id) {
                return $bank;
            }
        }
        
        return null;
    }
    
    /**
     * Yeni bank əlavə et
     */
    public function addBank($bankData) {
        $errors = $this->validateBank($bankData, null);
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validasiya xətası', 'errors' => $errors];
        }
        
        $banks = $this->loadBanks();
        
        $newBank = [
            'id' => uniqid('bank_', true),
            'name' => trim($bankData['name']),
            'limit' => floatval($bankData['limit'] ?? 2250),
            'isDefault' => false,
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s')
        ];
        
        $banks[] = $newBank;
        
        if ($this->saveBanks($banks)) {
            return ['success' => true, 'message' => 'Bank uğurla əlavə edildi', 'bank' => $newBank];
        }
        
        return ['success' => false, 'message' => 'Bank əlavə edilə bilmədi'];
    }
    
    /**
     * Bankı yenilə
     */
    public function updateBank($id, $bankData) {
        $errors = $this->validateBank($bankData, $id);
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validasiya xətası', 'errors' => $errors];
        }
        
        $banks = $this->loadBanks();
        $found = false;
        
        foreach ($banks as &$bank) {
            if ($bank['id'] === $id) {
                // Default bankı dəyişdirmə
                if (isset($bank['isDefault']) && $bank['isDefault']) {
                    return ['success' => false, 'message' => 'Default bankı dəyişdirə bilməzsiniz'];
                }
                
                $bank['name'] = trim($bankData['name']);
                if (isset($bankData['limit'])) {
                    $bank['limit'] = floatval($bankData['limit']);
                }
                $bank['updatedAt'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            return ['success' => false, 'message' => 'Bank tapılmadı'];
        }
        
        if ($this->saveBanks($banks)) {
            return ['success' => true, 'message' => 'Bank yeniləndi'];
        }
        
        return ['success' => false, 'message' => 'Məlumatlar yazıla bilmədi'];
    }
    
    /**
     * Bankı sil
     */
    public function deleteBank($id) {
        $banks = $this->loadBanks();
        $newBanks = [];
        $found = false;
        
        foreach ($banks as $bank) {
            if ($bank['id'] === $id) {
                // Default bankı silmə
                if (isset($bank['isDefault']) && $bank['isDefault']) {
                    return ['success' => false, 'message' => 'Default bankı silə bilməzsiniz'];
                }
                $found = true;
            } else {
                $newBanks[] = $bank;
            }
        }
        
        if (!$found) {
            return ['success' => false, 'message' => 'Bank tapılmadı'];
        }
        
        // Bu banka aid kreditləri və borcları da sil
        $credits = $this->loadData();
        $newCredits = [];
        
        foreach ($credits as $credit) {
            if (!isset($credit['bankId']) || $credit['bankId'] !== $id) {
                $newCredits[] = $credit;
            }
        }
        
        if ($this->saveBanks($newBanks) && $this->saveData($newCredits)) {
            return ['success' => true, 'message' => 'Bank və ona aid məlumatlar silindi'];
        }
        
        return ['success' => false, 'message' => 'Məlumatlar yazıla bilmədi'];
    }
    
    /**
     * Bank validasiyası
     */
    private function validateBank($data, $excludeId = null) {
        $errors = [];
        
        if (empty($data['name']) || !is_string($data['name'])) {
            $errors[] = 'Bank adı tələb olunur';
        }
        
        // Eyni adlı bankın olub olmadığını yoxla
        if (!empty($data['name'])) {
            $banks = $this->loadBanks();
            $name = trim($data['name']);
            
            foreach ($banks as $bank) {
                // Update zamanı özünü yoxla
                if ($excludeId && $bank['id'] === $excludeId) {
                    continue;
                }
                
                if (strcasecmp($bank['name'], $name) === 0) {
                    $errors[] = 'Bu bank adı artıq mövcuddur';
                    break;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Banka görə istifadə olunan məbləği hesabla
     */
    public function getBankUsage($bankId) {
        $data = $this->loadData();
        $usage = 0;
        
        foreach ($data as $item) {
            if (isset($item['bankId']) && $item['bankId'] === $bankId) {
                $itemType = isset($item['type']) ? $item['type'] : 'credit';
                
                if ($itemType === 'credit') {
                    // Kredit üçün qalan məbləğ
                    $monthlyPayment = $item['totalAmount'] / $item['monthCount'];
                    $paidCount = count(array_filter($item['payments'], function($p) { return $p['paid']; }));
                    $usage += $item['totalAmount'] - ($paidCount * $monthlyPayment);
                } elseif ($itemType === 'short') {
                    // Qısa borc üçün ödənilməmiş məbləğ
                    if (!$item['paid']) {
                        $usage += $item['amount'];
                    }
                }
            }
        }
        
        return $usage;
    }
    
    /**
     * Banka görə kreditləri say
     */
    public function countCreditsByBank($bankId) {
        $data = $this->loadData();
        $count = 0;
        
        foreach ($data as $item) {
            if (isset($item['bankId']) && $item['bankId'] === $bankId) {
                $count++;
            }
        }
        
        return $count;
    }
    
    // ===== CREDIT METHODS =====
    
    /**
     * Bütün kreditləri əldə et
     */
    public function getAllCredits() {
        $data = $this->loadData();
        
        usort($data, function($a, $b) {
            return strtotime($b['createdAt']) - strtotime($a['createdAt']);
        });
        
        return $data;
    }
    
    /**
     * Növə görə kreditləri əldə et
     */
    public function getCreditsByType($type, $bankId = null) {
        $data = $this->loadData();
        
        $filtered = array_filter($data, function($item) use ($type, $bankId) {
            // Fallback for old data without 'type' field - treat as 'credit'
            $itemType = isset($item['type']) ? $item['type'] : 'credit';
            
            if ($itemType !== $type) {
                return false;
            }
            
            // Bank filter
            if ($bankId !== null && $bankId !== '') {
                return isset($item['bankId']) && $item['bankId'] === $bankId;
            }
            
            return true;
        });
        
        usort($filtered, function($a, $b) {
            return strtotime($b['createdAt']) - strtotime($a['createdAt']);
        });
        
        return array_values($filtered);
    }
    
    /**
     * ID-yə görə kredit tap
     */
    public function getCreditById($id) {
        $data = $this->loadData();
        
        foreach ($data as $credit) {
            if ($credit['id'] === $id) {
                return $credit;
            }
        }
        
        return null;
    }
    
    /**
     * Yeni kredit əlavə et (Uzunmüddətli)
     */
    public function addCredit($creditData) {
        $errors = $this->validateCredit($creditData);
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validasiya xətası', 'errors' => $errors];
        }
        
        $data = $this->loadData();
        
        $monthCount = intval($creditData['monthCount']);
        $payments = [];
        for ($i = 0; $i < $monthCount; $i++) {
            $payments[] = ['month' => $i + 1, 'paid' => false, 'paidDate' => null];
        }
        
        $newCredit = [
            'id' => $this->generateId(),
            'type' => 'credit',
            'bankId' => $creditData['bankId'] ?? 'bank_default_1',
            'bankName' => trim($creditData['bankName']),
            'totalAmount' => floatval($creditData['totalAmount']),
            'monthCount' => $monthCount,
            'startDate' => $creditData['startDate'],
            'notes' => trim($creditData['notes'] ?? ''),
            'payments' => $payments,
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s')
        ];
        
        $data[] = $newCredit;
        
        if ($this->saveData($data)) {
            return ['success' => true, 'message' => 'Kredit uğurla əlavə edildi', 'credit' => $newCredit];
        }
        
        return ['success' => false, 'message' => 'Məlumatlar yazıla bilmədi'];
    }
    
    /**
     * Qısa müddətli borc əlavə et
     */
    public function addShortTermDebt($debtData) {
        $errors = $this->validateShortTermDebt($debtData);
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validasiya xətası', 'errors' => $errors];
        }
        
        $data = $this->loadData();
        
        // Son ödəniş tarixi: növbəti ayın son günü (63 günə qədər faizsiz)
        $expenseDate = $debtData['expenseDate'];
        $expenseDateTime = new DateTime($expenseDate);
        $dueDateTime = clone $expenseDateTime;
        
        // Növbəti ayın son gününü tap
        $dueDateTime->modify('+1 month')->modify('last day of this month');
        
        $newDebt = [
            'id' => $this->generateId(),
            'type' => 'short',
            'bankId' => $debtData['bankId'] ?? 'bank_default_1',
            'category' => trim($debtData['category']),
            'amount' => floatval($debtData['amount']),
            'expenseDate' => $expenseDate,
            'dueDate' => $dueDateTime->format('Y-m-d'),
            'description' => trim($debtData['description'] ?? ''),
            'paid' => false,
            'paidDate' => null,
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s')
        ];
        
        $data[] = $newDebt;
        
        if ($this->saveData($data)) {
            return ['success' => true, 'message' => 'Borc uğurla əlavə edildi', 'debt' => $newDebt];
        }
        
        return ['success' => false, 'message' => 'Məlumatlar yazıla bilmədi'];
    }
    
    /**
     * Aylıq ödənişi toggle et (Kredit)
     */
    public function togglePayment($id, $monthIndex) {
        $data = $this->loadData();
        $found = false;
        
        foreach ($data as &$credit) {
            // Fallback: old data without 'type' is treated as 'credit'
            $creditType = isset($credit['type']) ? $credit['type'] : 'credit';
            
            if ($credit['id'] === $id && $creditType === 'credit') {
                if (isset($credit['payments'][$monthIndex])) {
                    $credit['payments'][$monthIndex]['paid'] = !$credit['payments'][$monthIndex]['paid'];
                    $credit['payments'][$monthIndex]['paidDate'] = $credit['payments'][$monthIndex]['paid'] 
                        ? date('Y-m-d H:i:s') 
                        : null;
                    $credit['updatedAt'] = date('Y-m-d H:i:s');
                    $found = true;
                }
                break;
            }
        }
        
        if (!$found) {
            return ['success' => false, 'message' => 'Kredit və ya ay tapılmadı'];
        }
        
        if ($this->saveData($data)) {
            return ['success' => true, 'message' => 'Ödəniş statusu yeniləndi'];
        }
        
        return ['success' => false, 'message' => 'Məlumatlar yazıla bilmədi'];
    }
    
    /**
     * Qısa borc ödənişi toggle et
     */
    public function toggleShortTermPayment($id) {
        $data = $this->loadData();
        $found = false;
        
        foreach ($data as &$debt) {
            $debtType = isset($debt['type']) ? $debt['type'] : 'credit';
            
            if ($debt['id'] === $id && $debtType === 'short') {
                $debt['paid'] = !$debt['paid'];
                $debt['paidDate'] = $debt['paid'] ? date('Y-m-d H:i:s') : null;
                $debt['updatedAt'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            return ['success' => false, 'message' => 'Borc tapılmadı'];
        }
        
        if ($this->saveData($data)) {
            return ['success' => true, 'message' => 'Ödəniş statusu yeniləndi'];
        }
        
        return ['success' => false, 'message' => 'Məlumatlar yazıla bilmədi'];
    }
    
    /**
     * Krediti sil
     */
    public function deleteCredit($id) {
        $data = $this->loadData();
        $newData = [];
        $found = false;
        
        foreach ($data as $credit) {
            if ($credit['id'] !== $id) {
                $newData[] = $credit;
            } else {
                $found = true;
            }
        }
        
        if (!$found) {
            return ['success' => false, 'message' => 'Kredit tapılmadı'];
        }
        
        if ($this->saveData($newData)) {
            return ['success' => true, 'message' => 'Kredit uğurla silindi'];
        }
        
        return ['success' => false, 'message' => 'Məlumatlar yazıla bilmədi'];
    }
    
    /**
     * Kredit validasiyası
     */
    private function validateCredit($data) {
        $errors = [];
        
        if (empty($data['bankName']) || !is_string($data['bankName'])) {
            $errors[] = 'Bank adı tələb olunur';
        }
        
        if (!isset($data['totalAmount']) || !is_numeric($data['totalAmount'])) {
            $errors[] = 'Ümumi məbləğ tələb olunur';
        } elseif (floatval($data['totalAmount']) <= 0) {
            $errors[] = 'Məbləğ 0-dan böyük olmalıdır';
        }
        
        if (!isset($data['monthCount']) || !is_numeric($data['monthCount'])) {
            $errors[] = 'Ay sayı tələb olunur';
        } elseif (intval($data['monthCount']) < 1 || intval($data['monthCount']) > 60) {
            $errors[] = 'Ay sayı 1-60 arasında olmalıdır';
        }
        
        if (empty($data['startDate'])) {
            $errors[] = 'Başlama tarixi tələb olunur';
        } elseif (!$this->isValidDate($data['startDate'])) {
            $errors[] = 'Başlama tarixi düzgün formatda deyil';
        }
        
        return $errors;
    }
    
    /**
     * Qısa müddətli borc validasiyası
     */
    private function validateShortTermDebt($data) {
        $errors = [];
        
        if (empty($data['category']) || !is_string($data['category'])) {
            $errors[] = 'Kategoriya tələb olunur';
        }
        
        if (!isset($data['amount']) || !is_numeric($data['amount'])) {
            $errors[] = 'Məbləğ tələb olunur';
        } elseif (floatval($data['amount']) <= 0) {
            $errors[] = 'Məbləğ 0-dan böyük olmalıdır';
        }
        
        if (empty($data['expenseDate'])) {
            $errors[] = 'Xərc tarixi tələb olunur';
        } elseif (!$this->isValidDate($data['expenseDate'])) {
            $errors[] = 'Xərc tarixi düzgün formatda deyil';
        }
        
        return $errors;
    }
    
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Statistika
     */
    public function getStatistics($bankId = null) {
        $credits = $this->getAllCredits();
        
        $stats = [
            'totalCredits' => 0,
            'totalAmount' => 0,
            'totalPaid' => 0,
            'totalRemaining' => 0,
            'shortTermDebt' => 0,
            'shortTermPaid' => 0,
            'shortTermRemaining' => 0
        ];
        
        foreach ($credits as $item) {
            // Bank filter
            if ($bankId !== null && $bankId !== '') {
                if (!isset($item['bankId']) || $item['bankId'] !== $bankId) {
                    continue;
                }
            }
            
            // Fallback: old data without 'type' is treated as 'credit'
            $itemType = isset($item['type']) ? $item['type'] : 'credit';
            
            if ($itemType === 'credit') {
                $monthlyPayment = $item['totalAmount'] / $item['monthCount'];
                $paidCount = 0;
                 
                foreach ($item['payments'] as $payment) {
                    if ($payment['paid']) $paidCount++;
                }
                 
                $stats['totalCredits']++;
                $stats['totalAmount'] += $item['totalAmount'];
                $stats['totalPaid'] += $paidCount * $monthlyPayment;
            } elseif ($itemType === 'short') {
                $stats['shortTermDebt'] += $item['amount'];
                if ($item['paid']) {
                    $stats['shortTermPaid'] += $item['amount'];
                }
            }
        }
        
        $stats['totalRemaining'] = $stats['totalAmount'] - $stats['totalPaid'];
        $stats['shortTermRemaining'] = $stats['shortTermDebt'] - $stats['shortTermPaid'];
        
        // Kart limiti üçün ümumi qalıq
        $stats['cardLimitUsed'] = $stats['totalRemaining'] + $stats['shortTermRemaining'];
        
        return $stats;
    }
}
