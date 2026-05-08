<?php
require_once BASE_PATH . '/includes/bootstrap.php';
Auth::requireRole('super_admin','admin','manager','editor','reporter');
$pageTitle = 'Write Article - FatakNews';
$catModel  = new CategoryModel();
$postModel = new PostModel();
$categories = $catModel->getTopLevel();
$categoryOptionMap = array_map(static function (array $category): array {
    return [
        'id' => (int)$category['id'],
        'name' => (string)$category['name'],
        'slug' => (string)$category['slug'],
    ];
}, $categories);
$editId     = (int)($_GET['edit'] ?? 0);
$post       = $editId ? $postModel->findById($editId) : null;
$selectedCategoryId = (int)($post['category_id'] ?? 0);
$selectedSubcategoryId = 0;
if ($selectedCategoryId > 0) {
    $selectedCategory = $catModel->findById($selectedCategoryId);
    if ($selectedCategory && !empty($selectedCategory['parent_id'])) {
        $selectedSubcategoryId = $selectedCategoryId;
        $selectedCategoryId = (int)$selectedCategory['parent_id'];
    }
}
$initialPlacement = trim((string)($_GET['placement'] ?? ($post['location'] ?? '')));
if ($initialPlacement === '') {
    $initialPlacement = 'both';
}
$dashboardUrl = Auth::isAdmin()
    ? '/admin'
    : (Auth::isManager() ? '/manager' : '/employee');
$dashboardLabel = Auth::isAdmin()
    ? 'Admin Dashboard'
    : (Auth::isManager() ? 'Manager Dashboard' : 'Dashboard');
$successRedirect = Auth::isAdmin()
    ? '/admin/news'
    : (Auth::isManager() ? '/manager/posts' : '/employee');
if ($post && $post['user_id'] != Auth::id() && !Auth::isManager()) {
    Helper::redirect('/employee');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $pageTitle ?></title>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body class="create-post-page">
<nav class="navbar" style="position:sticky;top:0;z-index:999">
  <div class="nav-container">
    <a href="/" class="nav-logo">
      <div class="logo-icon"><i class="fa fa-bolt"></i></div>
      <span class="logo-text">Fatak<strong>News</strong></span>
    </a>
    <div class="create-topactions" style="margin-left:auto;display:flex;align-items:center;gap:12px">
      <span style="font-size:13px;color:var(--muted)" id="saveDraft">Draft saved</span>
      <button type="button" id="submitDraft" class="btn-ghost">Save Draft</button>
      <button type="button" id="submitPublish" class="btn-write">Submit for Review</button>
    </div>
  </div>
</nav>

<div class="create-page">
  <div class="create-breadcrumb" style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);margin-bottom:24px">
    <a href="<?= $dashboardUrl ?>" style="color:var(--muted)"><?= $dashboardLabel ?></a>
    <i class="fa fa-chevron-right" style="font-size:10px"></i>
    <span style="color:var(--text)"><?= $editId ? 'Edit Article' : 'New Article' ?></span>
  </div>

  <form id="postForm" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <input type="hidden" name="post_id" value="<?= $editId ?>">
    <input type="hidden" name="status" id="postStatus" value="draft">

    <div class="create-layout" style="display:grid;grid-template-columns:1fr 300px;gap:28px;align-items:start">
      <div class="create-main">
        <div class="create-widget" id="ai-generator" style="margin-bottom:18px">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div>
              <strong style="display:block;font-size:15px;color:var(--text)">Generate With AI</strong>
              <span style="font-size:12px;color:var(--muted)">Topic do, aur draft editor me fill ho jayega.</span>
            </div>
            <button type="button" id="generateAiBtn" class="btn-write" style="padding:10px 16px">
              <i class="fa fa-wand-magic-sparkles"></i> Generate Draft
            </button>
          </div>
          <div class="create-ai-promptgrid" style="display:grid;grid-template-columns:1.4fr 1fr;gap:10px;margin-top:14px">
            <input type="text" id="aiTopic" class="form-control" placeholder="Topic: e.g. Delhi budget 2026 highlights">
            <input type="text" id="aiAngle" class="form-control" placeholder="Angle / brief (optional)">
          </div>
          <div class="create-ai-selectgrid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:10px">
            <select id="aiLanguage" class="form-control">
              <option value="english">English</option>
              <option value="hindi">Hindi</option>
              <option value="hinglish">Hinglish</option>
            </select>
            <select id="aiTone" class="form-control">
              <option value="standard">Standard News</option>
              <option value="breaking">Breaking Style</option>
              <option value="explainer">Explainer</option>
              <option value="analytical">Analytical</option>
            </select>
            <select id="aiWordCount" class="form-control">
              <option value="500">~500 words</option>
              <option value="700" selected>~700 words</option>
              <option value="900">~900 words</option>
              <option value="1200">~1200 words</option>
            </select>
            <select id="aiPreferredType" class="form-control">
              <option value="news">News</option>
              <option value="article">Feature</option>
              <option value="breaking">Breaking</option>
            </select>
          </div>
        </div>

        <div class="create-widget" id="ai-insights" style="margin-bottom:18px;display:none">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px">
            <div>
              <strong style="display:block;font-size:15px;color:var(--text)">AI Headline + SEO Pack</strong>
              <span style="font-size:12px;color:var(--muted)">Headline variants apply kar sakte ho, aur SEO notes review kar sakte ho.</span>
            </div>
          </div>
          <div class="create-insightsgrid" style="display:grid;grid-template-columns:1.1fr .9fr;gap:14px">
            <div>
              <div style="font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);margin-bottom:8px">Headline Variants</div>
              <div id="aiHeadlineVariants" style="display:flex;flex-direction:column;gap:8px"></div>
            </div>
            <div>
              <div style="font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);margin-bottom:8px">SEO Notes</div>
              <div id="aiSeoNotes" style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px"></div>
              <div style="font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);margin-bottom:8px">Share Blurb</div>
              <div id="aiShareBlurb" style="padding:12px 14px;border:1px solid var(--border);border-radius:14px;background:var(--bg3);font-size:14px;line-height:1.5;color:var(--text)"></div>
            </div>
          </div>
        </div>

        <input type="text" name="title" class="create-title-input"
               placeholder="Write a compelling headline..."
               value="<?= Helper::sanitize($post['title'] ?? '') ?>"
               id="postTitle" maxlength="300" required>

        <textarea name="excerpt" placeholder="Brief summary (shown in previews)..."
                  id="postExcerpt"
                  style="width:100%;background:var(--card);border:1px solid var(--border);border-bottom:none;color:var(--text);padding:14px 16px;font-size:15px;resize:none;height:72px;border-radius:var(--radius) var(--radius) 0 0"
                  maxlength="500"><?= Helper::sanitize($post['excerpt'] ?? '') ?></textarea>

        <div class="create-toolbar">
          <div class="toolbar-group">
            <button type="button" class="toolbar-btn" onclick="fmt('undo')" title="Undo"><i class="fa fa-rotate-left"></i></button>
            <button type="button" class="toolbar-btn" onclick="fmt('redo')" title="Redo"><i class="fa fa-rotate-right"></i></button>
            <button type="button" class="toolbar-btn toolbar-btn-text" onclick="fmt('removeFormat')" title="Clear formatting">CLR</button>
          </div>
          <div class="toolbar-group">
            <button type="button" class="toolbar-btn" onclick="fmt('bold')" title="Bold"><i class="fa fa-bold"></i></button>
            <button type="button" class="toolbar-btn" onclick="fmt('italic')" title="Italic"><i class="fa fa-italic"></i></button>
            <button type="button" class="toolbar-btn" onclick="fmt('underline')" title="Underline"><i class="fa fa-underline"></i></button>
            <button type="button" class="toolbar-btn" onclick="fmt('strikeThrough')" title="Strikethrough"><i class="fa fa-strikethrough"></i></button>
            <button type="button" class="toolbar-btn" onclick="highlightText()" title="Highlight"><i class="fa fa-highlighter"></i></button>
            <label class="toolbar-colorpicker" title="Text Color">
              <i class="fa fa-palette"></i>
              <input type="color" id="textColorPicker" value="#ff2d2d" onchange="applyTextColor(this.value)">
            </label>
            <button type="button" class="toolbar-btn toolbar-btn-text" onclick="resetTextColor()" title="Reset Text Color">TXT</button>
          </div>
          <div class="toolbar-group">
            <button type="button" class="toolbar-btn toolbar-btn-text" onclick="fmtBlock('p')" title="Paragraph">P</button>
            <button type="button" class="toolbar-btn toolbar-btn-text" onclick="fmtBlock('h2')" title="Heading 2">H2</button>
            <button type="button" class="toolbar-btn toolbar-btn-text" onclick="fmtBlock('h3')" title="Heading 3">H3</button>
            <button type="button" class="toolbar-btn toolbar-btn-text" onclick="fmtBlock('h4')" title="Heading 4">H4</button>
            <button type="button" class="toolbar-btn" onclick="fmtBlock('blockquote')" title="Quote"><i class="fa fa-quote-left"></i></button>
            <button type="button" class="toolbar-btn" onclick="fmtBlock('pre')" title="Code block"><i class="fa fa-code"></i></button>
          </div>
          <div class="toolbar-group">
            <button type="button" class="toolbar-btn" onclick="insertList('ul')" title="Bullet List"><i class="fa fa-list-ul"></i></button>
            <button type="button" class="toolbar-btn" onclick="insertList('ol')" title="Numbered List"><i class="fa fa-list-ol"></i></button>
            <button type="button" class="toolbar-btn" onclick="fmt('justifyLeft')" title="Align Left"><i class="fa fa-align-left"></i></button>
            <button type="button" class="toolbar-btn" onclick="fmt('justifyCenter')" title="Align Center"><i class="fa fa-align-center"></i></button>
            <button type="button" class="toolbar-btn" onclick="insertDivider()" title="Divider"><i class="fa fa-minus"></i></button>
          </div>
          <div class="toolbar-group">
            <button type="button" class="toolbar-btn toolbar-btn-text toolbar-btn-wide" onclick="insertKeyPoints()" title="Insert Key Points">KP</button>
            <button type="button" class="toolbar-btn toolbar-btn-text toolbar-btn-wide" onclick="insertFactBox()" title="Insert Fact Box">NOTE</button>
            <button type="button" class="toolbar-btn toolbar-btn-text toolbar-btn-wide" onclick="insertFaqBlock()" title="Insert FAQ block">FAQ</button>
            <button type="button" class="toolbar-btn toolbar-btn-text toolbar-btn-wide" onclick="openCtaPanel()" title="Insert CTA Button">BTN</button>
          </div>
          <div class="toolbar-group">
            <button type="button" class="toolbar-btn" onclick="openLinkPanel()" title="Insert Link"><i class="fa fa-link"></i></button>
            <button type="button" class="toolbar-btn" onclick="fmt('unlink')" title="Remove Link"><i class="fa fa-link-slash"></i></button>
            <button type="button" class="toolbar-btn" onclick="document.getElementById('inlineImg').click()" title="Insert Image"><i class="fa fa-image"></i></button>
            <input type="file" id="inlineImg" accept="image/*" style="display:none" onchange="insertImage(this)">
          </div>
        </div>

        <div class="create-toolbar-panels">
          <div class="toolbar-inline-panel" id="linkPanel" hidden>
            <div class="toolbar-inline-panel-head">
              <strong>Insert Link</strong>
              <button type="button" class="toolbar-inline-close" onclick="closeInlinePanels()"><i class="fa fa-times"></i></button>
            </div>
            <div class="toolbar-inline-grid">
              <input type="text" id="linkTextInput" class="form-control" placeholder="Link text (optional if text selected)">
              <input type="url" id="linkUrlInput" class="form-control" placeholder="https://example.com">
            </div>
            <div class="toolbar-inline-actions">
              <button type="button" class="btn-write" onclick="insertLinkFromPanel()">Insert Link</button>
              <button type="button" class="btn-ghost" onclick="closeInlinePanels()">Cancel</button>
            </div>
          </div>

          <div class="toolbar-inline-panel" id="ctaPanel" hidden>
            <div class="toolbar-inline-panel-head">
              <strong>Insert Button</strong>
              <button type="button" class="toolbar-inline-close" onclick="closeInlinePanels()"><i class="fa fa-times"></i></button>
            </div>
            <div class="toolbar-inline-grid toolbar-inline-grid--cta">
              <input type="text" id="ctaTextInput" class="form-control" placeholder="Button text">
              <input type="url" id="ctaUrlInput" class="form-control" placeholder="https://example.com">
              <label class="toolbar-inline-color">
                <span>Background</span>
                <input type="color" id="ctaBgInput" value="#FF2D55">
              </label>
              <label class="toolbar-inline-color">
                <span>Text</span>
                <input type="color" id="ctaTextColorInput" value="#FFFFFF">
              </label>
            </div>
            <div class="toolbar-inline-actions">
              <button type="button" class="btn-write" onclick="insertCtaButtonFromPanel()">Insert Button</button>
              <button type="button" class="btn-ghost" onclick="closeInlinePanels()">Cancel</button>
            </div>
          </div>
        </div>

        <div id="editor" class="create-editor" contenteditable="true"
             data-placeholder="Start writing your story..."
             style="min-height:500px"><?= $post ? $post['content'] : '' ?></div>
        <input type="hidden" name="content" id="contentInput">

      </div>

      <div class="create-side">
        <div class="create-widget">
          <label>Cover Image</label>
          <div class="create-coverdrop" style="position:relative;border:2px dashed var(--border);border-radius:var(--radius);overflow:hidden;cursor:pointer;aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;background:var(--bg3)" onclick="document.getElementById('thumbInput').click()">
            <img id="thumbPreview" src="<?= $post && $post['thumbnail'] ? Helper::thumbnailUrl($post['thumbnail']) : '' ?>"
                 style="<?= $post && $post['thumbnail'] ? 'display:block' : 'display:none' ?>;width:100%;height:100%;object-fit:cover;position:absolute;inset:0">
            <div id="thumbPlaceholder" style="<?= $post && $post['thumbnail'] ? 'display:none' : '' ?>;text-align:center;color:var(--muted)">
              <i class="fa fa-cloud-upload-alt" style="font-size:28px;margin-bottom:8px"></i>
              <div style="font-size:13px">Click to upload image</div>
              <div style="font-size:11px">JPG, PNG, WebP | Max 5MB</div>
            </div>
          </div>
          <input type="file" id="thumbInput" name="thumbnail" accept="image/*" style="display:none"
                 data-preview="thumbPreview" onchange="this.closest('.create-widget').querySelector('#thumbPlaceholder').style.display='none'">
          <input type="text" name="image_alt" id="imageAlt" class="form-control" style="margin-top:10px"
                 placeholder="Image alt text for SEO/accessibility"
                 maxlength="255"
                 value="<?= Helper::sanitize($post['image_alt'] ?? '') ?>">
          <div style="font-size:11px;color:var(--muted);margin-top:6px">
            Blank chhodo to article title automatically alt text ban jayega.
          </div>
        </div>

        <div class="create-widget">
          <label>Placement</label>
          <select name="location" class="form-control" id="postPlacement">
            <option value="both" <?= $initialPlacement === 'both' ? 'selected' : '' ?>>Home + Category</option>
            <option value="home" <?= $initialPlacement === 'home' ? 'selected' : '' ?>>Home only</option>
            <option value="category" <?= $initialPlacement === 'category' ? 'selected' : '' ?>>Category only</option>
            <option value="explore" <?= $initialPlacement === 'explore' ? 'selected' : '' ?>>Explore page</option>
          </select>
          <div id="placementHint" style="font-size:11px;color:var(--muted);margin-top:6px">
            Select karo article homepage par dikhega, category page par, dono par, ya Explore stream me.
          </div>
        </div>

        <div class="create-widget">
          <label>Category *</label>
          <select name="category_id" class="form-control" required id="catSelect">
            <option value="">Select category...</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $selectedCategoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= Helper::sanitize($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="subcategory_id" class="form-control" style="margin-top:8px" id="subCatSelect">
            <option value="">Select subcategory (optional)...</option>
          </select>
        </div>

        <div class="create-widget">
          <label>Post Type</label>
          <select name="type" class="form-control">
            <option value="news" <?= ($post['type'] ?? '') === 'news' ? 'selected' : '' ?>>News Article</option>
            <option value="article" <?= ($post['type'] ?? '') === 'article' ? 'selected' : '' ?>>Feature Article</option>
            <option value="breaking" <?= ($post['type'] ?? '') === 'breaking' ? 'selected' : '' ?>>Breaking News</option>
          </select>
        </div>

        <div class="create-widget" id="exploreMediaWidget">
          <label>Media / Embed</label>
          <div id="exploreMediaHint" style="font-size:12px;color:var(--muted);line-height:1.5;margin-bottom:12px">
            Standard posts ke liye source details optional hain. Explore ke liye media/embed optional hai.
          </div>
          <div class="form-group" style="margin:0 0 10px">
            <label id="sourceNameLabel">Source or Platform Name</label>
            <input type="text" name="source_name" class="form-control" id="sourceNameInput" placeholder="PTI, ANI, Instagram, X, YouTube"
                   value="<?= Helper::sanitize($post['source_name'] ?? '') ?>">
          </div>
          <div class="form-group" style="margin:0 0 10px">
            <label id="sourceUrlLabel">Social Post URL</label>
            <input type="url" name="source_url" class="form-control" id="sourceUrlInput" placeholder="https://instagram.com/... or https://x.com/..."
                   value="<?= Helper::sanitize($post['source_url'] ?? '') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label>YouTube URL</label>
            <input type="url" name="video_url" class="form-control" id="videoUrlInput" placeholder="https://youtube.com/watch?v=..."
                   value="<?= Helper::sanitize($post['video_url'] ?? '') ?>">
          </div>
        </div>

        <div class="create-widget">
          <label>Tags</label>
          <input type="text" name="tags" id="postTags" class="form-control" placeholder="politics, modi, election (comma separated)"
                 value="<?= isset($post) ? '' : '' ?>">
          <div style="font-size:11px;color:var(--muted);margin-top:6px">Separate tags with commas</div>
        </div>

        <div class="create-widget">
          <label>Custom Slug <span style="color:var(--muted);font-weight:400">(optional)</span></label>
          <input type="text" name="slug" id="postSlug" class="form-control"
                 placeholder="leave blank for auto-slug"
                 value="<?= Helper::sanitize($post['slug'] ?? '') ?>">
          <div style="font-size:11px;color:var(--muted);margin-top:6px">
            English letters, numbers, aur hyphens best rahenge. Blank chhodo to title se auto-generate hoga.
          </div>
          <div id="slugPreview" style="font-size:11px;color:var(--muted);margin-top:8px;word-break:break-all"></div>
        </div>

        <div class="create-widget">
          <label>Options</label>
          <div class="create-options" style="display:flex;flex-direction:column;gap:10px">
            <?php
              $checks = [
                ['is_featured','Mark as Featured'],
                ['is_breaking','Mark as Breaking News'],
                ['allow_comments','Allow Comments'],
              ];
              foreach ($checks as [$name, $label]):
                $checked = isset($post) ? (bool)$post[$name] : ($name === 'allow_comments');
            ?>
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;font-weight:400">
              <input type="checkbox" name="<?= $name ?>" value="1" <?= $checked ? 'checked' : '' ?>
                     style="accent-color:var(--red);width:16px;height:16px">
              <?= $label ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="create-widget">
          <label>SEO</label>
          <input type="text" name="seo_title" id="seoTitle" class="form-control" placeholder="SEO Title (optional)"
                 style="margin-bottom:8px" maxlength="300"
                 value="<?= Helper::sanitize($post['seo_title'] ?? '') ?>">
          <textarea name="seo_description" id="seoDescription" class="form-control" placeholder="Meta description..." rows="3"
                    maxlength="500"><?= Helper::sanitize($post['seo_description'] ?? '') ?></textarea>
          <div style="font-size:11px;color:var(--muted);margin-top:6px">
            Blank fields save ke time auto-generate ho jayenge based on headline, excerpt, category, aur article content.
          </div>
        </div>

        <div class="create-widget" id="seoHealthCard">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px">
            <div>
              <label style="margin-bottom:4px">SEO Health</label>
              <div id="seoHealthSummary" style="font-size:14px;font-weight:700;color:var(--text)">Checking...</div>
            </div>
            <div id="seoHealthMeta" style="font-size:11px;color:var(--muted);text-align:right;line-height:1.5"></div>
          </div>
          <div id="seoHealthList" style="display:flex;flex-direction:column;gap:8px"></div>
        </div>

        <div class="create-meta-card" style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:14px;font-size:13px;color:var(--muted)">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px">
            <span>Words</span><strong id="wordCount" style="color:var(--text)">0</strong>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span>Reading time</span><strong id="readingTime" style="color:var(--text)">< 1 min</strong>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<div class="toast-container" id="toastContainer"></div>
<script>
const APP = { url:'<?= Helper::appUrl() ?>', csrfToken:'<?= Csrf::token() ?>', userId:<?= Auth::id() ?>, isLoggedIn:true };
const CATEGORY_OPTIONS = <?= json_encode($categoryOptionMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/public/assets/js/app.js"></script>
<script>
const CREATE_POST_REDIRECT = '<?= Helper::sanitize($successRedirect) ?>';
const editor = document.getElementById('editor');
const titleInput = document.getElementById('postTitle');
const excerptInput = document.getElementById('postExcerpt');
const tagsInput = document.getElementById('postTags');
const postTypeInput = document.querySelector('select[name="type"]');
const placementInput = document.getElementById('postPlacement');
const seoTitleInput = document.getElementById('seoTitle');
const seoDescriptionInput = document.getElementById('seoDescription');
const imageAltInput = document.getElementById('imageAlt');
const categorySelect = document.getElementById('catSelect');
const subCategorySelect = document.getElementById('subCatSelect');
const slugInput = document.getElementById('postSlug');
const postIdInput = document.querySelector('input[name="post_id"]');
const postForm = document.getElementById('postForm');
const saveDraftStatus = document.getElementById('saveDraft');
let isDraftSaveInFlight = false;
let hasPendingDraftChanges = false;
let lastSavedDraftSignature = '';
const slugPreview = document.getElementById('slugPreview');
const thumbInput = document.getElementById('thumbInput');
const thumbPreview = document.getElementById('thumbPreview');
const aiInsightsCard = document.getElementById('ai-insights');
const aiHeadlineVariants = document.getElementById('aiHeadlineVariants');
const aiSeoNotes = document.getElementById('aiSeoNotes');
const aiShareBlurb = document.getElementById('aiShareBlurb');
const placementHint = document.getElementById('placementHint');
const exploreMediaHint = document.getElementById('exploreMediaHint');
const sourceNameLabel = document.getElementById('sourceNameLabel');
const sourceUrlLabel = document.getElementById('sourceUrlLabel');
const sourceNameInput = document.getElementById('sourceNameInput');
const sourceUrlInput = document.getElementById('sourceUrlInput');
const videoUrlInput = document.getElementById('videoUrlInput');
const csrfField = document.querySelector('#postForm input[name="csrf_token"]');
const toolbar = document.querySelector('.create-toolbar');
const linkPanel = document.getElementById('linkPanel');
const ctaPanel = document.getElementById('ctaPanel');
const linkTextInput = document.getElementById('linkTextInput');
const linkUrlInput = document.getElementById('linkUrlInput');
const ctaTextInput = document.getElementById('ctaTextInput');
const ctaUrlInput = document.getElementById('ctaUrlInput');
const ctaBgInput = document.getElementById('ctaBgInput');
const ctaTextColorInput = document.getElementById('ctaTextColorInput');
const seoHealthSummary = document.getElementById('seoHealthSummary');
const seoHealthMeta = document.getElementById('seoHealthMeta');
const seoHealthList = document.getElementById('seoHealthList');
let editorSelectionRange = null;
let seoCheckTimer = null;
let seoCheckSequence = 0;

function syncCsrfToken(token) {
  if (!token) return;
  APP.csrfToken = token;
  if (csrfField) {
    csrfField.value = token;
  }
}

function refreshEditorMeta() {
  const words = editor.innerText.trim().split(/\s+/).filter(Boolean).length;
  document.getElementById('wordCount').textContent = words;
  document.getElementById('readingTime').textContent = Math.max(1, Math.ceil(words/200)) + ' min';
}

function slugifyPreview(value) {
  return String(value || '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/[\s-]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function refreshSlugPreview() {
  const entered = slugifyPreview(slugInput.value);
  const derived = slugifyPreview(titleInput.value);
  const activeSlug = entered || derived || 'post';
  slugPreview.textContent = `Slug preview: ${activeSlug}`;
}

function hasCoverImage() {
  return Boolean((thumbInput?.files && thumbInput.files.length > 0) || (thumbPreview?.getAttribute('src') || '').trim());
}

function buildSeoItem(type, message) {
  return { type, message };
}

function renderSeoHealth(items, meta = {}) {
  const errorCount = items.filter((item) => item.type === 'error').length;
  const warnCount = items.filter((item) => item.type === 'warn').length;
  const infoCount = items.filter((item) => item.type === 'info').length;
  let summary = 'SEO looks healthy';
  let summaryColor = '#00C853';

  if (errorCount > 0) {
    summary = `${errorCount} critical issue${errorCount > 1 ? 's' : ''} to fix`;
    summaryColor = '#FF5A5F';
  } else if (warnCount > 0) {
    summary = `${warnCount} warning${warnCount > 1 ? 's' : ''} to review`;
    summaryColor = '#FF9F1A';
  } else if (infoCount > 0) {
    summary = `${infoCount} optimization tip${infoCount > 1 ? 's' : ''} available`;
    summaryColor = '#4B7BFF';
  }

  seoHealthSummary.textContent = summary;
  seoHealthSummary.style.color = summaryColor;
  seoHealthMeta.innerHTML = `Title: <strong style="color:var(--text)">${meta.titleLength ?? 0}</strong> chars<br>Meta: <strong style="color:var(--text)">${meta.metaLength ?? 0}</strong> chars`;
  seoHealthList.innerHTML = '';

  if (!items.length) {
    const empty = document.createElement('div');
    empty.style.padding = '10px 12px';
    empty.style.border = '1px solid var(--border)';
    empty.style.borderRadius = '12px';
    empty.style.background = 'var(--bg3)';
    empty.style.fontSize = '13px';
    empty.style.color = 'var(--muted)';
    empty.textContent = 'No SEO issues detected.';
    seoHealthList.appendChild(empty);
    return;
  }

  items.forEach((item) => {
    const icon = item.type === 'error' ? 'fa-circle-exclamation' : (item.type === 'warn' ? 'fa-triangle-exclamation' : 'fa-circle-info');
    const color = item.type === 'error' ? '#FF5A5F' : (item.type === 'warn' ? '#FF9F1A' : '#4B7BFF');
    const card = document.createElement('div');
    card.style.display = 'flex';
    card.style.alignItems = 'flex-start';
    card.style.gap = '10px';
    card.style.padding = '10px 12px';
    card.style.borderRadius = '12px';
    card.style.border = `1px solid ${color}33`;
    card.style.background = `${color}12`;
    card.style.fontSize = '13px';
    card.style.lineHeight = '1.5';
    card.style.color = 'var(--text)';
    card.innerHTML = `<i class="fa ${icon}" style="color:${color};margin-top:2px"></i><span>${escapeHtml(item.message)}</span>`;
    seoHealthList.appendChild(card);
  });
}

async function refreshSeoHealth() {
  const title = titleInput.value.trim();
  const seoTitle = seoTitleInput.value.trim();
  const metaDescription = seoDescriptionInput.value.trim();
  const excerpt = excerptInput.value.trim();
  const imageAlt = imageAltInput.value.trim();
  const customSlug = slugifyPreview(slugInput.value);
  const effectiveSlug = customSlug || slugifyPreview(title) || 'post';
  const isExplore = (placementInput?.value || '') === 'explore';
  const isCommunityType = ['community_post', 'thought'].includes(postTypeInput?.value || '');
  const hasInternalLink = /<a\b/i.test(editor.innerHTML);
  const hasSectionHeading = /<h2\b|<h3\b/i.test(editor.innerHTML);
  const items = [];
  const titleLength = (seoTitle || title).length;
  const metaLength = (metaDescription || excerpt).length;
  const wordCount = editor.innerText.trim().split(/\s+/).filter(Boolean).length;

  if (!title) {
    items.push(buildSeoItem('error', 'Headline missing hai. Search result aur slug dono ke liye title required hai.'));
  } else if (title.length < 45) {
    items.push(buildSeoItem('warn', 'Headline thoda short hai. 45-70 characters range search CTR ke liye better rehti hai.'));
  } else if (title.length > 70) {
    items.push(buildSeoItem('warn', 'Headline long ho raha hai. 70 characters ke andar rakho to SERP truncation kam hota hai.'));
  }

  if (!seoTitle) {
    items.push(buildSeoItem('info', 'Custom SEO title blank hai. Publish par normal headline hi use hogi.'));
  } else if (seoTitle.length < 50 || seoTitle.length > 65) {
    items.push(buildSeoItem('warn', 'SEO title ideal 50-65 characters ke beech rakho.'));
  }

  if (!metaDescription) {
    items.push(buildSeoItem('warn', 'Meta description blank hai. Excerpt fallback hoga, but custom description better rank/CTR de sakta hai.'));
  } else if (metaDescription.length < 140 || metaDescription.length > 160) {
    items.push(buildSeoItem('warn', 'Meta description ideal 140-160 characters ke beech rakho.'));
  }

  if (!isCommunityType && !categorySelect.value) {
    items.push(buildSeoItem('error', 'Category required hai. Topic relevance aur sitemap grouping dono ke liye ye zaruri hai.'));
  }

  if (!isCommunityType && !isExplore && !hasCoverImage()) {
    items.push(buildSeoItem('error', 'Standard article/news post ke liye cover image missing hai.'));
  }

  if (hasCoverImage() && !imageAlt) {
    items.push(buildSeoItem('warn', 'Cover image alt text blank hai. Accessible aur image SEO ke liye alt text add karo.'));
  }

  if (effectiveSlug.length > 75) {
    items.push(buildSeoItem('warn', 'Slug kaafi long hai. Short aur readable slug SEO ke liye better hota hai.'));
  }

  if (wordCount > 0 && wordCount < 250 && !isExplore) {
    items.push(buildSeoItem('warn', 'Article body short hai. 250+ words se topic coverage stronger lagti hai.'));
  }

  if (!isExplore && !isCommunityType && wordCount >= 300 && !hasSectionHeading) {
    items.push(buildSeoItem('info', 'Long article me subheadings add karo. H2/H3 structure readability aur SEO dono improve karta hai.'));
  }

  if (!isExplore && !isCommunityType && wordCount >= 250 && !hasInternalLink) {
    items.push(buildSeoItem('warn', 'Article body me koi internal link nahi mila. Related story, category page, ya topic link add karo.'));
  }

  if (!isExplore && !isCommunityType && !tagsInput.value.trim()) {
    items.push(buildSeoItem('info', '2-5 relevant tags add karoge to topic discovery aur related-story linking better hogi.'));
  }

  const requestId = ++seoCheckSequence;
  clearTimeout(seoCheckTimer);
  seoCheckTimer = setTimeout(async () => {
    try {
      const params = new URLSearchParams({
        mode: 'seo-check',
        post_id: String(<?= $editId ?>),
        title,
        slug: customSlug,
        excerpt,
        content: editor.innerText.trim(),
        category_id: String(subCategorySelect?.value || categorySelect?.value || 0),
      });
      const response = await fetch(resolveAppUrl(`/api/posts?${params.toString()}`), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const payload = await response.json();
      if (requestId !== seoCheckSequence || !payload?.success) {
        return;
      }

      if (payload.title_conflicts > 0) {
        items.push(buildSeoItem('warn', `Isi title ke ${payload.title_conflicts} aur post already maujood hain. Headline ko thoda distinct banao.`));
      }

      if (payload.custom_slug_conflict) {
        items.push(buildSeoItem('warn', `Ye custom slug already taken hai. Save hote waqt slug '${payload.final_slug}' ban jayega.`));
      } else if (payload.final_slug && payload.final_slug !== effectiveSlug) {
        items.push(buildSeoItem('info', `Final saved slug '${payload.final_slug}' hoga.`));
      }

      if (!excerpt && payload.recommended_excerpt) {
        items.push(buildSeoItem('info', 'Excerpt blank hai. Save ke time short summary auto-fill ho jayegi.'));
      }

      if (!seoTitle && payload.recommended_seo_title) {
        items.push(buildSeoItem('info', `SEO title auto-generate hoga: "${payload.recommended_seo_title}"`));
      }

      if (!metaDescription && payload.recommended_seo_description) {
        items.push(buildSeoItem('info', 'Meta description blank hai. Save ke time optimized description auto-fill ho jayegi.'));
      }

      if (hasCoverImage() && !imageAlt && payload.recommended_image_alt) {
        items.push(buildSeoItem('info', `Image alt auto-fill hoga: "${payload.recommended_image_alt}"`));
      }

      renderSeoHealth(items, { titleLength, metaLength });
    } catch (error) {
      renderSeoHealth(items, { titleLength, metaLength });
    }
  }, 350);

  renderSeoHealth(items, { titleLength, metaLength });
}

function syncPlacementUI() {
  const placement = placementInput?.value || 'both';
  const isExplore = placement === 'explore';
  if (placementHint) {
    if (placement === 'home') {
      placementHint.textContent = 'Ye article sirf home feed aur homepage sections me show hoga.';
    } else if (placement === 'category') {
      placementHint.textContent = 'Ye article All home feed me nahi aayega, lekin apni category page aur homepage ke category tab me show hoga.';
    } else if (placement === 'both') {
      placementHint.textContent = 'Ye article home feed aur category dono jagah show hoga.';
    } else {
      placementHint.textContent = 'Explore items dedicated Explore page me show honge. Social URL ya YouTube URL dena optional hai.';
    }
  }
  if (exploreMediaHint) {
    exploreMediaHint.textContent = isExplore
      ? 'Instagram/X/TikTok post URL ya YouTube URL optional hai. Agar doge to Explore card embed/media render karega.'
      : 'Source fields optional hain. Normal article placements ke liye cover image aur category enough hai.';
  }
  if (sourceNameLabel) sourceNameLabel.textContent = isExplore ? 'Platform / Source Name' : 'Source or Platform Name';
  if (sourceUrlLabel) sourceUrlLabel.textContent = isExplore ? 'Social Post URL' : 'Source URL';
  if (sourceNameInput) sourceNameInput.placeholder = isExplore ? 'Instagram, X, TikTok, YouTube' : 'PTI, ANI, Reuters';
  if (sourceUrlInput) sourceUrlInput.placeholder = isExplore ? 'https://instagram.com/... or https://x.com/...' : 'https://...';
  if (videoUrlInput) videoUrlInput.placeholder = isExplore ? 'https://youtube.com/watch?v=...' : 'Optional video URL';
}

editor.addEventListener('focus', () => { if (!editor.textContent.trim()) editor.innerHTML = ''; });
editor.addEventListener('blur',  () => { if (!editor.textContent.trim()) editor.innerHTML = ''; });
editor.addEventListener('input', refreshEditorMeta);
editor.addEventListener('input', refreshSeoHealth);
editor.addEventListener('keyup', saveEditorSelection);
editor.addEventListener('mouseup', saveEditorSelection);
document.addEventListener('selectionchange', saveEditorSelection);
toolbar?.addEventListener('mousedown', (event) => {
  if (event.target.closest('button, label')) {
    event.preventDefault();
  }
});
titleInput.addEventListener('input', refreshSlugPreview);
slugInput.addEventListener('input', refreshSlugPreview);
[
  titleInput,
  excerptInput,
  seoTitleInput,
  seoDescriptionInput,
  imageAltInput,
  categorySelect,
  subCategorySelect,
  postTypeInput,
  placementInput,
  slugInput
].forEach((input) => input?.addEventListener('input', refreshSeoHealth));
[
  categorySelect,
  subCategorySelect,
  postTypeInput,
  placementInput
].forEach((input) => input?.addEventListener('change', refreshSeoHealth));
thumbInput?.addEventListener('change', refreshSeoHealth);
placementInput?.addEventListener('change', syncPlacementUI);
refreshEditorMeta();
refreshSlugPreview();
syncPlacementUI();
refreshSeoHealth();

function saveEditorSelection() {
  const selection = window.getSelection();
  if (!selection || selection.rangeCount === 0) return;

  const range = selection.getRangeAt(0);
  if (!editor.contains(range.commonAncestorContainer)) return;
  editorSelectionRange = range.cloneRange();
}

function restoreEditorSelection() {
  if (!editorSelectionRange) return;
  const selection = window.getSelection();
  if (!selection) return;
  selection.removeAllRanges();
  selection.addRange(editorSelectionRange);
}

function getSavedSelectionText() {
  if (!editorSelectionRange) return '';
  const fragment = editorSelectionRange.cloneContents();
  return (fragment.textContent || '').trim();
}

function closeInlinePanels() {
  linkPanel.hidden = true;
  ctaPanel.hidden = true;
}

function openLinkPanel() {
  saveEditorSelection();
  closeInlinePanels();
  linkPanel.hidden = false;
  linkTextInput.value = getSavedSelectionText();
  linkUrlInput.value = '';
  linkUrlInput.focus();
}

function openCtaPanel() {
  saveEditorSelection();
  closeInlinePanels();
  ctaPanel.hidden = false;
  ctaTextInput.value = getSavedSelectionText();
  ctaUrlInput.value = '';
  ctaBgInput.value = '#FF2D55';
  ctaTextColorInput.value = '#FFFFFF';
  ctaTextInput.focus();
}

function focusEditor() {
  editor.focus();
  restoreEditorSelection();
  refreshEditorMeta();
}

function fmt(cmd, value = null) {
  document.execCommand(cmd, false, value);
  focusEditor();
}

function fmtBlock(tag) {
  document.execCommand('formatBlock', false, `<${tag}>`);
  focusEditor();
}

function insertList(type) {
  document.execCommand(type === 'ul' ? 'insertUnorderedList' : 'insertOrderedList');
  focusEditor();
}

function insertHtml(html) {
  focusEditor();
  const selection = window.getSelection();
  let range = null;

  if (selection && selection.rangeCount > 0) {
    const activeRange = selection.getRangeAt(0);
    if (editor.contains(activeRange.commonAncestorContainer)) {
      range = activeRange.cloneRange();
    }
  }

  if (!range && editorSelectionRange) {
    range = editorSelectionRange.cloneRange();
  }

  if (!range) {
    range = document.createRange();
    range.selectNodeContents(editor);
    range.collapse(false);
  }

  range.deleteContents();
  const fragment = range.createContextualFragment(html);
  const lastNode = fragment.lastChild;
  range.insertNode(fragment);

  const nextRange = document.createRange();
  if (lastNode) {
    nextRange.setStartAfter(lastNode);
  } else {
    nextRange.selectNodeContents(editor);
    nextRange.collapse(false);
  }
  nextRange.collapse(true);

  if (selection) {
    selection.removeAllRanges();
    selection.addRange(nextRange);
  }

  editorSelectionRange = nextRange.cloneRange();
  refreshEditorMeta();
}

function insertDivider() {
  insertHtml('<hr class="editor-divider"><p></p>');
}

function highlightText() {
  document.execCommand('styleWithCSS', false, true);
  document.execCommand('backColor', false, '#FFF1A8');
  focusEditor();
}

function applyTextColor(color) {
  if (!color) return;
  document.execCommand('styleWithCSS', false, true);
  document.execCommand('foreColor', false, color);
  focusEditor();
}

function resetTextColor() {
  document.execCommand('styleWithCSS', false, true);
  document.execCommand('foreColor', false, '#F0F0F5');
  focusEditor();
}

function insertKeyPoints() {
  insertHtml(
    '<section class="editor-keypoints">' +
      '<h3>Key Points</h3>' +
      '<ul>' +
        '<li>Sabse important point yahan likho.</li>' +
        '<li>Doosra strong takeaway yahan likho.</li>' +
        '<li>Teesra summary point yahan likho.</li>' +
      '</ul>' +
    '</section><p></p>'
  );
}

function insertFactBox() {
  insertHtml(
    '<aside class="editor-note">' +
      '<strong>Quick Take</strong>' +
      '<p>Yahan short fact box, context, ya important warning likho.</p>' +
    '</aside><p></p>'
  );
}

function insertFaqBlock() {
  insertHtml(
    '<section class="editor-faq">' +
      '<h3>FAQ</h3>' +
      '<p><strong>Sawal:</strong> Yahan question likho.</p>' +
      '<p><strong>Jawab:</strong> Yahan crisp answer likho.</p>' +
    '</section><p></p>'
  );
}

function insertCtaButtonFromPanel() {
  const label = ctaTextInput.value.trim();
  const url = ctaUrlInput.value.trim();
  const bgColor = ctaBgInput.value || '#FF2D55';
  const textColor = ctaTextColorInput.value || '#FFFFFF';

  if (!label || !url) {
    Toast.show('Button text aur link dono required hain.', 'info');
    return;
  }

  insertHtml(
    '<p class="editor-cta-wrap">' +
      `<a class="editor-cta-button" href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" style="background:${escapeHtml(bgColor)};color:${escapeHtml(textColor)};border-color:${escapeHtml(bgColor)}">` +
        `${escapeHtml(label)}` +
      '</a>' +
    '</p><p></p>'
  );
  closeInlinePanels();
}

function insertLinkFromPanel() {
  const url = linkUrlInput.value.trim();
  const customLabel = linkTextInput.value.trim();
  if (!url) {
    Toast.show('Link URL required hai.', 'info');
    return;
  }

  const selectedText = getSavedSelectionText();
  const label = customLabel || selectedText || url;
  insertHtml(`<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(label)}</a>`);
  closeInlinePanels();
  focusEditor();
}

function insertImage(input) {
  if (!input.files[0]) return;
  const derivedAlt = input.files[0].name
    .replace(/\.[^.]+$/, '')
    .replace(/[-_]+/g, ' ')
    .trim();
  const reader = new FileReader();
  reader.onload = e => {
    insertHtml(`<img src="${e.target.result}" alt="${escapeHtml(derivedAlt)}">`);
  };
  reader.readAsDataURL(input.files[0]);
  input.value = '';
}

[linkTextInput, linkUrlInput].forEach((input) => {
  input?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      insertLinkFromPanel();
    } else if (event.key === 'Escape') {
      closeInlinePanels();
      focusEditor();
    }
  });
});

[ctaTextInput, ctaUrlInput, ctaBgInput, ctaTextColorInput].forEach((input) => {
  input?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      insertCtaButtonFromPanel();
    } else if (event.key === 'Escape') {
      closeInlinePanels();
      focusEditor();
    }
  });
});

function renderAiInsights(draft) {
  const headlineVariants = Array.isArray(draft.headline_variants) ? draft.headline_variants.filter(Boolean) : [];
  const seoNotes = Array.isArray(draft.seo_notes) ? draft.seo_notes.filter(Boolean) : [];
  const shareBlurb = (draft.share_blurb || '').trim();

  if (!headlineVariants.length && !seoNotes.length && !shareBlurb) {
    aiInsightsCard.style.display = 'none';
    return;
  }

  aiHeadlineVariants.innerHTML = '';
  headlineVariants.forEach((headline, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn-ghost';
    button.style.width = '100%';
    button.style.textAlign = 'left';
    button.style.justifyContent = 'flex-start';
    button.style.padding = '12px 14px';
    button.style.lineHeight = '1.45';
    const label = document.createElement('strong');
    label.style.marginRight = '8px';
    label.style.color = 'var(--red)';
    label.textContent = `Option ${index + 1}`;
    const text = document.createElement('span');
    text.textContent = headline;
    button.append(label, text);
    button.addEventListener('click', () => {
      titleInput.value = headline;
      if (!seoTitleInput.value.trim()) {
        seoTitleInput.value = headline;
      }
      refreshSlugPreview();
      titleInput.focus();
      Toast.show('Headline applied.', 'success', 1500);
    });
    aiHeadlineVariants.appendChild(button);
  });

  aiSeoNotes.innerHTML = '';
  seoNotes.forEach((note) => {
    const item = document.createElement('div');
    item.style.padding = '10px 12px';
    item.style.border = '1px solid var(--border)';
    item.style.borderRadius = '12px';
    item.style.background = 'var(--bg3)';
    item.style.fontSize = '13px';
    item.style.lineHeight = '1.45';
    item.style.color = 'var(--text)';
    item.textContent = note;
    aiSeoNotes.appendChild(item);
  });

  aiShareBlurb.textContent = shareBlurb || 'Share-ready summary unavailable for this draft.';
  aiInsightsCard.style.display = 'block';
}

// Category -> Subcategory
async function loadSubcategories(parentId, selectedId = '') {
  const sub = subCategorySelect;
  sub.innerHTML = '<option value="">Loading...</option>';
  if (!parentId) { sub.innerHTML = '<option value="">Select subcategory...</option>'; return; }
  const data = await API.get(`/api/categories?parent=${parentId}`);
  sub.innerHTML = '<option value="">Select subcategory (optional)...</option>' +
    (data.categories || []).map(c => `<option value="${c.id}" ${String(c.id) === String(selectedId) ? 'selected' : ''}>${c.name}</option>`).join('');
}

categorySelect.addEventListener('change', function() {
  loadSubcategories(this.value);
});

<?php if ($selectedCategoryId > 0): ?>
loadSubcategories('<?= $selectedCategoryId ?>', '<?= $selectedSubcategoryId ?>');
<?php endif; ?>

function applyGeneratedDraft(draft) {
  titleInput.value = draft.title || '';
  excerptInput.value = draft.excerpt || '';
  editor.innerHTML = draft.content_html || '';
  tagsInput.value = Array.isArray(draft.tags) ? draft.tags.join(', ') : '';
  seoTitleInput.value = draft.seo_title || '';
  seoDescriptionInput.value = draft.seo_description || '';
  if (imageAltInput && !imageAltInput.value.trim()) {
    imageAltInput.value = draft.seo_title || draft.title || '';
  }

  if (draft.post_type_hint) {
    postTypeInput.value = draft.post_type_hint;
  }

  if (draft.category_slug_hint) {
    const category = CATEGORY_OPTIONS.find((item) => item.slug === draft.category_slug_hint);
    if (category) {
      categorySelect.value = String(category.id);
      loadSubcategories(category.id);
    }
  }

  renderAiInsights(draft);
  refreshSlugPreview();
  refreshEditorMeta();
  refreshSeoHealth();
  editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

document.getElementById('generateAiBtn').addEventListener('click', async () => {
  const button = document.getElementById('generateAiBtn');
  const topic = document.getElementById('aiTopic').value.trim();
  if (!topic) {
    Toast.show('Topic required hai.', 'info');
    return;
  }

  button.disabled = true;
  const original = button.innerHTML;
  button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';

  try {
    const selectedCategory = CATEGORY_OPTIONS.find((item) => String(item.id) === String(categorySelect.value));
    const data = await API.post('/api/ai/generate', {
      topic,
      angle: document.getElementById('aiAngle').value.trim(),
      language: document.getElementById('aiLanguage').value,
      tone: document.getElementById('aiTone').value,
      word_count: Number(document.getElementById('aiWordCount').value || 700),
      preferred_type: document.getElementById('aiPreferredType').value,
      selected_category_slug: selectedCategory?.slug || ''
    });

    if (!data.success || !data.draft) {
      throw new Error(data.error || 'AI draft generate nahi hua.');
    }

    applyGeneratedDraft(data.draft);
    Toast.show('AI draft ready.', 'success');
  } catch (error) {
    Toast.show(error.message || 'AI draft generate nahi hua.', 'error');
  } finally {
    button.disabled = false;
    button.innerHTML = original;
  }
});

if (window.location.hash === '#ai-generator') {
  setTimeout(() => {
    document.getElementById('ai-generator')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.getElementById('aiTopic')?.focus();
  }, 150);
}

function buildDraftSignature() {
  return JSON.stringify({
    postId: postIdInput?.value || '',
    title: titleInput?.value.trim() || '',
    excerpt: excerptInput?.value.trim() || '',
    content: editor?.innerHTML.trim() || '',
    category: categorySelect?.value || '',
    subcategory: subCategorySelect?.value || '',
    type: postTypeInput?.value || '',
    placement: placementInput?.value || '',
    tags: tagsInput?.value.trim() || '',
    slug: slugInput?.value.trim() || '',
    seoTitle: seoTitleInput?.value.trim() || '',
    seoDescription: seoDescriptionInput?.value.trim() || '',
    imageAlt: imageAltInput?.value.trim() || ''
  });
}

function markDraftDirty() {
  hasPendingDraftChanges = true;
}

async function submitForm(status, options = {}) {
  return submitFormInternal(status, false, options);
}

async function refreshCsrfToken() {
  const response = await fetch(resolveAppUrl('/api/csrf'), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });

  const payload = await response.json();
  if (payload?.success && payload.token) {
    syncCsrfToken(payload.token);
    return true;
  }

  return false;
}

async function submitFormInternal(status, retried, options = {}) {
  const isDraftSave = status === 'draft';
  const isSilentDraftSave = isDraftSave && options.silent === true;
  if (isDraftSave && isDraftSaveInFlight) {
    return { skipped: true };
  }

  if (!excerptInput.value.trim()) {
    const generatedExcerpt = editor.innerText.trim().replace(/\s+/g, ' ').slice(0, 220).trim();
    excerptInput.value = generatedExcerpt.length === 220
      ? generatedExcerpt.replace(/\s+\S*$/, '').trim() + '...'
      : generatedExcerpt;
  }

  if (!imageAltInput.value.trim() && hasCoverImage()) {
    const categoryLabel = subCategorySelect?.selectedOptions?.[0]?.textContent?.trim()
      || categorySelect?.selectedOptions?.[0]?.textContent?.trim()
      || '';
    imageAltInput.value = [titleInput.value.trim(), categoryLabel !== '' ? `${categoryLabel} image` : 'image']
      .filter(Boolean)
      .join(' - ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  if (!seoTitleInput.value.trim()) {
    seoTitleInput.value = titleInput.value.trim();
  }

  if (!seoDescriptionInput.value.trim()) {
    seoDescriptionInput.value = excerptInput.value.trim();
  }

  document.getElementById('contentInput').value = editor.innerHTML;
  document.getElementById('postStatus').value = status;
  const formData = new FormData(postForm);

  if (isDraftSave) {
    isDraftSaveInFlight = true;
  }

  try {
    const res = await fetch(resolveAppUrl('/api/posts'), {
      method: 'POST',
      headers: { 'X-CSRF-Token': APP.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
      body: formData
    });

    const data = await res.json();
    if (data?.csrf_token) {
      syncCsrfToken(data.csrf_token);
    }

    if (res.status === 403 && data?.error === 'Invalid CSRF token' && !retried) {
      const refreshed = await refreshCsrfToken();
      if (refreshed) {
        return submitFormInternal(status, true, options);
      }
    }

    if (data.success) {
      if (isDraftSave && data.post_id && postIdInput) {
        postIdInput.value = String(data.post_id);
      }

      if (isDraftSave) {
        lastSavedDraftSignature = buildDraftSignature();
        hasPendingDraftChanges = false;
      }

      if (!isSilentDraftSave) {
        Toast.show(status === 'draft' ? 'Draft saved!' : 'Submitted for review!', 'success');
      }

      if (status !== 'draft') {
        setTimeout(() => window.location.href = resolveAppUrl(CREATE_POST_REDIRECT), 1500);
      }

      return data;
    }

    Toast.show(data.error || 'Something went wrong', 'error');
    return data;
  } finally {
    if (isDraftSave) {
      isDraftSaveInFlight = false;
    }
  }
}

postForm.addEventListener('input', markDraftDirty);
postForm.addEventListener('change', markDraftDirty);
lastSavedDraftSignature = buildDraftSignature();

document.getElementById('submitDraft').addEventListener('click', () => submitForm('draft'));
document.getElementById('submitPublish').addEventListener('click', () => submitForm('pending'));

setInterval(() => {
  if (!editor.textContent.trim() || !titleInput.value.trim()) return;
  if (isDraftSaveInFlight) return;

  const currentSignature = buildDraftSignature();
  if (!hasPendingDraftChanges || currentSignature === lastSavedDraftSignature) return;

  saveDraftStatus.textContent = 'Saving...';
  submitForm('draft', { silent: true }).then((result) => {
    if (result?.success) {
      saveDraftStatus.textContent = 'Draft saved ' + new Date().toLocaleTimeString();
    } else if (!result?.skipped) {
      saveDraftStatus.textContent = 'Autosave failed';
    }
  }).catch(() => {
    saveDraftStatus.textContent = 'Autosave failed';
  });
}, 30000);
</script>
</body>
</html>
