# 😈 Devil AI — Your Own AI Chatbot in 100% Pure PHP

> 🔥 **Sinfully smart — surprisingly helpful!**

A ChatGPT-style AI chatbot — **no framework, no Python, no Node.js, no database, no API key required** — just pure PHP. Runs on any PHP server or shared hosting (Hostinger, InfinityFree, cPanel, etc.).

**Works out of the box** — powered by free Prexzy APIs (no key needed!), with optional Google Gemini upgrade for the best quality.

---

## ✨ Features

| | |
|---|---|
| 🆓 **Zero setup** | Prexzy APIs built in — no API key, no signup, instant AI |
| 🤖 **Gemini upgrade** | Free Google Gemini key = best quality + conversation memory |
| ♻️ **Auto-fallback** | If Gemini fails (bad key / rate limit), Devil AI auto-switches to Prexzy |
| 😈 **Devil personality** | Dark-red theme, glowing SVG logo, wicked attitude |
| 👹 **Demo Mode** | Even fully offline it works — jokes, calculator, time/date |
| 💬 **Chat history + markdown** | Bold, lists, code blocks — all rendered |
| ⚙️ **Settings panel** | Switch providers in the browser, test the connection |
| 🌍 **Public-ready** | Per-IP rate limiting + admin password protection |
| 📱 **Responsive** | Perfect on mobile and desktop |

---

## 📁 Files

| File | Purpose |
|---|---|
| `index.php` | Chat UI (dark devil theme) |
| `api.php` | Backend — AI calls, demo mode, rate limiting, settings |
| `config.php` | Default settings (provider, fallback, limits) |
| `assets/logo.svg` | Devil AI logo (SVG) |
| `data/config.json` | Settings saved from the UI (auto-created) |
| `data/rl.json` | Rate-limit data (auto-created) |
| `.htaccess` | Security (directory listing off, `data/` blocked) |

---

## 🔧 Requirements

- **PHP 7.4+** (8.x recommended)
- **cURL** extension (present on 99% of hosts) — or `allow_url_fopen` ON
- Outbound internet access from the server (to reach the AI APIs)
- Sessions (ON by default almost everywhere)

---

## 🚀 Install (2 minutes)

1. Upload the `devil-ai` folder to your server (e.g. inside `public_html/`)
2. Open `https://your-site.com/devil-ai/`
3. **Done!** 😈 Full AI Mode is already active via Prexzy — no key needed!

---

## 🧠 Providers

| Provider | Key? | Quality | Notes |
|---|---|---|---|
| **Prexzy APIs** (default) | ❌ No key | ⭐⭐⭐ | Free, unlimited, instant setup — prexzyapis.com |
| **Google Gemini** | ✅ Free key | ⭐⭐⭐⭐⭐ | Best quality, conversation memory, your own quota |
| Demo Mode | ❌ | ⭐ | Offline fallback — jokes, calculator, time/date |

### Optional: upgrade to Gemini (recommended for public sites)

1. Open 👉 **https://aistudio.google.com/apikey** (Google account, no credit card)
2. Copy the key (starts with `AIza...`)
3. In Devil AI: ⚙️ **Settings** → Provider: **Google Gemini** → paste key → **Save** → **Test Connection**

**Auto-fallback:** if the Gemini key ever fails (expired, rate-limited, quota over), Devil AI automatically answers via Prexzy instead — your site never goes dumb 😈

### Prexzy endpoints (`prexzy_endpoint` in config.php)

| Endpoint | Model |
|---|---|
| `askgpt5` (default) | Qwen 3.5 397B — strong all-rounder |
| `gemini` | Google Gemini via Prexzy |
| `qwen` | Qwen (experimental) |

---

## 🌍 BEFORE DEPLOYING PUBLICLY (IMPORTANT!)

If you're opening the site to everyone, do these 3 things:

### 1. Set an Admin Password

In `config.php`:
```php
'admin_password' => 'your-strong-password',
```
Otherwise any visitor could open ⚙️ Settings and change your provider! 🔒

### 2. Review the rate limit

`rate_per_hour` in `config.php` (default 40) — max messages per user (IP) per hour. This keeps abuse and API load under control.

### 3. Know your limits

| | Speed | Daily limit |
|---|---|---|
| Prexzy APIs | ~1-5s per answer | No stated limit (free public service — can be slow/down sometimes) |
| Gemini 2.5 Flash (free) | ~10-15 req/min | ~500-1,500 req/day (Google doesn't publish exact numbers) |

- **For serious public use:** Gemini with billing ON is recommended (~$0.30 per 1M input tokens ≈ ₹25) — limits jump ~200x.
- **Privacy note:** free tiers (Prexzy & free Gemini) may log/use your data. Avoid sharing sensitive personal info.
- On `429` errors the app automatically shows a friendly "limit reached" message.

---

## ⚙️ Settings — Two Ways

**Way 1 (easy):** ⚙️ button in the app → provider + key → Save + Test

**Way 2 (file):** open `config.php` → set `provider` / `api_key`

> The API key is stored only on your server (`data/config.json`) — never sent to the browser. The `data/` folder is blocked from web access via `.htaccess` (Apache). For Nginx: `location ^~ /devil-ai/data/ { deny all; }`

---

## 🎨 Customize

- **Personality** → edit `SYSTEM_PROMPT` in `api.php`
- **Name / theme** → `index.php` (title, colors, logo)
- **Timezone** → `config.php` → `timezone`
- **Answer length** → `config.php` → `max_tokens`

---

## ❓ FAQ / Troubleshooting

| Problem | Fix |
|---|---|
| Site returns 500 | Delete `.htaccess` and retry (some hosts don't allow `Options`) |
| "Network error (cURL)" | Ask hosting support to enable cURL, or set `allow_url_fopen=On` |
| Prexzy is slow / empty answers | Switch `prexzy_endpoint` in config.php (try `gemini`), or upgrade to Google Gemini |
| Gemini API error 401 | Wrong key — copy-paste it again |
| Gemini API error 429 | Free limit reached — the app auto-falls back to Prexzy |
| Gemini 404 | Wrong model name — try `gemini-2.5-flash` |
| Where is chat history stored? | In the session — survives reloads; the **New** button clears it |

---

**Made with 🔥 in pure PHP • Devil AI v1.1 😈**
