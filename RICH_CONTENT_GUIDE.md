# Rich Content in Event Descriptions

The calendar plugin now supports rich content in event descriptions, including images, links, formatting, and unlimited length.

## Supported Content Types

### 1. **Images**

#### DokuWiki Syntax:
```
{{image.jpg}}
{{image.jpg|Alt text description}}
{{namespace:image.png|Product screenshot}}
```

#### External Images:
```
{{https://example.com/image.jpg}}
{{https://example.com/photo.png|Photo from website}}
```

#### Examples:
- Internal: `{{wiki:logo.png|Company logo}}`
- External: `{{https://picsum.photos/200|Random image}}`

**Result:** Images display inline within the event description, automatically sized to fit.

---

### 2. **Links**

#### DokuWiki Link Syntax:
```
[[wiki:page]]
[[wiki:page|Link text]]
[[namespace:subpage|Click here]]
```

#### External Links (DokuWiki):
```
[[https://example.com]]
[[https://example.com|Visit website]]
```

#### Markdown-Style Links:
```
[Link text](https://example.com)
[Documentation](https://docs.example.com)
```

#### Plain URLs:
```
https://example.com
http://wiki.example.com/page
```

**Result:** All become clickable links. External links open in new tabs.

---

### 3. **Text Formatting**

#### Bold:
```
**bold text**
<strong>bold text</strong>
<b>bold text</b>
```

#### Italic:
```
//italic text//
<em>italic text</em>
<i>italic text</i>
```

#### Code:
```
<code>inline code</code>
```

---

### 4. **Line Breaks**

Simply press Enter for a new line:
```
Line 1
Line 2
Line 3
```

---

## Complete Examples

### Example 1: Meeting with Agenda
```
**Agenda:**
1. Review Q4 results {{reports:q4.pdf|Q4 Report}}
2. Discuss new project [[projects:alpha|Project Alpha]]
3. Team updates

**Materials:**
{{https://example.com/slides.pdf|Presentation slides}}

**Zoom:** https://zoom.us/j/123456789
```

### Example 2: Product Launch
```
**Product Launch Event**

{{products:banner.jpg|Launch banner}}

Launch details: [[events:launch2026|Event page]]

Registration: https://events.example.com/register

**Key Points:**
- New features
- Demo at 2:00 PM
- Q&A session

Contact: [john@example.com](mailto:john@example.com)
```

### Example 3: Task with Screenshots
```
**Design Review Task**

Review the new dashboard mockups:

{{designs:dashboard-v1.png|Dashboard version 1}}
{{designs:dashboard-v2.png|Dashboard version 2}}

Feedback form: [[forms:design-feedback]]

Due: **January 30, 2026**
```

### Example 4: Travel Planning
```
**Business Trip - New York**

**Flight:**
{{https://airline.com/qr-code.png|Boarding pass QR code}}
Confirmation: [View on airline site](https://airline.com/booking/ABC123)

**Hotel:**
Hilton Midtown - https://hilton.com/nyc

**Itinerary:**
[[travel:nyc-jan2026|Full itinerary]]

**Documents:**
{{travel:passport-copy.pdf|Passport copy}}
{{travel:visa.pdf|Visa}}
```

### Example 5: Event with External Content
```
**Conference Attendance**

**Event website:** https://conference2026.com

**Schedule:**
{{https://conference2026.com/schedule.jpg|Conference schedule}}

**My sessions:**
- 9:00 AM - Keynote
- 11:00 AM - Workshop [[conferences:workshop-notes|My notes]]
- 2:00 PM - Panel discussion

**Networking:** [LinkedIn group](https://linkedin.com/groups/conference2026)

**Hotel:** {{https://maps.google.com/image.png|Map to hotel}}
```

---

## Practical Use Cases

### 1. **Project Management**
- Link to project docs: `[[projects:alpha|Project Alpha]]`
- Attach design files: `{{designs:mockup.png}}`
- Reference tickets: `See [JIRA-123](https://jira.example.com/JIRA-123)`

### 2. **Event Planning**
- Show venue photos: `{{venues:hall-a.jpg|Main hall}}`
- Link to RSVP: `https://eventbrite.com/event/123`
- Display floor plans: `{{events:floorplan.pdf}}`

### 3. **Travel & Logistics**
- Boarding passes: `{{travel:boarding-pass.png}}`
- Confirmation emails: Link PDFs from email attachments
- Maps and directions: External Google Maps images

### 4. **Documentation & Training**
- Procedure documents: `[[procedures:onboarding]]`
- Training videos: `[Watch training](https://youtube.com/watch?v=...)`
- Reference guides: `{{guides:quickstart.pdf}}`

### 5. **Sales & Client Meetings**
- Client logos: `{{clients:acme-logo.png}}`
- Product sheets: `{{sales:product-overview.pdf}}`
- Proposal links: `[[proposals:acme-2026]]`

---

## Tips for Best Results

### Image Optimization
- **Resize large images** before uploading (max 800px wide recommended)
- Use **PNG** for logos and diagrams
- Use **JPG** for photos
- Keep file sizes under 500KB for fast loading

### Link Best Practices
- Use **descriptive link text**: `[View report](url)` not `[Click here](url)`
- For external sites, full URLs work: `https://example.com`
- Internal links use DokuWiki syntax: `[[wiki:page]]`

### Content Organization
- **Keep descriptions concise** for quick scanning
- **Use bold** for key information
- **Use line breaks** to separate sections
- **One image** per event is usually enough

### Accessibility
- **Always add alt text** to images: `{{image.jpg|Description}}`
- **Use descriptive link text** instead of bare URLs
- **Format text clearly** with bold for emphasis

---

## Technical Details

### Supported Image Formats
- PNG, JPG, JPEG, GIF, WebP
- SVG (if server allows)
- Internal DokuWiki media files
- External image URLs (https://)

### Supported Link Formats
- DokuWiki internal: `[[page]]`, `[[page|text]]`
- DokuWiki external: `[[https://url]]`, `[[https://url|text]]`
- Markdown: `[text](url)`
- Plain URLs: `https://example.com`

### HTML Support
The following HTML tags are supported in descriptions:
- `<strong>`, `<b>` - Bold text
- `<em>`, `<i>` - Italic text
- `<u>` - Underline
- `<code>` - Inline code
- `<br>` - Line break (or just use Enter)
- `<img>` - Images (via syntax conversion)
- `<a>` - Links (via syntax conversion)

### Security
- All user input is sanitized
- External links open in new tabs with `rel="noopener noreferrer"`
- Only safe HTML tags are allowed
- XSS protection enabled

---

## Limitations

### What's NOT Supported
- ❌ Embedded videos (use links instead)
- ❌ JavaScript or active content
- ❌ iframes
- ❌ Forms
- ❌ Tables (keep descriptions simple)
- ❌ Complex DokuWiki plugins

### Workarounds
- **Videos:** Link to YouTube/Vimeo instead of embedding
- **Complex content:** Create a wiki page and link to it
- **Tables:** Screenshot the table as an image

---

## Migration from Plain Text

If you have existing events with plain text descriptions:
1. **They will continue to work** - no changes needed
2. **Add images** by using `{{image.jpg}}` syntax
3. **Add links** by using `[[page]]` or `[text](url)` syntax
4. **No data loss** - all existing content preserved

---

## Examples Gallery

### Before (Plain Text):
```
Team meeting to discuss Q4 results.
See the report at wiki:reports:q4
Contact John for details.
```

### After (Rich Content):
```
**Team meeting** to discuss Q4 results.

📊 Report: [[wiki:reports:q4|Q4 Results]]
📧 Contact: [John Smith](mailto:john@example.com)
🔗 Zoom: https://zoom.us/j/123456789
```

### With Image:
```
**New Product Launch**

{{products:hero-image.jpg|Product hero image}}

Join us for the unveiling of our latest innovation!

Details: [[events:product-launch-2026]]
RSVP: https://events.example.com/rsvp
```

---

## Quick Reference

| Content Type | Syntax | Example |
|--------------|--------|---------|
| Internal image | `{{file.jpg}}` | `{{logo.png|Logo}}` |
| External image | `{{https://...}}` | `{{https://example.com/pic.jpg}}` |
| Internal link | `[[page]]` | `[[projects:alpha|Project]]` |
| External link | `[[https://...]]` | `[[https://example.com|Site]]` |
| Markdown link | `[text](url)` | `[Docs](https://docs.example.com)` |
| Plain URL | `https://...` | `https://example.com` |
| Bold | `**text**` | `**Important**` |
| Italic | `//text//` | `//Note//` |
| Code | `<code>text</code>` | `<code>API_KEY</code>` |

---

**Version:** 3.0 (Compact Edition with Rich Content)  
**Last Updated:** January 24, 2026
