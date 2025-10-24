<?php
// generate_pdf.php

session_start();
require 'db.php'; 

// Sadece giriş yapmış kullanıcılar kendi biletlerini indirebilir
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$ticket_id = $_GET['ticket_id'] ?? null;
$error = '';

if (!$ticket_id || !is_numeric($ticket_id)) {
    $error = "Hata: Geçersiz bilet numarası.";
}

if (!$error) {
    try {
        // Bilet ve Sefer Bilgilerini Çekme
        $sql_ticket = "
            SELECT 
                t.id AS ticket_id, t.price, t.seat_number, t.status, t.purchase_time, 
                tr.origin, tr.destination, tr.departure_time, tr.arrival_time, 
                c.name AS company_name
            FROM tickets t
            JOIN trips tr ON t.trip_id = tr.id
            JOIN companies c ON tr.company_id = c.id
            WHERE t.id = ? AND t.user_id = ?
        ";
        $stmt_ticket = $pdo->prepare($sql_ticket);
        $stmt_ticket->execute([$ticket_id, $user_id]);
        $ticket = $stmt_ticket->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            $error = "Hata: Bilet bulunamadı veya bu bilet size ait değil.";
        }
        
    } catch (PDOException $e) {
        $error = "Veritabanı hatası: " . $e->getMessage();
    }
}

// Hata varsa sadece hata mesajını göster
if ($error) {
    echo "HATA: $error";
    exit();
}

// --- HTML İÇERİĞİ (Bootstrap ile görselleştirilmiş içerik) ---

// PDF başlıkları kaldırıldı, tarayıcıda görüntülemek için HTML başlıkları kullanılıyor.
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>Bilet Çıktısı #<?= $ticket['ticket_id'] ?></title>
    <!-- Bootstrap'i dahil et -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .ticket-container {
            max-width: 700px;
            margin: 20px auto; /* Margin küçüldü */
            border: 1px solid #dee2e6;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            background-color: white;
            padding: 30px;
        }
        .header { background-color: #007bff; color: white; padding: 15px; text-align: center; margin-bottom: 20px; }
        .details-row { border-bottom: 1px dashed #ccc; padding: 8px 0; }
        /* @media print kuralı ile yazdırırken butonu gizleriz */
        @media print {
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="ticket-container">
        <div class="header">
            <h1 class="h4 mb-0">RESMİ OTOBÜS BİLETİ</h1>
            <small>Bilet ID: <?= $ticket['ticket_id'] ?></small>
        </div>

        <div class="row">
            <div class="col-12 mb-3">
                <h5 class="text-primary"><?= htmlspecialchars($ticket['company_name']) ?></h5>
                <p class="mb-0">Yolcu: <strong><?= htmlspecialchars($_SESSION['fullname']) ?></strong></p>
                <p class="mb-0">Durum: <span class="badge bg-<?= $ticket['status'] === 'satın alındı' ? 'success' : 'danger' ?>"><?= ucfirst($ticket['status']) ?></span></p>
            </div>
        </div>
        
        <div class="details-row row">
            <div class="col-6"><strong>Kalkış / Varış</strong></div>
            <div class="col-6"><?= htmlspecialchars($ticket['origin']) ?> &rarr; <?= htmlspecialchars($ticket['destination']) ?></div>
        </div>
        <div class="details-row row">
            <div class="col-6"><strong>Kalkış Tarih/Saat</strong></div>
            <div class="col-6"><?= date('d.m.Y H:i', strtotime($ticket['departure_time'])) ?></div>
        </div>
        <div class="details-row row">
            <div class="col-6"><strong>Varış Tarih/Saat</strong></div>
            <div class="col-6"><?= date('d.m.Y H:i', strtotime($ticket['arrival_time'])) ?></div>
        </div>
        <div class="details-row row">
            <div class="col-6"><strong>Koltuk Numarası</strong></div>
            <div class="col-6"><strong><?= $ticket['seat_number'] ?></strong></div>
        </div>
        <div class="details-row row">
            <div class="col-6"><strong>Ödenen Tutar</strong></div>
            <div class="col-6 text-success"><strong><?= number_format($ticket['price'], 2) ?> TL</strong></div>
        </div>
        <div class="details-row row">
            <div class="col-6"><strong>Satın Alma Tarihi</strong></div>
            <div class="col-6"><?= date('d.m.Y H:i', strtotime($ticket['purchase_time'])) ?></div>
        </div>

        <div class="text-center mt-4">
            <!-- Bu buton, yazdır penceresini açar. "Hedef" olarak "PDF Olarak Kaydet" seçilmelidir. -->
            <button class="btn btn-success btn-lg btn-print" onclick="window.print()">Yazdır / PDF Kaydet</button>
            <p class="mt-2">İyi yolculuklar dileriz!</p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
exit();
?>
