# PhCard Refactoring Implementation Guide

## Overview
This document outlines the comprehensive refactoring strategy for the PhCard project to split large files into smaller, maintainable modules and reorganize the folder structure.

## Current State Analysis

### Large Files Identified
- **Frontend (JavaScript)**
  - `public/js/app.js` - 1383 lines (authentication, game logic, deck builder, UI effects, card collection)
  - `public/js/dashboard.js` - 452 lines (analytics, statistics, simulation)
  - `web/js/features/shop.js` - 523 lines (shop, packs, daily rewards)

- **Backend (PHP)**
  - `api/game.php` - 1531 lines (game state, battle system, AI logic, card effects)
  - `api/quests.php` - 575 lines (quest management)
  - `api/shop.php` - 443 lines (shop operations)
  - `api/analytics.php` - 434 lines (analytics and statistics)
  - `api/deck.php` - 416 lines (deck management)

## New Folder Structure

```
PhCard/
├── src/
│   ├── backend/
│   │   ├── core/                    # Core infrastructure
│   │   │   ├── Database.php         ✓ Created
│   │   │   ├── Response.php         ✓ Created
│   │   │   ├── Auth.php            
│   │   │   └── Session.php         
│   │   ├── models/                  # Data models
│   │   │   ├── User.php            ✓ Created
│   │   │   ├── Deck.php            ✓ Created
│   │   │   ├── Card.php            
│   │   │   └── Quest.php           
│   │   ├── game/                    # Game logic
│   │   │   ├── GameState.php       ✓ Created
│   │   │   ├── GameActions.php     
│   │   │   ├── BattleSystem.php    
│   │   │   ├── AIPlayer.php        
│   │   │   └── CardEffects.php     
│   │   ├── features/                # Feature modules
│   │   │   ├── Shop.php            
│   │   │   ├── Quest.php           
│   │   │   ├── Achievement.php     
│   │   │   └── DailyReward.php     
│   │   └── utils/                   # Utilities
│   │       ├── CardFactory.php     ✓ Copied
│   │       ├── CardEffectRegistry.php ✓ Copied
│   │       ├── GameEventSystem.php ✓ Copied
│   │       └── PluginSystem.php    ✓ Copied
│   └── frontend/
│       └── js/
│           ├── core/
│           │   ├── app.js          ✓ Created (minimal entry point)
│           │   ├── navigation.js   
│           │   └── state.js        
│           ├── auth/
│           │   └── auth.js         ✓ Created (moved from modules)
│           ├── user/
│           │   ├── profile.js      ✓ Created (moved from modules)
│           │   └── collection.js   ✓ Created (moved from modules)
│           ├── game/
│           │   ├── game.js         
│           │   ├── battle.js       
│           │   └── ui-effects.js   
│           ├── deck/
│           │   └── deck-builder.js 
│           ├── features/
│           │   ├── shop/
│           │   ├── quests/
│           │   └── achievements/
│           └── dashboard/
│               ├── overview.js     
│               ├── card-stats.js   
│               └── simulation.js   
├── api/                             # Thin API controllers
├── public/                          # Public assets
├── autoload.php                     ✓ Created (PHP autoloader)
├── config.php
└── index.html

```

## Implementation Status

### Phase 1: Infrastructure ✓
- [x] Created new folder structure
- [x] Created PHP autoloader (autoload.php)
- [x] Created core classes (Database.php, Response.php)
- [x] Created model classes (User.php, Deck.php)
- [x] Created game state management (GameState.php)
- [x] Moved utility classes to new location

### Phase 2: JavaScript Refactoring ✓
- [x] Created modular structure
- [x] Extracted authentication module (auth.js) - 119 lines
- [x] Extracted user profile module (profile.js) - 111 lines  
- [x] Extracted card collection module (collection.js) - 99 lines
- [x] Created minimal core app.js - 30 lines
- [x] Extract game logic module (game.js) - 425 lines
- [x] Extract UI effects module (ui-effects.js) - 150 lines
- [x] Extract deck builder module (deck-builder.js) - 450 lines
- [x] Update HTML files to load modules in correct order

### Phase 3: PHP Refactoring (Planned)
- [ ] Extract game logic from game.php
- [ ] Create AIPlayer class
- [ ] Create BattleSystem class
- [ ] Create CardEffects class
- [ ] Refactor shop.php into Shop model
- [ ] Refactor quests.php into Quest model
- [ ] Update API endpoints to use new classes

### Phase 4: Testing & Validation (Pending)
- [ ] Test authentication flow
- [ ] Test game functionality  
- [ ] Test deck builder
- [ ] Test shop features
- [ ] Test analytics dashboard

## Module Dependencies

### JavaScript Module Load Order
```html
<!-- Core must load first -->
<script src="src/frontend/js/core/app.js"></script>

<!-- Then utilities and shared modules -->
<script src="src/frontend/js/user/profile.js"></script>
<script src="src/frontend/js/user/collection.js"></script>

<!-- Authentication -->
<script src="src/frontend/js/auth/auth.js"></script>

<!-- Game modules -->
<script src="src/frontend/js/game/ui-effects.js"></script>
<script src="src/frontend/js/game/game.js"></script>

<!-- Deck builder -->
<script src="src/frontend/js/deck/deck-builder.js"></script>
```

### PHP Class Dependencies
```php
// In API files, include autoloader first
require_once '../autoload.php';
require_once '../config.php';

// Then use classes
$user = new User($_SESSION['user_id']);
$gameState = new GameState();
```

## Benefits of Refactoring

### Maintainability
- **Before**: 1383-line app.js with mixed concerns
- **After**: 6-8 focused modules, each <200 lines
- Easier to find and fix bugs
- Clearer code organization

### Scalability
- Easy to add new features without touching existing code
- Clear separation of concerns
- Modular architecture supports team development

### Testability
- Each module can be tested independently
- Smaller units of code are easier to test
- Better test coverage possible

### Performance
- Potential for lazy loading modules
- Only load what's needed for each page
- Better browser caching (smaller files)

## Migration Strategy

### For Developers
1. New features should use the new structure
2. When fixing bugs in old files, consider extracting that section to a module
3. Gradual migration - both structures can coexist temporarily

### For Deployment
1. Backup current working version
2. Deploy new structure alongside old files
3. Update HTML to load new modules
4. Test thoroughly
5. Remove old files once validated

## Examples

### Before (app.js - monolithic)
```javascript
// 1383 lines in one file
// Lines 1-29: Global state
// Lines 30-147: Authentication
// Lines 148-212: Leaderboard
// Lines 213-258: User profile
// Lines 259-356: Card collection
// Lines 357-781: Game logic
// Lines 782-933: Visual effects
// Lines 934-1373: Deck builder
```

### After (modular)
```javascript
// core/app.js - 30 lines
// auth/auth.js - 119 lines
// user/profile.js - 111 lines  
// user/collection.js - 99 lines
// game/game.js - 425 lines
// game/ui-effects.js - 152 lines
// deck/deck-builder.js - 440 lines
```

### PHP Backend Example

#### Before (game.php - 1531 lines)
```php
// All in one file
function startGame() { ... }
function performAITurn() { ... }
function applySpellEffect() { ... }
function endGame() { ... }
// + 10 more functions
```

#### After (modular)
```php
// api/game.php - 150 lines (controller only)
require_once '../autoload.php';
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'start':
        $game = new GameActions();
        Response::success($game->start());
        break;
}

// src/backend/game/GameActions.php - 300 lines
class GameActions {
    public function start() {
        $gameState = new GameState();
        $user = new User($_SESSION['user_id']);
        $deck = $user->getActiveDeck();
        $gameState->initializePlayerDeck($deck->getExpandedCards());
        return ['game_state' => $gameState->getState()];
    }
}

// src/backend/game/AIPlayer.php - 400 lines
class AIPlayer {
    public function performTurn($gameState) { ... }
    private function scoreCard($card, $gameState) { ... }
}
```

## Next Steps

1. **Complete JavaScript extraction**: Extract remaining modules from app.js
2. **Update HTML files**: Add proper script imports for all modules
3. **Test JavaScript modules**: Ensure all functionality works
4. **Begin PHP refactoring**: Start with game.php extraction
5. **Incremental testing**: Test after each major extraction
6. **Documentation**: Update inline documentation
7. **Performance testing**: Ensure no regressions

## Notes

- All existing functionality must continue to work
- No breaking changes to API contracts
- Maintain backward compatibility during transition
- Keep old files until new structure is fully validated
- Update this document as implementation progresses

## Completed Artifacts

### Created Files
- `/src/backend/core/Database.php` - Database connection management
- `/src/backend/core/Response.php` - Standardized API responses
- `/src/backend/models/User.php` - User data model
- `/src/backend/models/Deck.php` - Deck data model
- `/src/backend/game/GameState.php` - Game state management
- `/autoload.php` - PHP class autoloader
- `/src/frontend/js/core/app.js` - Minimal entry point
- `/public/js/modules/auth.js` - Authentication module
- `/public/js/modules/user-profile.js` - User profile module
- `/public/js/modules/card-collection.js` - Card collection module

### Copied Files
- Utility classes moved to `/src/backend/utils/`
  - CardFactory.php
  - CardEffectRegistry.php
  - GameEventSystem.php
  - PluginSystem.php
