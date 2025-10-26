#!/bin/bash

# PhCard Setup Script
# This script helps set up the PhCard game environment

# Exit on error
set -e

# Validate script has proper permissions
if [ ! -x "$0" ]; then
    echo "Error: Script doesn't have execute permissions. Run: chmod +x setup.sh"
    exit 1
fi

echo "========================================"
echo "PhCard - Setup Script"
echo "========================================"
echo ""

# Check for PHP
echo "Checking for PHP..."
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1)
    echo "✓ PHP found: $PHP_VERSION"
else
    echo "✗ PHP not found. Please install PHP 7.4 or higher."
    exit 1
fi

# Check for MySQL
echo ""
echo "Checking for MySQL/MariaDB..."
if command -v mysql &> /dev/null; then
    MYSQL_VERSION=$(mysql --version)
    echo "✓ MySQL found: $MYSQL_VERSION"
else
    echo "⚠ MySQL not found. You'll need MySQL or MariaDB installed."
fi

# Check PHP syntax
echo ""
echo "Checking PHP syntax..."
php -l config.php > /dev/null 2>&1 && echo "✓ config.php syntax OK"
php -l api/auth.php > /dev/null 2>&1 && echo "✓ api/auth.php syntax OK"
php -l api/user.php > /dev/null 2>&1 && echo "✓ api/user.php syntax OK"
php -l api/game.php > /dev/null 2>&1 && echo "✓ api/game.php syntax OK"

# Check file structure
echo ""
echo "Checking file structure..."
FILES=("index.html" "config.php" "database.sql" "api/auth.php" "api/user.php" "api/game.php" "public/css/style.css" "public/js/app.js")
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "✓ $file exists"
    else
        echo "✗ $file missing"
    fi
done

# Create .htaccess if not exists (for Apache)
echo ""
echo "Creating .htaccess for Apache (if needed)..."
if [ ! -f ".htaccess" ]; then
    cat > .htaccess << 'EOF'
# PhCard .htaccess

# Enable error display for development (disable in production)
php_flag display_errors On
php_flag display_startup_errors On

# Session settings
php_value session.cookie_httponly 1
php_value session.use_strict_mode 1

# Default page
DirectoryIndex index.html

# Protect sensitive files
<FilesMatch "^(config\.php|database\.sql)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json
</IfModule>

# Browser caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType text/html "access plus 0 seconds"
</IfModule>
EOF
    echo "✓ .htaccess created"
else
    echo "✓ .htaccess already exists"
fi

echo ""
echo "========================================"
echo "Setup Summary"
echo "========================================"
echo ""
echo "Next steps:"
echo "1. Create a MySQL database named 'phcard'"
echo "2. Import the schema: mysql -u root -p phcard < database.sql"
echo "3. Edit config.php with your database credentials"
echo "4. Open install.html in your browser for guided setup"
echo "5. Or open index.html to start playing!"
echo ""
echo "For development:"
echo "- PHP built-in server: php -S localhost:8000"
echo "- Open: http://localhost:8000"
echo ""
echo "For documentation:"
echo "- README.md - User documentation"
echo "- DEVELOPER.md - Developer documentation"
echo "- VISUAL_GUIDE.md - Visual guide"
echo ""
echo "========================================"
