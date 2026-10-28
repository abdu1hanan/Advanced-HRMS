# Master Design System Reference

## Overview
This document establishes the unified, global design language for the HRMS application overhaul on the `revamp` branch. Every page, component, and utility must adhere to these tokens and patterns.

---

## 1. Color Palette & CSS Variables

```css
:root {
    /* Brand Colors */
    --primary: #6366f1;
    --primary-hover: #4f46e5;
    --primary-light: rgba(99, 102, 241, 0.1);
    
    /* Neutral / Light Background Theme */
    --bg-main: #f8fafc;
    --bg-card: #ffffff;
    --bg-glass: rgba(255, 255, 255, 0.85);
    
    /* Typography Colors */
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --text-muted: #94a3b8;
    
    /* Accent & Status Colors */
    --success: #10b981;
    --success-bg: rgba(16, 185, 129, 0.1);
    --warning: #f59e0b;
    --warning-bg: rgba(245, 158, 11, 0.1);
    --danger: #ef4444;
    --danger-bg: rgba(239, 68, 68, 0.1);
    --info: #3b82f6;
    --info-bg: rgba(59, 130, 246, 0.1);
    
    /* Borders & Shadows */
    --border-color: #e2e8f0;
    --border-radius-sm: 8px;
    --border-radius-md: 14px;
    --border-radius-lg: 20px;
    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.08);
}
```

---

## 2. Global Component Standards

### Cards & Containers
- Clean white cards (`#ffffff`) with subtle borders (`1px solid var(--border-color)`), rounded corners (`border-radius: 14px` or `20px`), and micro-elevation shadows (`var(--shadow-md)`).

### Typography
- Primary font: Modern sans-serif (Inter / Outfit / System stack).
- Section titles: `font-size: 1.25rem`, `font-weight: 700`, `color: var(--text-primary)`.
- Subtitles & metadata: `font-size: 0.875rem`, `color: var(--text-secondary)`.

### Buttons & Inputs
- Action buttons: Curved pills (`border-radius: 9999px`) or rounded rectangles (`border-radius: 10px`) with subtle hover scale/lighten transitions.
- Inputs & Selects: Modern inset borders, clear focus rings (`ring-2 ring-primary/20`), matching icon prefixes/suffixes.

### Tables & Data Grids
- Clean header rows (`bg-slate-50`, uppercase tracking).
- Floating row cards or crisp border separators.
- Status badges: Dynamic pill tags with soft background tint and dark text.

---

## 3. Extending the System
When introducing new design elements from screenshot references:
1. Update CSS variables in global stylesheet first.
2. Define component classes in global CSS (or dedicated component stylesheets) rather than page-specific inline styles.
3. Reuse existing tokens across all subsequent page redesigns.
