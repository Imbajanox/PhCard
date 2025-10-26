/**
 * Card Sets Feature - Frontend JavaScript
 * 
 * Handles card set/expansion display and card browsing with modal.
 * Integrates with backend API at /api/card_sets.php
 * 
 * Backend Endpoints Used:
 * - GET /api/card_sets.php?action=list_sets - Fetch all card sets
 * - GET /api/card_sets.php?action=get_set_cards&set_id={id} - Fetch cards in a set
 * 
 * Integration Notes:
 * - Add CSRF token header if your auth system requires it
 * - Add Authorization/session headers if needed (currently relies on session cookies)
 * - Example: headers: { 'X-CSRF-Token': getCsrfToken() }
 */

let allCardSets = [];

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    loadCardSets();
    createModal();
});

/**
 * Load card sets from the backend API
 */
async function loadCardSets() {
    const container = document.getElementById('cardset-container');
    
    try {
        // Fetch card sets from API
        // Note: Add auth headers here if needed for your implementation
        const response = await fetch('../../api/card_sets.php?action=list_sets', {
            method: 'GET',
            credentials: 'include', // Include session cookies
            headers: {
                'Content-Type': 'application/json',
                // Add CSRF token if needed:
                // 'X-CSRF-Token': getCsrfToken(),
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success && data.sets) {
            allCardSets = data.sets;
            displayCardSets(allCardSets);
        } else {
            showError('Failed to load card sets: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error loading card sets:', error);
        showError('Unable to load card sets. The backend may not be available yet.');
    }
}

/**
 * Display card sets in a grid
 */
function displayCardSets(cardSets) {
    const container = document.getElementById('cardset-container');

    if (!cardSets || cardSets.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <h3>No card sets available</h3>
                <p>Card sets will appear here when they are added!</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="cardset-grid">
            ${cardSets.map(cardSet => createCardSetCard(cardSet)).join('')}
        </div>
    `;

    // Attach click event listeners
    document.querySelectorAll('.cardset-card').forEach(card => {
        card.addEventListener('click', (e) => {
            const setId = e.currentTarget.dataset.setId;
            openCardSetModal(setId);
        });
    });
}

/**
 * Create HTML for a single card set card
 */
function createCardSetCard(cardSet) {
    const releaseDate = cardSet.release_date ? formatDate(cardSet.release_date) : 'TBA';
    const cardCount = cardSet.card_count || 0;
    const setType = cardSet.set_type || 'core';

    return `
        <div class="cardset-card" data-set-id="${cardSet.id}">
            <div class="cardset-header">
                <h3 class="cardset-name">${escapeHtml(cardSet.name)}</h3>
                <span class="cardset-code">${escapeHtml(cardSet.code)}</span>
            </div>
            
            <span class="cardset-type ${setType}">${setType}</span>
            
            <p class="cardset-description">
                ${escapeHtml(cardSet.description || 'No description available')}
            </p>
            
            <div class="cardset-meta">
                <span class="card-count">🃏 ${cardCount} cards</span>
                <span class="release-date">📅 ${releaseDate}</span>
            </div>
        </div>
    `;
}

/**
 * Create modal HTML structure and inject into page
 */
function createModal() {
    const modalHtml = `
        <div id="cardset-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="modal-title">Card Set</h2>
                    <button class="close-modal" onclick="closeCardSetModal()">&times;</button>
                </div>
                <div class="modal-body" id="modal-body">
                    <div class="modal-loading">Loading cards...</div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Close modal when clicking outside
    document.getElementById('cardset-modal').addEventListener('click', (e) => {
        if (e.target.id === 'cardset-modal') {
            closeCardSetModal();
        }
    });
}

/**
 * Open modal and load cards for a specific set
 */
async function openCardSetModal(setId) {
    const modal = document.getElementById('cardset-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');
    
    // Find the card set
    const cardSet = allCardSets.find(cs => cs.id == setId);
    
    if (cardSet) {
        modalTitle.textContent = cardSet.name;
    }
    
    // Show modal with loading state
    modal.classList.add('active');
    modalBody.innerHTML = '<div class="modal-loading">Loading cards...</div>';
    
    try {
        // Fetch cards for this set
        const response = await fetch(`../../api/card_sets.php?action=get_set_cards&set_id=${setId}`, {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                // Add CSRF token if needed:
                // 'X-CSRF-Token': getCsrfToken(),
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success && data.cards) {
            displayCardsInModal(data.cards);
        } else {
            showModalError('Failed to load cards: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error loading cards:', error);
        showModalError('Unable to load cards. The backend may not be available yet.');
    }
}

/**
 * Display cards in the modal
 */
function displayCardsInModal(cards) {
    const modalBody = document.getElementById('modal-body');
    
    if (!cards || cards.length === 0) {
        modalBody.innerHTML = `
            <div class="empty-state">
                <h3>No cards in this set</h3>
                <p>This set doesn't contain any cards yet.</p>
            </div>
        `;
        return;
    }

    modalBody.innerHTML = `
        <div class="cards-grid">
            ${cards.map(card => createCardItem(card)).join('')}
        </div>
    `;
}

/**
 * Create HTML for a single card item in the modal
 */
function createCardItem(card) {
    const cardType = card.type || 'monster';
    const rarity = card.rarity || 'common';
    
    return `
        <div class="card-item ${cardType}">
            <h4 class="card-name">${escapeHtml(card.name)}</h4>
            <span class="card-type ${cardType}">${cardType}</span>
            
            ${cardType === 'monster' ? `
                <div class="card-stats">
                    <div class="card-stat">
                        <span class="stat-label">ATK</span>
                        <span class="stat-value">${card.attack || 0}</span>
                    </div>
                    <div class="card-stat">
                        <span class="stat-label">DEF</span>
                        <span class="stat-value">${card.defense || 0}</span>
                    </div>
                </div>
            ` : ''}
            
            ${card.mana_cost ? `
                <div class="card-stat">
                    <span class="stat-label">Mana</span>
                    <span class="stat-value">${card.mana_cost}</span>
                </div>
            ` : ''}
            
            <p class="card-description">${escapeHtml(card.description || '')}</p>
            
            <div class="card-rarity ${rarity}">${rarity}</div>
        </div>
    `;
}

/**
 * Close the card set modal
 */
function closeCardSetModal() {
    const modal = document.getElementById('cardset-modal');
    modal.classList.remove('active');
}

/**
 * Show error in modal
 */
function showModalError(message) {
    const modalBody = document.getElementById('modal-body');
    modalBody.innerHTML = `
        <div class="error-message">
            <h3>⚠️ Error</h3>
            <p>${escapeHtml(message)}</p>
        </div>
    `;
}

/**
 * Show error message in the main container
 */
function showError(message) {
    const container = document.getElementById('cardset-container');
    container.innerHTML = `
        <div class="error-message">
            <h3>⚠️ Error</h3>
            <p>${escapeHtml(message)}</p>
            <p>The card sets backend may not be fully deployed yet. This is a frontend-only feature that will work once the backend is available.</p>
        </div>
    `;
}

/**
 * Format date string for display
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Get CSRF token from cookie or meta tag
 * Uncomment and implement if your auth system uses CSRF tokens
 */
// function getCsrfToken() {
//     const token = document.querySelector('meta[name="csrf-token"]');
//     return token ? token.getAttribute('content') : '';
// }
