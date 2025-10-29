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
            // Store old field states to track destroyed cards
            const oldPlayerField = [...gameState.player_field];
            const oldAIField = [...gameState.ai_field];
            
            // Update game state immediately for HP/mana
            gameState.player_hp = data.game_state.player_hp;
            gameState.ai_hp = data.game_state.ai_hp;
            gameState.player_mana = data.game_state.player_mana;
            gameState.player_max_mana = data.game_state.player_max_mana;
            gameState.player_hand = data.game_state.player_hand;
            gameState.turn_count = data.game_state.turn_count;
            
            updateHP();
            updateMana();
            displayHand();
            document.getElementById('turn-count').textContent = gameState.turn_count;
            
            // Update game state immediately
            gameState.player_field = data.game_state.player_field;
            gameState.ai_field = data.game_state.ai_field;
            
            // Display fields to show current state (cards may have changed HP)
            displayField('player');
            displayField('ai');
            
            // Process battle events with visual effects only (no state updates)
            let delay = ANIMATION_DURATIONS.BATTLE_INITIAL_DELAY;
            
            // Show battle events with damage numbers
            if (Array.isArray(data.battle_events) && data.battle_events.length > 0) {
                for (let i = 0; i < data.battle_events.length; i++) {
                    const event = data.battle_events[i];
                    
                    setTimeout(() => {
                        if (event.type === 'damage') {
                            // Show damage number on the card
                            // Note: Card index may not match perfectly if cards were destroyed earlier,
                            // but damage numbers still appear in the general area. This is acceptable
                            // since the battle log provides exact details.
                            showCardDamageNumber(event.targetPlayer, event.targetIndex, event.amount);
                        } else if (event.type === 'destroyed') {
                            // Destroyed cards don't show because they're already removed from state
                            // This event is just for logging purposes now
                            // In the future, we could keep a "graveyard" display
                        }
                    }, delay);
                    
                    delay += ANIMATION_DURATIONS.BATTLE_EVENT_DELAY;
                }
            }
            
            // Add battle log with delay for readability
            data.battle_log.forEach((log, index) => {
                setTimeout(() => addLog(log), delay);
                delay += ANIMATION_DURATIONS.BATTLE_LOG_DELAY;
            });
            
            // Add AI actions with delay
            data.ai_actions.forEach((action, index) => {
                setTimeout(() => addLog(action, 'ai'), delay);
                delay += ANIMATION_DURATIONS.AI_ACTION_DELAY;
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
