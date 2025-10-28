# Card Health Point (HP) System

## Overview

This update implements a proper health point system for cards in PhCard, similar to popular card games like Hearthstone and Magic: The Gathering.

## Changes Made

### Database Schema

Added a `health` field to the `cards` table:
- `health` (INT): The maximum health points of a monster card
- For backwards compatibility, cards without a health value will use their `defense` value as health

**Migration**: Run `sql/add_card_health.sql` to update your database.

### Battle Mechanics

The battle system has been completely redesigned to use HP-based combat:

#### Old System (Defense-based)
- Monsters compared ATK vs DEF
- If ATK > DEF, the defender was destroyed
- Damage didn't accumulate across turns
- Defenders didn't fight back

#### New System (HP-based)
- Monsters have `current_health` and `max_health`
- When monsters attack each other, **both deal damage simultaneously** (like Hearthstone)
  - Attacker deals its ATK damage to defender's HP
  - Defender deals its ATK damage back to attacker's HP (counter-attack)
- A monster is destroyed when `current_health <= 0`
- Damage persists across turns until the monster is healed or destroyed
- Direct attacks to players work the same (no counter-attack from hero)

### Card Display

Cards now show their HP instead of DEF:
- **ATK** (red): Attack power (damage dealt)
- **HP** (green): Current Health / Max Health
- Example: "ATK: 300" and "HP: 450/500"
- When HP is full, only shows the value: "HP: 500"

### Game Flow Example

```
Turn 1:
Player plays "Dragon" (ATK: 400, HP: 500)
AI plays "Knight" (ATK: 300, HP: 400)

Turn 2:
Player attacks with Dragon:
  - Dragon deals 400 damage to Knight (HP: 400 -> 0) → Knight destroyed
  - Knight deals 300 counter-damage to Dragon (HP: 500 -> 200) → Dragon survives
  
Dragon now has 200/500 HP and can attack next turn
```

### Special Mechanics

- **Lifesteal**: Attacker heals for damage dealt (to monsters, not counter-damage taken)
- **Divine Shield**: Absorbs all damage from one attack (still allows counter-attack)
- **Poison**: Now deals damage to HP over time
- **Windfury**: Attacks twice, receiving counter-attacks both times

## Backwards Compatibility

- Existing cards without a `health` field will automatically use their `defense` value as health
- The `defense` field is still present in the database and can be used for future armor/shield mechanics
- All existing game features continue to work

## Testing

Run the test script to verify the implementation:
```bash
php /tmp/test_hp_system.php
```

## Impact on Gameplay

This change makes the game more strategic:
1. **Resource Management**: Damaged monsters persist, so players must decide whether to trade monsters or protect them
2. **Mutual Destruction**: Two strong monsters can destroy each other, making combat more dynamic
3. **Value Trading**: Players can chip away at high-HP monsters over multiple turns
4. **Healing**: Healing effects and Lifesteal become more valuable
5. **Board Control**: Keeping monsters alive is more important than before

## Visual Changes

Monster cards now display:
```
┌─────────────┐
│ [Mana Cost] │
│   Dragon    │
│   MONSTER   │
│             │
│ ATK: 400    │
│ HP: 450/500 │
│             │
│ [Keywords]  │
└─────────────┘
```

## Migration Notes

When updating an existing installation:
1. Back up your database
2. Run `sql/add_card_health.sql`
3. All existing cards will have their health set to their defense value
4. You can manually update card health values as needed
5. New cards should include a health value when created

## Design Philosophy

This implementation follows the Hearthstone model where:
- Combat is simultaneous (both creatures deal damage)
- Damage persists between turns
- Dead creatures are removed immediately
- Face (hero) attacks don't trigger counter-attacks

This creates more interesting decisions and strategic depth compared to the old one-shot destruction system.
