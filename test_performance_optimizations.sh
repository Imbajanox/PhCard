#!/bin/bash
# Performance Optimization Test Script
# This script tests the core functionality after performance optimizations

echo "===== PhCard Performance Optimization Test Suite ====="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

test_count=0
pass_count=0
fail_count=0

# Function to run a test
run_test() {
    test_count=$((test_count + 1))
    test_name=$1
    test_command=$2
    
    echo -n "Test $test_count: $test_name... "
    
    if eval $test_command > /dev/null 2>&1; then
        echo -e "${GREEN}PASS${NC}"
        pass_count=$((pass_count + 1))
    else
        echo -e "${RED}FAIL${NC}"
        fail_count=$((fail_count + 1))
    fi
}

# Test 1: Check PHP syntax for all modified files
echo "=== Syntax Checks ==="
run_test "CacheManager syntax" "php -l src/backend/utils/CacheManager.php"
run_test "GameActions syntax" "php -l src/backend/game/GameActions.php"
run_test "BattleSystem syntax" "php -l src/backend/game/BattleSystem.php"
run_test "AIPlayer syntax" "php -l src/backend/game/AIPlayer.php"
run_test "Multiplayer syntax" "php -l src/backend/game/Multiplayer.php"
run_test "user.php syntax" "php -l api/user.php"

echo ""
echo "=== File Existence Checks ==="
run_test "Performance indexes SQL exists" "test -f sql/performance_indexes.sql"
run_test "Performance docs exist" "test -f documentation/PERFORMANCE_OPTIMIZATION.md"
run_test "CacheManager exists" "test -f src/backend/utils/CacheManager.php"

echo ""
echo "=== Code Quality Checks ==="
run_test "No PHP short tags" "! grep -r '<?' src/backend/utils/CacheManager.php | grep -v '<?php'"
run_test "CacheManager has namespace" "grep -q 'namespace Utils' src/backend/utils/CacheManager.php"
run_test "GameActions imports CacheManager" "grep -q 'use Utils\\\\CacheManager' src/backend/game/GameActions.php"
run_test "AIPlayer imports CacheManager" "grep -q 'use Utils\\\\CacheManager' src/backend/game/AIPlayer.php"
run_test "Multiplayer imports CacheManager" "grep -q 'use Utils\\\\CacheManager' src/backend/game/Multiplayer.php"

echo ""
echo "=== Cache Implementation Checks ==="
run_test "GameActions uses CacheManager::remember" "grep -q 'CacheManager::remember' src/backend/game/GameActions.php"
run_test "AIPlayer uses CacheManager::remember" "grep -q 'CacheManager::remember' src/backend/game/AIPlayer.php"
run_test "Multiplayer uses CacheManager::remember" "grep -q 'CacheManager::remember' src/backend/game/Multiplayer.php"
run_test "user.php uses CacheManager::remember" "grep -q 'CacheManager::remember' api/user.php"

echo ""
echo "=== Frontend Optimization Checks ==="
run_test "game.js uses DocumentFragment" "grep -q 'DocumentFragment' src/frontend/js/game/game.js"
run_test "game.js uses requestAnimationFrame" "grep -q 'requestAnimationFrame' src/frontend/js/game/game.js"

echo ""
echo "=== Database Index Checks ==="
run_test "Index migration has user_cards indexes" "grep -q 'idx_user_id' sql/performance_indexes.sql"
run_test "Index migration has game_history indexes" "grep -q 'game_history' sql/performance_indexes.sql"
run_test "Index migration has deck_cards indexes" "grep -q 'deck_cards' sql/performance_indexes.sql"

echo ""
echo "=== Documentation Checks ==="
run_test "Performance docs mention caching" "grep -qi 'cache' documentation/PERFORMANCE_OPTIMIZATION.md"
run_test "Performance docs mention indexes" "grep -qi 'index' documentation/PERFORMANCE_OPTIMIZATION.md"
run_test "Performance docs mention expected gains" "grep -qi 'faster' documentation/PERFORMANCE_OPTIMIZATION.md"

echo ""
echo "=== Battle System Optimization Checks ==="
run_test "BattleSystem uses array_filter" "grep -q 'array_filter' src/backend/game/BattleSystem.php"
run_test "BattleSystem uses ARRAY_FILTER_USE_BOTH" "grep -q 'ARRAY_FILTER_USE_BOTH' src/backend/game/BattleSystem.php"

echo ""
echo "===== Test Summary ====="
echo "Total tests: $test_count"
echo -e "Passed: ${GREEN}$pass_count${NC}"
echo -e "Failed: ${RED}$fail_count${NC}"

if [ $fail_count -eq 0 ]; then
    echo -e "\n${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "\n${RED}Some tests failed!${NC}"
    exit 1
fi
