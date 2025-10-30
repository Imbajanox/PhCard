# PhCard Multiplayer System

## Overview

The PhCard multiplayer system enables players to compete against each other in real-time turn-based card battles. This feature extends the existing single-player game to support player-vs-player (PvP) gameplay while maintaining compatibility with the core game mechanics.

## Features

### Core Multiplayer Features
- **Game Lobby**: Create and browse available multiplayer games
- **Real-time Updates**: Automatic polling for game state updates
- **Turn-based Gameplay**: Players take turns playing cards and attacking
- **Player Statistics**: Track wins, losses, rating, and win streaks
- **Game History**: Complete move logging for each game

### Technical Implementation
- **Database-backed State**: Game states persist in the database for reliability
- **Polling-based Updates**: 3-second polling interval for smooth gameplay without WebSockets
- **Minimal Core Changes**: Reuses existing BattleSystem and GameState classes
- **Backward Compatible**: Single-player AI games continue to work unchanged

## Database Schema

The multiplayer system adds three new tables:

1. **multiplayer_games**: Stores game rooms and state
   - Tracks players, game status, current turn
   - Stores serialized game state as JSON
   - Tracks creation, start, and finish times

2. **multiplayer_moves**: Logs all moves in a game
   - Records play_card, end_turn, and surrender actions
   - Useful for replays and debugging

3. **multiplayer_stats**: Tracks player statistics
   - Games played, won, lost, drawn
   - Rating system (starts at 1000)
   - Win streaks and personal bests

## API Endpoints

### `/api/multiplayer.php`

All multiplayer actions use this endpoint:

- `create_game`: Create a new multiplayer game room
- `join_game`: Join an existing waiting game
- `list_games`: Get list of available games
- `get_state`: Get current game state
- `play_card`: Play a card from hand
- `end_turn`: End current turn and trigger battle phase
- `surrender`: Forfeit the game
- `current_game`: Check if user has an active game

## Game Flow

### Creating a Game

1. Player clicks "Create Game" in the lobby
2. New game room is created with status "waiting"
3. Player waits for an opponent to join
4. Polling checks for opponent every 2 seconds

### Joining a Game

1. Player sees list of available games
2. Clicks "Join" on a game
3. Game transitions to "active" status
4. Both players' decks are shuffled and initial hands drawn
5. Game begins with player1's turn

### Playing the Game

1. Current player can:
   - Play cards from hand (costs mana)
   - End turn to trigger battle phase
   - Surrender to forfeit

2. When turn ends:
   - Player's monsters attack opponent
   - Battle damage is calculated
   - Turn switches to opponent
   - Opponent draws a card and gains mana

3. Game ends when:
   - One player's HP reaches 0 (winner declared)
   - Both players' HP reaches 0 (draw)
   - A player surrenders (opponent wins)

## Client-Side Architecture

### JavaScript (`src/frontend/js/game/multiplayer.js`)

Key functions:
- `initMultiplayer()`: Initialize lobby and check for active games
- `createMultiplayerGame()`: Create new game room
- `joinMultiplayerGame(gameId)`: Join a waiting game
- `loadMultiplayerGameState(gameId)`: Fetch and display game state
- `playMultiplayerCard(index)`: Play a card
- `endMultiplayerTurn()`: End turn and process battle
- `updateMultiplayerGameDisplay()`: Update UI with game state

### HTML (`multiplayer.html`)

Two main screens:
- **Lobby Screen**: Browse and create games
- **Game Screen**: Play the multiplayer game

### CSS (`web/css/multiplayer.css`)

Styled components:
- Game lobby with available games list
- Waiting room with loading spinner
- Game board with player/opponent sections
- Turn indicator with visual feedback
- Battle log for game events

## Server-Side Architecture

### Multiplayer Class (`src/backend/game/Multiplayer.php`)

Core methods:
- `createGame()`: Initialize new game room
- `joinGame()`: Add second player and start game
- `getGameState()`: Retrieve current state
- `playCard()`: Process card play action
- `endTurn()`: Execute battle and switch turns
- `surrender()`: Handle forfeit
- `endGame()`: Update stats and mark game finished

### BattleSystem Extensions (`src/backend/game/BattleSystem.php`)

Added methods:
- `playCard()`: Enhanced to support player1/player2 (not just player/ai)
- `executeMultiplayerTurnBattle()`: Battle phase for PvP
- `executeMultiplayerMonsterAttack()`: Monster combat for PvP
- `applyMultiplayerSpellEffect()`: Spell effects for PvP

## Installation

1. **Run the SQL migration**:
   ```bash
   mysql -u root -p phcard < sql/database_multiplayer.sql
   ```

2. **Access multiplayer**:
   - Log in to the game
   - Click "Multiplayer (vs Spieler)" in the main menu
   - Create or join a game

## Rating System

- Starting rating: 1000
- Win: +25 rating
- Loss: -20 rating (minimum 0)
- Rating determines player ranking

## Future Enhancements

Potential improvements:
- WebSocket support for real-time updates
- Matchmaking by rating/level
- Tournament system
- Spectator mode
- Game replays
- Friend system
- Chat functionality
- Seasonal leaderboards

## Troubleshooting

### Game not starting
- Check that both players have valid decks
- Ensure database connection is working
- Verify polling interval is active

### State not updating
- Check browser console for JavaScript errors
- Verify API endpoint is accessible
- Check database for game state updates

### Cards not playing
- Ensure it's the player's turn
- Verify player has enough mana
- Check card index is valid

## Testing

To test multiplayer locally:
1. Open two browser windows/tabs
2. Log in as different users in each
3. Create a game in one window
4. Join the game in the other window
5. Play cards and test battle mechanics

## Security Considerations

- Session-based authentication required
- Game state validated on server
- Players can only access their own games
- Move validation prevents cheating
- Turn enforcement on server side

## Performance

- Polling interval: 3 seconds during game
- Database queries optimized with indexes
- Game state stored as compressed JSON
- Old games auto-cleaned (can be configured)

## Compatibility

- Works with existing single-player code
- Uses same card and battle system
- Compatible with deck builder
- Integrates with existing user system
