// User Profile Module
// Handles user profile loading and display

async function loadUserProfile() {
    try {
        const response = await fetch('api/user.php?action=profile');
        const data = await response.json();

        if (data.success) {
            currentUser = data.user;
            updateUserDisplay();
        } else {
            console.error('Failed to load user profile:', data);
            // Show a user-facing message and return to the auth screen to avoid running with a broken state
            // showMessage(data.error || 'Fehler beim Laden des Profils', true);
            // currentUser = null;
            // showScreen('auth-screen');
        }
    } catch (error) {
        console.error('Failed to load profile:', error);
        // showMessage('Fehler beim Laden des Profils', true);
        // currentUser = null;
        // showScreen('auth-screen');
    }
}

function updateUserDisplay() {
    console.log(currentUser);
    if (!currentUser) return;
    
    document.getElementById('username-display').textContent = currentUser.username;
    document.getElementById('level-display').textContent = currentUser.level;
    document.getElementById('wins-display').textContent = currentUser.total_wins;
    document.getElementById('losses-display').textContent = currentUser.total_losses;
    
    // XP bar
    const xpProgress = (currentUser.xp_progress / currentUser.xp_needed) * 100;
    document.getElementById('xp-progress').style.width = xpProgress + '%';
    document.getElementById('xp-current').textContent = currentUser.xp_progress;
    document.getElementById('xp-max').textContent = currentUser.xp_needed;
    
    // Show/hide analytics button based on admin status
    const analyticsBtn = document.getElementById('analytics-btn');
    if (analyticsBtn) {
        analyticsBtn.style.display = currentUser.is_admin ? 'block' : 'none';
    }
}

async function loadLeaderboard() {
    const leaderboardContent = document.getElementById('leaderboard-content');
    
    try {
        const response = await fetch('api/leaderboard.php?action=get');
        const data = await response.json();
        
        if (data.success && data.leaderboard && data.leaderboard.length > 0) {
            displayLeaderboard(data.leaderboard);
        } else {
            leaderboardContent.innerHTML = '<p class="leaderboard-empty">Keine Spieler in der Bestenliste</p>';
        }
    } catch (error) {
        console.error('Failed to load leaderboard:', error);
        leaderboardContent.innerHTML = '<p class="leaderboard-error">Fehler beim Laden der Bestenliste</p>';
    }
}

function displayLeaderboard(players) {
    const leaderboardContent = document.getElementById('leaderboard-content');
    
    let html = '<table><thead><tr>';
    html += '<th>Rang</th>';
    html += '<th>Spieler</th>';
    html += '<th>Level</th>';
    html += '<th>XP</th>';
    html += '<th>Siege</th>';
    html += '<th>Spiele</th>';
    html += '<th>Siegrate</th>';
    html += '</tr></thead><tbody>';
    
    players.forEach((player, index) => {
        const rank = index + 1;
        let rankClass = 'leaderboard-rank';
        if (rank === 1) rankClass += ' leaderboard-rank-1';
        else if (rank === 2) rankClass += ' leaderboard-rank-2';
        else if (rank === 3) rankClass += ' leaderboard-rank-3';
        
        html += '<tr>';
        html += `<td class="${rankClass}">#${rank}</td>`;
        html += `<td><strong>${escapeHtml(player.username)}</strong></td>`;
        html += `<td>${player.level}</td>`;
        html += `<td>${player.xp}</td>`;
        html += `<td>${player.total_wins}</td>`;
        html += `<td>${player.total_games}</td>`;
        html += `<td>${player.win_rate}%</td>`;
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    leaderboardContent.innerHTML = html;
}

function escapeHtml(text) {
    const escapeMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, char => escapeMap[char]);
}
