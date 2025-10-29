# Quest Reset and Card Shop System

## Overview
This update adds two major features to PhCard:
1. **Quest Reset System** - Reset daily and weekly quests
2. **Multiple Card Acquisition Methods** - Shop, card packs, daily rewards, and earning currency

## New Features

### 1. Quest Reset System

#### Manual Reset
- **Daily Quest Reset**: Reset all daily quests once per day
- **Weekly Quest Reset**: Reset all weekly quests once per week
- Access via buttons on the Quests page
- Only resets unclaimed quests (claimed rewards are preserved)

#### Automatic Reset
- Daily quests automatically reset at midnight
- Weekly quests automatically reset at the start of each week
- Old uncompleted quests are cleared automatically

### 2. Currency System

Two types of currency have been added:

#### Coins (🪙)
- Primary currency earned from playing games
- Used to purchase cards and basic card packs
- Earnings:
  - **Win**: 50 + (AI Level × 10) coins
  - **Loss**: 10 + (AI Level × 2) coins
  - **Draw**: 25 + (AI Level × 5) coins
  - **Daily Login**: 100-300 coins depending on streak day

#### Gems (💎)
- Premium currency earned occasionally
- Used for premium card packs and rare items
- Earnings:
  - **Win Chance**: 10% + (AI Level × 5%) chance per win
  - **Win Amount**: 1 + floor(AI Level / 2) gems
  - **Daily Login**: 5-10 gems on specific streak days

### 3. Card Shop

Browse and purchase individual cards:
- Cards organized by rarity (Common, Rare, Epic, Legendary)
- Prices scale with rarity:
  - Common: 200 coins
  - Rare: 500 coins
  - Epic: 1500 coins or 5 gems
  - Legendary: 5000 coins or 15 gems
- Only cards at or below your level are shown

### 4. Card Packs

Open randomized card packs:

#### Pack Types
1. **Starter Pack** (100 coins)
   - 5 random cards
   - All rarities possible

2. **Standard Pack** (500 coins)
   - 5 cards with guaranteed Rare or better
   - Better odds for higher rarities

3. **Premium Pack** (10 gems)
   - 7 cards with guaranteed Epic or better
   - High chance of multiple rare+ cards

4. **Legendary Pack** (25 gems)
   - 10 cards with guaranteed Legendary
   - Best odds for premium cards

### 5. Daily Login Rewards

Consecutive login streak system (7-day cycle):
- **Day 1**: 100 coins
- **Day 2**: 150 coins
- **Day 3**: 5 gems
- **Day 4**: 200 coins
- **Day 5**: 300 coins
- **Day 6**: 10 gems
- **Day 7**: Standard Pack (500 coin value)

Streak tracking:
- Login consecutively to maintain streak
- Missing a day resets the streak to 1
- The longest streak is tracked for achievements

## Database Changes

New SQL schema file: `sql/database_quest_reset_and_shop.sql`

New tables:
- `shop_items` - Card shop inventory
- `card_packs` - Pack definitions
- `card_pack_contents` - Pack rarity configurations
- `purchase_history` - Transaction log
- `daily_login_rewards` - Daily reward configuration
- `user_login_streaks` - Streak tracking

Modified tables:
- `users` - Added `coins`, `gems`, `last_daily_reset`, `last_weekly_reset`, `last_daily_login`

## API Endpoints

### Shop API (`api/shop.php`)
- `GET ?action=get_shop_items` - Get available cards for purchase
- `GET ?action=get_card_packs` - Get available card packs
- `POST ?action=purchase_card` - Buy a card (params: `card_id`)
- `POST ?action=purchase_pack` - Buy a pack (params: `pack_id`)
- `GET ?action=get_user_currency` - Get user's coins and gems
- `POST ?action=claim_daily_login` - Claim daily login reward

### Quest API Updates (`api/quests.php`)
- `POST ?action=reset_daily_quests` - Manual daily quest reset
- `POST ?action=reset_weekly_quests` - Manual weekly quest reset
- `POST ?action=check_auto_reset` - Check/trigger automatic resets

### Game API Updates (`api/game.php`)
- `endGame()` now returns `coins_earned` and `gems_earned`
- Currency is automatically awarded on game completion

## Installation

1. **Run the database migration:**
   ```bash
   mysql -u your_user -p phcard < sql/database_quest_reset_and_shop.sql
   ```

2. **The system is ready to use!**
   - Existing users will get 1000 coins and 50 gems as starting currency
   - Shop items are auto-populated from existing cards
   - Default card packs are created

3. **Access the shop:**
   - Navigate to the Shop page from the main menu
   - Or visit directly: `web/features/shop.html`

## Frontend Files

New files:
- `web/features/shop.html` - Shop page
- `web/js/features/shop.js` - Shop functionality

Modified files:
- `web/features/quests.html` - Added reset buttons
- `web/js/features/quests.js` - Added reset functionality
- `web/css/features.css` - Added reset button styles
- `components/header.html` - Added Shop navigation link

## Usage Examples

### Earning Currency
Play games to earn coins and gems:
```
Win vs AI Level 3:
- Coins: 50 + (3 × 10) = 80 coins
- Gems: 25% chance for 2 gems
```

### Buying Cards
1. Go to Shop → Cards tab
2. Browse available cards
3. Click "Buy Card" on desired card
4. Card is added to your collection

### Opening Packs
1. Go to Shop → Card Packs tab
2. Choose a pack type
3. Click "Buy Pack"
4. View cards received in modal

### Daily Rewards
1. Visit Shop page daily
2. Click "Claim Daily Reward"
3. Receive reward and maintain streak

### Resetting Quests
1. Go to Quests page
2. Click "Reset Daily Quests" or "Reset Weekly Quests"
3. Confirm the reset
4. Fresh quests are loaded

## Configuration

### Adjusting Prices
Edit `sql/database_quest_reset_and_shop.sql` before running:
- Modify `shop_items` INSERT prices
- Adjust `card_packs` prices
- Change `daily_login_rewards` amounts

### Customizing Rewards
Edit `api/game.php` `endGame()` function:
- Coin formula: `$coinsEarned = 50 + ($gameState['ai_level'] * 10);`
- Gem chance: `$gemChance = 10 + ($gameState['ai_level'] * 5);`
- Gem amount: `$gemsEarned = 1 + floor($gameState['ai_level'] / 2);`

### Pack Drop Rates
Edit `card_pack_contents` table:
- Higher `drop_weight` = more common
- Default weights: Common=100, Rare=25, Epic=10, Legendary=5

## Testing

Basic tests to verify functionality:

1. **Quest Reset**
   ```bash
   # Visit quests page
   # Click "Reset Daily Quests"
   # Verify unclaimed quests are cleared
   ```

2. **Shop Purchase**
   ```bash
   # Go to Shop
   # Check currency display
   # Buy a card
   # Verify currency deducted
   ```

3. **Pack Opening**
   ```bash
   # Buy a card pack
   # Verify modal shows received cards
   # Check collection for new cards
   ```

4. **Daily Login**
   ```bash
   # Claim daily reward
   # Verify currency added
   # Try claiming again (should fail)
   ```

5. **Currency from Games**
   ```bash
   # Play a game
   # Win/lose/draw
   # Check coins/gems awarded
   ```

## Future Enhancements

Possible additions:
- Trading system between players
- Special event packs
- Bundle deals (multiple packs at discount)
- Quest completion card rewards
- Achievement-based currency bonuses
- Daily/weekly shop rotations
- Limited-time exclusive cards
- VIP/subscription system for bonus currency

## Troubleshooting

### Shop not loading
- Verify `database_quest_reset_and_shop.sql` was run
- Check browser console for API errors
- Verify session is authenticated

### Currency not updating
- Check game.php was updated with currency code
- Verify users table has coins/gems columns
- Check browser network tab for API responses

### Reset not working
- Verify last_daily_reset/last_weekly_reset columns exist
- Check that user is authenticated
- Look for error messages in response

## Support

For issues or questions:
1. Check browser console for errors
2. Review PHP error logs
3. Verify database schema is up to date
4. Ensure all new files are deployed
