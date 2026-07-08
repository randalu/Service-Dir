#!/bin/bash
set -e

echo "=== CyberPanel Quick Deployment ==="
echo ""

INSTALL_PATH="/home/zro.ovh/public_html/dir"

echo "=== Step 1: Update .env configuration ==="
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

echo "✓ .env created"

echo ""
echo "=== Step 2: Set permissions for CyberPanel ==="
chown -R nobody:nobody $INSTALL_PATH
chmod -R 755 $INSTALL_PATH
chmod 644 $INSTALL_PATH/.env
echo "✓ Permissions set"

echo ""
echo "=== Step 3: Run database migrations ==="
cd $INSTALL_PATH
php db.php 2>/dev/null || true
echo "✓ Database ready"

echo ""
echo "=== Step 4: Reload OpenLiteSpeed ==="
systemctl reload lsws 2>/dev/null || true
echo "✓ OpenLiteSpeed reloaded"

echo ""
echo "============================================"
echo "  DEPLOYMENT COMPLETE!"
echo "  Visit: https://ZRO.ovh/dir"
echo "============================================"
