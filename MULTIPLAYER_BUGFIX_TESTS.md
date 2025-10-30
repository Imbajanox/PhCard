# Multiplayer Bug Fixes - Manual Test Guide

This document outlines manual testing procedures to verify the multiplayer bug fixes.

## Prerequisites

1. Two different browser sessions (or incognito/private windows) with two different user accounts
2. Database updated with the migration: `mysql -u root -p < sql/add_multiplayer_rewards.sql`
3. Both users should have at least some cards (or the system will provide default starter cards)

## Test 1: Mana Increment Fix

**Issue**: Player 1 has 1 mana → player 2 has 1 mana → player 1 still has 1 mana in round 2

**Test Steps**:
1. User A creates a multiplayer game
2. User B joins the game
3. User A (Player 1) plays their turn:
   - Note: Should have 1 mana at start
   - Play a card if possible or just end turn
   - Click "End Turn"
4. User B (Player 2) checks their mana:
   - Should have 1 mana on their first turn
   - Play a card or end turn
   - Click "End Turn"
5. User A (Player 1) checks their mana:
   - **EXPECTED**: Should now have 2 max mana (was 1 before)
   - **VERIFY**: The mana display shows "2 / 2" or similar
6. Continue for one more round:
   - User B should have 2 max mana on their second turn
   - User A should have 3 max mana on their third turn

**Success Criteria**: 
- ✅ Both players' max mana increases by 1 each turn (starting from turn 2)
- ✅ Mana increments correctly: 1 → 2 → 3 → 4... up to max of 10

## Test 2: No Cards at Start Fix

**Issue**: Sometimes a player has no cards at start

**Test Steps**:
1. (If possible) Create a user with no deck and no cards in collection
   - Or use an existing user
2. User A creates a multiplayer game
3. User B joins the game
4. Both users check their hands:
   - **EXPECTED**: Each player should have 5 cards in hand
   - **VERIFY**: The hand section displays 5 cards for each player

**Success Criteria**:
- ✅ Both players start with 5 cards even if they have no custom deck
- ✅ Cards are playable and functional
- ✅ If a player has no deck/cards, they receive default starter cards

## Test 3: Old Battle Log Persistence Fix

**Issue**: Sometimes at start there is still the old battle log

**Test Steps**:
1. Play a complete multiplayer game to the end
2. After the game ends, return to lobby
3. Start a new multiplayer game (create and join)
4. Check the Battle Log section:
   - **EXPECTED**: Battle log should be empty/clear
   - **VERIFY**: No entries from the previous game appear

**Success Criteria**:
- ✅ Battle log is cleared when starting a new game
- ✅ Only new game actions appear in the log
- ✅ Previous game logs don't persist

## Test 4: Losing Screen and Rewards Fix

**Issue**: Sometimes the losing screen needs too long to show. Also there should be a losing reward

**Test Steps**:
1. Start a multiplayer game with User A and User B
2. Play the game to completion (one player wins)
3. On the WINNING player's screen:
   - **EXPECTED**: Game end modal appears immediately or within 3 seconds
   - **VERIFY**: Modal shows "🎉 You won! 🎉"
   - **VERIFY**: Rewards section shows:
     - XP gained: +75
     - Coins earned: +100 💰
     - Possibly gems (30% chance)
     - Possibly level up notification
4. On the LOSING player's screen:
   - **EXPECTED**: Game end modal appears within 3 seconds (at next poll)
   - **VERIFY**: Modal shows "💔 You lost! 💔"
   - **VERIFY**: Rewards section shows:
     - XP gained: +15
     - Coins earned: +20 💰
     - **IMPORTANT**: Losing player DOES get rewards!
5. Click "Return to Lobby" on both screens
6. Check both users' profiles to verify rewards were applied

**Success Criteria**:
- ✅ Game end screen appears within 3 seconds for both players
- ✅ Winning player gets rewards: 75 XP, 100 coins, possible gems
- ✅ Losing player gets rewards: 15 XP, 20 coins
- ✅ Rewards are correctly displayed in the modal
- ✅ Rewards are correctly applied to user accounts
- ✅ If either player levels up, new cards are unlocked

## Test 5: Draw Game Rewards

**Test Steps**:
1. (This is a rare case, might need to engineer it)
2. Play a game where both players' HP reaches 0 simultaneously
3. Both players should see:
   - **EXPECTED**: Modal shows "🤝 It's a draw! 🤝"
   - **VERIFY**: Each player gets draw rewards:
     - XP gained: +25
     - Coins earned: +30 💰

**Success Criteria**:
- ✅ Draw is correctly detected
- ✅ Both players get draw rewards
- ✅ Win streak resets for both players

## Test 6: Surrender Rewards

**Test Steps**:
1. Start a multiplayer game
2. User A clicks "Surrender" button
3. User A should see:
   - **EXPECTED**: Game end modal immediately
   - **VERIFY**: Shows "💔 You lost! 💔"
   - **VERIFY**: Losing rewards displayed (15 XP, 20 coins)
4. User B should see:
   - **EXPECTED**: Game end modal within 3 seconds
   - **VERIFY**: Shows "🎉 You won! 🎉"  
   - **VERIFY**: Winning rewards displayed (75 XP, 100 coins)

**Success Criteria**:
- ✅ Surrender immediately ends the game
- ✅ Both players get appropriate rewards
- ✅ Stats are correctly updated (surrendering player gets a loss, opponent gets a win)

## Integration Test: Complete Game Flow

**Test Steps**:
1. User A creates a game
2. User B joins
3. Play several turns:
   - Verify mana increases each turn
   - Verify battle log updates correctly
   - Verify cards can be played
   - Verify turn switches correctly
4. Complete the game (win/loss/surrender)
5. Verify game end modal shows correctly for both players
6. Verify both players get rewards
7. Return to lobby and start a new game
8. Verify battle log is cleared
9. Verify new game starts fresh with correct mana and cards

**Success Criteria**:
- ✅ All game mechanics work correctly
- ✅ All four bug fixes work together without conflicts
- ✅ Game can be played multiple times in sequence
- ✅ No errors in browser console

## Regression Testing

Test that existing functionality still works:

1. **Single Player (vs AI)**: Verify single player mode still works correctly
2. **Card Effects**: Verify various card effects still work
3. **Database**: Check that stats are correctly recorded
4. **UI Updates**: Ensure all UI elements update correctly

## Notes for Testers

- The polling interval is 3 seconds, so there may be up to 3 seconds delay for the losing player to see the game end screen
- If you see console errors, note them down with reproduction steps
- Test with different card types and effects
- Test with different player levels

## Expected Database Changes

After running the migration, the `multiplayer_games` table should have two new columns:
- `player1_rewards` (TEXT)
- `player2_rewards` (TEXT)

Verify with: `DESCRIBE multiplayer_games;`
