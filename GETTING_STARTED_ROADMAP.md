# Getting Started with PhCard Development Roadmap

Welcome! This guide helps you navigate the PhCard roadmap and start contributing to the project's development.

---

## 📚 Which Document Should I Read?

Choose based on your needs:

### 🚀 **New to PhCard Development?**
→ Start with **[ROADMAP_SUMMARY.md](ROADMAP_SUMMARY.md)**
- Quick overview of development phases
- Critical path and priorities
- 5-minute read to understand the big picture

### 📊 **Want Visual Overview?**
→ Check **[ROADMAP_VISUAL.md](ROADMAP_VISUAL.md)**
- ASCII diagrams and flowcharts
- Timeline visualizations
- Priority quadrants
- Perfect for visual learners

### 📖 **Need Detailed Information?**
→ Read **[ROADMAP.md](ROADMAP.md)**
- Comprehensive strategic document
- All 6 phases with detailed breakdowns
- Resource planning and risk assessment
- Complete reference guide

### 🎯 **Ready to Start Working?**
→ See **[Phase 1: Quick Wins](#phase-1-quick-wins)** below
- Immediate actionable tasks
- Good first issues
- Clear acceptance criteria

---

## 🎯 Phase 1: Quick Wins - Getting Started

These are the **highest priority, lowest effort** items. Perfect for getting started!

### 1.1 Add Tutorial System
**Effort**: Low | **Impact**: High | **Skills**: JavaScript, UX

**What to do**:
- Create interactive tutorial overlay
- Guide new players through first game
- Add tooltips for game mechanics
- Implement "Skip Tutorial" option

**Acceptance Criteria**:
- [ ] New players see tutorial on first login
- [ ] Tutorial covers: cards, playing, turn ending
- [ ] Users can skip or replay tutorial
- [ ] Tutorial doesn't break existing gameplay

**Files to modify**:
- `index.html` - Add tutorial modal
- `src/frontend/js/core/app.js` - Tutorial logic
- `public/css/style.css` - Tutorial styling

---

### 1.2 Add Sound Effects
**Effort**: Low | **Impact**: Medium | **Skills**: JavaScript, Audio

**What to do**:
- Add sound for card play
- Add sound for attacks
- Add sound for victories/defeats
- Add sound for button clicks
- Add volume control and mute option

**Acceptance Criteria**:
- [ ] Sounds play at appropriate moments
- [ ] No jarring or annoying sounds
- [ ] Users can mute/adjust volume
- [ ] Sounds load asynchronously (no blocking)

**Files to create**:
- `public/audio/` - Sound files directory
- `src/frontend/js/audio/sound-manager.js` - Sound controller

**Files to modify**:
- `src/frontend/js/game/game.js` - Trigger sounds
- `index.html` - Add audio controls

---

### 1.3 Improve Mobile Responsiveness
**Effort**: Medium | **Impact**: High | **Skills**: CSS, Responsive Design

**What to do**:
- Make cards swipeable on mobile
- Improve touch target sizes (44x44px min)
- Add hamburger menu for navigation
- Test on various screen sizes
- Add PWA manifest

**Acceptance Criteria**:
- [ ] Works on phones (320px width)
- [ ] Works on tablets (768px width)
- [ ] Touch targets meet accessibility standards
- [ ] No horizontal scrolling
- [ ] Can be installed as PWA

**Files to modify**:
- `public/css/style.css` - Media queries
- `index.html` - Viewport meta tag
- `manifest.json` - PWA manifest (create)
- `service-worker.js` - Offline support (create)

---

### 1.4 Add Basic Error Handling
**Effort**: Low | **Impact**: High | **Skills**: JavaScript, PHP

**What to do**:
- Add try-catch blocks in critical paths
- Show user-friendly error messages
- Log errors to console/server
- Add graceful degradation for API failures

**Acceptance Criteria**:
- [ ] No uncaught exceptions in console
- [ ] Users see helpful error messages
- [ ] Game doesn't crash on API failure
- [ ] Errors are logged for debugging

**Files to modify**:
- `src/frontend/js/game/game.js` - Frontend error handling
- `src/frontend/js/core/app.js` - Global error handler
- `api/*.php` - Backend error responses

---

### 1.5 Add Basic Unit Tests
**Effort**: Medium | **Impact**: High | **Skills**: PHPUnit, Testing

**What to do**:
- Set up PHPUnit testing framework
- Write tests for critical backend functions
- Write tests for game logic
- Add tests to CI pipeline

**Acceptance Criteria**:
- [ ] PHPUnit configured and running
- [ ] At least 10 unit tests passing
- [ ] Tests cover: auth, game logic, card effects
- [ ] Tests run in CI/CD

**Files to create**:
- `tests/` - Test directory
- `phpunit.xml` - PHPUnit configuration
- `tests/GameLogicTest.php` - Example test

---

## 🔄 Development Workflow

### 1. Pick a Task
Choose from Phase 1 items or:
- Browse GitHub Issues labeled "good first issue"
- Check project board for "Ready to Start" column
- Ask in Discord what needs help

### 2. Set Up Environment
```bash
# Clone repository
git clone https://github.com/Imbajanox/PhCard.git
cd PhCard

# Set up database (see README.md)
mysql -u root -p phcard < sql/database.sql
mysql -u root -p phcard < sql/database_extensions.sql
mysql -u root -p phcard < sql/database_multiplayer.sql

# Configure database connection
cp config.php.example config.php
# Edit config.php with your DB credentials

# Start local server
php -S localhost:8000
```

### 3. Create Feature Branch
```bash
git checkout -b feature/tutorial-system
# or
git checkout -b fix/mobile-responsiveness
```

### 4. Implement Changes
- Write code following existing patterns
- Add comments for complex logic
- Test manually in browser
- Add unit tests if applicable

### 5. Test Thoroughly
```bash
# Run PHP syntax check
php -l api/auth.php

# Run tests (once set up)
./vendor/bin/phpunit

# Test in browser
# - Different screen sizes
# - Different browsers
# - Edge cases
```

### 6. Submit Pull Request
```bash
git add .
git commit -m "Add tutorial system for new players"
git push origin feature/tutorial-system
```
Then create PR on GitHub with:
- Description of changes
- Screenshots/GIFs if UI changes
- Test results
- Reference to issue number

---

## 📋 Coding Standards

### PHP
- Use PSR-12 coding standard
- Type hints for function parameters
- DocBlocks for all functions
- Prepared statements for SQL (no raw queries)

```php
/**
 * Get user profile data
 * 
 * @param int $userId User ID
 * @return array User profile data
 */
function getUserProfile(int $userId): array {
    // Implementation
}
```

### JavaScript
- Use ES6+ features
- Async/await for API calls
- JSDoc comments for functions
- Modular code organization

```javascript
/**
 * Load user profile
 * @param {number} userId - User ID to load
 * @returns {Promise<Object>} User profile data
 */
async function loadUserProfile(userId) {
    // Implementation
}
```

### CSS
- Use meaningful class names
- Follow BEM naming convention
- Mobile-first responsive design
- Group related rules together

```css
/* Card component */
.card {
    /* Base styles */
}

.card__title {
    /* Element styles */
}

.card--highlighted {
    /* Modifier styles */
}
```

---

## 🧪 Testing Guidelines

### What to Test
- All API endpoints
- Game logic functions
- Card effect calculations
- User authentication
- Data validation

### Test Structure
```php
class GameLogicTest extends TestCase {
    public function testCardDamageCalculation() {
        // Arrange
        $card = new Card(['attack' => 500]);
        $hp = 2000;
        
        // Act
        $newHp = applyDamage($hp, $card);
        
        // Assert
        $this->assertEquals(1500, $newHp);
    }
}
```

---

## 🐛 Bug Reporting

### Found a Bug?
1. Check if already reported in GitHub Issues
2. If not, create new issue with:
   - Clear, descriptive title
   - Steps to reproduce
   - Expected behavior
   - Actual behavior
   - Screenshots if applicable
   - Browser/device information

### Example Bug Report
```markdown
**Title**: Cards not displaying on mobile devices

**Steps to Reproduce**:
1. Open game on iPhone Safari
2. Start new game
3. Cards should appear in hand

**Expected**: Cards display in hand
**Actual**: Hand area is blank

**Screenshots**: [attach screenshot]
**Device**: iPhone 12, iOS 15, Safari
```

---

## 💬 Getting Help

### Resources
- **Documentation**: `documentation/` folder
- **README**: Setup and feature overview
- **Developer Guide**: `documentation/DEVELOPER.md`
- **API Reference**: (coming soon)

### Community
- **Discord**: (link to be added)
- **GitHub Discussions**: Ask questions
- **GitHub Issues**: Report bugs

### Response Time
- Issues: Typically within 48 hours
- PRs: Review within 1 week
- Questions: 24-48 hours

---

## 🎓 Learning Path

### New to Card Games?
1. Play PhCard to understand mechanics
2. Read game design documents
3. Study existing card implementations
4. Start with simple card effects

### New to PHP?
1. Read PHP documentation
2. Study existing API files
3. Learn PDO for database
4. Understand sessions and auth

### New to JavaScript?
1. Learn ES6+ syntax
2. Understand async/await
3. Study Fetch API
4. Review existing frontend code

### New to Web Development?
1. HTML/CSS fundamentals
2. JavaScript basics
3. HTTP/REST concepts
4. Database basics (SQL)

---

## 🏆 Recognition

### Contribution Levels

**First-Time Contributor**
- 1 merged PR
- Listed in contributors
- Welcome to community!

**Regular Contributor**
- 5+ merged PRs
- Special Discord role
- Input on roadmap priorities

**Core Contributor**
- 20+ merged PRs
- Write access to repository
- Roadmap planning participation

**Maintainer**
- Consistent contributions
- Code review permissions
- Direct decision-making input

---

## 📅 Roadmap Review Cycle

The roadmap is reviewed and updated:
- **Weekly**: During team sync (if team exists)
- **Monthly**: Priority adjustments
- **Quarterly**: Major milestone review
- **As needed**: Based on data/feedback

Your input matters! Suggest changes via:
- GitHub Issues
- Pull Requests to roadmap docs
- Community discussions

---

## ✅ Quick Checklist for Starting

- [ ] Read ROADMAP_SUMMARY.md (5 min)
- [ ] Set up local development environment
- [ ] Pick a Phase 1 task
- [ ] Create feature branch
- [ ] Make changes
- [ ] Test thoroughly
- [ ] Submit PR
- [ ] Respond to review feedback
- [ ] Celebrate your contribution! 🎉

---

## 🚀 Ready to Start?

**Recommended First Tasks**:

1. **Tutorial System** - Great for UX/JavaScript practice
2. **Sound Effects** - Fun and satisfying to implement
3. **Error Handling** - Learn codebase structure
4. **Unit Tests** - Understand game logic
5. **Mobile CSS** - Practice responsive design

Pick one that interests you and matches your skills. Don't be afraid to ask questions!

---

**Happy Coding!** 🎮

*Remember: Every contribution, no matter how small, helps make PhCard better for all players.*
