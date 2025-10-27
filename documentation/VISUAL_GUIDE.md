# PhCard - Visual Guide

## Screens Overview

### 1. Authentication Screen (Login/Register)
The first screen users see. Allows registration of new accounts or login with existing credentials.

**Features:**
- Username/Password login
- New user registration with email
- Toggle between login and register forms
- Error/success message display

### 2. Main Menu
After login, users see their profile and game options.

**Features:**
- User profile display (username, level, XP bar)
- Win/loss statistics
- XP progress bar
- Menu options: New Game, Card Collection, Logout

### 3. Game Setup
Select AI difficulty before starting a game.

**Features:**
- 5 AI difficulty levels (1-5)
- Each level provides different XP rewards
- Higher levels require stronger cards to win
- Back button to return to menu

### 4. Card Collection
View all owned cards and their quantities.

**Features:**
- Grid display of all owned cards
- Card details: name, type, stats, rarity
- Quantity indicator (x3, x2, etc.)
- Color-coded by card type (monster/spell)
- Rarity badges (common, rare, epic, legendary)

### 5. Game Screen
The main gameplay interface.

**Components:**

#### Top Bar (Status)
- Player HP bar (green)
- Turn counter
- AI HP bar (red)
- AI difficulty level

#### Battle Field
- AI Field (top) - Shows AI's monsters
- Player Field (bottom) - Shows player's monsters
- Cards on field display ATK/DEF stats

#### Player Hand
- 5 cards displayed at bottom
- Click to play cards
- Monster cards go to field
- Spell cards have immediate effects

#### Action Buttons
- "End Turn" button
- Triggers battle phase
- AI takes turn automatically

#### Battle Log
- Scrollable combat log
- Shows all actions and combat results
- AI actions in red
- Player actions in normal color

### 6. Game Over Modal
Appears when game ends (win or loss).

**Features:**
- Result display (Victory or Defeat)
- XP gained
- Level up notification (if applicable)
- New cards unlocked (if level up occurred)
- Return to menu button

## Card Types

### Monster Cards
- Red border
- Display ATK and DEF values
- Example: "Goblin Scout - ATK: 200 / DEF: 100"
- Can be placed on field to attack

### Spell Cards
- Blue border
- Display effect type and value
- Example: "Fireball - Effect: damage 400"
- Immediate effect when played

## Rarity Colors
- **Common** (Gray): Basic starter cards
- **Rare** (Blue): Level 3+ cards
- **Epic** (Purple): Level 5+ cards
- **Legendary** (Gold): Level 8+ cards

## Gameplay Flow

1. **Start Game** → Select AI level
2. **Draw Phase** → Receive 5 random cards
3. **Main Phase** → Play cards from hand
4. **Battle Phase** → Click "End Turn"
   - Your monsters attack
   - AI plays cards
   - AI monsters attack back
   - Draw 1 card
5. **Repeat** until someone's HP reaches 0
6. **Game Over** → Collect XP and potential level up
7. **Return to Menu** → Start new game or view collection

## User Progression

### Level System
- Start at Level 1
- Earn XP by winning games
- Higher AI levels give more XP
- Level up unlocks new cards

### XP Requirements
- Level 2: 100 XP
- Level 3: 300 XP
- Level 4: 600 XP
- Level 5: 1000 XP
- Level 8: 2800 XP
- Level 10: 4500 XP

### Card Unlocks
- Level 1: Starter cards (5 cards)
- Level 3: Advanced cards (3 cards)
- Level 5: Epic cards (3 cards)
- Level 8: Legendary cards (4 cards)

## Tips for Players

1. **Start with Level 1 AI** to learn the mechanics
2. **Balance your deck** - mix monsters and spells
3. **Use healing spells** when HP is low
4. **Level up** to unlock stronger cards
5. **Higher AI = More XP** but also harder to win
6. **Monster defense** helps protect your HP
7. **Direct attacks** when enemy field is empty deal full damage

## Technical Notes

- Game state stored in PHP session
- AJAX calls for all game actions
- Real-time HP bar updates
- Responsive design for different screen sizes
- Card animations on hover
- Smooth transitions between screens

## Future Enhancements

Potential additions to the game:
- Card trading system
- Multiplayer (PvP)
- Tournament mode
- More card effects
- Animated battles
- Sound effects
- Achievement system
- Daily quests
