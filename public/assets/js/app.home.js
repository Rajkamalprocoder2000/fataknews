// FatakNews.in - Home page JS

(function initMobileHomeMenu() {
  const btn = document.getElementById('mobileHomeMenuBtn');
  const panel = document.getElementById('mobileHomeMenu');
  const overlay = document.getElementById('mobileHomeMenuOverlay');
  if (!btn || !panel || !overlay || panel.dataset.menuBound === 'true') return;

  const closeSelector = '#mobileHomeMenuClose, #mobileHomeMenuOverlay, #mobileHomeMenu a';

  const setOpen = (open) => {
    panel.hidden = !open;
    overlay.hidden = !open;
    panel.classList.toggle('open', open);
    overlay.classList.toggle('open', open);
    btn.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.classList.toggle('menu-open', open);
  };

  panel.dataset.menuBound = 'true';
  setOpen(false);

  document.addEventListener('click', (event) => {
    if (event.target.closest('#mobileHomeMenuBtn')) {
      event.preventDefault();
      setOpen(!panel.classList.contains('open'));
      return;
    }

    if (event.target.closest(closeSelector)) {
      setOpen(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setOpen(false);
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
      setOpen(false);
    }
  });
})();

(function initHeroSlider() {
  const slides = document.querySelectorAll('.hero-slide');
  const dots = document.querySelectorAll('.hero-dot');
  if (!slides.length) return;

  let current = 0;
  const show = (index) => {
    slides[current].classList.remove('active');
    dots[current]?.classList.remove('active');
    current = (index + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current]?.classList.add('active');
  };

  dots.forEach((dot, index) => dot.addEventListener('click', () => show(index)));
  setInterval(() => show(current + 1), 5000);
})();

let homeFeedRevealObserver = null;

function initHomeFeedReveal(feed = document.getElementById('newsFeed')) {
  if (!feed) return;

  const cards = [...feed.querySelectorAll('.news-card')];
  if (!cards.length) return;

  cards.forEach((card, index) => {
    card.style.setProperty('--reveal-delay', `${Math.min(index, 5) * 90}ms`);
  });

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
    cards.forEach((card) => card.classList.add('is-visible'));
    return;
  }

  cards.forEach((card) => {
    card.classList.add('reveal-ready');
    card.classList.remove('is-visible');
  });

  homeFeedRevealObserver?.disconnect();
  homeFeedRevealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      entry.target.classList.toggle('is-visible', entry.isIntersecting);
    });
  }, {
    threshold: 0.18,
    rootMargin: '0px 0px -8% 0px'
  });

  window.requestAnimationFrame(() => {
    cards.forEach((card) => {
      const rect = card.getBoundingClientRect();
      const inView = rect.top < (window.innerHeight * 0.92) && rect.bottom > 0;
      if (inView) {
        card.classList.add('is-visible');
      }
      homeFeedRevealObserver.observe(card);
    });
  });
}

window.initHomeFeedReveal = initHomeFeedReveal;

let mobileHomeRevealObserver = null;

function initMobileHomeCardReveal(root = document.querySelector('.mobile-home-frame')) {
  if (!root) return;

  const cards = [...root.querySelectorAll('.mobile-home-card, .mobile-home-trendingcard')];
  if (!cards.length) return;

  cards.forEach((card, index) => {
    card.style.setProperty('--mobile-reveal-delay', `${Math.min(index, 5) * 80}ms`);
    card.style.setProperty('--mobile-reveal-x', index % 2 === 0 ? '-22px' : '22px');
  });

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
    cards.forEach((card) => card.classList.add('is-visible'));
    return;
  }

  cards.forEach((card) => {
    card.classList.add('reveal-ready');
    card.classList.remove('is-visible');
  });

  mobileHomeRevealObserver?.disconnect();
  mobileHomeRevealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      entry.target.classList.toggle('is-visible', entry.isIntersecting);
    });
  }, {
    threshold: 0.16,
    rootMargin: '0px 0px -8% 0px'
  });

  window.requestAnimationFrame(() => {
    cards.forEach((card) => {
      const rect = card.getBoundingClientRect();
      const inView = rect.top < (window.innerHeight * 0.92) && rect.bottom > 0;
      if (inView) {
        card.classList.add('is-visible');
      }
      mobileHomeRevealObserver.observe(card);
    });
  });
}

function initMobileFeaturedSlider() {
  const slider = document.querySelector('.mobile-home-featuredslider');
  if (!slider || slider.dataset.sliderBound === 'true') return;

  const cards = [...slider.querySelectorAll('.mobile-home-featuredslide')];
  if (cards.length < 2) return;

  slider.dataset.sliderBound = 'true';

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let currentIndex = 0;
  let autoplayId = null;
  let resumeId = null;
  let scrollTicking = false;

  const updateSlideState = () => {
    cards.forEach((card, index) => {
      card.classList.toggle('is-active', index === currentIndex);
      card.classList.toggle('is-prev', index === currentIndex - 1);
      card.classList.toggle('is-next', index === currentIndex + 1);
    });
  };

  const syncActiveIndex = () => {
    const sliderCenter = slider.scrollLeft + (slider.clientWidth / 2);
    let closestIndex = 0;
    let closestDistance = Number.POSITIVE_INFINITY;

    cards.forEach((card, index) => {
      const cardCenter = card.offsetLeft + (card.offsetWidth / 2);
      const distance = Math.abs(cardCenter - sliderCenter);
      if (distance < closestDistance) {
        closestDistance = distance;
        closestIndex = index;
      }
    });

    currentIndex = closestIndex;
    updateSlideState();
  };

  const goToSlide = (nextIndex, behavior = 'smooth') => {
    currentIndex = (nextIndex + cards.length) % cards.length;
    updateSlideState();
    const targetCard = cards[currentIndex];
    const targetLeft = targetCard.offsetLeft - ((slider.clientWidth - targetCard.offsetWidth) / 2);
    slider.scrollTo({
      left: Math.max(0, targetLeft),
      behavior
    });
  };

  const stopAutoplay = () => {
    if (autoplayId) {
      clearInterval(autoplayId);
      autoplayId = null;
    }
  };

  const startAutoplay = () => {
    if (prefersReducedMotion || autoplayId) return;
    autoplayId = window.setInterval(() => {
      goToSlide(currentIndex + 1);
    }, 3600);
  };

  const queueResume = (delay = 2200) => {
    if (prefersReducedMotion) return;
    clearTimeout(resumeId);
    resumeId = window.setTimeout(() => {
      syncActiveIndex();
      startAutoplay();
    }, delay);
  };

  slider.addEventListener('mouseenter', stopAutoplay);
  slider.addEventListener('mouseleave', () => {
    syncActiveIndex();
    startAutoplay();
  });
  slider.addEventListener('focusin', stopAutoplay);
  slider.addEventListener('focusout', () => queueResume(300));
  slider.addEventListener('touchstart', () => {
    stopAutoplay();
    clearTimeout(resumeId);
  }, { passive: true });
  slider.addEventListener('touchend', () => queueResume());
  slider.addEventListener('pointerdown', stopAutoplay);
  slider.addEventListener('pointerup', () => queueResume(1400));
  slider.addEventListener('scroll', () => {
    if (scrollTicking) return;
    scrollTicking = true;
    window.requestAnimationFrame(() => {
      syncActiveIndex();
      scrollTicking = false;
    });
  }, { passive: true });

  window.addEventListener('resize', () => {
    syncActiveIndex();
    goToSlide(currentIndex, 'auto');
  });

  goToSlide(0, 'auto');
  startAutoplay();
}

(function initCategoryRailControls() {
  const rail = document.getElementById('categoryRail');
  if (!rail) return;

  const cards = [...rail.querySelectorAll('.home-category-card')];
  if (!cards.length) return;

  const prevBtn = document.querySelector('[data-rail-prev="categoryRail"]');
  const nextBtn = document.querySelector('[data-rail-next="categoryRail"]');
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let autoplayId = null;
  let resumeId = null;
  let currentIndex = 0;
  let scrollTicking = false;

  const getScrollLeftForIndex = (index) => {
    const card = cards[index];
    if (!card) return 0;
    return Math.max(0, card.offsetLeft);
  };

  const hasOverflow = () => rail.scrollWidth > rail.clientWidth + 6;

  const syncActiveIndex = () => {
    const railLeft = rail.scrollLeft;
    let closestIndex = 0;
    let closestDistance = Number.POSITIVE_INFINITY;

    cards.forEach((card, index) => {
      const distance = Math.abs(card.offsetLeft - railLeft);
      if (distance < closestDistance) {
        closestDistance = distance;
        closestIndex = index;
      }
    });

    currentIndex = closestIndex;
  };

  const goToIndex = (index, behavior = 'smooth') => {
    currentIndex = (index + cards.length) % cards.length;
    rail.scrollTo({
      left: getScrollLeftForIndex(currentIndex),
      behavior
    });
  };

  const syncButtons = () => {
    const maxScroll = Math.max(0, rail.scrollWidth - rail.clientWidth - 2);
    if (prevBtn) prevBtn.disabled = rail.scrollLeft <= 4;
    if (nextBtn) nextBtn.disabled = rail.scrollLeft >= maxScroll;
  };

  const stopAutoplay = () => {
    if (autoplayId) {
      clearInterval(autoplayId);
      autoplayId = null;
    }
  };

  const startAutoplay = () => {
    if (prefersReducedMotion || autoplayId || !hasOverflow()) return;
    autoplayId = window.setInterval(() => {
      syncActiveIndex();
      goToIndex(currentIndex + 1);
    }, 2800);
  };

  const queueResume = (delay = 1400) => {
    if (prefersReducedMotion) return;
    clearTimeout(resumeId);
    resumeId = window.setTimeout(() => {
      syncButtons();
      startAutoplay();
    }, delay);
  };

  prevBtn?.addEventListener('click', () => {
    stopAutoplay();
    syncActiveIndex();
    goToIndex(currentIndex - 1);
    queueResume();
  });

  nextBtn?.addEventListener('click', () => {
    stopAutoplay();
    syncActiveIndex();
    goToIndex(currentIndex + 1);
    queueResume();
  });

  rail.addEventListener('scroll', () => {
    if (scrollTicking) return;
    scrollTicking = true;
    window.requestAnimationFrame(() => {
      syncActiveIndex();
      syncButtons();
      scrollTicking = false;
    });
  }, { passive: true });
  rail.addEventListener('mouseenter', stopAutoplay);
  rail.addEventListener('mouseleave', () => queueResume(300));
  rail.addEventListener('focusin', stopAutoplay);
  rail.addEventListener('focusout', () => queueResume(300));
  rail.addEventListener('touchstart', () => {
    stopAutoplay();
    clearTimeout(resumeId);
  }, { passive: true });
  rail.addEventListener('touchend', () => queueResume());
  rail.addEventListener('pointerdown', stopAutoplay);
  rail.addEventListener('pointerup', () => queueResume());
  window.addEventListener('resize', () => {
    stopAutoplay();
    syncActiveIndex();
    goToIndex(currentIndex, 'auto');
    syncButtons();
    startAutoplay();
  });
  syncActiveIndex();
  syncButtons();
  goToIndex(0, 'auto');
  startAutoplay();
})();

(function initFollowRowAutoplay() {
  const rail = document.querySelector('.home-follow-row');
  if (!rail || rail.dataset.autoplayBound === 'true') return;

  const cards = [...rail.querySelectorAll('.suggest-item')];
  if (cards.length < 2) return;

  rail.dataset.autoplayBound = 'true';

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let autoplayId = null;
  let resumeId = null;
  let currentIndex = 0;
  let scrollTicking = false;

  const hasOverflow = () => rail.scrollWidth > rail.clientWidth + 6;

  const getScrollLeftForIndex = (index) => {
    const card = cards[index];
    if (!card) return 0;
    return Math.max(0, card.offsetLeft);
  };

  const syncActiveIndex = () => {
    const railLeft = rail.scrollLeft;
    let closestIndex = 0;
    let closestDistance = Number.POSITIVE_INFINITY;

    cards.forEach((card, index) => {
      const distance = Math.abs(card.offsetLeft - railLeft);
      if (distance < closestDistance) {
        closestDistance = distance;
        closestIndex = index;
      }
    });

    currentIndex = closestIndex;
  };

  const goToIndex = (index, behavior = 'smooth') => {
    currentIndex = (index + cards.length) % cards.length;
    rail.scrollTo({
      left: getScrollLeftForIndex(currentIndex),
      behavior
    });
  };

  const stopAutoplay = () => {
    if (autoplayId) {
      clearInterval(autoplayId);
      autoplayId = null;
    }
  };

  const startAutoplay = () => {
    if (prefersReducedMotion || autoplayId || !hasOverflow()) return;
    autoplayId = window.setInterval(() => {
      syncActiveIndex();
      goToIndex(currentIndex + 1);
    }, 2600);
  };

  const queueResume = (delay = 1400) => {
    if (prefersReducedMotion) return;
    clearTimeout(resumeId);
    resumeId = window.setTimeout(() => {
      startAutoplay();
    }, delay);
  };

  rail.addEventListener('scroll', () => {
    if (scrollTicking) return;
    scrollTicking = true;
    window.requestAnimationFrame(() => {
      syncActiveIndex();
      scrollTicking = false;
    });
  }, { passive: true });

  rail.addEventListener('mouseenter', stopAutoplay);
  rail.addEventListener('mouseleave', () => queueResume(300));
  rail.addEventListener('focusin', stopAutoplay);
  rail.addEventListener('focusout', () => queueResume(300));
  rail.addEventListener('touchstart', () => {
    stopAutoplay();
    clearTimeout(resumeId);
  }, { passive: true });
  rail.addEventListener('touchend', () => queueResume());
  rail.addEventListener('pointerdown', stopAutoplay);
  rail.addEventListener('pointerup', () => queueResume());

  window.addEventListener('resize', () => {
    stopAutoplay();
    syncActiveIndex();
    goToIndex(currentIndex, 'auto');
    startAutoplay();
  });

  syncActiveIndex();
  goToIndex(0, 'auto');
  startAutoplay();
})();

function initMobileCategoryRailAutoplay() {
  const rail = document.querySelector('.mobile-home-categoryrail');
  if (!rail || rail.dataset.sliderBound === 'true') return;

  const cards = [...rail.querySelectorAll('.mobile-home-categorycard')];
  if (cards.length < 2) return;

  rail.dataset.sliderBound = 'true';

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let autoplayId = null;
  let kickoffId = null;
  let resumeId = null;
  let currentIndex = 0;
  let scrollTicking = false;

  const hasOverflow = () => rail.scrollWidth > rail.clientWidth + 6;

  const updateCardState = () => {
    cards.forEach((card, index) => {
      card.classList.toggle('is-active', index === currentIndex);
      card.classList.toggle('is-prev', index === currentIndex - 1);
      card.classList.toggle('is-next', index === currentIndex + 1);
    });
  };

  const getScrollLeftForIndex = (index) => {
    const card = cards[index];
    if (!card) return 0;
    return Math.max(0, card.offsetLeft - ((rail.clientWidth - card.offsetWidth) / 2));
  };

  const syncActiveIndex = () => {
    const railCenter = rail.scrollLeft + (rail.clientWidth / 2);
    let closestIndex = 0;
    let closestDistance = Number.POSITIVE_INFINITY;

    cards.forEach((card, index) => {
      const cardCenter = card.offsetLeft + (card.offsetWidth / 2);
      const distance = Math.abs(cardCenter - railCenter);
      if (distance < closestDistance) {
        closestDistance = distance;
        closestIndex = index;
      }
    });

    currentIndex = closestIndex;
    updateCardState();
  };

  const goToIndex = (index, behavior = 'smooth') => {
    currentIndex = (index + cards.length) % cards.length;
    updateCardState();
    rail.scrollTo({
      left: getScrollLeftForIndex(currentIndex),
      behavior
    });
  };

  const stopAutoplay = () => {
    if (autoplayId) {
      clearInterval(autoplayId);
      autoplayId = null;
    }
    if (kickoffId) {
      clearTimeout(kickoffId);
      kickoffId = null;
    }
  };

  const startAutoplay = () => {
    if (prefersReducedMotion || autoplayId || !hasOverflow()) return;
    autoplayId = window.setInterval(() => {
      goToIndex(currentIndex + 1);
    }, 2400);
  };

  const kickoffAutoplay = (delay = 600) => {
    if (prefersReducedMotion || !hasOverflow()) return;
    clearTimeout(kickoffId);
    kickoffId = window.setTimeout(() => {
      goToIndex(currentIndex + 1);
      startAutoplay();
    }, delay);
  };

  const queueResume = (delay = 1600) => {
    if (prefersReducedMotion) return;
    clearTimeout(resumeId);
    resumeId = window.setTimeout(() => {
      kickoffAutoplay(Math.min(delay, 900));
    }, delay);
  };

  rail.addEventListener('scroll', () => {
    if (scrollTicking) return;
    scrollTicking = true;
    window.requestAnimationFrame(() => {
      syncActiveIndex();
      scrollTicking = false;
    });
  }, { passive: true });

  rail.addEventListener('mouseenter', stopAutoplay);
  rail.addEventListener('mouseleave', () => queueResume(300));
  rail.addEventListener('focusin', stopAutoplay);
  rail.addEventListener('focusout', () => queueResume(300));
  rail.addEventListener('touchstart', () => {
    stopAutoplay();
    clearTimeout(resumeId);
  }, { passive: true });
  rail.addEventListener('touchend', () => queueResume());
  rail.addEventListener('pointerdown', stopAutoplay);
  rail.addEventListener('pointerup', () => queueResume());

  window.addEventListener('resize', () => {
    stopAutoplay();
    syncActiveIndex();
    goToIndex(currentIndex, 'auto');
    kickoffAutoplay(500);
  });

  syncActiveIndex();
  goToIndex(0, 'auto');
  kickoffAutoplay();
}

function initMobileTopicsRailAutoplay() {
  const rail = document.querySelector('.mobile-home-topicsrail');
  if (!rail || rail.dataset.sliderBound === 'true') return;

  const items = [...rail.querySelectorAll('.mobile-home-topicchip')];
  if (items.length < 2) return;

  rail.dataset.sliderBound = 'true';

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let autoplayId = null;
  let resumeId = null;
  let currentIndex = 0;
  let scrollTicking = false;

  const hasOverflow = () => rail.scrollWidth > rail.clientWidth + 6;

  const getScrollLeftForIndex = (index) => {
    const item = items[index];
    if (!item) return 0;
    return Math.max(0, item.offsetLeft - 14);
  };

  const syncActiveIndex = () => {
    const railLeft = rail.scrollLeft;
    let closestIndex = 0;
    let closestDistance = Number.POSITIVE_INFINITY;

    items.forEach((item, index) => {
      const distance = Math.abs(item.offsetLeft - railLeft);
      if (distance < closestDistance) {
        closestDistance = distance;
        closestIndex = index;
      }
    });

    currentIndex = closestIndex;
  };

  const goToIndex = (index, behavior = 'smooth') => {
    currentIndex = (index + items.length) % items.length;
    rail.scrollTo({
      left: getScrollLeftForIndex(currentIndex),
      behavior
    });
  };

  const stopAutoplay = () => {
    if (autoplayId) {
      clearInterval(autoplayId);
      autoplayId = null;
    }
  };

  const startAutoplay = () => {
    if (prefersReducedMotion || autoplayId || !hasOverflow()) return;
    autoplayId = window.setInterval(() => {
      syncActiveIndex();
      goToIndex(currentIndex + 1);
    }, 2200);
  };

  const queueResume = (delay = 1200) => {
    if (prefersReducedMotion) return;
    clearTimeout(resumeId);
    resumeId = window.setTimeout(() => {
      startAutoplay();
    }, delay);
  };

  rail.addEventListener('scroll', () => {
    if (scrollTicking) return;
    scrollTicking = true;
    window.requestAnimationFrame(() => {
      syncActiveIndex();
      scrollTicking = false;
    });
  }, { passive: true });

  rail.addEventListener('mouseenter', stopAutoplay);
  rail.addEventListener('mouseleave', () => queueResume(300));
  rail.addEventListener('focusin', stopAutoplay);
  rail.addEventListener('focusout', () => queueResume(300));
  rail.addEventListener('touchstart', () => {
    stopAutoplay();
    clearTimeout(resumeId);
  }, { passive: true });
  rail.addEventListener('touchend', () => queueResume());
  rail.addEventListener('pointerdown', stopAutoplay);
  rail.addEventListener('pointerup', () => queueResume());

  window.addEventListener('resize', () => {
    stopAutoplay();
    syncActiveIndex();
    goToIndex(currentIndex, 'auto');
    startAutoplay();
  });

  syncActiveIndex();
  goToIndex(0, 'auto');
  startAutoplay();
}

function initMobileFollowRailAutoplay() {
  const rail = document.querySelector('.mobile-home-followrail');
  if (!rail || rail.dataset.sliderBound === 'true') return;

  const cards = [...rail.querySelectorAll('.mobile-home-followcard')];
  if (cards.length < 2) return;

  rail.dataset.sliderBound = 'true';

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let autoplayId = null;
  let kickoffId = null;
  let resumeId = null;
  let currentIndex = 0;
  let scrollTicking = false;

  const hasOverflow = () => rail.scrollWidth > rail.clientWidth + 6;

  const getScrollLeftForIndex = (index) => {
    const card = cards[index];
    if (!card) return 0;
    return Math.max(0, card.offsetLeft - ((rail.clientWidth - card.offsetWidth) / 2));
  };

  const syncActiveIndex = () => {
    const railCenter = rail.scrollLeft + (rail.clientWidth / 2);
    let closestIndex = 0;
    let closestDistance = Number.POSITIVE_INFINITY;

    cards.forEach((card, index) => {
      const cardCenter = card.offsetLeft + (card.offsetWidth / 2);
      const distance = Math.abs(cardCenter - railCenter);
      if (distance < closestDistance) {
        closestDistance = distance;
        closestIndex = index;
      }
    });

    currentIndex = closestIndex;
  };

  const goToIndex = (index, behavior = 'smooth') => {
    currentIndex = (index + cards.length) % cards.length;
    rail.scrollTo({
      left: getScrollLeftForIndex(currentIndex),
      behavior
    });
  };

  const stopAutoplay = () => {
    if (autoplayId) {
      clearInterval(autoplayId);
      autoplayId = null;
    }
    if (kickoffId) {
      clearTimeout(kickoffId);
      kickoffId = null;
    }
  };

  const startAutoplay = () => {
    if (prefersReducedMotion || autoplayId || !hasOverflow()) return;
    autoplayId = window.setInterval(() => {
      goToIndex(currentIndex + 1);
    }, 2600);
  };

  const kickoffAutoplay = (delay = 700) => {
    if (prefersReducedMotion || !hasOverflow()) return;
    clearTimeout(kickoffId);
    kickoffId = window.setTimeout(() => {
      goToIndex(currentIndex + 1);
      startAutoplay();
    }, delay);
  };

  const queueResume = (delay = 1600) => {
    if (prefersReducedMotion) return;
    clearTimeout(resumeId);
    resumeId = window.setTimeout(() => {
      kickoffAutoplay(Math.min(delay, 900));
    }, delay);
  };

  rail.addEventListener('scroll', () => {
    if (scrollTicking) return;
    scrollTicking = true;
    window.requestAnimationFrame(() => {
      syncActiveIndex();
      scrollTicking = false;
    });
  }, { passive: true });

  rail.addEventListener('mouseenter', stopAutoplay);
  rail.addEventListener('mouseleave', () => queueResume(300));
  rail.addEventListener('focusin', stopAutoplay);
  rail.addEventListener('focusout', () => queueResume(300));
  rail.addEventListener('touchstart', () => {
    stopAutoplay();
    clearTimeout(resumeId);
  }, { passive: true });
  rail.addEventListener('touchend', () => queueResume());
  rail.addEventListener('pointerdown', stopAutoplay);
  rail.addEventListener('pointerup', () => queueResume());

  window.addEventListener('resize', () => {
    stopAutoplay();
    syncActiveIndex();
    goToIndex(currentIndex, 'auto');
    kickoffAutoplay(500);
  });

  syncActiveIndex();
  goToIndex(0, 'auto');
  kickoffAutoplay();
}

(function initCategoryTabs() {
  const tabs = [...document.querySelectorAll('.cat-tab')];
  if (tabs.length && !tabs.some((tab) => tab.classList.contains('active'))) {
    tabs[0].classList.add('active');
  }

  const mobilePills = [...document.querySelectorAll('.mobile-home-pill')];
  if (mobilePills.length && !mobilePills.some((pill) => pill.classList.contains('active'))) {
    mobilePills[0].classList.add('active');
  }
})();

initHomeFeedReveal();
initMobileHomeCardReveal();
initMobileFeaturedSlider();
initMobileCategoryRailAutoplay();
initMobileTopicsRailAutoplay();
initMobileFollowRailAutoplay();
