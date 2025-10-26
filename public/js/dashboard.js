// Dashboard JavaScript

// Global state
let cardsData = [];
let sortColumn = null;
let sortDirection = 'desc';

// Initialize dashboard on page load
document.addEventListener('DOMContentLoaded', function() {
    checkAuth();
    loadOverviewData();
});

// Check authentication
async function checkAuth() {
    try {
        const response = await fetch('/api/auth.php?action=check');
        const data = await response.json();
        
        if (!data.success || !data.authenticated) {
            window.location.href = 'index.html';
        }
    } catch (error) {
        console.error('Auth check failed:', error);
        window.location.href = 'index.html';
    }
}

// Show dashboard section
function showDashboardSection(section) {
    // Hide all sections
    document.querySelectorAll('.dashboard-section').forEach(s => {
        s.classList.remove('active');
    });
    
    // Remove active from all nav buttons
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected section
    const sectionElement = document.getElementById(`${section}-section`);
    if (sectionElement) {
        sectionElement.classList.add('active');
    }
    
    // Set active nav button
    event.target.classList.add('active');
    
    // Load section data
    switch(section) {
        case 'overview':
            loadOverviewData();
            break;
        case 'card-stats':
            loadCardStats();
            break;
        case 'deck-performance':
            loadDeckPerformance();
            break;
        case 'simulation':
            // No automatic loading needed
            break;
    }
}

// Load Overview Data
async function loadOverviewData() {
    showLoading(true);
    
    try {
        const response = await fetch('/api/analytics.php?action=winrate_analysis');
        const data = await response.json();
        
        if (data.success) {
            updateOverviewStats(data);
        } else {
            console.error('Failed to load overview data:', data.error);
        }
    } catch (error) {
        console.error('Error loading overview data:', error);
    } finally {
        showLoading(false);
    }
}

function updateOverviewStats(data) {
    const overall = data.overall;
    
    // Update overall stats
    document.getElementById('total-games').textContent = overall.total_games || 0;
    document.getElementById('total-wins').textContent = overall.wins || 0;
    document.getElementById('total-losses').textContent = overall.losses || 0;
    
    const winrate = overall.total_games > 0 
        ? ((overall.wins / overall.total_games) * 100).toFixed(2) 
        : 0;
    document.getElementById('overall-winrate').textContent = winrate + '%';
    
    document.getElementById('avg-turns').textContent = overall.avg_turns 
        ? parseFloat(overall.avg_turns).toFixed(1) 
        : '0';
    document.getElementById('avg-xp').textContent = overall.avg_xp 
        ? parseFloat(overall.avg_xp).toFixed(0) 
        : '0';
    
    // Update AI level stats
    const aiLevelContainer = document.getElementById('ai-level-stats');
    aiLevelContainer.innerHTML = '';
    
    if (data.by_ai_level && data.by_ai_level.length > 0) {
        data.by_ai_level.forEach(level => {
            const card = document.createElement('div');
            card.className = 'ai-level-card';
            card.innerHTML = `
                <h4>KI Level ${level.ai_level}</h4>
                <div class="ai-stats">
                    <div>Spiele: ${level.games}</div>
                    <div>Siege: ${level.wins}</div>
                </div>
                <div class="winrate">${level.winrate}%</div>
            `;
            aiLevelContainer.appendChild(card);
        });
    } else {
        aiLevelContainer.innerHTML = '<p style="color: #fff;">Keine Daten verfügbar</p>';
    }
    
    // Update most played cards
    const tbody = document.getElementById('most-played-tbody');
    tbody.innerHTML = '';
    
    if (data.most_played_cards && data.most_played_cards.length > 0) {
        data.most_played_cards.forEach(card => {
            const row = document.createElement('tr');
            const cardWinrate = card.times_played > 0 
                ? ((card.wins / card.times_played) * 100).toFixed(2) 
                : 0;
            row.innerHTML = `
                <td>${card.name}</td>
                <td>${card.times_played}</td>
                <td>${card.wins}</td>
                <td>${cardWinrate}%</td>
            `;
            tbody.appendChild(row);
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Keine Daten verfügbar</td></tr>';
    }
}

// Load Card Statistics
async function loadCardStats() {
    showLoading(true);
    
    try {
        const response = await fetch('/api/analytics.php?action=card_stats');
        const data = await response.json();
        
        if (data.success) {
            cardsData = data.cards || [];
            displayCardStats(cardsData);
        } else {
            console.error('Failed to load card stats:', data.error);
        }
    } catch (error) {
        console.error('Error loading card stats:', error);
    } finally {
        showLoading(false);
    }
}

function displayCardStats(cards) {
    const tbody = document.getElementById('cards-stats-tbody');
    tbody.innerHTML = '';
    
    if (cards && cards.length > 0) {
        cards.forEach(card => {
            const row = document.createElement('tr');
            const rarityClass = `rarity-${card.rarity}`;
            row.innerHTML = `
                <td>${card.name}</td>
                <td>${card.type}</td>
                <td class="${rarityClass}">${card.rarity}</td>
                <td>${card.times_played || 0}</td>
                <td>${card.winrate || 0}%</td>
                <td>${card.total_damage_dealt || 0}</td>
                <td>${card.total_healing_done || 0}</td>
                <td>${card.avg_turn_played ? parseFloat(card.avg_turn_played).toFixed(1) : '-'}</td>
            `;
            tbody.appendChild(row);
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">Keine Kartendaten verfügbar</td></tr>';
    }
}

// Filter cards
function filterCards() {
    const searchTerm = document.getElementById('card-search').value.toLowerCase();
    const typeFilter = document.getElementById('card-type-filter').value;
    const rarityFilter = document.getElementById('card-rarity-filter').value;
    
    const filteredCards = cardsData.filter(card => {
        const matchesSearch = card.name.toLowerCase().includes(searchTerm);
        const matchesType = !typeFilter || card.type === typeFilter;
        const matchesRarity = !rarityFilter || card.rarity === rarityFilter;
        
        return matchesSearch && matchesType && matchesRarity;
    });
    
    displayCardStats(filteredCards);
}

// Sort cards table
function sortCardsTable(column) {
    if (sortColumn === column) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = column;
        sortDirection = 'desc';
    }
    
    const sortedCards = [...cardsData].sort((a, b) => {
        let valA = a[column];
        let valB = b[column];
        
        // Handle null/undefined values
        if (valA === null || valA === undefined) valA = 0;
        if (valB === null || valB === undefined) valB = 0;
        
        // String comparison
        if (typeof valA === 'string') {
            return sortDirection === 'asc' 
                ? valA.localeCompare(valB)
                : valB.localeCompare(valA);
        }
        
        // Numeric comparison
        return sortDirection === 'asc' ? valA - valB : valB - valA;
    });
    
    displayCardStats(sortedCards);
}

// Load Deck Performance
async function loadDeckPerformance() {
    showLoading(true);
    
    try {
        const response = await fetch('/api/analytics.php?action=deck_performance');
        const data = await response.json();
        
        if (data.success) {
            displayDeckPerformance(data.decks || []);
        } else {
            console.error('Failed to load deck performance:', data.error);
        }
    } catch (error) {
        console.error('Error loading deck performance:', error);
    } finally {
        showLoading(false);
    }
}

function displayDeckPerformance(decks) {
    const grid = document.getElementById('decks-grid');
    grid.innerHTML = '';
    
    if (decks && decks.length > 0) {
        decks.forEach(deck => {
            const card = document.createElement('div');
            card.className = 'deck-card';
            card.innerHTML = `
                <h3>${deck.name || 'Unbenanntes Deck'}</h3>
                <div class="deck-stats">
                    <p><span class="label">Spiele:</span> <span class="value">${deck.games_played || 0}</span></p>
                    <p><span class="label">Siege:</span> <span class="value">${deck.wins || 0}</span></p>
                    <p><span class="label">Siegrate:</span> <span class="value">${deck.winrate || 0}%</span></p>
                </div>
            `;
            grid.appendChild(card);
        });
    } else {
        grid.innerHTML = '<p style="color: #fff; text-align: center;">Keine Decks gefunden. Erstelle ein Deck, um Performance-Daten zu sehen.</p>';
    }
}

// Run Simulation
async function runSimulation() {
    const deckAConfig = document.getElementById('deck-a-config').value.trim();
    const deckBConfig = document.getElementById('deck-b-config').value.trim();
    const iterations = parseInt(document.getElementById('sim-iterations').value) || 100;
    
    // Validate inputs
    if (!deckAConfig || !deckBConfig) {
        alert('Bitte beide Deck-Konfigurationen eingeben');
        return;
    }
    
    let deckA, deckB;
    try {
        deckA = JSON.parse(deckAConfig);
        deckB = JSON.parse(deckBConfig);
    } catch (error) {
        alert('Ungültiges JSON-Format. Bitte überprüfen Sie die Eingabe.');
        return;
    }
    
    showLoading(true);
    
    try {
        const formData = new FormData();
        formData.append('deck_a', JSON.stringify(deckA));
        formData.append('deck_b', JSON.stringify(deckB));
        formData.append('iterations', iterations);
        
        const response = await fetch('/api/simulation.php?action=run_simulation', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            displaySimulationResults(data.results);
        } else {
            alert('Simulation fehlgeschlagen: ' + (data.error || 'Unbekannter Fehler'));
        }
    } catch (error) {
        console.error('Error running simulation:', error);
        alert('Fehler bei der Simulation: ' + error.message);
    } finally {
        showLoading(false);
    }
}

function displaySimulationResults(results) {
    const container = document.getElementById('simulation-results');
    
    const totalGames = results.total_games || 0;
    const deckAWins = results.deck_a_wins || 0;
    const deckBWins = results.deck_b_wins || 0;
    const draws = results.draws || 0;
    
    const deckAWinrate = totalGames > 0 ? ((deckAWins / totalGames) * 100).toFixed(2) : 0;
    const deckBWinrate = totalGames > 0 ? ((deckBWins / totalGames) * 100).toFixed(2) : 0;
    
    container.innerHTML = `
        <div class="result-summary">
            <h3>Simulationsergebnisse</h3>
            
            <div class="result-row">
                <span class="label">Gesamte Spiele:</span>
                <span class="value">${totalGames}</span>
            </div>
            
            <div class="result-row">
                <span class="label">Deck A Siege:</span>
                <span class="value">${deckAWins} (${deckAWinrate}%)</span>
            </div>
            
            <div class="winrate-bar">
                <div class="winrate-fill" style="width: ${deckAWinrate}%">
                    ${deckAWinrate}%
                </div>
            </div>
            
            <div class="result-row">
                <span class="label">Deck B Siege:</span>
                <span class="value">${deckBWins} (${deckBWinrate}%)</span>
            </div>
            
            <div class="winrate-bar">
                <div class="winrate-fill" style="width: ${deckBWinrate}%">
                    ${deckBWinrate}%
                </div>
            </div>
            
            <div class="result-row">
                <span class="label">Unentschieden:</span>
                <span class="value">${draws}</span>
            </div>
            
            <div class="result-row">
                <span class="label">Durchschnittliche Runden:</span>
                <span class="value">${results.avg_turns || 0}</span>
            </div>
        </div>
    `;
}

// Load template decks
function loadTemplate(templateName) {
    let deckA, deckB;
    
    switch(templateName) {
        case 'aggro-vs-control':
            // Aggro deck: low cost, high attack cards
            deckA = [
                {"id": 1, "quantity": 3},
                {"id": 2, "quantity": 3},
                {"id": 5, "quantity": 2}
            ];
            // Control deck: high cost, defensive cards
            deckB = [
                {"id": 3, "quantity": 3},
                {"id": 4, "quantity": 2},
                {"id": 6, "quantity": 2}
            ];
            break;
            
        case 'balanced':
            // Balanced decks
            deckA = [
                {"id": 1, "quantity": 2},
                {"id": 2, "quantity": 2},
                {"id": 3, "quantity": 2}
            ];
            deckB = [
                {"id": 1, "quantity": 2},
                {"id": 2, "quantity": 2},
                {"id": 3, "quantity": 2}
            ];
            break;
            
        case 'spell-heavy':
            // Spell heavy vs monster heavy
            deckA = [
                {"id": 4, "quantity": 3},
                {"id": 5, "quantity": 3}
            ];
            deckB = [
                {"id": 1, "quantity": 3},
                {"id": 2, "quantity": 3},
                {"id": 3, "quantity": 2}
            ];
            break;
    }
    
    document.getElementById('deck-a-config').value = JSON.stringify(deckA, null, 2);
    document.getElementById('deck-b-config').value = JSON.stringify(deckB, null, 2);
}

// Show/Hide Loading
function showLoading(show) {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.style.display = show ? 'flex' : 'none';
    }
}
