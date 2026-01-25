# Event Panel Height Customization

The `{{eventpanel}}` tag now supports custom height values, allowing you to control how much vertical space the event list takes before it starts scrolling.

## Syntax

```
{{eventpanel height=VALUE}}
```

## Supported Units

You can use any standard CSS unit:

- **Pixels:** `300px`, `500px`, `800px`
- **Viewport Height:** `50vh`, `75vh`, `90vh`
- **Em/Rem:** `20em`, `30rem`
- **Percentage:** `50%`, `100%`

## Examples

### Small Panel (300px)
```
{{eventpanel height=300px}}
```
Good for sidebars or compact displays.

### Medium Panel (500px)
```
{{eventpanel height=500px}}
```
Balanced size for most use cases.

### Large Panel (800px)
```
{{eventpanel height=800px}}
```
Shows many events before scrolling.

### Viewport-Based (50vh)
```
{{eventpanel height=50vh}}
```
Takes 50% of viewport height (responsive to screen size).

### With Namespace
```
{{eventpanel height=400px namespace=team:projects}}
```

## Default Behavior

If no height is specified, the default is **400px**:
```
{{eventpanel}}
```
Same as:
```
{{eventpanel height=400px}}
```

## How It Works

The height parameter controls the **maximum height** of the scrollable event list area. The header and "Add Event" button remain fixed at the top, while the event list scrolls when content exceeds the specified height.

### Visual Structure:
```
┌─────────────────────────────┐
│  ‹  January 2026 Events  › │ ← Fixed header
├─────────────────────────────┤
│      [+ Add Event]          │ ← Fixed button
├─────────────────────────────┤
│  Event 1                    │ ↕
│  Event 2                    │ ↕ Scrollable area
│  Event 3                    │ ↕ (height=VALUE)
│  Event 4                    │ ↕
│  ...                        │ ↕
└─────────────────────────────┘
```

## Validation

The height value is validated to ensure it's a valid CSS unit:
- Must include a number
- Must include a unit (px, em, rem, vh, %)
- Invalid values fall back to 400px

**Valid:**
- `300px` ✓
- `50vh` ✓
- `25em` ✓
- `100%` ✓

**Invalid:**
- `300` ✗ (no unit)
- `large` ✗ (not a number)
- `300px 500px` ✗ (multiple values)

## Use Cases

### Sidebar Event Panel
```
{{eventpanel height=600px namespace=team}}
```
Perfect for a sidebar showing team events.

### Dashboard Widget
```
{{eventpanel height=300px}}
```
Compact widget for a dashboard overview.

### Full-Page Event List
```
{{eventpanel height=90vh}}
```
Uses most of the screen for maximum events visible.

### Mobile-Friendly
```
{{eventpanel height=50vh}}
```
Adapts to different screen sizes automatically.

## Tips

1. **Use vh for responsive designs:** `height=60vh` adapts to screen size
2. **Use px for fixed layouts:** `height=400px` stays consistent
3. **Consider content:** Shorter heights for fewer events, taller for more
4. **Test on mobile:** Make sure the panel is usable on small screens

## Responsive Behavior

The event panel is fully responsive and works with custom heights:
- **Desktop:** Full height as specified
- **Tablet:** Adjusts based on available space
- **Mobile:** May override very large heights for usability

---

**Version:** 3.2  
**Feature:** Customizable Event Panel Height  
**Last Updated:** January 24, 2026
