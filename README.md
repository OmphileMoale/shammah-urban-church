# Shammah Urban Church – Setup Instructions
## Getting the project running in XAMPP + VS Code

---

### STEP 1: Copy the project folder

1. Open **File Explorer** on your PC
2. Navigate to: `C:\xampp\htdocs\`
3. Copy the **entire `shammah` folder** into `htdocs`

Your final path should be:
```
C:\xampp\htdocs\shammah\
```

---

### STEP 2: Start XAMPP

1. Open the **XAMPP Control Panel**
2. Click **Start** next to **Apache**
3. The status should turn green

---

### STEP 3: Open the site

Open your browser and go to:
```
http://localhost/shammah/
```

You should see the Shammah Urban Church home page.

---

### STEP 4: Open in VS Code

1. Open VS Code
2. Click **File → Open Folder**
3. Navigate to `C:\xampp\htdocs\shammah`
4. Click **Select Folder**

You can now edit all files. Save a file and refresh the browser to see changes.

---

### FILE STRUCTURE EXPLAINED

```
shammah/
├── index.php              ← HOME PAGE (start here)
│
├── css/
│   ├── style.css          ← Design tokens (colours) + base reset
│   ├── nav.css            ← Ticker + navigation bar styles
│   ├── hero.css           ← Hero section (big headline area)
│   └── components.css     ← Everything else (stats, events, footer…)
│
├── js/
│   ├── toast.js           ← showToast() notification function
│   ├── main.js            ← Nav scroll, hamburger, stat counter
│   └── rsvp.js            ← RSVP button → fetch → rsvp.php
│
├── includes/
│   ├── data.php           ← All page data ($events, $stats, etc.)
│   ├── header.php         ← <head> tag + CSS links
│   ├── nav.php            ← Ticker + nav bar HTML
│   └── footer.php         ← Footer + JS script tags
│
└── pages/                 ← (create your other pages here)
    ├── about.php
    ├── events.php
    └── …
```

---

### HOW TO CREATE A NEW PAGE (e.g. about.php)

```php
<?php
session_start();
require_once 'includes/data.php';

$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';

$page_title = 'About Us | Shammah Urban Church';
$page_desc  = 'Learn about our church history and vision.';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<main id="main-content">
  <!-- your page content here -->
  <section class="section" style="background:#fff">
    <div class="section-narrow">
      <h1 class="section-title">About <em>Our Church</em></h1>
      <p>Content goes here…</p>
    </div>
  </section>
</main>

<?php require_once 'includes/footer.php'; ?>
```

That's all you need — the header, nav, and footer load automatically.

---

### TROUBLESHOOTING

| Problem | Fix |
|---|---|
| White page, no styles | Check that Apache is running in XAMPP |
| 404 error | Make sure folder is named `shammah` in `htdocs` |
| PHP tags showing as text | Confirm URL starts with `http://localhost/` not `file:///` |
| Fonts not loading | You need an internet connection (fonts load from Google) |
