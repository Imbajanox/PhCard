-- Add admin flag to users table
USE phcard;

ALTER TABLE users 
ADD COLUMN is_admin BOOLEAN DEFAULT false AFTER email;

-- Add 30 new cards for deck building
INSERT INTO cards (name, type, attack, defense, effect, required_level, rarity, description, keywords, mana_cost, card_class) VALUES
-- Common Monster Cards (Level 1-2)
('Cave Bat', 'monster', 150, 50, NULL, 1, 'common', 'Ein schneller Höhlenfledermaus', 'rush', 1, 'neutral'),
('Young Warrior', 'monster', 300, 200, NULL, 1, 'common', 'Ein junger Krieger in Ausbildung', NULL, 2, 'warrior'),
('Apprentice Mage', 'monster', 250, 150, NULL, 1, 'common', 'Ein Magierlehrling', NULL, 2, 'mage'),
('Shadow Rogue', 'monster', 350, 100, NULL, 1, 'common', 'Ein geschickter Schurke aus dem Schatten', 'stealth', 2, 'rogue'),
('Temple Guardian', 'monster', 100, 400, NULL, 1, 'common', 'Ein Wächter des Tempels', 'taunt', 2, 'priest'),

-- Common Spell Cards (Level 1-2)
('Minor Heal', 'spell', 0, 0, 'heal:200', 1, 'common', 'Heilt 200 Lebenspunkte', NULL, 1, 'priest'),
('Spark', 'spell', 0, 0, 'damage:300', 1, 'common', 'Verursacht 300 Schaden', NULL, 1, 'mage'),
('Shield Wall', 'spell', 0, 0, 'shield:400', 1, 'common', 'Gewährt 400 Verteidigung', NULL, 2, 'warrior'),
('Backstab', 'spell', 0, 0, 'damage:500', 2, 'common', 'Verursacht 500 direkten Schaden', NULL, 2, 'rogue'),
('Holy Light', 'spell', 0, 0, 'heal:400', 2, 'common', 'Heilt 400 Lebenspunkte mit heiligem Licht', NULL, 2, 'paladin'),

-- Rare Monster Cards (Level 3-4)
('Flame Elemental', 'monster', 600, 300, NULL, 3, 'rare', 'Ein lodernder Feuerelementar', 'charge', 4, 'mage'),
('Iron Golem', 'monster', 400, 700, NULL, 3, 'rare', 'Ein massiver eiserner Golem', 'taunt', 5, 'warrior'),
('Poison Assassin', 'monster', 500, 200, 'poison:3', 3, 'rare', 'Vergiftet Feinde für 3 Runden', 'poison,stealth', 4, 'rogue'),
('Holy Paladin', 'monster', 450, 450, NULL, 3, 'rare', 'Ein heiliger Paladin', 'taunt,lifesteal', 5, 'paladin'),
('Mind Controller', 'monster', 300, 500, NULL, 4, 'rare', 'Ein Gedankenkontrolleur', NULL, 4, 'priest'),

-- Rare Spell Cards (Level 3-4)
('Frost Nova', 'spell', 0, 0, 'freeze:all', 3, 'rare', 'Friert alle gegnerischen Monster ein', NULL, 3, 'mage'),
('Execute', 'spell', 0, 0, 'damage:700', 3, 'rare', 'Vernichtet ein geschwächtes Ziel', NULL, 3, 'warrior'),
('Vanish', 'spell', 0, 0, 'return:all', 4, 'rare', 'Gibt alle Monster auf die Hand zurück', NULL, 4, 'rogue'),
('Divine Blessing', 'spell', 0, 0, 'heal:600', 4, 'rare', 'Segnet mit göttlicher Heilung', NULL, 3, 'paladin'),
('Shadow Word: Death', 'spell', 0, 0, 'destroy:1', 4, 'rare', 'Zerstört ein starkes Monster', NULL, 4, 'priest'),

-- Epic Monster Cards (Level 5-6)
('Inferno Drake', 'monster', 1000, 400, NULL, 5, 'epic', 'Ein gewaltiger Feuerdrache', 'charge,windfury', 7, 'mage'),
('Armored Titan', 'monster', 800, 900, NULL, 5, 'epic', 'Ein gepanzerter Titan', 'taunt,divine_shield', 8, 'warrior'),
('Master Assassin', 'monster', 1200, 300, NULL, 5, 'epic', 'Ein Meisterassassine', 'stealth,poison', 6, 'rogue'),
('Lightbringer', 'monster', 900, 600, NULL, 6, 'epic', 'Ein Träger des Lichts', 'lifesteal,divine_shield', 7, 'paladin'),
('Shadow Priest', 'monster', 700, 700, NULL, 6, 'epic', 'Ein Schattenpriester', 'lifesteal', 6, 'priest'),

-- Epic Spell Cards (Level 5-6)
('Meteor', 'spell', 0, 0, 'damage:1000', 5, 'epic', 'Ruft einen Meteor herbei', NULL, 7, 'mage'),
('Battle Rage', 'spell', 0, 0, 'boost:800', 5, 'epic', 'Entfesselt kampfwut', NULL, 5, 'warrior'),
('Deadly Poison', 'spell', 0, 0, 'poison:5', 6, 'epic', 'Vergiftet dauerhaft', NULL, 5, 'rogue'),
('Lay on Hands', 'spell', 0, 0, 'heal:1000', 6, 'epic', 'Vollständige Heilung durch Handauflegen', NULL, 8, 'paladin'),
('Mind Blast', 'spell', 0, 0, 'damage:900', 6, 'epic', 'Zerstört den Geist des Feindes', NULL, 5, 'priest'),

-- Legendary Cards (Level 8+)
('Archmage Supreme', 'monster', 1400, 800, NULL, 8, 'legendary', 'Der oberste Erzmagier', 'windfury,lifesteal', 10, 'mage');
