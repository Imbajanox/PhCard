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
- `GameActions.php` - Game flow operations (start, mulligan, end) ✓
- `BattleSystem.php` - Combat resolution and battle mechanics ✓
- `AIPlayer.php` - AI opponent logic and card scoring ✓
- `CardEffects.php` - Card effects integrated into BattleSystem ✓

#### `/features/` - Feature Modules
- `Shop.php` - Shop operations and currency management ✓
- `DailyReward.php` - Daily login rewards ✓
- `Quest.php` - Quest system logic ✓
- `Achievement.php` - Achievement tracking and unlocking ✓

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
- `game.js` - Game state, card playing, turn management (425 lines) ✓
- `ui-effects.js` - Visual feedback (damage numbers, animations) (150 lines) ✓

#### `/deck/` - Deck Management
- `deck-builder.js` - Deck building interface and operations (450 lines) ✓

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

### After Refactoring (Phase 3 Complete)
- **Backend modules**: Each class < 450 lines
  - GameActions.php: ~330 lines
  - BattleSystem.php: ~620 lines
  - AIPlayer.php: ~340 lines
  - Shop.php: ~300 lines
  - Quest.php: ~215 lines
  - Achievement.php: ~125 lines
- **Frontend modules**: Each < 450 lines
  - game.js: 425 lines
  - deck-builder.js: 450 lines
  - ui-effects.js: 150 lines
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
