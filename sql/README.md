# Database Migration Guide

This folder contains SQL migration scripts for the PhCard database.

## Migration Order

Run the SQL files in the following order to set up the database:

1. **database.sql** - Base schema with users, cards, decks, and game tables
2. **database_extensions.sql** - Advanced card mechanics (keywords, mana, status effects)
3. **database_multiplayer.sql** - Multiplayer game system (PvP functionality)
4. **database_quest_achievement_system.sql** - Quest and achievement system
5. **database_quest_reset_and_shop.sql** - Quest reset functionality and shop features
6. **add_card_health.sql** - Add health attribute to cards
7. **database_admin_and_cards.sql** - Admin features and additional card data
8. **add_multiplayer_rewards.sql** - Add rewards columns to multiplayer_games table

## Quick Setup

```bash
# Connect to MySQL
mysql -u root -p

# Run migrations in order
mysql -u root -p < sql/database.sql
mysql -u root -p < sql/database_extensions.sql
mysql -u root -p < sql/database_multiplayer.sql
mysql -u root -p < sql/database_quest_achievement_system.sql
mysql -u root -p < sql/database_quest_reset_and_shop.sql
mysql -u root -p < sql/add_card_health.sql
mysql -u root -p < sql/database_admin_and_cards.sql
mysql -u root -p < sql/add_multiplayer_rewards.sql
```

## Updating Existing Installation

If you already have a database and need to apply new migrations:

```bash
# For multiplayer rewards feature (fixes losing screen and rewards display)
mysql -u root -p < sql/add_multiplayer_rewards.sql
```

## Notes

- Make sure MySQL server is running before executing migrations
- Update `config.php` with your database credentials
- Backup your database before running migrations on an existing installation
