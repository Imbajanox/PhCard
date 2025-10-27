/**
 * Achievements Feature - Frontend JavaScript
 * 
 * Handles achievement display, progress tracking, and unlocked status.
 * Integrates with backend API at /api/quests.php
 * 
 * Backend Endpoints Used:
 * - GET /api/quests.php?action=get_achievements - Fetch all achievements
 * - GET /api/quests.php?action=get_user_achievements - Fetch user's achievement progress
 * 
 * Integration Notes:
 * - Add CSRF token header if your auth system requires it
 * - Add Authorization/session headers if needed (currently relies on session cookies)
 * - Example: headers: { 'X-CSRF-Token': getCsrfToken() }
 */

let allAchievements = [];
let userAchievements = [];

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    loadAchievements();
});

/**
 * Load achievements from the backend API
 */
async function loadAchievements() {
    const container = document.getElementById('achievement-container');
    
    try {
        // Fetch achievements from API
        // Note: Add auth headers here if needed for your implementation
        const [achievementsResponse, userAchievementsResponse] = await Promise.all([
            fetch('api/quests.php?action=get_achievements', {
                method: 'GET',
                credentials: 'include', // Include session cookies
                headers: {
                    'Content-Type': 'application/json',
                    // Add CSRF token if needed:
                    // 'X-CSRF-Token': getCsrfToken(),
                }
            }),
            fetch('api/quests.php?action=get_user_achievements', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    // Add CSRF token if needed:
                    // 'X-CSRF-Token': getCsrfToken(),
                }
            })
        ]);

        if (!achievementsResponse.ok || !userAchievementsResponse.ok) {
            throw new Error(`HTTP error! status: ${achievementsResponse.status}`);
        }

        const achievementsData = await achievementsResponse.json();
        const userAchievementsData = await userAchievementsResponse.json();

        if (achievementsData.success && achievementsData.achievements) {
            allAchievements = achievementsData.achievements;
            
            if (userAchievementsData.success && userAchievementsData.achievements) {
                userAchievements = userAchievementsData.achievements;
            }
            
            displayAchievements();
            updateProgressStats();
        } else {
            showError('Failed to load achievements: ' + (achievementsData.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error loading achievements:', error);
        showError('Unable to load achievements. The backend may not be available yet.');
    }
}

/**
 * Display achievements in a grid
 */
function displayAchievements() {
    const container = document.getElementById('achievement-container');

    if (!allAchievements || allAchievements.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <h3>No achievements available</h3>
                <p>Achievements will appear here as they become available!</p>
            </div>
        `;
        return;
    }

    // Merge achievement data with user progress
    const achievementsWithProgress = allAchievements.map(achievement => {
        const userProgress = userAchievements.find(ua => ua.achievement_id === achievement.id);
        return {
            ...achievement,
            unlocked: userProgress ? userProgress.unlocked : false,
            progress: userProgress ? userProgress.progress : 0,
            unlocked_at: userProgress ? userProgress.unlocked_at : null
        };
    });

    container.innerHTML = `
        <div class="achievement-grid">
            ${achievementsWithProgress.map(achievement => createAchievementCard(achievement)).join('')}
        </div>
    `;
}

/**
 * Create HTML for a single achievement card
 */
function createAchievementCard(achievement) {
    const isUnlocked = achievement.unlocked;
    const progress = parseInt(achievement.progress) || 0;
    const target = parseInt(achievement.objective_target) || 1;
    const percentage = Math.min((progress / target) * 100, 100);
    
    const unlockedClass = isUnlocked ? 'unlocked' : 'locked';
    const icon = achievement.icon || '🏆';

    return `
        <div class="achievement-card ${unlockedClass}">
            <div class="achievement-icon">${icon}</div>
            <h3 class="achievement-title">${escapeHtml(achievement.name)}</h3>
            <p class="achievement-description">${escapeHtml(achievement.description)}</p>
            
            ${!isUnlocked ? `
                <div class="achievement-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${percentage}%"></div>
                    </div>
                    <p class="progress-text">${progress} / ${target}</p>
                </div>
            ` : ''}
            
            ${achievement.xp_reward ? `
                <div class="achievement-reward">
                    🎁 ${achievement.xp_reward} XP
                </div>
            ` : ''}
            
            ${isUnlocked && achievement.unlocked_at ? `
                <div class="achievement-date">
                    Unlocked: ${formatDate(achievement.unlocked_at)}
                </div>
            ` : ''}
        </div>
    `;
}

/**
 * Update the achievement progress statistics
 */
function updateProgressStats() {
    const totalAchievements = allAchievements.length;
    const unlockedAchievements = userAchievements.filter(ua => ua.unlocked).length;
    
    const progressElement = document.getElementById('achievement-progress');
    if (progressElement) {
        progressElement.textContent = `${unlockedAchievements}/${totalAchievements}`;
    }
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
 * Show error message in the container
 */
function showError(message) {
    const container = document.getElementById('achievement-container');
    container.innerHTML = `
        <div class="error-message">
            <h3>⚠️ Error</h3>
            <p>${escapeHtml(message)}</p>
            <p>The achievement system backend may not be fully deployed yet. This is a frontend-only feature that will work once the backend is available.</p>
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
        const response = await fetch('header.html');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const headerHtml = await response.text();
        document.getElementById('header-placeholder').innerHTML = headerHtml;
        
        // Initial visibility control for the header after it's loaded
        // This assumes the app starts on the 'auth-screen'.
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