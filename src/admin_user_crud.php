<?php
// admin_user_crud.php

session_start();
require 'db.php'; 

// Yetkilendirme Kontrolü (Sadece 'admin' rolü erişebilir)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$error = '';
$success = $_GET['success'] ?? '';

// Mevcut Firmaları Çekme (Atama için SELECT kutusu doldurmak amacıyla)
try {
    $companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Firmalar listelenirken hata oluştu: " . $e->getMessage();
}

// Form Gönderimi (Firma Admin Oluşturma ve Atama)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_admin'])) {
    
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $company_id_to_assign = (int)($_POST['company_id'] ?? 0);
    $role = 'firma_admin'; // Oluşturulan rol sabit

    // Basit doğrulama
    if (empty($fullname) || empty($email) || empty($password) || $company_id_to_assign <= 0) {
        $error = "Lütfen tüm alanları doldurunuz ve bir firma seçiniz.";
    } else {
        
        try {
            // 1. E-posta Benzersizlik Kontrolü
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt_check->execute([$email]);
            
            if ($stmt_check->fetch()) {
                $error = "Bu e-posta adresi zaten sistemde kayıtlıdır.";
            } else {
                
                // 2. Şifreyi Hashleme
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $balance = 0.00; // Firma Admin'in kredisi olmaz

                // 3. Veritabanına Kayıt (CREATE)
                $sql = "INSERT INTO users (fullname, email, password, role, company_id, balance) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$fullname, $email, $hashed_password, $role, $company_id_to_assign, $balance]);
                
                $success = "Yeni Firma Admin ('" . htmlspecialchars($fullname) . "') başarıyla oluşturuldu ve firmaya atandı.";
                
                // Başarılı işlem sonrası paneli yönlendir
                header("Location: admin_panel.php?success=" . urlencode($success));
                exit();
            }
        } catch (PDOException $e) {
            $error = "Veritabanı işlemi başarısız: " . $e->getMessage();
        }
    }
}

// Mevcut Firma Adminlerini Listeleme
try {
    // Sadece 'firma_admin' rolündeki kullanıcıları firmalarıyla birlikte çek
    $sql_admin_users = "
        SELECT 
            u.id, u.fullname, u.email, c.name AS company_name, c.id AS company_id
        FROM users u
        JOIN companies c ON u.company_id = c.id
        WHERE u.role = 'firma_admin'
        ORDER BY c.name, u.fullname
    ";
    $admin_users = $pdo->query($sql_admin_users)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Mevcut firma adminleri listelenirken hata oluştu.";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Admin Ekle/Ata</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f0f3f5; }
        .form-card, .list-card { max-width: 900px; margin: 30px auto; }
        .form-card { border-left: 5px solid #007bff; }
        .list-card { border-top: 5px solid #28a745; }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
            <h1 class="h3 mb-0 text-primary"><i class="fas fa-user-plus me-2"></i> Firma Admin Ekleme ve Atama</h1>
            <a href="admin_panel.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Admin Paneline Dön</a>
        </div>
        
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

        <!-- Yeni Firma Admin Oluşturma Formu -->
        <div class="card shadow-sm mb-5 form-card">
            <div class="card-header bg-primary text-white">
                <h2 class="h5 mb-0"><i class="fas fa-user-plus me-2"></i> Yeni Firma Admin Oluştur ve Ata</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="admin_user_crud.php">
                    <input type="hidden" name="create_admin" value="1">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="fullname" class="form-label">Ad Soyad:</label>
                            <input type="text" id="fullname" name="fullname" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">E-posta:</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label for="password" class="form-label">Geçici Şifre:</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label for="company_id" class="form-label">Atanacak Firma:</label>
                            <select id="company_id" name="company_id" class="form-select" required>
                                <option value="">-- Seçiniz --</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i> Oluştur ve Ata</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Mevcut Firma Adminleri Listesi -->
        <div class="card shadow-sm list-card">
            <div class="card-header bg-success text-white">
                <h2 class="h5 mb-0"><i class="fas fa-users-cog me-2"></i> Mevcut Firma Adminleri</h2>
            </div>
            <div class="card-body">
                <?php if (empty($admin_users)): ?>
                    <div class="alert alert-info text-center mb-0">Sistemde atanmış Firma Admin bulunmamaktadır.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($admin_users as $user): ?>
                            <div class="col-md-6">
                                <div class="p-3 border rounded d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1 fw-bold"><?= htmlspecialchars($user['fullname']) ?></h6>
                                        <p class="mb-0 small text-muted"><?= htmlspecialchars($user['email']) ?></p>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success me-2"><?= htmlspecialchars($user['company_name']) ?></span>
                                        <span class="text-muted small">(ID: <?= $user['id'] ?>)</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
