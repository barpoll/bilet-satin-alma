<?php
// trip_crud.php

session_start();
require 'db.php'; 

// Yetkilendirme Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'firma_admin' || !isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
$action = $_GET['action'] ?? 'add'; // 'add' veya 'edit'
$trip_id = $_GET['id'] ?? null;

$error = '';
$success = '';
$trip_data = [
    'origin' => '',
    'destination' => '',
    'departure_time' => '',
    'arrival_time' => '',
    'price' => '',
    'capacity' => ''
];
$page_title = $action === 'add' ? 'Yeni Sefer Oluştur' : 'Sefer Düzenle';

// Düzenleme (Edit) Modu için Mevcut Verileri Çekme
if ($action === 'edit' && $trip_id) {
    try {
        // Sadece kullanıcının firmasına ait seferi çek
        $sql = "SELECT * FROM trips WHERE id = ? AND company_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$trip_id, $company_id]);
        $existing_trip = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing_trip) {
            header("Location: firma_admin_panel.php?error=Sefer bulunamadı veya yetkiniz yok.");
            exit();
        }
        $trip_data = $existing_trip;
        
    } catch (PDOException $e) {
        $error = "Veri çekme hatası: " . $e->getMessage();
    }
}

// Form Gönderimi (Ekleme veya Düzenleme)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $origin = trim($_POST['origin'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $departure_time = trim($_POST['departure_time'] ?? '');
    $arrival_time = trim($_POST['arrival_time'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $capacity = (int)($_POST['capacity'] ?? 0);
    $company_id_post = $_POST['company_id_post'] ?? $company_id; // Formdan gelmezse session'dan al

    // Basit doğrulama
    if (empty($origin) || empty($destination) || empty($departure_time) || empty($arrival_time) || $price <= 0 || $capacity <= 0) {
        $error = "Lütfen tüm alanları geçerli değerlerle doldurunuz.";
    } else {
        
        try {
            if ($action === 'add') {
                // Ekleme İşlemi (CREATE)
                $sql = "INSERT INTO trips (company_id, origin, destination, departure_time, arrival_time, price, capacity) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$company_id_post, $origin, $destination, $departure_time, $arrival_time, $price, $capacity]);
                $success = "Yeni sefer başarıyla oluşturuldu.";
                
            } elseif ($action === 'edit' && $trip_id) {
                // Düzenleme İşlemi (UPDATE)
                $sql = "UPDATE trips 
                        SET origin = ?, destination = ?, departure_time = ?, arrival_time = ?, price = ?, capacity = ? 
                        WHERE id = ? AND company_id = ?"; // Güvenlik için company_id kontrolü
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$origin, $destination, $departure_time, $arrival_time, $price, $capacity, $trip_id, $company_id_post]);
                $success = "Sefer başarıyla güncellendi.";
            }
            
            // Başarılı işlem sonrası paneli yönlendir
            header("Location: firma_admin_panel.php?success=" . urlencode($success));
            exit();
            
        } catch (PDOException $e) {
            $error = "Veritabanı işlemi başarısız: " . $e->getMessage();
        }
    }
    // Hata durumunda formu doldurmaya devam etmesi için
    $trip_data = compact('origin', 'destination', 'departure_time', 'arrival_time', 'price', 'capacity');
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .form-card { max-width: 700px; margin: 50px auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background-color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <h1 class="h3 mb-4 text-center text-primary"><i class="fas fa-bus-alt me-2"></i> <?= $page_title ?></h1>
            
            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <form method="POST" action="trip_crud.php?action=<?= $action ?><?= $trip_id ? '&id=' . $trip_id : '' ?>">
                
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="company_id_post" value="<?= $company_id ?>">
                    <p class="text-muted small mb-3">Sefer ID: <?= $trip_id ?> | Firma ID: <?= $company_id ?></p>
                <?php endif; ?>
                
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="origin" class="form-label">Kalkış Yeri</label>
                        <input type="text" id="origin" name="origin" class="form-control" value="<?= htmlspecialchars($trip_data['origin']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="destination" class="form-label">Varış Yeri</label>
                        <input type="text" id="destination" name="destination" class="form-control" value="<?= htmlspecialchars($trip_data['destination']) ?>" required>
                    </div>
                </div>
                
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="departure_time" class="form-label">Kalkış Tarih ve Saat</label>
                        <input type="datetime-local" id="departure_time" name="departure_time" class="form-control" 
                               value="<?= date('Y-m-d\TH:i', strtotime($trip_data['departure_time'] ?: 'now')) ?>" required min="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="arrival_time" class="form-label">Varış Tarih ve Saat</label>
                        <input type="datetime-local" id="arrival_time" name="arrival_time" class="form-control" 
                               value="<?= date('Y-m-d\TH:i', strtotime($trip_data['arrival_time'] ?: 'now')) ?>" required min="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="price" class="form-label">Bilet Fiyatı (TL)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" id="price" name="price" class="form-control" value="<?= $trip_data['price'] ?>" required min="0.01">
                            <span class="input-group-text">TL</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="capacity" class="form-label">Koltuk Kapasitesi</label>
                        <input type="number" id="capacity" name="capacity" class="form-control" value="<?= $trip_data['capacity'] ?>" required min="1">
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i> <?= $action === 'add' ? 'Sefer Oluştur' : 'Seferi Güncelle' ?></button>
                    <a href="firma_admin_panel.php" class="btn btn-secondary"><i class="fas fa-times-circle me-2"></i> İptal ve Geri Dön</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
