// FatakNews.in - Stories JS

(function initStories() {
  const composerModal = document.getElementById('storyComposerModal');
  const composerForm = document.getElementById('storyComposerForm');
  const createBtn = document.getElementById('mobileStoryCreateBtn');
  const inlineCreateBtns = Array.from(document.querySelectorAll('[data-story-create]'));
  const composeFab = document.getElementById('mobileStoryComposeFab');
  const preview = document.getElementById('storyUploadPreview');
  const viewerModal = document.getElementById('storyViewerModal');

  if (!composerModal && !viewerModal) return;

  const body = document.body;
  const fileInput = composerForm?.querySelector('input[type="file"]');
  const submitBtn = document.getElementById('storyComposerSubmit');
  const ownStoryBtn = document.getElementById('mobileOwnStoryBtn');
  const viewerMedia = document.getElementById('storyViewerMedia');
  const viewerName = document.getElementById('storyViewerName');
  const viewerTime = document.getElementById('storyViewerTime');
  const viewerCaption = document.getElementById('storyViewerCaption');
  const viewerAvatar = document.getElementById('storyViewerAvatar');
  const viewerDelete = document.getElementById('storyViewerDelete');
  const viewerProgress = document.getElementById('storyViewerProgress');
  const prevBtn = document.getElementById('storyViewerPrev');
  const nextBtn = document.getElementById('storyViewerNext');
  const storyButtons = Array.from(document.querySelectorAll('[data-story-user]'));
  const syncBodyLock = () => {
    const navOpen = document.getElementById('mobileNav')?.classList.contains('open');
    const modalOpen = (composerModal && !composerModal.hidden) || (viewerModal && !viewerModal.hidden);
    body.classList.toggle('menu-open', Boolean(navOpen || modalOpen));
  };

  const state = {
    stories: [],
    index: 0,
    progress: 0,
    timer: null,
    activeUserId: 0
  };

  const setModalState = (modal, open) => {
    if (!modal) return;
    modal.hidden = !open;
    syncBodyLock();
  };

  const openComposer = () => {
    if (!window.APP.isLoggedIn) {
      window.location.href = window.resolveAppUrl('/login');
      return;
    }

    setModalState(composerModal, true);
  };

  const closeComposer = () => {
    setModalState(composerModal, false);
    if (composerForm) composerForm.reset();
    if (preview) {
      preview.hidden = true;
      preview.innerHTML = '';
    }
  };

  createBtn?.addEventListener('click', openComposer);
  inlineCreateBtns.forEach((node) => {
    node.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      openComposer();
    });
  });
  composeFab?.addEventListener('click', openComposer);
  ownStoryBtn?.addEventListener('click', openComposer);
  composerModal?.querySelectorAll('[data-story-close]').forEach((node) => {
    node.addEventListener('click', closeComposer);
  });

  fileInput?.addEventListener('change', () => {
    const file = fileInput.files?.[0];
    if (!preview || !file) {
      if (preview) {
        preview.hidden = true;
        preview.innerHTML = '';
      }
      return;
    }

    preview.hidden = false;
    preview.innerHTML = `<img src="${URL.createObjectURL(file)}" alt="Story preview">`;
  });

  composerForm?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const caption = composerForm.querySelector('textarea[name="caption"]')?.value.trim() || '';
    const backgroundColor = composerForm.querySelector('input[name="background_color"]:checked')?.value || '#2D2244';
    const file = fileInput?.files?.[0] || null;

    if (!caption && !file) {
      window.Toast.show('Add text or choose an image for the story', 'info');
      return;
    }

    const originalLabel = submitBtn?.textContent || 'Publish Story';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Publishing...';
    }

    try {
      let mediaPath = '';
      if (file) {
        const upload = await window.API.upload('/api/upload', file, { dir: 'stories' });
        if (!upload.success || !upload.filename) {
          throw new Error(upload.error || 'Story upload failed');
        }
        mediaPath = upload.filename;
      }

      const result = await window.API.post('/api/stories', {
        action: 'create',
        caption,
        media_path: mediaPath,
        background_color: backgroundColor,
        text_color: '#FFFFFF'
      });

      if (!result.success) {
        throw new Error(result.error || 'Story publish failed');
      }

      window.Toast.show('Story published', 'success');
      closeComposer();
      window.location.reload();
    } catch (error) {
      window.Toast.show(error.message || 'Story publish failed', 'error');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalLabel;
      }
    }
  });

  const stopTimer = () => {
    if (state.timer) {
      clearInterval(state.timer);
      state.timer = null;
    }
  };

  const closeViewer = () => {
    stopTimer();
    state.stories = [];
    state.index = 0;
    state.progress = 0;
    state.activeUserId = 0;
    if (viewerDelete) viewerDelete.hidden = true;
    setModalState(viewerModal, false);
  };

  const renderProgress = () => {
    if (!viewerProgress) return;
    viewerProgress.innerHTML = state.stories.map((_, index) => {
      let value = '0%';
      if (index < state.index) value = '100%';
      if (index === state.index) value = `${state.progress}%`;
      return `<span style="--story-progress:${value}"></span>`;
    }).join('');
  };

  const advanceStory = (direction = 1) => {
    const nextIndex = state.index + direction;
    if (nextIndex < 0) {
      closeViewer();
      return;
    }
    if (nextIndex >= state.stories.length) {
      closeViewer();
      return;
    }
    state.index = nextIndex;
    state.progress = 0;
    renderStory();
  };

  const renderStory = async () => {
    const story = state.stories[state.index];
    if (!story || !viewerMedia || !viewerName || !viewerTime || !viewerCaption || !viewerAvatar) {
      closeViewer();
      return;
    }

    viewerName.textContent = story.full_name;
    viewerTime.textContent = story.time_ago;
    viewerAvatar.src = story.avatar;
    viewerCaption.textContent = story.caption || '';
    if (viewerDelete) viewerDelete.hidden = !story.is_owner;

    if (story.media_type === 'image' && story.media_url) {
      viewerMedia.style.background = '#0F0D18';
      viewerMedia.innerHTML = `<img src="${story.media_url}" alt="${story.caption || story.full_name}">`;
    } else {
      const safeText = (story.caption || 'FatakNews Story')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
      viewerMedia.style.background = `linear-gradient(160deg, ${story.background_color || '#2D2244'}, #12111B)`;
      viewerMedia.innerHTML = `<div class="story-viewer-text" style="color:${story.text_color || '#FFFFFF'}">${safeText}</div>`;
    }

    renderProgress();

    if (window.APP.isLoggedIn && !story.is_viewed) {
      story.is_viewed = 1;
      window.API.post('/api/stories', { action: 'view', story_id: story.id }).catch(() => {});
    }

    document.querySelector(`[data-story-user="${state.activeUserId}"]`)?.classList.remove('has-unseen');
    document.querySelector(`[data-story-user="${state.activeUserId}"]`)?.classList.add('is-seen');

    stopTimer();
    state.timer = setInterval(() => {
      state.progress = Math.min(100, state.progress + 2);
      renderProgress();
      if (state.progress >= 100) {
        advanceStory(1);
      }
    }, 100);
  };

  const openViewerForUser = async (userId) => {
    try {
      const data = await window.API.get(`/api/stories?user_id=${encodeURIComponent(userId)}`);
      if (!data.success || !Array.isArray(data.stories) || !data.stories.length) {
        window.Toast.show('No active stories found', 'info');
        return;
      }

      state.stories = data.stories;
      state.activeUserId = userId;
      state.index = Math.max(0, data.stories.findIndex((story) => !story.is_viewed));
      state.progress = 0;
      setModalState(viewerModal, true);
      renderStory();
    } catch {
      window.Toast.show('Failed to load stories', 'error');
    }
  };

  storyButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const userId = Number(button.dataset.storyUser || 0);
      if (userId > 0) {
        openViewerForUser(userId);
      }
    });
  });

  prevBtn?.addEventListener('click', () => advanceStory(-1));
  nextBtn?.addEventListener('click', () => advanceStory(1));
  viewerDelete?.addEventListener('click', async () => {
    const story = state.stories[state.index];
    if (!story?.is_owner) return;
    if (!confirm('Delete this story?')) return;

    try {
      const result = await window.API.post('/api/stories', { action: 'delete', story_id: story.id });
      if (!result.success) {
        throw new Error(result.error || 'Delete failed');
      }

      state.stories.splice(state.index, 1);
      window.Toast.show('Story deleted', 'success');

      if (!state.stories.length) {
        closeViewer();
        window.location.reload();
        return;
      }

      if (state.index >= state.stories.length) {
        state.index = state.stories.length - 1;
      }
      state.progress = 0;
      renderStory();
    } catch (error) {
      window.Toast.show(error.message || 'Delete failed', 'error');
    }
  });
  viewerModal?.querySelectorAll('[data-story-viewer-close]').forEach((node) => {
    node.addEventListener('click', closeViewer);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeComposer();
      closeViewer();
    }
    if (viewerModal && !viewerModal.hidden && event.key === 'ArrowRight') {
      advanceStory(1);
    }
    if (viewerModal && !viewerModal.hidden && event.key === 'ArrowLeft') {
      advanceStory(-1);
    }
  });
})();
