<?php
// company_crud.php

session_start();
require 'db.php'; 

// Yetkilendirme Kontrolü (Sadece 'admin' rolü erişebilir)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$action = $_GET['action'] ?? 'add'; // 'add' (Ekleme) veya 'edit' (Düzenleme)
$company_id = $_GET['id'] ?? null;

$error = '';
$success = '';
$company_name = '';
$page_title = $action === 'add' ? 'Yeni Firma Oluştur' : 'Firma Düzenle';

// Düzenleme (Edit) Modu için Mevcut Verileri Çekme
if ($action === 'edit' && $company_id) {
    try {
        $sql = "SELECT name FROM companies WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$company_id]);
        $existing_company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing_company) {
            header("Location: admin_panel.php?error=Firma bulunamadı.");
            exit();
        }
        $company_name = $existing_company['name'];
        
    } catch (PDOException $e) {
        $error = "Veri çekme hatası: " . $e->getMessage();
    }
}

// Form Gönderimi (Ekleme veya Düzenleme)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $name = trim($_POST['name'] ?? '');
    $company_id_post = $_POST['company_id'] ?? null; // Düzenleme için

    // Basit doğrulama
    if (empty($name)) {
        $error = "Lütfen firma adını doldurunuz.";
    } else {
        
        try {
            // Firma adı benzersizlik kontrolü
            $sql_check = "SELECT id FROM companies WHERE name = ?";
            $params_check = [$name];
            
            if ($action === 'edit' && $company_id_post) {
                // Düzenleme modunda, aynı ID'ye sahip olmayan kayıtları kontrol et
                $sql_check .= " AND id != ?";
                $params_check[] = $company_id_post;
            }
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute($params_check);
            
            if ($stmt_check->fetch()) {
                $error = "Bu firma adı zaten sistemde kayıtlıdır.";
            } else {
                
                if ($action === 'add') {
                    // Ekleme İşlemi (CREATE)
                    $sql = "INSERT INTO companies (name) VALUES (?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$name]);
                    $success = "Yeni otobüs firması başarıyla oluşturuldu.";
                    
                } elseif ($action === 'edit' && $company_id_post) {
                    // Düzenleme İşlemi (UPDATE)
                    $sql = "UPDATE companies SET name = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$name, $company_id_post]);
                    $success = "Firma adı başarıyla güncellendi.";
                }
                
                // Başarılı işlem sonrası paneli yönlendir
                header("Location: admin_panel.php?success=" . urlencode($success));
                exit();
            }
        } catch (PDOException $e) {
            $error = "Veritabanı işlemi başarısız: " . $e->getMessage();
        }
    }
    // Hata durumunda formu doldurmaya devam etmesi için
    $company_name = $name;
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
        .form-card { max-width: 500px; margin: 50px auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background-color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <h1 class="h3 mb-4 text-center text-success"><i class="fas fa-edit me-2"></i> <?= $page_title ?></h1>
            
            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <form method="POST" action="company_crud.php?action=<?= $action ?><?= $company_id ? '&id=' . $company_id : '' ?>">
                
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="company_id" value="<?= $company_id ?>">
                    <p class="text-muted small mb-3">Firma ID: <?= $company_id ?></p>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label for="name" class="form-label">Firma Adı</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($company_name) ?>" required>
                </div>
                
                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i> <?= $action === 'add' ? 'Firma Oluştur' : 'Firma Adını Güncelle' ?></button>
                    <a href="admin_panel.php" class="btn btn-secondary"><i class="fas fa-times-circle me-2"></i> İptal ve Geri Dön</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
