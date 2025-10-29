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

function showCardDamageNumber(playerType, cardIndex, amount) {
    // Get the field element
    const fieldEl = document.getElementById(playerType + '-field');
    if (!fieldEl) return;
    
    // Get the specific card element
    const cardElements = fieldEl.querySelectorAll('.card');
    if (cardIndex >= 0 && cardIndex < cardElements.length) {
        const cardEl = cardElements[cardIndex];
        const rect = cardEl.getBoundingClientRect();
        
        const numberEl = document.createElement('div');
        numberEl.className = 'damage-number';
        numberEl.textContent = `-${amount}`;
        numberEl.style.left = (rect.left + rect.width / 2) + 'px';
        numberEl.style.top = (rect.top + rect.height / 2) + 'px';
        
        document.body.appendChild(numberEl);
        
        // Highlight the card being damaged
        cardEl.classList.add('card-damage-flash');
        
        // Remove effects after animation completes
        setTimeout(() => {
            numberEl.remove();
            cardEl.classList.remove('card-damage-flash');
        }, ANIMATION_DURATIONS.DAMAGE_NUMBER);
    }
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
