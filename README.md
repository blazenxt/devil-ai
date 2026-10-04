# 😈 Devil AI — Your Own AI Chatbot in 100% Pure PHP

> 🔥 **Sinfully smart — surprisingly helpful!**

A ChatGPT-style personal AI chatbot — **no framework, no Python, no Node.js, no database** — just pure PHP. Runs on any PHP server or shared hosting (Hostinger, InfinityFree, cPanel, etc.).

---

## ✨ Features

| | |
|---|---|
| 😈 **Devil personality** | Dark-red theme, glowing SVG logo, wicked attitude |
| 🤖 **Full AI Mode** | Gemini / Groq / OpenAI / OpenRouter / any OpenAI-compatible API |
| 👹 **Demo Mode** | Works with no API key at all — jokes, calculator, time/date, small talk |
| 💬 **Chat history + markdown** | Bold, lists, code blocks — all rendered |
| ⚙️ **Settings panel** | Add your key in the browser, switch providers, test the connection |
| 🌍 **Public-ready** | Per-IP rate limiting + admin password protection |
| 📱 **Responsive** | Perfect on mobile and desktop |

---

## 📁 Files

| File | Purpose |
|---|---|
| `index.php` | Chat UI (dark devil theme) |
| `api.php` | Backend — AI calls, demo mode, rate limiting, settings |
| `config.php` | Default settings (you can put your key here) |
| `assets/logo.svg` | Devil AI logo (SVG) |
| `data/config.json` | Settings saved from the UI (auto-created) |
| `data/rl.json` | Rate-limit data (auto-created) |
| `.htaccess` | Security (directory listing off, `data/` blocked) |

---

## 🔧 Requirements

- **PHP 7.4+** (8.x recommended)
- **cURL** extension (present on 99% of hosts) — or `allow_url_fopen` ON
- Sessions (ON by default almost everywhere)

---

## 🚀 Install (2 minutes)

1. Upload the `devil-ai` folder to your server (e.g. inside `public_html/`)
2. Open `https://your-site.com/devil-ai/`
3. **Done!** 😈 The app is already running in **Demo Mode** (no key needed).

---

## 🔑 Get a FREE API Key (2 minutes)

> **Note:** AI apps like venom.app / the reference site have no public API — that's why "direct fetch" isn't possible. Official **free** APIs are the right way: legal, stable, and genuinely free.

### Option 1: Google Gemini (recommended)

1. Open 👉 **https://aistudio.google.com/apikey**
2. Sign in with a Google account (no credit card needed)
3. Click **"Get API key"** / **"Create API key"**
4. Copy the key (starts with `AIza...`)
5. In Devil AI: ⚙️ **Settings** → Provider: **Google Gemini** → paste key → **Save** → **Test Connection**

### Option 2: Groq (fast + free)

1. Open 👉 **https://console.groq.com/keys**
2. Sign up with Google/GitHub
3. **Create API Key** → copy (starts with `gsk_...`)
4. Settings → Provider: **Groq** → paste → Save

### Option 3: OpenRouter (free models)

👉 **https://openrouter.ai/settings/keys** — get a key, then use model `meta-llama/llama-3.3-70b-instruct:free`

---

## 🌍 BEFORE DEPLOYING PUBLICLY (IMPORTANT!)

If you're opening the site to everyone, do these 3 things:

### 1. Set an Admin Password

In `config.php`:
```php
'admin_password' => 'your-strong-password',
```
Otherwise any visitor could open ⚙️ Settings and change your API key! 🔒

### 2. Review the rate limit

`rate_per_hour` in `config.php` (default 40) — max messages per user (IP) per hour. This protects your free quota.

### 3. Understand the free tier

| Provider (free) | Speed | Approx. daily limit |
|---|---|---|
| Gemini 2.5 Flash | ~10-15 req/min | ~500-1,500 req/day (Google doesn't publish exact numbers) |
| Gemini 2.5 Flash-Lite | ~15-30 req/min | ~1,000-1,500 req/day |
| Groq llama-3.3-70b | ~30 req/min | ~1,000 req/day |

- **Meaning:** 20-50 daily users are comfortably handled. Beyond that you'll see `429` errors — the app automatically shows a friendly message ("limit reached, try again soon").
- **When traffic grows:** turn on billing in Google AI Studio → limits jump ~200x, daily cap removed. Gemini Flash is cheap (~$0.30 per 1M input tokens ≈ ₹25).
- **Privacy note:** on the free tier, Google may use data to improve its products. Consider a small disclaimer for public users.

---

## ⚙️ Settings — Two Ways

**Way 1 (easy):** ⚙️ button in the app → provider + key → Save + Test

**Way 2 (file):** open `config.php` → set `provider` + `api_key`

> The key is stored only on your server (`data/config.json`) — never sent to the browser. The `data/` folder is blocked from web access via `.htaccess` (Apache). For Nginx: `location ^~ /devil-ai/data/ { deny all; }`

---

## 🧠 Model Options

| Provider | Model | Note |
|---|---|---|
| Gemini | `gemini-2.5-flash` | Default — best balance |
| Gemini | `gemini-2.5-flash-lite` | Fastest, higher daily limit |
| Groq | `llama-3.3-70b-versatile` | Quality + speed |
| Groq | `llama-3.1-8b-instant` | Very fast, lightweight |
| OpenRouter | `meta-llama/llama-3.3-70b-instruct:free` | Free |
| OpenAI | `gpt-4o-mini` | Paid, cheap |

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
| "Network error (cURL)" | Ask hosting support to enable cURL, or set `allow_url_fopen=On` in `php.ini` |
| API error 401 | Wrong key — copy-paste it again |
| API error 429 | Free limit reached — wait a while (or enable billing) |
| Gemini 404 | Wrong model name — try `gemini-2.5-flash` |
| Where is chat history stored? | In the session — survives reloads; the **New** button clears it |
| How do I change the key? | ⚙️ Settings → paste the new key → Save (the old one is replaced) |

---

**Made with 🔥 in pure PHP • Devil AI v1.0 😈**
