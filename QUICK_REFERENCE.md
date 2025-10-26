# PhCard Extension Quick Reference

A quick reference for common extension tasks in PhCard.

## Quick Start Commands

### Import Cards from JSON
```bash
php import_cards.php my_cards.json SET_CODE
```

### Install Quest/Achievement System
```bash
mysql -u USER -p DATABASE < database_quest_achievement_system.sql
```

### Validate Card JSON
```bash
python3 -m json.tool my_cards.json
```

### Run Integration Tests
```bash
./test_extensibility.sh
```

## Common Code Snippets

### 1. Create a Custom Card Effect

```php
<?php
require_once 'api/CardEffectRegistry.php';

CardEffectRegistry::register('my_effect', function($context) {
    $value = $context['value'] ?? 100;
    $gameState = $context['gameState'];
    
    // Your effect logic here
    // Example: Deal damage and draw a card
    $gameState['ai_hp'] -= $value;
    if (!empty($gameState['available_cards'])) {
        $gameState['player_hand'][] = array_shift($gameState['available_cards']);
    }
    
    return $gameState;
});
```

### 2. Hook into Game Event

```php
<?php
require_once 'api/GameEventSystem.php';

GameEventSystem::on('card_played', function($data) {
    // Log card plays
    error_log("Card played: {$data['card']['name']}");
    
    // Update custom statistics
    // Track achievements
    // Trigger combo effects
    
    return $data; // Always return data
}, 15); // Priority (optional, default 10)
```

### 3. Add a New Quest

```sql
INSERT INTO quests (
    name, 
    description, 
    quest_type, 
    objective_type, 
    objective_target, 
    xp_reward,
    required_level
) VALUES (
    'Win Streak',
    'Win 5 games in a row',
    'weekly',
    'win_games',
    5,
    200,
    5
);
```

### 4. Add an Achievement

```sql
INSERT INTO achievements (
    name,
    description,
    category,
    achievement_type,
    requirement_value,
    xp_reward,
    rarity
) VALUES (
    'Card Master',
    'Collect 50 cards',
    'collection',
    'card_collection',
    50,
    500,
    'epic'
);
```

### 5. Create a Card Expansion

```json
[
  {
    "name": "New Monster",
    "type": "monster",
    "attack": 500,
    "defense": 300,
    "mana_cost": 3,
    "keywords": ["charge"],
    "required_level": 5,
    "rarity": "rare",
    "card_class": "neutral",
    "description": "A powerful new card"
  }
]
```

### 6. Validate Card Configuration

```php
<?php
require_once 'api/CardFactory.php';

$config = [
    'name' => 'Test Card',
    'type' => 'monster',
    'attack' => 500
];

$validation = CardFactory::validate($config);
if ($validation['valid']) {
    echo "Valid!";
} else {
    print_r($validation['errors']);
}
```

### 7. Export Cards to JSON

```php
<?php
require_once 'api/CardFactory.php';

$conn = getDBConnection();

// Export legendary cards only
CardFactory::exportToJSON(
    $conn, 
    'legendary_cards.json',
    ['rarity' => 'legendary']
);
```

## Card Properties Reference

| Property | Type | Required | Values |
|----------|------|----------|--------|
| name | string | Yes | Any unique name |
| type | string | Yes | 'monster', 'spell' |
| attack | int | No | 0+ (monsters) |
| defense | int | No | 0+ (monsters) |
| effect | string | No | 'type:value' (spells) |
| mana_cost | int | No | 0-10 (default: 1) |
| keywords | array/string | No | See keyword list |
| required_level | int | No | 1-60 (default: 1) |
| rarity | string | No | common, rare, epic, legendary |
| card_class | string | No | neutral, warrior, mage, rogue, priest, paladin |
| overload | int | No | 0+ (default: 0) |
| description | string | No | Flavor text |

## Available Keywords

- `charge` - Attack immediately
- `taunt` - Must be attacked first  
- `rush` - Attack minions immediately
- `divine_shield` - Block next damage
- `windfury` - Attack twice
- `stealth` - Can't be targeted
- `lifesteal` - Heal for damage
- `poison` - Apply poison effect

## Effect Types

### Spell Effects
- `damage:X` - Deal X damage
- `heal:X` - Heal X HP
- `boost:X` - +X attack to all monsters
- `shield:X` - Gain X shield
- `draw:X` - Draw X cards
- `poison:X` - Apply poison (X per turn)
- `burn:X` - Apply burn (escalating)
- `stun:X` - Stun for X turns
- `freeze:X` - Freeze for X turns

### Custom Effects
Register with `CardEffectRegistry::register('name', function)`

## Game Events

| Event | When | Data |
|-------|------|------|
| game_start | Game begins | user_id, ai_level |
| game_end | Game ends | user_id, result, xp_gained |
| turn_start | Turn starts | turn_number |
| turn_end | Turn ends | turn_number |
| card_played | Card played | card, user_id |
| card_drawn | Card drawn | card, user_id |
| damage_dealt | Damage dealt | amount, source, target |
| healing_done | Healing done | amount, target |
| level_up | Player levels up | old_level, new_level |
| achievement_unlocked | Achievement earned | achievement_id |
| quest_completed | Quest done | quest_id |

## Quest Objective Types

- `win_games` - Win X games
- `play_cards` - Play X cards
- `deal_damage` - Deal X damage
- `heal_hp` - Heal X HP
- `play_card_type` - Play X of type (metadata: card_type)
- `use_keyword` - Use X cards with keyword (metadata: keyword)
- `reach_level` - Reach level X
- `collect_cards` - Collect X cards (metadata: rarity)
- `custom` - Custom logic (requires code)

## Achievement Types

- `total_wins` - Win X total games
- `win_streak` - Win X in a row
- `card_collection` - Collect X cards
- `level_reached` - Reach level X
- `damage_milestone` - Deal X in one game
- `perfect_game` - Win with conditions
- `custom` - Custom logic

## API Endpoints

### Quest System
```
GET  /api/quests.php?action=get_active_quests
GET  /api/quests.php?action=get_quest_progress
POST /api/quests.php?action=claim_quest_reward
GET  /api/quests.php?action=get_achievements
POST /api/quests.php?action=update_quest_progress
POST /api/quests.php?action=check_achievements
```

### Card Sets
```
GET  /api/card_sets.php?action=list_sets
GET  /api/card_sets.php?action=get_set&set_id=X
GET  /api/card_sets.php?action=get_set_cards&set_id=X
POST /api/card_sets.php?action=create_set (admin)
POST /api/card_sets.php?action=update_set (admin)
```

## File Locations

- Effect Registry: `api/CardEffectRegistry.php`
- Event System: `api/GameEventSystem.php`
- Card Factory: `api/CardFactory.php`
- Quest API: `api/quests.php`
- Card Set API: `api/card_sets.php`
- Import Tool: `import_cards.php`
- Database Schema: `database_quest_achievement_system.sql`

## Best Practices

1. **Test JSON before import** - Use `python3 -m json.tool`
2. **Use event system** - Don't modify core files
3. **Validate card configs** - Use `CardFactory::validate()`
4. **Version your expansions** - Tag releases
5. **Document new effects** - Add to EXTENSION_GUIDE.md
6. **Balance test** - Use simulation framework
7. **Set appropriate levels** - Don't make everything level 1

## Troubleshooting

### Card import fails
- Check JSON syntax with `python3 -m json.tool file.json`
- Verify required fields (name, type)
- Check database connection

### Effect not working
- Ensure effect is registered: `CardEffectRegistry::init()`
- Check effect name matches card effect string
- Verify function returns modified gameState

### Event not firing
- Check event name spelling
- Ensure GameEventSystem is initialized
- Verify event is triggered in game code

## Examples

See these files for complete examples:
- `card_expansion_example.json` - 15 example cards
- `EXTENSION_GUIDE.md` - Detailed tutorials
- `EXTENSIBILITY_README.md` - Overview
- `test_extensibility.sh` - Integration tests

## Support

- Full guide: `EXTENSION_GUIDE.md`
- Overview: `EXTENSIBILITY_README.md`
- Test framework: `test_extensibility.sh`
- GitHub Issues: https://github.com/Imbajanox/PhCard/issues
