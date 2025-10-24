<?php
// admin_panel.php

session_start();
require 'db.php'; 

// Yetkilendirme Kontrolü (Sadece 'admin' rolü erişebilir)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); // Yetkisiz erişimi engelle
    exit();
}

$error = '';
$success = $_GET['success'] ?? '';

// Firma Silme İşlemi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_company'])) {
    $company_id_to_delete = (int)$_POST['company_id'];

    try {
        $pdo->beginTransaction();

        // 1. Bu firmaya atanmış Firma Admin var mı? Varsa, company_id'lerini NULL yap
        $sql_update_users = "UPDATE users SET company_id = NULL WHERE company_id = ?";
        $stmt_update_users = $pdo->prepare($sql_update_users);
        $stmt_update_users->execute([$company_id_to_delete]);

        // 2. Bu firmaya ait seferleri ve kuponları sil
        $sql_delete_trips = "DELETE FROM trips WHERE company_id = ?";
        $pdo->prepare($sql_delete_trips)->execute([$company_id_to_delete]);

        $sql_delete_coupons = "DELETE FROM coupons WHERE company_id = ?";
        $pdo->prepare($sql_delete_coupons)->execute([$company_id_to_delete]);

        // 3. Firmayı sil
        $sql_delete_company = "DELETE FROM companies WHERE id = ?";
        $stmt_delete_company = $pdo->prepare($sql_delete_company);
        $stmt_delete_company->execute([$company_id_to_delete]);

        $pdo->commit();
        $success = "Firma başarıyla silindi ve tüm ilişkili veriler (seferler, kuponlar, admin atamaları) güncellendi.";

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Firma silme işlemi başarısız: " . $e->getMessage();
    }
}

// Tüm Firmaları Listeleme
try {
    $sql_companies = "
        SELECT 
            c.*, 
            (SELECT COUNT(id) FROM users WHERE company_id = c.id AND role = 'firma_admin') AS admin_count,
            (SELECT COUNT(id) FROM trips WHERE company_id = c.id) AS trip_count
        FROM companies c
        ORDER BY c.id ASC
    ";
    $companies = $pdo->query($sql_companies)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Veritabanı hatası: Firmalar çekilemedi.";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Admin Paneli</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #e9ecef; }
        .dashboard-header { background-color: #17a2b8; color: white; padding: 25px; border-radius: 8px; margin-bottom: 30px; }
        .action-link { transition: transform 0.2s; }
        .action-link:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .company-item { border-left: 5px solid #17a2b8; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        
        <div class="dashboard-header d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0"><i class="fas fa-tools me-2"></i> Sistem Admin Paneli</h1>
            <a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-home me-1"></i> Ana Sayfa</a>
        </div>
        
        <p class="mb-4 text-muted">Hoş geldiniz, **<?= htmlspecialchars($_SESSION['fullname']) ?>**. (En yetkili rol)</p>

        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        
        <h2 class="h5 mb-3 text-dark"><i class="fas fa-cubes me-1"></i> Hızlı Erişim & Yönetim</h2>
        
        <!-- Hızlı Erişim Butonları -->
        <div class="row mb-5 g-3">
            <div class="col-md-4">
                <a href="company_crud.php?action=add" class="card text-white bg-success action-link text-decoration-none shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-bus me-2"></i> Yeni Firma Oluştur</h5>
                        <p class="card-text small mb-0">Otobüs firmalarını ekleyin/düzenleyin.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="admin_user_crud.php" class="card text-white bg-info action-link text-decoration-none shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-tie me-2"></i> Firma Admin Ata/Ekle</h5>
                        <p class="card-text small mb-0">Yetkili kullanıcıları firmalara atayın.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="coupon_management.php" class="card text-white bg-primary action-link text-decoration-none shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-globe me-2"></i> Genel Kuponları Yönet</h5>
                        <p class="card-text small mb-0">Tüm firmalarda geçerli kuponları yönetin.</p>
                    </div>
                </a>
            </div>
        </div>
        
        <h2 class="h5 mb-4 text-dark"><i class="fas fa-list me-1"></i> Kayıtlı Otobüs Firmaları (<?= count($companies) ?> Adet)</h2>

        <?php if (empty($companies)): ?>
            <div class="alert alert-info text-center">Sistemde kayıtlı firma bulunmamaktadır.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($companies as $company): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card company-item p-3 bg-white">
                            <h3 class="h6 card-title mb-2 fw-bold text-info"><?= htmlspecialchars($company['name']) ?> (ID: <?= $company['id'] ?>)</h3>
                            <p class="mb-1 small">Firma Admini: **<?= $company['admin_count'] ?>** kişi</p>
                            <p class="mb-2 small">Toplam Sefer: **<?= $company['trip_count'] ?>** adet</p>
                            
                            <!-- Aksiyonlar -->
                            <div class="d-flex justify-content-end pt-2 border-top">
                                <a href="company_crud.php?action=edit&id=<?= $company['id'] ?>" class="btn btn-sm btn-outline-info me-2" title="Firma Adını Düzenle"><i class="fas fa-edit"></i></a>
                                <a href="admin_user_crud.php?company_id=<?= $company['id'] ?>" class="btn btn-sm btn-outline-primary me-2" title="Adminleri Yönet/Ata"><i class="fas fa-user-plus"></i></a>
                                <form method="POST" onsubmit="return confirm('UYARI: Bu işlem firmanın tüm seferlerini, kuponlarını ve admin atamalarını SİLECEKTİR. Emin misiniz?');" style="display: inline;">
                                    <input type="hidden" name="company_id" value="<?= $company['id'] ?>">
                                    <button type="submit" name="delete_company" class="btn btn-sm btn-outline-danger" title="Firmayı Sil">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
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
