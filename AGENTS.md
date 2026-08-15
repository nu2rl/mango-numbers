# Mango Numbers - AI Agent Mandatory Rules & Guidelines

This repository (`mango-numbers`) requires all AI Coding Assistants / Agents (Antigravity, Codex, Claude Code, Cursor, Copilot, etc.) to strictly follow these mandatory workflows:

---

## 1. 🐙 Automatic Git Commit & Push Rule
- **Mandatory Action**: After making ANY code edit, bug fix, feature implementation, or UI change, you MUST automatically commit and push the changes to GitHub (`main` branch):
  ```bash
  git add . && git commit -m "<descriptive message>" && git push origin main
  ```
- **Commit Messages**: Write meaningful, human-readable commit messages.
- **Security**: Never commit sensitive secrets or credentials.

---

## 2. 🎨 Mandatory Skill Usage Guidelines
Whenever working on UI, UX, frontend design, CSS/styling, or refactoring code in this project, you MUST reference and actively apply the installed project skills located in `.agents/skills/`:

- **`anti-ui-slop`** (`.agents/skills/anti-ui-slop/SKILL.md`):
  Prevent generic, boring, or overly flashy "AI-slop" design. Enforce product-specific branding, intentional contrast, clean typography, and a hard finish gate.

- **`ui-design`** (`.agents/skills/ui-design/SKILL.md`):
  Design polished, intentional web interfaces with clean spatial harmony, visual hierarchy, consistent color tokens, and smooth micro-interactions.

- **`web-design-guidelines`** (`.agents/skills/web-design-guidelines/SKILL.md`):
  Ensure accessibility, semantic HTML, responsive standards (desktop, tablet, mobile), and UX best practices.

- **`tailwind-4-docs`** (`.agents/skills/tailwind-4-docs/SKILL.md`):
  Reference official Tailwind CSS v4 documentation, utility selections, and migration gotchas when working with Tailwind styles.

- **`ponytail`** (`.agents/skills/ponytail/SKILL.md`):
  Keep backend & architecture simple, efficient, and minimal. Avoid unnecessary bloat, redundant code, or over-engineering.

---

## 3. ⚡ Core Project Directives
- **Branding**: Preserve the Mango orange identity (`#FF8A1F` / `#FF5E36`) as the primary brand accent.
- **Backend & Safety**: Preserve existing working PHP logic, database contracts, and routes while upgrading UI/UX.
