# PhCard - Browser-basiertes Kartenspiel

Ein rundenbasiertes Browser-Kartenspiel gegen eine KI mit Level-System, XP und Kartenprogression.

## Features

### Core Game Features
- **Rundenbasiertes Gameplay**: Spieler und KI wechseln sich ab, um Monster- und Zauberkarten zu spielen
- **Monster- und Zauberkarten**: Verschiedene Kartentypen mit unterschiedlichen Effekten
- **KI-Gegner**: Skalierbare KI mit 5 Schwierigkeitsstufen
- **Level-System**: Sammle XP durch Siege und steige im Level auf
- **Kartenfreischaltung**: Höhere Level schalten mächtigere Karten frei
- **Progression**: Stärkere KI-Gegner werden mit höherem Level verfügbar
- **Datenbank-Backend**: PHP verwaltet Benutzer, Karten und Spielstände
- **AJAX-basierte UI**: JavaScript steuert die Benutzeroberfläche

### 🆕 Extensibility Features (NEW!)
- **Plugin System**: Drop-in plugins für custom Funktionalität
- **Event System**: Event-driven Architektur für Spielaktionen
- **Effect Registry**: Einfaches Hinzufügen neuer Karteneffekte
- **Card Factory**: JSON-basierte Kartenerstellung und -import
- **Quest System**: Konfigurierbare Quests mit verschiedenen Zielen
- **Achievement System**: Fortschritts-Tracking und Belohnungen
- **Card Sets**: Organisiere Karten in Erweiterungen
- **Developer Tools**: CLI-Tools und umfassende Dokumentation

> 📚 **Für Entwickler**: Siehe [EXTENSIBILITY_README.md](EXTENSIBILITY_README.md) für Details zum Erweitern des Spiels

## Technologie-Stack

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: PHP 7.4+
- **Datenbank**: MySQL/MariaDB
- **AJAX**: Fetch API für asynchrone Kommunikation

## Installation

### Voraussetzungen

- PHP 7.4 oder höher
- MySQL 5.7 oder höher / MariaDB 10.2+
- Webserver (Apache/Nginx)

### Setup

1. **Repository klonen**
   ```bash
   git clone https://github.com/Imbajanox/PhCard.git
   cd PhCard
   ```

2. **Datenbank konfigurieren**
   - Erstelle eine MySQL-Datenbank
   - Importiere das Schema:
     ```bash
     mysql -u root -p < database.sql
     ```

3. **Konfiguration anpassen**
   - Öffne `config.php`
   - Passe die Datenbankverbindung an:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'dein_benutzer');
     define('DB_PASS', 'dein_passwort');
     define('DB_NAME', 'phcard');
     ```

4. **Webserver konfigurieren**
   - Stelle sicher, dass der Webserver auf das Projektverzeichnis zeigt
   - Die `index.html` sollte die Startseite sein
   - **Für Apache:**
     - Entwicklung: Die aktuelle `.htaccess` ist für Produktion konfiguriert
     - Wenn du Fehler sehen möchtest, verwende `.htaccess.development`:
       ```bash
       cp .htaccess.development .htaccess
       ```
   - **Für Nginx:** Siehe separate Nginx-Konfiguration im Projekt

5. **Starten**
   - Öffne den Browser und navigiere zur Anwendung
   - Registriere einen neuen Account
   - Beginne zu spielen!

## Spielanleitung

### Spielstart

1. Registriere einen Account oder melde dich an
2. Wähle einen KI-Schwierigkeitsgrad (1-5)
3. Das Spiel beginnt mit 5 zufälligen Karten in deiner Hand

### Spielablauf

1. **Kartenphase**: Spiele Monster- oder Zauberkarten aus deiner Hand
   - **Monster**: Werden auf dein Feld platziert und greifen in der Battle-Phase an
   - **Zauber**: Haben sofortige Effekte (Schaden, Heilung, etc.)

2. **Runde beenden**: Klicke "Runde beenden"
   - Deine Monster greifen die KI an
   - Die KI spielt ihre Karten
   - KI-Monster greifen dich an
   - Du ziehst eine neue Karte

3. **Sieg**: Reduziere die Lebenspunkte des Gegners auf 0

### Progression

- **XP sammeln**: Gewinne Spiele, um XP zu erhalten
- **Level aufsteigen**: Erreiche XP-Schwellenwerte für neue Level
- **Karten freischalten**: Höhere Level schalten mächtigere Karten frei
- **Stärkere KI**: Spiele gegen höhere KI-Level für mehr XP

## Kartentypen

### Monster-Karten
- Haben Angriffs- (ATK) und Verteidigungs- (DEF) Werte
- Greifen in jeder Runde an
- Können gegnerische Monster blockieren

### Zauber-Karten
- Sofortige Effekte:
  - **Schaden**: Fügt dem Gegner direkten Schaden zu
  - **Heilung**: Stellt deine Lebenspunkte wieder her
  - **Boost**: Erhöht Angriffswerte
  - **Schild**: Gewährt temporäre Verteidigung

## API-Endpunkte

### Authentifizierung (`api/auth.php`)
- `POST /api/auth.php?action=register` - Neuen Benutzer registrieren
- `POST /api/auth.php?action=login` - Benutzer anmelden
- `POST /api/auth.php?action=logout` - Benutzer abmelden
- `POST /api/auth.php?action=check` - Authentifizierungsstatus prüfen

### Benutzerdaten (`api/user.php`)
- `GET /api/user.php?action=profile` - Benutzerprofil abrufen
- `GET /api/user.php?action=cards` - Kartensammlung abrufen

### Spiel (`api/game.php`)
- `POST /api/game.php?action=start` - Neues Spiel starten
- `POST /api/game.php?action=play_card` - Karte spielen
- `POST /api/game.php?action=end_turn` - Runde beenden
- `POST /api/game.php?action=end_game` - Spiel beenden und Ergebnis speichern

## Datenbankschema

### Tabellen

- **users**: Benutzerkonten und Stats
- **cards**: Alle verfügbaren Karten
- **user_cards**: Kartenbesitz der Spieler
- **game_history**: Spielhistorie und Ergebnisse

## Entwicklung

### Projektstruktur

```
PhCard/
├── api/
│   ├── auth.php       # Authentifizierung
│   ├── game.php       # Spiellogik
│   └── user.php       # Benutzerdaten
├── public/
│   ├── css/
│   │   └── style.css  # Styling
│   └── js/
│       └── app.js     # Frontend-Logik
├── config.php         # Konfiguration
├── database.sql       # Datenbankschema
├── index.html         # Hauptseite
└── README.md
```

## Erweiterbarkeit (für Entwickler)

PhCard verfügt über ein umfassendes Extensibility Framework, das es einfach macht, neue Features und Inhalte hinzuzufügen:

### Schnelle Erweiterung

```bash
# Neue Karten aus JSON importieren
php import_cards.php my_cards.json EXPANSION1

# Quest/Achievement System installieren
mysql -u root -p phcard < database_quest_achievement_system.sql

# Extensibility Tests ausführen
./test_extensibility.sh
```

### Plugin-Beispiel

Erstelle `plugins/plugin_custom.php`:

```php
<?php
// Neuen Karteneffekt registrieren
CardEffectRegistry::register('super_damage', function($context) {
    $context['gameState']['ai_hp'] -= 1000;
    return $context['gameState'];
});

// Auf Spielereignisse reagieren
GameEventSystem::on('game_end', function($data) {
    error_log("Game ended: {$data['result']}");
    return $data;
});
?>
```

### Dokumentation für Entwickler

- **[EXTENSIBILITY_README.md](EXTENSIBILITY_README.md)** - Überblick über alle Features
- **[EXTENSION_GUIDE.md](EXTENSION_GUIDE.md)** - Detaillierte Tutorials und Beispiele
- **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** - Schnellreferenz für häufige Aufgaben
- **[EXPANDABILITY_SUMMARY.md](EXPANDABILITY_SUMMARY.md)** - Zusammenfassung der Verbesserungen

### Verfügbare Erweiterungspunkte

- ✅ **Card Effects** - Neue Karteneffekte via Registry
- ✅ **Game Events** - Event Listener für Spielaktionen
- ✅ **Plugins** - Drop-in Erweiterungen
- ✅ **Quests** - Konfigurierbare Aufgaben
- ✅ **Achievements** - Fortschritts-Tracking
- ✅ **Card Sets** - Organisiere Erweiterungen
- ✅ **JSON Import** - Massenimport von Karten

## Lizenz

MIT License

## Autor

Imbajanox
