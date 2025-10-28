// Global state
let currentUser = null;
let gameState = null;

// Initialize app
document.addEventListener('DOMContentLoaded', () => {
    //console.log("hallo");
    checkAuth();
    loadLeaderboard(); // Load leaderboard on page load
});

// Show/Hide screens
function showScreen(screenId) {
    document.querySelectorAll('.screen').forEach(screen => {
        screen.classList.remove('active');
    });
    document.getElementById(screenId).classList.add('active');
}

// Authentication functions
function showAuthScreen(formType) {
    showScreen('auth-screen');
    if (formType === 'register') {
        showRegister();
    } else {
        showLogin();
    }
}

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
        showScreen('landing-screen');
        loadLeaderboard(); // Reload leaderboard when user logs out
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
        //console.log(data);
        if (data.logged_in) {
            loadUserProfile();
            showScreen('menu-screen');
        } else {
            showScreen('landing-screen');
        }
    } catch (error) {
        showScreen('landing-screen');
    }
}

// Leaderboard functions
async function loadLeaderboard() {
    const leaderboardContent = document.getElementById('leaderboard-content');
    
    try {
        const response = await fetch('api/leaderboard.php?action=get');
        const data = await response.json();
        
        if (data.success && data.leaderboard && data.leaderboard.length > 0) {
            displayLeaderboard(data.leaderboard);
        } else {
            leaderboardContent.innerHTML = '<p class="leaderboard-empty">Keine Spieler in der Bestenliste</p>';
        }
    } catch (error) {
        console.error('Failed to load leaderboard:', error);
        leaderboardContent.innerHTML = '<p class="leaderboard-error">Fehler beim Laden der Bestenliste</p>';
    }
}

function displayLeaderboard(players) {
    const leaderboardContent = document.getElementById('leaderboard-content');
    
    let html = '<table><thead><tr>';
    html += '<th>Rang</th>';
    html += '<th>Spieler</th>';
    html += '<th>Level</th>';
    html += '<th>XP</th>';
    html += '<th>Siege</th>';
    html += '<th>Spiele</th>';
    html += '<th>Siegrate</th>';
    html += '</tr></thead><tbody>';
    
    players.forEach((player, index) => {
        const rank = index + 1;
        let rankClass = 'leaderboard-rank';
        if (rank === 1) rankClass += ' leaderboard-rank-1';
        else if (rank === 2) rankClass += ' leaderboard-rank-2';
        else if (rank === 3) rankClass += ' leaderboard-rank-3';
        
        html += '<tr>';
        html += `<td class="${rankClass}">#${rank}</td>`;
        html += `<td><strong>${escapeHtml(player.username)}</strong></td>`;
        html += `<td>${player.level}</td>`;
        html += `<td>${player.xp}</td>`;
        html += `<td>${player.total_wins}</td>`;
        html += `<td>${player.total_games}</td>`;
        html += `<td>${player.win_rate}%</td>`;
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    leaderboardContent.innerHTML = html;
}

function escapeHtml(text) {
    const escapeMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, char => escapeMap[char]);
}

// User profile functions
async function loadUserProfile() {
    try {
        const response = await fetch('api/user.php?action=profile');
        const data = await response.json();

        if (data.success) {
            currentUser = data.user;
            updateUserDisplay();
        } else {
            console.error('Failed to load user profile:', data);
            // Show a user-facing message and return to the auth screen to avoid running with a broken state
            // showMessage(data.error || 'Fehler beim Laden des Profils', true);
            // currentUser = null;
            // showScreen('auth-screen');
        }
    } catch (error) {
        console.error('Failed to load profile:', error);
        // showMessage('Fehler beim Laden des Profils', true);
        // currentUser = null;
        // showScreen('auth-screen');
    }
}

function updateUserDisplay() {
    console.log(currentUser);
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
    
    // Show/hide analytics button based on admin status
    const analyticsBtn = document.getElementById('analytics-btn');
    if (analyticsBtn) {
        analyticsBtn.style.display = currentUser.is_admin ? 'block' : 'none';
    }
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

    // Add mana cost badge
    const manaCost = card.mana_cost || 1;
    const manaBadge = `<div class="card-mana-cost">${manaCost}</div>`;

    let statsHTML = '';
    let descriptionHTML = `<div class="card-description">${card.description || ''}</div>`; // Move description here

    if (card.type === 'monster') {
        // Display ATK and HP (current_health if available, otherwise health or defense)
        const currentHP = card.current_health !== undefined ? card.current_health : (card.health || card.defense);
        const maxHP = card.max_health !== undefined ? card.max_health : (card.health || card.defense);
        statsHTML = `<div class="card-stats">
            <span class="stat-atk">ATK: ${card.attack}</span>
            <span class="stat-hp">HP: ${currentHP}${maxHP !== currentHP ? '/' + maxHP : ''}</span>
        </div>`;
    } else if (card.type === 'spell') {
        // Updated logic for spell cards to match image, showing effect in stats area
        if (card.effect) {
             // Split only on the first colon for effect: value
            const parts = card.effect.split(/:(.*)/s); // Splits into [key, value]
            if (parts.length >= 2) {
                statsHTML = `<div class="card-stats">Effect: <strong>${parts[0].trim()}</strong>: ${parts[1].trim()}</div>`;
            } else {
                 statsHTML = `<div class="card-stats">Effect: ${card.effect}</div>`;
            }
        }
    }

    // Display keywords and status effects together
    let effectsHTML = '';
    const allEffects = [];

    // Add keywords
    if (card.keywords) {
        card.keywords.split(',').map(k => k.trim()).forEach(k => allEffects.push({ type: 'keyword', value: k }));
    }

    // Add status effects
    if (card.status_effects && card.status_effects.length > 0) {
        card.status_effects.map(s => s.trim()).forEach(s => allEffects.push({ type: 'status', value: s }));
    }

    if (allEffects.length > 0) {
        effectsHTML = `<div class="card-effects">${allEffects.map(item =>
            `<span class="${item.type} ${item.type}-${item.value.toLowerCase()}">${item.value}</span>`
        ).join(' ')}</div>`;
    }

    // Overload indicator (moved above description)
    let overloadHTML = '';
    if (card.overload && card.overload > 0) {
        overloadHTML = `<div class="card-overload">Overload: ${card.overload}</div>`;
    }

    cardEl.innerHTML = `
        ${manaBadge}
        <div class="card-rarity ${card.rarity}">${card.rarity}</div>
        <div class="card-name">${card.name}</div>
        <div class="card-type">${card.type.toUpperCase()}</div>
        ${statsHTML}
        ${overloadHTML}
        ${effectsHTML}
        ${descriptionHTML}
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
            console.log(data);
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
    
    // Update Mana
    updateMana();
    
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
    
    // Show mulligan option if available
    if (gameState.mulligan_available) {
        showMulliganOption();
    }
}

function updateMana() {
    if (!gameState) return;
    
    // Player mana
    const manaDisplay = document.getElementById('player-mana-display');
    if (manaDisplay) {
        manaDisplay.textContent = `Mana: ${gameState.player_mana || 0} / ${gameState.player_max_mana || 1}`;
    }
}

function showMulliganOption() {
    const message = 'Möchten Sie einige Karten tauschen? (Mulligan)\nWählen Sie bis zu 3 Karten aus Ihrer Hand.';
    if (confirm(message)) {
        // Show mulligan interface
        const handEl = document.getElementById('player-hand');
        const cards = handEl.querySelectorAll('.card');
        let selectedCards = [];
        
        // Make cards selectable
        cards.forEach((cardEl, index) => {
            cardEl.style.border = '2px solid #333';
            cardEl.onclick = () => {
                if (selectedCards.includes(index)) {
                    selectedCards = selectedCards.filter(i => i !== index);
                    cardEl.style.border = '2px solid #333';
                } else if (selectedCards.length < 3) {
                    selectedCards.push(index);
                    cardEl.style.border = '3px solid #ff0';
                }
            };
        });
        
        // Add confirm button
        const confirmBtn = document.createElement('button');
        confirmBtn.textContent = 'Mulligan bestätigen';
        confirmBtn.style.margin = '10px';
        confirmBtn.onclick = () => performMulligan(selectedCards);
        handEl.appendChild(confirmBtn);
        
        // Add skip button
        const skipBtn = document.createElement('button');
        skipBtn.textContent = 'Überspringen';
        skipBtn.style.margin = '10px';
        skipBtn.onclick = () => {
            handEl.querySelectorAll('button').forEach(btn => btn.remove());
            displayHand(); // Reset hand display
        };
        handEl.appendChild(skipBtn);
    }
}

async function performMulligan(cardIndices) {
    try {
        const response = await fetch('api/game.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 
                action: 'mulligan', 
                card_indices: JSON.stringify(cardIndices) 
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            gameState.player_hand = data.game_state.player_hand;
            gameState.mulligan_available = data.game_state.mulligan_available;
            addLog(data.message);
            displayHand();
        } else {
            alert('Fehler beim Mulligan: ' + data.error);
        }
    } catch (error) {
        console.error('Failed to perform mulligan:', error);
    }
}

function updateHP() {
    const maxHP = 2000;
    
    // Store previous values to determine if damage or healing occurred
    const prevPlayerHP = parseInt(document.getElementById('player-hp-text').textContent.split(':')[1].split('/')[0].trim()) || gameState.player_hp;
    const prevAIHP = parseInt(document.getElementById('ai-hp-text').textContent.split(':')[1].split('/')[0].trim()) || gameState.ai_hp;
    
    // Player HP - ensure minimum display of 0
    const displayPlayerHP = Math.max(0, gameState.player_hp);
    const playerHPPercent = (displayPlayerHP / maxHP) * 100;
    document.getElementById('player-hp-fill').style.width = playerHPPercent + '%';
    document.getElementById('player-hp-text').textContent = `HP: ${displayPlayerHP} / ${maxHP}`;
    
    // Visual feedback for player HP change
    if (prevPlayerHP > gameState.player_hp) {
        const damage = prevPlayerHP - gameState.player_hp;
        flashHPBar('player', false);
        showDamageNumber(document.getElementById('player-hp-text'), damage);
    } else if (prevPlayerHP < gameState.player_hp) {
        const heal = gameState.player_hp - prevPlayerHP;
        flashHPBar('player', true);
        showHealNumber(document.getElementById('player-hp-text'), heal);
    }
    
    // AI HP - ensure minimum display of 0
    const displayAIHP = Math.max(0, gameState.ai_hp);
    const aiHPPercent = (displayAIHP / maxHP) * 100;
    document.getElementById('ai-hp-fill').style.width = aiHPPercent + '%';
    document.getElementById('ai-hp-text').textContent = `HP: ${displayAIHP} / ${maxHP}`;
    
    // Visual feedback for AI HP change
    if (prevAIHP > gameState.ai_hp) {
        const damage = prevAIHP - gameState.ai_hp;
        flashHPBar('ai', false);
        showDamageNumber(document.getElementById('ai-hp-text'), damage);
    } else if (prevAIHP < gameState.ai_hp) {
        const heal = gameState.ai_hp - prevAIHP;
        flashHPBar('ai', true);
        showHealNumber(document.getElementById('ai-hp-text'), heal);
    }
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

    const card = gameState.player_hand[cardIndex];
    if (!card) {
        alert('Ungültige Karte!');
        return;
    }
    
    // Check if player has enough mana
    const manaCost = card.mana_cost || 1;
    if ((gameState.player_mana || 0) < manaCost) {
        alert(`Nicht genug Mana! Benötigt: ${manaCost}, Verfügbar: ${gameState.player_mana || 0}`);
        return;
    }
    
    // Handle Choose One cards
    let choice = 0;
    if (card.choice_effects) {
        try {
            const choices = JSON.parse(card.choice_effects);
            if (choices.choices && choices.choices.length > 1) {
                const choiceMsg = choices.choices.map((c, i) => `${i}: ${c.name}`).join('\n');
                const selectedChoice = prompt(`Wähle eine Option:\n${choiceMsg}`);
                choice = parseInt(selectedChoice) || 0;
            }
        } catch (e) {
            console.error('Failed to parse choice effects', e);
        }
    }

    let targetToSend = 'opponent';

    if (card.type === 'spell') {
        if (card.effect && card.effect.startsWith('heal:')) {
            targetToSend = 'self';
        }
    }

    // Disable input during animation
    const handEl = document.getElementById('player-hand');
    const originalPointerEvents = handEl.style.pointerEvents;
    handEl.style.pointerEvents = 'none';

    try {
        const response = await fetch('api/game.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 
                action: 'play_card', 
                card_index: cardIndex,
                target: targetToSend,
                choice: choice
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Visual feedback: highlight card being played before removing it
            const cardElements = handEl.querySelectorAll('.card');
            if (cardElements[cardIndex]) {
                highlightCard(cardElements[cardIndex]);
            }
            
            // Update game state after brief delay for visual feedback
            setTimeout(() => {
                gameState.player_hp = data.game_state.player_hp;
                gameState.ai_hp = data.game_state.ai_hp;
                gameState.player_mana = data.game_state.player_mana;
                gameState.player_hand = data.game_state.player_hand;
                gameState.player_field = data.game_state.player_field;
                gameState.ai_field = data.game_state.ai_field;
                
                updateHP();
                updateMana();
                displayHand();
                displayField('player');
                displayField('ai');
                
                addLog(data.message);
                
                // Re-enable input after animation
                handEl.style.pointerEvents = originalPointerEvents;
            }, 300);
        } else {
            // Re-enable input on error
            handEl.style.pointerEvents = originalPointerEvents;
            alert(data.error);
        }
    } catch (error) {
        // Re-enable input on error
        handEl.style.pointerEvents = originalPointerEvents;
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
            gameState.player_mana = data.game_state.player_mana;
            gameState.player_max_mana = data.game_state.player_max_mana;
            gameState.player_hand = data.game_state.player_hand;
            gameState.player_field = data.game_state.player_field;
            gameState.ai_field = data.game_state.ai_field;
            gameState.turn_count = data.game_state.turn_count;
            
            updateHP();
            updateMana();
            displayHand();
            displayField('player');
            displayField('ai');
            document.getElementById('turn-count').textContent = gameState.turn_count;
            console.log(data.game_state.player_hand);
            // Add battle log with delay for readability
            let delay = 0;
            data.battle_log.forEach((log, index) => {
                setTimeout(() => addLog(log), delay);
                delay += 200; // 200ms between each log entry
            });
            
            // Add AI actions with delay
            data.ai_actions.forEach((action, index) => {
                setTimeout(() => addLog(action, 'ai'), delay);
                delay += 300; // 300ms between AI actions for better visibility
            });
            
            // Check for game over
            if (data.winner) {
                setTimeout(() => {
                    if (data.winner === 'draw') {
                        endGame('draw');
                    } else {
                        endGame(data.winner === 'player' ? 'win' : 'loss');
                    }
                }, delay + 500); // Wait for all logs to appear
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
    
    // Add visual classification based on message content
    if (message.includes('damage') || message.includes('attacks') || message.includes('destroyed')) {
        entry.classList.add('damage');
    } else if (message.includes('heal') || message.includes('Healed')) {
        entry.classList.add('heal');
    } else if (message.includes('Boost') || message.includes('shield') || message.includes('Stun') || message.includes('Poison')) {
        entry.classList.add('effect');
    }
    
    if (type === 'ai') {
        entry.style.color = '#dc3545';
    }
    
    entry.textContent = message;
    logEl.appendChild(entry);
    logEl.scrollTop = logEl.scrollHeight;
}

// Visual feedback functions
function showDamageNumber(targetEl, amount) {
    const numberEl = document.createElement('div');
    numberEl.className = 'damage-number';
    numberEl.textContent = `-${amount}`;
    
    const rect = targetEl.getBoundingClientRect();
    numberEl.style.left = (rect.left + rect.width / 2) + 'px';
    numberEl.style.top = (rect.top + rect.height / 2) + 'px';
    
    document.body.appendChild(numberEl);
    
    setTimeout(() => {
        numberEl.remove();
    }, 1000);
}

function showHealNumber(targetEl, amount) {
    const numberEl = document.createElement('div');
    numberEl.className = 'heal-number';
    numberEl.textContent = `+${amount}`;
    
    const rect = targetEl.getBoundingClientRect();
    numberEl.style.left = (rect.left + rect.width / 2) + 'px';
    numberEl.style.top = (rect.top + rect.height / 2) + 'px';
    
    document.body.appendChild(numberEl);
    
    setTimeout(() => {
        numberEl.remove();
    }, 1000);
}

function highlightCard(cardEl) {
    cardEl.classList.add('card-highlight');
    setTimeout(() => {
        cardEl.classList.remove('card-highlight');
    }, 600);
}

function animateAttack(cardEl) {
    cardEl.classList.add('card-attacking');
    setTimeout(() => {
        cardEl.classList.remove('card-attacking');
    }, 500);
}

function flashHPBar(playerId, isHeal = false) {
    const hpFillEl = document.getElementById(playerId + '-hp-fill');
    if (hpFillEl) {
        hpFillEl.classList.add(isHeal ? 'hp-heal-flash' : 'hp-damage-flash');
        setTimeout(() => {
            hpFillEl.classList.remove(isHeal ? 'hp-heal-flash' : 'hp-damage-flash');
        }, 500);
    }
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
    
    if (result === 'draw') {
        resultEl.textContent = '🤝 Unentschieden! 🤝';
        resultEl.style.color = '#ffc107';
    } else {
        resultEl.textContent = result === 'win' ? '🎉 Sieg! 🎉' : '💔 Niederlage 💔';
        resultEl.style.color = result === 'win' ? '#28a745' : '#dc3545';
    }
    
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

// Deck Builder functionality
let currentDeck = null;
let allUserCards = [];
let allDecks = [];

async function loadDeckBuilder() {
    try {
        // Load user's cards
        const cardsResponse = await fetch('api/user.php?action=cards');
        const cardsData = await cardsResponse.json();
        if (cardsData.success) {
            allUserCards = cardsData.cards;
        }
        
        // Load user's decks
        await loadUserDecks();
        
        showScreen('deck-builder-screen');
    } catch (error) {
        console.error('Failed to load deck builder:', error);
        alert('Fehler beim Laden des Deck Builders');
    }
}

async function loadUserDecks() {
    try {
        const response = await fetch('api/deck.php?action=list');
        const data = await response.json();
        
        if (data.success) {
            allDecks = data.decks;
            displayDeckList();
        }
    } catch (error) {
        console.error('Failed to load decks:', error);
    }
}

function displayDeckList() {
    const container = document.getElementById('deck-list');
    container.innerHTML = '';
    
    if (allDecks.length === 0) {
        container.innerHTML = '<p style="color: #ccc; font-size: 0.9rem;">Noch keine Decks erstellt</p>';
        return;
    }
    
    allDecks.forEach(deck => {
        const item = document.createElement('div');
        item.className = 'deck-item';
        if (currentDeck && currentDeck.id === deck.id) {
            item.classList.add('active');
        }
        
        item.innerHTML = `
            <span class="deck-item-name">${deck.name}</span>
            <span class="deck-item-info">${deck.total_cards || 0} Karten${deck.is_active ? ' ⭐' : ''}</span>
        `;
        
        item.onclick = () => loadDeck(deck.id);
        container.appendChild(item);
    });
}

async function createNewDeck() {
    const name = prompt('Deck Name:');
    if (!name) return;
    
    try {
        const response = await fetch('api/deck.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 
                action: 'create', 
                name: name,
                card_class: 'neutral'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadUserDecks();
            loadDeck(data.deck_id);
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
    } catch (error) {
        console.error('Failed to create deck:', error);
        alert('Fehler beim Erstellen des Decks');
    }
}

async function loadDeck(deckId) {
    try {
        const response = await fetch(`api/deck.php?action=get_deck&deck_id=${deckId}`);
        const data = await response.json();
        
        if (data.success) {
            currentDeck = data.deck;
            displayDeckEditor();
            displayDeckList();
        } else {
            alert('Fehler: ' + (data.error || 'Deck nicht gefunden'));
        }
    } catch (error) {
        console.error('Failed to load deck:', error);
        alert('Fehler beim Laden des Decks');
    }
}

function displayDeckEditor() {
    document.getElementById('deck-editor-empty').style.display = 'none';
    document.getElementById('deck-editor-content').style.display = 'block';
    
    // Set deck info
    document.getElementById('deck-name-input').value = currentDeck.name;
    document.getElementById('deck-class-select').value = currentDeck.card_class || 'neutral';
    
    // Calculate total cards
    const totalCards = currentDeck.cards.reduce((sum, card) => sum + parseInt(card.quantity), 0);
    document.getElementById('deck-card-count').textContent = totalCards;
    document.getElementById('deck-cards-count').textContent = totalCards;
    
    // Validate deck
    if (totalCards < 30) {
        document.getElementById('deck-validation-message').textContent = '⚠️ Deck muss 30 Karten haben';
    } else if (totalCards > 30) {
        document.getElementById('deck-validation-message').textContent = '⚠️ Deck hat zu viele Karten';
    } else {
        document.getElementById('deck-validation-message').textContent = '✓ Deck ist gültig';
        document.getElementById('deck-validation-message').style.color = '#4caf50';
    }
    
    // Display current deck cards
    displayCurrentDeckCards();
    
    // Display available cards
    displayAvailableDeckCards();
}

function displayCurrentDeckCards() {
    const container = document.getElementById('current-deck-cards');
    container.innerHTML = '';
    
    if (currentDeck.cards.length === 0) {
        container.innerHTML = '<p style="color: #ccc;">Keine Karten im Deck</p>';
        return;
    }
    
    currentDeck.cards.forEach(card => {
        const item = document.createElement('div');
        item.className = 'deck-card-item';
        
        const stats = card.type === 'monster' 
            ? `${card.attack}/${card.defense}` 
            : card.effect || '';
        
        item.innerHTML = `
            <div class="deck-card-info">
                <span class="deck-card-name">${card.name}</span>
                <span class="deck-card-details">${card.type} | ${stats} | Mana: ${card.mana_cost || 1}</span>
            </div>
            <div class="deck-card-actions">
                <span class="deck-card-quantity">x${card.quantity}</span>
                <button class="card-action-btn" onclick="removeCardFromDeck(${card.id})">−</button>
            </div>
        `;
        
        container.appendChild(item);
    });
}
function displayAvailableDeckCards() {
    const container = document.getElementById('available-deck-cards');
    container.innerHTML = '';

    const filteredCards = filterCards(allUserCards);

    // Determine max copies per card. Normally 2, but if the user has fewer than 15 distinct cards
    // allow more copies so a 30-card deck can be built.
    const distinctAvailable = allUserCards.length || 0;
    let maxCopies = 2;
    if (distinctAvailable > 0 && distinctAvailable < 15) {
        maxCopies = Math.max(2, Math.ceil(30 / distinctAvailable));
    } else if (distinctAvailable === 0) {
        maxCopies = 30; // fallback when no distinct cards (avoid division by zero)
    }

    // Show note when max copies was increased
    if (maxCopies > 2) {
        const note = document.createElement('div');
        note.style.color = '#ffc107';
        note.style.marginBottom = '8px';
        note.textContent = `Hinweis: Da nur ${distinctAvailable} verschiedene Karten verfügbar sind, sind bis zu ${maxCopies} Kopien pro Karte erlaubt, damit ein 30-Karten-Deck möglich ist.`;
        container.appendChild(note);
    }

    if (filteredCards.length === 0) {
        container.innerHTML += '<p style="color: #ccc;">Keine Karten verfügbar</p>';
        return;
    }

    filteredCards.forEach(card => {
        const item = document.createElement('div');
        item.className = 'deck-card-item';

        const stats = card.type === 'monster'
            ? `${card.attack}/${card.defense}`
            : card.effect || '';

        const inDeck = currentDeck.cards.find(c => c.id === card.id);
        const inDeckCount = inDeck ? inDeck.quantity : 0;
        const canAdd = inDeckCount < maxCopies; // use dynamic maxCopies

        item.innerHTML = `
            <div class="deck-card-info">
                <span class="deck-card-name">${card.name}</span>
                <span class="deck-card-details">${card.type} | ${stats} | Mana: ${card.mana_cost || 1}</span>
            </div>
            <div class="deck-card-actions">
                ${inDeckCount > 0 ? `<span class="deck-card-quantity">${inDeckCount} im Deck</span>` : ''}
                ${canAdd ? `<button class="card-action-btn" onclick="addCardToDeck(${card.id})">+</button>` : `<span style="color:#999; margin-left:8px;">Max ${maxCopies}</span>`}
            </div>
        `;

        container.appendChild(item);
    });
}

function filterCards(cards) {
    const search = document.getElementById('deck-card-search')?.value.toLowerCase() || '';
    const typeFilter = document.getElementById('deck-card-type-filter')?.value || '';
    const rarityFilter = document.getElementById('deck-card-rarity-filter')?.value || '';
    
    return cards.filter(card => {
        const matchesSearch = !search || card.name.toLowerCase().includes(search);
        const matchesType = !typeFilter || card.type === typeFilter;
        const matchesRarity = !rarityFilter || card.rarity === rarityFilter;
        
        return matchesSearch && matchesType && matchesRarity;
    });
}

function filterDeckCards() {
    displayAvailableDeckCards();
}

async function addCardToDeck(cardId) {
    if (!currentDeck) return;
    
    try {
        const response = await fetch('api/deck.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 
                action: 'add_card', 
                deck_id: currentDeck.id,
                card_id: cardId,
                quantity: 1
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadDeck(currentDeck.id);
        } else {
            alert('Fehler: ' + (data.error || 'Karte konnte nicht hinzugefügt werden'));
        }
    } catch (error) {
        console.error('Failed to add card:', error);
        alert('Fehler beim Hinzufügen der Karte');
    }
}

async function removeCardFromDeck(cardId) {
    if (!currentDeck) return;
    
    try {
        const response = await fetch('api/deck.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 
                action: 'remove_card', 
                deck_id: currentDeck.id,
                card_id: cardId,
                quantity: 1
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadDeck(currentDeck.id);
        } else {
            alert('Fehler: ' + (data.error || 'Karte konnte nicht entfernt werden'));
        }
    } catch (error) {
        console.error('Failed to remove card:', error);
        alert('Fehler beim Entfernen der Karte');
    }
}

async function saveDeck() {
    if (!currentDeck) return;
    
    const name = document.getElementById('deck-name-input').value;
    const cardClass = document.getElementById('deck-class-select').value;
    
    try {
        const response = await fetch('api/deck.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 
                action: 'update', 
                deck_id: currentDeck.id,
                name: name,
                card_class: cardClass
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Deck gespeichert!');
            await loadUserDecks();
        } else {
            alert('Fehler: ' + (data.error || 'Deck konnte nicht gespeichert werden'));
        }
    } catch (error) {
        console.error('Failed to save deck:', error);
        alert('Fehler beim Speichern des Decks');
    }
}

async function setActiveDeck() {
    if (!currentDeck) return;
    
    try {
        const response = await fetch('api/deck.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 
                action: 'set_active', 
                deck_id: currentDeck.id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Aktives Deck gesetzt!');
            await loadUserDecks();
        } else {
            alert('Fehler: ' + (data.error || 'Deck konnte nicht aktiviert werden'));
        }
    } catch (error) {
        console.error('Failed to set active deck:', error);
        alert('Fehler beim Aktivieren des Decks');
    }
}

async function deleteDeck() {
    if (!currentDeck) return;
    
    if (!confirm('Möchten Sie dieses Deck wirklich löschen?')) return;
    
    try {
        const response = await fetch('api/deck.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 
                action: 'delete', 
                deck_id: currentDeck.id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentDeck = null;
            document.getElementById('deck-editor-empty').style.display = 'block';
            document.getElementById('deck-editor-content').style.display = 'none';
            await loadUserDecks();
        } else {
            alert('Fehler: ' + (data.error || 'Deck konnte nicht gelöscht werden'));
        }
    } catch (error) {
        console.error('Failed to delete deck:', error);
        alert('Fehler beim Löschen des Decks');
    }
}

async function loadHeader() {
    try {
        const response = await fetch('components/header.html');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const headerHtml = await response.text();
        const placeholder = document.getElementById('header-placeholder');
        placeholder.innerHTML = headerHtml;
        
        // Execute scripts that were inserted via innerHTML
        const scripts = placeholder.querySelectorAll('script');
        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(attr => {
                newScript.setAttribute(attr.name, attr.value);
            });
            newScript.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
        
        // Initial visibility control for the header after it's loaded
        // This assumes the app starts on the 'auth-screen'.
        updateHeaderVisibility('auth-screen'); 
        
    } catch (error) {
        console.error("Could not load header:", error);
    }
}

/**
 * Manages the header's display based on the active screen.
 */
function updateHeaderVisibility(screenId) {
    const headerElement = document.getElementById('main-navigation');
    
    if (headerElement) {
        if (screenId === 'auth-screen' || screenId === 'landing-screen') {
            headerElement.style.display = 'none';
        } else {
            // Note: The visibility might need to be set to 'flex' 
            // depending on the CSS setup, but 'block' or '' usually works.
            headerElement.style.display = 'flex'; 
        }
    }
}

// Intercept the showScreen function to call the header update
const originalShowScreen = window.showScreen;
window.showScreen = function(screenId) {
    if (originalShowScreen) {
        originalShowScreen(screenId);
    }
    updateHeaderVisibility(screenId);
};

// Call the function to load the header when the page loads
document.addEventListener('DOMContentLoaded', loadHeader);