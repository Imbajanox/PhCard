<?php
require_once '../config.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

// Allow GET requests only for the 'check' action
if (empty($action) && isset($_GET['action']) && $_GET['action'] === 'check') {
    $action = 'check';
}

switch ($action) {
    case 'register':
        register();
        break;
    case 'login':
        login();
        break;
    case 'logout':
        logout();
        break;
    case 'check':
        checkAuth();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function register() {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';
    
    if (empty($username) || empty($password) || empty($email)) {
        echo json_encode(['success' => false, 'error' => 'All fields required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        return;
    }
    
    try {
        // Check if username or email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Username or email already exists']);
            return;
        }
        
        // Create user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
        $stmt->execute([$username, $hashedPassword, $email]);
        $userId = $conn->lastInsertId();
        
        // Give starter cards
        $stmt = $conn->prepare("SELECT id FROM cards WHERE required_level = 1");
        $stmt->execute();
        $starterCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $conn->prepare("INSERT INTO user_cards (user_id, card_id, quantity) VALUES (?, ?, 3)");
        foreach ($starterCards as $card) {
            $stmt->execute([$userId, $card['id']]);
        }
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        
        echo json_encode(['success' => true, 'message' => 'Registration successful']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Registration failed: ' . $e->getMessage()]);
    }
}

function login() {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Username and password required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        return;
    }
    
    try {
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            echo json_encode(['success' => true, 'message' => 'Login successful']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Login failed']);
    }
}

function logout() {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out']);
}

function checkAuth() {
    if (isLoggedIn()) {
        echo json_encode(['success' => true, 'authenticated' => true, 'logged_in' => true, 'user_id' => $_SESSION['user_id'], 'username' => $_SESSION['username']]);
    } else {
        echo json_encode(['success' => true, 'authenticated' => false, 'logged_in' => false]);
    }
}
?>
