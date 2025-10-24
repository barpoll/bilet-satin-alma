<?php
// buy_ticket.php

session_start();
require 'db.php'; 

// Sadece 'user' rolündeki kullanıcılar bilet satın alabilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php?alert=Lütfen bilet satın almak için giriş yapın.");
    exit();
}
// Kupon ve Bilet Satın Alma Mantığı (PHP)
$user_id = $_SESSION['user_id'];
$trip_id = $_GET['trip_id'] ?? null;

$error = '';
$success = '';
$applied_coupon = null; 
$final_price = 0; 
$initial_price = 0; 
$user_balance = 0; 

if (!$trip_id || !is_numeric($trip_id)) {
    $error = "Hata: Geçersiz sefer ID'si.";
}

// Gerekli verilerin çekilmesi ve ilk fiyat hesaplaması
try {
    // 1. Sefer Detaylarını Çekme
    $sql_trip = "SELECT t.*, c.name as company_name FROM trips t JOIN companies c ON t.company_id = c.id WHERE t.id = ?";
    $stmt_trip = $pdo->prepare($sql_trip);
    $stmt_trip->execute([$trip_id]);
    $trip = $stmt_trip->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        $error = "Sefer bulunamadı.";
    } else {
        $initial_price = $trip['price'];
        $final_price = $initial_price;
        $capacity = $trip['capacity'];

        // 2. Dolu Koltukları Çekme
        $sql_sold_seats = "SELECT seat_number FROM tickets WHERE trip_id = ? AND status = 'satın alındı'";
        $stmt_seats = $pdo->prepare($sql_sold_seats);
        $stmt_seats->execute([$trip_id]);
        $sold_seats = $stmt_seats->fetchAll(PDO::FETCH_COLUMN, 0);
        $sold_seats_map = array_flip($sold_seats);

        // 3. Kullanıcının Mevcut Kredisini Çekme
        $sql_balance = "SELECT balance FROM users WHERE id = ?";
        $stmt_balance = $pdo->prepare($sql_balance);
        $stmt_balance->execute([$user_id]);
        $user_balance = $stmt_balance->fetchColumn();
    }
} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}


// --- POST İŞLEMLERİ ---

// Kupon Uygulama Mantığı (GÖREV 7: İndirim Uygulanmalı)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_coupon'])) {
    $coupon_code = strtoupper(trim($_POST['coupon_code'] ?? ''));

    if (!empty($coupon_code) && $trip) {
        try {
            $sql_coupon = "
                SELECT id, discount_rate, usage_limit, used_count 
                FROM coupons 
                WHERE code = ? 
                AND expiry_date >= DATE('now') 
                AND usage_limit > used_count 
                AND (company_id IS NULL OR company_id = ?) 
            ";
            // Kontrol: Ya genel kupon (company_id IS NULL) ya da sefere ait firmaya ait kupon olmalı
            $stmt_coupon = $pdo->prepare($sql_coupon);
            $stmt_coupon->execute([$coupon_code, $trip['company_id']]);
            $applied_coupon_data = $stmt_coupon->fetch(PDO::FETCH_ASSOC);

            if ($applied_coupon_data) {
                // Kupon başarılı: İndirimi uygula
                $discount_amount = $initial_price * $applied_coupon_data['discount_rate'];
                $final_price = $initial_price - $discount_amount;
                $_SESSION['applied_coupon'] = ['id' => $applied_coupon_data['id'], 'rate' => $applied_coupon_data['discount_rate']];
                $success = "Kupon başarıyla uygulandı! Yeni fiyat: " . number_format($final_price, 2) . " TL";
            } else {
                $error = "Geçersiz, süresi dolmuş veya kullanım limitine ulaşmış kupon kodu.";
                unset($_SESSION['applied_coupon']);
            }
        } catch (PDOException $e) {
            $error = "Kupon doğrulama hatası: " . $e->getMessage();
            unset($_SESSION['applied_coupon']);
        }
    }
} 
// Kupon Kaldırma Mantığı
elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_coupon'])) {
    unset($_SESSION['applied_coupon']);
    $success = "Kupon başarıyla kaldırıldı.";
}


// Bilet Satın Alma İşlemi (GÖREV 7: Kredi ile bilet alımı)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['buy_ticket']) && isset($_POST['seat_number'])) {
    
    $seat_number = (int)$_POST['seat_number'];

    // Kupon oturumda tutuluyorsa (fiyatı tekrar hesapla)
    if (isset($_SESSION['applied_coupon'])) {
        $discount_amount = $initial_price * $_SESSION['applied_coupon']['rate'];
        $final_price = $initial_price - $discount_amount;
    } else {
        $final_price = $initial_price;
    }

    if ($seat_number < 1 || $seat_number > $capacity) {
        $error = "Geçersiz koltuk numarası.";
    } elseif (isset($sold_seats_map[$seat_number])) {
        $error = "Bu koltuk zaten dolu!";
    } elseif ($user_balance < $final_price) {
        $error = "Hesabınızda yeterli sanal kredi bulunmamaktadır. Kredi: " . number_format($user_balance, 2) . " TL";
    } else {
        // Tüm kontroller başarılı: İşlemi başlat
        try {
            $pdo->beginTransaction();

            // 1. Kullanıcının bakiyesini güncelle
            $new_balance = $user_balance - $final_price;
            $sql_update_balance = "UPDATE users SET balance = ? WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update_balance);
            $stmt_update->execute([$new_balance, $user_id]);

            // 2. Bilet kaydını oluştur
            $sql_insert_ticket = "INSERT INTO tickets (user_id, trip_id, seat_number, price, status) VALUES (?, ?, ?, ?, 'satın alındı')";
            $stmt_insert = $pdo->prepare($sql_insert_ticket);
            $stmt_insert->execute([$user_id, $trip_id, $seat_number, $final_price]);
            
            // 3. Kupon kullanıldıysa, kullanım sayısını artır
            if (isset($_SESSION['applied_coupon'])) {
                $sql_update_coupon = "UPDATE coupons SET used_count = used_count + 1 WHERE id = ?";
                $stmt_coupon_update = $pdo->prepare($sql_update_coupon);
                $stmt_coupon_update->execute([$_SESSION['applied_coupon']['id']]);
                unset($_SESSION['applied_coupon']); // Kuponu oturumdan kaldır
            }

            $pdo->commit();
            $success = "Biletiniz başarıyla satın alındı! Yeni Krediniz: " . number_format($new_balance, 2) . " TL";
            
            // Satın alma sonrası sayfanın yenilenmesi için yönlendirme (URL'deki POST verisini temizler)
            header("Location: buy_ticket.php?trip_id=" . $trip_id . "&success=" . urlencode($success));
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Bilet satın alma işlemi başarısız oldu. Hata: " . $e->getMessage();
        }
    }
}
// Kupon oturumda tutuluyorsa (sayfa yenilendiğinde fiyatı tekrar hesaplamak için)
elseif (isset($_SESSION['applied_coupon']) && $initial_price > 0) {
    $applied_coupon = $_SESSION['applied_coupon'];
    $discount_amount = $initial_price * $applied_coupon['rate'];
    $final_price = $initial_price - $discount_amount;
}
// Sayfa yönlendirmesinden gelen success/error mesajları
if (isset($_GET['success'])) { $success = htmlspecialchars($_GET['success']); }
if (isset($_GET['error'])) { $error = htmlspecialchars($_GET['error']); }
// --- HTML ÇIKTISI ---
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <title>Bilet Satın Alma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .bus-layout {
            /* Otobüs dış çerçevesi */
            max-width: 700px;
            margin: 20px auto;
            padding: 20px;
            border: 2px solid #333;
            border-radius: 15px;
            background-color: #e9ecef;
            position: relative;
        }
        .seat-map-container {
            display: grid;
            /* 4 Sütunlu düzen: 2 Koltuk | Koridor (0.5fr) | 1 Koltuk */
            grid-template-columns: 1fr 1fr 0.5fr 1fr; 
            gap: 10px;
            padding-top: 10px;
        }
        .seat-map-rear {
            display: grid;
            /* Arka sıra 4 koltuk yan yana */
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 20px;
            padding: 10px 0;
            border-top: 1px dashed #ccc;
        }

        .seat-label {
            padding: 8px 0;
            text-align: center;
            border-radius: 8px; /* Daha oval koltuk görünümü */
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s;
            display: block; 
            width: 100%; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.2); /* Koltuklara hafif gölge */
        }
        .seat-available {
            background-color: #28a745; /* Yeşil */
            color: white;
            border: 1px solid #1e7e34;
        }
        .seat-sold {
            background-color: #dc3545; /* Kırmızı */
            color: white;
            opacity: 0.7;
            cursor: not-allowed;
        }
        .seat-map-container input[type="radio"] {
            display: none; 
        }
        .seat-map-container input[type="radio"]:checked + .seat-label {
            background-color: #ffc107; /* Sarı */
            color: black;
            border: 2px solid #da9f06;
        }
        /* Ön Kısım ve Direksiyon */
        .front-section {
            display: grid;
            grid-template-columns: 1fr 1fr 0.5fr 1fr; 
            gap: 10px;
            height: 80px;
            align-items: center;
            margin-bottom: 20px;
        }
        .driver-seat {
            grid-column: 1;
            text-align: center;
            font-size: 24px;
            color: #333;
            /* Direksiyon simidi simülasyonu */
        }
        .entrance-area {
            grid-column: 3 / span 2; /* Koridor ve tekli koltuk tarafını kapla */
            text-align: center;
            font-size: 14px;
            color: #6c757d;
            border-left: 1px dashed #aaa;
            padding-left: 15px;
        }
        .driver-seat-filler {
            grid-column: 2; /* Şoförün yanındaki boş koltuğu kapat */
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3">Bilet Satın Alma</h1>
            <a href="index.php" class="btn btn-secondary btn-sm">Geri Dön</a>
        </div>
        
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

        <?php if ($trip): ?>
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h2 class="h5 mb-0">Sefer Bilgileri - <?= htmlspecialchars($trip['company_name']) ?></h2>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Güzergah:</strong> <?= htmlspecialchars($trip['origin']) ?> &rarr; <?= htmlspecialchars($trip['destination']) ?></p>
                            <p class="mb-1"><strong>Kalkış:</strong> <?= date('d.m.Y H:i', strtotime($trip['departure_time'])) ?></p>
                            <p class="mb-1"><strong>Mevcut Kredi:</strong> <span class="badge bg-info text-dark"><?= number_format($user_balance, 2) ?> TL</span></p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="mb-1"><strong>Kapasite:</strong> <?= $capacity ?></p>
                            <p class="mb-1"><strong>Standart Fiyat:</strong> <?= number_format($initial_price, 2) ?> TL</p>
                            
                            <!-- Kupon Uygulama Sonucu Gösterimi -->
                            <?php if ($initial_price !== $final_price): ?>
                                <p class="mb-1 text-danger"><strong>İndirim:</strong> %<?= number_format(($initial_price - $final_price) / $initial_price * 100, 0) ?></p>
                                <h4 class="text-success"><strong>Nihai Tutar:</strong> <?= number_format($final_price, 2) ?> TL</h4>
                            <?php else: ?>
                                <h4 class="text-success"><strong>Ödenecek Tutar:</strong> <?= number_format($final_price, 2) ?> TL</h4>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h3 class="mt-4 mb-3">Koltuk Seçimi (<?= $capacity ?> Koltuk)</h3>
                    
                    <!-- Koltuk Seçim Formu -->
                    <form method="POST" action="buy_ticket.php?trip_id=<?= $trip_id ?>" class="mb-4">
                        <input type="hidden" name="buy_ticket" value="1">
                        
                        <!-- YENİ OTOBÜS GÖRÜNÜMÜ -->
                        <div class="bus-layout">
                            
                            <!-- ÖN KISIM: Direksiyon ve Kapı Alanı -->
                            <div class="front-section">
                                <div class="driver-seat">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="currentColor" class="bi bi-person-circle" viewBox="0 0 16 16">
                                      <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/>
                                      <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1"/>
                                    </svg>
                                    <small class="d-block">Şoför</small>
                                </div>
                                <div class="driver-seat-filler"></div>
                                <div class="entrance-area">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-door-open" viewBox="0 0 16 16">
                                      <path d="M8.5 10c-.276 0-.5-.448-.5-1s.224-1 .5-1 .5.448.5 1-.224 1-.5 1"/>
                                      <path d="M10.828.122A.5.5 0 0 1 11 .5V.5h2a.5.5 0 0 1 .5.5v15a.5.5 0 0 1-.5.5h-2.5a.5.5 0 0 1 0-1h1.5V1H11zM2.5 1h1.5v15h-1.5a.5.5 0 0 1-.5-.5V1h1z"/>
                                    </svg>
                                    <small class="d-block">Giriş / Kapı</small>
                                </div>
                            </div>

                            <!-- KOLTUK HARİTASI (2+1 Düzeni) -->
                            <div class="seat-map-container">
                                <?php 
                                $i = 1; // Koltuk numarası başlangıcı
                                $seats_per_row = 3; // Bir sıradaki koltuk sayısı (2+1)
                                $seat_counter = 0;

                                while ($i <= $capacity) {
                                    $is_sold = isset($sold_seats_map[$i]);
                                    $status_class = $is_sold ? 'seat-sold' : 'seat-available';
                                    
                                    // 2+1 düzeni: Sol 2, Koridor, Sağ 1
                                    
                                    // Koltukları Yerleştirme
                                    echo '<input type="radio" id="seat-' . $i . '" name="seat_number" value="' . $i . '" ' . ($is_sold ? 'disabled' : '') . ' required>';
                                    echo '<label for="seat-' . $i . '" class="seat-label ' . $status_class . '">' . $i . '</label>';
                                    
                                    $i++;
                                    $seat_counter++;

                                    // Koridor Boşluğu Ekleme (2. koltuktan sonra, yani her 3 koltuktan sonra)
                                    if ($seat_counter % 3 === 2) {
                                        echo '<div class="corridor-filler"></div>';
                                    }
                                    
                                    // Yeni Sıra Başlatma (Her 3 koltuk ve 1 koridordan sonra)
                                    if ($seat_counter % 4 === 0 && $seat_counter > 0) {
                                        // 4. sütunda zaten bir koltuk var (tekli) ve döngü 4. koltuğu yerleştirdikten sonra $i'yi artırdı.
                                        // Bir sonraki sıra otomatik başlar, ancak biz burada tekli koltuktan sonra ekstra bir boşluk bırakmalıyız.
                                    }
                                }
                                ?>
                                
                            </div>
                            
                            <!-- ARTA KALAN KOLTUKLAR (Bu mantık basitleştirilmiş ve hatalıydı, yukarıdaki while döngüsü ile çözüldü) -->
                            <?php 
                            // Tüm koltuklar yukarıdaki while döngüsünde (1'den capacity'ye kadar) yerleştirildiği için bu kısım gereksiz.
                            // Kalan koltukların yerleşimi CSS Grid tarafından otomatik yönetilir.
                            ?>

                        </div>
                        <!-- SON OTOBÜS GÖRÜNÜMÜ -->
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-warning btn-lg" <?= ($capacity === count($sold_seats)) ? 'disabled' : '' ?>>
                                <?= number_format($final_price, 2) ?> TL'ye Bilet Satın Al
                            </button>
                        </div>
                    </form>
                    
                    <!-- Kupon Uygulama Formu EKLENDİ -->
                    <div class="card p-3 bg-light">
                        <h5 class="mb-3">İndirim Kuponu Uygula</h5>
                        <form method="POST" action="buy_ticket.php?trip_id=<?= $trip_id ?>">
                            <div class="input-group">
                                <input type="text" name="coupon_code" class="form-control" placeholder="Kupon Kodunu Girin" required>
                                <button type="submit" name="apply_coupon" class="btn btn-info text-white">Uygula</button>
                                <?php if (isset($_SESSION['applied_coupon'])): ?>
                                    <!-- Kupon kaldırma butonu sadece oturumda kupon varsa görünür -->
                                    <button type="submit" name="remove_coupon" class="btn btn-outline-danger">Kaldır</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <!-- Kupon Uygulama Formu SONU -->

                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
