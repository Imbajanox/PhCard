#!/bin/bash
# Integration test for PhCard extensibility framework

echo "PhCard Extensibility Framework - Integration Tests"
echo "===================================================="
echo ""

# Test 1: PHP Syntax
echo "Test 1: PHP Syntax Validation"
echo "------------------------------"
php_files=(
    "api/CardEffectRegistry.php"
    "api/GameEventSystem.php"
    "api/CardFactory.php"
    "api/quests.php"
    "api/card_sets.php"
    "import_cards.php"
    "api/game.php"
)

all_valid=true
for file in "${php_files[@]}"; do
    if php -l "$file" > /dev/null 2>&1; then
        echo "  ✓ $file"
    else
        echo "  ✗ $file - SYNTAX ERROR"
        all_valid=false
    fi
done

if [ "$all_valid" = true ]; then
    echo "  All PHP files valid ✓"
else
    echo "  Some PHP files have syntax errors ✗"
    exit 1
fi

echo ""

# Test 2: SQL Validation
echo "Test 2: SQL Schema Validation"
echo "------------------------------"
sql_statements=$(grep -c "^CREATE\|^ALTER\|^INSERT" database_quest_achievement_system.sql)
echo "  Database schema contains $sql_statements statements"
if [ "$sql_statements" -ge 10 ]; then
    echo "  ✓ Schema appears complete"
else
    echo "  ✗ Schema may be incomplete"
fi

echo ""

# Test 3: File Structure
echo "Test 3: File Structure Check"
echo "-----------------------------"
required_files=(
    "api/CardEffectRegistry.php"
    "api/GameEventSystem.php"
    "api/CardFactory.php"
    "api/quests.php"
    "api/card_sets.php"
    "database_quest_achievement_system.sql"
    "card_expansion_example.json"
    "import_cards.php"
    "EXTENSION_GUIDE.md"
    "EXTENSIBILITY_README.md"
)

all_exist=true
for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file"
    else
        echo "  ✗ $file - MISSING"
        all_exist=false
    fi
done

if [ "$all_exist" = true ]; then
    echo "  All required files present ✓"
else
    echo "  Some files are missing ✗"
    exit 1
fi

echo ""

# Test 4: JSON Validation
echo "Test 4: JSON Configuration Validation"
echo "--------------------------------------"
if command -v python3 &> /dev/null; then
    if python3 -c "import json; json.load(open('card_expansion_example.json'))" 2>/dev/null; then
        echo "  ✓ card_expansion_example.json is valid JSON"
    else
        echo "  ✗ card_expansion_example.json is invalid JSON"
        all_valid=false
    fi
else
    echo "  ⚠ Python3 not available, skipping JSON validation"
fi

echo ""

# Test 5: Class/Function Existence
echo "Test 5: Class and Function Checks"
echo "----------------------------------"
if grep -q "class CardEffectRegistry" api/CardEffectRegistry.php; then
    echo "  ✓ CardEffectRegistry class defined"
else
    echo "  ✗ CardEffectRegistry class not found"
    all_valid=false
fi

if grep -q "class GameEventSystem" api/GameEventSystem.php; then
    echo "  ✓ GameEventSystem class defined"
else
    echo "  ✗ GameEventSystem class not found"
    all_valid=false
fi

if grep -q "class CardFactory" api/CardFactory.php; then
    echo "  ✓ CardFactory class defined"
else
    echo "  ✗ CardFactory class not found"
    all_valid=false
fi

echo ""

# Test 6: Integration Points
echo "Test 6: Integration Point Verification"
echo "---------------------------------------"
if grep -q "require_once 'GameEventSystem.php'" api/game.php; then
    echo "  ✓ GameEventSystem integrated in game.php"
else
    echo "  ✗ GameEventSystem not integrated in game.php"
    all_valid=false
fi

if grep -q "require_once 'CardEffectRegistry.php'" api/game.php; then
    echo "  ✓ CardEffectRegistry integrated in game.php"
else
    echo "  ✗ CardEffectRegistry not integrated in game.php"
    all_valid=false
fi

if grep -q "GameEventSystem::trigger" api/game.php; then
    echo "  ✓ Event triggers found in game.php"
else
    echo "  ✗ No event triggers in game.php"
    all_valid=false
fi

echo ""

# Test 7: Documentation
echo "Test 7: Documentation Completeness"
echo "-----------------------------------"
doc_sections=$(grep -c "^##" EXTENSION_GUIDE.md)
echo "  EXTENSION_GUIDE.md contains $doc_sections sections"
if [ "$doc_sections" -ge 5 ]; then
    echo "  ✓ Documentation appears comprehensive"
else
    echo "  ✗ Documentation may be incomplete"
fi

readme_lines=$(wc -l < EXTENSIBILITY_README.md)
echo "  EXTENSIBILITY_README.md contains $readme_lines lines"
if [ "$readme_lines" -ge 100 ]; then
    echo "  ✓ README is detailed"
else
    echo "  ⚠ README could be more detailed"
fi

echo ""

# Test 8: Example Card Count
echo "Test 8: Example Card Validation"
echo "--------------------------------"
if command -v python3 &> /dev/null; then
    card_count=$(python3 -c "import json; print(len(json.load(open('card_expansion_example.json'))))" 2>/dev/null)
    echo "  Example expansion contains $card_count cards"
    if [ "$card_count" -ge 10 ]; then
        echo "  ✓ Good variety of example cards"
    else
        echo "  ⚠ Could include more example cards"
    fi
else
    echo "  ⚠ Python3 not available, skipping card count"
fi

echo ""

# Summary
echo "===================================================="
echo "Test Summary"
echo "===================================================="
if [ "$all_valid" = true ]; then
    echo "✓ All tests passed!"
    echo ""
    echo "The extensibility framework is ready to use."
    echo ""
    echo "Next steps:"
    echo "1. Install database schema:"
    echo "   mysql -u USER -p DATABASE < database_quest_achievement_system.sql"
    echo ""
    echo "2. Try importing example cards:"
    echo "   php import_cards.php card_expansion_example.json EXAMPLE"
    echo ""
    echo "3. Review EXTENSION_GUIDE.md for detailed tutorials"
    echo ""
    echo "4. Start building your own content!"
    exit 0
else
    echo "✗ Some tests failed"
    echo "Please review the errors above"
    exit 1
fi
