/**
 * Multiplayer Game JavaScript
 * Handles multiplayer game lobby, matchmaking, and game UI
 */

let currentMultiplayerGame = null;
let multiplayerGameState = null;
let pollInterval = null;
let isMyTurn = false;

/**
 * Initialize multiplayer UI
 */
function initMultiplayer() {
    // Check if user has an active game
    checkCurrentGame();
    
    // Load available games
    loadAvailableGames();
}

/**
 * Check if user has an active multiplayer game
 */
async function checkCurrentGame() {
    try {
        const response = await fetch('api/multiplayer.php?action=current_game');
        const data = await response.json();
        
        if (data.success && data.game) {
            currentMultiplayerGame = data.game;
            
            if (data.game.status === 'waiting') {
                showWaitingScreen(data.game.id);
            } else if (data.game.status === 'active') {
                // Resume active game
                resumeMultiplayerGame(data.game.id);
            }
        }
    } catch (error) {
        console.error('Failed to check current game:', error);
    }
}

/**
 * Create a new multiplayer game
 */
async function createMultiplayerGame() {
    try {
        const response = await fetch('api/multiplayer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'create_game', deck_id: 0 })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentMultiplayerGame = { id: data.game_id, status: 'waiting' };
            showWaitingScreen(data.game_id);
            addLog('Game room created. Waiting for opponent...');
        } else {
            alert('Error creating game: ' + data.error);
        }
    } catch (error) {
        console.error('Failed to create game:', error);
        alert('Failed to create game');
    }
}

/**
 * Show waiting screen while waiting for opponent
 */
function showWaitingScreen(gameId) {
    const waitingHtml = `
        <div class="waiting-screen">
            <h2>Waiting for Opponent</h2>
            <p>Game ID: ${gameId}</p>
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>Waiting for another player to join...</p>
            </div>
            <button onclick="cancelMultiplayerGame(${gameId})" class="btn-danger">Cancel</button>
        </div>
    `;
    
    document.getElementById('multiplayer-content').innerHTML = waitingHtml;
    
    // Poll for game start
    pollForGameStart(gameId);
}

/**
 * Poll for game start
 */
function pollForGameStart(gameId) {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
    
    pollInterval = setInterval(async () => {
        try {
            const response = await fetch(`api/multiplayer.php?action=get_state&game_id=${gameId}`);
            const data = await response.json();
            
            if (data.success && data.status === 'active') {
                clearInterval(pollInterval);
                startMultiplayerGame(gameId);
            }
        } catch (error) {
            console.error('Failed to poll game state:', error);
        }
    }, 2000);
}

/**
 * Load list of available games
 */
async function loadAvailableGames() {
    try {
        const response = await fetch('api/multiplayer.php?action=list_games');
        const data = await response.json();
        
        if (data.success) {
            displayAvailableGames(data.games);
        }
    } catch (error) {
        console.error('Failed to load games:', error);
    }
}

/**
 * Display available games in the lobby
 */
function displayAvailableGames(games) {
    const gamesListEl = document.getElementById('available-games-list');
    
    if (!games || games.length === 0) {
        gamesListEl.innerHTML = '<p>No games available. Create one!</p>';
        return;
    }
    
    let html = '<div class="games-list">';
    games.forEach(game => {
        const createdTime = new Date(game.created_at).toLocaleTimeString();
        html += `
            <div class="game-item">
                <div class="game-info">
                    <strong>${game.host_username}</strong> (Level ${game.host_level})
                    <br>
                    <small>Created: ${createdTime}</small>
                </div>
                <button onclick="joinMultiplayerGame(${game.id})" class="btn-primary">Join</button>
            </div>
        `;
    });
    html += '</div>';
    
    gamesListEl.innerHTML = html;
}

/**
 * Join a multiplayer game
 */
async function joinMultiplayerGame(gameId) {
    try {
        const response = await fetch('api/multiplayer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'join_game', game_id: gameId, deck_id: 0 })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentMultiplayerGame = { id: gameId, status: 'active' };
            multiplayerGameState = data.game_state;
            startMultiplayerGame(gameId);
        } else {
            alert('Error joining game: ' + data.error);
        }
    } catch (error) {
        console.error('Failed to join game:', error);
        alert('Failed to join game');
    }
}

/**
 * Start/resume a multiplayer game
 */
function startMultiplayerGame(gameId) {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
    
    // Clear battle log when starting a new game
    const logEl = document.getElementById('log-content');
    if (logEl) {
        logEl.innerHTML = '';
    }
    
    showScreen('multiplayer-game-screen');
    loadMultiplayerGameState(gameId);
    
    // Poll for game updates
    pollInterval = setInterval(() => {
        loadMultiplayerGameState(gameId);
    }, 3000);
}

/**
 * Resume an active multiplayer game
 */
function resumeMultiplayerGame(gameId) {
    startMultiplayerGame(gameId);
}

/**
 * Load multiplayer game state
 */
async function loadMultiplayerGameState(gameId) {
    try {
        const response = await fetch(`api/multiplayer.php?action=get_state&game_id=${gameId}`);
        const data = await response.json();
        
        if (data.success) {
            if (data.status === 'finished') {
                clearInterval(pollInterval);
                showMultiplayerGameEnd(data.winner_id, data.rewards);
                return;
            }
            
            multiplayerGameState = data.game_state;
            isMyTurn = data.is_your_turn;
            
            updateMultiplayerGameDisplay();
        }
    } catch (error) {
        console.error('Failed to load game state:', error);
    }
}

/**
 * Update multiplayer game display
 */
function updateMultiplayerGameDisplay() {
    if (!multiplayerGameState) return;
    
    // Determine if current user is player1 or player2
    const isPlayer1 = (multiplayerGameState.player1_id === currentUserId);
    const playerKey = isPlayer1 ? 'player1' : 'player2';
    const opponentKey = isPlayer1 ? 'player2' : 'player1';
    
    // Update HP
    document.getElementById('mp-player-hp').textContent = multiplayerGameState[playerKey + '_hp'];
    document.getElementById('mp-opponent-hp').textContent = multiplayerGameState[opponentKey + '_hp'];
    
    // Update Mana
    document.getElementById('mp-player-mana').textContent = 
        `${multiplayerGameState[playerKey + '_mana']} / ${multiplayerGameState[playerKey + '_max_mana']}`;
    
    // Update hand
    displayMultiplayerHand(playerKey);
    
    // Update fields
    displayMultiplayerField(playerKey, 'mp-player-field');
    displayMultiplayerField(opponentKey, 'mp-opponent-field');
    
    // Update turn indicator
    const turnIndicator = document.getElementById('mp-turn-indicator');
    if (isMyTurn) {
        turnIndicator.textContent = 'Your Turn';
        turnIndicator.className = 'turn-indicator your-turn';
        document.getElementById('mp-end-turn-btn').disabled = false;
    } else {
        turnIndicator.textContent = "Opponent's Turn";
        turnIndicator.className = 'turn-indicator opponent-turn';
        document.getElementById('mp-end-turn-btn').disabled = true;
    }
}

/**
 * Display multiplayer hand
 */
function displayMultiplayerHand(playerKey) {
    const handEl = document.getElementById('mp-player-hand');
    handEl.innerHTML = '';
    
    const hand = multiplayerGameState[playerKey + '_hand'];
    
    hand.forEach((card, index) => {
        if (card.hidden) {
            return; // Skip hidden cards
        }
        
        const cardEl = createCardElement(card);
        cardEl.onclick = () => {
            if (isMyTurn) {
                playMultiplayerCard(index);
            }
        };
        handEl.appendChild(cardEl);
    });
}

/**
 * Display multiplayer field
 */
function displayMultiplayerField(playerKey, elementId) {
    const fieldEl = document.getElementById(elementId);
    fieldEl.innerHTML = '';
    
    const field = multiplayerGameState[playerKey + '_field'];
    
    field.forEach(card => {
        const cardEl = createFieldCardElement(card);
        fieldEl.appendChild(cardEl);
    });
}

/**
 * Play a card in multiplayer game
 */
async function playMultiplayerCard(cardIndex) {
    if (!isMyTurn) {
        addLog('Not your turn!');
        return;
    }
    
    try {
        const response = await fetch('api/multiplayer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'play_card',
                game_id: currentMultiplayerGame.id,
                card_index: cardIndex,
                target: 'opponent'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            addLog(data.message);
            multiplayerGameState = data.game_state;
            updateMultiplayerGameDisplay();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Failed to play card:', error);
        alert('Failed to play card');
    }
}

/**
 * End turn in multiplayer game
 */
async function endMultiplayerTurn() {
    if (!isMyTurn) {
        return;
    }
    
    try {
        const response = await fetch('api/multiplayer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'end_turn',
                game_id: currentMultiplayerGame.id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Display battle log
            if (data.battle_log) {
                data.battle_log.forEach(log => addLog(log));
            }
            
            multiplayerGameState = data.game_state;
            isMyTurn = false;
            updateMultiplayerGameDisplay();
            
            if (data.status === 'finished') {
                clearInterval(pollInterval);
                showMultiplayerGameEnd(data.winner, data.rewards);
            }
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Failed to end turn:', error);
        alert('Failed to end turn');
    }
}

/**
 * Surrender multiplayer game
 */
async function surrenderMultiplayerGame() {
    if (!confirm('Are you sure you want to surrender?')) {
        return;
    }
    
    try {
        const response = await fetch('api/multiplayer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'surrender',
                game_id: currentMultiplayerGame.id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            clearInterval(pollInterval);
            showMultiplayerGameEnd(data.winner_id, data.rewards);
        }
    } catch (error) {
        console.error('Failed to surrender:', error);
    }
}

/**
 * Show multiplayer game end screen
 */
function showMultiplayerGameEnd(winnerId, rewards) {
    const isWinner = (winnerId === currentUserId);
    const isDraw = (winnerId === 'draw');
    
    let message;
    let messageColor;
    if (isDraw) {
        message = "🤝 It's a draw! 🤝";
        messageColor = '#ffc107';
    } else if (isWinner) {
        message = '🎉 You won! 🎉';
        messageColor = '#28a745';
    } else {
        message = '💔 You lost! 💔';
        messageColor = '#dc3545';
    }
    
    let rewardsHTML = '';
    if (rewards) {
        rewardsHTML = `
            <div class="rewards-section">
                <h3>Rewards</h3>
                <p>XP gained: +${rewards.xp_gained}</p>
                <p>Coins earned: +${rewards.coins_earned} 💰</p>
                ${rewards.gems_earned > 0 ? `<p>Gems earned: +${rewards.gems_earned} 💎</p>` : ''}
                ${rewards.leveled_up ? `
                    <p style="color: #28a745; font-weight: bold;">🎊 Level Up! 🎊</p>
                    <p>New Level: ${rewards.new_level}</p>
                ` : ''}
                ${rewards.unlocked_cards && rewards.unlocked_cards.length > 0 ? `
                    <div class="unlocked-cards">
                        <h4>🎁 New Cards Unlocked! 🎁</h4>
                        ${rewards.unlocked_cards.map(card => `<div style="font-weight: bold; color: #667eea;">${card.name}</div>`).join('')}
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    const modalHTML = `
        <div class="game-over-modal" style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        ">
            <div class="modal-content" style="
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 40px;
                border-radius: 15px;
                text-align: center;
                max-width: 500px;
                color: white;
            ">
                <h2 style="color: ${messageColor}; font-size: 2em; margin-bottom: 20px;">${message}</h2>
                ${rewardsHTML}
                <button onclick="closeMultiplayerGameOver()" class="btn-primary" style="margin-top: 20px;">Return to Lobby</button>
            </div>
        </div>
    `;
    
    const modalDiv = document.createElement('div');
    modalDiv.id = 'mp-game-over-modal';
    modalDiv.innerHTML = modalHTML;
    document.body.appendChild(modalDiv);
}

/**
 * Close multiplayer game over modal
 */
function closeMultiplayerGameOver() {
    const modal = document.getElementById('mp-game-over-modal');
    if (modal) {
        modal.remove();
    }
    
    currentMultiplayerGame = null;
    multiplayerGameState = null;
    
    // Return to lobby
    showMultiplayerLobby();
}

/**
 * Cancel/leave multiplayer game
 */
async function cancelMultiplayerGame(gameId) {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
    
    currentMultiplayerGame = null;
    showMultiplayerLobby();
}

/**
 * Show multiplayer lobby
 */
function showMultiplayerLobby() {
    showScreen('multiplayer-lobby-screen');
    loadAvailableGames();
}

/**
 * Create a card element for display
 */
function createCardElement(card) {
    const cardEl = document.createElement('div');
    cardEl.className = 'card ' + card.type;
    
    // Add mana cost badge
    const manaCost = card.mana_cost || 1;
    const manaBadge = `<div class="card-mana-cost">${manaCost}</div>`;
    
    // Add rarity badge
    const rarity = card.rarity || 'common';
    const rarityBadge = `<div class="card-rarity ${rarity}">${rarity}</div>`;
    
    let statsHTML = '';
    let descriptionHTML = card.description ? `<div class="card-description">${card.description}</div>` : '';
    
    if (card.type === 'monster') {
        statsHTML = `
            <div class="card-stats">
                <span class="stat-atk">⚔️ ${card.attack}</span>
                <span class="stat-hp">🛡️ ${card.defense || card.health}</span>
            </div>
        `;
    } else if (card.type === 'spell') {
        if (card.effect) {
            const parts = card.effect.split(/:(.*)/s);
            if (parts.length >= 2) {
                statsHTML = `<div class="card-stats">Effect: <strong>${parts[0].trim()}</strong>: ${parts[1].trim()}</div>`;
            } else {
                statsHTML = `<div class="card-stats">Effect: ${card.effect}</div>`;
            }
        }
    }
    
    // Display keywords and status effects
    let effectsHTML = '';
    const allEffects = [];
    
    if (card.keywords) {
        card.keywords.split(',').map(k => k.trim()).forEach(k => allEffects.push({ type: 'keyword', value: k }));
    }
    
    if (card.status_effects && card.status_effects.length > 0) {
        card.status_effects.forEach(s => allEffects.push({ type: 'status', value: s }));
    }
    
    if (allEffects.length > 0) {
        effectsHTML = `<div class="card-effects">${allEffects.map(item =>
            `<span class="${item.type} ${item.type}-${item.value.toLowerCase()}">${item.value}</span>`
        ).join(' ')}</div>`;
    }
    
    // Overload indicator
    let overloadHTML = '';
    if (card.overload && card.overload > 0) {
        overloadHTML = `<div class="card-overload">Overload: ${card.overload}</div>`;
    }
    
    cardEl.innerHTML = `
        ${manaBadge}
        ${rarityBadge}
        <div class="card-name">${card.name}</div>
        <div class="card-type">${card.type.toUpperCase()}</div>
        ${statsHTML}
        ${overloadHTML}
        ${effectsHTML}
        ${descriptionHTML}
    `;
    
    return cardEl;
}

/**
 * Create a field card element
 */
function createFieldCardElement(card) {
    const cardEl = document.createElement('div');
    cardEl.className = 'field-card ' + card.type;
    
    // Add mana cost badge
    const manaCost = card.mana_cost || 1;
    const manaBadge = `<div class="card-mana-cost">${manaCost}</div>`;
    
    // Add rarity badge (smaller for field cards)
    const rarity = card.rarity || 'common';
    const rarityBadge = `<div class="card-rarity ${rarity}">${rarity}</div>`;
    
    let content = `
        ${manaBadge}
        ${rarityBadge}
        <div class="card-name">${card.name}</div>
    `;
    
    if (card.type === 'monster') {
        const currentHP = card.current_health !== undefined ? card.current_health : card.max_health;
        const maxHP = card.max_health || card.health || card.defense;
        content += `
            <div class="card-stats">
                <span class="attack">⚔️ ${card.attack}</span>
                <span class="health">❤️ ${currentHP}/${maxHP}</span>
            </div>
        `;
    }
    
    cardEl.innerHTML = content;
    return cardEl;
}
