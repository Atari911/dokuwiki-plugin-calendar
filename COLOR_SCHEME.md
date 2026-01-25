# Calendar Color Scheme

## Current Theme: Green

The calendar uses a professional green color scheme for all UI elements.

## Color Palette

### Primary Colors

**Main Green** - `#008800` (RGB: 0, 136, 0)
- Primary buttons (Today, Save)
- Active states
- Header backgrounds
- Primary accents

**Dark Green** - `#388e3c`
- Button hover states
- Secondary text
- Borders (active)
- Links

**Darker Green** - `#2e7d32`
- Button pressed states
- Deep accents
- Strong borders

### Background Colors

**Light Green Background** - `#e8f5e9`
- Today's date highlight
- Selected states
- Subtle backgrounds

**Very Light Green** - `#c8e6c9`
- Hover states on today
- Light accents

**Medium Light Green** - `#81c784`
- Secondary accents
- Border highlights

**Pale Green Tint** - `#f1f8f4`
- Very subtle backgrounds
- Form field backgrounds
- Checkbox backgrounds

## Where Colors Are Used

### Buttons
- **Today Button:** Green background (#4caf50)
- **Save Button:** Green background (#4caf50)
- **Hover:** Darker green (#388e3c)
- **Active:** Darkest green (#2e7d32)

### Calendar
- **Today's Date:** Light green background (#e8f5e9)
- **Today Hover:** Lighter green (#c8e6c9)
- **Namespace Badge:** Green background (#e8f5e9), green text (#388e3c)

### Links
- **Link Color:** Green (#4caf50)
- **Link Underline:** Dotted green (#4caf50)
- **Link Hover:** Solid underline

### Forms
- **Task Checkbox Background:** Pale green (#f1f8f4)
- **Task Checkbox Border:** Green (#4caf50)
- **Task Checkbox Text:** Dark green (#388e3c)
- **Recurring Options Background:** Pale green (#f1f8f4)
- **Recurring Options Border:** Medium green (#81c784)

### Event List Widget
- **Header Background:** Green (#4caf50)
- **Header Text:** White

### Borders & Accents
- **Active Borders:** Green (#4caf50)
- **Highlight Borders:** Dark green (#388e3c)
- **Section Borders:** Green (#4caf50)

## Customization

To change the color scheme, replace these colors in `style.css`:

### To Blue Theme:
```css
#008800 → #2196f3  (Material Blue)
#388e3c → #1976d2  (Dark Blue)
#2e7d32 → #0d47a1  (Darker Blue)
#e8f5e9 → #e3f2fd  (Light Blue)
#c8e6c9 → #d1e7fd  (Lighter Blue)
#81c784 → #90caf9  (Medium Blue)
#f1f8f4 → #f0f8ff  (Pale Blue)
```

### To Red Theme:
```css
#008800 → #f44336  (Material Red)
#388e3c → #d32f2f  (Dark Red)
#2e7d32 → #b71c1c  (Darker Red)
#e8f5e9 → #ffebee  (Light Red)
#c8e6c9 → #ffcdd2  (Lighter Red)
#81c784 → #ef5350  (Medium Red)
#f1f8f4 → #fff5f5  (Pale Red)
```

### To Purple Theme:
```css
#008800 → #9c27b0  (Material Purple)
#388e3c → #7b1fa2  (Dark Purple)
#2e7d32 → #6a1b9a  (Darker Purple)
#e8f5e9 → #f3e5f5  (Light Purple)
#c8e6c9 → #e1bee7  (Lighter Purple)
#81c784 → #ba68c8  (Medium Purple)
#f1f8f4 → #faf5fb  (Pale Purple)
```

### To Orange Theme:
```css
#008800 → #ff9800  (Material Orange)
#388e3c → #f57c00  (Dark Orange)
#2e7d32 → #e65100  (Darker Orange)
#e8f5e9 → #fff3e0  (Light Orange)
#c8e6c9 → #ffe0b2  (Lighter Orange)
#81c784 → #ffb74d  (Medium Orange)
#f1f8f4 → #fffaf5  (Pale Orange)
```

## Quick Change Script

To change all colors at once, use this sed command:

```bash
# Change to blue
sed -i 's/#008800/#2196f3/g; s/#388e3c/#1976d2/g; s/#2e7d32/#0d47a1/g; s/#e8f5e9/#e3f2fd/g; s/#c8e6c9/#d1e7fd/g; s/#81c784/#90caf9/g; s/#f1f8f4/#f0f8ff/g' style.css

# Change to red
sed -i 's/#008800/#f44336/g; s/#388e3c/#d32f2f/g; s/#2e7d32/#b71c1c/g; s/#e8f5e9/#ffebee/g; s/#c8e6c9/#ffcdd2/g; s/#81c784/#ef5350/g; s/#f1f8f4/#fff5f5/g' style.css
```

## Event Colors

Note: Individual event colors (set by users) are **not** affected by the theme. Users can still choose any color for their events:
- Default event color: `#3498db` (blue)
- Users can select any color via the color picker

Only the UI elements (buttons, headers, highlights, links) use the theme colors.

---

**Current Theme:** Green  
**Version:** 3.2  
**Last Updated:** January 24, 2026
