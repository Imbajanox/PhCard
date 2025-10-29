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
