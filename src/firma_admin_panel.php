<?php
// firma_admin_panel.php

session_start();
require 'db.php'; 

// 1. Yetkilendirme Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'firma_admin' || !isset($_SESSION['company_id'])) {
    header("Location: login.php"); // Yetkisiz erişimi engelle
    exit();
}

$company_id = $_SESSION['company_id'];
$error = '';
$success = $_GET['success'] ?? '';

// Sefer Silme İşlemi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_trip'])) {
    $trip_id_to_delete = (int)$_POST['trip_id'];

    try {
        // Silme işleminden önce: Bu seferin kullanıcının firmasına ait olduğunu kontrol et
        $sql_check = "SELECT company_id FROM trips WHERE id = ?";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$trip_id_to_delete]);
        
        if ($stmt_check->fetchColumn() == $company_id) {
            
            // Satın alınmış bilet var mı kontrol et (Daha güvenli bir senaryo için)
            $sql_tickets = "SELECT COUNT(id) FROM tickets WHERE trip_id = ? AND status = 'satın alındı'";
            $stmt_tickets = $pdo->prepare($sql_tickets);
            $stmt_tickets->execute([$trip_id_to_delete]);

            if ($stmt_tickets->fetchColumn() > 0) {
                 $error = "Bu sefere bilet satıldığı için silme işlemi yapılamaz. Önce tüm biletlerin iptal edilmesi gerekir.";
            } else {
                // Silebiliriz
                $sql_delete = "DELETE FROM trips WHERE id = ?";
                $stmt_delete = $pdo->prepare($sql_delete);
                $stmt_delete->execute([$trip_id_to_delete]);
                $success = "Sefer başarıyla silindi.";
            }

        } else {
            $error = "Yetkisiz işlem: Bu sefer firmanıza ait değil.";
        }
    } catch (PDOException $e) {
        $error = "Silme işlemi başarısız oldu: " . $e->getMessage();
    }
}

// Firma Admin'in Seferlerini Listeleme
try {
    // Sadece oturumdaki kullanıcının firmasına ait seferleri çek
    $sql_trips = "
        SELECT 
            t.*, 
            (SELECT COUNT(id) FROM tickets WHERE trip_id = t.id AND status = 'satın alındı') AS sold_seats 
        FROM trips t
        WHERE t.company_id = ?
        ORDER BY t.departure_time DESC
    ";
    $stmt_trips = $pdo->prepare($sql_trips);
    $stmt_trips->execute([$company_id]);
    $trips = $stmt_trips->fetchAll(PDO::FETCH_ASSOC);

    // Firmanın adını çek
    $sql_company_name = "SELECT name FROM companies WHERE id = ?";
    $stmt_company = $pdo->prepare($sql_company_name);
    $stmt_company->execute([$company_id]);
    $company_name = $stmt_company->fetchColumn();

} catch (PDOException $e) {
    $error = "Veritabanı hatası: Seferler çekilemedi.";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Yönetim Paneli - <?= htmlspecialchars($company_name) ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .dashboard-header { background-color: #343a40; color: white; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
        .action-card { transition: transform 0.2s; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .trip-item { border-left: 5px solid #007bff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        
        <div class="dashboard-header d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0"><i class="fas fa-building me-2"></i> <?= htmlspecialchars($company_name) ?> Yönetim Paneli</h1>
            <a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-home me-1"></i> Ana Sayfa</a>
        </div>
        
        <p class="mb-4">Hoş geldiniz, **<?= htmlspecialchars($_SESSION['fullname']) ?>**.</p>

        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        
        <h2 class="h5 mb-3 text-dark"><i class="fas fa-cog me-1"></i> Hızlı Erişim</h2>
        
        <!-- Hızlı Erişim Butonları -->
        <div class="row mb-5">
            <div class="col-md-6 col-lg-4 mb-3">
                <a href="trip_crud.php?action=add" class="card text-white bg-primary action-card text-decoration-none">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-plus-circle me-2"></i> Yeni Sefer Ekle</h5>
                        <p class="card-text">Yeni bir otobüs güzergahı oluşturun.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-4 mb-3">
                <a href="coupon_management.php" class="card text-white bg-info action-card text-decoration-none">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-tags me-2"></i> Kuponları Yönet</h5>
                        <p class="card-text">Firma özelinde indirim kuponları oluşturun/düzenleyin.</p>
                    </div>
                </a>
            </div>
        </div>
        
        <h2 class="h5 mb-4 text-dark"><i class="fas fa-route me-1"></i> Kayıtlı Seferler (<?= count($trips) ?> Adet)</h2>

        <?php if (empty($trips)): ?>
            <div class="alert alert-info text-center">Henüz kayıtlı bir seferiniz bulunmamaktadır.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($trips as $trip): ?>
                    <div class="col-lg-6">
                        <div class="card trip-item p-3 bg-white">
                            <h4 class="h6 card-title mb-1 fw-bold"><?= htmlspecialchars($trip['origin']) ?> <i class="fas fa-arrow-right mx-2"></i> <?= htmlspecialchars($trip['destination']) ?></h4>
                            <p class="mb-1 small">Kalkış: **<?= date('d.m.Y H:i', strtotime($trip['departure_time'])) ?>**</p>
                            <p class="mb-2 small">Fiyat: **<?= number_format($trip['price'], 2) ?> TL** | Kapasite: **<?= $trip['capacity'] ?>**</p>
                            <p class="mb-2 small fw-bold text-success">Satılan Koltuk: **<?= $trip['sold_seats'] ?>** / Boş: **<?= $trip['capacity'] - $trip['sold_seats'] ?>**</p>
                            
                            <!-- Aksiyonlar -->
                            <div class="d-flex justify-content-between pt-2 border-top">
                                <a href="trip_crud.php?action=edit&id=<?= $trip['id'] ?>" class="btn btn-sm btn-outline-primary me-2"><i class="fas fa-edit me-1"></i> Düzenle</a> 
                                <form method="POST" onsubmit="return confirm('Bu seferi silmek istediğinizden emin misiniz?');" style="display: inline;">
                                    <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                                    <button type="submit" name="delete_trip" class="btn btn-sm btn-outline-danger" <?= $trip['sold_seats'] > 0 ? 'disabled' : '' ?> title="<?= $trip['sold_seats'] > 0 ? 'Bilet satıldığı için silinemez' : 'Sil' ?>">
                                        <i class="fas fa-trash me-1"></i> Sil
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
