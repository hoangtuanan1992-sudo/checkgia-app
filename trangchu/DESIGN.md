```markdown
# Design System Document: Precision Editorial

## 1. Overview & Creative North Star: "The Financial Sentinel"
The design system is built to transform raw pricing data into high-stakes intelligence. Moving away from the cluttered, "dashboard-heavy" aesthetics of typical SaaS tools, this system adopts a **Creative North Star of "The Financial Sentinel."** 

The aesthetic is characterized by **Editorial Precision**: high-contrast typography, expansive white space, and a refusal to use traditional containment lines. We leverage intentional asymmetry and sophisticated tonal layering to create a UI that feels less like a software tool and more like a premium financial journal. By breaking the grid with overlapping elements and shifting the depth through color rather than borders, the interface achieves a sense of "living data"—fast, accurate, and authoritative.

---

### 2. Colors & Surface Philosophy
The palette avoids the "standard blue" trap by utilizing a deep, architectural Navy (`primary`) balanced against a warm, paper-like foundation (`background`).

#### The "No-Line" Rule
**Explicit Instruction:** Designers are prohibited from using 1px solid borders for sectioning or containment. Boundaries must be defined solely through background color shifts. 
*   *Implementation:* A `surface-container-low` section sitting on a `surface` background provides all the separation needed. If the eye can perceive the shift, the line is redundant.

#### Surface Hierarchy & Nesting
Treat the UI as a physical stack of fine paper. 
*   **Base:** `surface` (#fcf9f5) – The canvas.
*   **Secondary Content:** `surface-container` (#f0ede9) – To group secondary metrics.
*   **Active/Elevated Elements:** `surface-container-highest` (#e5e2de) – For focused analysis panels.

#### Glass & Gradient Signature
To elevate the "Tech-Forward" requirement, main CTAs and Hero sections should utilize a **Signature Texture**:
*   **Primary Action Gradient:** Linear transition from `primary` (#004bca) to `primary_container` (#0061ff) at a 135-degree angle.
*   **Glassmorphism:** For floating tooltips or navigation overlays, use `surface_container_lowest` at 80% opacity with a `24px` backdrop-blur. This creates a "frosted glass" effect that keeps the data visible but secondary.

---

### 3. Typography: The Narrative Scale
The system uses a dual-type approach to balance high-end editorial feel with technical data clarity.

*   **Display & Headline (Inter):** High-impact, tight tracking (-2%). These are used for big price movements and market overviews.
*   **Labels (Space Grotesk):** Monospaced-leaning sans-serif used for technical data, price points, and status badges. This font choice signals "data accuracy" and "automation."

| Level | Token | Font | Size | Weight |
| :--- | :--- | :--- | :--- | :--- |
| **Hero Price** | `display-lg` | Inter | 3.5rem | 700 |
| **Section Head** | `headline-sm` | Inter | 1.5rem | 600 |
| **Technical Label** | `label-md` | Space Grotesk | 0.75rem | 500 |
| **Data Body** | `body-md` | Inter | 0.875rem | 400 |

---

### 4. Elevation & Depth: Tonal Layering
Traditional shadows are too "heavy" for a system meant to feel fast. We use **Tonal Layering** to convey importance.

*   **The Layering Principle:** Place a `surface-container-lowest` card on a `surface-container-low` background to create a soft, natural lift.
*   **Ambient Shadows:** For critical floating modals, use an extra-diffused shadow: `box-shadow: 0 20px 40px rgba(28, 28, 26, 0.05)`. Note the use of `on_surface` (#1c1c1a) as the shadow tint rather than pure black.
*   **The "Ghost Border" Fallback:** If a border is required for accessibility (e.g., in a high-density data table), use `outline_variant` at **15% opacity**.

---

### 5. Primitive Components

#### Buttons
*   **Primary:** Gradient fill (`primary` to `primary_container`), white text, `md` (0.375rem) roundedness.
*   **Secondary:** No fill, `surface_variant` background, `on_surface` text.
*   **Ghost:** No fill, `on_surface` text, no border. Interaction is shown through a slight background shift to `surface_container_high`.

#### Data Cards & Lists
*   **Rule:** Forbid the use of divider lines.
*   **Separation:** Use vertical white space (32px - 48px) or a background shift to `surface_container_low`.
*   **Interactivity:** On hover, a card should shift from `surface` to `surface_container_lowest` with an ambient shadow.

#### Status Badges (The Price Indicators)
*   **Success (Price Drop):** `tertiary_container` background with `on_tertiary_fixed` text.
*   **Alert (Price Hike):** `secondary_container` background with `on_secondary_fixed` text.
*   **Shape:** Use `full` (9999px) roundedness for a pill shape to contrast against the sharp editorial grid.

#### Price Charts
*   **The "Sentinel" Line:** Charts should use a 2.5pt stroke weight.
*   **Fills:** Use a subtle vertical gradient from `tertiary` to transparent for "growth" areas to provide depth without obscuring grid lines.

---

### 6. Do’s and Don’ts

#### Do
*   **Use Asymmetry:** Place high-level summaries offset to the left with large `display-lg` numbers to create a modern, editorial rhythm.
*   **Embrace Space:** Give data room to breathe. Use at least 24px of padding inside any container.
*   **Color as Information:** Use `secondary` (#b41f00) only for critical alerts or negative price movements.

#### Don’t
*   **Don't Use Boxes:** Avoid putting every chart inside a bordered box. Let the chart breathe directly on a `surface-container-low` background.
*   **Don't Use Pure Black:** Always use `on_surface` (#1c1c1a) for text to maintain a premium, slightly softer contrast.
*   **Don't Over-Animate:** Transitions should be fast (200ms) and use "Ease-Out" to mimic the speed and accuracy of the system.