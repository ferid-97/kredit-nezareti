# 💳 Kredit Nəzarət Sistemi

Modern və istifadəsi asan bank kredit və borc nəzarət sistemi.

## 🎯 Xüsusiyyətlər

### 🏦 Bank Nəzarəti
- **Çoxlu Bank Dəstəyi** - İstənilən sayda bank əlavə edin
- **Limit İzləmə** - Hər bank üçün ayrı limit təyin edin
- **Bank Statistikası** - Real-time istifadə məlumatları

### 💰 Kredit Nəzarəti
- **Uzunmüddətli Kreditlər** - 3-36 ay arası kreditlər
- **Aylıq Ödənişlər** - Hər ay üçün ayrı ödəniş izləmə
- **Avtomatik Hesablama** - Aylıq ödəniş avtomatik hesablanır
- **Progress Tracking** - Vizual ödəniş gedişi

### 📊 Qısa Müddətli Borclar
- **Kart Xərcləri** - Gündəlik xərcləri qeyd edin
- **Kategoriyalı İzləmə** - Alış-veriş, Restoran, Nəqliyyat və s.
- **63 Gün Güzəşt** - Avtomatik son ödəniş tarixi hesablanması
- **Vaxt Keçmiş Xəbərdarlıq** - Gecikmiş ödənişlər qırmızı rənglə göstərilir

### ⚡ Performans
- **Dərhal UI Yeniləməsi** - Server cavabını gözləmədən dəyişikliklər görünür
- **Optimized AJAX** - Background-da statistika yeniləməsi

### 🎨 İstifadəçi İnterfeysi
- **Modern Dizayn** - Təmiz və minimalist görünüş
- **Qaranlıq Rejim** - Gündüz/Gecə rejimi dəstəyi (localStorage-da saxlanılır)
- **Modal Blur Effect** - Modallar açılanda arxa fon blur olur
- **Responsive** - Mobil və desktop uygun

## 🚀 Quraşdırma

2. **İcazələr**
   ```bash
   chmod 666 banks_data.dat credits_data.dat
   ```

## 🔒 Təhlükəsizlik

- **Data Şifrələməsi** - Bütün məlumatlar base64 + serialize ilə şifrələnir
- **XSS Qorunması** - Bütün user input-lar təmizlənir (escapeHtml)
- **CSRF Token** - (Tövsiyə olunur: production-da əlavə edin)
- **Input Validasiya** - Həm client-side həm server-side

## 🛠️ Texnologiyalar

### Backend
- **PHP 7.4+** - Server-side məntiq
- **File-based Database** - .dat fayllarında data saxlama
- **REST API** - JSON formatında AJAX əlaqə

## 💡 İpuçuları

1. **Bank Limitləri** - Hər bank üçün real kart limitini daxil edin
2. **Kategoriyalar** - Qısa borcları kateqoriyalara ayırın və xərcləri izləyin
3. **Qeydlər** - Kreditlərə ətraflı qeydlər əlavə edin
4. **Müntəzəm Yoxlama** - Vaxtında ödəməmək üçün tez-tez yoxlayın
5. **Qaranlıq Rejim** - Gecə işləyərkən gözlərinizi qorumaq üçün qaranlıq rejimi aktiv edin (🌙/☀️ düyməsi)

## 👤 Müəllif

**Kredit Nəzarət Sistemi**
- Versiya: 1.1.0
- Son Yeniləmə: 2026-02-07
- Yeni: Qaranlıq Rejim Dəstəyi 🌙

---

**Qeyd:** Bu sistem şəxsi istifadə üçün nəzərdə tutulub. Production mühitində istifadə edərkən əlavə təhlükəsizlik tədbirləri görün.
