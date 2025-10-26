# PhCard Extensibility Framework

## Overview

PhCard has been enhanced with a comprehensive extensibility framework that makes it easy to add new features, content, and mechanics without modifying core game code.

## Key Features

### 1. Plugin System for Card Effects
- **CardEffectRegistry**: Register custom card effects without touching core code
- Pre-built effects: damage, heal, boost, shield, draw, poison, burn, stun, freeze, lifesteal
- Easy to add new effects with simple callback functions

### 2. Event-Driven Architecture
- **GameEventSystem**: Hook into game actions with event listeners
- Standard events: card_played, damage_dealt, game_end, level_up, etc.
- Priority-based execution for control over event handling order
- Built-in event logging for debugging

### 3. Configuration-Based Card Creation
- **CardFactory**: Create cards from JSON configurations
- Import entire card sets from JSON files
- Export existing cards for sharing
- Validation system ensures card data integrity

### 4. Quest & Achievement System
- Fully data-driven quest definitions
- Multiple objective types: win games, play cards, deal damage, collect cards
- Daily, weekly, and story quest support
- Achievement tracking with progress monitoring
- Automatic reward distribution

### 5. Card Set/Expansion Framework
- Organize cards into sets and expansions
- Track card numbering within sets
- Easy activation/deactivation of content
- Support for core, expansion, promo, and seasonal sets

### 6. Developer Tools
- Command-line card import utility
- Card validation tools
- Balance testing framework integration
- Comprehensive documentation

## Quick Start

### Adding New Cards

Create a JSON file with your card definitions:

```json
[
  {
    "name": "My Custom Card",
    "type": "monster",
    "attack": 500,
    "defense": 300,
    "mana_cost": 3,
    "keywords": ["charge"],
    "rarity": "rare",
    "description": "A powerful custom card"
  }
]
```

Import using the command-line tool:

```bash
php import_cards.php my_cards.json CUSTOM
```

### Creating Custom Effects

```php
require_once 'api/CardEffectRegistry.php';

CardEffectRegistry::register('my_effect', function($context) {
    $gameState = $context['gameState'];
    // Implement your effect logic
    return $gameState;
});
```

### Listening to Game Events

```php
require_once 'api/GameEventSystem.php';

GameEventSystem::on('card_played', function($data) {
    // React to card plays
    error_log("Card played: " . $data['card']['name']);
    return $data;
});
```

### Adding Quests

```sql
INSERT INTO quests (name, description, quest_type, objective_type, objective_target, xp_reward)
VALUES ('My Quest', 'Complete this quest', 'daily', 'win_games', 3, 50);
```

## File Structure

```
PhCard/
├── api/
│   ├── CardEffectRegistry.php   # Effect plugin system
│   ├── GameEventSystem.php      # Event system
│   ├── CardFactory.php           # Card creation/import
│   ├── quests.php                # Quest/achievement API
│   └── card_sets.php             # Card set management
├── database_quest_achievement_system.sql  # Database schema
├── card_expansion_example.json   # Example card pack
├── import_cards.php              # Import utility
└── EXTENSION_GUIDE.md            # Detailed documentation
```

## Database Schema

### New Tables
- `quests` - Quest definitions
- `user_quest_progress` - User quest tracking
- `achievements` - Achievement definitions
- `user_achievements` - User achievement tracking
- `card_sets` - Card set/expansion metadata
- `card_set_members` - Card-to-set relationships

## API Endpoints

### Quest System
- `GET /api/quests.php?action=get_active_quests` - Get available quests
- `POST /api/quests.php?action=claim_quest_reward` - Claim quest rewards
- `GET /api/quests.php?action=get_achievements` - Get all achievements
- `POST /api/quests.php?action=update_quest_progress` - Update progress

### Card Sets
- `GET /api/card_sets.php?action=list_sets` - List all card sets
- `GET /api/card_sets.php?action=get_set_cards` - Get cards in a set
- `POST /api/card_sets.php?action=create_set` - Create new set (admin)

## Examples

### Example 1: Custom "Draw Cards" Effect

```php
CardEffectRegistry::register('draw_many', function($context) {
    $count = $context['value'] ?? 3;
    $gameState = $context['gameState'];
    
    for ($i = 0; $i < $count; $i++) {
        if (!empty($gameState['available_cards'])) {
            $card = array_shift($gameState['available_cards']);
            $gameState['player_hand'][] = $card;
        }
    }
    
    return $gameState;
});
```

### Example 2: Track Legendary Card Plays

```php
GameEventSystem::on('card_played', function($data) {
    if (isset($data['card']['rarity']) && $data['card']['rarity'] === 'legendary') {
        // Track legendary plays
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            INSERT INTO legendary_plays (user_id, card_id, played_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$data['user_id'], $data['card']['id']]);
    }
    return $data;
});
```

### Example 3: Create a Card Expansion

```bash
# 1. Create JSON with cards
cat > shadow_expansion.json << EOF
[
  {"name": "Shadow Monster", "type": "monster", "attack": 700, ...}
]
EOF

# 2. Import cards
php import_cards.php shadow_expansion.json SHADOW

# 3. Cards are now in database and linked to SHADOW set
```

## Benefits

1. **No Core Code Changes**: Add features via plugins and configuration
2. **Easy Content Updates**: JSON-based card definitions
3. **Modular Design**: Features are independent and reusable
4. **Developer-Friendly**: Clear APIs and comprehensive docs
5. **Extensible**: Built with future growth in mind
6. **Maintainable**: Separation of concerns makes code easier to manage

## Documentation

- **EXTENSION_GUIDE.md** - Comprehensive developer guide with examples
- **API Documentation** - Inline comments in all PHP files
- **Database Schema** - See `database_quest_achievement_system.sql`

## Testing

All PHP files have been syntax-checked and are ready to use:
- ✓ CardEffectRegistry.php
- ✓ GameEventSystem.php
- ✓ CardFactory.php
- ✓ quests.php
- ✓ card_sets.php
- ✓ import_cards.php

## Next Steps

1. Install database schema: `mysql -u user -p database < database_quest_achievement_system.sql`
2. Try importing the example cards: `php import_cards.php card_expansion_example.json`
3. Review the EXTENSION_GUIDE.md for detailed tutorials
4. Start building your own content!

## Compatibility

This framework is fully compatible with existing PhCard features:
- ✓ Works with current deck system
- ✓ Compatible with analytics/telemetry
- ✓ Integrates with simulation framework
- ✓ No breaking changes to existing APIs

## Security Considerations

**Plugin System Security:**
- Plugins execute arbitrary PHP code - only use trusted plugins
- Plugin directory is validated to prevent path traversal
- Plugins are verified to be within the designated directory
- **Production recommendation**: Only allow admin-approved plugins
- Review all plugin code before deployment
- Consider implementing a plugin approval workflow

**Best Practices:**
- Keep plugins in a dedicated directory outside web root when possible
- Regularly audit installed plugins
- Test plugins in a development environment first
- Remove unused plugins

## Support

For questions or issues:
1. Check EXTENSION_GUIDE.md for detailed examples
2. Review inline code documentation
3. Open an issue on GitHub
