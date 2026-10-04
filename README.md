# Devil AI — v1.0.0.0

A complete Claude-style AI chat product in **100% pure PHP**. No frameworks, no Composer, no database — upload and run on any shared hosting.

## What's inside

| Page | File | What it does |
|---|---|---|
| Landing page | `index.php` | Claude-style marketing page: hero, feature grid, model showcase, footer |
| Sign in / Sign up | `login.php` | Account creation and sign-in (tabbed card, password visibility toggle) |
| Chat app | `app.php` | The full chat experience (login required) |
| Cookie policy | `cookies.php` | Complete cookie policy page |
| API | `api.php` | All backend logic (accounts, chats, models, admin) |
| Icons | `inc/icons.php` | SVG icon library — the entire UI uses SVG icons, no emoji chrome |
| Cookie consent | `inc/cookiebar.php` | Claude-style cookie banner + "Manage cookies" modal (opt-in analytics/personalization) |
| Logo | `assets/logo.svg` | SVG brand logo |

## Features

- **Isolated accounts** — email + password sign-up, bcrypt-hashed passwords, per-user chat storage (`data/chats/{user_id}/`), brute-force protection, account deletion.
- **Claude-style chat UI** — sidebar with chat history (grouped Today / Yesterday / Previous 7 days), search, rename, delete, collapsible on mobile, user menu, markdown rendering, copy & retry per message.
- **Light & dark themes** — Claude-style warm light palette + devil dark, system-preference aware, one-click toggle on every page (respects the personalization cookie choice).
- **Code preview (mini artifacts)** — HTML/CSS/JS code blocks get a "Preview" button that renders them live in a sandboxed iframe (with console capture for JS), plus "Open in new tab".
- **Multiple models** — pick **Devil Flash**, **Devil Pro**, **Devil Ultra** or **Demo Mode** from the chat box. Public visitors only see the devil names — which real engine powers each model is a server-side secret configurable in Admin settings.
- **Automatic fallback** — if any engine fails (bad key, rate limit, downtime), Devil AI automatically retries on the free Prexzy engine. The chat never dies; the offline brain is the last resort.
- **Cookie consent system** — first-visit banner (Accept all / Manage), per-category toggles (Essential locked, Analytics & Personalization opt-in), choices stored in localStorage + a 1-year cookie, full policy page, footer link to reopen settings.
- **Identity protection** — the bot always answers as **Devil AI** and never reveals the underlying models/providers (hardened persona, scripted identity answer). UI, API and page source contain zero provider names.
- **Public-safety hardening** — admin password lock on the settings panel, per-user message rate limit, per-IP auth attempt limit, XSS-safe rendering, HTTP-only sessions.

## Requirements

- PHP 7.4+ (cURL recommended, `allow_url_fopen` as fallback)
- Writable `data/` folder (chmod 755)
- That's it.

## Quick start

1. Upload everything to a folder on your host (e.g. `/www/yourdomain.com/devil-ai/`).
2. Make sure `data/` is writable (it already contains a protective `.htaccess`).
3. Open the site — create an account — chat.
4. **Set the admin password**: sign in, open the user menu (bottom-left) → **Admin settings**. Without a password the panel is open to everyone.

### Admin settings (owner only)

Password-protected panel where you configure:

- Which engine powers **Devil Flash / Pro / Ultra** (engine names are visible to the admin only)
- Site API key + model for the Gemini engine
- Messages per user per hour, max chats per user
- Change the admin password

## File storage

```
data/
├── users.json          # accounts (password hashes only)
├── config.json         # admin-managed runtime config (created on first save)
├── rl.json             # per-user message rate limiting
├── authrl.json         # per-IP auth attempt limiting
└── chats/<user_id>/    # one JSON file per conversation (isolated per user)
```

Everything under `data/` is blocked from direct browser access via `.htaccess`.

## API overview

Public: `bootstrap`, `register`, `login`, `logout`, `me`, `settings` (GET — public-safe, exposes nothing).
User: `chats`, `chat_load`, `chat_send`, `chat_delete`, `chat_rename`, `account_delete`.
Admin: `auth`, `settings` (POST), `test`.

All responses are JSON. All error messages are in English.

## Privacy notes

- Analytics and personalization cookies are **off by default** and opt-in only.
- No third-party scripts, no ad networks, no trackers.
- Passwords are stored as one-way hashes; chats are isolated per account.

---

Devil AI v1.0.0.0 • 100% PHP • Sinfully smart, surprisingly helpful.
