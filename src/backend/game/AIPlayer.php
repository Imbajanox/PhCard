<?php

namespace Game;

use Core\Database;

/**
 * AIPlayer handles AI decision-making and turn execution
 */
class AIPlayer {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Perform AI turn with difficulty-based logic
     */
    public function performTurn(&$gameState) {
        $actions = [];
        $aiLevel = $gameState['ai_level'];
        
        // Get AI cards based on level
        $cardLimit = match($aiLevel) {
            1 => 5,   // Level 1: Very limited choices
            2 => 6,   // Level 2: Limited choices
            3 => 7,   // Level 3: Moderate choices
            default => 8  // Level 4+: Full choices
        };
        
        $stmt = $this->db->prepare("SELECT * FROM cards WHERE required_level <= ? ORDER BY RAND() LIMIT " . intval($cardLimit));
        $stmt->execute([min($aiLevel * 2, 10)]);
        $aiCards = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // AI plays cards based on available mana and strategy
        $aiMana = $gameState['ai_mana'];
        
        // Analyze game state
        $playerFieldSize = count($gameState['player_field']);
        $aiFieldSize = count($gameState['ai_field']);
        $aiHPPercent = $gameState['ai_hp'] / STARTING_HP;
        $playerHPPercent = $gameState['player_hp'] / STARTING_HP;
        
        // Calculate total player field power
        $playerFieldPower = 0;
        foreach ($gameState['player_field'] as $monster) {
            $playerFieldPower += intval($monster['attack'] ?? 0);
        }
        
        // Prioritize and score cards
        $scoredCards = [];
        foreach ($aiCards as $card) {
            $manaCost = intval($card['mana_cost'] ?? 1);
            if ($aiMana < $manaCost) {
                continue; // Can't afford this card
            }
            
            $score = $this->scoreCard($card, $gameState, $aiHPPercent, $playerHPPercent, $playerFieldSize, $aiFieldSize, $playerFieldPower, $aiLevel);
            
            // Add randomness to lower difficulty levels
            if ($aiLevel == 1) {
                $randomFactor = mt_rand(40, 160) / 100.0;
                $score *= $randomFactor;
            } else if ($aiLevel == 2) {
                $randomFactor = mt_rand(70, 130) / 100.0;
                $score *= $randomFactor;
            } else if ($aiLevel == 3) {
                $randomFactor = mt_rand(85, 115) / 100.0;
                $score *= $randomFactor;
            }
            
            $scoredCards[] = ['card' => $card, 'score' => $score];
        }
        
        // Sort cards by score (highest first)
        usort($scoredCards, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Play cards in priority order
        $cardsPlayed = 0;
        $maxCardsToPlay = match($aiLevel) {
            1 => 2,   // Level 1: Plays max 2 cards per turn
            2 => 3,   // Level 2: Plays max 3 cards per turn
            3 => 4,   // Level 3: Plays max 4 cards per turn
            default => 10  // Level 4+: Plays as many as possible
        };
        
        foreach ($scoredCards as $scoredCard) {
            if ($cardsPlayed >= $maxCardsToPlay) {
                break;
            }
            
            $card = $scoredCard['card'];
            $manaCost = intval($card['mana_cost'] ?? 1);
            
            if ($aiMana < $manaCost) {
                continue;
            }
            
            if ($card['type'] === 'monster') {
                // Normalize numeric stats
                $card['attack'] = intval($card['attack'] ?? 0);
                $card['defense'] = intval($card['defense'] ?? 0);
                
                // Ensure status_effects is an array
                if (!isset($card['status_effects']) || !is_array($card['status_effects'])) {
                    $card['status_effects'] = [];
                }
                
                // Apply keywords to monster
                if (!empty($card['keywords'])) {
                    $keywords = explode(',', $card['keywords']);
                    foreach ($keywords as $keyword) {
                        $keyword = trim($keyword);
                        if (in_array($keyword, ['taunt', 'divine_shield', 'stealth', 'windfury', 'lifesteal', 'poison', 'charge', 'rush'])) {
                            $card['status_effects'][] = $keyword;
                        }
                    }
                    $card['status_effects'] = array_values(array_unique($card['status_effects']));
                }
                
                // Initialize current_health
                $card['current_health'] = $card['health'] ?? $card['defense'];
                $card['max_health'] = $card['health'] ?? $card['defense'];
                
                $gameState['ai_field'][] = $card;
                $actions[] = "AI played {$card['name']} (ATK: {$card['attack']}, HP: {$card['current_health']})";
                $aiMana -= $manaCost;
                $aiFieldSize++;
                $cardsPlayed++;
                
                // Apply overload
                if (isset($card['overload']) && $card['overload'] > 0) {
                    $gameState['ai_overload'] += intval($card['overload']);
                }
            } else if ($card['type'] === 'spell') {
                $target = 'opponent';
                
                // Smart spell targeting
                if (strpos($card['effect'], 'heal') !== false) {
                    if ($gameState['ai_hp'] < STARTING_HP * 0.6) {
                        $target = 'self';
                    } else {
                        continue;
                    }
                } else if (strpos($card['effect'], 'boost') !== false) {
                    if ($aiFieldSize === 0) {
                        continue;
                    }
                }
                
                $battleSystem = new BattleSystem();
                $result = $battleSystem->applySpellEffect($gameState, $card, 'ai', $target);
                $message = $result['message'];
                $gameState = $result['gameState'];
                $actions[] = $message;
                $aiMana -= $manaCost;
                $cardsPlayed++;
                
                // Apply overload
                if (isset($card['overload']) && $card['overload'] > 0) {
                    $gameState['ai_overload'] += intval($card['overload']);
                }
            }
            
            // Stop if out of mana
            if ($aiMana <= 0) break;
        }
        
        $gameState['ai_mana'] = $aiMana;
        
        return ['actions' => $actions, 'gameState' => $gameState];
    }
    
    /**
     * Score a card based on game state and AI difficulty
     */
    private function scoreCard($card, $gameState, $aiHPPercent, $playerHPPercent, $playerFieldSize, $aiFieldSize, $playerFieldPower, $aiLevel) {
        $score = 0;
        $manaCost = intval($card['mana_cost'] ?? 1);
        
        if ($card['type'] === 'monster') {
            $attack = intval($card['attack'] ?? 0);
            $defense = intval($card['defense'] ?? 0);
            
            // Base value: stats relative to mana cost
            $statsValue = ($attack + $defense) / max(1, $manaCost);
            $score += $statsValue * 10;
            
            // Bonus for keywords
            if (!empty($card['keywords'])) {
                $keywords = explode(',', $card['keywords']);
                foreach ($keywords as $keyword) {
                    $keyword = trim($keyword);
                    
                    $keywordMultiplier = match($aiLevel) {
                        1 => 0.3,
                        2 => 0.6,
                        3 => 1.0,
                        4 => 1.5,
                        default => 2.0
                    };
                    
                    if ($keyword === 'taunt' && $playerFieldPower > 0) {
                        $score += 15 * $keywordMultiplier;
                    }
                    
                    if ($keyword === 'divine_shield') {
                        $score += 10 * $keywordMultiplier;
                    }
                    
                    if ($keyword === 'lifesteal' && $aiHPPercent < 0.7) {
                        $score += 12 * $keywordMultiplier;
                    }
                    
                    if (in_array($keyword, ['charge', 'rush'])) {
                        $score += 8 * $keywordMultiplier;
                    }
                    
                    if ($keyword === 'windfury') {
                        $score += 10 * $keywordMultiplier;
                    }
                }
            }
            
            // Prioritize monsters when we need board presence
            if ($aiFieldSize < $playerFieldSize) {
                if ($aiLevel >= 3) {
                    $score += 20;
                } else if ($aiLevel == 2) {
                    $score += 10;
                }
            }
            
            // Higher level AI values efficient trades
            if ($aiLevel >= 3) {
                $score += $attack * 2;
            } else if ($aiLevel == 2) {
                $score += $attack;
            }
            
        } else if ($card['type'] === 'spell') {
            $effect = $card['effect'];
            
            if (strpos($effect, 'damage') !== false) {
                preg_match('/damage:(\d+)/', $effect, $matches);
                $damage = isset($matches[1]) ? intval($matches[1]) : 0;
                
                $damageMultiplier = match($aiLevel) {
                    1 => 2,
                    2 => 3,
                    3 => 5,
                    4 => 6,
                    default => 7
                };
                $score += $damage * $damageMultiplier;
                
                // More valuable when opponent is low HP
                if ($playerHPPercent < 0.3) {
                    if ($aiLevel >= 4) {
                        $score += 50;
                    } else if ($aiLevel == 3) {
                        $score += 30;
                    } else if ($aiLevel == 2) {
                        $score += 15;
                    }
                }
                
                // Less valuable early game
                if ($gameState['turn_count'] < 3 && $aiLevel >= 3) {
                    $score -= 10;
                }
            } else if (strpos($effect, 'heal') !== false) {
                preg_match('/heal:(\d+)/', $effect, $matches);
                $heal = isset($matches[1]) ? intval($matches[1]) : 0;
                
                if ($aiLevel <= 2) {
                    if ($aiHPPercent < 0.8) {
                        $score += $heal * (1 - $aiHPPercent) * 15;
                    } else {
                        $score -= 5;
                    }
                } else {
                    if ($aiHPPercent < 0.6) {
                        $score += $heal * (1 - $aiHPPercent) * 10;
                    } else {
                        $score -= 20;
                    }
                }
            } else if (strpos($effect, 'boost') !== false) {
                if ($aiFieldSize > 0) {
                    $boostValue = match($aiLevel) {
                        1 => 5,
                        2 => 10,
                        3 => 15,
                        4 => 20,
                        default => 25
                    };
                    $score += $boostValue * $aiFieldSize;
                } else {
                    $score -= 30;
                }
            } else if (strpos($effect, 'stun') !== false) {
                if ($playerFieldSize > 0) {
                    $stunValue = match($aiLevel) {
                        1 => 5,
                        2 => 10,
                        3 => 15,
                        4 => 20,
                        default => 25
                    };
                    $score += $stunValue * $playerFieldSize;
                }
            }
            
            // Higher level AI uses spells more strategically
            if ($aiLevel >= 4) {
                $score += 5;
            }
        }
        
        // Mana efficiency bonus
        $manaEfficiency = 10 - $manaCost;
        if ($aiLevel >= 3) {
            $score += $manaEfficiency;
        } else if ($aiLevel == 2) {
            $score += $manaEfficiency * 0.5;
        }
        
        return $score;
    }
}
