# Multiplayer Bug Fixes - Visual Guide

## Bug 1: Mana Increment Issue

### Before (Broken):
```
Game Start:
  player1_turn_count = 0, max_mana = 1
  player2_turn_count = 0, max_mana = 1

Player 1 Turn 1:
  Has 1 mana ✓
  Ends turn → startTurn('player2')
  → player2_turn_count++ → 1
  → Check: 1 > 1? NO ✗
  → max_mana stays at 1 ✗

Player 2 Turn 1:
  Has 1 mana (should be OK)
  Ends turn → startTurn('player1')
  → player1_turn_count++ → 1
  → Check: 1 > 1? NO ✗
  → max_mana stays at 1 ✗

Player 1 Turn 2:
  Has 1 mana ✗ (STUCK AT 1!)
```

### After (Fixed):
```
Game Start:
  player1_turn_count = 1, max_mana = 1  ← Changed from 0 to 1
  player2_turn_count = 1, max_mana = 1  ← Changed from 0 to 1

Player 1 Turn 1:
  Has 1 mana ✓
  Ends turn → startTurn('player2')
  → player2_turn_count++ → 2
  → Check: 2 > 1? YES ✓
  → max_mana++ → 2 ✓

Player 2 Turn 1:
  Has 2 mana ✓
  Ends turn → startTurn('player1')
  → player1_turn_count++ → 2
  → Check: 2 > 1? YES ✓
  → max_mana++ → 2 ✓

Player 1 Turn 2:
  Has 2 mana ✓ (WORKS!)
```

## Bug 2: No Cards at Start

### Before (Broken):
```
getPlayerDeck(userId, deckId):
  1. Check for active deck → None found
  2. Try user's collection → Empty
  3. Return empty array []
  
Draw initial hand:
  for i in 0..4:
    if deck not empty:
      hand.push(deck.shift())  ← Never executes!
  
Result: Player has 0 cards ✗
```

### After (Fixed):
```
getPlayerDeck(userId, deckId):
  1. Check for active deck → None found
  2. Try user's collection → Empty
  3. Fallback to default starter cards ← NEW!
     SELECT * FROM cards WHERE required_level <= 1
  4. Return 30 starter cards
  
Draw initial hand:
  for i in 0..4:
    if deck not empty:  ← Always true now
      hand.push(deck.shift())
  
Result: Player has 5 cards ✓
```

## Bug 3: Battle Log Persistence

### Before (Broken):
```
User plays Game 1:
  Battle Log: ["Player 1 played Dragon", "Player 2 took 5 damage", ...]
  Game ends → Return to lobby
  
User starts Game 2:
  startMultiplayerGame() called
  → Does NOT clear log ✗
  → Old entries still visible
  
Result: "Player 1 played Dragon" shows in new game ✗
```

### After (Fixed):
```
User plays Game 1:
  Battle Log: ["Player 1 played Dragon", "Player 2 took 5 damage", ...]
  Game ends → Return to lobby
  
User starts Game 2:
  startMultiplayerGame() called
  → log.innerHTML = '' ← NEW!
  → Log is cleared
  
Result: Clean battle log for new game ✓
```

## Bug 4: Losing Screen & Rewards

### Before (Broken):
```
Player 1 wins the game:
  endGame() called
  → Calculate rewards for player1
  → Calculate rewards for player2
  → Store in session:
      $_SESSION['mp_rewards_1'] = {xp: 75, coins: 100}
      $_SESSION['mp_rewards_2'] = {xp: 15, coins: 20}
  
Player 1 polls game state:
  → Get rewards from session
  → Delete from session ← Deleted!
  → Show game end modal ✓
  
Player 2 polls game state (3 seconds later):
  → Get rewards from session → NULL ✗ (Already deleted!)
  → Show game end modal with no rewards ✗
  
Player 2 polls again:
  → Still NULL ✗
  
Result: Losing player sees no rewards ✗
```

### After (Fixed):
```
Player 1 wins the game:
  endGame() called
  → Calculate rewards for player1
  → Calculate rewards for player2
  → Store in DATABASE: ← NEW!
      UPDATE multiplayer_games SET
        player1_rewards = '{"xp":75,"coins":100}',
        player2_rewards = '{"xp":15,"coins":20}'
  
Player 1 polls game state:
  → Get rewards from database ✓
  → Show game end modal ✓
  
Player 2 polls game state (3 seconds later):
  → Get rewards from database ✓
  → Show game end modal with rewards ✓
  
Player 2 polls again (if needed):
  → Get rewards from database ✓
  → Still available ✓
  
Result: Both players see rewards ✓
```

## Reward Distribution

```
Game Result         | Winner Rewards      | Loser Rewards
--------------------|---------------------|------------------
Win/Loss            | 75 XP, 100 coins    | 15 XP, 20 coins  ← Fixed!
                    | 30% chance: 1-3 gems|
Draw                | 25 XP, 30 coins     | 25 XP, 30 coins
Surrender           | 75 XP, 100 coins    | 15 XP, 20 coins
```

## Game Flow Timeline

```
Time 0s:   Player 1 creates game
Time 5s:   Player 2 joins
           → Both players get 5 cards ✓
           → Battle log cleared ✓
           → turn_count = 1 for both ✓

Time 10s:  Player 1 plays turn (1 mana)
Time 15s:  Player 1 ends turn
           → Player 2 turn_count: 1→2
           → Player 2 max_mana: 1→2 ✓

Time 20s:  Player 2 plays turn (2 mana) ✓
Time 25s:  Player 2 ends turn
           → Player 1 turn_count: 1→2
           → Player 1 max_mana: 1→2 ✓

Time 30s:  Player 1 plays turn (2 mana) ✓
           ... game continues ...

Time 90s:  Player 1 wins!
           → Rewards calculated for both
           → Stored in database
           → Player 1 sees modal immediately ✓

Time 93s:  Player 2 polls
           → Detects game finished
           → Retrieves rewards from DB
           → Shows modal with rewards ✓
```

## Database Schema Change

```sql
-- Before
CREATE TABLE multiplayer_games (
  id INT PRIMARY KEY,
  player1_id INT,
  player2_id INT,
  status ENUM(...),
  game_state LONGTEXT,
  winner_id INT,
  ...
);

-- After (Migration)
ALTER TABLE multiplayer_games 
ADD COLUMN player1_rewards TEXT,  ← NEW
ADD COLUMN player2_rewards TEXT;  ← NEW

-- Rewards stored as JSON:
-- player1_rewards: '{"xp_gained":75,"coins_earned":100,"gems_earned":2,...}'
-- player2_rewards: '{"xp_gained":15,"coins_earned":20,"gems_earned":0,...}'
```

## Code Structure Improvement

```
Before:
┌─────────────────────────────┐
│ updateStatsAndRewards()     │
│  - Calculate rewards        │
│  - Update stats             │
│  - Store in session ✗       │
└─────────────────────────────┘
         ↓
    Session Storage (temporary)

After:
┌─────────────────────────────┐
│ calculateRewards()          │
│  - Calculate XP/coins/gems  │
│  - Check level up           │
│  - Return rewards object    │
└─────────────────────────────┘
         ↓
┌─────────────────────────────┐
│ updatePlayerStats()         │
│  - Update multiplayer_stats │
│  - Apply XP/level changes   │
│  - Unlock new cards         │
└─────────────────────────────┘
         ↓
┌─────────────────────────────┐
│ endGame()                   │
│  - Calculate for player1    │
│  - Calculate for player2    │
│  - Store in database ✓      │
└─────────────────────────────┘
         ↓
    Database Storage (persistent)
```

All bugs fixed! 🎉
