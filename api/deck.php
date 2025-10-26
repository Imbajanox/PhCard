<?php
require_once '../config.php';

header('Content-Type: application/json');
requireLogin();

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'list':
        listDecks();
        break;
    case 'create':
        createDeck();
        break;
    case 'update':
        updateDeck();
        break;
    case 'delete':
        deleteDeck();
        break;
    case 'set_active':
        setActiveDeck();
        break;
    case 'get_deck':
        getDeck();
        break;
    case 'add_card':
        addCardToDeck();
        break;
    case 'remove_card':
        removeCardFromDeck();
        break;
    case 'list_archetypes':
        listArchetypes();
        break;
    case 'validate_deck':
        validateDeck();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function listDecks() {
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                d.id, d.name, d.card_class, d.is_active,
                a.name as archetype_name,
                COUNT(dc.id) as card_count,
                SUM(dc.quantity) as total_cards
            FROM user_decks d
            LEFT JOIN deck_archetypes a ON d.archetype_id = a.id
            LEFT JOIN deck_cards dc ON d.id = dc.deck_id
            WHERE d.user_id = ?
            GROUP BY d.id
            ORDER BY d.is_active DESC, d.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $decks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'decks' => $decks
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to list decks']);
    }
}

function createDeck() {
    $name = $_POST['name'] ?? '';
    $archetypeId = intval($_POST['archetype_id'] ?? 0);
    $cardClass = $_POST['card_class'] ?? 'neutral';
    $conn = getDBConnection();
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Deck name required']);
        return;
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO user_decks (user_id, name, archetype_id, card_class)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $name, $archetypeId ?: null, $cardClass]);
        
        $deckId = $conn->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'deck_id' => $deckId,
            'message' => 'Deck created successfully'
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to create deck']);
    }
}

function updateDeck() {
    $deckId = intval($_POST['deck_id'] ?? 0);
    $name = $_POST['name'] ?? null;
    $archetypeId = $_POST['archetype_id'] ?? null;
    $cardClass = $_POST['card_class'] ?? null;
    $conn = getDBConnection();
    
    try {
        // Verify deck belongs to user
        $stmt = $conn->prepare("SELECT id FROM user_decks WHERE id = ? AND user_id = ?");
        $stmt->execute([$deckId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Deck not found']);
            return;
        }
        
        $updates = [];
        $params = [];
        
        if ($name !== null) {
            $updates[] = "name = ?";
            $params[] = $name;
        }
        if ($archetypeId !== null) {
            $updates[] = "archetype_id = ?";
            $params[] = $archetypeId ?: null;
        }
        if ($cardClass !== null) {
            $updates[] = "card_class = ?";
            $params[] = $cardClass;
        }
        
        if (empty($updates)) {
            echo json_encode(['success' => false, 'error' => 'No updates provided']);
            return;
        }
        
        $params[] = $deckId;
        $params[] = $_SESSION['user_id'];
        
        $sql = "UPDATE user_decks SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'message' => 'Deck updated successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to update deck']);
    }
}

function deleteDeck() {
    $deckId = intval($_POST['deck_id'] ?? 0);
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("DELETE FROM user_decks WHERE id = ? AND user_id = ?");
        $stmt->execute([$deckId, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Deck deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Deck not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to delete deck']);
    }
}

function setActiveDeck() {
    $deckId = intval($_POST['deck_id'] ?? 0);
    $conn = getDBConnection();
    
    try {
        // Deactivate all decks
        $stmt = $conn->prepare("UPDATE user_decks SET is_active = 0 WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Activate selected deck
        $stmt = $conn->prepare("UPDATE user_decks SET is_active = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$deckId, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Active deck set successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Deck not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to set active deck']);
    }
}

function getDeck() {
    $deckId = intval($_REQUEST['deck_id'] ?? 0);
    $conn = getDBConnection();
    
    try {
        // Get deck info
        $stmt = $conn->prepare("
            SELECT d.*, a.name as archetype_name, a.max_duplicates
            FROM user_decks d
            LEFT JOIN deck_archetypes a ON d.archetype_id = a.id
            WHERE d.id = ? AND d.user_id = ?
        ");
        $stmt->execute([$deckId, $_SESSION['user_id']]);
        $deck = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deck) {
            echo json_encode(['success' => false, 'error' => 'Deck not found']);
            return;
        }
        
        // Get cards in deck
        $stmt = $conn->prepare("
            SELECT c.*, dc.quantity
            FROM deck_cards dc
            JOIN cards c ON dc.card_id = c.id
            WHERE dc.deck_id = ?
            ORDER BY c.mana_cost, c.name
        ");
        $stmt->execute([$deckId]);
        $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $deck['cards'] = $cards;
        
        echo json_encode([
            'success' => true,
            'deck' => $deck
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get deck']);
    }
}

function addCardToDeck() {
    $deckId = intval($_POST['deck_id'] ?? 0);
    $cardId = intval($_POST['card_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    $conn = getDBConnection();
    
    try {
        // Verify deck ownership
        $stmt = $conn->prepare("
            SELECT d.*, a.max_duplicates 
            FROM user_decks d
            LEFT JOIN deck_archetypes a ON d.archetype_id = a.id
            WHERE d.id = ? AND d.user_id = ?
        ");
        $stmt->execute([$deckId, $_SESSION['user_id']]);
        $deck = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deck) {
            echo json_encode(['success' => false, 'error' => 'Deck not found']);
            return;
        }
        
        // Verify user owns the card
        $stmt = $conn->prepare("SELECT quantity FROM user_cards WHERE user_id = ? AND card_id = ?");
        $stmt->execute([$_SESSION['user_id'], $cardId]);
        $userCard = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userCard || $userCard['quantity'] < $quantity) {
            echo json_encode(['success' => false, 'error' => 'You do not own this card']);
            return;
        }
        
        // Check duplicate limit
        $maxDuplicates = $deck['max_duplicates'] ?: MAX_CARD_DUPLICATES;
        
        // Get current card count in deck
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM deck_cards WHERE deck_id = ?");
        $stmt->execute([$deckId]);
        $deckSize = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
        
        if ($deckSize + $quantity > MAX_DECK_SIZE) {
            echo json_encode(['success' => false, 'error' => 'Deck is full (max ' . MAX_DECK_SIZE . ' cards)']);
            return;
        }
        
        // Add or update card in deck
        $stmt = $conn->prepare("
            INSERT INTO deck_cards (deck_id, card_id, quantity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + ?, ?)
        ");
        $stmt->execute([$deckId, $cardId, $quantity, $quantity, $maxDuplicates]);
        
        echo json_encode(['success' => true, 'message' => 'Card added to deck']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to add card to deck']);
    }
}

function removeCardFromDeck() {
    $deckId = intval($_POST['deck_id'] ?? 0);
    $cardId = intval($_POST['card_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    $conn = getDBConnection();
    
    try {
        // Verify deck ownership
        $stmt = $conn->prepare("SELECT id FROM user_decks WHERE id = ? AND user_id = ?");
        $stmt->execute([$deckId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Deck not found']);
            return;
        }
        
        // Get current quantity
        $stmt = $conn->prepare("SELECT quantity FROM deck_cards WHERE deck_id = ? AND card_id = ?");
        $stmt->execute([$deckId, $cardId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) {
            echo json_encode(['success' => false, 'error' => 'Card not in deck']);
            return;
        }
        
        if ($current['quantity'] <= $quantity) {
            // Remove card entirely
            $stmt = $conn->prepare("DELETE FROM deck_cards WHERE deck_id = ? AND card_id = ?");
            $stmt->execute([$deckId, $cardId]);
        } else {
            // Decrease quantity
            $stmt = $conn->prepare("UPDATE deck_cards SET quantity = quantity - ? WHERE deck_id = ? AND card_id = ?");
            $stmt->execute([$quantity, $deckId, $cardId]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Card removed from deck']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to remove card from deck']);
    }
}

function listArchetypes() {
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("SELECT * FROM deck_archetypes ORDER BY name");
        $stmt->execute();
        $archetypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'archetypes' => $archetypes
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to list archetypes']);
    }
}

function validateDeck() {
    $deckId = intval($_REQUEST['deck_id'] ?? 0);
    $conn = getDBConnection();
    
    try {
        // Get deck info
        $stmt = $conn->prepare("
            SELECT d.*, a.min_cards, a.max_cards, a.max_duplicates
            FROM user_decks d
            LEFT JOIN deck_archetypes a ON d.archetype_id = a.id
            WHERE d.id = ? AND d.user_id = ?
        ");
        $stmt->execute([$deckId, $_SESSION['user_id']]);
        $deck = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deck) {
            echo json_encode(['success' => false, 'error' => 'Deck not found']);
            return;
        }
        
        $minCards = $deck['min_cards'] ?: MIN_DECK_SIZE;
        $maxCards = $deck['max_cards'] ?: MAX_DECK_SIZE;
        $maxDuplicates = $deck['max_duplicates'] ?: MAX_CARD_DUPLICATES;
        
        // Count total cards
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM deck_cards WHERE deck_id = ?");
        $stmt->execute([$deckId]);
        $totalCards = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
        
        $errors = [];
        $warnings = [];
        
        if ($totalCards < $minCards) {
            $errors[] = "Deck has only {$totalCards} cards (minimum: {$minCards})";
        }
        if ($totalCards > $maxCards) {
            $errors[] = "Deck has {$totalCards} cards (maximum: {$maxCards})";
        }
        
        // Check duplicate limits
        $stmt = $conn->prepare("SELECT card_id, quantity FROM deck_cards WHERE deck_id = ? AND quantity > ?");
        $stmt->execute([$deckId, $maxDuplicates]);
        $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($violations as $v) {
            $errors[] = "Card ID {$v['card_id']} has {$v['quantity']} copies (max: {$maxDuplicates})";
        }
        
        $isValid = count($errors) === 0;
        
        echo json_encode([
            'success' => true,
            'is_valid' => $isValid,
            'total_cards' => $totalCards,
            'min_cards' => $minCards,
            'max_cards' => $maxCards,
            'errors' => $errors,
            'warnings' => $warnings
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to validate deck']);
    }
}
?>
