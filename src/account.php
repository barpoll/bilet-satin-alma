<?php
// account.php

session_start();
require 'db.php'; 

// Sadece 'user' rolündeki kullanıcılar bu sayfaya erişebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = $_GET['success'] ?? '';

// 1. Kullanıcı Bilgilerini ve Krediyi Çekme
try {
    $sql_user = "SELECT fullname, email, balance FROM users WHERE id = ?";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([$user_id]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // 2. Kullanıcının Tüm Biletlerini Çekme
    $sql_tickets = "
        SELECT 
            t.id AS ticket_id, t.price, t.seat_number, t.status, t.purchase_time, 
            tr.origin, tr.destination, tr.departure_time, tr.arrival_time, 
            c.name AS company_name
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        JOIN companies c ON tr.company_id = c.id
        WHERE t.user_id = ?
        ORDER BY tr.departure_time DESC
    ";
    $stmt_tickets = $pdo->prepare($sql_tickets);
    $stmt_tickets->execute([$user_id]);
    $tickets = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Veritabanı hatası: Biletler veya kullanıcı bilgisi çekilemedi.";
}


// Bilet İptal İşlemi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_ticket'])) {
    
    $ticket_id_to_cancel = (int)$_POST['ticket_id'];

    try {
        // İptal edilecek biletin ve seferin bilgilerini çek
        $sql_ticket_info = "
            SELECT 
                t.price, tr.departure_time, t.trip_id, t.seat_number 
            FROM tickets t
            JOIN trips tr ON t.trip_id = tr.id
            WHERE t.id = ? AND t.user_id = ? AND t.status = 'satın alındı'
        ";
        $stmt_info = $pdo->prepare($sql_ticket_info);
        $stmt_info->execute([$ticket_id_to_cancel, $user_id]);
        $ticket_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if (!$ticket_info) {
            $error = "İptal edilebilir aktif bilet bulunamadı.";
        } else {
            $departure_time = new DateTime($ticket_info['departure_time']);
            $now = new DateTime();
            
            // Kalkış saatine son 1 saat öncesine kadar kuralı kontrol et
            $cancellation_deadline = (clone $departure_time)->modify('-1 hour');

            if ($now > $cancellation_deadline) {
                // Son 1 saat kuralına takıldı
                $error = "Kalkış saatine son 1 saatten az kaldığı için bilet iptal edilemez. Kalkış: " . $departure_time->format('H:i');
            } else {
                // İptal başarılı: İşlemi başlat
                $pdo->beginTransaction();

                // 1. Biletin durumunu 'iptal edildi' olarak güncelle
                $sql_update_ticket = "UPDATE tickets SET status = 'iptal edildi' WHERE id = ?";
                $stmt_update_ticket = $pdo->prepare($sql_update_ticket);
                $stmt_update_ticket->execute([$ticket_id_to_cancel]);

                // 2. Bilet ücretini kullanıcının hesabına iade et
                $refund_amount = $ticket_info['price'];
                $sql_update_balance = "UPDATE users SET balance = balance + ? WHERE id = ?";
                $stmt_update_balance = $pdo->prepare($sql_update_balance);
                $stmt_update_balance->execute([$refund_amount, $user_id]);
                
                $pdo->commit();
                $success = "Bilet başarıyla iptal edildi ve **" . number_format($refund_amount, 2) . " TL** hesabınıza iade edildi.";
            
                // Sayfayı yeniden yükle
                header("Location: account.php?success=" . urlencode($success));
                exit();
            }
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Bilet iptal işlemi başarısız oldu. Hata: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesabım / Biletlerim</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .info-card { background-color: white; border-left: 5px solid #007bff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .ticket-card { border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .ticket-header { background-color: #007bff; color: white; padding: 10px 15px; border-radius: 7px 7px 0 0; }
        .ticket-body { padding: 15px; }
        .canceled { border-left: 5px solid #dc3545; background-color: #fdd; opacity: 0.8; }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
             <h1 class="h3 mb-0 text-dark"><i class="fas fa-user-circle me-2"></i> Hesabım / Biletlerim</h1>
             <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Ana Sayfaya Dön</a>
        </div>
        
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

        <div class="row mb-5">
            <!-- Profil ve Kredi Bilgileri -->
            <div class="col-lg-6">
                <div class="info-card">
                    <h2 class="h5 mb-3 text-primary"><i class="fas fa-info-circle me-2"></i> Hesap Bilgileri</h2>
                    <p class="mb-1"><strong>Ad Soyad:</strong> <?= htmlspecialchars($user_info['fullname']) ?></p>
                    <p class="mb-1"><strong>E-posta:</strong> <?= htmlspecialchars($user_info['email']) ?></p>
                </div>
            </div>
            
            <!-- Kredi Durumu -->
            <div class="col-lg-6 mt-3 mt-lg-0">
                 <div class="info-card bg-light">
                    <h2 class="h5 mb-3 text-success"><i class="fas fa-wallet me-2"></i> Mevcut Kredi</h2>
                    <p class="fs-4 fw-bold text-success mb-0"><?= number_format($user_info['balance'], 2) ?> TL</p>
                </div>
            </div>
        </div>

        <h2 class="h4 mb-4 text-dark"><i class="fas fa-ticket-alt me-2"></i> Satın Aldığım Biletler</h2>

        <?php if (empty($tickets)): ?>
            <div class="alert alert-info text-center">Henüz satın alınmış biletiniz bulunmamaktadır.</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($tickets as $ticket): ?>
                    <?php 
                        $is_active = $ticket['status'] === 'satın alındı';
                        $is_cancellable = false;
                        $time_until_departure = '';

                        if ($is_active) {
                            $departure_time = new DateTime($ticket['departure_time']);
                            $now = new DateTime();
                            $cancellation_deadline = (clone $departure_time)->modify('-1 hour');
                            
                            $is_cancellable = $now < $cancellation_deadline;

                            // Kalkışa kalan süreyi hesapla
                            $diff = $departure_time->diff($now);
                            $time_until_departure = $diff->days > 0 ? $diff->format('%a gün, %h saat') : $diff->format('%h saat %i dakika');
                        }
                    ?>
                    
                    <div class="col-md-6">
                        <div class="ticket-card <?= $ticket['status'] === 'iptal edildi' ? 'canceled' : '' ?>">
                            <div class="ticket-header">
                                <span class="fw-bold"><?= htmlspecialchars($ticket['company_name']) ?></span> | Koltuk No: <?= $ticket['seat_number'] ?>
                            </div>
                            
                            <div class="ticket-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($ticket['origin']) ?></span>
                                    <i class="fas fa-arrow-right text-muted mx-2"></i>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($ticket['destination']) ?></span>
                                </div>
                                
                                <p class="mb-1 small"><strong>Kalkış:</strong> <?= date('d.m.Y H:i', strtotime($ticket['departure_time'])) ?></p>
                                <p class="mb-2 small"><strong>Ödenen Tutar:</strong> <?= number_format($ticket['price'], 2) ?> TL</p>
                                
                                <span class="badge bg-<?= $is_active ? 'success' : 'danger' ?> mb-3">
                                    <?= ucfirst($ticket['status']) ?>
                                </span>
                                
                                <div class="d-flex justify-content-between align-items-center border-top pt-2">
                                    <?php if ($is_active): ?>
                                        <?php if ($is_cancellable): ?>
                                            <form method="POST" onsubmit="return confirm('Bileti iptal etmek istediğinizden emin misiniz? (<?= number_format($ticket['price'], 2) ?> TL iade edilecektir)');">
                                                <input type="hidden" name="ticket_id" value="<?= $ticket['ticket_id'] ?>">
                                                <button type="submit" name="cancel_ticket" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-times"></i> İptal Et
                                                </button>
                                            </form>
                                            <small class="text-danger fw-bold">Kalkışa Kalan: <?= $time_until_departure ?></small>
                                        <?php else: ?>
                                            <span class="text-danger small">İptal süresi doldu.</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">İşlem Yapılamaz</span>
                                    <?php endif; ?>
                                    
                                    <a href="generate_pdf.php?ticket_id=<?= $ticket['ticket_id'] ?>" target="_blank" class="btn btn-info btn-sm text-white">
                                        <i class="fas fa-download"></i> PDF İndir
                                    </a>
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
