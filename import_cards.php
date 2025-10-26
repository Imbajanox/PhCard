<?php
/**
 * Card Import Utility
 * 
 * Command-line tool for importing cards from JSON files
 * 
 * Usage:
 *   php import_cards.php <json_file> [set_code]
 * 
 * Example:
 *   php import_cards.php card_expansion_example.json EXP1
 */

require_once 'config.php';
require_once 'api/CardFactory.php';

// Check command line arguments
if ($argc < 2) {
    echo "Usage: php import_cards.php <json_file> [set_code]\n";
    echo "Example: php import_cards.php card_expansion_example.json EXP1\n";
    exit(1);
}

$jsonFile = $argv[1];
$setCode = $argv[2] ?? null;

// Validate file exists
if (!file_exists($jsonFile)) {
    echo "Error: File not found: $jsonFile\n";
    exit(1);
}

echo "PhCard Card Import Utility\n";
echo "==========================\n\n";

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }
    
    // Import cards
    echo "Importing cards from: $jsonFile\n";
    $results = CardFactory::importFromJSON($jsonFile, $conn);
    
    echo "\nResults:\n";
    echo "  Inserted: {$results['inserted']} cards\n";
    echo "  Failed: {$results['failed']} cards\n";
    
    if (!empty($results['errors'])) {
        echo "\nErrors:\n";
        foreach ($results['errors'] as $error) {
            echo "  - $error\n";
        }
    }
    
    // Link cards to set if set_code provided
    if ($setCode && $results['inserted'] > 0) {
        echo "\nLinking cards to set: $setCode\n";
        
        // Check if set exists
        $stmt = $conn->prepare("SELECT id, name FROM card_sets WHERE code = ?");
        $stmt->execute([$setCode]);
        $set = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$set) {
            echo "Warning: Card set '$setCode' not found. Creating new set...\n";
            
            // Create the set
            $setName = readline("Enter set name: ");
            $setDesc = readline("Enter set description: ");
            
            $stmt = $conn->prepare("
                INSERT INTO card_sets (name, code, description, set_type)
                VALUES (?, ?, ?, 'expansion')
            ");
            $stmt->execute([$setName, $setCode, $setDesc]);
            $setId = $conn->lastInsertId();
            
            echo "Created new set: $setName (ID: $setId)\n";
        } else {
            $setId = $set['id'];
            echo "Using existing set: {$set['name']}\n";
        }
        
        // Get recently inserted cards (last N cards)
        $stmt = $conn->prepare("
            SELECT id, name FROM cards 
            ORDER BY id DESC 
            LIMIT ?
        ");
        $insertedCount = $results['inserted'];
		$stmt->bindValue(1, $insertedCount, PDO::PARAM_INT);
		$stmt->execute();

		$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Link cards to set
        $linked = 0;
        foreach ($cards as $index => $card) {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO card_set_members (card_id, set_id, set_number)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE set_number = ?
                ");
                $setNumber = $index + 1;
                $stmt->execute([$card['id'], $setId, $setNumber, $setNumber]);
                $linked++;
            } catch (PDOException $e) {
                // Ignore duplicate errors
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }
        
        echo "Linked $linked cards to set '$setCode'\n";
    }
    
    echo "\n✓ Import completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
