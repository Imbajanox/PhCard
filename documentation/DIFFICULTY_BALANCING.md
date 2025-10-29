# Schwierigkeitsbalancierung / Difficulty Balancing

## Problem

Die ursprüngliche Implementierung der Schwierigkeitsstufen war unausgeglichen. Die KI spielte auf **allen Schwierigkeitsstufen nahezu optimal**, was bedeutete, dass selbst die "leichte" Stufe (Level 1) für neue Spieler fast unschlagbar war.

**The original difficulty level implementation was unbalanced.** The AI played nearly optimally at **all difficulty levels**, meaning even the "easy" level (Level 1) was nearly impossible for new players to beat.

## Root Causes / Grundursachen

1. **Gleiche intelligente Kartenbewertung auf allen Stufen** - Die `scoreCard()`-Funktion verwendete ausgeklügelte Strategien auch bei Level 1
   - *Same intelligent card scoring at all levels* - The `scoreCard()` function used sophisticated strategies even at Level 1

2. **Zu hohe Keyword-Multiplikatoren** - Selbst `$aiLevel * 15` bei Level 1 machte Keywords extrem wertvoll
   - *Keyword multipliers too high* - Even `$aiLevel * 15` at Level 1 made keywords extremely valuable

3. **Perfekte Mana-Effizienz** - Die KI spielte immer alle verfügbaren Karten aus
   - *Perfect mana efficiency* - The AI always played all available cards

4. **Keine Fehler** - Die KI machte nie Fehler oder suboptimale Züge
   - *No mistakes* - The AI never made mistakes or suboptimal moves

## Solution / Lösung

Die Schwierigkeitsbalancierung wurde überarbeitet, um tatsächlich unterschiedliche Spielstärken zu bieten:

*The difficulty balancing has been reworked to provide genuinely different play strengths:*

### Level 1 (Sehr Leicht / Very Easy)

**Für neue Spieler / For New Players**

- **Keyword-Bewertung**: 70% reduziert (Multiplikator: 0.3)
  - *Keyword evaluation: 70% reduced (multiplier: 0.3)*
- **Kartenlimit**: 5 Karten zur Auswahl
  - *Card limit: 5 cards to choose from*
- **Züge pro Runde**: Maximal 2 Karten
  - *Plays per turn: Maximum 2 cards*
- **Zufälligkeit**: ±60% Zufallsfaktor bei Kartenbewertung
  - *Randomness: ±60% random factor in card scoring*
- **Strategien**: 
  - Keine Berücksichtigung der Brettlage
  - Kein Verständnis für Mana-Effizienz
  - Kein Erkennen von Lethal-Situationen
  - Überbewertet Heilzauber
  - *No board state consideration*
  - *No mana efficiency understanding*
  - *No lethal recognition*
  - *Overvalues healing spells*

**Resultat**: Die KI macht viele Fehler, lässt Mana ungenutzt, spielt falsche Karten zur falschen Zeit.

*Result: The AI makes many mistakes, leaves mana unused, plays wrong cards at the wrong time.*

### Level 2 (Leicht / Easy)

**Für Anfänger mit etwas Erfahrung / For Beginners with Some Experience**

- **Keyword-Bewertung**: 40% reduziert (Multiplikator: 0.6)
- **Kartenlimit**: 6 Karten zur Auswahl
- **Züge pro Runde**: Maximal 3 Karten
- **Zufälligkeit**: ±30% Zufallsfaktor
- **Strategien**: 
  - Geringe Berücksichtigung der Brettlage
  - Minimales Verständnis für Mana-Effizienz
  - Kein Erkennen von Lethal
  - Etwas bessere Heilzauber-Nutzung

**Resultat**: Die KI macht noch Fehler, aber weniger als Level 1. Bessere Kartenauswahl.

*Result: The AI still makes mistakes, but fewer than Level 1. Better card selection.*

### Level 3 (Mittel / Medium)

**Für erfahrene Spieler / For Experienced Players**

- **Keyword-Bewertung**: Normal (Multiplikator: 1.0)
- **Kartenlimit**: 7 Karten zur Auswahl
- **Züge pro Runde**: Maximal 4 Karten
- **Zufälligkeit**: ±15% Zufallsfaktor
- **Strategien**: 
  - Normale Brettlagenbewertung
  - Gutes Mana-Effizienz-Verständnis
  - Moderate Angriffswert-Präferenz
  - Bessere Zaubertiming

**Resultat**: Solide Spielweise mit kleinen gelegentlichen Fehlern. Faire Herausforderung.

*Result: Solid gameplay with small occasional mistakes. Fair challenge.*

### Level 4 (Schwer / Hard)

**Für Experten / For Experts**

- **Keyword-Bewertung**: Erhöht (Multiplikator: 1.5)
- **Kartenlimit**: 8 Karten zur Auswahl
- **Züge pro Runde**: Unbegrenzt
- **Zufälligkeit**: Keine
- **Strategien**: 
  - Volle strategische Bewertung
  - Optimale Mana-Nutzung
  - Starke Angriffswert-Präferenz
  - Strategisches Zaubern

**Resultat**: Starke, konsistente Spielweise ohne Fehler.

*Result: Strong, consistent gameplay without mistakes.*

### Level 5+ (Sehr Schwer / Very Hard)

**Für Meister / For Masters**

- **Keyword-Bewertung**: Stark erhöht (Multiplikator: 2.0)
- **Kartenlimit**: 8 Karten zur Auswahl
- **Züge pro Runde**: Unbegrenzt
- **Zufälligkeit**: Keine
- **Strategien**: Maximale Optimierung aller Aspekte

**Resultat**: Nahezu perfekte Spielweise. Sehr schwer zu schlagen.

*Result: Nearly perfect gameplay. Very hard to beat.*

## Technische Implementierung / Technical Implementation

### Keyword-Multiplikatoren / Keyword Multipliers

```php
$keywordMultiplier = match($aiLevel) {
    1 => 0.3,  // 70% reduction
    2 => 0.6,  // 40% reduction
    3 => 1.0,  // Normal
    4 => 1.5,  // 50% increase
    default => 2.0  // 100% increase
};
```

### Zufallsfaktoren / Random Factors

```php
if ($aiLevel == 1) {
    $randomFactor = mt_rand(40, 160) / 100.0;  // 0.4 to 1.6
} else if ($aiLevel == 2) {
    $randomFactor = mt_rand(70, 130) / 100.0;  // 0.7 to 1.3
} else if ($aiLevel == 3) {
    $randomFactor = mt_rand(85, 115) / 100.0;  // 0.85 to 1.15
}
```

### Karten-Limit / Card Limit

```php
$cardLimit = match($aiLevel) {
    1 => 5,   // Very limited
    2 => 6,   // Limited
    3 => 7,   // Moderate
    default => 8  // Full
};
```

### Züge pro Runde / Plays per Turn

```php
$maxCardsToPlay = match($aiLevel) {
    1 => 2,   // Very inefficient
    2 => 3,   // Inefficient
    3 => 4,   // Moderate
    default => 10  // Optimal
};
```

## Empfohlene Schwierigkeitsstufen / Recommended Difficulty Levels

- **Neue Spieler / New Players**: Level 1
- **Anfänger / Beginners**: Level 2
- **Fortgeschrittene / Intermediate**: Level 3
- **Experten / Experts**: Level 4
- **Meister / Masters**: Level 5

## Testen / Testing

Um die Schwierigkeitsbalancierung zu testen:

*To test the difficulty balancing:*

1. Starte ein Spiel auf Level 1
   - *Start a game on Level 1*
2. Beobachte, dass die KI:
   - Weniger Karten spielt als möglich
   - Manchmal ineffiziente Karten wählt
   - Mana verschwendet
   - *Observe that the AI:*
     - *Plays fewer cards than possible*
     - *Sometimes chooses inefficient cards*
     - *Wastes mana*
3. Vergleiche mit höheren Levels
   - *Compare with higher levels*

## Weitere Verbesserungen / Future Improvements

Mögliche zukünftige Verbesserungen:

*Possible future improvements:*

- Anpassbare Schwierigkeitsstufen (Custom Difficulty)
- KI-"Persönlichkeiten" mit unterschiedlichen Spielstilen
  - *AI "personalities" with different playstyles*
- Adaptive Schwierigkeit basierend auf Spielerleistung
  - *Adaptive difficulty based on player performance*
- Tutorial-Modus mit noch einfacherer KI
  - *Tutorial mode with even easier AI*
