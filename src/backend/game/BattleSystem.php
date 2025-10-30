<?php

namespace Game;

/**
 * BattleSystem handles combat mechanics and spell effects
 */
class BattleSystem {
    
    /**
     * Apply spell effect to game state
     */
    public function applySpellEffect(&$gameState, $card, $caster, $target) {
        $effect = $card['effect'];
        $message = "Cast {$card['name']}: ";
        
        if (!$effect) {
            return ['message' => $message . "No effect", 'gameState' => $gameState];
        }
        
        list($type, $value) = explode(':', $effect);
        $value = intval($value);
        
        switch ($type) {
            case 'damage':
                if ($target === 'opponent') {
                    if ($caster === 'player') {
                        $gameState['ai_hp'] -= $value;
                        $message .= "Dealt {$value} damage to AI";
                    } else {
                        $gameState['player_hp'] -= $value;
                        $message .= "AI dealt {$value} damage to you";
                    }
                }
                break;
            case 'heal':
                if ($target === 'self' || $caster === $target) {
                    if ($caster === 'player') {
                        $gameState['player_hp'] = min($gameState['player_hp'] + $value, STARTING_HP);
                        $message .= "Healed {$value} HP";
                    } else {
                        $gameState['ai_hp'] = min($gameState['ai_hp'] + $value, STARTING_HP);
                        $message .= "AI healed {$value} HP";
                    }
                }
                break;
            case 'boost':
                // Boost all monsters on caster's field
                $fieldKey = $caster . '_field';
                foreach ($gameState[$fieldKey] as &$monster) {
                    $monster['attack'] = ($monster['attack'] ?? 0) + $value;
                }
                $message .= "Boosted attack by {$value}";
                break;
            case 'shield':
                $message .= "Gained {$value} shield";
                break;
            case 'stun':
                // Stun all opponent monsters
                $opponentField = ($caster === 'player') ? 'ai_field' : 'player_field';
                foreach ($gameState[$opponentField] as &$monster) {
                    if (!isset($monster['status_effects'])) {
                        $monster['status_effects'] = [];
                    }
                    $monster['status_effects'][] = 'stunned';
                    $monster['stun_duration'] = $value;
                }
                $message .= "Stunned all enemy monsters for {$value} turns";
                break;
            case 'poison':
                // Poison opponent
                $opponentKey = ($caster === 'player') ? 'ai' : 'player';
                if (!isset($gameState[$opponentKey . '_status_effects'])) {
                    $gameState[$opponentKey . '_status_effects'] = [];
                }
                $gameState[$opponentKey . '_status_effects']['poison'] = $value;
                $message .= "Poisoned opponent for {$value} turns";
                break;
            case 'combo_boost':
                // Combo effect based on cards played this turn
                $cardsPlayed = $gameState['cards_played_this_turn'] ?? 0;
                $boostAmount = $value * $cardsPlayed;
                $fieldKey = $caster . '_field';
                if (count($gameState[$fieldKey]) > 0) {
                    $gameState[$fieldKey][count($gameState[$fieldKey]) - 1]['attack'] += $boostAmount;
                    $message .= "Combo! Boosted by {$boostAmount} (cards played: {$cardsPlayed})";
                }
                break;
        }
        
        return ['message' => $message, 'gameState' => $gameState];
    }
    
    /**
     * Play a card from player's hand (supports both single-player and multiplayer)
     */
    public function playCard(&$gameState, $cardIndex, $target = 'opponent', $choice = 0, $playerKey = 'player') {
        // For backward compatibility, default to single player if playerKey is 'player'
        if ($playerKey === 'player' && isset($gameState['ai_hp'])) {
            // Single player mode - use original logic
            if ($gameState['turn'] !== 'player') {
                return ['success' => false, 'error' => 'Not your turn'];
            }
            
            if ($cardIndex < 0 || $cardIndex >= count($gameState['player_hand'])) {
                return ['success' => false, 'error' => 'Invalid card'];
            }
            
            $card = $gameState['player_hand'][$cardIndex];
            $manaCost = intval($card['mana_cost'] ?? 1);
            
            if ($gameState['player_mana'] < $manaCost) {
                return ['success' => false, 'error' => 'Not enough mana'];
            }
            
            $gameState['player_mana'] -= $manaCost;
            
            if (isset($card['overload']) && $card['overload'] > 0) {
                $gameState['player_overload'] += intval($card['overload']);
            }
            
            array_splice($gameState['player_hand'], $cardIndex, 1);
            
            $message = '';
            
            if ($card['type'] === 'monster') {
                if (!empty($card['choice_effects'])) {
                    $choices = json_decode($card['choice_effects'], true);
                    if (isset($choices['choices'][$choice])) {
                        $selectedChoice = $choices['choices'][$choice];
                        $card['attack'] = $selectedChoice['attack'] ?? $card['attack'];
                        $card['defense'] = $selectedChoice['defense'] ?? $card['defense'];
                        $message = "Played {$card['name']} ({$selectedChoice['name']}) - ";
                    }
                }
                
                $card['status_effects'] = [];
                if (!empty($card['keywords'])) {
                    $keywords = explode(',', $card['keywords']);
                    foreach ($keywords as $keyword) {
                        $keyword = trim($keyword);
                        if (in_array($keyword, ['taunt', 'divine_shield', 'stealth', 'windfury', 'lifesteal', 'poison', 'charge', 'rush'])) {
                            $card['status_effects'][] = $keyword;
                        }
                    }
                }
                
                $card['current_health'] = $card['health'] ?? $card['defense'];
                $card['max_health'] = $card['health'] ?? $card['defense'];
                
                $gameState['player_field'][] = $card;
                if (empty($message)) {
                    $message = "Played {$card['name']} (ATK: {$card['attack']}, HP: {$card['current_health']})";
                } else {
                    $message .= "(ATK: {$card['attack']}, HP: {$card['current_health']})";
                }
            } else if ($card['type'] === 'spell') {
                $result = $this->applySpellEffect($gameState, $card, 'player', $target);
                $gameState = $result['gameState'];
                $message = $result['message'];
            }
            
            $gameState['cards_played_this_turn']++;
            
            return [
                'success' => true,
                'message' => $message,
                'game_state' => [
                    'player_hp' => $gameState['player_hp'],
                    'ai_hp' => $gameState['ai_hp'],
                    'player_mana' => $gameState['player_mana'],
                    'player_hand' => $gameState['player_hand'],
                    'player_field' => $gameState['player_field'],
                    'ai_field' => $gameState['ai_field']
                ]
            ];
        }
        
        // Multiplayer mode
        $opponentKey = ($playerKey === 'player1') ? 'player2' : 'player1';
        $handKey = $playerKey . '_hand';
        $fieldKey = $playerKey . '_field';
        $manaKey = $playerKey . '_mana';
        $overloadKey = $playerKey . '_overload';
        
        if ($cardIndex < 0 || $cardIndex >= count($gameState[$handKey])) {
            return ['success' => false, 'error' => 'Invalid card'];
        }
        
        $card = $gameState[$handKey][$cardIndex];
        $manaCost = intval($card['mana_cost'] ?? 1);
        
        if ($gameState[$manaKey] < $manaCost) {
            return ['success' => false, 'error' => 'Not enough mana'];
        }
        
        $gameState[$manaKey] -= $manaCost;
        
        if (isset($card['overload']) && $card['overload'] > 0) {
            $gameState[$overloadKey] += intval($card['overload']);
        }
        
        array_splice($gameState[$handKey], $cardIndex, 1);
        
        $message = '';
        
        if ($card['type'] === 'monster') {
            $card['status_effects'] = [];
            if (!empty($card['keywords'])) {
                $keywords = explode(',', $card['keywords']);
                foreach ($keywords as $keyword) {
                    $keyword = trim($keyword);
                    if (in_array($keyword, ['taunt', 'divine_shield', 'stealth', 'windfury', 'lifesteal', 'poison', 'charge', 'rush'])) {
                        $card['status_effects'][] = $keyword;
                    }
                }
            }
            
            $card['current_health'] = $card['health'] ?? $card['defense'];
            $card['max_health'] = $card['health'] ?? $card['defense'];
            
            $gameState[$fieldKey][] = $card;
            $message = "Played {$card['name']} (ATK: {$card['attack']}, HP: {$card['current_health']})";
        } else if ($card['type'] === 'spell') {
            $result = $this->applyMultiplayerSpellEffect($gameState, $card, $playerKey, $target);
            $gameState = $result['gameState'];
            $message = $result['message'];
        }
        
        return [
            'success' => true,
            'message' => $message
        ];
    }
    
    /**
     * Process status effects for a player
     */
    public function processStatusEffects(&$gameState, $player) {
        $battleLog = [];
        
        // Process poison damage
        if (isset($gameState[$player . '_status_effects']['poison'])) {
            $poisonDuration = $gameState[$player . '_status_effects']['poison'];
            $poisonDamage = 2;
            $gameState[$player . '_hp'] -= $poisonDamage;
            $battleLog[] = ucfirst($player) . " takes {$poisonDamage} poison damage";
            
            $poisonDuration--;
            if ($poisonDuration <= 0) {
                unset($gameState[$player . '_status_effects']['poison']);
                $battleLog[] = ucfirst($player) . "'s poison wears off";
            } else {
                $gameState[$player . '_status_effects']['poison'] = $poisonDuration;
            }
        }
        
        // Process monster poison effects
        $fieldKey = $player . '_field';
        foreach ($gameState[$fieldKey] as &$monster) {
            if (isset($monster['status_effects']) && in_array('poisoned', $monster['status_effects'])) {
                $poisonDamage = $monster['poison_damage'] ?? 1;
                $monster['current_health'] -= $poisonDamage;
                $battleLog[] = "{$monster['name']} takes {$poisonDamage} poison damage";
                
                if (!isset($monster['poison_duration'])) {
                    $monster['poison_duration'] = 3;
                }
                $monster['poison_duration']--;
                
                if ($monster['poison_duration'] <= 0) {
                    $monster['status_effects'] = array_diff($monster['status_effects'], ['poisoned']);
                    unset($monster['poison_damage']);
                    unset($monster['poison_duration']);
                }
            }
        }
        
        return $battleLog;
    }
    
    /**
     * Execute end turn battle phase
     */
    public function executeTurnBattle(&$gameState) {
        $battleLog = [];
        $battleEvents = [];
        $gameActions = new GameActions();
        
        // Process status effects at start of battle
        $battleLog = array_merge($battleLog, $this->processStatusEffects($gameState, 'player'));
        
        // Player monsters attack
        foreach ($gameState['player_field'] as $i => $playerMonster) {
            // Check if monster can attack (not stunned/frozen)
            $canAttack = true;
            if (isset($playerMonster['status_effects']) && in_array('stunned', $playerMonster['status_effects'])) {
                $canAttack = false;
                $battleLog[] = "{$playerMonster['name']} is stunned and cannot attack";
            }
            
            if (!$canAttack) continue;
            
            // Check for Taunt monsters on opponent's field
            $tauntMonsterIndex = $this->findTauntMonster($gameState['ai_field']);
            
            if ($tauntMonsterIndex !== null) {
                $result = $this->executeMonsterAttack($gameState, $i, $tauntMonsterIndex, 'player', 'ai');
                $battleLog = array_merge($battleLog, $result['log']);
                $battleEvents = array_merge($battleEvents, $result['events']);
            } else if (count($gameState['ai_field']) > 0) {
                $result = $this->executeMonsterAttack($gameState, $i, 0, 'player', 'ai');
                $battleLog = array_merge($battleLog, $result['log']);
                $battleEvents = array_merge($battleEvents, $result['events']);
            } else {
                // Direct attack
                $gameState['ai_hp'] -= $playerMonster['attack'];
                $battleLog[] = "{$playerMonster['name']} attacks directly for {$playerMonster['attack']} damage";
                
                // Apply Lifesteal
                if (isset($playerMonster['status_effects']) && in_array('lifesteal', $playerMonster['status_effects'])) {
                    $gameState['player_hp'] = min($gameState['player_hp'] + $playerMonster['attack'], STARTING_HP);
                    $battleLog[] = "{$playerMonster['name']} heals for {$playerMonster['attack']} HP (Lifesteal)";
                }
            }
            
            // Windfury - attack twice
            if (isset($playerMonster['status_effects']) && in_array('windfury', $playerMonster['status_effects'])) {
                if (count($gameState['ai_field']) > 0) {
                    $aiMonster = &$gameState['ai_field'][0];
                    $damage = $playerMonster['attack'];
                    $aiMonster['current_health'] -= $damage;
                    $battleLog[] = "{$playerMonster['name']} attacks again (Windfury) for {$damage} damage!";
                    
                    $counterDamage = $aiMonster['attack'];
                    $playerMonster['current_health'] -= $counterDamage;
                    $gameState['player_field'][$i]['current_health'] = $playerMonster['current_health'];
                    $battleLog[] = "{$aiMonster['name']} deals {$counterDamage} counter-damage";
                    
                    if ($aiMonster['current_health'] <= 0) {
                        array_shift($gameState['ai_field']);
                        $battleLog[] = "{$aiMonster['name']} was destroyed!";
                    }
                } else {
                    $gameState['ai_hp'] -= $playerMonster['attack'];
                    $battleLog[] = "{$playerMonster['name']} attacks again (Windfury) for {$playerMonster['attack']} damage!";
                }
            }
        }
        
        // Clean up destroyed player monsters
        for ($i = count($gameState['player_field']) - 1; $i >= 0; $i--) {
            if ($gameState['player_field'][$i]['current_health'] <= 0) {
                $destroyedMonster = $gameState['player_field'][$i];
                $battleEvents[] = [
                    'type' => 'destroyed',
                    'target' => $destroyedMonster['name'],
                    'targetPlayer' => 'player',
                    'targetIndex' => $i
                ];
                array_splice($gameState['player_field'], $i, 1);
                $battleLog[] = "{$destroyedMonster['name']} was destroyed in combat!";
            }
        }
        
        // Check if AI is defeated
        $aiActions = [];
        if ($gameState['ai_hp'] > 0) {
            // Switch to AI turn
            $gameState['turn'] = 'ai';
            
            // AI plays
            $aiPlayer = new AIPlayer();
            $aiResult = $aiPlayer->performTurn($gameState);
            $aiActions = $aiResult['actions'];
            $gameState = $aiResult['gameState'];
            
            // Process AI status effects
            $battleLog = array_merge($battleLog, $this->processStatusEffects($gameState, 'ai'));
        }
        
        // AI monsters attack (only if AI is still alive)
        if ($gameState['ai_hp'] > 0) {
            for ($ai_i = 0; $ai_i < count($gameState['ai_field']); $ai_i++) {
                $aiMonster = $gameState['ai_field'][$ai_i];
                
                // Check if monster can attack
                $canAttack = true;
                if (isset($aiMonster['status_effects']) && in_array('stunned', $aiMonster['status_effects'])) {
                    $canAttack = false;
                    $battleLog[] = "AI {$aiMonster['name']} is stunned and cannot attack";
                }
                
                if (!$canAttack) continue;
                
                // Check for Taunt
                $tauntMonsterIndex = $this->findTauntMonster($gameState['player_field']);
                
                if ($tauntMonsterIndex !== null) {
                    $result = $this->executeMonsterAttack($gameState, $ai_i, $tauntMonsterIndex, 'ai', 'player');
                    $battleLog = array_merge($battleLog, $result['log']);
                    $battleEvents = array_merge($battleEvents, $result['events']);
                } else if (count($gameState['player_field']) > 0) {
                    $result = $this->executeMonsterAttack($gameState, $ai_i, 0, 'ai', 'player');
                    $battleLog = array_merge($battleLog, $result['log']);
                    $battleEvents = array_merge($battleEvents, $result['events']);
                } else {
                    // Direct attack
                    $gameState['player_hp'] -= $aiMonster['attack'];
                    $battleLog[] = "AI {$aiMonster['name']} attacks directly for {$aiMonster['attack']} damage";
                }
            }
        }
        
        // Clean up destroyed AI monsters
        for ($i = count($gameState['ai_field']) - 1; $i >= 0; $i--) {
            if ($gameState['ai_field'][$i]['current_health'] <= 0) {
                $destroyedMonster = $gameState['ai_field'][$i];
                $battleEvents[] = [
                    'type' => 'destroyed',
                    'target' => $destroyedMonster['name'],
                    'targetPlayer' => 'ai',
                    'targetIndex' => $i
                ];
                array_splice($gameState['ai_field'], $i, 1);
                $battleLog[] = "AI {$destroyedMonster['name']} was destroyed in combat!";
            }
        }
        
        // Switch back to player
        $gameState['turn'] = 'player';
        $gameState['turn_count']++;
        $gameState['cards_played_this_turn'] = 0;
        
        // Increase max mana
        $gameState['player_max_mana'] = min(MAX_MANA, $gameState['player_max_mana'] + MANA_PER_TURN);
        $gameState['ai_max_mana'] = min(MAX_MANA, $gameState['ai_max_mana'] + MANA_PER_TURN);
        
        // Restore mana minus overload
        $gameState['player_mana'] = max(0, $gameState['player_max_mana'] - $gameState['player_overload']);
        $gameState['ai_mana'] = max(0, $gameState['ai_max_mana'] - $gameState['ai_overload']);
        
        // Clear overload
        $gameState['player_overload'] = 0;
        $gameState['ai_overload'] = 0;
        
        // Draw a card
        if (count($gameState['player_hand']) < 10) {
            $newCards = $gameActions->drawCards($gameState['available_cards'], 1);
            if (count($newCards) > 0) {
                $gameState['player_hand'][] = $newCards[0];
            }
        }
        
        // Decrease stun durations
        $this->decreaseStunDurations($gameState);
        
        // Check for game over
        $winner = null;
        if ($gameState['player_hp'] <= 0 && $gameState['ai_hp'] <= 0) {
            $winner = 'draw';
        } else if ($gameState['player_hp'] <= 0) {
            $winner = 'ai';
        } else if ($gameState['ai_hp'] <= 0) {
            $winner = 'player';
        }
        
        return [
            'battle_log' => $battleLog,
            'battle_events' => $battleEvents,
            'ai_actions' => $aiActions,
            'winner' => $winner
        ];
    }
    
    /**
     * Find taunt monster in field
     */
    private function findTauntMonster($field) {
        foreach ($field as $idx => $monster) {
            if (isset($monster['status_effects']) && in_array('taunt', $monster['status_effects'])) {
                return $idx;
            }
        }
        return null;
    }
    
    /**
     * Execute monster attack
     */
    private function executeMonsterAttack(&$gameState, $attackerIdx, $defenderIdx, $attackerSide, $defenderSide) {
        $log = [];
        $events = [];
        
        $attackerField = $attackerSide . '_field';
        $defenderField = $defenderSide . '_field';
        
        $attacker = $gameState[$attackerField][$attackerIdx];
        $defender = &$gameState[$defenderField][$defenderIdx];
        
        $prefix = ($attackerSide === 'ai') ? 'AI ' : '';
        
        // Check for Divine Shield
        if (isset($defender['status_effects']) && in_array('divine_shield', $defender['status_effects'])) {
            $log[] = "{$prefix}{$attacker['name']} attacks {$defender['name']} but Divine Shield absorbs the damage!";
            $defender['status_effects'] = array_diff($defender['status_effects'], ['divine_shield']);
            
            // Counter-attack damage
            $counterDamage = $defender['attack'];
            $attacker['current_health'] -= $counterDamage;
            $gameState[$attackerField][$attackerIdx]['current_health'] = $attacker['current_health'];
            $log[] = "{$defender['name']} deals {$counterDamage} counter-damage to {$attacker['name']}";
        } else {
            // Deal damage to the monster's HP
            $damage = $attacker['attack'];
            $defender['current_health'] -= $damage;
            $log[] = "{$prefix}{$attacker['name']} attacks {$defender['name']} for {$damage} damage (HP: {$defender['current_health']}/{$defender['max_health']})";
            $events[] = [
                'type' => 'damage',
                'source' => $attacker['name'],
                'target' => $defender['name'],
                'targetPlayer' => $defenderSide,
                'targetIndex' => $defenderIdx,
                'amount' => $damage
            ];
            
            // Counter-attack damage
            $counterDamage = $defender['attack'];
            $attacker['current_health'] -= $counterDamage;
            $gameState[$attackerField][$attackerIdx]['current_health'] = $attacker['current_health'];
            $log[] = "{$defender['name']} deals {$counterDamage} counter-damage to {$attacker['name']} (HP: {$attacker['current_health']}/{$attacker['max_health']})";
            $events[] = [
                'type' => 'damage',
                'source' => $defender['name'],
                'target' => $attacker['name'],
                'targetPlayer' => $attackerSide,
                'targetIndex' => $attackerIdx,
                'amount' => $counterDamage
            ];
            
            // Apply Lifesteal
            if (isset($attacker['status_effects']) && in_array('lifesteal', $attacker['status_effects'])) {
                $hpKey = $attackerSide . '_hp';
                $gameState[$hpKey] = min($gameState[$hpKey] + $damage, STARTING_HP);
                $log[] = "{$prefix}{$attacker['name']} heals for {$damage} HP (Lifesteal)";
            }
            
            // Apply Poison
            if (isset($attacker['status_effects']) && in_array('poison', $attacker['status_effects'])) {
                if (!isset($defender['status_effects'])) $defender['status_effects'] = [];
                $defender['status_effects'][] = 'poisoned';
                $defender['poison_damage'] = 50;
            }
            
            // Check if defender is destroyed
            if ($defender['current_health'] <= 0) {
                $events[] = [
                    'type' => 'destroyed',
                    'target' => $defender['name'],
                    'targetPlayer' => $defenderSide,
                    'targetIndex' => $defenderIdx
                ];
                array_splice($gameState[$defenderField], $defenderIdx, 1);
                $log[] = "{$defender['name']} was destroyed!";
            }
        }
        
        return ['log' => $log, 'events' => $events];
    }
    
    /**
     * Decrease stun durations
     */
    private function decreaseStunDurations(&$gameState) {
        foreach ($gameState['player_field'] as &$monster) {
            if (isset($monster['stun_duration'])) {
                $monster['stun_duration']--;
                if ($monster['stun_duration'] <= 0) {
                    $monster['status_effects'] = array_diff($monster['status_effects'], ['stunned']);
                    unset($monster['stun_duration']);
                }
            }
        }
        foreach ($gameState['ai_field'] as &$monster) {
            if (isset($monster['stun_duration'])) {
                $monster['stun_duration']--;
                if ($monster['stun_duration'] <= 0) {
                    $monster['status_effects'] = array_diff($monster['status_effects'], ['stunned']);
                    unset($monster['stun_duration']);
                }
            }
        }
    }
    
    /**
     * Apply spell effect in multiplayer game
     */
    private function applyMultiplayerSpellEffect(&$gameState, $card, $caster, $target) {
        $effect = $card['effect'];
        $message = "Cast {$card['name']}: ";
        
        if (!$effect) {
            return ['message' => $message . "No effect", 'gameState' => $gameState];
        }
        
        list($type, $value) = explode(':', $effect);
        $value = intval($value);
        
        $opponent = ($caster === 'player1') ? 'player2' : 'player1';
        
        switch ($type) {
            case 'damage':
                if ($target === 'opponent') {
                    $gameState[$opponent . '_hp'] -= $value;
                    $message .= "Dealt {$value} damage to opponent";
                }
                break;
            case 'heal':
                $gameState[$caster . '_hp'] = min($gameState[$caster . '_hp'] + $value, STARTING_HP);
                $message .= "Healed {$value} HP";
                break;
            case 'boost':
                $fieldKey = $caster . '_field';
                foreach ($gameState[$fieldKey] as &$monster) {
                    $monster['attack'] = ($monster['attack'] ?? 0) + $value;
                }
                $message .= "Boosted attack by {$value}";
                break;
            case 'shield':
                $message .= "Gained {$value} shield";
                break;
        }
        
        return ['message' => $message, 'gameState' => $gameState];
    }
    
    /**
     * Execute turn battle for multiplayer game
     */
    public function executeMultiplayerTurnBattle(&$gameState, $playerKey) {
        $battleLog = [];
        $battleEvents = [];
        
        $opponentKey = ($playerKey === 'player1') ? 'player2' : 'player1';
        $playerFieldKey = $playerKey . '_field';
        $opponentFieldKey = $opponentKey . '_field';
        $playerHpKey = $playerKey . '_hp';
        $opponentHpKey = $opponentKey . '_hp';
        
        // Player monsters attack
        foreach ($gameState[$playerFieldKey] as $i => $playerMonster) {
            $canAttack = true;
            if (isset($playerMonster['status_effects']) && in_array('stunned', $playerMonster['status_effects'])) {
                $canAttack = false;
                $battleLog[] = "{$playerMonster['name']} is stunned and cannot attack";
            }
            
            if (!$canAttack) continue;
            
            if (count($gameState[$opponentFieldKey]) > 0) {
                $result = $this->executeMultiplayerMonsterAttack($gameState, $i, 0, $playerKey, $opponentKey);
                $battleLog = array_merge($battleLog, $result['log']);
                $battleEvents = array_merge($battleEvents, $result['events']);
            } else {
                $gameState[$opponentHpKey] -= $playerMonster['attack'];
                $battleLog[] = "{$playerMonster['name']} attacks directly for {$playerMonster['attack']} damage";
                
                if (isset($playerMonster['status_effects']) && in_array('lifesteal', $playerMonster['status_effects'])) {
                    $gameState[$playerHpKey] = min($gameState[$playerHpKey] + $playerMonster['attack'], STARTING_HP);
                    $battleLog[] = "{$playerMonster['name']} heals for {$playerMonster['attack']} HP (Lifesteal)";
                }
            }
        }
        
        // Clean up destroyed monsters
        for ($i = count($gameState[$playerFieldKey]) - 1; $i >= 0; $i--) {
            if ($gameState[$playerFieldKey][$i]['current_health'] <= 0) {
                $destroyedMonster = $gameState[$playerFieldKey][$i];
                array_splice($gameState[$playerFieldKey], $i, 1);
                $battleLog[] = "{$destroyedMonster['name']} was destroyed!";
            }
        }
        
        for ($i = count($gameState[$opponentFieldKey]) - 1; $i >= 0; $i--) {
            if ($gameState[$opponentFieldKey][$i]['current_health'] <= 0) {
                $destroyedMonster = $gameState[$opponentFieldKey][$i];
                array_splice($gameState[$opponentFieldKey], $i, 1);
                $battleLog[] = "{$destroyedMonster['name']} was destroyed!";
            }
        }
        
        return [
            'battle_log' => $battleLog,
            'battle_events' => $battleEvents
        ];
    }
    
    /**
     * Execute monster attack in multiplayer
     */
    private function executeMultiplayerMonsterAttack(&$gameState, $attackerIndex, $defenderIndex, $attackerKey, $defenderKey) {
        $attackerFieldKey = $attackerKey . '_field';
        $defenderFieldKey = $defenderKey . '_field';
        
        $log = [];
        $events = [];
        
        $attacker = &$gameState[$attackerFieldKey][$attackerIndex];
        $defender = &$gameState[$defenderFieldKey][$defenderIndex];
        
        $damage = $attacker['attack'];
        $counterDamage = $defender['attack'];
        
        // Divine Shield
        if (isset($defender['status_effects']) && in_array('divine_shield', $defender['status_effects'])) {
            $defender['status_effects'] = array_diff($defender['status_effects'], ['divine_shield']);
            $log[] = "{$defender['name']}'s Divine Shield absorbs the attack!";
            $damage = 0;
        }
        
        $defender['current_health'] -= $damage;
        $log[] = "{$attacker['name']} attacks {$defender['name']} for {$damage} damage";
        
        // Counter damage
        if ($damage > 0) {
            $attacker['current_health'] -= $counterDamage;
            $log[] = "{$defender['name']} deals {$counterDamage} counter-damage";
        }
        
        // Lifesteal
        if (isset($attacker['status_effects']) && in_array('lifesteal', $attacker['status_effects']) && $damage > 0) {
            $gameState[$attackerKey . '_hp'] = min($gameState[$attackerKey . '_hp'] + $damage, STARTING_HP);
            $log[] = "{$attacker['name']} heals for {$damage} HP (Lifesteal)";
        }
        
        return ['log' => $log, 'events' => $events];
    }
}
