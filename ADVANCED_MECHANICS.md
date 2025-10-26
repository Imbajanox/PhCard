# Advanced Card Mechanics - Feature Documentation

This document describes the new advanced card mechanics that have been implemented in PhCard.

## New Features

### 1. Mana System
- Players start with 1 mana crystal
- Gain +1 max mana each turn (up to 10)
- Each card has a mana cost
- Overload mechanic: Some cards lock mana crystals for the next turn

### 2. Card Keywords
The following keywords have been implemented:

- **Charge**: Can attack immediately after being played
- **Taunt**: Must be attacked before other minions
- **Rush**: Can attack minions immediately (not face)
- **Divine Shield**: Absorbs the next damage
- **Windfury**: Can attack twice per turn
- **Stealth**: Cannot be targeted until it attacks
- **Lifesteal**: Heals for damage dealt
- **Poison**: Damages the opponent over multiple turns

### 3. Status Effects
- **Stun**: Monster cannot attack for specified turns
- **Poison**: Takes damage at end of each turn
- **Burn**: Takes increasing damage each turn
- **Freeze**: Cannot attack or use abilities

### 4. Deck Building System

New API endpoint: `api/deck.php`

**Features:**
- Create custom decks (30 cards)
- Choose deck archetypes (Aggro, Control, Combo, Midrange, Tempo)
- Limit of 2 duplicate cards per deck
- Class-based deck restrictions
- Deck validation

**API Actions:**
- `list` - List all user decks
- `create` - Create a new deck
- `update` - Update deck properties
- `delete` - Delete a deck
- `set_active` - Set active deck for games
- `get_deck` - Get deck details with cards
- `add_card` - Add card to deck
- `remove_card` - Remove card from deck
- `list_archetypes` - List available archetypes
- `validate_deck` - Validate deck composition

### 5. Mulligan System
- At game start, players can exchange up to 3 cards
- Reduces bad starting hands
- API action: `api/game.php?action=mulligan`

### 6. Choose One Cards
- Some cards offer multiple choices when played
- Example: "Druid of the Flame" can become 5/2 OR 2/5
- Choice is stored in `choice_effects` JSON field

### 7. Combo System
- Cards can have combo effects
- Combo cards get bonus based on cards played this turn
- Example: "Combo Master" gains +200 ATK per card played

### 8. Analytics & Telemetry

New API endpoint: `api/analytics.php`

**Features:**
- Track card play rates
- Monitor win rates by card
- Deck performance statistics
- Balance metrics for each card

**API Actions:**
- `record_event` - Record game event
- `card_stats` - Get statistics for cards
- `winrate_analysis` - Overall winrate analysis
- `deck_performance` - Get deck performance metrics
- `update_card_metrics` - Update card balance metrics

### 9. A/B Testing System
- Test different card configurations
- Track results by variant
- API actions for creating and analyzing A/B tests

**API Actions:**
- `create_ab_test` - Create new A/B test
- `get_ab_variant` - Get assigned variant for user
- `record_ab_result` - Record game result for variant
- `ab_test_results` - Get test results summary

### 10. Simulation Framework

New API endpoint: `api/simulation.php`

**Features:**
- Run headless game simulations
- Test deck matchups
- Batch simulation for balance testing

**API Actions:**
- `run_simulation` - Run single simulation
- `batch_simulate` - Run multiple simulations
- `get_simulation_results` - Retrieve saved results

## Database Changes

### New Tables
- `card_status_effects` - Defines available status effects
- `deck_archetypes` - Deck archetype definitions
- `user_decks` - User's custom decks
- `deck_cards` - Cards in each deck
- `game_telemetry` - Event tracking for analytics
- `card_balance_metrics` - Card performance metrics
- `ab_test_configs` - A/B test configurations
- `ab_test_results` - A/B test results

### Updated Tables

**cards table** - New columns:
- `keywords` - Comma-separated keywords
- `mana_cost` - Mana cost to play
- `overload` - Overload amount
- `card_class` - Card class (neutral, warrior, mage, etc.)
- `choice_effects` - JSON for Choose One effects

**game_history table** - New columns:
- `turns_played` - Number of turns in game
- `cards_played` - Cards played count
- `final_player_hp` - Player HP at end
- `final_ai_hp` - AI HP at end
- `deck_id` - Deck used in game
- `telemetry_recorded` - Whether telemetry was recorded

## Configuration Updates

New constants in `config.php`:
- `STARTING_MANA = 1`
- `MAX_MANA = 10`
- `MANA_PER_TURN = 1`
- `MAX_DECK_SIZE = 30`
- `MIN_DECK_SIZE = 30`
- `MAX_CARD_DUPLICATES = 2`
- `MULLIGAN_CARDS = 3`

## Frontend Updates

### JavaScript (app.js)
- Updated `createCardElement()` to display mana, keywords, status effects
- Added `updateMana()` function
- Added `showMulliganOption()` and `performMulligan()` functions
- Updated `playCard()` to check mana and handle Choose One
- Updated `initGameDisplay()` to show mulligan option

### HTML (index.html)
- Added mana display to game screen
- Mana shown as "Mana: X / Y"

### CSS (style.css)
- Added `.card-mana-cost` styling
- Added `.keyword` and `.card-keywords` styling
- Added status effect styling (`.status-*`)
- Added `.card-overload` styling
- Added mulligan interface styling

## Usage Examples

### Creating a Deck
```javascript
fetch('api/deck.php', {
    method: 'POST',
    body: new URLSearchParams({
        action: 'create',
        name: 'My Aggro Deck',
        archetype_id: 1, // Aggro
        card_class: 'warrior'
    })
});
```

### Adding Cards to Deck
```javascript
fetch('api/deck.php', {
    method: 'POST',
    body: new URLSearchParams({
        action: 'add_card',
        deck_id: 1,
        card_id: 5,
        quantity: 2
    })
});
```

### Starting Game with Deck
```javascript
fetch('api/game.php', {
    method: 'POST',
    body: new URLSearchParams({
        action: 'start',
        ai_level: 3,
        deck_id: 1
    })
});
```

### Performing Mulligan
```javascript
fetch('api/game.php', {
    method: 'POST',
    body: new URLSearchParams({
        action: 'mulligan',
        card_indices: JSON.stringify([0, 2, 4]) // Exchange cards at positions 0, 2, 4
    })
});
```

### Getting Card Statistics
```javascript
fetch('api/analytics.php?action=card_stats&card_id=5')
    .then(r => r.json())
    .then(data => console.log(data.card));
```

### Running Simulations
```javascript
fetch('api/simulation.php', {
    method: 'POST',
    body: new URLSearchParams({
        action: 'run_simulation',
        deck_a: JSON.stringify(deckConfigA),
        deck_b: JSON.stringify(deckConfigB),
        iterations: 100
    })
});
```

## Installation

1. Run the database extensions:
```bash
mysql -u root -p phcard < database_extensions.sql
```

2. Clear your browser cache to load updated CSS/JS

3. Start playing with new mechanics!

## Balance Considerations

The analytics and telemetry systems track:
- Card play frequency
- Win rates when card is in deck
- Average turn played
- Damage dealt / healing done

Use this data to:
- Identify overpowered cards
- Find underused cards
- Balance mana costs
- Tune status effect durations

## Future Enhancements

Potential additions:
- More keywords (Deathrattle, Battlecry, etc.)
- Card buffs/debuffs persistence
- Board-wide effects
- Secret cards (triggered by opponent actions)
- Spell damage modifiers
- Armor system
- Weapon cards for heroes
- Multi-class cards
- Legendary deck-building restrictions

## Testing

Run simulations to test balance:
```bash
# Access simulation API
curl -X POST 'http://localhost:8000/api/simulation.php' \
  -d 'action=batch_simulate&iterations=1000&card_configs=[...]'
```

Monitor analytics:
```bash
# Check card win rates
curl 'http://localhost:8000/api/analytics.php?action=card_stats'
```

## Troubleshooting

**Issue**: Mana not updating
- Check that `updateMana()` is called after turn actions
- Verify `player_mana` and `player_max_mana` in game state

**Issue**: Keywords not showing
- Ensure `keywords` field is populated in database
- Check CSS for keyword classes

**Issue**: Mulligan not appearing
- Verify `mulligan_available` is true in game state
- Check browser console for JavaScript errors

**Issue**: Deck validation failing
- Ensure deck has exactly 30 cards
- Check for duplicate card limits
- Verify all cards are owned by user
