// Core Application Entry Point
// Minimal bootstrapping code - actual functionality in modules

// Global state
let currentUser = null;
let gameState = null;

// Animation timing constants
const ANIMATION_DURATIONS = {
    DAMAGE_NUMBER: 1000,
    CARD_FLASH: 800,
    BATTLE_INITIAL_DELAY: 300,
    BATTLE_EVENT_DELAY: 400,
    BATTLE_LOG_DELAY: 200,
    AI_ACTION_DELAY: 300
};

// Initialize app
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    loadLeaderboard();
});

// Show/Hide screens
function showScreen(screenId) {
    document.querySelectorAll('.screen').forEach(screen => {
        screen.classList.remove('active');
    });
    document.getElementById(screenId).classList.add('active');
}
