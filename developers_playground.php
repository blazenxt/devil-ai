<?php
require_once __DIR__ . '/inc/developer_console.php';
dev_console_start('Playground', 'playground');
?>
<section class="hero">
  <h1><?= icon('play', 28) ?> Playground</h1>
  <p>Test Devil AI models before integrating. Playground uses your logged-in account, not an API key.</p>
</section>

<section class="card">
  <div class="grid two">
    <div>
      <h2><?= icon('message-circle', 18) ?> Request</h2>
      <label class="sub" for="pgModel" style="display:block;margin-bottom:6px">Model</label>
      <select class="field" id="pgModel"><option value="devil-flash">Gemini 3.5 Flash Lite</option><option value="devil-pro">Gemini 3.6 Flash</option><option value="devil-ultra">Gemini 3.8 Flash</option></select>
      <label class="sub" for="pgSystem" style="display:block;margin:12px 0 6px">Optional developer instruction</label>
      <input class="field" id="pgSystem" type="text" maxlength="1200" placeholder="e.g. Reply in Hinglish">
      <label class="sub" for="pgPrompt" style="display:block;margin:12px 0 6px">Prompt</label>
      <textarea class="field" id="pgPrompt" maxlength="4000" placeholder="Ask Devil AI anything…"></textarea>
      <div class="row" style="margin-top:12px"><button class="btn primary" id="pgRun" type="button"><?= icon('play', 15) ?> Run</button><button class="btn ghost" id="pgClear" type="button"><?= icon('x', 14) ?> Clear</button></div>
      <div class="status" id="pgStatus"></div>
    </div>
    <div>
      <h2><?= icon('sparkles', 18) ?> Response</h2>
      <div class="code playOut" id="pgOut">Response will appear here…</div>
    </div>
  </div>
</section>
<?php
dev_console_end(<<<'JS'
(function(){
var D=window.DevilDev;
D.$('#pgRun').addEventListener('click',function(){
  var msg=D.$('#pgPrompt').value.trim();
  if(!msg){D.setStatus('#pgStatus','Enter a prompt first.','bad');return;}
  D.$('#pgOut').textContent='Thinking…';
  D.setStatus('#pgStatus','Running playground…');
  D.api('dev_playground',{model:D.$('#pgModel').value,system:D.$('#pgSystem').value,message:msg}).then(function(j){
    if(j.ok){D.$('#pgOut').textContent=j.reply||'';D.setStatus('#pgStatus','Done · '+((j.usage&&j.usage.total_tokens)||0)+' estimated tokens','ok')}
    else{D.$('#pgOut').textContent='';D.setStatus('#pgStatus',j.error||'Playground failed.','bad')}
  });
});
D.$('#pgClear').addEventListener('click',function(){D.$('#pgPrompt').value='';D.$('#pgSystem').value='';D.$('#pgOut').textContent='Response will appear here…';D.setStatus('#pgStatus','')});
})();
JS);
