# PhCard - Developer Documentation

## Architektur-Übersicht

PhCard ist ein rundenbasiertes Browser-Kartenspiel mit einer klassischen Client-Server-Architektur:

- **Frontend**: Vanilla JavaScript + HTML5 + CSS3
- **Backend**: PHP mit PDO für Datenbankzugriff
- **Kommunikation**: AJAX (Fetch API)
- **Persistenz**: MySQL/MariaDB

## Projekt-Struktur

```
PhCard/
├── api/                    # Backend API Endpunkte
│   ├── auth.php           # Authentifizierung (Login, Register, Logout)
│   ├── game.php           # Spiellogik (Start, Play, End)
│   └── user.php           # Benutzerdaten (Profile, Cards)
├── public/                 # Frontend-Ressourcen
│   ├── css/
│   │   └── style.css      # Alle Styles
│   └── js/
│       └── app.js         # Client-seitige Logik
├── config.php             # Datenbank- und Spiel-Konfiguration
├── database.sql           # Datenbankschema und Startdaten
├── index.html             # Hauptanwendung
├── install.html           # Installationsassistent
├── test.html              # Testseite für Entwickler
└── README.md              # Benutzer-Dokumentation
```

## Datenbank-Schema

### Tabelle: `users`
Speichert Benutzerkonten und Statistiken.

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,      -- Gehashed mit password_hash()
    email VARCHAR(100) UNIQUE NOT NULL,
    level INT DEFAULT 1,                  -- Aktuelles Level
    xp INT DEFAULT 0,                     -- Gesamte XP
    total_wins INT DEFAULT 0,             -- Anzahl Siege
    total_losses INT DEFAULT 0,           -- Anzahl Niederlagen
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabelle: `cards`
Alle verfügbaren Karten im Spiel.

```sql
CREATE TABLE cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('monster', 'spell') NOT NULL,
    attack INT DEFAULT 0,                 -- Nur für Monster
    defense INT DEFAULT 0,                -- Nur für Monster
    effect VARCHAR(255),                  -- Format: "type:value"
    required_level INT DEFAULT 1,         -- Level zum Freischalten
    rarity ENUM('common', 'rare', 'epic', 'legendary'),
    description TEXT
);
```

**Spell Effects Format:**
- `damage:400` - Fügt 400 Schaden zu
- `heal:300` - Heilt 300 HP
- `boost:500` - Erhöht Angriff um 500
- `shield:1000` - Gewährt 1000 Verteidigung

### Tabelle: `user_cards`
Kartenbesitz der Spieler.

```sql
CREATE TABLE user_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    card_id INT NOT NULL,
    quantity INT DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (card_id) REFERENCES cards(id),
    UNIQUE KEY unique_user_card (user_id, card_id)
);
```

### Tabelle: `game_history`
Protokolliert gespielte Spiele.

```sql
CREATE TABLE game_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ai_level INT NOT NULL,
    result ENUM('win', 'loss') NOT NULL,
    xp_gained INT DEFAULT 0,
    duration_seconds INT,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

## API-Endpunkte

### Authentication API (`api/auth.php`)

#### POST /api/auth.php?action=register
Registriert einen neuen Benutzer.

**Request:**
```
username=player1
password=secret123
email=player1@example.com
```

**Response:**
```json
{
    "success": true,
    "message": "Registration successful"
}
```

**Fehler:**
```json
{
    "success": false,
    "error": "Username or email already exists"
}
```

#### POST /api/auth.php?action=login
Meldet einen Benutzer an.

**Request:**
```
username=player1
password=secret123
```

**Response:**
```json
{
    "success": true,
    "message": "Login successful"
}
```

#### POST /api/auth.php?action=logout
Meldet den aktuellen Benutzer ab.

**Response:**
```json
{
    "success": true,
    "message": "Logged out"
}
```

#### POST /api/auth.php?action=check
Prüft den Authentifizierungsstatus.

**Response:**
```json
{
    "success": true,
    "logged_in": true,
    "username": "player1"
}
```

### User API (`api/user.php`)

#### GET /api/user.php?action=profile
Ruft das Benutzerprofil ab.

**Response:**
```json
{
    "success": true,
    "user": {
        "username": "player1",
        "level": 3,
        "xp": 450,
        "total_wins": 5,
        "total_losses": 2,
        "xp_for_next_level": 600,
        "xp_progress": 150,
        "xp_needed": 300
    }
}
```

#### GET /api/user.php?action=cards
Ruft die Kartensammlung des Benutzers ab.

**Response:**
```json
{
    "success": true,
    "cards": [
        {
            "id": 1,
            "name": "Goblin Scout",
            "type": "monster",
            "attack": 200,
            "defense": 100,
            "effect": null,
            "required_level": 1,
            "rarity": "common",
            "description": "Ein schwacher Goblin-Späher",
            "quantity": 3
        }
    ]
}
```

### Game API (`api/game.php`)

#### POST /api/game.php?action=start
Startet ein neues Spiel.

**Request:**
```
ai_level=3
```

**Response:**
```json
{
    "success": true,
    "game_state": {
        "player_hp": 2000,
        "ai_hp": 2000,
        "ai_level": 3,
        "turn": "player",
        "player_hand": [...],
        "player_field": [],
        "ai_field": [],
        "turn_count": 1
    }
}
```

#### POST /api/game.php?action=play_card
Spielt eine Karte aus der Hand.

**Request:**
```
card_index=0
target=opponent
```

**Response:**
```json
{
    "success": true,
    "message": "Played Goblin Scout (ATK: 200, DEF: 100)",
    "game_state": {
        "player_hp": 2000,
        "ai_hp": 2000,
        "player_hand": [...],
        "player_field": [...],
        "ai_field": [...]
    }
}
```

#### POST /api/game.php?action=end_turn
Beendet die Spielerrunde und führt die Battle-Phase aus.

**Response:**
```json
{
    "success": true,
    "battle_log": [
        "Goblin Scout attacks directly for 200 damage",
        "AI played Dark Knight (ATK: 500, DEF: 400)"
    ],
    "ai_actions": [
        "AI played Dark Knight (ATK: 500, DEF: 400)"
    ],
    "game_state": {...},
    "winner": null
}
```

#### POST /api/game.php?action=end_game
Beendet das Spiel und speichert das Ergebnis.

**Request:**
```
result=win
```

**Response:**
```json
{
    "success": true,
    "result": "win",
    "xp_gained": 120,
    "new_level": 4,
    "leveled_up": true,
    "unlocked_cards": [
        {
            "id": 10,
            "name": "Epic Card",
            "type": "monster",
            ...
        }
    ]
}
```

## Spielmechanik

### Level-System

XP-Anforderungen für Level-Ups:

```php
$LEVEL_REQUIREMENTS = [
    1 => 0,
    2 => 100,
    3 => 300,
    4 => 600,
    5 => 1000,
    6 => 1500,
    7 => 2100,
    8 => 2800,
    9 => 3600,
    10 => 4500
];
```

### XP-Berechnung

- Basis XP pro Sieg: 100
- Multiplikator für höhere KI-Level: 1 + (AI_LEVEL - 1) × 0.2
- Beispiel: KI Level 5 = 100 × (1 + 4 × 0.2) = 180 XP

### Battle-System

1. **Spieler-Phase:**
   - Spieler kann Karten spielen
   - Monster werden aufs Feld gelegt
   - Zauber haben sofortige Effekte

2. **Battle-Phase (bei "Runde beenden"):**
   - Spieler-Monster greifen KI-Monster/direkt an
   - KI spielt ihre Karten
   - KI-Monster greifen Spieler-Monster/direkt an
   - Spieler zieht eine neue Karte

3. **Angriffsmechanik:**
   - Mit Verteidiger: `Schaden = max(0, ATK - DEF)`
   - Direktangriff: `Schaden = ATK`
   - Monster mit DEF ≤ ATK werden zerstört

### KI-Verhalten

Die KI-Schwierigkeit skaliert mit dem Level:

- **Level 1-2:** Spielt 1 Karte pro Runde, nur Level-1 Karten
- **Level 3-4:** Spielt 2 Karten, Zugriff auf Level-6 Karten
- **Level 5:** Spielt 2 Karten, Zugriff auf Level-10 Karten

KI-Entscheidungen:
- Bevorzugt Heilzauber wenn HP < 50%
- Spielt zufällig aus verfügbaren Karten
- Wird in Zukunft intelligenter

## Frontend-Architektur

### Screen-Management

Die App verwendet ein einfaches Screen-System:

```javascript
function showScreen(screenId) {
    document.querySelectorAll('.screen').forEach(screen => {
        screen.classList.remove('active');
    });
    document.getElementById(screenId).classList.add('active');
}
```

Screens:
- `auth-screen` - Login/Registrierung
- `menu-screen` - Hauptmenü
- `game-setup-screen` - KI-Level auswählen
- `collection-screen` - Kartensammlung
- `game-screen` - Aktives Spiel

### Game State Management

Der Spielzustand wird in der Session (Server) und als lokale Variable (Client) verwaltet:

```javascript
let gameState = {
    player_hp: 2000,
    ai_hp: 2000,
    ai_level: 1,
    turn: 'player',
    player_hand: [],
    player_field: [],
    ai_field: [],
    turn_count: 1
};
```

### Card Rendering

Karten werden dynamisch generiert:

```javascript
function createCardElement(card, showQuantity = false) {
    const cardEl = document.createElement('div');
    cardEl.className = `card ${card.type}`;
    // ... HTML-Generierung
    return cardEl;
}
```

## Sicherheitsüberlegungen

1. **Passwort-Hashing:** Verwendet `password_hash()` und `password_verify()`
2. **SQL-Injection-Schutz:** PDO mit Prepared Statements
3. **Session-Management:** PHP Sessions für Authentifizierung
4. **XSS-Schutz:** JSON-Encoding für API-Responses

**Noch zu implementieren:**
- CSRF-Tokens für Formulare
- Rate-Limiting für API-Endpunkte
- Input-Validierung verbessern
- HTTPS erzwingen in Produktion

## Erweiterungsmöglichkeiten

### Features für die Zukunft

1. **Erweiterte KI:**
   - Strategische Entscheidungen
   - Kartenauswahl basierend auf Spielsituation
   - Verteidigungslogik

2. **Mehr Spielmodi:**
   - Multiplayer (PvP)
   - Turniere
   - Rangliste

3. **Erweiterte Kartenmechaniken:**
   - Kartensynergien
   - Spezialeffekte
   - Ausrüstungskarten

4. **UI-Verbesserungen:**
   - Animationen
   - Soundeffekte
   - Besseres Feedback

5. **Progression:**
   - Achievements
   - Tägliche Quests
   - Kartenhandel

## Testing

### Manuelle Tests

1. Öffne `test.html` für System-Checks
2. Teste API-Endpunkte einzeln
3. Spiele vollständige Runden

### Test-Szenarien

- [ ] Benutzerregistrierung
- [ ] Login/Logout
- [ ] Spiel starten (verschiedene KI-Level)
- [ ] Karten spielen (Monster + Zauber)
- [ ] Battle-Phase
- [ ] Sieg/Niederlage
- [ ] Level-Up
- [ ] Kartenfreischaltung

## Troubleshooting

### Häufige Probleme

**Problem:** "Database connection failed"
- **Lösung:** Prüfe `config.php` und MySQL-Zugangsdaten

**Problem:** "No active game"
- **Lösung:** Session abgelaufen, neu starten

**Problem:** API gibt keinen JSON zurück
- **Lösung:** PHP-Fehler, prüfe `error_log`

**Problem:** Karten werden nicht angezeigt
- **Lösung:** Prüfe ob `database.sql` importiert wurde

## Performance-Tipps

1. **Datenbank:**
   - Indizes auf foreign keys sind bereits definiert
   - Bei vielen Benutzern: Caching für Kartendaten

2. **Frontend:**
   - Minimize CSS/JS für Produktion
   - Verwende Sprite-Sheets für Kartenbilder

3. **API:**
   - Implementiere Response-Caching
   - Reduziere Datenbankabfragen

## Deployment

### Produktions-Checkliste

- [ ] HTTPS aktivieren
- [ ] `error_reporting` ausschalten in `config.php`
- [ ] Datenbankpasswörter ändern
- [ ] Backup-Strategie implementieren
- [ ] Monitoring einrichten
- [ ] CSS/JS minifizieren

### Umgebungsvariablen

Für Produktion sollte `config.php` Umgebungsvariablen nutzen:

```php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'phcard');
```

## Lizenz

MIT License - siehe Hauptdokumentation

## Kontakt

Bei Fragen oder Problemen öffne ein Issue auf GitHub.
