# Devil AI — v1.0.0.0

A complete Claude-style AI chat product for shared hosting. No frameworks, no Composer, no database — upload and run.

## What's inside

| Page | File | What it does |
|---|---|---|
| Landing page | `index.php` | Claude-style marketing page: hero, feature grid, model showcase, footer |
| Sign in | `login.php` | **Passwordless** login: enter email → 6-digit code or one-click magic link |
| Chat app | `/chat` (served by `app.php`) | The full chat experience (login required); also `/agent`, `/battle`, `/side-by-side` |
| Account settings | `settings.php` | Claude-style account settings page: profile, appearance, cookie controls, export chats, delete account |
| Admin panel | `admin.php` | Standalone, password-locked settings page for the owner (not linked anywhere public) |
| Cookie policy | `cookies.php` | Complete cookie policy page |
| API | `api.php` | All backend logic (accounts, chats, models, admin) |
| Icons | `inc/icons.php` | SVG icon library — the entire UI uses SVG icons, no emoji chrome |
| Cookie consent | `inc/cookiebar.php` | Claude-style cookie banner + "Manage cookies" modal (opt-in analytics/personalization) |
| Logo | `assets/logo.svg` | SVG brand logo |

## Features

- **Passwordless accounts** — no passwords anywhere. Enter an email → receive a 6-digit code + a one-click magic link → you're in. Accounts are auto-created on first login. Codes expire in 10 minutes, allow max 5 wrong tries, 60-second resend cooldown, per-email + per-IP rate limits.
- **Isolated accounts** — per-user chat storage (`data/chats/{user_id}/`), account deletion (confirmed by an email code), brute-force protection.
- **Image understanding (vision)** — attach an image (paperclip, paste, or drop a file), or send it alone with no text: Devil AI looks at it and answers. Client-side downscaling keeps chats light; strict server-side validation (magic bytes, MIME allowlist, size cap); thumbnails in history + full-screen viewer.
- **MPA-style navigation** — separate pages for landing, login, chat, account settings, cookie policy and admin; chat URLs can open directly with `/chat?chat=...`.
- **Claude-style chat UI** — sidebar with chat history (grouped Today / Yesterday / Previous 7 days), search, rename, delete, collapsible on mobile, user menu, markdown rendering, copy & retry per message.
- **Light & dark themes** — Claude-style warm light palette + devil dark, system-preference aware, one-click toggle on every page (respects the personalization cookie choice).
- **Code preview (mini artifacts)** — HTML/CSS/JS code blocks get a "Preview" button that renders them live in a sandboxed iframe (with console capture for JS), plus "Open in new tab".
- **Multiple models** — pick **Devil Flash**, **Devil Pro** or **Devil Ultra** from the chat box. Public visitors only see the devil names — which engine powers each model is a server-side secret configurable in the admin panel.
- **100% Prexzy-powered** — all engines are free endpoints on prexzyapis.com. No API keys, no accounts, nothing to configure. Defaults: Flash = fastest engine, Pro = most reliable, Ultra = smartest.
- **Automatic fallback** — if an engine fails (downtime, empty reply), Devil AI automatically retries on the other engines. The chat never dies; a tiny offline brain is the invisible last resort.
- **No intro spam** — engines are instructed (and server-side replies are sanitized) so responses never open with a self-introduction. The bot introduces itself only when explicitly asked.
- **Cookie consent system** — first-visit banner (Accept all / Manage), per-category toggles (Essential locked, Analytics & Personalization opt-in), choices stored in localStorage + a 1-year cookie, full policy page, footer link to reopen settings.
- **Identity protection** — the bot always answers as **Devil AI** and never reveals the underlying models/providers (hardened persona + scripted identity answer; every engine is live-tested before being added to the admin list). UI, API and page source contain zero provider names.
- **Public-safety hardening** — password-locked admin panel, per-user message rate limit, per-IP auth attempt limit, XSS-safe rendering, HTTP-only sessions with ID regeneration on login.

## AI Mode / Agent Mode

The **Devil AI** title at the top of the chat page is a dropdown: choose **AI Mode** (normal chat) or **Agent Mode** (web search, read pages, calculator, date/time tools).

- Agent chats live at `/agent/{128-char-slug}`; a new agent chat is `/agent`.
- Normal chats keep their `/chat/{model}/{type}/{slug}` URLs.
- Each chat remembers its mode (`mode: ai|agent`); opening a chat with the wrong URL corrects it automatically.
- Switching mode inside a saved chat starts a fresh chat in the other mode. The admin flag `agent_enabled` disables Agent Mode.

## Requirements

- PHP 7.4+ with `mail()` working (cURL recommended, `allow_url_fopen` as fallback)
- Writable `data/` folder (chmod 755)
- That's it.

## Quick start

1. Upload everything to a folder on your host (e.g. `/www/yourdomain.com/devil-ai/`).
2. Make sure `data/` is writable (it already contains a protective `.htaccess`).
3. Open the site → enter your email → get the code / magic link → chat.
4. **Bookmark `admin.php`** and **set the admin password on first visit**. The panel is not linked anywhere in the public UI.

### Admin panel (owner only — `admin.php`)

Password-locked page where you configure:

- Which engine powers **Devil Flash / Pro / Ultra** (engine names are visible to the admin only, after the password checks out)
- Messages per user per hour, max chats per user
- Change the admin password
- Test the engines with one click

## File storage

```
data/
├── users.json          # accounts (no passwords — email + id only)
├── otps.json           # one-time login/delete codes + magic-link tokens (auto-expiring)
├── config.json         # admin-managed runtime config (created on first save)
├── rl.json             # per-user message rate limiting
├── authrl.json         # per-IP auth attempt limiting
├── mail.log            # local log of mail() failures
└── chats/<user_id>/    # one JSON file per conversation (isolated per user)
```

Everything under `data/` is blocked from direct browser access via `.htaccess`.

## API overview

Public: `bootstrap`, `otp_request`, `otp_verify`, `me`, `logout`, `settings` (GET — public-safe, exposes nothing).
User: `chats`, `chat_load`, `chat_send` (text + optional `image`), `chat_delete`, `chat_rename`, `otp_request` (`purpose: "delete"`), `account_delete`.
Admin: `auth`, `settings` (POST), `test`.

All responses are JSON. All error messages are in English.

## Privacy notes

- Analytics and personalization cookies are **off by default** and opt-in only.
- No third-party scripts, no ad networks, no trackers.
- No passwords are stored at all; chats are isolated per account; login codes expire in minutes.

---

Devil AI v1.0.0.0 • Developed by [BlazeNXT](https://www.blazenxt.in) • Sinfully smart, surprisingly helpful.
- 
