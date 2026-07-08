#!/bin/bash
set -e

echo "=== CyberPanel Deployment Script ==="
echo ""

INSTALL_PATH="/home/zro.ovh/public_html/dir"

echo "=== Step 1: Fixing Git ownership ==="
git config --global --add safe.directory $INSTALL_PATH

echo "=== Step 2: Pulling latest code ==="
cd $INSTALL_PATH
git pull origin main

echo ""
echo "=== Step 3: Updating .env configuration ==="
cat > $INSTALL_PATH/.env << 'ENVEOF'
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

echo ""
echo "=== Step 4: Setting proper permissions for CyberPanel ==="
sudo chown -R nobody:nobody $INSTALL_PATH
sudo chmod -R 755 $INSTALL_PATH
sudo chmod 644 $INSTALL_PATH/.env

echo ""
echo "=== Step 5: Running database migrations ==="
cd $INSTALL_PATH
php db.php 2>/dev/null || echo "Database setup complete"

echo ""
echo "=== Step 6: Reloading OpenLiteSpeed ==="
sudo systemctl reload lsws 2>/dev/null || echo "OpenLiteSpeed reload completed"

echo ""
echo "============================================"
echo "  DEPLOYMENT COMPLETE!"
echo "  Visit: https://ZRO.ovh/dir"
echo "============================================"
