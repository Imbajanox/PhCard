// Tutorial and Onboarding System
// Provides interactive tutorial for new players

class TutorialSystem {
    constructor() {
        this.currentStep = 0;
        this.tutorialSteps = [
            {
                title: 'Willkommen bei PhCard!',
                message: 'PhCard ist ein rundenbasiertes Kartenspiel. Lass uns die Grundlagen kennenlernen!',
                highlight: null
            },
            {
                title: 'Dein Ziel',
                message: 'Reduziere die HP deines Gegners auf 0, bevor deine eigenen HP aufgebraucht sind. Jeder Spieler startet mit 2000 HP.',
                highlight: null
            },
            {
                title: 'Mana System',
                message: 'Jede Karte kostet Mana zum Ausspielen. Du startest mit 1 Mana und erhältst jede Runde +1 Mana (max. 10). Verwalte dein Mana weise!',
                highlight: '.player-status'
            },
            {
                title: 'Kartentypen',
                message: 'Es gibt zwei Hauptkartentypen:<br><br><strong>Monster:</strong> Bleiben auf dem Feld und können angreifen<br><strong>Zauber:</strong> Haben sofortige Effekte und verschwinden dann',
                highlight: null
            },
            {
                title: 'Monster spielen',
                message: 'Klicke auf eine Monsterkarte in deiner Hand, um sie auf dein Feld zu spielen. Du kannst bis zu 7 Monster gleichzeitig auf dem Feld haben.',
                highlight: '#player-hand'
            },
            {
                title: 'Angreifen',
                message: 'Monster können in der nächsten Runde nach dem Ausspielen angreifen. Wähle ein Monster auf deinem Feld und dann ein gegnerisches Ziel.',
                highlight: '#player-field'
            },
            {
                title: 'Rundenende',
                message: 'Wenn du fertig bist, klicke auf "Runde beenden". Dann ist dein Gegner am Zug. Am Ende deiner Runde ziehst du eine Karte.',
                highlight: '#end-turn-btn'
            },
            {
                title: 'Level & Fortschritt',
                message: 'Gewinne Spiele, um XP zu sammeln und aufzusteigen. Mit jedem Level schaltest du stärkere Karten frei!',
                highlight: '.user-info'
            },
            {
                title: 'Viel Erfolg!',
                message: 'Du bist jetzt bereit! Starte dein erstes Spiel und hab Spaß beim Spielen von PhCard!',
                highlight: null
            }
        ];
    }

    start() {
        this.currentStep = 0;
        this.showStep();
    }

    showStep() {
        if (this.currentStep >= this.tutorialSteps.length) {
            this.complete();
            return;
        }

        const step = this.tutorialSteps[this.currentStep];
        
        // Remove previous highlights
        document.querySelectorAll('.tutorial-highlight').forEach(el => {
            el.classList.remove('tutorial-highlight');
        });

        // Add highlight if specified
        if (step.highlight) {
            const element = document.querySelector(step.highlight);
            if (element) {
                element.classList.add('tutorial-highlight');
            }
        }

        this.showStepModal(step);
    }

    showStepModal(step) {
        const modal = document.getElementById('global-modal-container');
        const titleEl = document.getElementById('modal-title');
        const messageEl = document.getElementById('modal-message');
        const buttonsEl = document.getElementById('modal-buttons');

        titleEl.textContent = step.title;
        messageEl.innerHTML = step.message;
        buttonsEl.innerHTML = '';

        // Show progress
        const progress = document.createElement('div');
        progress.style.margin = '10px 0';
        progress.style.color = '#00ffff';
        progress.textContent = `Schritt ${this.currentStep + 1} von ${this.tutorialSteps.length}`;
        messageEl.appendChild(progress);

        // Add buttons
        if (this.currentStep > 0) {
            const backBtn = document.createElement('button');
            backBtn.textContent = 'Zurück';
            backBtn.className = 'modal-btn modal-btn-secondary';
            backBtn.onclick = () => {
                this.currentStep--;
                this.showStep();
            };
            buttonsEl.appendChild(backBtn);
        }

        const nextBtn = document.createElement('button');
        nextBtn.textContent = this.currentStep === this.tutorialSteps.length - 1 ? 'Fertig' : 'Weiter';
        nextBtn.className = 'modal-btn modal-btn-primary';
        nextBtn.onclick = () => {
            this.currentStep++;
            this.showStep();
        };
        buttonsEl.appendChild(nextBtn);

        const skipBtn = document.createElement('button');
        skipBtn.textContent = 'Überspringen';
        skipBtn.className = 'modal-btn modal-btn-secondary';
        skipBtn.onclick = () => {
            this.complete();
        };
        buttonsEl.appendChild(skipBtn);

        modal.classList.add('active');
        nextBtn.focus();
    }

    complete() {
        // Remove highlights
        document.querySelectorAll('.tutorial-highlight').forEach(el => {
            el.classList.remove('tutorial-highlight');
        });

        // Close modal
        modalSystem.closeModal();

        // Mark tutorial as completed
        localStorage.setItem('phcard_tutorial_completed', 'true');
    }

    isCompleted() {
        return localStorage.getItem('phcard_tutorial_completed') === 'true';
    }

    reset() {
        localStorage.removeItem('phcard_tutorial_completed');
    }
}

// Create global tutorial instance
const tutorialSystem = new TutorialSystem();

// Auto-show tutorial for new users
function checkAndShowTutorial() {
    if (!tutorialSystem.isCompleted()) {
        // Show tutorial after a brief delay
        setTimeout(() => {
            if (confirm('Bist du neu bei PhCard? Möchtest du das Tutorial starten?')) {
                tutorialSystem.start();
            } else {
                tutorialSystem.complete();
            }
        }, 500);
    }
}
