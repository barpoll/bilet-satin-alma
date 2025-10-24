<?php
// coupon_management.php

session_start();
require 'db.php'; 

// Yetkilendirme Kontrolü (Admin veya Firma Admin)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'firma_admin'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'];
$company_id = $_SESSION['company_id'] ?? null; // Admin için NULL, Firma Admin için firma ID'si

$error = '';
$success = $_GET['success'] ?? '';
$coupon_data = []; // Düzenleme için kupon verisi

// Ekleme veya Düzenleme İşlemi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_coupon'])) {
    
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discount_rate = (float)($_POST['discount_rate'] ?? 0); // Örn: 0.10 (%10 için)
    $usage_limit = (int)($_POST['usage_limit'] ?? 0);
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $coupon_id = $_POST['coupon_id'] ?? null; // Düzenleme ise ID içerir

    // Basit doğrulama
    if (empty($code) || $discount_rate <= 0 || $usage_limit <= 0 || empty($expiry_date)) {
        $error = "Lütfen tüm alanları geçerli değerlerle doldurunuz.";
    } elseif ($discount_rate > 1.0) {
        $error = "İndirim oranı 1.0'dan (%100) fazla olamaz.";
    } else {
        
        try {
            if ($coupon_id) {
                // Düzenleme İşlemi (UPDATE)
                $sql = "UPDATE coupons SET code = ?, discount_rate = ?, usage_limit = ?, expiry_date = ? 
                        WHERE id = ?";
                $params = [$code, $discount_rate, $usage_limit, $expiry_date, $coupon_id];
                
                // Güvenlik: Firma Admin ise sadece kendi kuponunu düzenleyebilmesi için kontrol
                if ($user_role === 'firma_admin') {
                    $sql .= " AND company_id = ?";
                    $params[] = $company_id;
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $success = "Kupon başarıyla güncellendi.";
                
            } else {
                // Ekleme İşlemi (CREATE)
                $target_company_id = ($user_role === 'admin') ? null : $company_id; 
                
                // Benzersizlik kontrolü
                $stmt_check = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
                $stmt_check->execute([$code]);
                if ($stmt_check->fetch()) {
                    $error = "Bu kupon kodu zaten mevcut.";
                } else {
                    $sql = "INSERT INTO coupons (code, discount_rate, usage_limit, expiry_date, company_id) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$code, $discount_rate, $usage_limit, $expiry_date, $target_company_id]);
                    $success = "Yeni kupon başarıyla oluşturuldu.";
                }
            }
            // Başarılı işlem sonrası formu sıfırla
            if ($success) {
                header("Location: coupon_management.php?success=" . urlencode($success));
                exit();
            }
            
        } catch (PDOException $e) {
            $error = "Veritabanı işlemi başarısız: " . $e->getMessage();
        }
    }
    // Hata durumunda veriyi koru
    $coupon_data = compact('code', 'discount_rate', 'usage_limit', 'expiry_date');
}

// Silme İşlemi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_coupon'])) {
    $coupon_id_to_delete = (int)$_POST['coupon_id'];

    try {
        $sql = "DELETE FROM coupons WHERE id = ?";
        $params = [$coupon_id_to_delete];

        // Güvenlik: Firma Admin ise sadece kendi kuponunu silebilmesi için kontrol
        if ($user_role === 'firma_admin') {
            $sql .= " AND company_id = ?";
            $params[] = $company_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $success = "Kupon başarıyla silindi.";
        header("Location: coupon_management.php?success=" . urlencode($success));
        exit();

    } catch (PDOException $e) {
        $error = "Silme işlemi başarısız: " . $e->getMessage();
    }
}

// Düzenleme Modu için veriyi yükle
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $sql = "SELECT * FROM coupons WHERE id = ?";
    $params = [$edit_id];

    // Firma Admin ise kendi firma ID'si ile kısıtla
    if ($user_role === 'firma_admin') {
        $sql .= " AND company_id = ?";
        $params[] = $company_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $coupon_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon_data) {
        $error = "Kupon bulunamadı veya yetkiniz yok.";
    }
}

// Kuponları Listeleme
try {
    $sql_list = "SELECT c.*, co.name AS company_name FROM coupons c 
                 LEFT JOIN companies co ON c.company_id = co.id
                 WHERE 1=1"; 
    $params_list = [];

    // Firma Admin ise sadece kendi firmasının kuponlarını göster
    if ($user_role === 'firma_admin') {
        $sql_list .= " AND c.company_id = ?";
        $params_list[] = $company_id;
        $panel_name = "Firma Yönetimi";
        $return_link = "firma_admin_panel.php";
    } else {
        // Admin ise tüm kuponları göster
        $panel_name = "Sistem Yönetimi";
        $return_link = "admin_panel.php";
    }

    $sql_list .= " ORDER BY c.id DESC";
    $stmt_list = $pdo->prepare($sql_list);
    $stmt_list->execute($params_list);
    $coupons = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Kuponlar listelenirken veritabanı hatası oluştu.";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $panel_name ?> - Kupon Yönetimi</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivrne t/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style> 
        body { background-color: #f8f9fa; }
        .coupon-card { border-left: 5px solid #ffc107; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .expired-card { border-left-color: #dc3545 !important; background-color: #f8d7da; }
        .used-up-card { border-left-color: #6c757d !important; opacity: 0.8; }
        .general-coupon { border-left-color: #007bff !important; }
        .coupon-header { background-color: #f0f0f0; padding: 10px 15px; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h4 mb-0"><i class="fas fa-tags me-2"></i> <?= $panel_name ?> - Kupon Yönetimi</h1>
            <a href="<?= $return_link ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Geri Dön</a>
        </div>
        
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        
        <!-- Kupon Ekleme/Düzenleme Formu -->
        <div class="card shadow-sm mb-5">
            <div class="card-header bg-primary text-white">
                <h2 class="h6 mb-0"><i class="fas fa-plus me-1"></i> <?= $coupon_data ? 'Kupon Düzenle (ID: ' . $coupon_data['id'] . ')' : 'Yeni Kupon Oluştur' ?></h2>
            </div>
            <div class="card-body">
                <form method="POST" action="coupon_management.php">
                    <?php if ($coupon_data): ?>
                        <input type="hidden" name="coupon_id" value="<?= $coupon_data['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="code" class="form-label">Kupon Kodu</label>
                            <input type="text" id="code" name="code" class="form-control" value="<?= htmlspecialchars($coupon_data['code'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="discount_rate" class="form-label">İndirim Oranı (0.01 - 1.00)</label>
                            <input type="number" step="0.01" id="discount_rate" name="discount_rate" class="form-control" 
                                   value="<?= htmlspecialchars($coupon_data['discount_rate'] ?? '') ?>" required min="0.01" max="1.00">
                        </div>
                        <div class="col-md-4">
                            <label for="usage_limit" class="form-label">Kullanım Limiti</label>
                            <input type="number" id="usage_limit" name="usage_limit" class="form-control" 
                                   value="<?= htmlspecialchars($coupon_data['usage_limit'] ?? '') ?>" required min="1">
                        </div>
                        <div class="col-md-4">
                            <label for="expiry_date" class="form-label">Son Kullanma Tarihi</label>
                            <input type="date" id="expiry_date" name="expiry_date" class="form-control" 
                                   value="<?= htmlspecialchars($coupon_data['expiry_date'] ?? date('Y-m-d')) ?>" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-8 d-flex align-items-end">
                             <div class="d-grid gap-2 d-md-block w-100">
                                <button type="submit" name="save_coupon" class="btn btn-success me-2">
                                    <i class="fas fa-save me-1"></i> <?= $coupon_data ? 'Kuponu Güncelle' : 'Kupon Oluştur' ?>
                                </button>
                                <?php if ($coupon_data): ?>
                                    <a href="coupon_management.php" class="btn btn-outline-secondary">İptal</a>
                                <?php endif; ?>
                             </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        
        <!-- Kupon Listesi -->
        <h2 class="h5 mb-4 text-dark"><i class="fas fa-list me-1"></i> Kayıtlı Kuponlar</h2>
        
        <?php if (empty($coupons)): ?>
            <div class="alert alert-info text-center">Kayıtlı kupon bulunmamaktadır.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($coupons as $coupon): ?>
                    <?php 
                        $is_expired = strtotime($coupon['expiry_date']) < time(); 
                        $is_used_up = $coupon['used_count'] >= $coupon['usage_limit'];
                        $is_general = $coupon['company_id'] === null;

                        $card_class = 'coupon-card';
                        if ($is_expired) {
                            $card_class .= ' expired-card';
                        } elseif ($is_used_up) {
                            $card_class .= ' used-up-card';
                        } elseif ($is_general) {
                            $card_class .= ' general-coupon';
                        }
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card shadow-sm <?= $card_class ?>">
                            <div class="coupon-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($coupon['code']) ?></h6>
                                <span class="badge bg-<?= $is_general ? 'primary' : 'warning text-dark' ?>">
                                    <?= $is_general ? 'GENEL KUPON' : htmlspecialchars($coupon['company_name']) ?>
                                </span>
                            </div>
                            <div class="card-body small">
                                <p class="mb-1"><strong>İndirim:</strong> %<?= number_format($coupon['discount_rate'] * 100, 0) ?></p>
                                <p class="mb-1"><strong>Kullanım:</strong> <?= $coupon['used_count'] ?> / <?= $coupon['usage_limit'] ?> (Kalan: <?= $coupon['usage_limit'] - $coupon['used_count'] ?>)</p>
                                <p class="mb-2"><strong>Son Kullanma:</strong> <?= date('d.m.Y', strtotime($coupon['expiry_date'])) ?> 
                                    <?php if ($is_expired): ?><span class="text-danger fw-bold">(SÜRESİ DOLDU)</span><?php endif; ?>
                                    <?php if ($is_used_up && !$is_expired): ?><span class="text-secondary fw-bold">(TÜKENDİ)</span><?php endif; ?>
                                </p>
                                
                                <div class="d-flex justify-content-end border-top pt-2">
                                    <a href="coupon_management.php?edit_id=<?= $coupon['id'] ?>" class="btn btn-sm btn-outline-secondary me-2" title="Düzenle">
                                        <i class="fas fa-edit"></i>
                                    </a> 
                                    <form method="POST" onsubmit="return confirm('Bu kuponu silmek istediğinizden emin misiniz?');">
                                        <input type="hidden" name="coupon_id" value="<?= $coupon['id'] ?>">
                                        <button type="submit" name="delete_coupon" class="btn btn-sm btn-outline-danger" title="Sil">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
