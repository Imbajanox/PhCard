# PhCard Extension Guide

## Overview

This guide explains how to extend PhCard with new features and content. The game has been designed with expandability in mind, using plugin systems, event-driven architecture, and configuration-based content creation.

## Table of Contents

1. [Adding New Cards](#adding-new-cards)
2. [Creating Custom Card Effects](#creating-custom-card-effects)
3. [Implementing New Game Events](#implementing-new-game-events)
4. [Adding Quests and Achievements](#adding-quests-and-achievements)
5. [Creating Card Expansions](#creating-card-expansions)
6. [Extending AI Behavior](#extending-ai-behavior)
7. [Custom Deck Archetypes](#custom-deck-archetypes)

---

## Adding New Cards

### Method 1: Configuration-Based (Recommended)

Create a JSON file with your card definitions:

```json
[
  {
    "name": "Fire Elemental",
    "type": "monster",
    "attack": 600,
    "defense": 200,
    "mana_cost": 3,
    "keywords": ["charge", "lifesteal"],
    "required_level": 5,
    "rarity": "rare",
    "card_class": "mage",
    "description": "A blazing elemental from the fire plane"
  },
  {
    "name": "Arcane Blast",
    "type": "spell",
    "effect": "damage:800",
    "mana_cost": 4,
    "required_level": 7,
    "rarity": "epic",
    "card_class": "mage",
    "description": "Unleash arcane energy on your opponent"
  }
]
```

Import using CardFactory:

```php
<?php
require_once 'api/CardFactory.php';

$conn = getDBConnection();
$results = CardFactory::importFromJSON('my_cards.json', $conn);

echo "Imported: {$results['inserted']} cards\n";
echo "Failed: {$results['failed']} cards\n";
```

### Method 2: Programmatic Creation

```php
<?php
require_once 'api/CardFactory.php';

$cardConfig = [
    'name' => 'Shadow Assassin',
    'type' => 'monster',
    'attack' => 1000,
    'defense' => 100,
    'mana_cost' => 5,
    'keywords' => ['stealth', 'charge'],
    'required_level' => 10,
    'rarity' => 'epic',
    'description' => 'Strikes from the shadows'
];

$card = CardFactory::createFromConfig($cardConfig);
// Insert into database...
```

### Available Card Properties

- **name** (required): Card name
- **type** (required): 'monster' or 'spell'
- **attack**: Attack value (monsters)
- **defense**: Defense value (monsters)
- **effect**: Effect string (spells, format: "type:value")
- **mana_cost**: Mana cost (1-10)
- **keywords**: Array or comma-separated string
- **required_level**: Level needed to unlock
- **rarity**: 'common', 'rare', 'epic', 'legendary'
- **card_class**: 'neutral', 'warrior', 'mage', 'rogue', 'priest', 'paladin'
- **overload**: Overload amount
- **choice_effects**: JSON array for Choose One mechanics
- **description**: Flavor text

### Available Keywords

- **charge**: Can attack immediately
- **taunt**: Must be attacked first
- **rush**: Can attack minions immediately
- **divine_shield**: Prevents next damage
- **windfury**: Attacks twice per turn
- **stealth**: Cannot be targeted until attacks
- **lifesteal**: Heals for damage dealt
- **poison**: Applies poison status effect

---

## Creating Custom Card Effects

Use the CardEffectRegistry to add new effect types:

```php
<?php
require_once 'api/CardEffectRegistry.php';

// Initialize registry
CardEffectRegistry::init();

// Register custom effect
CardEffectRegistry::register('summon_token', function($context) {
    $gameState = $context['gameState'];
    
    // Create a 1/1 token monster
    $token = [
        'name' => 'Token',
        'type' => 'monster',
        'attack' => 100,
        'defense' => 100,
        'is_token' => true
    ];
    
    $gameState['player_field'][] = $token;
    return $gameState;
});

// Register effect with variable power
CardEffectRegistry::register('multiply_attack', function($context) {
    $multiplier = $context['value'] ?? 2;
    $gameState = $context['gameState'];
    
    foreach ($gameState['player_field'] as &$monster) {
        $monster['attack'] *= $multiplier;
    }
    
    return $gameState;
});
```

### Using Custom Effects in Cards

```json
{
  "name": "Token Master",
  "type": "spell",
  "effect": "summon_token:1",
  "mana_cost": 2,
  "description": "Summon a 1/1 token"
}
```

### Built-in Effects

- **damage:X** - Deal X damage
- **heal:X** - Heal X HP
- **boost:X** - Increase attack by X
- **shield:X** - Add X temporary shield
- **draw:X** - Draw X cards
- **poison:X** - Apply poison (X damage per turn)
- **burn:X** - Apply burn (escalating damage)
- **stun** - Stun target
- **freeze** - Freeze target
- **lifesteal** - Heal for damage dealt

---

## Implementing New Game Events

Use the GameEventSystem to hook into game actions:

```php
<?php
require_once 'api/GameEventSystem.php';

// Listen for card plays
GameEventSystem::on('card_played', function($data) {
    // Track statistics
    if (isset($data['card']['id'])) {
        // Update play count in database
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            UPDATE card_balance_metrics 
            SET times_played = times_played + 1 
            WHERE card_id = ?
        ");
        $stmt->execute([$data['card']['id']]);
    }
    return $data;
}, 20); // Priority 20 (higher = runs earlier)

// Listen for damage events
GameEventSystem::on('damage_dealt', function($data) {
    $amount = $data['amount'] ?? 0;
    $userId = $data['user_id'] ?? null;
    
    // Update quest progress for damage dealing
    if ($userId && $amount > 0) {
        // Call quest progress update
        updateQuestProgress('deal_damage', $amount, $userId);
    }
    
    return $data;
});

// Listen for game end
GameEventSystem::on('game_end', function($data) {
    if ($data['result'] === 'win') {
        // Check for achievements
        checkAchievements($data['user_id']);
    }
    return $data;
});
```

### Available Events

- **game_start** - Game begins
- **game_end** - Game ends
- **turn_start** - Turn begins
- **turn_end** - Turn ends
- **card_drawn** - Card drawn
- **card_played** - Card played
- **monster_summoned** - Monster placed on field
- **spell_cast** - Spell cast
- **damage_dealt** - Damage dealt
- **healing_done** - Healing occurred
- **monster_destroyed** - Monster destroyed
- **status_effect_applied** - Status applied
- **mana_gained** - Mana gained
- **mana_spent** - Mana spent
- **level_up** - Player leveled up
- **achievement_unlocked** - Achievement earned
- **quest_completed** - Quest completed

### Event Data Structure

Events receive a context array with relevant data:

```php
$eventData = [
    'user_id' => 123,
    'card' => ['id' => 45, 'name' => 'Fireball'],
    'gameState' => $currentGameState,
    'target' => 'ai',
    'value' => 400,
    // ... other relevant data
];
```

---

## Adding Quests and Achievements

### Creating Quests via SQL

```sql
INSERT INTO quests (
    name, 
    description, 
    quest_type, 
    objective_type, 
    objective_target, 
    objective_metadata,
    xp_reward,
    required_level
) VALUES (
    'Legendary Collector',
    'Collect 5 legendary cards',
    'story',
    'collect_cards',
    5,
    JSON_OBJECT('rarity', 'legendary'),
    200,
    15
);
```

### Quest Objective Types

- **win_games**: Win X games
- **play_cards**: Play X cards
- **deal_damage**: Deal X total damage
- **heal_hp**: Heal X total HP
- **play_card_type**: Play X cards of specific type (metadata: card_type)
- **use_keyword**: Play X cards with keyword (metadata: keyword)
- **reach_level**: Reach level X
- **collect_cards**: Collect X cards (metadata: rarity, card_class)
- **custom**: Custom logic (requires code)

### Creating Achievements

```sql
INSERT INTO achievements (
    name,
    description,
    category,
    achievement_type,
    requirement_value,
    requirement_metadata,
    xp_reward,
    rarity,
    is_hidden
) VALUES (
    'Perfect Victory',
    'Win without taking any damage',
    'combat',
    'perfect_game',
    1,
    JSON_OBJECT('damage_taken', 0),
    300,
    'epic',
    true
);
```

### Achievement Types

- **win_streak**: Win X games in a row
- **total_wins**: Win X total games
- **card_collection**: Collect X cards
- **level_reached**: Reach level X
- **damage_milestone**: Deal X damage in one game
- **perfect_game**: Win with specific conditions
- **custom**: Custom logic

### Updating Quest Progress Programmatically

```php
// In your game logic
GameEventSystem::trigger('quest_progress', [
    'user_id' => $userId,
    'objective_type' => 'play_cards',
    'value' => 1,
    'metadata' => ['card_type' => 'spell']
]);
```

---

## Creating Card Expansions

### Step 1: Create Card Set

```sql
INSERT INTO card_sets (name, code, description, set_type, release_date)
VALUES (
    'Shadow Realm',
    'SHDW',
    'Dark cards from the shadow realm',
    'expansion',
    NOW()
);
```

### Step 2: Create Cards for Set

```json
[
  {
    "name": "Shadow Walker",
    "type": "monster",
    "attack": 700,
    "defense": 400,
    "keywords": ["stealth"],
    "mana_cost": 4,
    "rarity": "rare",
    "required_level": 12,
    "description": "Expansion: Shadow Realm"
  }
]
```

### Step 3: Link Cards to Set

```sql
INSERT INTO card_set_members (card_id, set_id, set_number)
SELECT c.id, s.id, ROW_NUMBER() OVER (ORDER BY c.id)
FROM cards c
CROSS JOIN card_sets s
WHERE s.code = 'SHDW'
AND c.description LIKE '%Shadow Realm%';
```

### Step 4: Export Expansion Cards

```php
<?php
require_once 'api/CardFactory.php';

$conn = getDBConnection();

// Get cards from expansion
$stmt = $conn->prepare("
    SELECT c.* FROM cards c
    JOIN card_set_members csm ON c.id = csm.card_id
    JOIN card_sets cs ON csm.set_id = cs.id
    WHERE cs.code = 'SHDW'
");
$stmt->execute();
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Export to JSON for distribution
CardFactory::exportToJSON($conn, 'shadow_realm_expansion.json', ['set_code' => 'SHDW']);
```

---

## Extending AI Behavior

The AI system is data-driven. You can create custom AI strategies:

### Creating AI Strategy Configuration

```json
{
  "strategy_name": "aggro",
  "priority": {
    "play_monsters": 100,
    "attack_face": 90,
    "play_spells": 50,
    "heal": 20
  },
  "conditions": {
    "heal_threshold": 500,
    "attack_threshold": 1500
  }
}
```

### Implementing Custom AI Logic

Extend game.php with custom AI behavior:

```php
function customAIStrategy($gameState, $aiLevel) {
    $actions = [];
    
    // Aggressive strategy - play all monsters
    foreach ($gameState['ai_hand'] as $card) {
        if ($card['type'] === 'monster' && canAffordCard($card, $gameState)) {
            $actions[] = ['type' => 'play', 'card' => $card];
        }
    }
    
    // Then play direct damage spells
    foreach ($gameState['ai_hand'] as $card) {
        if ($card['type'] === 'spell' && 
            strpos($card['effect'], 'damage:') === 0 && 
            canAffordCard($card, $gameState)) {
            $actions[] = ['type' => 'play', 'card' => $card];
        }
    }
    
    return $actions;
}
```

---

## Custom Deck Archetypes

### Adding New Archetype

```sql
INSERT INTO deck_archetypes (
    name,
    description,
    preferred_keywords,
    min_cards,
    max_cards,
    max_duplicates
) VALUES (
    'OTK',
    'One Turn Kill deck focused on burst damage',
    'charge,lifesteal',
    30,
    30,
    2
);
```

### Archetype Validation

The deck builder automatically validates decks based on archetype rules. You can extend validation in `api/deck.php`.

---

## Best Practices

1. **Use Configuration Over Code**: Prefer JSON configs and database entries for content
2. **Hook into Events**: Use GameEventSystem instead of modifying core files
3. **Register Effects**: Use CardEffectRegistry for new card mechanics
4. **Version Control**: Tag releases when adding new card sets
5. **Test Balance**: Use analytics and simulation tools to test new cards
6. **Document Cards**: Always include clear descriptions
7. **Progressive Unlock**: Set appropriate required_level for new cards

---

## Testing Your Extensions

### Test Card Balance

```php
// Use simulation API
$result = runSimulation([
    'deck1' => 'custom_deck',
    'deck2' => 'standard_deck',
    'iterations' => 1000
]);

echo "Win rate: " . $result['deck1_wins'] / 1000;
```

### Validate Card Configs

```php
$config = json_decode(file_get_contents('my_cards.json'), true);
foreach ($config as $cardConfig) {
    $validation = CardFactory::validate($cardConfig);
    if (!$validation['valid']) {
        echo "Errors in {$cardConfig['name']}:\n";
        print_r($validation['errors']);
    }
}
```

---

## Examples

See the following files for complete examples:

- `api/CardFactory.php` - Card creation examples
- `api/CardEffectRegistry.php` - Effect implementation
- `api/GameEventSystem.php` - Event system usage
- `api/quests.php` - Quest/achievement system
- `database_quest_achievement_system.sql` - Database schema

---

## Support

For questions or issues, please open an issue on the GitHub repository.
