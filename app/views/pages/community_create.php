<?php
Auth::requireLogin();
$pageTitle = 'Create Community Post - FatakNews';
$pageDesc = 'Create a community post on FatakNews and share your perspective with the audience.';
$canonicalUrl = Helper::siteUrl('community/create');
$breadcrumbItems = [
    ['name' => 'Home', 'url' => Helper::siteUrl()],
    ['name' => 'Community', 'url' => Helper::siteUrl('community')],
    ['name' => 'Create Post', 'url' => $canonicalUrl],
];
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $pageTitle,
        'description' => $pageDesc,
        'url' => $canonicalUrl,
    ],
    Helper::breadcrumbSchema($breadcrumbItems),
];
$bodyClass = 'community-page community-create-page';
include VIEW . 'layouts/header.php';
?>
<div class="home-grid">
  <main class="feed-col">
    <?= Helper::breadcrumbNav($breadcrumbItems) ?>

    <div class="create-page community-create-shell">
      <div class="create-header community-create-header">
        <div class="widget-title"><i class="fa fa-pen"></i> Community Submission</div>
        <h1 class="community-create-title">Write something worth discussing</h1>
        <p class="community-create-intro">Share a clear, thoughtful update for the FatakNews community. Your post will be submitted through the standard community review flow.</p>
      </div>

      <form action="/api/posts" method="post" enctype="multipart/form-data" id="communityCreateForm">
        <?= Csrf::field() ?>
        <input type="hidden" name="type" value="community_post">
        <input type="hidden" name="allow_comments" value="1">

        <div class="create-layout community-create-layout">
          <div class="create-main">
            <input type="text" class="create-title-input" name="title" placeholder="Give your post a strong title" required>
            <textarea class="create-editor" name="content" placeholder="Write your post here..." required></textarea>
          </div>

          <aside class="create-side community-create-side">
            <div class="create-widget">
              <label>Summary</label>
              <textarea class="form-control" name="excerpt" rows="4" placeholder="Short summary for cards and previews"></textarea>
            </div>

            <div class="create-widget">
              <label>Tags</label>
              <input class="form-control" type="text" name="tags" placeholder="politics, campus, local issue">
            </div>

            <div class="create-widget">
              <label>Thumbnail</label>
              <input class="form-control" type="file" name="thumbnail" accept="image/*">
              <input class="form-control community-create-alt" type="text" name="image_alt" placeholder="Image alt text (optional)" maxlength="255">
            </div>

            <div class="community-create-actions">
              <button class="btn-write" type="submit" name="status" value="published" id="communitySubmitPublish"><i class="fa fa-paper-plane"></i> Submit for review</button>
              <button class="btn-ghost" type="submit" name="status" value="draft" id="communitySubmitDraft">Save draft</button>
            </div>
          </aside>
        </div>
      </form>
    </div>
  </main>

  <aside class="sidebar-col">
    <div class="sidebar-widget">
      <div class="widget-title"><i class="fa fa-circle-question"></i> Writing Tips</div>
      <p class="community-create-sidebar-copy">Use a specific headline, explain the issue in plain language, and keep the first paragraph useful enough to stand on its own.</p>
    </div>

    <div class="sidebar-widget">
      <div class="widget-title"><i class="fa fa-shield-heart"></i> Community Rules</div>
      <p class="community-create-sidebar-copy">Posts should stay civil, factual, and relevant. Avoid abuse, spam, personal attacks, and misleading claims.</p>
    </div>
  </aside>
</div>
<?php
$extraScripts = <<<HTML
<script>
(function initCommunityCreateForm() {
  const form = document.getElementById('communityCreateForm');
  if (!form) return;

  async function submitCommunityForm(submitter, retried = false) {
    const originalLabel = submitter?.dataset.originalLabel || submitter?.innerHTML || '';
    const formData = new FormData(form);

    if (submitter?.name && submitter?.value) {
      formData.set(submitter.name, submitter.value);
    }

    if (submitter) {
      if (!submitter.dataset.originalLabel) {
        submitter.dataset.originalLabel = originalLabel;
      }
      submitter.disabled = true;
      submitter.innerHTML = submitter.value === 'draft' ? 'Saving...' : 'Submitting...';
    }

    try {
      const response = await fetch(resolveAppUrl('/api/posts'), {
        method: 'POST',
        headers: {
          'X-CSRF-Token': APP.csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      });

      const data = await response.json();

      if (data?.csrf_token) {
        window.syncAppCsrfToken?.(data.csrf_token);
      }

      if (response.status === 403 && data?.error === 'Invalid CSRF token' && !retried) {
        const refreshed = await window.refreshAppCsrfToken?.();
        if (refreshed) {
          return submitCommunityForm(submitter, true);
        }
      }

      if (!response.ok || !data.success) {
        throw new Error(data.error || 'Submission failed');
      }

      Toast.show(data.message || 'Submitted successfully', 'success');
      window.setTimeout(() => {
        window.location.href = submitter?.value === 'draft'
          ? resolveAppUrl('/community/create')
          : resolveAppUrl('/community');
      }, 500);
    } catch (error) {
      Toast.show(error.message || 'Submission failed', 'error');
    } finally {
      if (submitter) {
        submitter.disabled = false;
        submitter.innerHTML = originalLabel;
      }
    }
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitter = event.submitter;
    await submitCommunityForm(submitter);
  });
})();
</script>
HTML;
?>
<?php include VIEW . 'layouts/mobile_bottom_nav.php'; ?>
<?php include VIEW . 'layouts/footer.php'; ?>
