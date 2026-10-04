<?php
require_once __DIR__ . '/inc/developer_console.php';
$baseUrl = dev_console_base_url();
dev_console_start('API Docs', 'docs');
?>
<section class="hero">
  <h1><?= icon('code', 28) ?> API Docs</h1>
  <p>OpenAI-compatible JSON endpoints for Devil AI. Use API keys only on your server, never in public frontend code.</p>
  <div style="margin-top:14px"><span class="pill"><?= icon('server', 14) ?> Base URL: <?= htmlspecialchars($baseUrl) ?>/v1</span><span class="pill"><?= icon('key', 14) ?> Authorization: Bearer API key</span></div>
</section>

<section class="grid two">
  <div class="card">
    <h2><?= icon('server', 18) ?> List models</h2>
    <p class="sub">Returns available Devil AI model ids.</p>
    <div class="code">curl <?= htmlspecialchars($baseUrl) ?>/v1/models \
  -H "Authorization: Bearer dv_live_YOUR_KEY"</div>
  </div>
  <div class="card">
    <h2><?= icon('message-circle', 18) ?> Chat completions</h2>
    <p class="sub">OpenAI-style chat completion request.</p>
    <div class="code">curl <?= htmlspecialchars($baseUrl) ?>/v1/chat/completions \
  -H "Authorization: Bearer dv_live_YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "devil-flash",
    "messages": [
      {"role": "user", "content": "Explain APIs in Hinglish."}
    ]
  }'</div>
  </div>
</section>

<section class="card">
  <h2><?= icon('code', 18) ?> JavaScript server example</h2>
  <p class="sub">Keep the API key in environment variables on your server.</p>
  <div class="code">const res = await fetch('<?= htmlspecialchars($baseUrl) ?>/v1/chat/completions', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + process.env.DEVIL_API_KEY,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    model: 'devil-pro',
    messages: [{ role: 'user', content: 'Write a launch tweet.' }]
  })
});
const data = await res.json();
console.log(data.choices[0].message.content);</div>
</section>

<section class="card">
  <h2><?= icon('info', 18) ?> Response shape</h2>
  <div class="code">{
  "id": "chatcmpl-devil-...",
  "object": "chat.completion",
  "created": 1760000000,
  "model": "devil-flash",
  "choices": [{
    "index": 0,
    "message": {"role": "assistant", "content": "..."},
    "finish_reason": "stop"
  }],
  "usage": {"prompt_tokens": 12, "completion_tokens": 34, "total_tokens": 46}
}</div>
</section>
<?php dev_console_end();
