FROM php:8.2-apache

# Gerekli uzantıları yükle
RUN apt-get update && \
    apt-get install -y \
    sqlite3 \
    libsqlite3-dev && \
    docker-php-ext-install pdo pdo_sqlite && \
    rm -rf /var/lib/apt/lists/*

# Proje dosyalarını Apache web kök dizinine kopyala
COPY src/ /var/www/html/

# Veritabanı dosyası artık COPY ile taşınmayacak, PHP ile oluşturulacak.
# Ancak veritabanının bulunacağı /var/www/data dizinini oluşturalım.
RUN mkdir -p /var/www/data
RUN chmod -R 777 /var/www/data

# Varsayılan port (80) açılacaktır.
EXPOSE 80
