/**
 * Shop Feature - Frontend JavaScript
 * 
 * Handles card shop, card packs, and daily login rewards
 */

let shopItems = [];
let cardPacks = [];
let userCurrency = { coins: 0, gems: 0 };

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    loadHeader();
    loadCurrency();
    
    setupTabButtons();
    setupDailyLogin();

    loadShopItems();
    loadCardPacks();
});

/**
 * Load user's currency
 */
async function loadCurrency() {
    try {
        const response = await fetch('../../api/shop.php?action=get_user_currency', {
            method: 'GET',
            credentials: 'include'
        });

        const data = await response.json();
        if (data.success && data.currency) {
            userCurrency = data.currency;
            updateCurrencyDisplay();
        }
    } catch (error) {
        console.error('Error loading currency:', error);
    }
}

/**
 * Update currency display
 */
function updateCurrencyDisplay() {
    document.getElementById('coins-display').textContent = userCurrency.coins || 0;
    document.getElementById('gems-display').textContent = userCurrency.gems || 0;
}

/**
 * Load shop items
 */
async function loadShopItems() {
    const container = document.getElementById('cards-container');
    
    try {
        const response = await fetch('../../api/shop.php?action=get_shop_items', {
            method: 'GET',
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success && data.items) {
            shopItems = data.items;
            displayShopItems(shopItems);
        } else {
            showError(container, 'Failed to load shop items');
        }
    } catch (error) {
        console.error('Error loading shop items:', error);
        showError(container, 'Unable to load shop. Please try again later.');
    }
}

/**
 * Display shop items
 */
async function displayShopItems(items) {
    const container = document.getElementById('cards-container');

    // Ensure we have the latest currency before rendering (avoids race on initial load)
    try {
        await loadCurrency();
    } catch (e) {
        // If loadCurrency fails, proceed with whatever currency we have (likely zeros)
        console.warn('Warning: failed to refresh currency before rendering shop items', e);
    }

    if (!items || items.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <h3>No items available</h3>
                <p>Check back later!</p>
            </div>
        `;
        return;
    }

    container.innerHTML = items.map(item => `
        <div class="shop-item">
            <div class="item-header">
                <div class="item-name">${escapeHtml(item.name)}</div>
                <span class="item-rarity rarity-${item.rarity}">${item.rarity}</span>
            </div>
            
            <div class="item-stats">
                <div><strong>Type:</strong> ${item.type}</div>
                ${item.type === 'monster' ? `
                    <div><strong>ATK:</strong> ${item.attack} | <strong>DEF:</strong> ${item.defense}</div>
                ` : ''}
                ${item.description ? `<div style="margin-top: 10px;">${escapeHtml(item.description)}</div>` : ''}
            </div>
            
            <div class="item-price">
                ${item.price_coins > 0 ? `<span class="price-coins">🪙 ${item.price_coins}</span>` : ''}
                ${item.price_gems > 0 ? `<span class="price-gems">💎 ${item.price_gems}</span>` : ''}
            </div>
            
            <button class="buy-button" onclick="purchaseCard(${item.card_id})" 
                    ${canAfford(item) ? '' : 'disabled'}>
                ${canAfford(item) ? 'Buy Card' : 'Cannot Afford'}
            </button>
        </div>
    `).join('');
}

/**
 * Load card packs
 */
async function loadCardPacks() {
    const container = document.getElementById('packs-container');
    
    try {
        const response = await fetch('../../api/shop.php?action=get_card_packs', {
            method: 'GET',
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success && data.packs) {
            cardPacks = data.packs;
            displayCardPacks(cardPacks);
        } else {
            showError(container, 'Failed to load card packs');
        }
    } catch (error) {
        console.error('Error loading card packs:', error);
        showError(container, 'Unable to load packs. Please try again later.');
    }
}

/**
 * Display card packs
 */
function displayCardPacks(packs) {
    const container = document.getElementById('packs-container');

    if (!packs || packs.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <h3>No packs available</h3>
                <p>Check back later!</p>
            </div>
        `;
        return;
    }

    container.innerHTML = packs.map(pack => `
        <div class="pack-item">
            <div class="item-header">
                <div class="item-name">${escapeHtml(pack.name)}</div>
                <span class="item-rarity rarity-${pack.pack_type}">${pack.pack_type}</span>
            </div>
            
            <div class="pack-description">${escapeHtml(pack.description || '')}</div>
            
            <div class="pack-info">
                <div class="pack-info-item">
                    <div class="pack-info-label">Cards</div>
                    <div class="pack-info-value">${pack.cards_per_pack}</div>
                </div>
                ${pack.guaranteed_rarity ? `
                    <div class="pack-info-item">
                        <div class="pack-info-label">Guaranteed</div>
                        <div class="pack-info-value">${pack.guaranteed_rarity}+</div>
                    </div>
                ` : ''}
            </div>
            
            <div class="item-price">
                ${pack.price_coins > 0 ? `<span class="price-coins">🪙 ${pack.price_coins}</span>` : ''}
                ${pack.price_gems > 0 ? `<span class="price-gems">💎 ${pack.price_gems}</span>` : ''}
            </div>
            
            <button class="buy-button" onclick="purchasePack(${pack.id})" 
                    ${canAffordPack(pack) ? '' : 'disabled'}>
                ${canAffordPack(pack) ? 'Buy Pack' : 'Cannot Afford'}
            </button>
        </div>
    `).join('');
}

/**
 * Check if user can afford an item
 */
function canAfford(item) {
    return (item.price_coins === 0 || userCurrency.coins >= item.price_coins) &&
           (item.price_gems === 0 || userCurrency.gems >= item.price_gems);
}

/**
 * Check if user can afford a pack
 */
function canAffordPack(pack) {
    return (pack.price_coins === 0 || userCurrency.coins >= pack.price_coins) &&
           (pack.price_gems === 0 || userCurrency.gems >= pack.price_gems);
}

/**
 * Purchase a card
 */
async function purchaseCard(cardId) {
    try {
        const response = await fetch('../../api/shop.php?action=purchase_card', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `card_id=${cardId}`
        });

        const data = await response.json();

        if (data.success) {
            alert(`Successfully purchased ${data.card_name}!`);
            userCurrency.coins = data.coins_remaining;
            userCurrency.gems = data.gems_remaining;
            updateCurrencyDisplay();
            // Refresh shop to update button states
            displayShopItems(shopItems);
        } else {
            alert('Purchase failed: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error purchasing card:', error);
        alert('Unable to purchase card. Please try again later.');
    }
}

/**
 * Purchase a pack
 */
async function purchasePack(packId) {
    try {
        const response = await fetch('../../api/shop.php?action=purchase_pack', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `pack_id=${packId}`
        });

        const data = await response.json();

        if (data.success) {
            userCurrency.coins = data.coins_remaining;
            userCurrency.gems = data.gems_remaining;
            updateCurrencyDisplay();
            // Show pack opening animation/modal
            showPackCards(data.pack_name, data.cards);
            // Refresh to update button states
            displayCardPacks(cardPacks);
        } else {
            alert('Purchase failed: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error purchasing pack:', error);
        alert('Unable to purchase pack. Please try again later.');
    }
}

/**
 * Show pack cards in modal
 */
function showPackCards(packName, cards) {
    const modal = document.getElementById('pack-modal');
    const title = document.getElementById('modal-title');
    const cardsContainer = document.getElementById('pack-cards');

    title.textContent = `${packName} - You got ${cards.length} cards!`;
    
    cardsContainer.innerHTML = cards.map(card => `
        <div class="card-reveal-item">
            <span class="item-rarity rarity-${card.rarity}">${card.rarity}</span>
            <div class="card-reveal-name">${escapeHtml(card.name)}</div>
            ${card.type === 'monster' ? `
                <div style="font-size: 0.85em; color: #ccc;">
                    ATK: ${card.attack}<br>
                    DEF: ${card.defense}
                </div>
            ` : ''}
        </div>
    `).join('');

    modal.classList.add('active');
}

/**
 * Close pack modal
 */
function closePackModal() {
    const modal = document.getElementById('pack-modal');
    modal.classList.remove('active');
}

/**
 * Setup tab buttons
 */
function setupTabButtons() {
    const tabButtons = document.querySelectorAll('.shop-tab');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Update active button
            tabButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            
            // Show/hide tabs
            const tab = button.dataset.tab;
            document.getElementById('cards-tab').style.display = tab === 'cards' ? 'block' : 'none';
            document.getElementById('packs-tab').style.display = tab === 'packs' ? 'block' : 'none';
        });
    });

    // Close modal when clicking close button
    document.getElementById('modal-close').addEventListener('click', closePackModal);
}
/**
 * Setup daily login
 */
function setupDailyLogin() {
    const claimBtn = document.getElementById('claim-daily-btn');
    const streakEl = document.getElementById('streak-display');
    const dailyStatusEl = document.getElementById('daily-status');

    if (!claimBtn) return;

    // Refresh UI based on server status
    async function refreshStatus() {
        try {
            // prevent cached responses
            const response = await fetch('../../api/shop.php?action=get_daily_status&_=' + Date.now(), {
                method: 'GET',
                credentials: 'include',
                cache: 'no-store'
            });
            const data = await response.json();

            // Always defensively update the button state first so UI isn't left clickable on error
            if (data && typeof data.claimed !== 'undefined') {
                const claimed = !!data.claimed;
                claimBtn.disabled = claimed;
                claimBtn.textContent = claimed ? 'Claimed Today!' : 'Claim Reward';
            }

            if (data && data.success) {
                if (typeof data.streak !== 'undefined' && streakEl) {
                    streakEl.textContent = `🔥 ${data.streak} Day Streak!`;
                }

                if (typeof data.claimed !== 'undefined') {
                    if (data.claimed) {
                        if (dailyStatusEl) dailyStatusEl.textContent = data.message || 'Come back tomorrow for your next reward!';
                    } else {
                        if (dailyStatusEl) dailyStatusEl.textContent = data.message || 'Claim your daily reward!';
                    }
                } else {
                    // no claimed flag from API - fall back to message if provided
                    if (dailyStatusEl && data.message) dailyStatusEl.textContent = data.message;
                }
            } else {
                // API returned failure - still show streak if included and ensure button state respects claimed flag if present
                if (typeof data !== 'undefined' && typeof data.streak !== 'undefined' && streakEl) {
                    streakEl.textContent = `🔥 ${data.streak} Day Streak!`;
                }
                // If API indicates already claimed via error text but no claimed flag, detect that:
                if (data && typeof data.error === 'string' && data.error.toLowerCase().includes('already claimed')) {
                    claimBtn.disabled = true;
                    claimBtn.textContent = 'Claimed Today!';
                    if (dailyStatusEl) dailyStatusEl.textContent = 'Come back tomorrow!';
                }
            }
        } catch (error) {
            console.error('Error checking daily login status:', error);
            // On error, do not change button state (avoid enabling it if server indicated claimed previously).
            // Optionally show a generic message:
            if (dailyStatusEl && !dailyStatusEl.textContent) {
                dailyStatusEl.textContent = 'Unable to check daily reward status right now.';
            }
        }
    }

    // Claim handler
    claimBtn.addEventListener('click', async () => {
        // guard against double-clicks
        if (claimBtn.disabled) return;

        claimBtn.disabled = true;
        const prevText = claimBtn.textContent;
        claimBtn.textContent = 'Claiming...';

        try {
            const response = await fetch('../../api/shop.php?action=claim_daily_login', {
                method: 'POST',
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                alert(`${data.description}\nYou received: ${data.reward}\nStreak: ${data.streak} days!`);
                if (typeof data.streak !== 'undefined' && streakEl) {
                    streakEl.textContent = `🔥 ${data.streak} Day Streak!`;
                }
                claimBtn.disabled = true;
                claimBtn.textContent = 'Claimed Today!';
                if (dailyStatusEl) dailyStatusEl.textContent = 'Come back tomorrow for your next reward!';
                // Reload currency and refresh status/UI
                await loadCurrency();
                await refreshStatus();
            } else {
                const errMsg = data.error || '';
                if (errMsg.toLowerCase().includes('already claimed')) {
                    claimBtn.disabled = true;
                    claimBtn.textContent = 'Claimed Today!';
                    if (dailyStatusEl) dailyStatusEl.textContent = 'Come back tomorrow!';
                    if (typeof data.streak !== 'undefined' && streakEl) {
                        streakEl.textContent = `🔥 ${data.streak} Day Streak!`;
                    }
                } else {
                    alert('Failed to claim reward: ' + errMsg);
                    // restore button so user can try again
                    claimBtn.disabled = false;
                    claimBtn.textContent = prevText || 'Claim Reward';
                }
            }
        } catch (error) {
            console.error('Error claiming daily login:', error);
            alert('Unable to claim daily reward. Please try again later.');
            claimBtn.disabled = false;
            claimBtn.textContent = prevText || 'Claim Reward';
        }
    });

    // Initial check on load
    refreshStatus();
}

/**
 * Show error message
 */
function showError(container, message) {
    container.innerHTML = `
        <div class="error-message">
            <h3>⚠️ Error</h3>
            <p>${escapeHtml(message)}</p>
        </div>
    `;
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
 * Load header component
 */
async function loadHeader() {
    try {
        const response = await fetch('../../components/header.html');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const headerHtml = await response.text();
        const placeholder = document.getElementById('header-placeholder');
        placeholder.innerHTML = headerHtml;
        
        // Execute scripts
        const scripts = placeholder.querySelectorAll('script');
        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(attr => {
                newScript.setAttribute(attr.name, attr.value);
            });
            newScript.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
        
        updateHeaderVisibility();
    } catch (error) {
        console.error("Could not load header:", error);
    }
}

/**
 * Update header visibility
 */
function updateHeaderVisibility() {
    const headerElement = document.getElementById('main-navigation');
    if (headerElement) {
        headerElement.style.display = 'flex';
    }
}
