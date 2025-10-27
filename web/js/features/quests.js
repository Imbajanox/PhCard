/**
 * Quests Feature - Frontend JavaScript
 * 
 * Handles quest display, filtering, progress tracking, and reward claiming.
 * Integrates with backend API at /api/quests.php
 * 
 * Backend Endpoints Used:
 * - GET /api/quests.php?action=get_active_quests - Fetch all active quests
 * - POST /api/quests.php?action=claim_quest_reward - Claim completed quest rewards
 * 
 * Integration Notes:
 * - Add CSRF token header if your auth system requires it
 * - Add Authorization/session headers if needed (currently relies on session cookies)
 * - Example: headers: { 'X-CSRF-Token': getCsrfToken() }
 */

let allQuests = [];
let currentFilter = 'all';

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    loadQuests();
    setupFilterButtons();
});

/**
 * Load quests from the backend API
 */
async function loadQuests() {
    const container = document.getElementById('quest-container');
    
    try {
        // Fetch quests from API
        // Note: Add auth headers here if needed for your implementation
        const response = await fetch('../../api/quests.php?action=get_active_quests', {
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

        if (data.success && data.quests) {
            allQuests = data.quests;
            displayQuests(allQuests);
        } else {
            showError('Failed to load quests: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error loading quests:', error);
        showError('Unable to load quests. The backend may not be available yet.');
    }
}

/**
 * Display quests in the container
 */
function displayQuests(quests) {
    const container = document.getElementById('quest-container');

    if (!quests || quests.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <h3>No quests available</h3>
                <p>Check back later for new quests!</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="quest-list">
            ${quests.map(quest => createQuestCard(quest)).join('')}
        </div>
    `;

    // Attach event listeners to claim buttons
    document.querySelectorAll('.claim-button').forEach(button => {
        button.addEventListener('click', (e) => {
            const questId = e.target.dataset.questId;
            claimQuestReward(questId);
        });
    });
}

/**
 * Create HTML for a single quest card
 */
function createQuestCard(quest) {
    const progress = parseInt(quest.current_progress) || 0;
    const target = parseInt(quest.objective_target) || 1;
    const percentage = Math.min((progress / target) * 100, 100);
    const isCompleted = quest.completed || percentage >= 100;
    const isClaimed = quest.claimed;
    
    const completedClass = isCompleted ? 'completed' : '';
    const claimedClass = isClaimed ? 'claimed' : '';

    return `
        <div class="quest-card ${completedClass} ${claimedClass}">
            <div class="quest-header">
                <h3 class="quest-title">${escapeHtml(quest.name)}</h3>
                <span class="quest-type ${quest.quest_type}">${quest.quest_type}</span>
            </div>
            
            <p class="quest-description">${escapeHtml(quest.description)}</p>
            
            <div class="quest-progress">
                <div class="progress-bar">
                    <div class="progress-fill ${isCompleted ? 'complete' : ''}" style="width: ${percentage}%">
                        ${percentage >= 20 ? Math.round(percentage) + '%' : ''}
                    </div>
                </div>
                <p class="progress-text">${progress} / ${target} ${quest.objective_type.replace('_', ' ')}</p>
            </div>
            
            <div class="quest-reward">
                <span class="reward-icon">🎁</span>
                <span>Reward: ${quest.xp_reward} XP${quest.card_reward_id ? ' + Special Card' : ''}</span>
            </div>
            
            ${isCompleted && !isClaimed ? `
                <button class="claim-button" data-quest-id="${quest.id}">
                    Claim Reward
                </button>
            ` : ''}
            
            ${isClaimed ? '<p style="color: #28a745; font-weight: bold; margin-top: 10px;">✓ Claimed</p>' : ''}
        </div>
    `;
}

/**
 * Claim a quest reward
 */
async function claimQuestReward(questId) {
    try {
        const response = await fetch('../../api/quests.php?action=claim_quest_reward', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                // Add CSRF token if needed:
                // 'X-CSRF-Token': getCsrfToken(),
            },
            body: `quest_id=${questId}`
        });

        const data = await response.json();

        if (data.success) {
            alert('Quest reward claimed successfully! You earned ' + data.xp_reward + ' XP!');
            // Reload quests to update display
            loadQuests();
        } else {
            alert('Failed to claim reward: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error claiming reward:', error);
        alert('Unable to claim reward. Please try again later.');
    }
}

/**
 * Setup filter button event listeners
 */
function setupFilterButtons() {
    const filterButtons = document.querySelectorAll('.quest-filters button');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Update active button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            
            // Apply filter
            currentFilter = button.dataset.filter;
            filterQuests();
        });
    });
}

/**
 * Filter quests based on current filter
 */
function filterQuests() {
    let filteredQuests = allQuests;

    switch (currentFilter) {
        case 'daily':
        case 'weekly':
        case 'story':
            filteredQuests = allQuests.filter(q => q.quest_type === currentFilter);
            break;
        case 'active':
            filteredQuests = allQuests.filter(q => !q.completed);
            break;
        case 'completed':
            filteredQuests = allQuests.filter(q => q.completed);
            break;
        case 'all':
        default:
            filteredQuests = allQuests;
            break;
    }

    displayQuests(filteredQuests);
}

/**
 * Show error message in the container
 */
function showError(message) {
    const container = document.getElementById('quest-container');
    container.innerHTML = `
        <div class="error-message">
            <h3>⚠️ Error</h3>
            <p>${escapeHtml(message)}</p>
            <p>The quest system backend may not be fully deployed yet. This is a frontend-only feature that will work once the backend is available.</p>
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
 * Get CSRF token from cookie or meta tag
 * Uncomment and implement if your auth system uses CSRF tokens
 */
// function getCsrfToken() {
//     const token = document.querySelector('meta[name="csrf-token"]');
//     return token ? token.getAttribute('content') : '';
// }



async function loadHeader() {
    try {
        const response = await fetch('../../components/header.html');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const headerHtml = await response.text();
        document.getElementById('header-placeholder').innerHTML = headerHtml;
        
        // Initial visibility control for the header after it's loaded
        updateHeaderVisibility(); 
        
    } catch (error) {
        console.error("Could not load header:", error);
    }
}

/**
 * Manages the header's display based on the active screen.
 */
function updateHeaderVisibility() {
    const headerElement = document.getElementById('main-navigation');
    
    if (headerElement) {
        headerElement.style.display = 'flex'; 
    }
}

// Intercept the showScreen function to call the header update
const originalShowScreen = window.showScreen;
window.showScreen = function(screenId) {
    if (originalShowScreen) {
        originalShowScreen(screenId);
    }
    updateHeaderVisibility();
};

// Call the function to load the header when the page loads
document.addEventListener('DOMContentLoaded', loadHeader);