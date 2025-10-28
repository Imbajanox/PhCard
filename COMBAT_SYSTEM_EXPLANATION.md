# Combat System Explanation

## Overview

PhCard uses a Hearthstone-inspired combat system with automatic targeting suitable for a single-player auto-battler card game.

## How Combat Works

### Core Mechanics

1. **HP-Based Combat**: Monsters have health points that persist across turns
2. **Simultaneous Damage**: When monsters fight, both deal damage to each other (attacker and defender)
3. **Direct Attacks**: Monsters attack the opponent directly when no enemy monsters block them
4. **Taunt Mechanic**: Monsters with Taunt must be attacked first

### Combat Flow

**Each Turn:**
1. Player plays cards from hand
2. Player ends turn
3. Player's monsters attack (in order they were played)
4. AI plays its cards
5. AI's monsters attack
6. Repeat

**Monster Targeting Priority:**
1. If enemy has Taunt monsters → Attack taunt monster
2. If enemy has non-Taunt monsters → Attack first monster on field
3. If enemy has no monsters → Attack opponent directly (deal damage to HP)

## Comparison to Hearthstone

### Similarities ✓

- **HP System**: Damage persists, monsters don't fully heal between turns
- **Mutual Combat**: Both attacker and defender deal damage
- **Taunt Mechanic**: Forces attacks against specific targets
- **Special Keywords**: Divine Shield, Lifesteal, Windfury, etc.
- **Mana System**: Increasing mana each turn
- **Direct Face Damage**: Monsters can attack the opponent when board is clear

### Differences ⚠️

- **No Manual Target Selection**: Hearthstone lets players choose which monster to attack or to go face. PhCard uses automatic targeting (attack monsters first, then face)
- **Turn-Based Auto-Combat**: All monsters attack automatically; no decision on which monsters attack
- **Simplified Keywords**: Charge and Rush are recognized but don't change immediate attack behavior
- **Auto-Battler Design**: Optimized for playing against AI without complex micromanagement

## Why the Current System Works

### Strategic Depth
- **Board Control Matters**: Clearing enemy monsters opens up direct damage
- **Resource Management**: Damaged monsters persist, creating ongoing value
- **Spell Timing**: Direct damage spells are valuable for finishing opponents
- **Monster Quality**: High-HP monsters survive longer and deal more total damage

### Balance Considerations

The system is intentionally defensive compared to pure face-rush strategies because:

1. **Single-Player Focus**: The game is designed for player vs AI, not PvP
2. **Skill Expression**: Players must manage their board and resources efficiently  
3. **Comeback Potential**: The HP system allows for strategic trades and recovery
4. **Spell Value**: Direct damage spells are powerful finishing tools, not the only win condition

## Recent Improvements (This Update)

### Visual Enhancements
1. **Floating Damage Numbers**: See exactly how much damage each monster takes
2. **Card Destruction Delays**: Destroyed cards remain visible for 800ms before fading out
3. **Damage Flash Effects**: Cards visually flash when taking damage
4. **Battle Events System**: Structured tracking of all combat actions for better animations

### Impact on Gameplay
- **Better Clarity**: Players can see what happened in combat
- **Improved Feedback**: Visual cues make combat more engaging
- **Strategic Understanding**: Easier to track which monsters are threatening

## How to Win

### Effective Strategies

1. **Build a Strong Board**: Play monsters early to establish board control
2. **Protect Key Monsters**: Use Taunt to protect high-value monsters
3. **Efficient Trading**: Trade weaker monsters for stronger enemy monsters
4. **Spell Timing**: Save direct damage for lethal or removing key threats
5. **Resource Curves**: Play on-curve (use all mana each turn)

### Why Direct Damage Feels Necessary

In Hearthstone, players can choose to ignore small monsters and attack face directly for lethal. In PhCard's auto-battler system, you must clear the board first OR use spells. This makes direct damage spells very valuable but not the ONLY way to win.

**Winning requires:**
- Board control (monsters)
- Direct damage (spells)  
- Efficient trades
- Good mana management

The game is NOT purely luck-based - strategic deck building and card playing decisions matter significantly.

## Future Considerations

Potential enhancements to add more strategic depth:

1. **Target Selection UI**: Allow players to choose which monster attacks which target
2. **Charge/Rush Implementation**: Charge monsters can attack face immediately, Rush can attack monsters immediately
3. **Attack Order Selection**: Let players choose the order their monsters attack
4. **More Combat Keywords**: Implement additional Hearthstone-style keywords

These would make the game closer to Hearthstone but require significant UI development.
