<?php
/* ═══════════════════════════════════════
   sermon_notes.php  —  INTERACTIVE SERMON NOTES

   Opened by the "Notes" button on sermons.php when a sermon
   has no uploaded notes file (SERMON.notes_url is empty).

   Members TYPE their notes straight into the page:
     • everything auto-saves in THIS browser (localStorage) –
       closing the tab or coming back later keeps their notes
     • Print / Save as PDF, Download (.txt) and Copy buttons
     • nothing is sent to the server – notes stay private

   If you upload real notes, put the link in SERMON.notes_url
   and the Notes button opens that file instead of this page.
═══════════════════════════════════════ */
session_start();
require_once 'includes/db.php';

function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES); }

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM SERMON WHERE sermon_id = ?');
$st->execute([$id]);
$s = $st->fetch();

if (!$s) {
    http_response_code(404);
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Not found</title>'
       . '<p style="font-family:sans-serif;padding:2rem">Sermon not found. <a href="sermons.php">Back to sermons</a></p>';
    exit;
}

$ts        = strtotime($s['preached_on']);
$scripture = trim((string) ($s['scripture'] ?? ''));
$notes_url = trim((string) $s['notes_url']);
if ($notes_url !== '' && !preg_match('~^https?://~i', $notes_url) && preg_match('~^[a-z][a-z0-9+.-]*:~i', $notes_url)) {
    $notes_url = '';   // never link to javascript:/data: URLs
}
$is_logged_in = isset($_SESSION['member_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Notes — <?= e($s['title']) ?> | Shammah Urban Church</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --maroon:#3D0C2F; --maroon-2:#5C1A47; --gold:#C9A84C; --gold-lt:#E3C877;
    --bg:#f7f3f0; --card:#fff; --ink:#271a25; --muted:#756a73; --line:#e8e0e6; --ok:#2e7d4f;
  }
  * { box-sizing:border-box; }
  html { scroll-behavior:smooth; }
  body { margin:0; background:var(--bg); color:var(--ink); font-family:'DM Sans', system-ui, sans-serif; line-height:1.55; }

  /* ── top bar ── */
  .topbar { position:sticky; top:0; z-index:20; background:rgba(61,12,47,.97); backdrop-filter:blur(6px); color:#fff; }
  .topbar-in { max-width:860px; margin:0 auto; padding:10px 16px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
  .topbar a.back { color:var(--gold-lt); text-decoration:none; font-size:14px; font-weight:500; }
  .topbar a.back:hover { text-decoration:underline; }
  .grow { flex:1; }
  .saved { font-size:12.5px; color:rgba(255,255,255,.75); display:flex; align-items:center; gap:6px; }
  .saved .dot { width:8px; height:8px; border-radius:50%; background:#6fcf97; }
  .saved.saving .dot { background:var(--gold); }

  .wrap { max-width:860px; margin:0 auto; padding:20px 16px 60px; }

  /* ── sermon header ── */
  .hero { background:linear-gradient(135deg,var(--maroon),var(--maroon-2)); color:#fff; border-radius:16px; padding:26px 28px; position:relative; overflow:hidden; }
  .hero::after { content:""; position:absolute; right:-40px; top:-40px; width:170px; height:170px; border-radius:50%; background:rgba(201,168,76,.15); }
  .eyebrow { color:var(--gold-lt); font-size:11.5px; letter-spacing:.14em; text-transform:uppercase; font-weight:600; }
  .hero h1 { font-family:'Playfair Display', Georgia, serif; font-size:clamp(24px,5vw,34px); margin:6px 0 12px; line-height:1.2; }
  .chips { display:flex; flex-wrap:wrap; gap:8px; }
  .chip { background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); border-radius:99px; padding:4px 12px; font-size:13px; }
  .summary { margin:14px 0 0; font-size:14.5px; color:rgba(255,255,255,.85); max-width:62ch; position:relative; z-index:1; }

  /* ── how it works ── */
  .how { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin:16px 0 4px; }
  .how div { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:12px 14px; font-size:13.5px; color:var(--muted); }
  .how b { display:flex; align-items:center; gap:8px; color:var(--ink); font-size:14px; margin-bottom:2px; }
  .how b span { width:22px; height:22px; border-radius:50%; background:var(--gold); color:var(--maroon); font-size:12px; display:grid; place-items:center; }
  @media (max-width:640px){ .how{ grid-template-columns:1fr; } }

  .official { margin-top:14px; background:#fff8e1; border:1px solid #f0dfa0; border-radius:12px; padding:12px 16px; font-size:14px; }
  .official a { color:var(--maroon); font-weight:600; }

  /* ── note cards ── */
  .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:18px 20px; margin-top:14px; transition:border-color .15s, box-shadow .15s; }
  .card:focus-within { border-color:var(--gold); box-shadow:0 0 0 3px rgba(201,168,76,.2); }
  .card h2 { margin:0; font-size:16px; display:flex; align-items:center; gap:10px; }
  .card h2 .ico { width:30px; height:30px; border-radius:9px; background:rgba(61,12,47,.08); display:grid; place-items:center; font-size:15px; }
  .hint { margin:4px 0 10px 40px; color:var(--muted); font-size:13px; }
  .card textarea {
    width:100%; border:1px solid var(--line); border-radius:10px; background:#fdfbfc;
    padding:12px 14px; font:inherit; font-size:15px; color:var(--ink); resize:none; overflow:hidden; min-height:92px;
  }
  .card textarea:focus { outline:none; background:#fff; }
  .card textarea::placeholder { color:#b5aab3; }
  .q { margin:14px 0 6px; font-weight:600; font-size:14.5px; }
  .q:first-of-type { margin-top:4px; }
  .check { display:flex; align-items:center; gap:10px; margin-top:10px; font-size:14px; cursor:pointer; }
  .check input { width:18px; height:18px; accent-color:var(--maroon); }

  /* ── action bar ── */
  .actions { margin-top:22px; background:var(--card); border:1px solid var(--line); border-radius:14px; padding:16px 18px; }
  .actions h3 { margin:0 0 4px; font-size:15px; }
  .actions p { margin:0 0 12px; font-size:13px; color:var(--muted); }
  .btns { display:flex; flex-wrap:wrap; gap:10px; }
  button.btn { font:inherit; font-size:14px; font-weight:600; padding:10px 16px; border-radius:10px; border:1.5px solid var(--maroon); background:#fff; color:var(--maroon); cursor:pointer; }
  button.btn:hover { background:rgba(61,12,47,.06); }
  button.btn.primary { background:var(--maroon); color:#fff; }
  button.btn.primary:hover { background:var(--maroon-2); }
  button.btn.danger { border-color:#c0392b; color:#c0392b; margin-left:auto; }
  .signin { margin-top:12px; font-size:13px; color:var(--muted); }
  .signin a { color:var(--maroon); font-weight:600; }

  .toast { position:fixed; left:50%; bottom:24px; transform:translateX(-50%) translateY(20px); background:var(--maroon); color:#fff; padding:10px 18px; border-radius:10px; font-size:14px; opacity:0; pointer-events:none; transition:.25s; z-index:50; }
  .toast.show { opacity:1; transform:translateX(-50%) translateY(0); }

  .foot { text-align:center; color:var(--muted); font-size:12.5px; margin-top:26px; }

  /* ── printing: clean sheet with what they wrote ── */
  @media print {
    body { background:#fff; }
    .topbar, .how, .actions, .toast, .official, .hint { display:none !important; }
    .wrap { padding:0; max-width:none; }
    .hero, .card { box-shadow:none !important; break-inside:avoid; }
    .hero { -webkit-print-color-adjust:exact; print-color-adjust:exact; border-radius:0; }
    .card { border-color:#ccc; }
    .card textarea { border:none; border-bottom:1px solid #ccc; border-radius:0; background:#fff; padding:6px 0; min-height:56px; }
  }
</style>
</head>
<body>

<!-- ───────── sticky bar ───────── -->
<header class="topbar">
  <div class="topbar-in">
    <a class="back" href="sermons.php">← Back to sermons</a>
    <span class="grow"></span>
    <span class="saved" id="savedState" aria-live="polite"><span class="dot"></span><span id="savedText">Ready — start typing</span></span>
  </div>
</header>

<main class="wrap">

  <!-- ───────── sermon info ───────── -->
  <section class="hero">
    <div class="eyebrow">Shammah Urban Church · My Sermon Notes</div>
    <h1><?= e($s['title']) ?></h1>
    <div class="chips">
      <span class="chip">🎤 <?= e($s['speaker']) ?></span>
      <span class="chip">📅 <?= e(date('j F Y', $ts)) ?></span>
      <?php if ($s['series'] !== ''): ?><span class="chip">📖 <?= e($s['series']) ?></span><?php endif; ?>
      <?php if ($scripture !== ''): ?><span class="chip">✝ <?= e($scripture) ?></span><?php endif; ?>
    </div>
    <?php if (trim((string) $s['description']) !== ''): ?>
      <p class="summary"><?= e($s['description']) ?></p>
    <?php endif; ?>
  </section>

  <!-- ───────── how it works ───────── -->
  <div class="how" aria-label="How to use these notes">
    <div><b><span>1</span> Listen</b>Keep the sermon playing in your other tab — this page opened separately.</div>
    <div><b><span>2</span> Write</b>Click any box and type. It grows as you write. Skip any box you don't need.</div>
    <div><b><span>3</span> Keep</b>Notes save automatically on this device. Print, download or copy them anytime.</div>
  </div>

  <?php if ($notes_url !== ''): ?>
    <div class="official">📎 The church also shared notes for this message — <a href="<?= e($notes_url) ?>" target="_blank" rel="noopener noreferrer">open them here</a>.</div>
  <?php endif; ?>

  <!-- ───────── the writing boxes ───────── -->
  <form id="notesForm" onsubmit="return false" autocomplete="off">

    <section class="card">
      <h2><span class="ico">📖</span><label for="f_scripture">Key scripture</label></h2>
      <p class="hint">The Bible verse(s) the preacher read or kept returning to.</p>
      <textarea id="f_scripture" data-label="Key scripture" placeholder="e.g. <?= $scripture !== '' ? e($scripture) : 'John 3:16' ?> — write the verse or what it says"></textarea>
    </section>

    <section class="card">
      <h2><span class="ico">📝</span><label for="f_points">Main points</label></h2>
      <p class="hint">The big ideas. Tip: put each point on its own line (press Enter).</p>
      <textarea id="f_points" data-label="Main points" placeholder="1. &#10;2. &#10;3. "></textarea>
    </section>

    <section class="card">
      <h2><span class="ico">💡</span><label for="f_stood">What stood out to me</label></h2>
      <p class="hint">A line, a story, a verse — anything that spoke to you personally.</p>
      <textarea id="f_stood" data-label="What stood out to me" placeholder="This part really spoke to me because…"></textarea>
    </section>

    <section class="card">
      <h2><span class="ico">🤔</span>Reflect</h2>
      <p class="hint">Answer after the service — this is where the message becomes real life.</p>
      <p class="q"><label for="f_q1">1. What is God saying to me through this message?</label></p>
      <textarea id="f_q1" data-label="What is God saying to me through this message?" placeholder="Type your answer…"></textarea>
      <p class="q"><label for="f_q2">2. Who can I encourage or share this with?</label></p>
      <textarea id="f_q2" data-label="Who can I encourage or share this with?" placeholder="Type a name or a plan…"></textarea>
    </section>

    <section class="card">
      <h2><span class="ico">🎯</span><label for="f_action">One step I will take this week</label></h2>
      <p class="hint">Keep it small and specific so you can actually do it.</p>
      <textarea id="f_action" data-label="One step I will take this week" placeholder="This week I will…" style="min-height:64px"></textarea>
      <label class="check"><input type="checkbox" id="f_done" data-label="Step completed"> I have done this step ✔</label>
    </section>

    <section class="card">
      <h2><span class="ico">🙏</span><label for="f_prayer">Prayer points</label></h2>
      <p class="hint">What do you want to thank God for, or ask Him for?</p>
      <textarea id="f_prayer" data-label="Prayer points" placeholder="Lord, thank You for… Please help me with…"></textarea>
    </section>

  </form>

  <!-- ───────── save / share ───────── -->
  <section class="actions">
    <h3>Keep your notes</h3>
    <p>Your notes are saved automatically in this browser on this device, and are private — they are not sent to anyone.</p>
    <div class="btns">
      <button class="btn primary" id="btnPrint" type="button">🖨 Print / Save as PDF</button>
      <button class="btn" id="btnDownload" type="button">⬇ Download (.txt)</button>
      <button class="btn" id="btnCopy" type="button">📋 Copy all</button>
      <button class="btn danger" id="btnClear" type="button">Clear notes</button>
    </div>
    <?php if (!$is_logged_in): ?>
      <p class="signin">💛 Tip: <a href="login.php">log in</a> to the member portal to follow your ministries and events too.</p>
    <?php endif; ?>
  </section>

  <p class="foot">Shammah Urban Church · Pretoria, South Africa</p>
</main>

<div class="toast" id="toast" role="status"></div>

<script>
(function () {
  const SERMON_ID    = <?= (int) $s['sermon_id'] ?>;
  const TITLE        = <?= json_encode($s['title']) ?>;
  const SPEAKER      = <?= json_encode($s['speaker']) ?>;
  const DATE_TXT     = <?= json_encode(date('j F Y', $ts)) ?>;
  const KEY          = 'suc_sermon_notes_' + SERMON_ID;

  const fields   = Array.from(document.querySelectorAll('#notesForm textarea, #notesForm input[type=checkbox]'));
  const savedBox = document.getElementById('savedState');
  const savedTxt = document.getElementById('savedText');
  const toastEl  = document.getElementById('toast');

  /* localStorage can be blocked (private mode) – never let that break the page */
  const store = {
    get() { try { return JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { return {}; } },
    set(v) { try { localStorage.setItem(KEY, JSON.stringify(v)); return true; } catch (e) { return false; } },
    del() { try { localStorage.removeItem(KEY); } catch (e) {} }
  };

  function toast(msg) {
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(toast.t);
    toast.t = setTimeout(() => toastEl.classList.remove('show'), 2200);
  }

  /* Make each box grow as the person types */
  function grow(el) {
    if (el.tagName !== 'TEXTAREA') return;
    el.style.height = 'auto';
    el.style.height = (el.scrollHeight + 2) + 'px';
  }

  /* Load saved notes */
  const saved = store.get();
  fields.forEach(f => {
    if (f.type === 'checkbox') f.checked = !!saved[f.id];
    else if (saved[f.id]) f.value = saved[f.id];
    grow(f);
  });
  if (Object.values(saved).some(Boolean)) savedTxt.textContent = 'Your saved notes were restored';

  /* Auto-save while typing */
  let timer;
  function save() {
    const data = {};
    fields.forEach(f => { data[f.id] = f.type === 'checkbox' ? f.checked : f.value; });
    const ok = store.set(data);
    savedBox.classList.remove('saving');
    const t = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    savedTxt.textContent = ok ? 'Saved on this device · ' + t : 'Could not auto-save (private browsing?) — use Download';
  }
  fields.forEach(f => {
    f.addEventListener('input', () => {
      grow(f);
      savedBox.classList.add('saving');
      savedTxt.textContent = 'Saving…';
      clearTimeout(timer);
      timer = setTimeout(save, 400);
    });
  });
  window.addEventListener('resize', () => fields.forEach(grow));

  /* Build plain text of everything written */
  function asText() {
    let out = TITLE + '\n' + SPEAKER + ' · ' + DATE_TXT + '\nShammah Urban Church — My Sermon Notes\n';
    out += '='.repeat(40) + '\n\n';
    fields.forEach(f => {
      if (f.type === 'checkbox') { out += '[' + (f.checked ? 'x' : ' ') + '] ' + f.dataset.label + '\n\n'; return; }
      const v = f.value.trim();
      if (v) out += f.dataset.label.toUpperCase() + '\n' + v + '\n\n';
    });
    return out.trim() + '\n';
  }
  const hasContent = () => fields.some(f => f.type === 'checkbox' ? false : f.value.trim() !== '');

  /* Buttons */
  document.getElementById('btnPrint').addEventListener('click', () => { fields.forEach(grow); window.print(); });

  document.getElementById('btnDownload').addEventListener('click', () => {
    if (!hasContent()) { toast('Write something first, then download'); return; }
    const blob = new Blob([asText()], { type: 'text/plain;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'Notes - ' + TITLE.replace(/[^\w\- ]+/g, '') + '.txt';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    toast('Notes downloaded');
  });

  document.getElementById('btnCopy').addEventListener('click', async () => {
    if (!hasContent()) { toast('Write something first, then copy'); return; }
    try { await navigator.clipboard.writeText(asText()); toast('Copied — paste it anywhere'); }
    catch (e) { toast('Copy not available — use Download instead'); }
  });

  document.getElementById('btnClear').addEventListener('click', () => {
    if (!confirm('Clear all your notes for this sermon? This cannot be undone.')) return;
    fields.forEach(f => { if (f.type === 'checkbox') f.checked = false; else { f.value = ''; grow(f); } });
    store.del();
    savedTxt.textContent = 'Notes cleared';
    toast('Notes cleared');
  });
})();
</script>
</body>
</html>