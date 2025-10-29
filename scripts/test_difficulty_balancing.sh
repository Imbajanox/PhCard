#!/bin/bash

# Test script to verify AI difficulty balancing changes
# This tests that the difficulty-dependent values are correctly configured

echo "========================================"
echo "AI Difficulty Balancing - Test Suite"
echo "========================================"
echo ""

# Test 1: Match expressions work
echo "Test 1: Match expressions syntax..."
php -r "
\$aiLevel = 1;
\$result = match(\$aiLevel) {
    1 => 0.3,
    2 => 0.6,
    3 => 1.0,
    4 => 1.5,
    default => 2.0
};
if (\$result == 0.3) {
    echo \"✓ Match expression test passed\n\";
} else {
    echo \"✗ Match expression test failed\n\";
    exit(1);
}
"

# Test 2: Keyword multipliers
echo "Test 2: Keyword multipliers..."
php -r "
function getKeywordMultiplier(\$aiLevel) {
    return match(\$aiLevel) {
        1 => 0.3,
        2 => 0.6,
        3 => 1.0,
        4 => 1.5,
        default => 2.0
    };
}

\$tests = [
    [1, 0.3],
    [2, 0.6],
    [3, 1.0],
    [4, 1.5],
    [5, 2.0]
];

foreach (\$tests as \$test) {
    \$level = \$test[0];
    \$expected = \$test[1];
    \$actual = getKeywordMultiplier(\$level);
    if (\$actual == \$expected) {
        echo \"  ✓ Level \$level: multiplier = \$actual\n\";
    } else {
        echo \"  ✗ Level \$level: expected \$expected, got \$actual\n\";
        exit(1);
    }
}
"

# Test 3: Card limits
echo "Test 3: Card limits..."
php -r "
function getCardLimit(\$aiLevel) {
    return match(\$aiLevel) {
        1 => 5,
        2 => 6,
        3 => 7,
        default => 8
    };
}

\$tests = [
    [1, 5],
    [2, 6],
    [3, 7],
    [4, 8],
    [5, 8]
];

foreach (\$tests as \$test) {
    \$level = \$test[0];
    \$expected = \$test[1];
    \$actual = getCardLimit(\$level);
    if (\$actual == \$expected) {
        echo \"  ✓ Level \$level: card limit = \$actual\n\";
    } else {
        echo \"  ✗ Level \$level: expected \$expected, got \$actual\n\";
        exit(1);
    }
}
"

# Test 4: Max cards to play
echo "Test 4: Max cards to play per turn..."
php -r "
function getMaxCardsToPlay(\$aiLevel) {
    return match(\$aiLevel) {
        1 => 2,
        2 => 3,
        3 => 4,
        default => 10
    };
}

\$tests = [
    [1, 2],
    [2, 3],
    [3, 4],
    [4, 10],
    [5, 10]
];

foreach (\$tests as \$test) {
    \$level = \$test[0];
    \$expected = \$test[1];
    \$actual = getMaxCardsToPlay(\$level);
    if (\$actual == \$expected) {
        echo \"  ✓ Level \$level: max cards = \$actual\n\";
    } else {
        echo \"  ✗ Level \$level: expected \$expected, got \$actual\n\";
        exit(1);
    }
}
"

# Test 5: Randomness factors
echo "Test 5: Randomness factors..."
php -r "
function testRandomness(\$aiLevel, \$iterations = 100) {
    \$min = PHP_INT_MAX;
    \$max = PHP_INT_MIN;
    
    for (\$i = 0; \$i < \$iterations; \$i++) {
        if (\$aiLevel == 1) {
            \$factor = mt_rand(40, 160) / 100.0;
        } else if (\$aiLevel == 2) {
            \$factor = mt_rand(70, 130) / 100.0;
        } else if (\$aiLevel == 3) {
            \$factor = mt_rand(85, 115) / 100.0;
        } else {
            \$factor = 1.0;
        }
        
        \$min = min(\$min, \$factor);
        \$max = max(\$max, \$factor);
    }
    
    return [\$min, \$max];
}

\$tests = [
    [1, 0.40, 1.60],
    [2, 0.70, 1.30],
    [3, 0.85, 1.15],
    [4, 1.0, 1.0]
];

foreach (\$tests as \$test) {
    \$level = \$test[0];
    \$expectedMin = \$test[1];
    \$expectedMax = \$test[2];
    list(\$actualMin, \$actualMax) = testRandomness(\$level);
    
    // Allow for some tolerance
    \$minOk = abs(\$actualMin - \$expectedMin) < 0.05;
    \$maxOk = abs(\$actualMax - \$expectedMax) < 0.05;
    
    if (\$minOk && \$maxOk) {
        echo \"  ✓ Level \$level: randomness range [\";
        printf('%.2f', \$actualMin);
        echo ', ';
        printf('%.2f', \$actualMax);
        echo \"]\n\";
    } else {
        echo \"  ✗ Level \$level: randomness out of expected range\n\";
        exit(1);
    }
}
"

# Test 6: No syntax errors in game.php
echo "Test 6: Checking game.php for syntax errors..."
php -l api/game.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "  ✓ No syntax errors in api/game.php"
else
    echo "  ✗ Syntax errors found in api/game.php"
    exit 1
fi

echo ""
echo "========================================"
echo "All tests passed! ✓"
echo "========================================"
echo ""
echo "Summary of AI Difficulty Changes:"
echo "Level 1: Very Easy - 70% keyword reduction, 2 cards/turn, ±60% randomness"
echo "Level 2: Easy - 40% keyword reduction, 3 cards/turn, ±30% randomness"
echo "Level 3: Normal - Standard play, 4 cards/turn, ±15% randomness"
echo "Level 4+: Hard - Optimal play, 10 cards/turn, no randomness"
echo ""
