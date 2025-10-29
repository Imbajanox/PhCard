// Card Collection Module
// Handles displaying user's card collection

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
