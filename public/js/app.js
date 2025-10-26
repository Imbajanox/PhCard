// Global state
let currentUser = null;
let gameState = null;

// Initialize app
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
});

// Show/Hide screens
function showScreen(screenId) {
    document.querySelectorAll('.screen').forEach(screen => {
        screen.classList.remove('active');
    });
    document.getElementById(screenId).classList.add('active');
}

// Authentication functions
function showLogin() {
    document.getElementById('login-form').classList.add('active');
    document.getElementById('register-form').classList.remove('active');
}

function showRegister() {
    document.getElementById('register-form').classList.add('active');
    document.getElementById('login-form').classList.remove('active');
}

function showMessage(message, isError = false) {
    const messageEl = document.getElementById('auth-message');
    messageEl.textContent = message;
    messageEl.className = 'message ' + (isError ? 'error' : 'success');
}

async function register() {
    const username = document.getElementById('register-username').value;
    const email = document.getElementById('register-email').value;
    const password = document.getElementById('register-password').value;
    
    try {
        const response = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'register', username, email, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.message, false);
            setTimeout(() => {
                loadUserProfile();
                showScreen('menu-screen');
            }, 1000);
        } else {
            showMessage(data.error, true);
        }
    } catch (error) {
        showMessage('Fehler bei der Registrierung', true);
    }
}

async function login() {
    const username = document.getElementById('login-username').value;
    const password = document.getElementById('login-password').value;
    
    try {
        const response = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'login', username, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.message, false);
            setTimeout(() => {
                loadUserProfile();
                showScreen('menu-screen');
            }, 1000);
        } else {
            showMessage(data.error, true);
        }
    } catch (error) {
        showMessage('Fehler beim Login', true);
    }
}

async function logout() {
    try {
        await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'logout' })
        });
        
        currentUser = null;
        showScreen('auth-screen');
    } catch (error) {
        console.error('Logout error:', error);
    }
}

async function checkAuth() {
    try {
        const response = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'check' })
        });
        
        const data = await response.json();
        
        if (data.logged_in) {
            loadUserProfile();
            showScreen('menu-screen');
        } else {
            showScreen('auth-screen');
        }
    } catch (error) {
        showScreen('auth-screen');
    }
}

// User profile functions
async function loadUserProfile() {
    try {
        const response = await fetch('api/user.php?action=profile');
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            updateUserDisplay();
        }
    } catch (error) {
        console.error('Failed to load profile:', error);
    }
}

function updateUserDisplay() {
    if (!currentUser) return;
    
    document.getElementById('username-display').textContent = currentUser.username;
    document.getElementById('level-display').textContent = currentUser.level;
    document.getElementById('wins-display').textContent = currentUser.total_wins;
    document.getElementById('losses-display').textContent = currentUser.total_losses;
    
    // XP bar
    const xpProgress = (currentUser.xp_progress / currentUser.xp_needed) * 100;
    document.getElementById('xp-progress').style.width = xpProgress + '%';
    document.getElementById('xp-current').textContent = currentUser.xp_progress;
    document.getElementById('xp-max').textContent = currentUser.xp_needed;
}

// Card collection
async function showCardCollection() {
    try {
        const response = await fetch('api/user.php?action=cards');
        const data = await response.json();
        
        if (data.success) {
            displayCardCollection(data.cards);
            showScreen('collection-screen');
        }
    } catch (error) {
        console.error('Failed to load cards:', error);
    }
}

function displayCardCollection(cards) {
    const container = document.getElementById('card-collection');
    container.innerHTML = '';
    
    cards.forEach(card => {
        const cardEl = createCardElement(card, true);
        container.appendChild(cardEl);
    });
}

function createCardElement(card, showQuantity = false) {
    const cardEl = document.createElement('div');
    cardEl.className = `card ${card.type}`;
    
    let statsHTML = '';
    if (card.type === 'monster') {
        statsHTML = `<div class="card-stats">ATK: ${card.attack} / DEF: ${card.defense}</div>`;
    } else if (card.effect) {
        const effectParts = card.effect.split(':');
        statsHTML = `<div class="card-stats">Effect: ${effectParts[0]} ${effectParts[1]}</div>`;
    }
    
    cardEl.innerHTML = `
        <div class="card-rarity ${card.rarity}">${card.rarity}</div>
        <div class="card-name">${card.name}</div>
        <div class="card-type">${card.type}</div>
        ${statsHTML}
        <div class="card-description">${card.description || ''}</div>
        ${showQuantity ? `<div style="text-align: center; margin-top: 10px; font-weight: bold;">x${card.quantity}</div>` : ''}
    `;
    
    return cardEl;
}

// Game functions
async function startGame(aiLevel) {
    try {
        const response = await fetch('api/game.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'start', ai_level: aiLevel })
        });
        
        const data = await response.json();
        
        if (data.success) {
            gameState = data.game_state;
            initGameDisplay();
            showScreen('game-screen');
        } else {
            alert('Fehler beim Starten des Spiels: ' + data.error);
        }
    } catch (error) {
        console.error('Failed to start game:', error);
        alert('Fehler beim Starten des Spiels');
    }
}

function initGameDisplay() {
    if (!gameState) return;
    
    // Set AI level
    document.getElementById('ai-level-display').textContent = gameState.ai_level;
    
    // Update HP
    updateHP();
    
    // Display hand
    displayHand();
    
    // Display fields
    displayField('player');
    displayField('ai');
    
    // Update turn counter
    document.getElementById('turn-count').textContent = gameState.turn_count;
    
    // Clear log
    document.getElementById('log-content').innerHTML = '';
    
    addLog('Spiel gestartet! Viel Erfolg!');
}

function updateHP() {
    const maxHP = 2000;
    
    // Player HP
    const playerHPPercent = (gameState.player_hp / maxHP) * 100;
    document.getElementById('player-hp-fill').style.width = playerHPPercent + '%';
    document.getElementById('player-hp-text').textContent = `HP: ${gameState.player_hp} / ${maxHP}`;
    
    // AI HP
    const aiHPPercent = (gameState.ai_hp / maxHP) * 100;
    document.getElementById('ai-hp-fill').style.width = aiHPPercent + '%';
    document.getElementById('ai-hp-text').textContent = `HP: ${gameState.ai_hp} / ${maxHP}`;
}

function displayHand() {
    const handEl = document.getElementById('player-hand');
    handEl.innerHTML = '';
    
    gameState.player_hand.forEach((card, index) => {
        const cardEl = createCardElement(card);
        cardEl.onclick = () => playCard(index);
        handEl.appendChild(cardEl);
    });
}

function displayField(player) {
    const fieldEl = document.getElementById(player + '-field');
    fieldEl.innerHTML = '';
    
    const field = player === 'player' ? gameState.player_field : gameState.ai_field;
    
    field.forEach(card => {
        const cardEl = createCardElement(card);
        cardEl.style.cursor = 'default';
        fieldEl.appendChild(cardEl);
    });
}

async function playCard(cardIndex) {
    if (gameState.turn !== 'player') {
        alert('Nicht deine Runde!');
        return;
    }
    
    try {
        const response = await fetch('api/game.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 
                action: 'play_card', 
                card_index: cardIndex,
                target: 'opponent'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            gameState.player_hp = data.game_state.player_hp;
            gameState.ai_hp = data.game_state.ai_hp;
            gameState.player_hand = data.game_state.player_hand;
            gameState.player_field = data.game_state.player_field;
            gameState.ai_field = data.game_state.ai_field;
            
            updateHP();
            displayHand();
            displayField('player');
            displayField('ai');
            
            addLog(data.message);
        } else {
            alert(data.error);
        }
    } catch (error) {
        console.error('Failed to play card:', error);
    }
}

async function endTurn() {
    if (gameState.turn !== 'player') {
        return;
    }
    
    // Disable button during processing
    document.getElementById('end-turn-btn').disabled = true;
    
    try {
        const response = await fetch('api/game.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'end_turn' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            gameState.player_hp = data.game_state.player_hp;
            gameState.ai_hp = data.game_state.ai_hp;
            gameState.player_hand = data.game_state.player_hand;
            gameState.player_field = data.game_state.player_field;
            gameState.ai_field = data.game_state.ai_field;
            gameState.turn_count = data.game_state.turn_count;
            
            updateHP();
            displayHand();
            displayField('player');
            displayField('ai');
            document.getElementById('turn-count').textContent = gameState.turn_count;
            
            // Add battle log
            data.battle_log.forEach(log => addLog(log));
            
            // Add AI actions
            data.ai_actions.forEach(action => addLog(action, 'ai'));
            
            // Check for game over
            if (data.winner) {
                setTimeout(() => {
                    endGame(data.winner === 'player' ? 'win' : 'loss');
                }, 1000);
            }
        }
        
        document.getElementById('end-turn-btn').disabled = false;
    } catch (error) {
        console.error('Failed to end turn:', error);
        document.getElementById('end-turn-btn').disabled = false;
    }
}

function addLog(message, type = 'normal') {
    const logEl = document.getElementById('log-content');
    const entry = document.createElement('div');
    entry.className = 'log-entry';
    
    if (type === 'ai') {
        entry.style.color = '#dc3545';
    }
    
    entry.textContent = message;
    logEl.appendChild(entry);
    logEl.scrollTop = logEl.scrollHeight;
}

async function endGame(result) {
    try {
        const response = await fetch('api/game.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'end_game', result })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showGameOverModal(result, data);
        }
    } catch (error) {
        console.error('Failed to end game:', error);
    }
}

function showGameOverModal(result, data) {
    const modal = document.getElementById('game-over-modal');
    const resultEl = document.getElementById('game-result');
    const statsEl = document.getElementById('game-stats');
    const unlockedEl = document.getElementById('unlocked-cards');
    
    resultEl.textContent = result === 'win' ? '🎉 Sieg! 🎉' : '💔 Niederlage 💔';
    resultEl.style.color = result === 'win' ? '#28a745' : '#dc3545';
    
    let statsHTML = `<p>XP gewonnen: +${data.xp_gained}</p>`;
    
    if (data.leveled_up) {
        statsHTML += `<p style="color: #28a745; font-weight: bold;">🎊 Level Up! 🎊</p>`;
        statsHTML += `<p>Neues Level: ${data.new_level}</p>`;
    }
    
    statsEl.innerHTML = statsHTML;
    
    if (data.unlocked_cards && data.unlocked_cards.length > 0) {
        let unlockedHTML = '<h3>🎁 Neue Karten freigeschaltet! 🎁</h3>';
        unlockedHTML += '<div class="unlocked-card-list">';
        
        data.unlocked_cards.forEach(card => {
            unlockedHTML += `<div style="font-weight: bold; color: #667eea;">${card.name}</div>`;
        });
        
        unlockedHTML += '</div>';
        unlockedEl.innerHTML = unlockedHTML;
    } else {
        unlockedEl.innerHTML = '';
    }
    
    modal.classList.add('active');
}

function closeGameOver() {
    document.getElementById('game-over-modal').classList.remove('active');
    gameState = null;
    loadUserProfile();
    showScreen('menu-screen');
}
