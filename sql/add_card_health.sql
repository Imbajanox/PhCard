-- Add health/HP system to cards
-- This migration adds a health field to cards and sets default values based on defense

-- Add health column to cards table
ALTER TABLE cards ADD COLUMN health INT DEFAULT NULL AFTER defense;

-- Set health equal to defense for existing cards (initial migration)
-- This preserves game balance while adding the new system
UPDATE cards SET health = defense WHERE health IS NULL;

-- Make health NOT NULL after setting values
ALTER TABLE cards MODIFY COLUMN health INT NOT NULL DEFAULT 0;

-- For future reference:
-- Monster cards will now have: attack, defense, and health
-- - attack: damage dealt when attacking
-- - defense: reduces incoming damage (optional - can be repurposed or kept for armor-like mechanics)
-- - health: how much damage the card can take before being destroyed
