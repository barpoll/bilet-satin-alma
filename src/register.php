<?php
// register.php

// Oturumu başlat
session_start(); 

// 1. Veritabanı bağlantısını dahil et
require 'db.php';

$error = '';
$success = '';

// Form POST edildi mi kontrol et
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Güvenlik için trim ve filtreleme yap
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // 2. Veri Kontrolü
    if (empty($fullname) || empty($email) || empty($password)) {
        $error = "Lütfen tüm alanları doldurunuz.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Geçerli bir e-posta adresi giriniz.";
    } else {
        
        try {
            // 3. E-posta Benzersizlik Kontrolü
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = "Bu e-posta adresi zaten sistemde kayıtlıdır.";
            } else {
                
                // 4. Şifreyi Hashleme
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // 5. Veritabanına Kayıt
                $role = 'user'; // Varsayılan rol (Yolcu)
                $balance = 10000.00; // YENİ: 10.000 sanal kredi
                
                $sql = "INSERT INTO users (fullname, email, password, role, balance) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                
                // company_id NULL olacağı için, sorguda sadece gerekli alanlar var
                if ($stmt->execute([$fullname, $email, $hashed_password, $role, $balance])) {
                    $success = "Başarıyla kayıt oldunuz! Lütfen giriş yapın.";
                    // Header yönlendirmesi buraya eklenebilir
                } else {
                    $error = "Kayıt işlemi sırasında bir hata oluştu.";
                }
            }
        } catch (PDOException $e) {
            $error = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="card p-4 shadow" style="max-width: 400px; width: 100%;">
            <h1 class="h3 card-title text-center mb-4">Yeni Kullanıcı Kaydı</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="mb-3">
                    <label for="fullname" class="form-label">Tam Adınız:</label>
                    <input type="text" id="fullname" name="fullname" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">E-posta:</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Şifre:</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Kayıt Ol</button>
            </form>
            
            <p class="text-center mt-3">Zaten hesabınız var mı? <a href="login.php">Giriş Yapın</a></p>
        </div>
    </div>
</body>
</html>
