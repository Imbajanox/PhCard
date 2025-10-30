# PhCard Multiplayer Implementation Summary

## Overview

This document provides a high-level summary of the multiplayer implementation for PhCard, a browser-based turn-based card game.

## Architecture

### Client-Server Model
```
Player 1 Browser  <----->  PHP Backend  <----->  Player 2 Browser
                            |
                            v
                         MySQL Database
```

### Game Flow

1. **Lobby Phase**
   - Player 1 creates a game → Database stores game with status "waiting"
   - Player 2 sees available games → Selects and joins
   - Game status changes to "active" → Both players start

2. **Game Phase**
   - Current player:
     * Plays cards (costs mana)
     * Ends turn → Battle phase executes
   - Turn switches to opponent
   - Opponent draws card and gains mana
   - Repeat until winner determined

3. **End Game**
   - HP reaches 0 → Winner declared
   - Stats updated (wins/losses/rating)
   - Game marked as "finished"

## File Structure

### Backend Files
- `sql/database_multiplayer.sql` - Database schema for multiplayer tables
- `src/backend/game/Multiplayer.php` - Core multiplayer game logic
- `src/backend/game/BattleSystem.php` - Enhanced with PvP support
- `api/multiplayer.php` - API endpoints for multiplayer actions

### Frontend Files
- `multiplayer.html` - Main multiplayer interface
- `src/frontend/js/game/multiplayer.js` - Client-side game logic
- `web/css/multiplayer.css` - Multiplayer UI styling

### Documentation
- `documentation/MULTIPLAYER_README.md` - Complete technical documentation

## Database Tables

### multiplayer_games
Stores game rooms and state.

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Game ID |
| player1_id | INT | First player |
| player2_id | INT | Second player |
| status | ENUM | waiting/active/finished |
| current_turn | INT | Whose turn it is |
| game_state | LONGTEXT | JSON game state |
| winner_id | INT | Winner (null if ongoing) |

### multiplayer_moves
Logs all moves in a game.

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Move ID |
| game_id | INT | Associated game |
| player_id | INT | Player who made move |
| move_type | ENUM | play_card/end_turn/surrender |
| move_data | TEXT | JSON move details |

### multiplayer_stats
Tracks player statistics.

| Column | Type | Description |
|--------|------|-------------|
| user_id | INT | Player ID |
| games_played | INT | Total games |
| games_won | INT | Victories |
| games_lost | INT | Defeats |
| rating | INT | Current rating (default 1000) |
| win_streak | INT | Current streak |

## Key API Endpoints

### Creating/Joining Games
```
POST /api/multiplayer.php?action=create_game
- Creates new game room
- Returns game_id

POST /api/multiplayer.php?action=join_game&game_id=123
- Joins existing game
- Initializes game state
- Returns game state
```

### Playing the Game
```
GET /api/multiplayer.php?action=get_state&game_id=123
- Retrieves current game state
- Hides opponent's hand

POST /api/multiplayer.php?action=play_card
- Plays a card from hand
- Updates game state

POST /api/multiplayer.php?action=end_turn
- Executes battle phase
- Switches turns
- Returns battle log
```

## Real-Time Updates

### Polling Mechanism
- Client polls every 3 seconds during active game
- Checks for:
  * Game state changes
  * Turn switches
  * Game end conditions
- Updates UI accordingly

### Why Polling?
- Simple implementation
- No WebSocket infrastructure needed
- Works with standard PHP hosting
- Turn-based nature tolerates 3-second delay

## Game State Structure

### Single-Player (vs AI)
```json
{
  "player_hp": 2000,
  "ai_hp": 2000,
  "player_hand": [...],
  "player_field": [...],
  "ai_field": [...],
  "turn": "player"
}
```

### Multiplayer (PvP)
```json
{
  "player1_hp": 2000,
  "player2_hp": 2000,
  "player1_hand": [...],
  "player2_hand": [...],
  "player1_field": [...],
  "player2_field": [...],
  "turn": "player1",
  "player1_id": 1,
  "player2_id": 2
}
```

## Backward Compatibility

### BattleSystem Enhancement
The `playCard()` method was enhanced to support both modes:

```php
public function playCard(&$gameState, $cardIndex, $target = 'opponent', 
                        $choice = 0, $playerKey = 'player') {
    // Detect mode based on game state
    if ($playerKey === 'player' && isset($gameState['ai_hp'])) {
        // Single-player mode - original logic
    } else {
        // Multiplayer mode - new logic
    }
}
```

This ensures:
- Existing single-player code works unchanged
- No breaking changes to game.php API
- Same BattleSystem handles both modes

## Security Considerations

1. **Session Authentication**: All API calls require valid session
2. **Turn Validation**: Server validates it's actually the player's turn
3. **Move Validation**: Card plays validated server-side
4. **Game Ownership**: Players can only access their own games
5. **State Integrity**: Game state stored and validated on server

## Rating System

- Starting rating: 1000
- Win: +25 rating
- Loss: -20 rating (minimum 0)
- Draw: No rating change
- Highest rating tracked for achievements

## Future Enhancements

Possible improvements for future versions:

1. **WebSocket Support** - Real-time updates without polling
2. **Matchmaking** - Automatic pairing by rating/level
3. **Tournament Mode** - Bracket-style competitions
4. **Spectator Mode** - Watch ongoing games
5. **Replay System** - Review past games
6. **Friend System** - Challenge specific players
7. **Chat** - In-game messaging
8. **Seasonal Rankings** - Leaderboards reset periodically
9. **Casual/Ranked Modes** - Separate rating systems

## Testing Checklist

To test multiplayer functionality:

- [ ] Create game in one browser/user
- [ ] Join game in another browser/user
- [ ] Verify game starts with both players' hands
- [ ] Play cards and verify mana deduction
- [ ] End turn and verify battle phase executes
- [ ] Verify turn switches to opponent
- [ ] Verify opponent can play cards
- [ ] Play until game ends
- [ ] Verify winner is determined correctly
- [ ] Check stats are updated
- [ ] Test surrender functionality
- [ ] Test game with disconnection/timeout

## Performance Considerations

- **Polling Interval**: 3 seconds balances responsiveness and server load
- **Database Indexes**: Added on status and last_activity for fast queries
- **JSON Compression**: Game state stored as compressed JSON
- **Cleanup**: Old finished games can be archived/deleted
- **Connection Pooling**: Standard PHP database connection pooling

## Conclusion

The multiplayer implementation provides a solid foundation for PvP gameplay while maintaining backward compatibility with the existing single-player game. The polling-based approach is simple, reliable, and suitable for the turn-based nature of the game.

For detailed technical information, see `documentation/MULTIPLAYER_README.md`.
