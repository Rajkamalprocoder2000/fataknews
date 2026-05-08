// FatakNews.in - Explore embeds JS

(function initExploreEmbeds() {
  const root = document.querySelector('.explore-grid');
  if (!root) return;

  const hosts = Array.from(root.querySelectorAll('.explore-social-host'));
  if (!hosts.length) return;

  const scriptPromises = new Map();
  const platformUrls = {
    x: 'https://platform.twitter.com/widgets.js',
    instagram: 'https://www.instagram.com/embed.js',
    tiktok: 'https://www.tiktok.com/embed.js'
  };

  const ensureTwitterFallbacks = () => {
    root.querySelectorAll('.explore-social-host[data-platform="x"]').forEach((host) => {
      const hasRenderedTweet = host.querySelector('iframe, .twitter-tweet-rendered');
      const hasFallback = host.querySelector('.explore-social-fallback');
      if (!hasRenderedTweet && !hasFallback) {
        const link = document.createElement('a');
        link.href = host.dataset.socialUrl || '#';
        link.target = '_blank';
        link.rel = 'noreferrer';
        link.className = 'explore-social-fallback';
        link.innerHTML = '<i class="fa fa-arrow-up-right-from-square"></i><span>Open X post</span>';
        host.appendChild(link);
      }
    });
  };

  const loadScript = (url) => {
    if (!url) return Promise.resolve();
    if (scriptPromises.has(url)) return scriptPromises.get(url);

    const promise = new Promise((resolve) => {
      const existing = document.querySelector(`script[src="${url}"]`);
      if (existing) {
        if (existing.dataset.loaded === 'true') {
          resolve();
          return;
        }
        existing.addEventListener('load', () => resolve(), { once: true });
        existing.addEventListener('error', () => resolve(), { once: true });
        return;
      }

      const script = document.createElement('script');
      script.src = url;
      script.async = true;
      script.onload = () => {
        script.dataset.loaded = 'true';
        resolve();
      };
      script.onerror = () => resolve();
      document.body.appendChild(script);
    });

    scriptPromises.set(url, promise);
    return promise;
  };

  let activated = false;

  const activateEmbeds = async () => {
    if (activated) return;
    activated = true;

    const platforms = new Set(hosts.map((host) => String(host.dataset.platform || '').toLowerCase()));
    const loaders = [];

    if (platforms.has('x')) loaders.push(loadScript(platformUrls.x));
    if (platforms.has('instagram')) loaders.push(loadScript(platformUrls.instagram));
    if (platforms.has('tiktok')) loaders.push(loadScript(platformUrls.tiktok));

    await Promise.all(loaders);

    if (window.twttr?.widgets?.load) {
      window.twttr.widgets.load(root);
    }
    if (window.instgrm?.Embeds?.process) {
      window.instgrm.Embeds.process();
    }

    window.setTimeout(ensureTwitterFallbacks, 2200);
  };

  if (!('IntersectionObserver' in window)) {
    activateEmbeds();
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    if (!entries.some((entry) => entry.isIntersecting)) {
      return;
    }

    observer.disconnect();
    activateEmbeds();
  }, {
    rootMargin: '320px 0px'
  });

  hosts.forEach((host) => observer.observe(host));
})();
