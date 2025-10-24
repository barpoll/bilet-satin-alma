🚌 Otobüs Bileti Satın Alma Platformu - Proje Teslimatı

Bu proje, görev dökümanında belirtilen gereksinimlere uygun olarak PHP (PDO/SQLite), Docker ve Bootstrap kullanılarak geliştirilmiştir.

📦 Proje İçeriği ve Teknolojiler

Programlama Dili: PHP (8.2+)

Veritabanı: SQLite (PDO ile entegre)

Arayüz: HTML, CSS ve Bootstrap 5 (Responsive tasarım)

Paketleme: Docker Container (Linux/Apache tabanlı)

🚀 Çalıştırma Talimatları (Docker)

Projenin çalıştırılması için Docker Desktop kurulu olmalıdır. Tüm komutlar, projenin kök dizininde (README.md, Dockerfile ve src/ klasörlerinin bulunduğu yer) çalıştırılmalıdır.

⚠️ KRİTİK UYARI: Yol Değişikliği Gereklidir!

**Aşağıdaki komutlarda yer alan C:\Users\BARIŞ\Desktop\yavuzlar_proje yolunu, kendi bilgisayarınızdaki projenin kurulu olduğu tam yol ile değiştirmek zorunludur.**

Adım 1: İmajı Oluşturma (BUILD)

Gerekli yazılımları ve proje dosyalarını içeren Docker imajını oluşturur:

docker build -t bilet-platformu .




Adım 2: Veritabanını Kurma ve Test Verilerini Doldurma

Bu komut, setup.sql dosyasını kullanarak otobus_bilet.sqlite veritabanı dosyasını oluşturur ve tüm tabloları/test verilerini doldurur.

docker run --rm -v C:\Users\BARIŞ\Desktop\yavuzlar_proje\data:/data -v C:\Users\BARIŞ\Desktop\yavuzlar_proje:/app bilet-platformu /bin/bash -c "sqlite3 /data/otobus_bilet.sqlite < /app/setup.sql"



Adım 3: Uygulamayı Volume ile Başlatma

Bu komut, uygulamanın çalışmasını sağlar ve Adım 2'de oluşturulan veritabanını bağlayarak verilerin kalıcılığını garanti eder.

docker run -d --name bilet-app -p 8080:80 -v C:\Users\BARIŞ\Desktop\yavuzlar_proje\data:/var/www/data bilet-platformu




Adım 4: Tarayıcıda Açma

Uygulama çalışmaya başladıktan sonra tarayıcıda erişim adresi:

http://localhost:8080/index.php




🔑 Test Hesapları ve Bütçeler

Tüm hesapların bütçeleri ve şifreleri güncellenmiştir.

Admin (Sistem Yöneticisi)

E-posta: admin@bilet.com

Şifre: admn23344556

Bütçe: 10.000.000 TL

Firma Admin (Hızlı Seyahat Yetkilisi)

E-posta: firma_admin@hizli.com

Şifre: yvzlr455667

Bütçe: 500.000 TL

Yolcu (User)

E-posta: ylc12233445@mail.com

Şifre: ylc12233445

Bütçe: 10.000 TL
