# Responsive Design Guide

The calendar plugin is now fully responsive and adapts to all screen sizes from large desktop monitors to small mobile phones.

## Screen Size Breakpoints

### Desktop (1200px+)
- **Calendar:** Flexible width, up to 1200px
- **Left Panel:** Flexible (60-70% of width)
- **Right Panel:** 300-400px
- **Height:** 600px, max 90vh

### Large Tablet (769px - 1024px)
- **Calendar:** Full width container
- **Left Panel:** 60% width, min 400px
- **Right Panel:** 40% width, min 250px
- **Layout:** Side-by-side

### Mobile/Small Tablet (max 768px)
- **Layout:** Stacked vertically
- **Calendar:** 100% width on top
- **Event List:** 100% width below, max 400px height
- **Height:** Auto (no fixed height)

### Small Mobile (max 600px)
- **Font Size:** Reduced to 11px
- **Calendar Cells:** Smaller (45px height)
- **Compact headers:** Reduced padding

### Tiny Mobile (max 480px)
- **Dialog:** Full screen (no rounded corners)
- **Buttons:** Full width
- **Maximum space utilization**

## Responsive Features

### Calendar Container
```css
width: 100%
max-width: 1200px
min-width: 320px
height: 600px (desktop)
max-height: 90vh
```

**Benefits:**
- Never cuts off on any screen
- Scrolls if needed
- Scales proportionally

### Event Dialog
**Desktop:**
- 450px wide
- Centered on screen
- Max 90vh height
- Scrollable form area

**Mobile:**
- Full width minus 20px padding
- Full screen on very small devices
- Header fixed at top
- Actions fixed at bottom
- Form scrolls between them

**Key Features:**
✅ Always shows Save button (scrollable form)
✅ Never cuts off content
✅ Touch-friendly on mobile

### Day Popup
Similar responsive behavior:
- Max width 450px on desktop
- Full width on mobile
- Padding adjusts by screen size
- Scrollable event list

### Calendar Grid
**Desktop:**
- 58px cell height
- Clear day numbers
- Visible time bars

**Mobile:**
- 45px cell height (600px screens)
- 35px minimum height
- Compact but readable

## Usage by Device

### Desktop Computer (1920x1080)
```
┌──────────────────────────────────────────────┐
│  Calendar (flexible)    │  Events (300-400px)│
│                         │                    │
│  [Large cells]          │  [Event list]      │
│                         │                    │
│  Full calendar visible  │  Scrollable list   │
└──────────────────────────────────────────────┘
```

### Laptop (1366x768)
```
┌────────────────────────────────────────┐
│  Calendar (flex)  │  Events (300px)    │
│                   │                    │
│  [Medium cells]   │  [Event list]      │
│                   │                    │
└────────────────────────────────────────┘
```

### Tablet Portrait (768x1024)
```
┌────────────────────┐
│  Calendar          │
│  [Cells visible]   │
│────────────────────│
│  Events            │
│  [Scrollable list] │
│  Max 400px height  │
└────────────────────┘
```

### Phone (375x667)
```
┌────────────────┐
│  Calendar      │
│  [Small cells] │
│────────────────│
│  Events        │
│  [Short list]  │
└────────────────┘
```

## Dialog Responsiveness

### Desktop Dialog
```
┌─────────────────────────────────┐
│ Add Event                    × │ ← Fixed header
├─────────────────────────────────┤
│ ┌─ Form (Scrollable) ─────────┐│
│ │ 📋 This is a task           ││
│ │                             ││
│ │ 📅 Start Date  📅 End Date  ││
│ │ [2026-01-25]  [2026-01-26]  ││
│ │                             ││
│ │ 🕐 Time                      ││
│ │ [14:00]                     ││
│ │                             ││
│ │ 📝 Title                     ││
│ │ [Event title...]            ││
│ │                             ││
│ │ 📄 Description               ││
│ │ [......................]     ││
│ │                             ││
│ │ 🎨 Color                     ││
│ │ [🔵] Choose color           ││
│ └─────────────────────────────┘│
├─────────────────────────────────┤
│              [Cancel] [💾 Save] │ ← Fixed footer
└─────────────────────────────────┘
```

### Mobile Dialog (Full Screen)
```
┌─────────────────┐
│ Add Event    × │ ← Fixed
├─────────────────┤
│ [Form content] │
│ (scrollable)   │ ← Scrolls here
│                │
│ ⬆️ Scroll up    │
│ ⬇️ Scroll down  │
│                │
├─────────────────┤
│ [Cancel][Save] │ ← Fixed
└─────────────────┘
```

## Scrolling Behavior

### Calendar
- **Desktop:** Fixed height, event list scrolls
- **Mobile:** Full height visible, no scroll needed

### Event List
- **Always scrollable** when events exceed visible area
- Thin 6px scrollbar
- Smooth scrolling

### Dialog Form
- **Header:** Fixed at top
- **Form fields:** Scroll vertically
- **Action buttons:** Fixed at bottom
- **Max height:** 100vh - header - footer

### Day Popup
- **Event list scrolls** if many events
- **Add button** always visible at bottom

## Touch Optimization

### Mobile-Specific Enhancements

**Tap Targets:**
- Minimum 44x44px (Apple guideline)
- Buttons have adequate spacing
- Calendar cells easy to tap

**Gestures:**
- Tap calendar cell → Day popup
- Tap event → Edit
- Swipe scroll → Event list
- Tap outside → Close dialogs

**Typography:**
- Readable at arm's length
- Minimum 10px font on mobile
- Good contrast ratios

## Testing on Different Screens

### Desktop (1920x1080)
```bash
# Should see:
✓ Full calendar grid (7 days x 5-6 weeks)
✓ Event panel on right (300-400px)
✓ All content visible without scroll
✓ Dialog centered, 450px wide
```

### Laptop (1366x768)
```bash
# Should see:
✓ Full calendar grid
✓ Event panel (300px)
✓ Slight vertical scroll if many events
✓ Dialog fits comfortably
```

### Tablet (768x1024)
```bash
# Should see:
✓ Calendar stacked on top
✓ Event list below (max 400px)
✓ Both fully visible
✓ Dialog 90% width
```

### Phone (375x667)
```bash
# Should see:
✓ Compact calendar on top
✓ Short event list below
✓ Everything readable
✓ Dialog full screen
✓ Save button always visible
```

## Common Issues & Solutions

### Issue: Calendar cut off on laptop
**Solution:** Container now has `max-height: 90vh` - fits any screen

### Issue: Can't see Save button on mobile
**Solution:** Form scrolls, buttons fixed at bottom

### Issue: Event list too tall on tablet
**Solution:** Max height 400px with scroll

### Issue: Dialog too wide on phone
**Solution:** Full width on screens < 480px

### Issue: Text too small on mobile
**Solution:** Font size reduces gracefully to 11px minimum

## CSS Media Queries Used

```css
/* Large tablets and up */
@media (min-width: 769px) and (max-width: 1024px)

/* Tablets and down */
@media (max-width: 768px)

/* Small phones */
@media (max-width: 600px)

/* Tiny phones */
@media (max-width: 480px)

/* Short screens (landscape phones) */
@media (max-height: 600px)
@media (max-height: 500px)
```

## Browser Compatibility

### Desktop Browsers
- ✅ Chrome/Edge 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Opera 76+

### Mobile Browsers
- ✅ iOS Safari 14+
- ✅ Chrome Mobile
- ✅ Samsung Internet
- ✅ Firefox Mobile

### Features Used
- Flexbox (full support)
- CSS Grid (form layouts)
- Media queries (universal)
- calc() for responsive heights
- vh/vw units (widely supported)

## Performance

### Optimizations
- **No JavaScript resize handlers** (pure CSS)
- **Hardware acceleration** (transform animations)
- **Efficient reflows** (fixed headers/footers)
- **Touch scrolling** optimized

### Load Times
- Small CSS file (~25KB compressed)
- No external dependencies
- Instant responsive adaptation

## Accessibility

### Screen Readers
- Proper semantic HTML
- ARIA labels where needed
- Keyboard navigable

### High Contrast
- Good color contrast ratios
- Works in dark mode
- Clear focus indicators

### Zoom
- Supports 200% browser zoom
- Text remains readable
- Layout doesn't break

## Testing Checklist

- [ ] Desktop 1920x1080 - Full view
- [ ] Laptop 1366x768 - Comfortable fit
- [ ] Tablet 1024x768 - Stacked layout
- [ ] iPad 768x1024 - Portrait mode
- [ ] Phone 414x896 - iPhone view
- [ ] Phone 375x667 - Compact view
- [ ] Landscape 667x375 - Horizontal
- [ ] Dialog scrolls on all sizes
- [ ] Save button always visible
- [ ] Calendar never cuts off
- [ ] Event list scrollable
- [ ] Touch targets adequate

## Future Enhancements

Potential improvements:
- Swipe gestures for month navigation
- Pull-to-refresh event list
- Responsive font scaling
- Orientation change handling
- PWA support for offline use

---

**Version:** 3.0 Responsive Edition  
**Last Updated:** January 24, 2026  
**Tested on:** Desktop, Tablet, Mobile devices
