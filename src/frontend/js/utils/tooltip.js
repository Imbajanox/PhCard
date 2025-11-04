// Tooltip System
// Provides contextual help for game mechanics

class TooltipSystem {
    constructor() {
        this.tooltip = null;
        this.createTooltip();
        this.tooltipData = {
            'mana': {
                title: 'Mana',
                text: 'Ressource zum Ausspielen von Karten. Du erhältst jede Runde +1 Mana (max. 10).'
            },
            'hp': {
                title: 'Trefferpunkte (HP)',
                text: 'Deine Lebenspunkte. Wenn sie auf 0 fallen, hast du verloren.'
            },
            'xp': {
                title: 'Erfahrungspunkte (XP)',
                text: 'Sammle XP durch Siege, um im Level aufzusteigen und neue Karten freizuschalten.'
            },
            'level': {
                title: 'Level',
                text: 'Dein Fortschritt. Höhere Level schalten stärkere Karten und schwierigere Gegner frei.'
            },
            'monster': {
                title: 'Monsterkarte',
                text: 'Bleibt auf dem Feld und kann angreifen. Hat Angriffs- und Verteidigungswerte.'
            },
            'spell': {
                title: 'Zauberkarte',
                text: 'Hat einen sofortigen Effekt (Schaden, Heilung, etc.) und verschwindet dann.'
            },
            'attack': {
                title: 'Angriffswert',
                text: 'Der Schaden, den dieses Monster verursacht.'
            },
            'defense': {
                title: 'Verteidigungswert',
                text: 'Die HP dieses Monsters. Bei 0 wird es zerstört.'
            },
            'taunt': {
                title: 'Taunt',
                text: 'Dieses Monster muss zuerst angegriffen werden, bevor andere Ziele gewählt werden können.'
            },
            'divine_shield': {
                title: 'Divine Shield',
                text: 'Negiert den ersten Schaden, der diesem Monster zugefügt wird.'
            },
            'stealth': {
                title: 'Stealth',
                text: 'Kann für eine Runde nicht angegriffen werden. Verschwindet nach dem ersten Angriff.'
            },
            'windfury': {
                title: 'Windfury',
                text: 'Kann zweimal pro Runde angreifen statt nur einmal.'
            },
            'lifesteal': {
                title: 'Lifesteal',
                text: 'Heilt dich um den Schaden, den dieses Monster verursacht.'
            },
            'mulligan': {
                title: 'Mulligan',
                text: 'Zu Spielbeginn kannst du bis zu 3 Karten zurücklegen und neue ziehen.'
            },
            'deck': {
                title: 'Deck',
                text: 'Deine Kartensammlung für ein Spiel. Ein Deck muss genau 30 Karten enthalten.'
            },
            'hand': {
                title: 'Hand',
                text: 'Karten, die du aktuell spielen kannst. Maximum 10 Karten.'
            },
            'field': {
                title: 'Spielfeld',
                text: 'Hier stehen deine Monster. Maximum 7 Monster gleichzeitig.'
            }
        };
    }

    createTooltip() {
        const existing = document.getElementById('game-tooltip');
        if (existing) {
            existing.remove();
        }

        this.tooltip = document.createElement('div');
        this.tooltip.id = 'game-tooltip';
        this.tooltip.className = 'game-tooltip';
        this.tooltip.style.display = 'none';
        document.body.appendChild(this.tooltip);
    }

    show(key, x, y) {
        const data = this.tooltipData[key];
        if (!data) return;

        this.tooltip.innerHTML = `
            <div class="tooltip-title">${data.title}</div>
            <div class="tooltip-text">${data.text}</div>
        `;

        this.tooltip.style.display = 'block';
        this.tooltip.style.left = x + 'px';
        this.tooltip.style.top = y + 'px';

        // Adjust position if tooltip goes off screen
        const rect = this.tooltip.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            this.tooltip.style.left = (x - rect.width - 10) + 'px';
        }
        if (rect.bottom > window.innerHeight) {
            this.tooltip.style.top = (y - rect.height - 10) + 'px';
        }
    }

    hide() {
        this.tooltip.style.display = 'none';
    }

    addToElement(element, key) {
        element.classList.add('has-tooltip');
        element.dataset.tooltipKey = key;
        
        element.addEventListener('mouseenter', (e) => {
            this.show(key, e.pageX + 10, e.pageY + 10);
        });

        element.addEventListener('mousemove', (e) => {
            if (this.tooltip.style.display === 'block') {
                this.show(key, e.pageX + 10, e.pageY + 10);
            }
        });

        element.addEventListener('mouseleave', () => {
            this.hide();
        });
    }

    // Add tooltips to common game elements
    initializeGameTooltips() {
        // Add tooltips to player mana
        const manaDisplay = document.getElementById('player-mana-display');
        if (manaDisplay) {
            this.addToElement(manaDisplay, 'mana');
        }

        // Add tooltips to HP bars
        const playerHpText = document.getElementById('player-hp-text');
        if (playerHpText) {
            this.addToElement(playerHpText, 'hp');
        }

        // Add tooltips to XP bar
        const xpText = document.querySelector('.xp-text');
        if (xpText) {
            this.addToElement(xpText, 'xp');
        }

        // Add tooltips to level display
        const levelDisplay = document.getElementById('level-display');
        if (levelDisplay && levelDisplay.parentElement) {
            this.addToElement(levelDisplay.parentElement, 'level');
        }

        // Add tooltips to hand
        const handTitle = document.querySelector('.hand-container h4');
        if (handTitle) {
            this.addToElement(handTitle, 'hand');
        }

        // Add tooltips to field
        const fieldTitles = document.querySelectorAll('.field h4');
        fieldTitles.forEach(title => {
            this.addToElement(title, 'field');
        });
    }
}

// Create global tooltip instance
const tooltipSystem = new TooltipSystem();
