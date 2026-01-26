# 🎉 DokuWiki Calendar Plugin v3.3 - Quick Reference

**A sleek, modern calendar system with multi-namespace support, recurring events, and smart filtering.**

---

## 🚀 Widgets

### **{{calendar}}** - Full Interactive Calendar
**The main calendar with month grid + event panel**

### **{{eventpanel}}** - Event Panel Only
**Just the scrolling event list, no calendar grid**

### **{{eventlist}}** - Simple Event List
**Lightweight 2-line display widget**

---

## ⚙️ Parameters

### **namespace**
- `namespace=work` - Single namespace
- `namespace=work;personal` - Multiple namespaces (semicolon-separated)
- `namespace=*` - All namespaces (wildcard)
- `namespace=projects:*` - Wildcard within namespace

### **range** *(eventlist only)*
- `range=day` - Today only
- `range=week` - Next 7 days
- `range=month` - Current month

### **daterange** *(eventlist only)*
- `daterange=2026-01-01:2026-01-31` - Custom date range

### **date** *(eventlist only)*
- `date=2026-01-25` - Specific single date

### **height** *(eventpanel only)*
- `height=400px` - Custom panel height

### **sidebar** *(eventlist only)*
- Shows today through +1 month
- Hides completed tasks
- Highlights today/tomorrow

### **today** *(eventlist only)*
- Shows today's events only
- Same as `range=day`

---

## 🎨 Event Features

### **Multi-day Events**
- Spans across months automatically
- Displays as continuous bar on calendar
- Shows original start date in all months

### **Recurring Events**
- Daily, Weekly, Monthly options
- End date optional
- Generates all occurrences on save

### **Tasks (Checkboxes)**
- Toggle completion status
- Auto-filtered in sidebar/day/week modes
- Persistent state

### **Time Picker**
- 15-minute intervals
- 12-hour format display
- 24-hour storage

### **Colors**
- Blue, Green, Red, Orange, Purple, Pink, Teal

---

## 🔍 Smart Features

### **Namespace Filtering**
- Click any namespace badge to filter
- Badge appears in event panel header with ✕
- New events auto-save to filtered namespace
- Click ✕ or badge again to clear

### **Date Defaults**
- Start date: 1st of displayed month
- End date picker: Opens on displayed month
- Time: 15-min increments (00, 15, 30, 45)

### **Auto-Highlighting**
- **TODAY badge** - Purple, shown on current events
- **Tomorrow** - Light yellow/green tint
- **Past events** - Collapsed, click to expand

### **Multi-Month Spanning**
- Events stored in each month they appear
- Proper date display across boundaries
- Single delete removes from all months

---

## 🎯 Quick Syntax

```dokuwiki
{{calendar}}
{{calendar namespace=work}}
{{calendar namespace=*}}
{{calendar namespace=projects:website;work}}

{{eventpanel height=500px}}
{{eventpanel namespace=personal}}

{{eventlist}}
{{eventlist range=day}}
{{eventlist range=week}}
{{eventlist range=month}}
{{eventlist range=week namespace=work}}
{{eventlist sidebar}}
{{eventlist today}}
{{eventlist date=2026-02-15}}
{{eventlist daterange=2026-02-01:2026-02-28}}
```

---

## 📁 Data Storage

**Location:** `/data/meta/[namespace]/calendar/YYYY-MM.json`

**Structure:**
```json
{
  "2026-01-25": [
    {
      "id": "abc123",
      "title": "Meeting",
      "time": "14:00",
      "endDate": "2026-01-26",
      "namespace": "work",
      "color": "#3498db",
      "isTask": false,
      "completed": false,
      "description": "**Bold** and //italic// supported"
    }
  ]
}
```

---

## 🎨 UI Colors

- **Primary:** Green (#008800)
- **TODAY badge:** Purple (#c084fc)
- **Tomorrow:** Light yellow (#fffbeb)
- **Past events:** Muted gray
- **Namespace badges:** Green background, white text

---

## 🔧 Debug Tools

**JSON Corruption Fixer:**
```bash
php fix_corrupted_json.php /path/to/dokuwiki
```

**Clear Cache:**
```bash
rm -rf /var/www/html/dokuwiki/data/cache/*
```

---

## ✨ Pro Tips

1. Use `namespace=*` for dashboard view, then filter by clicking badges
2. `range=day` perfect for sidebar widgets
3. `range=week` great for weekly planning pages
4. Multi-day events: Just set end date, it handles the rest
5. Recurring events: Set end date or leave blank for infinite
6. DokuWiki formatting works in descriptions: **bold**, //italic//, [[links]]

---

**Version:** 3.3  
**Author:** Built with Claude  
**License:** Keep it free, keep it open 🚀  

🎊 **Happy Scheduling!** 🎊
