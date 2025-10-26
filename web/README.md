# Features Frontend

This directory contains the frontend UI for PhCard's extensibility features: Quests, Achievements, and Card Sets.

## Files

### HTML Pages (web/features/)
- **quests.html** - Quest listing and progress tracking interface
- **achievements.html** - Achievement display and unlock tracking
- **cardsets.html** - Card set/expansion browser with modal card viewer

### JavaScript (web/js/features/)
- **quests.js** - Quest API integration, filtering, and reward claiming
- **achievements.js** - Achievement API integration and progress display
- **cardsets.js** - Card set API integration with modal functionality

### CSS (web/css/)
- **features.css** - Shared styling for all feature pages

## Features

### Progressive Enhancement
All pages are designed to gracefully degrade if backend APIs are not available:
- Display friendly error messages when endpoints return 404/500
- Show loading states while fetching data
- Handle network errors gracefully

### API Integration
The pages integrate with these backend endpoints:
- `/api/quests.php?action=get_active_quests` - Fetch active quests
- `/api/quests.php?action=claim_quest_reward` - Claim quest rewards
- `/api/quests.php?action=get_achievements` - Fetch all achievements
- `/api/quests.php?action=get_user_achievements` - Fetch user achievement progress
- `/api/card_sets.php?action=list_sets` - Fetch all card sets
- `/api/card_sets.php?action=get_set_cards&set_id={id}` - Fetch cards in a set

### Security Notes
- All JavaScript includes XSS protection via HTML escaping
- API calls use credentials: 'include' for session cookies
- Comments in JS files indicate where to add CSRF tokens if needed
- Ready for integration with existing auth/session code

### Quest Features
- Filter by quest type (daily, weekly, story)
- Filter by status (active, completed, all)
- Visual progress bars for quest objectives
- One-click reward claiming for completed quests
- Clear indication of claimed quests

### Achievement Features
- Grid display of all achievements
- Locked/unlocked state visualization
- Progress tracking for in-progress achievements
- Achievement statistics (X/Y unlocked)
- Display unlock dates for completed achievements

### Card Set Features
- Grid display of all card sets/expansions
- Set metadata (name, code, type, card count, release date)
- Click to view cards in a set
- Modal popup for card browsing
- Card details (stats, type, rarity, description)

## Usage

Simply open any of the HTML pages in a browser. The pages will:
1. Attempt to load data from the backend API
2. Display data if available
3. Show a friendly error message if the backend is not yet deployed

## Navigation
All pages include a navigation bar linking to:
- Home (index.html)
- Dashboard (dashboard.html)
- Quests, Achievements, and Card Sets pages

## Styling
The `features.css` file provides:
- Responsive design (mobile-friendly)
- Modern, clean UI with gradient backgrounds
- Consistent theming across all pages
- Hover effects and transitions
- Modal overlay for card viewing
- Progress bars and status indicators

## Integration Notes for Maintainers

### CSRF Token Integration
If your auth system uses CSRF tokens, uncomment and implement the `getCsrfToken()` function in each JS file:

```javascript
function getCsrfToken() {
    const token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.getAttribute('content') : '';
}
```

Then add to fetch headers:
```javascript
headers: {
    'X-CSRF-Token': getCsrfToken(),
}
```

### Authorization Headers
If using token-based auth instead of session cookies, add to fetch calls:
```javascript
headers: {
    'Authorization': 'Bearer ' + getAuthToken(),
}
```

## Browser Compatibility
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Uses ES6+ JavaScript (async/await, arrow functions, template literals)
- CSS Grid and Flexbox for layouts
- No external dependencies (vanilla JavaScript)

## No Backend Changes Required
This is a pure frontend implementation. The backend APIs were added in a previous PR (#11). These pages will work once those APIs are deployed and accessible.
