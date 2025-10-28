# Zusammenfassung der Schwierigkeitsanpassungen / Summary of Difficulty Adjustments

## Problem / Issue

**Deutsch:**
Die KI-Schwierigkeitsstufen waren nicht ausgeglichen. Spieler berichteten, dass selbst die leichteste Stufe (Level 1) kaum zu schlagen war. Die Analyse zeigte, dass die KI auf allen Stufen nahezu optimal spielte.

**English:**
The AI difficulty levels were not balanced. Players reported that even the easiest level (Level 1) was nearly impossible to beat. Analysis showed the AI played nearly optimally at all levels.

## Lösung / Solution

Die KI wurde überarbeitet, um auf niedrigeren Schwierigkeitsstufen tatsächlich schwächer zu spielen:

*The AI has been reworked to actually play weaker at lower difficulty levels:*

### Änderungen / Changes

1. **Keyword-Bewertung skaliert nach Schwierigkeit**
   - Level 1: 70% Reduktion (0.3x)
   - Level 2: 40% Reduktion (0.6x)
   - Level 3: Normal (1.0x)
   - Level 4: Erhöht (1.5x)
   - Level 5+: Stark erhöht (2.0x)
   
   *Keyword evaluation scales with difficulty*

2. **Kartenlimit pro Zug**
   - Level 1: Max 2 Karten (verschwendet Mana)
   - Level 2: Max 3 Karten
   - Level 3: Max 4 Karten
   - Level 4+: Unbegrenzt
   
   *Card limit per turn*

3. **Verfügbare Karten zur Auswahl**
   - Level 1: 5 Karten
   - Level 2: 6 Karten
   - Level 3: 7 Karten
   - Level 4+: 8 Karten
   
   *Available cards to choose from*

4. **Zufälligkeit in Kartenbewertung**
   - Level 1: ±60% Variation
   - Level 2: ±30% Variation
   - Level 3: ±15% Variation
   - Level 4+: Keine Variation (optimal)
   
   *Randomness in card scoring*

5. **Strategische Fehler**
   - Level 1: Keine Brettlagenbewertung, kein Lethal-Erkennung, verschwendet Heilzauber
   - Level 2: Minimale Strategie
   - Level 3: Normale Strategie
   - Level 4+: Optimale Strategie
   
   *Strategic mistakes*

## Erwartetes Verhalten / Expected Behavior

### Level 1 - Sehr Leicht / Very Easy
**Für absolute Anfänger / For complete beginners**

Die KI:
- Spielt oft die falschen Karten
- Lässt viel Mana ungenutzt
- Erkennt keine Lethal-Situationen
- Macht viele strategische Fehler

*The AI:*
- *Often plays wrong cards*
- *Leaves much mana unused*
- *Doesn't recognize lethal situations*
- *Makes many strategic mistakes*

**Dies sollte für die meisten Spieler gewinnbar sein!**
*This should be winnable for most players!*

### Level 2 - Leicht / Easy
**Für Anfänger mit etwas Übung / For beginners with some practice**

Die KI macht noch Fehler, aber weniger häufig. Immer noch gut für Spieler, die das Spiel lernen.

*The AI still makes mistakes, but less frequently. Still good for players learning the game.*

### Level 3 - Mittel / Medium
**Für erfahrene Spieler / For experienced players**

Ausgewogenes Spiel. Die KI spielt solide mit gelegentlichen kleinen Fehlern.

*Balanced gameplay. The AI plays solidly with occasional minor mistakes.*

### Level 4-5 - Schwer/Sehr Schwer / Hard/Very Hard
**Für Experten / For experts**

Die KI spielt optimal oder nahezu optimal. Erfordert gute Decks und strategisches Denken.

*The AI plays optimally or near-optimally. Requires good decks and strategic thinking.*

## Technische Details / Technical Details

**Geänderte Datei / Modified File:**
- `api/game.php` - `scoreCard()` und `performAITurn()` Funktionen

**Neue Dateien / New Files:**
- `DIFFICULTY_BALANCING.md` - Ausführliche Dokumentation
- `test_difficulty_balancing.sh` - Automatisierte Tests

**Tests / Testing:**
```bash
./test_difficulty_balancing.sh
```

Alle Tests bestanden ✓
*All tests passed ✓*

## Hinweise für Spieler / Notes for Players

1. **Neue Spieler sollten mit Level 1 beginnen**
   - *New players should start with Level 1*

2. **Steigere die Schwierigkeit graduell**
   - Wenn du Level 1 regelmäßig gewinnst, versuche Level 2
   - *If you regularly win Level 1, try Level 2*

3. **Level 3 ist der Standardtest für ein gutes Deck**
   - *Level 3 is the standard test for a good deck*

4. **Level 4-5 sind für Herausforderungen**
   - Erfordern optimierte Decks und gutes Spielverständnis
   - *Require optimized decks and good game understanding*

## Feedback / Rückmeldung

Falls die Schwierigkeitsbalancierung immer noch nicht passt, bitte melden:
- Welches Level
- Ungefähre Gewinnrate
- Spielerlevel und Deckqualität

*If the difficulty balancing still doesn't feel right, please report:*
- *Which level*
- *Approximate win rate*
- *Player level and deck quality*

Die Werte können bei Bedarf weiter angepasst werden.
*The values can be further adjusted if needed.*
