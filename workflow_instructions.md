# Development Git Workflow

This document outlines the workflow and rules for managing the code repository on GitHub.

## 1. When to Commit
Commit after any significant development milestone, bug fix, or feature implementation.
* **Significant changes** include: new route controllers, model migrations, database logic updates, role permissions, notification systems, new modals/views, or refactoring services.
* **Minor cosmetic tweaks** (e.g., minor padding adjustment, changing a button hex color, fixing a typo) do **not** need immediate separate commits unless they are part of a larger UI overhaul.

## 2. Commit Message Guidelines
Commit messages must look **human-written, natural, and concise**.
* **Do NOT** use overly robotic, generic, or verbose messages (e.g., `feat: implement dual notification structure in Hierarchical Leave Request module`).
* **Do NOT** write "dumb" or empty messages (e.g., `update`, `fix`, `changes`).
* **DO** use conversational, clear, human-like summaries of what was actually achieved.

### Excellent Examples:
* `added custom confirmation popups and removed native browser alerts`
* `completed line manager approval flow and dual notifications`
* `built unified chat modal for employees and updated badges`
* `fixed department sync issue when unassigning department head`
* `integrated speech bubble unread indicators on leave dashboard`

## 3. Workflow Steps
Whenever a significant task block is finished:
1. Stage all changes: `git add .`
2. Commit with a natural, clear message: `git commit -m "human-written message"`
3. Push to main/master: `git push origin main`
