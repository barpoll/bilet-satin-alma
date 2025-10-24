<?php
// db.php - DİKKAT: Bu yol, Docker'daki CONTAINER içi mutlak yoldur.

// Veritabanı dosyasının CONTAINER içindeki mutlak yolu:
$db_path = '/var/www/data/otobus_bilet.sqlite'; 
$pdo = null; // PDO nesnesini tutacak değişken

try {
    // SQLite veritabanı bağlantısı kuruldu. Eğer dosya yoksa, SQLite bu dosyayı oluşturmaya çalışacaktır.
    $pdo = new PDO("sqlite:" . $db_path);
    // Hata modunu ayarlama: Hata oluştuğunda istisna (exception) fırlat
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // YENİ: Eğer veritabanı yeni oluşturulduysa (yani tablolar yoksa), tablo oluşturma betiğini çalıştır.
    $tables_exist = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();

    if (!$tables_exist) {
        // Tabloları oluşturma betiğini çalıştıran geçici bir fonksiyon
        $setup_sql = file_get_contents('/var/www/html/setup.sql');
        if ($setup_sql) {
            $pdo->exec($setup_sql);
        }
    }

} catch (PDOException $e) {
    // Bağlantı hatası durumunda mesajı göster
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
?>
