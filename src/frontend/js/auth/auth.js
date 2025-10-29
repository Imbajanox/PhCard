// Authentication Module
// Handles user login, registration, logout, and authentication checks

function showAuthScreen(formType) {
    showScreen('auth-screen');
    if (formType === 'register') {
        showRegister();
    } else {
        showLogin();
    }
}

function showLogin() {
    document.getElementById('login-form').classList.add('active');
    document.getElementById('register-form').classList.remove('active');
}

function showRegister() {
    document.getElementById('register-form').classList.add('active');
    document.getElementById('login-form').classList.remove('active');
}

function showMessage(message, isError = false) {
    const messageEl = document.getElementById('auth-message');
    messageEl.textContent = message;
    messageEl.className = 'message ' + (isError ? 'error' : 'success');
}

async function register() {
    const username = document.getElementById('register-username').value;
    const email = document.getElementById('register-email').value;
    const password = document.getElementById('register-password').value;
    
    try {
        const response = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'register', username, email, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.message, false);
            setTimeout(() => {
                loadUserProfile();
                showScreen('menu-screen');
            }, 1000);
        } else {
            showMessage(data.error, true);
        }
    } catch (error) {
        showMessage('Fehler bei der Registrierung', true);
    }
}

async function login() {
    const username = document.getElementById('login-username').value;
    const password = document.getElementById('login-password').value;
    
    try {
        const response = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'login', username, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.message, false);
            setTimeout(() => {
                loadUserProfile();
                showScreen('menu-screen');
            }, 1000);
        } else {
            showMessage(data.error, true);
        }
    } catch (error) {
        showMessage('Fehler beim Login', true);
    }
}

async function logout() {
    try {
        await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'logout' })
        });
        
        currentUser = null;
        showScreen('landing-screen');
        loadLeaderboard(); // Reload leaderboard when user logs out
    } catch (error) {
        console.error('Logout error:', error);
    }
}

async function checkAuth() {
    try {
        const response = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'check' })
        });
        
        const data = await response.json();
        //console.log(data);
        if (data.logged_in) {
            loadUserProfile();
            showScreen('menu-screen');
        } else {
            showScreen('landing-screen');
        }
    } catch (error) {
        showScreen('landing-screen');
    }
}
