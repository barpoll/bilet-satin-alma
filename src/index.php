<?php
// index.php

session_start();
require 'db.php'; 

// Kullanıcının rolünü al
$user_role = $_SESSION['role'] ?? 'ziyaretci';
$is_logged_in = isset($_SESSION['user_id']);

// Veritabanından tüm kalkış ve varış yerlerini çekme
try {
    $origins = $pdo->query("SELECT DISTINCT origin FROM trips ORDER BY origin")->fetchAll(PDO::FETCH_COLUMN);
    $destinations = $pdo->query("SELECT DISTINCT destination FROM trips ORDER BY destination")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Veritabanı sorunları için basit hata mesajı
    $error = "Veritabanından şehirler çekilemedi.";
}

// Arama sonuçlarını tutacak değişken
$trips = [];
$search_performed = false; 

// Arama Formu POST edildi mi kontrol et
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $search_performed = true;
    
    $origin = $_POST['origin'] ?? '';
    $destination = $_POST['destination'] ?? '';
    $date = $_POST['date'] ?? date('Y-m-d');
    
    // SQL sorgusunu hazırlama
    $sql = "
        SELECT 
            t.*, 
            c.name AS company_name,
            -- Satılan bilet sayısını hesapla (dolu koltuklar)
            (SELECT COUNT(id) FROM tickets WHERE trip_id = t.id AND status = 'satın alındı') AS sold_seats 
        FROM trips t
        JOIN companies c ON t.company_id = c.id
        WHERE t.origin = :origin 
          AND t.destination = :destination 
          -- Tarihe göre filtreleme (sadece ilgili günü dikkate al)
          AND DATE(t.departure_time) = :date
          -- Sadece gelecekteki seferleri göster
          AND t.departure_time > DATETIME('now', 'localtime') 
        ORDER BY t.departure_time ASC
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':origin' => $origin,
            ':destination' => $destination,
            ':date' => $date
        ]);
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $error = "Sefer arama hatası: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet Satın Alma Platformu</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome Icons (opsiyonel) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .hero-section { background: linear-gradient(135deg, #007bff 0%, #00c6ff 100%); color: white; padding: 40px 0; margin-bottom: 30px; border-radius: 8px; }
        .search-card { background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-top: -80px; }
        .trip-card { border: none; border-left: 5px solid #007bff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .trip-card:hover { transform: translateY(-3px); }
        .trip-details { font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- NAV BAR -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="index.php"><i class="fas fa-bus"></i> Bilet Platformu</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <?php if ($is_logged_in): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle"></i> Merhaba, <?= htmlspecialchars($_SESSION['fullname']) ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <?php if ($user_role === 'user'): ?>
                                        <li><a class="dropdown-item" href="account.php">Hesabım / Biletlerim</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                    <?php endif; ?>
                                    <?php if ($user_role === 'firma_admin'): ?>
                                        <li><a class="dropdown-item" href="firma_admin_panel.php">Firma Panelim</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                    <?php endif; ?>
                                    <?php if ($user_role === 'admin'): ?>
                                        <li><a class="dropdown-item" href="admin_panel.php">Admin Panelim</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item text-danger" href="logout.php">Çıkış Yap</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="login.php">Giriş Yap</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="register.php">Kayıt Ol</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- HERO VE ARAMA -->
        <div class="hero-section text-center">
            <h1 class="display-4">Hayalindeki Seyahate Başla</h1>
            <p class="lead">Geniş güzergah ağımızda en uygun otobüs biletini bul.</p>
        </div>

        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <!-- ARAMA FORMU -->
                    <div class="search-card">
                        <form method="POST" action="index.php" class="row g-3">
                            <input type="hidden" name="search" value="1">
                            
                            <div class="col-md-4">
                                <label for="origin" class="form-label">Kalkış Yeri</label>
                                <select id="origin" name="origin" class="form-select" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($origins as $o): ?>
                                        <option value="<?= htmlspecialchars($o) ?>"><?= htmlspecialchars($o) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="destination" class="form-label">Varış Yeri</label>
                                <select id="destination" name="destination" class="form-select" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($destinations as $d): ?>
                                        <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date" class="form-label">Tarih</label>
                                <input type="date" id="date" name="date" class="form-control" required value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
                            </div>
                            
                            <div class="col-md-1 d-grid align-self-end">
                                <button type="submit" class="btn btn-primary">Ara</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- SEFER SONUÇLARI -->
            <div class="row mt-5">
                <div class="col-12">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <?php if ($search_performed): ?>
                        <h2 class="mb-4">Sefer Sonuçları</h2>
                        
                        <?php if (empty($trips)): ?>
                            <div class="alert alert-warning text-center" role="alert">
                                Seçtiğiniz kriterlere uygun sefer bulunamamıştır.
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($trips as $trip): ?>
                                    <?php 
                                    $available_seats = $trip['capacity'] - $trip['sold_seats'];
                                    $detail_link = 'trip_details.php?trip_id=' . $trip['id'];
                                    $is_available = $available_seats > 0;
                                    ?>
                                    
                                    <div class="col-md-12">
                                        <div class="card trip-card p-3 <?= $is_available ? 'bg-white' : 'bg-light text-muted' ?>">
                                            <div class="row align-items-center">
                                                
                                                <!-- Firma ve Koltuk -->
                                                <div class="col-lg-3 col-md-12 mb-2 mb-lg-0">
                                                    <h5 class="card-title text-dark fw-bold"><?= htmlspecialchars($trip['company_name']) ?></h5>
                                                    <p class="mb-0 trip-details">
                                                        <i class="fas fa-couch"></i> Boş Koltuk: <span class="fw-bold <?= $is_available ? 'text-success' : 'text-danger' ?>"><?= $available_seats ?></span> / <?= $trip['capacity'] ?>
                                                    </p>
                                                </div>
                                                
                                                <!-- Kalkış ve Varış Saatleri -->
                                                <div class="col-lg-5 col-md-12 mb-2 mb-lg-0">
                                                    <div class="d-flex align-items-center">
                                                        <div class="text-center me-3">
                                                            <div class="fw-bold text-dark"><?= date('H:i', strtotime($trip['departure_time'])) ?></div>
                                                            <div class="trip-details"><?= htmlspecialchars($trip['origin']) ?></div>
                                                        </div>
                                                        <i class="fas fa-long-arrow-alt-right text-primary mx-3"></i>
                                                        <div class="text-center">
                                                            <div class="fw-bold text-dark"><?= date('H:i', strtotime($trip['arrival_time'])) ?></div>
                                                            <div class="trip-details"><?= htmlspecialchars($trip['destination']) ?></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Fiyat ve Aksiyon -->
                                                <div class="col-lg-4 col-md-12 d-flex justify-content-between align-items-center">
                                                    <h4 class="text-primary fw-bold mb-0 me-3"><?= number_format($trip['price'], 2) ?> TL</h4>
                                                    
                                                    <?php if ($is_available): ?>
                                                        <!-- Bilet satın alma yetkilendirmesi -->
                                                        <?php if ($user_role === 'user'): ?>
                                                            <a href="buy_ticket.php?trip_id=<?= $trip['id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-ticket-alt"></i> Bilet Al</a>
                                                        <?php else: ?>
                                                            <button onclick="alert('Lütfen Giriş Yapın'); window.location.href='login.php';" 
                                                                    class="btn btn-primary btn-sm"
                                                                    <?= ($user_role === 'ziyaretci') ? '' : 'disabled' ?>>
                                                                Bilet Satın Al
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <button class="btn btn-secondary btn-sm" disabled><i class="fas fa-times-circle"></i> Dolu</button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
