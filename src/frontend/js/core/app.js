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


// Call the function to load the header when the page loads
document.addEventListener('DOMContentLoaded', loadHeader);