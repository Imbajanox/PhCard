# PhCard Source Directory

This directory contains the refactored, modular source code for PhCard.

## Structure

### `/src/backend/` - PHP Backend Code

#### `/core/` - Core Infrastructure
- `Database.php` - Singleton database connection manager with query helpers
- `Response.php` - Standardized JSON response helper for API endpoints
- `Auth.php` - (Planned) Authentication and session management
- `Session.php` - (Planned) Session handling utilities

#### `/models/` - Data Models
- `User.php` - User data model with CRUD operations and game stats
- `Deck.php` - Deck data model with card management
- `Card.php` - (Planned) Card data model
- `Quest.php` - (Planned) Quest data model

#### `/game/` - Game Logic
- `GameState.php` - Game state management (initialization, persistence, turn management)
- `GameActions.php` - (Planned) Player actions (play card, end turn, etc.)
- `BattleSystem.php` - (Planned) Combat resolution and battle mechanics
- `AIPlayer.php` - (Planned) AI opponent logic and card scoring
- `CardEffects.php` - (Planned) Card effect implementations

#### `/features/` - Feature Modules
- `Shop.php` - (Planned) Shop operations and currency management
- `Quest.php` - (Planned) Quest system logic
- `Achievement.php` - (Planned) Achievement tracking and unlocking
- `DailyReward.php` - (Planned) Daily login rewards

#### `/utils/` - Utility Classes
- `CardFactory.php` - Card creation and initialization
- `CardEffectRegistry.php` - Registry for card effects
- `GameEventSystem.php` - Event system for game hooks
- `PluginSystem.php` - Plugin architecture

### `/src/frontend/js/` - JavaScript Frontend Code

#### `/core/` - Core Application
- `app.js` - Minimal entry point, global state, initialization

#### `/auth/` - Authentication
- `auth.js` - Login, register, logout, session management (119 lines)

#### `/user/` - User Features
- `profile.js` - User profile display, leaderboard (111 lines)
- `collection.js` - Card collection display and rendering (99 lines)

#### `/game/` - Game Logic
- `game.js` - (Planned) Game state, card playing, turn management
- `battle.js` - (Planned) Battle animations and resolution
- `ui-effects.js` - (Planned) Visual feedback (damage numbers, animations)

#### `/deck/` - Deck Management
- `deck-builder.js` - (Planned) Deck building interface and operations

#### `/features/` - Feature Modules
- `/shop/` - (Planned) Shop interface and purchasing
- `/quests/` - (Planned) Quest display and tracking
- `/achievements/` - (Planned) Achievement display

#### `/dashboard/` - Analytics Dashboard
- `overview.js` - (Planned) Dashboard overview and stats
- `card-stats.js` - (Planned) Card statistics and analysis
- `simulation.js` - (Planned) Deck simulation

## Usage

### PHP Backend

Include the autoloader in your API endpoints:

```php
require_once '../autoload.php';
require_once '../config.php';

// Use classes
$user = new User($_SESSION['user_id']);
$deck = new Deck($deckId);
$gameState = new GameState();

// Send responses
Response::success(['data' => $user->getData()]);
Response::error('Invalid request', 400);
```

### JavaScript Frontend

Load modules in proper order in HTML:

```html
<!-- Core (must load first) -->
<script src="src/frontend/js/core/app.js"></script>

<!-- User modules -->
<script src="src/frontend/js/user/profile.js"></script>
<script src="src/frontend/js/user/collection.js"></script>

<!-- Authentication -->
<script src="src/frontend/js/auth/auth.js"></script>

<!-- Game modules -->
<script src="src/frontend/js/game/ui-effects.js"></script>
<script src="src/frontend/js/game/game.js"></script>
```

## Benefits

### Before Refactoring
- `app.js`: 1383 lines - everything in one file
- `game.php`: 1531 lines - entire game backend in one file
- Hard to maintain, test, and extend

### After Refactoring
- Focused modules: each < 200 lines
- Clear separation of concerns
- Easy to find and fix bugs
- Testable units
- Team-friendly development

## Migration Status

See [REFACTORING_GUIDE.md](../REFACTORING_GUIDE.md) for detailed implementation status and migration strategy.

## Contributing

When adding new features:
1. Create new files in appropriate directories
2. Keep files focused and < 300 lines
3. Follow existing patterns
4. Update this README if adding new directories
