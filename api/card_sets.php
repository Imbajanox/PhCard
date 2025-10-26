<?php
/**
 * Card Set Management API
 * 
 * Manages card sets/expansions for organizing game content
 */
require_once '../config.php';

header('Content-Type: application/json');
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list_sets':
        listCardSets();
        break;
    case 'get_set':
        getCardSet();
        break;
    case 'get_set_cards':
        getSetCards();
        break;
    case 'create_set':
        createCardSet();
        break;
    case 'update_set':
        updateCardSet();
        break;
    case 'delete_set':
        deleteCardSet();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function listCardSets() {
    $conn = getDBConnection();
    $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === 'true';
    
    $sql = "SELECT cs.*, COUNT(csm.card_id) as card_count
            FROM card_sets cs
            LEFT JOIN card_set_members csm ON cs.id = csm.set_id";
    
    if ($activeOnly) {
        $sql .= " WHERE cs.is_active = true";
    }
    
    $sql .= " GROUP BY cs.id ORDER BY cs.release_date DESC";
    
    $stmt = $conn->query($sql);
    $sets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'sets' => $sets]);
}

function getCardSet() {
    $setId = intval($_GET['set_id'] ?? 0);
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT cs.*, COUNT(csm.card_id) as card_count
        FROM card_sets cs
        LEFT JOIN card_set_members csm ON cs.id = csm.set_id
        WHERE cs.id = ?
        GROUP BY cs.id
    ");
    $stmt->execute([$setId]);
    $set = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$set) {
        echo json_encode(['success' => false, 'error' => 'Card set not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'set' => $set]);
}

function getSetCards() {
    $setId = intval($_GET['set_id'] ?? 0);
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT c.*, csm.set_number
        FROM cards c
        JOIN card_set_members csm ON c.id = csm.card_id
        WHERE csm.set_id = ?
        ORDER BY csm.set_number
    ");
    $stmt->execute([$setId]);
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'cards' => $cards]);
}

function createCardSet() {
    requireAdmin(); // Only admins can create sets
    
    $name = $_POST['name'] ?? '';
    $code = $_POST['code'] ?? '';
    $description = $_POST['description'] ?? '';
    $setType = $_POST['set_type'] ?? 'expansion';
    $icon = $_POST['icon'] ?? '';
    
    if (empty($name) || empty($code)) {
        echo json_encode(['success' => false, 'error' => 'Name and code are required']);
        return;
    }
    
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO card_sets (name, code, description, set_type, icon)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $code, $description, $setType, $icon]);
        
        $setId = $conn->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'set_id' => $setId,
            'message' => 'Card set created successfully'
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            echo json_encode(['success' => false, 'error' => 'Card set code already exists']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }
}

function updateCardSet() {
    requireAdmin();
    
    $setId = intval($_POST['set_id'] ?? 0);
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $isActive = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;
    $icon = $_POST['icon'] ?? '';
    
    if ($setId <= 0 || empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        return;
    }
    
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("
            UPDATE card_sets 
            SET name = ?, description = ?, is_active = ?, icon = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $isActive, $icon, $setId]);
        
        echo json_encode(['success' => true, 'message' => 'Card set updated']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

function deleteCardSet() {
    requireAdmin();
    
    $setId = intval($_POST['set_id'] ?? 0);
    
    if ($setId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid set ID']);
        return;
    }
    
    $conn = getDBConnection();
    
    try {
        // Check if set has cards
        $stmt = $conn->prepare("SELECT COUNT(*) FROM card_set_members WHERE set_id = ?");
        $stmt->execute([$setId]);
        $cardCount = $stmt->fetchColumn();
        
        if ($cardCount > 0) {
            echo json_encode([
                'success' => false,
                'error' => "Cannot delete set with $cardCount cards. Remove cards first."
            ]);
            return;
        }
        
        $stmt = $conn->prepare("DELETE FROM card_sets WHERE id = ?");
        $stmt->execute([$setId]);
        
        echo json_encode(['success' => true, 'message' => 'Card set deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}
?>
