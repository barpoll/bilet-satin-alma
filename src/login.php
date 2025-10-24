<?php
// login.php

session_start();
require 'db.php'; 

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Lütfen e-posta ve şifrenizi giriniz.";
    } else {
        
        try {
            $stmt = $pdo->prepare("SELECT id, fullname, password, role, company_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC); 
            
            if ($user && password_verify($password, $user['password'])) {
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role']; 
                
                if ($user['role'] === 'firma_admin') {
                    $_SESSION['company_id'] = $user['company_id'];
                }
                
                if ($user['role'] === 'admin') {
                    header("Location: admin_panel.php");
                } elseif ($user['role'] === 'firma_admin') {
                    header("Location: firma_admin_panel.php");
                } else {
                    header("Location: index.php");
                }
                exit();
                
            } else {
                $error = "E-posta veya şifre hatalı.";
            }
            
        } catch (PDOException $e) {
            $error = "Giriş işlemi sırasında bir veritabanı hatası oluştu.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        body { background-color: #f8f9fa; }
        .login-container { max-width: 400px; margin-top: 100px; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); background-color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5">
                <div class="login-container">
                    <h1 class="text-center mb-4 text-primary">Bilet Platformu Giriş</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta:</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Şifre:</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary">Giriş Yap</button>
                        </div>
                    </form>
                    
                    <p class="text-center mt-3">Hesabınız yok mu? <a href="register.php">Kayıt Olun</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
