#!/bin/bash
# Test script for PhCard advanced mechanics

echo "PhCard Advanced Mechanics - Test Script"
echo "========================================"
echo ""

# Test 1: Check if database extensions SQL is valid
echo "Test 1: Validating database_extensions.sql syntax..."
if grep -q "CREATE TABLE" database_extensions.sql; then
    echo "✓ Database extensions SQL appears valid"
else
    echo "✗ Database extensions SQL may be invalid"
fi

# Test 2: Check PHP syntax
echo ""
echo "Test 2: Checking PHP syntax..."
php_files=(api/game.php api/deck.php api/analytics.php api/simulation.php)
all_valid=true
for file in "${php_files[@]}"; do
    if php -l "$file" > /dev/null 2>&1; then
        echo "✓ $file - syntax OK"
    else
        echo "✗ $file - syntax error"
        all_valid=false
    fi
done

# Test 3: Check JavaScript syntax (basic)
echo ""
echo "Test 3: Checking JavaScript files..."
if [ -f "public/js/app.js" ]; then
    if grep -q "updateMana" public/js/app.js; then
        echo "✓ app.js contains updateMana function"
    else
        echo "✗ app.js missing updateMana function"
    fi
    
    if grep -q "performMulligan" public/js/app.js; then
        echo "✓ app.js contains performMulligan function"
    else
        echo "✗ app.js missing performMulligan function"
    fi
else
    echo "✗ app.js not found"
fi

# Test 4: Check HTML updates
echo ""
echo "Test 4: Checking HTML updates..."
if grep -q "player-mana-display" index.html; then
    echo "✓ index.html contains mana display"
else
    echo "✗ index.html missing mana display"
fi

# Test 5: Check CSS updates
echo ""
echo "Test 5: Checking CSS updates..."
if grep -q "card-mana-cost" public/css/style.css; then
    echo "✓ style.css contains mana cost styling"
else
    echo "✗ style.css missing mana cost styling"
fi

if grep -q "keyword" public/css/style.css; then
    echo "✓ style.css contains keyword styling"
else
    echo "✗ style.css missing keyword styling"
fi

# Test 6: Check config updates
echo ""
echo "Test 6: Checking config.php constants..."
if grep -q "STARTING_MANA" config.php; then
    echo "✓ config.php contains STARTING_MANA"
else
    echo "✗ config.php missing STARTING_MANA"
fi

if grep -q "MAX_MANA" config.php; then
    echo "✓ config.php contains MAX_MANA"
else
    echo "✗ config.php missing MAX_MANA"
fi

if grep -q "MULLIGAN_CARDS" config.php; then
    echo "✓ config.php contains MULLIGAN_CARDS"
else
    echo "✗ config.php missing MULLIGAN_CARDS"
fi

# Test 7: Count new API endpoints
echo ""
echo "Test 7: Checking API endpoints..."
endpoint_count=0
[ -f "api/deck.php" ] && ((endpoint_count++)) && echo "✓ api/deck.php exists"
[ -f "api/analytics.php" ] && ((endpoint_count++)) && echo "✓ api/analytics.php exists"
[ -f "api/simulation.php" ] && ((endpoint_count++)) && echo "✓ api/simulation.php exists"
echo "  Total new endpoints: $endpoint_count/3"

# Summary
echo ""
echo "========================================"
echo "Test Summary"
echo "========================================"
if [ "$all_valid" = true ]; then
    echo "✓ All PHP syntax checks passed"
    echo "✓ Core functionality appears to be implemented"
    echo ""
    echo "Next steps:"
    echo "1. Run: mysql -u root -p phcard < database_extensions.sql"
    echo "2. Test in browser by starting a new game"
    echo "3. Verify mana display, mulligan option, and card keywords"
else
    echo "✗ Some tests failed - please review"
fi
