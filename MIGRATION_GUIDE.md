# Migrations-Anleitung / Migration Guide

## Deutsch

### Neue Funktionen in diesem Update:
1. **Admin-Flag für Benutzer**: Nur Admins können Analytics sehen
2. **Deck Builder**: Vollständige Deck-Building-Funktionalität im Frontend
3. **Statistik-Seite**: Einfache Statistikseite für normale Benutzer
4. **30 neue Karten**: Neue Karten für verschiedene Klassen

### Datenbank-Migration

Um die neuen Funktionen zu nutzen, müssen Sie die Datenbank aktualisieren:

```bash
# Führen Sie folgende SQL-Skripte in dieser Reihenfolge aus:

# 1. Zuerst die erweiterten Tabellen (falls noch nicht geschehen)
mysql -u root -p phcard < database_extensions.sql

# 2. Dann die neuen Admin- und Karten-Updates
mysql -u root -p phcard < database_admin_and_cards.sql
```

### Admin-Benutzer erstellen

Um einen Benutzer zum Admin zu machen:

```sql
USE phcard;
UPDATE users SET is_admin = 1 WHERE username = 'dein_username';
```

### Neue Seiten

- **Deck Builder**: Verfügbar im Hauptmenü nach dem Login
- **Statistiken**: Verfügbar für alle Benutzer im Hauptmenü
- **Analytics**: Nur für Admin-Benutzer sichtbar

---

## English

### New Features in this Update:
1. **Admin Flag for Users**: Only admins can see Analytics
2. **Deck Builder**: Complete deck-building functionality in the frontend
3. **Statistics Page**: Simple statistics page for normal users
4. **30 New Cards**: New cards for different classes

### Database Migration

To use the new features, you need to update the database:

```bash
# Run the following SQL scripts in this order:

# 1. First the extended tables (if not done yet)
mysql -u root -p phcard < database_extensions.sql

# Check if extensions were applied successfully
if [ $? -eq 0 ]; then
    echo "✓ Extensions migration successful"
else
    echo "✗ Extensions migration failed - check errors"
    exit 1
fi

# 2. Then the new admin and card updates
mysql -u root -p phcard < database_admin_and_cards.sql

# Check if admin updates were applied successfully
if [ $? -eq 0 ]; then
    echo "✓ Admin and cards migration successful"
else
    echo "✗ Admin migration failed - check errors"
    exit 1
fi

# 3. Verify the changes
echo "Verifying database changes..."
mysql -u root -p phcard -e "DESCRIBE users;" | grep is_admin && echo "✓ Admin column added"
mysql -u root -p phcard -e "SELECT COUNT(*) as card_count FROM cards;" && echo "✓ Cards counted"
```

### Create Admin User

To make a user an admin:

```sql
USE phcard;
UPDATE users SET is_admin = 1 WHERE username = 'your_username';
```

### New Pages

- **Deck Builder**: Available in the main menu after login
- **Statistics**: Available for all users in the main menu
- **Analytics**: Only visible for admin users

## Testing the Features

1. **Test Deck Builder**:
   - Login as a regular user
   - Click "Deck Builder" button
   - Create a new deck
   - Add/remove cards
   - Save the deck

2. **Test Statistics Page**:
   - Login as any user
   - Click "Statistiken" button
   - View your game statistics

3. **Test Admin Access**:
   - Login as a non-admin user - Analytics button should be hidden
   - Login as an admin user - Analytics button should be visible
   - Click Analytics to access the dashboard

## Notes

- The deck builder requires the `database_extensions.sql` to be applied first
- Admin flag defaults to `false` for all users
- Normal users will see "Statistiken" button instead of "Analytics & Simulation"
- Admin users will see both "Statistiken" and "Analytics & Simulation" buttons
