# Calendar Plugin - Matrix Edition
## Version 5.0.0 Release Notes

**Release Date:** February 8, 2026  
**Major Milestone:** Complete Theming Perfection  

---

## 🎉 Welcome to Version 5.0

This major release represents the culmination of extensive development to create the most visually consistent, beautifully themed calendar plugin for DokuWiki. Every pixel, every element, and every interaction has been carefully themed to provide a cohesive, professional experience.

---

## 🌟 What's New in Version 5.0

### Five Stunning Themes

The plugin now includes **5 complete themes**, each with distinct personalities:

#### 🟢 **Matrix Edition** (Default)
- **Style:** Dark green with clean appearance
- **Colors:** Neon green (#00cc07) on dark backgrounds
- **Best For:** Tech-focused wikis, retro aesthetic fans
- **Features:** Green borders, clean text, professional look
- **Mood:** Technical, focused, classic

#### 🟣 **Purple Dream**
- **Style:** Rich purple with elegant styling
- **Colors:** Lavender and violet (#9b59b6, #b19cd9)
- **Best For:** Creative wikis, elegant presentations
- **Features:** Purple borders, sophisticated appearance
- **Mood:** Elegant, creative, refined

#### 🔵 **Professional Blue**
- **Style:** Clean, modern corporate aesthetic
- **Colors:** Blue (#4a90e2) with light backgrounds
- **Best For:** Business wikis, professional documentation
- **Features:** No glow effects, subtle shadows, clean lines
- **Mood:** Professional, trustworthy, modern

#### 💎 **Pink Bling**
- **Style:** Glamorous with maximum sparkle
- **Colors:** Hot pink (#ff1493) with glow effects
- **Best For:** Fun projects, creative teams, personal wikis
- **Features:** Text glow, border sparkle, maximum visual impact
- **Mood:** Fun, energetic, eye-catching ✨

#### 📄 **Wiki Default** (NEW in v5.0)
- **Style:** Automatically matches your DokuWiki template
- **Colors:** Uses CSS variables from your template
- **Best For:** Perfect integration with any wiki theme
- **Features:** Adapts to light/dark themes automatically
- **Mood:** Seamless, integrated, consistent

---

## 🎨 Complete Theming Coverage

### Every Element is Themed

**Calendar Grid:**
- ✅ Grid backgrounds and borders
- ✅ Cell backgrounds (normal, today, hover states)
- ✅ Day numbers with theme colors
- ✅ Event bars with color-coded left borders
- ✅ Today cell with prominent border (2px solid)
- ✅ Week headers with theme text colors

**Sidebar Widget:**
- ✅ Widget background and borders
- ✅ Header with gradient backgrounds
- ✅ Week grid cells
- ✅ Day numbers (themed text)
- ✅ "Add Event" button
- ✅ Event dividers (themed borders)
- ✅ Section headers (Today, Upcoming, Past)

**Event List Panel:**
- ✅ Event boxes (all 4 borders themed)
- ✅ Event titles, dates, times
- ✅ Event descriptions with styled links and bold text
- ✅ Namespace badges
- ✅ TODAY and PAST DUE badges
- ✅ Task checkboxes (checked and unchecked)
- ✅ Edit and Delete buttons
- ✅ Search bar with themed placeholder
- ✅ Past Events toggle with themed border
- ✅ Completed task strikethrough

**Edit/Add Event Dialog:**
- ✅ Dialog container (background, border, shadow)
- ✅ Dialog header with gradient
- ✅ Close button (×)
- ✅ All form field backgrounds
- ✅ All text inputs (title, description, dates)
- ✅ All select dropdowns (time, color, recurrence)
- ✅ Namespace search with dropdown
- ✅ All labels and field names
- ✅ All checkboxes (Repeating Event, Task)
- ✅ Checkbox field containers
- ✅ Recurring options section
- ✅ Color picker
- ✅ Cancel and Save buttons
- ✅ Button footer section

**Day Popup Dialog:**
- ✅ Popup container (background, border, shadow)
- ✅ Popup header with date
- ✅ Close button
- ✅ Event list items
- ✅ Event titles and times
- ✅ Namespace badges
- ✅ "No events" message
- ✅ "Add Event" button
- ✅ Footer section

**Month Picker:**
- ✅ Month and year dropdowns
- ✅ Go button
- ✅ All backgrounds and borders

**Interactive Elements:**
- ✅ Event highlight glow (click event bar)
- ✅ Button hover effects
- ✅ Link colors in descriptions
- ✅ Bold text in descriptions

---

## 🔧 Technical Improvements

### CSS Variable Integration (Wiki Default Theme)

The Wiki Default theme uses DokuWiki's CSS variables for perfect integration:

```css
--__background_site__    /* Main page background */
--__background_alt__     /* Section backgrounds */
--__background__         /* Content backgrounds */
--__text__               /* Primary text color */
--__link__               /* Link color */
--__text_neu__           /* Dimmed text */
--__border__             /* Border color */
--__background_neu__     /* Neutral backgrounds */
```

**With fallbacks** for older DokuWiki versions that don't have these variables.

### Theme Persistence

Themes persist across:
- ✅ Page loads
- ✅ Navigation (month to month)
- ✅ Filtering and searching
- ✅ Event creation and editing
- ✅ All interactions

### Border Glow Effects (Selective)

**Border/Box Glow:**
- Present on all themes ✓
- Creates visual depth
- Subtle on Professional/Wiki themes
- Prominent on Matrix/Purple/Pink themes

**Text Glow:**
- **Only on Pink Bling theme** ✓
- Removed from Matrix (v4.11.0)
- Removed from Purple (v4.11.0)
- Never on Professional or Wiki Default
- Creates cleaner, more professional appearance

### Event Highlight System

When you click an event bar in the calendar:
1. Function identifies the theme
2. Applies theme-specific highlight colors
3. Glows for 3 seconds with smooth animation
4. Auto-scrolls event into view
5. Fades back to normal

**Highlight Colors by Theme:**
- Matrix: Dark green with double green glow
- Purple: Dark purple with double purple glow
- Professional: Light blue with subtle blue glow
- Pink: Dark pink with maximum sparkle glow
- Wiki: Adaptive based on template

---

## 📋 Feature Highlights

### Complete Form Theming

Every form element themed:
- Text inputs (background, text, border)
- Textareas (description field)
- Date inputs (start date, end date, recurrence end)
- Time dropdowns (start time, end time)
- Select dropdowns (recurrence type, color)
- Checkboxes (repeating event, task checkbox)
- Color picker
- Namespace search with autocomplete
- All labels and field descriptions

### Smart Badge System

Badges automatically theme:
- **TODAY badge:** Uses theme border color
- **PAST DUE badge:** Always orange (#ff9800)
- **Namespace badges:** Use theme border color
- All badges have proper contrast (white or theme background text)

### Task Management

Full task support:
- Checkboxes themed (checked and unchecked states)
- Accent color matches theme
- Background color matches theme
- Border color matches theme
- Strikethrough on completed tasks
- Toggle completion with click

### Search Functionality

Complete search theming:
- Input background themed
- Text color themed
- Border color themed
- Placeholder text themed (with all vendor prefixes)
- Search icon (🔍) integrated
- Clear button themed

---

## 🎯 Perfect Visual Consistency

### No White Pixels

**Achieved through:**
- Inline !important styles on all elements
- Theme-aware backgrounds everywhere
- Proper border-color application
- Form field container backgrounds
- Dialog section backgrounds
- Checkbox field backgrounds

### Theme-Specific Customization

**Each theme has unique:**
- Background colors (bg, cell_bg, grid_bg)
- Text colors (text_primary, text_bright, text_dim)
- Border colors (border, grid_border)
- Shadow effects (shadow, header_shadow, bar_glow)
- Header gradients (header_bg)
- Hover states

### Comprehensive Color Palette

**Matrix Theme Colors:**
```
bg: #242424 (dark background)
border: #00cc07 (neon green)
text_primary: #00cc07 (green text)
text_bright: #00ff00 (bright green)
text_dim: #00aa00 (dim green)
cell_bg: #242424 (cell background)
grid_border: #00cc07 (borders)
```

**Purple Theme Colors:**
```
bg: #2a2030 (dark purple background)
border: #9b59b6 (rich purple)
text_primary: #b19cd9 (lavender)
text_bright: #d4a5ff (bright purple)
text_dim: #8e7ab8 (dim purple)
cell_bg: #2a2030 (cell background)
grid_border: #9b59b6 (borders)
```

**Professional Theme Colors:**
```
bg: #f5f7fa (light background)
border: #4a90e2 (professional blue)
text_primary: #2c3e50 (dark text)
text_bright: #4a90e2 (blue accents)
text_dim: #7f8c8d (gray text)
cell_bg: #ffffff (white cells)
grid_border: #d0d7de (subtle borders)
```

**Pink Theme Colors:**
```
bg: #1a0d14 (dark pink background)
border: #ff1493 (hot pink)
text_primary: #ff69b4 (pink text)
text_bright: #ff1493 (bright pink)
text_dim: #ff85c1 (light pink)
cell_bg: #1a0d14 (cell background)
grid_border: #ff1493 (borders)
```

**Wiki Default Theme:**
```
Uses CSS variables from your template
Automatically adapts to:
- Light templates
- Dark templates
- Custom templates
- Any DokuWiki theme
```

---

## 📦 Installation

### Requirements
- DokuWiki (any recent version)
- PHP 7.0 or higher
- Modern web browser

### Installation Steps

1. **Download** the plugin archive (`calendar-matrix-edition-v5.0.0.zip`)

2. **Upload** via DokuWiki Extension Manager:
   - Go to Admin → Extension Manager
   - Click "Manual Install" tab
   - Upload the zip file
   - Install

3. **Select Theme**:
   - Go to Admin → Calendar Management → Themes tab
   - Choose your preferred theme
   - Click "Save Settings"

4. **Add to Sidebar**:
   ```
   {{calendar>}}
   ```

5. **Add to Pages** (optional):
   ```
   {{calendar>namespace}}
   ```

---

## 🎨 Theme Selection Guide

### When to Use Each Theme

**Matrix Edition:**
- Tech documentation wikis
- Developer teams
- Retro/cyberpunk aesthetic
- Dark mode preference
- Want the classic look

**Purple Dream:**
- Creative projects
- Design teams
- Elegant presentations
- Sophisticated appearance
- Want something different from green

**Professional Blue:**
- Corporate wikis
- Business documentation
- Clean, modern look
- Light mode preference
- Professional environment

**Pink Bling:**
- Fun projects
- Personal wikis
- Creative/artistic teams
- Want maximum visual impact
- Love sparkle and glamour

**Wiki Default:**
- Want perfect integration
- Use a custom DokuWiki template
- Need light/dark theme adaptation
- Want it to "just match"
- Prefer consistency over custom branding

---

## 🔄 Upgrading from Previous Versions

### From Version 4.x

Simply install v5.0.0 over your existing installation. All features and themes are backward compatible. Your selected theme will be preserved.

### From Version 3.x or Earlier

Major improvements include:
- 5 themes (vs 4 in v4.x, vs 1 in v3.x)
- Complete dialog theming
- Event highlight effects
- Wiki Default theme
- Cleaner text appearance
- 100% visual consistency

Your events, namespaces, and settings will be preserved.

---

## 🐛 Known Issues & Solutions

### Issue: Event Highlight Doesn't Show

**Solution:** Clear browser cache and hard reload (Ctrl+Shift+R / Cmd+Shift+R)

### Issue: Theme Doesn't Apply After Selection

**Solution:** 
1. Save theme in Admin → Calendar Management → Themes
2. Refresh any page with the calendar
3. Clear browser cache if needed

### Issue: Wiki Default Theme Not Matching

**Solution:** Ensure your DokuWiki template supports CSS variables. Most modern templates do. Fallback colors will be used for older templates.

### Issue: Dialog Elements Show White

**Solution:** This was fixed in v4.8.5-4.9.0. Update to v5.0.0 for complete theming.

---

## 📚 Documentation Files

This release includes comprehensive documentation:

- **README.md** - General plugin information
- **CHANGELOG.md** - Complete version history
- **RELEASE_NOTES.md** - This file
- **COLOR_SCHEME.md** - Theme color specifications
- **QUICK_REFERENCE.md** - Common tasks and syntax
- **RICH_CONTENT_GUIDE.md** - Using rich content in events
- **OUTLOOK_SYNC_SETUP.md** - Outlook synchronization setup
- **EXAMPLES_DOKUWIKI.txt** - Usage examples

---

## 🙏 Credits

### Development Journey

This plugin has evolved through careful iteration and attention to detail:

**v1.0-3.0:** Core functionality and Matrix theme  
**v4.0-4.7:** Multiple themes and sidebar improvements  
**v4.8:** Complete dialog theming breakthrough  
**v4.9:** Final checkbox and border perfection  
**v4.10:** Wiki Default theme addition  
**v4.11:** Clean text appearance refinement  
**v4.12:** Event highlight effects  
**v5.0:** Major release milestone  

### Special Features

- **Matrix Edition** branding and aesthetic
- Comprehensive theming system
- CSS variable integration
- Event highlight system
- Smart badge system
- Task management
- Rich content support
- Outlook synchronization
- Namespace organization

---

## 🚀 Future Roadmap

Potential future enhancements:

- Additional themes
- Custom theme builder
- More color customization
- Enhanced mobile experience
- Additional integrations
- Performance optimizations

---

## 📞 Support

For issues, questions, or suggestions:

1. Check the documentation files
2. Review the CHANGELOG.md
3. Check browser console for errors
4. Clear cache and try again

---

## 🎓 Best Practices

### Theme Selection
- Choose a theme that matches your wiki's purpose
- Professional Blue for business wikis
- Matrix/Purple for tech wikis
- Pink for creative/fun projects
- Wiki Default for seamless integration

### Event Organization
- Use namespaces to organize by project/team
- Use color coding for event types
- Mark important events as tasks
- Use rich content in descriptions for formatting

### Performance
- Archive old events periodically
- Use specific namespaces rather than viewing all
- Clear browser cache after plugin updates

---

## 📊 Version 5.0 Statistics

**Lines of Code:** ~8,000+  
**Themes:** 5  
**Themed Elements:** 100+  
**CSS Variables:** 15+ per theme  
**Form Fields:** 12+ all themed  
**Supported Features:** 20+  
**Documentation Files:** 10+  

---

## 🎉 Conclusion

Version 5.0 represents a major milestone in the Calendar Plugin development. With 5 beautiful themes, 100% theming coverage, and countless refinements, this is the most polished and visually consistent version ever released.

Whether you choose the classic Matrix Edition, the elegant Purple Dream, the professional Blue theme, the glamorous Pink Bling, or the adaptive Wiki Default, you'll enjoy a beautifully themed, fully functional calendar experience.

**Thank you for using the Calendar Plugin - Matrix Edition!**

Enjoy version 5.0! 🎨✨

---

**Version:** 5.0.0  
**Release Date:** February 8, 2026  
**Plugin:** calendar  
**Author:** atari911  
**License:** GPL v2 or later  

---
