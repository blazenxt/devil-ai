<?php
require_once __DIR__ . '/inc/developer_console.php';
dev_console_start('Models Available', 'models');
?>
<section class="hero">
  <h1><?= icon('server', 28) ?> Models Available</h1>
  <p>Use these public model ids in API requests. Internal routing stays private behind Devil AI.</p>
</section>

<section class="grid three">
  <div class="card"><h2><?= icon('zap', 18) ?> Devil Flash</h2><p class="sub"><b>Model ID:</b> <code>devil-flash</code></p><p class="sub">Fast, lightweight model for everyday chat, UI features, quick helpers, and responsive apps.</p></div>
  <div class="card"><h2><?= icon('sparkles', 18) ?> Devil Pro</h2><p class="sub"><b>Model ID:</b> <code>devil-pro</code></p><p class="sub">Balanced model for better writing, deeper assistance, and production-quality conversational UX.</p></div>
  <div class="card"><h2><?= icon('crown', 18) ?> Devil Ultra</h2><p class="sub"><b>Model ID:</b> <code>devil-ultra</code></p><p class="sub">Maximum quality mode for complex prompts, reasoning-heavy answers, and premium experiences.</p></div>
</section>

<section class="card">
  <h2><?= icon('code', 18) ?> Example</h2>
  <div class="code">{
  "model": "devil-pro",
  "messages": [
    {"role": "user", "content": "Build me a product description."}
  ]
}</div>
</section>
<?php dev_console_end();
