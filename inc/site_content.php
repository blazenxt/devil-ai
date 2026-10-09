<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — content of the static site pages
 *  (how it works, FAQ, about, careers, legal, blog posts)
 * ═══════════════════════════════════════════════════════
 */

/* blog posts: newest first. 'cover' picks the gradient + icon of the card art */
function site_blog_posts(): array {
    return [
        'home-is-the-chat' => [
            'title' => 'No more landing page: the home page is the chat',
            'date' => '2026-10-09', 'tag' => 'Product', 'cover' => ['c1', 'message'],
            'summary' => 'Open Devil AI and you are already in the chat. Look around first, log in only when you send your first message.',
            'body' => '<p>Until today, opening Devil AI showed a marketing page first. You had to click through it, log in, and only then could you type anything. That is three steps too many.</p>
<p>From now on the home page <b>is</b> the chat. You see the chat box, the modes and the suggestions straight away — exactly what you will use every day.</p>
<h2>What changes for you</h2>
<ul><li><b>No account needed to look around.</b> Browse the modes, the leaderboard, the FAQ and the blog without logging in.</li>
<li><b>Log in when you send.</b> When you send your first message we ask you to log in. Whatever you typed is kept and put back in the box afterwards.</li>
<li><b>New pages.</b> A full <a href="leaderboard">Leaderboard</a>, a <a href="history/search">Search</a> page for your chats, <a href="how-it-works">How it works</a>, an <a href="faq">FAQ</a> and this blog.</li></ul>
<p>Everything else stays the same: your chats, your settings and your account.</p>',
        ],
        'agent-mode' => [
            'title' => 'Introducing Agent Mode',
            'date' => '2026-10-02', 'tag' => 'Product', 'cover' => ['c2', 'spark'],
            'summary' => 'An agent with its own computer: it writes code, runs it, tests it in a browser and hands you a live preview.',
            'body' => '<p>Most chatbots answer with text. <b>Agent Mode</b> answers with finished work.</p>
<p>Every Agent chat gets a private workspace — a small computer in the cloud. The agent can create files, run commands, install packages, start a web server and open the result in a real browser to check it.</p>
<h2>What it can do</h2>
<ul><li><b>Build websites and apps</b> — multi-page sites, dashboards, games and tools, with a live preview link.</li>
<li><b>Test its own work</b> — it opens every page, clicks buttons, fills forms and fixes what is broken before it says "done".</li>
<li><b>Search and read the web</b> — for up-to-date facts, docs and examples.</li>
<li><b>Create images</b> — photos and illustrations for the things it builds.</li>
<li><b>Hand everything over</b> — browse the files in the Workspace panel or download them as a zip.</li></ul>
<h2>Finishing the whole job</h2>
<p>An agent is only useful if it completes what you asked. Before Devil Agent finishes, an automatic check looks for missing files, broken links, untested pages, unused images and designs that are too plain — and sends the agent back to work until everything is fixed.</p>
<p>Pick <b>Agent</b> from the mode menu on the <a href="./">home page</a> to try it.</p>',
        ],
        'battle-mode-leaderboard' => [
            'title' => 'Battle Mode and the Devil Leaderboard',
            'date' => '2026-09-20', 'tag' => 'Leaderboard', 'cover' => ['c3', 'trophy'],
            'summary' => 'Two anonymous models answer your prompt. You vote. Every vote moves the public leaderboard.',
            'body' => '<p>Which model is actually better? Benchmarks only tell part of the story. The most honest test is simple: ask a real question, compare two answers, and pick the one you prefer — without knowing who wrote which.</p>
<h2>How a battle works</h2>
<ol><li>Type a prompt in <b>Battle</b> mode.</li><li>Two anonymous models answer side by side.</li><li>Vote: <i>A is better</i>, <i>B is better</i>, <i>tie</i> or <i>both are bad</i>.</li><li>The model names are revealed after you vote.</li></ol>
<h2>From votes to rankings</h2>
<p>Each vote updates an <b>Elo score</b> — the same system used to rank chess players. Beating a strong model earns more points than beating a weak one. The <a href="leaderboard">Leaderboard</a> shows the current scores, win rates and vote counts, and the most recent battles.</p>
<p>Because names stay hidden until after the vote, the ranking reflects the answers, not the brand.</p>',
        ],
        'side-by-side' => [
            'title' => 'Side by Side: two models, one prompt',
            'date' => '2026-09-12', 'tag' => 'Product', 'cover' => ['c4', 'columns'],
            'summary' => 'Choose any two models yourself and compare their answers to the same prompt, next to each other.',
            'body' => '<p>Battle Mode keeps the models secret. Sometimes you want the opposite: you already know which two models you want to compare.</p>
<p><b>Side by Side</b> lets you pick both models. Your prompt goes to both at once and the answers stream in next to each other, so differences in accuracy, tone and detail are easy to spot.</p>
<ul><li>Pick model A and model B from the menus above each column.</li><li>Keep the conversation going — every follow-up goes to both.</li><li>Like an answer? Continue the chat with that model in Direct mode.</li></ul>
<p>Side by Side votes are kept separate from Battle votes, so they never affect the public leaderboard.</p>',
        ],
        'privacy-first' => [
            'title' => 'How Devil AI handles your data',
            'date' => '2026-08-28', 'tag' => 'Privacy', 'cover' => ['c5', 'shield'],
            'summary' => 'No passwords, no ad trackers, chats isolated per account and deletable at any time.',
            'body' => '<p>Privacy was a design rule from day one, not an add-on.</p>
<ul><li><b>No passwords.</b> You log in with a one-time code sent to your email, or with GitHub. There is no password to leak.</li>
<li><b>No ad trackers.</b> No advertising cookies, no third-party analytics scripts, no fingerprinting. Optional cookies stay off until you turn them on.</li>
<li><b>Your chats are yours.</b> They are stored per account, shown only to you, and you can delete a chat — or your whole account — at any time.</li>
<li><b>Temporary chats.</b> Use a temporary chat when you do not want a conversation saved at all.</li></ul>
<p>To answer your messages, your prompts are sent to the AI model you chose. Read the full <a href="privacy-policy">Privacy Policy</a> and <a href="cookie-policy">Cookie Policy</a> for the details.</p>',
        ],
    ];
}

function site_page_content(string $p): ?array {
    $updated = 'October 9, 2026';
    switch ($p) {
    case 'how-it-works':
        return ['title' => 'How it works', 'desc' => 'Chat with top AI models, compare them in anonymous battles and see which one people prefer.', 'html' => '
<div class="hero sm"><span class="eyebrow">How it works</span><h1>Ask. Compare. Vote.</h1><p class="lead">Devil AI lets you chat with strong AI models, compare their answers side by side, and see which ones people really prefer.</p></div>
<div class="steps">
  <div class="step"><span class="n">1</span><h3>Ask anything</h3><p>Type a question, paste code, attach a file or an image. Pick a mode and a model — or let Devil AI pick for you.</p></div>
  <div class="step"><span class="n">2</span><h3>Compare answers</h3><p>In <b>Battle</b> two anonymous models answer the same prompt. In <b>Side by Side</b> you choose both models yourself.</p></div>
  <div class="step"><span class="n">3</span><h3>Vote</h3><p>Pick the better answer (or a tie). In Battle the names are revealed only after you vote, so brands cannot sway you.</p></div>
  <div class="step"><span class="n">4</span><h3>Shape the leaderboard</h3><p>Every Battle vote updates the models\' Elo scores on the public <a href="leaderboard">Leaderboard</a>.</p></div>
</div>
<h2 class="sect">Four ways to chat</h2>
<div class="modes">
  <a class="mcard" href="battle"><span class="sic">' . icon('swords', 20) . '</span><b>Battle</b><span>Two anonymous models answer. Vote, then the names are revealed.</span></a>
  <a class="mcard" href="agent"><span class="sic">' . icon('spark', 20) . '</span><b>Agent</b><span>Built for complex tasks: it writes and runs code, builds apps, tests them and gives you a live preview.</span></a>
  <a class="mcard" href="side-by-side"><span class="sic">' . icon('columns', 20) . '</span><b>Side by Side</b><span>Compare two models of your choice on the same prompt.</span></a>
  <a class="mcard" href="direct"><span class="sic">' . icon('message', 20) . '</span><b>Direct</b><span>Chat with one model at a time — Devil Flash, Pro, Ultra or a custom engine.</span></a>
</div>
<h2 class="sect">How the ranking is calculated</h2>
<div class="prose"><p>Each Battle vote is a match between two models. We use the <b>Elo rating system</b>: every model starts at 1000 points. A win against a stronger model earns more points than a win against a weaker one; a tie moves both scores towards each other. "Both are bad" votes count as a tie.</p>
<p>Only anonymous Battle votes count. Side by Side votes are kept out of the ranking because the voter knew the model names.</p></div>
<div class="cta-band"><div><h3>Ready to try it?</h3><p>Open the chat — no account needed until you send.</p></div><a class="btn primary" href="./">Start a chat</a></div>'];

    case 'faq':
        $qa = [
            ['What is Devil AI?', 'Devil AI is an AI chat platform built by BlazeNXT. You can chat with several AI models, compare them in anonymous battles, run an agent that builds and tests software for you, and see a public leaderboard of the models.'],
            ['Is it free?', 'Yes. Chatting, battles, the leaderboard and Agent Mode are free to use. Fair-use limits apply so that the service stays fast for everyone.'],
            ['Do I need an account?', 'You can look around without one. To send a message, save chats or vote, log in with a one-time email code or with GitHub. There are no passwords.'],
            ['What are the modes?', '<b>Battle</b>: two anonymous models answer and you vote. <b>Agent</b>: an agent with its own workspace that writes code, runs it and gives you a live preview. <b>Side by Side</b>: compare two models you choose. <b>Direct</b>: chat with one model.'],
            ['How does the leaderboard work?', 'Every anonymous Battle vote updates the Elo scores of the two models involved. See <a href="how-it-works">How it works</a> for details.'],
            ['What can Agent Mode build?', 'Websites, multi-page sites, dashboards, games, small tools and scripts. It creates the files, runs a server, tests the pages in a browser, fixes problems and shares a preview link. You can browse or download all files from the Workspace panel.'],
            ['Can I attach files and images?', 'Yes, in Direct and Agent mode. Battle and Side by Side are text-only so that both models get exactly the same input.'],
            ['Are my chats private?', 'Your chats are stored under your account and only you can see them. You can delete any chat, or your whole account, at any time. Use a temporary chat if you do not want a conversation saved. Read the <a href="privacy-policy">Privacy Policy</a>.'],
            ['Can answers be wrong?', 'Yes. AI models can make mistakes or invent facts. Check important information, especially medical, legal or financial advice.'],
            ['Is there an API?', 'Yes. Signed-in users can create API keys in the Developer console and call an OpenAI-compatible <code>/v1/chat/completions</code> endpoint.'],
            ['How do I delete my account?', 'Open the account menu at the bottom of the sidebar and choose <b>Delete account</b>, or go to Account settings.'],
        ];
        $h = '<div class="hero sm"><span class="eyebrow">FAQ</span><h1>Frequently asked questions</h1><p class="lead">Everything you need to know about Devil AI.</p></div><div class="faq">';
        foreach ($qa as $i => $x) { $h .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . h($x[0]) . icon('chevron-down', 16) . '</summary><div class="ans">' . $x[1] . '</div></details>'; }
        $h .= '</div><div class="cta-band"><div><h3>Still have a question?</h3><p>Ask Devil AI itself — it knows its way around.</p></div><a class="btn primary" href="./">Ask in chat</a></div>';
        return ['title' => 'FAQ', 'desc' => 'Answers to common questions about Devil AI, its modes, the leaderboard and your privacy.', 'html' => $h];

    case 'about':
        return ['title' => 'About', 'desc' => 'Devil AI is built by BlazeNXT: an AI chat platform with battles, an agent and a public leaderboard.', 'html' => '
<div class="hero sm"><span class="eyebrow">Company</span><h1>About Devil AI</h1><p class="lead">A one-of-a-kind AI platform built by BlazeNXT. Real answers, real privacy, and an honest view of which models are best.</p></div>
<div class="prose">
<h2>Our mission</h2>
<p>AI is moving fast and every company says its model is the best. We think people should be able to judge for themselves — on their own questions, without brand names getting in the way. Devil AI puts strong models in one place, lets you compare them blind, and publishes what people prefer.</p>
<h2>What we build</h2>
</div>
<div class="feat">
  <div><span class="sic">' . icon('message', 18) . '</span><b>Chat</b><p>Devil Flash, Pro and Ultra, plus custom engines — with files, images, voice, sharing and export.</p></div>
  <div><span class="sic">' . icon('swords', 18) . '</span><b>Battles</b><p>Anonymous head-to-head comparisons that power the public leaderboard.</p></div>
  <div><span class="sic">' . icon('spark', 18) . '</span><b>Agent</b><p>An agent with its own workspace that builds, runs and tests real software.</p></div>
  <div><span class="sic">' . icon('shield', 18) . '</span><b>Privacy</b><p>No passwords, no ad trackers, and chats that you can delete at any time.</p></div>
</div>
<div class="prose">
<h2>Who we are</h2>
<p>Devil AI is developed and operated by <b>BlazeNXT</b>, a small independent team from India. We design, build and run every part of the product ourselves.</p>
<p>Want to get in touch? Visit <a href="https://www.blazenxt.in" target="_blank" rel="noopener">www.blazenxt.in</a>.</p>
</div>
<div class="cta-band"><div><h3>Join the team?</h3><p>See how you can help build Devil AI.</p></div><a class="btn primary" href="company/careers">Careers</a></div>'];

    case 'careers':
        return ['title' => 'Careers', 'desc' => 'Help build Devil AI at BlazeNXT.', 'html' => '
<div class="hero sm"><span class="eyebrow">Company</span><h1>Careers</h1><p class="lead">We are a small team building an AI platform used for real work every day. We care about speed, honesty and finishing what we start.</p></div>
<div class="feat">
  <div><span class="sic">' . icon('zap', 18) . '</span><b>Ship fast</b><p>Small team, short loops. Ideas go live in days, not quarters.</p></div>
  <div><span class="sic">' . icon('globe', 18) . '</span><b>Remote-friendly</b><p>Work from wherever you do your best work.</p></div>
  <div><span class="sic">' . icon('brain', 18) . '</span><b>Real AI problems</b><p>Agents, evaluation, model routing and fast, reliable infrastructure.</p></div>
  <div><span class="sic">' . icon('flame', 18) . '</span><b>Own it</b><p>Everyone owns features end to end — from idea to production.</p></div>
</div>
<h2 class="sect">Open roles</h2>
<div class="empty-card"><span class="sic">' . icon('file-text', 22) . '</span><b>No open roles right now</b><p>We are not hiring for specific positions at the moment. If you love AI products and want to help, introduce yourself at <a href="https://www.blazenxt.in" target="_blank" rel="noopener">www.blazenxt.in</a> — we read every message.</p></div>'];

    case 'privacy-policy':
        return ['title' => 'Privacy Policy', 'desc' => 'How Devil AI collects, uses and protects your information.', 'legal' => true, 'html' => '
<div class="hero sm"><span class="eyebrow">Legal</span><h1>Privacy Policy</h1><p class="lead">Last updated: ' . $updated . '</p></div>
<div class="prose legal">
<p class="note"><b>The short version:</b> we collect only what we need to run Devil AI, we never sell your data, there are no ad trackers, and you can delete your chats or your whole account at any time.</p>
<h2>1. Who we are</h2><p>Devil AI is operated by BlazeNXT ("we", "us"). BlazeNXT is responsible for the personal information processed through this service.</p>
<h2>2. Information we collect</h2><ul>
<li><b>Account information:</b> your email address and display name. If you log in with GitHub, we receive your public profile name and verified email.</li>
<li><b>Content:</b> the messages, files and images you send, the answers you receive, your votes and feedback, and the files an agent creates in your workspace.</li>
<li><b>Technical information:</b> IP address, browser type and request logs, used for security, abuse prevention and fixing errors.</li>
<li><b>Cookies:</b> an essential session cookie and, only if you allow them, preference cookies. See the <a href="cookie-policy">Cookie Policy</a>.</li></ul>
<h2>3. How we use it</h2><ul><li>To provide the service: sign you in, answer your messages, run agents, save your chats.</li><li>To compute the public leaderboard from anonymous Battle votes (votes are not shown with your name).</li><li>To keep the service secure, prevent abuse and enforce fair-use limits.</li><li>To fix bugs and improve the product.</li></ul>
<h2>4. AI processing</h2><p>To generate answers, your prompts and attachments are sent to the AI model providers that power the model you chose, and agent tasks run in isolated cloud workspaces. These partners process the data only to provide the service to you.</p>
<h2>5. Sharing</h2><p>We do not sell or rent your personal information and we do not share it for advertising. We share data only with the service providers needed to run Devil AI (hosting, email delivery, AI processing), when you choose to share a chat link, or when required by law.</p>
<h2>6. Retention and deletion</h2><p>Chats are kept until you delete them. Temporary chats are not saved. Agent workspaces are removed automatically after a period of inactivity. When you delete your account, your account data and chats are deleted.</p>
<h2>7. Security</h2><p>We use HTTPS everywhere, HTTP-only secure session cookies, one-time login codes instead of passwords, and per-account isolation of chats. No system is perfectly secure, but we work hard to protect your data.</p>
<h2>8. Your rights</h2><p>You can access, export and delete your chats in the app, and delete your account at any time. For any other request, contact us via <a href="https://www.blazenxt.in" target="_blank" rel="noopener">www.blazenxt.in</a>.</p>
<h2>9. Children</h2><p>Devil AI is not intended for children under 13, and we do not knowingly collect their data.</p>
<h2>10. Changes</h2><p>If we change this policy we will update this page and the date above. Significant changes will be announced in the app.</p>
</div>'];

    case 'terms-of-use':
        return ['title' => 'Terms of Use', 'desc' => 'The rules for using Devil AI.', 'legal' => true, 'html' => '
<div class="hero sm"><span class="eyebrow">Legal</span><h1>Terms of Use</h1><p class="lead">Last updated: ' . $updated . '</p></div>
<div class="prose legal">
<p class="note">By using Devil AI you agree to these terms. Please read them — they are short.</p>
<h2>1. The service</h2><p>Devil AI, operated by BlazeNXT, lets you chat with AI models, compare them, run agents and view a public leaderboard. Features may change, and we may add or remove models at any time.</p>
<h2>2. Your account</h2><p>You are responsible for activity on your account. Keep access to your email (or GitHub) secure, since it is used to log in.</p>
<h2>3. Acceptable use</h2><p>Do not use Devil AI to:</p><ul><li>break the law or infringe anyone\'s rights;</li><li>create malware, attack systems, or run abusive workloads (crypto mining, spam, scanning) in agent workspaces;</li><li>harass, threaten or exploit others, or create sexual content involving minors;</li><li>overload the service, bypass limits or security checks, or scrape it at scale;</li><li>manipulate the leaderboard with fake or automated votes.</li></ul>
<p>We may limit or suspend accounts that break these rules.</p>
<h2>4. AI output</h2><p>Answers are generated by AI and <b>may be inaccurate, incomplete or offensive</b>. Do not rely on them for medical, legal, financial or safety decisions without checking with a qualified person. You are responsible for how you use the output.</p>
<h2>5. Your content</h2><p>You keep the rights to what you send and to the output you receive, as far as the law allows. You give us permission to process your content to provide the service. Anonymous Battle votes may be published in aggregate on the leaderboard.</p>
<h2>6. Shared chats</h2><p>When you create a share link, anyone with the link can view that chat. You can delete shared chats at any time.</p>
<h2>7. Availability</h2><p>Devil AI is provided "as is", without warranties. We aim for high availability but cannot guarantee that the service will always be available or error-free.</p>
<h2>8. Liability</h2><p>To the extent permitted by law, BlazeNXT is not liable for indirect or consequential damages arising from your use of the service.</p>
<h2>9. Changes</h2><p>We may update these terms. Continuing to use Devil AI after a change means you accept the new terms.</p>
<h2>10. Contact</h2><p>Questions? Reach us via <a href="https://www.blazenxt.in" target="_blank" rel="noopener">www.blazenxt.in</a>.</p>
</div>'];

    case 'cookie-policy':
        $rows = [
            ['Essential', 'DEVILAISESSID', 'Keeps you signed in and protects against session hijacking (HTTP-only, Secure).', '30 days rolling', 'Always on — required'],
            ['Essential', 'devil_cookies, devil_cookie_version', 'Remembers your cookie choices so we stop asking on every visit.', '1 year', 'Always on — required'],
            ['Analytics (optional)', 'devil_analytics', 'Anonymous usage preference flag for future improvements. No third-party tracker is loaded.', '1 year', 'Opt-in only'],
            ['Personalization (optional)', 'devil_personal, devil_theme, devil_model, devil_custom_model, devil_sb', 'Remembers theme, selected model, sidebar state and UI preferences across pages.', '1 year', 'Opt-in only'],
        ];
        $t = '<div class="tblwrap"><table class="tbl"><thead><tr><th>Category</th><th>Cookie</th><th>Purpose</th><th>Duration</th><th>Consent</th></tr></thead><tbody>';
        foreach ($rows as $r) { $t .= '<tr><td>' . h($r[0]) . '</td><td><code>' . h($r[1]) . '</code></td><td>' . h($r[2]) . '</td><td>' . h($r[3]) . '</td><td>' . h($r[4]) . '</td></tr>'; }
        $t .= '</tbody></table></div>';
        return ['title' => 'Cookie Policy', 'desc' => 'How Devil AI uses cookies and how you can control them.', 'legal' => true, 'html' => '
<div class="hero sm"><span class="eyebrow">Legal</span><h1>Cookie Policy</h1><p class="lead">Last updated: ' . $updated . '</p></div>
<div class="prose legal">
<p class="note"><b>The short version:</b> essential cookies keep you signed in. Analytics and personalization cookies are strictly opt-in — they stay off until you enable them. You can change your choice at any time.</p>
<p><button class="btn ghost" type="button" onclick="if (window.devilOpenCookies) { window.devilOpenCookies(); }">' . icon('cookie', 15) . ' Cookie settings</button></p>
<h2>1. What are cookies?</h2><p>Cookies are small text files that a website stores in your browser. They let a site remember things between page loads — for example, that you are signed in, or which preferences you chose. They cannot read your files or install anything on your device.</p>
<h2>2. The cookies we use</h2>' . $t . '
<p>We do not use advertising cookies, third-party trackers or fingerprinting. There are no ad networks, trackers or data brokers on this site.</p>
<h2>3. Managing your preferences</h2><ul><li><b>Cookie banner:</b> shown on your first visit — "Accept all" or "Manage cookies" with per-category toggles.</li><li><b>Cookie settings:</b> available any time from the account menu in the app, or with the button above.</li><li><b>Browser settings:</b> you can block or delete cookies in your browser. Blocking essential cookies will sign you out.</li></ul>
<p>When you decline optional categories, we actively delete any leftover optional cookies.</p>
<h2>4. Cookies and local storage</h2><p>Cookie choices and personalization preferences are stored as first-party cookies and mirrored in local storage (key <code>devil_cookie_prefs</code>) for faster loading. If you turn personalization off, Devil AI deletes the optional preference cookies and their local-storage copies.</p>
<h2>5. Changes to this policy</h2><p>If we change how cookies are used, we will update this page and the banner will ask for your choices again.</p>
<p>Questions? BlazeNXT is responsible for this service — visit <a href="https://www.blazenxt.in" target="_blank" rel="noopener">www.blazenxt.in</a>.</p>
</div>'];
    }
    return null;
}
