#!/bin/bash
set -e

echo "=== Step 1: Installing Apache ==="
sudo dnf install -y httpd
sudo systemctl enable --now httpd

echo "=== Step 2: Installing PHP extensions ==="
sudo dnf install -y php php-mysqlnd php-gd php-mbstring php-xml php-curl php-zip php-intl php-opcache
sudo systemctl restart httpd

echo "=== Step 3: Creating MySQL database ==="
sudo mysql -e "CREATE DATABASE IF NOT EXISTS zro_srv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'zro_srv'@'localhost' IDENTIFIED BY 'RdlDir2026!Str0ng';"
sudo mysql -e "GRANT ALL PRIVILEGES ON zro_srv.* TO 'zro_srv'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

echo "=== Step 4: Creating Apache config ==="
sudo tee /etc/httpd/conf.d/zro-app.conf > /dev/null << 'HTMLEOF'
Alias /dir /home/zro.ovh/public_html/dir/

<Directory /home/zro.ovh/public_html/dir/>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted

    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php/$1 [L]
    </IfModule>
</Directory>
HTMLEOF
sudo systemctl restart httpd

echo "=== Step 5: Setting permissions ==="
sudo chown -R apache:apache /home/zro.ovh/public_html/dir/
sudo chmod -R 755 /home/zro.ovh/public_html/dir/

echo "=== Step 6: Opening firewall ==="
sudo firewall-cmd --permanent --add-service=http 2>/dev/null || true
sudo firewall-cmd --permanent --add-service=https 2>/dev/null || true
sudo firewall-cmd --reload 2>/dev/null || true

echo "=== Step 7: Creating .env ==="
cat > /home/zro.ovh/public_html/dir/.env << 'ENVEOF'
DB_HOST=localhost
DB_NAME=zro_srv
DB_USER=zro_srv
DB_PASS=RdlDir2026!Str0ng
DB_CHARSET=utf8mb4

SMS_API_USER_ID=1557
SMS_API_KEY=b39e3d04-754e-4562-9e01-c53c3edce2e5
SMS_SENDER_ID=RandaluWebs

APP_ENV=production
APP_URL=https://ZRO.ovh/dir
GOOGLE_MAPS_API_KEY=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://ZRO.ovh/dir/google_callback.php
ENVEOF

echo "=== Step 8: Running database migration ==="
cd /home/zro.ovh/public_html/dir
php db.php 2>/dev/null || echo "db.php will auto-create tables on first visit"

echo ""
echo "============================================"
echo "  DEPLOYMENT COMPLETE!"
echo "  Visit: https://ZRO.ovh/dir"
echo "  DB: zro_srv / zro_srv"
echo "  DB Pass: RdlDir2026!Str0ng"
echo "============================================"
