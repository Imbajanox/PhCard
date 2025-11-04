// How to Play Modal
// Comprehensive game guide accessible from main menu

function showHowToPlayModal() {
    const modal = document.getElementById('global-modal-container');
    const contentWrapper = document.getElementById('modal-content-wrapper');
    
    // Store original content
    const originalContent = contentWrapper.innerHTML;
    
    // Create custom content for How to Play
    contentWrapper.innerHTML = `
        <h2 style="color: #00ff00; margin-bottom: 20px;">Wie man spielt</h2>
        <div style="max-height: 60vh; overflow-y: auto; text-align: left; padding: 0 20px;">
            
            <h3 style="color: #00ffff; margin-top: 15px;">🎯 Spielziel</h3>
            <p>Reduziere die HP deines Gegners auf 0, bevor deine eigenen HP aufgebraucht sind. Jeder Spieler startet mit 2000 HP.</p>
            
            <h3 style="color: #00ffff; margin-top: 15px;">⚡ Mana System</h3>
            <p>• Jede Karte kostet Mana zum Ausspielen<br>
            • Du startest mit 1 Mana und erhältst jede Runde +1 (max. 10)<br>
            • Dein Mana wird jede Runde vollständig aufgefüllt</p>
            
            <h3 style="color: #00ffff; margin-top: 15px;">🃏 Kartentypen</h3>
            <p><strong style="color: #ff00ff;">Monster:</strong> Bleiben auf dem Feld und können angreifen oder blocken<br>
            <strong style="color: #ffff00;">Zauber:</strong> Haben sofortige Effekte (Schaden, Heilung, etc.) und verschwinden dann</p>
            
            <h3 style="color: #00ffff; margin-top: 15px;">🎮 Spielablauf</h3>
            <p>1. <strong>Karten ziehen:</strong> Zu Beginn ziehst du 5 Karten<br>
            2. <strong>Karten spielen:</strong> Klicke auf Karten in deiner Hand, um sie zu spielen<br>
            3. <strong>Angreifen:</strong> Monster können nach einer Runde Wartezeit angreifen<br>
            4. <strong>Runde beenden:</strong> Klicke "Runde beenden" und ziehe eine Karte</p>
            
            <h3 style="color: #00ffff; margin-top: 15px;">⚔️ Kampf</h3>
            <p>• Wähle ein Monster auf deinem Feld<br>
            • Klicke auf ein gegnerisches Monster oder den Gegner direkt<br>
            • Monster mit <strong style="color: #ff0000;">Taunt</strong> müssen zuerst angegriffen werden<br>
            • Beide Monster erleiden Schaden basierend auf ihrem Angriffswert</p>
            
            <h3 style="color: #00ffff; margin-top: 15px;">🌟 Spezialfähigkeiten</h3>
            <p><strong style="color: #ff0000;">Taunt:</strong> Muss zuerst angegriffen werden<br>
            <strong style="color: #ffff00;">Divine Shield:</strong> Negiert den ersten Schaden<br>
            <strong style="color: #ff00ff;">Stealth:</strong> Kann eine Runde nicht angegriffen werden<br>
            <strong style="color: #00ffff;">Windfury:</strong> Kann zweimal pro Runde angreifen<br>
            <strong style="color: #ff0000;">Lifesteal:</strong> Heilt dich um den verursachten Schaden</p>
            
            <h3 style="color: #00ffff; margin-top: 15px;">📈 Fortschritt</h3>
            <p>• Gewinne Spiele, um XP zu sammeln<br>
            • Steige im Level auf, um neue Karten freizuschalten<br>
            • Baue verschiedene Decks im Deck Builder<br>
            • Stelle dich stärkeren KI-Gegnern oder anderen Spielern</p>
            
            <h3 style="color: #00ffff; margin-top: 15px;">💡 Tipps</h3>
            <p>• Verwalte dein Mana effizient<br>
            • Baue ein ausgewogenes Deck (Monster + Zauber)<br>
            • Beachte die Manakurve (verschiedene Kosten)<br>
            • Nutze Zauberkarten zur richtigen Zeit<br>
            • Schütze deine HP und greife strategisch an</p>
            
        </div>
        <div id="modal-buttons" style="margin-top: 20px;">
            <button class="modal-btn modal-btn-primary" onclick="closeHowToPlayModal()">Schließen</button>
            <button class="modal-btn modal-btn-secondary" onclick="startTutorialFromHelp()">Tutorial starten</button>
        </div>
    `;
    
    modal.classList.add('active');
    
    // Store function to restore original modal
    window.closeHowToPlayModal = () => {
        contentWrapper.innerHTML = originalContent;
        modal.classList.remove('active');
    };
    
    window.startTutorialFromHelp = () => {
        closeHowToPlayModal();
        tutorialSystem.start();
    };
}
