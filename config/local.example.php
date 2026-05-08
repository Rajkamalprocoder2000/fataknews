<?php
// Example local overrides. Do not commit real secrets.

define('APP_ENV', 'development');
define('APP_URL', 'http://localhost/fataknews_complete_2/fataknews');
define('APP_ALLOWED_HOSTS', ['localhost', '127.0.0.1']);
define('APP_FORCE_HTTPS', false);
define('APP_TRUST_PROXY_HEADERS', false);
define('GOOGLE_CLIENT_ID', 'your-google-client-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your-google-client-secret');
define('GTM_CONTAINER_ID', 'GTM-XXXXXXX');
define('GA_MEASUREMENT_ID', 'G-XXXXXXXXXX');
define('AI_PROVIDER', 'xai'); // or 'groq' or 'auto'
define('XAI_API_KEY', 'your-xai-api-key');
define('XAI_MODEL', 'grok-4-fast-non-reasoning');
define('GROQ_API_KEY', '');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');
define('CONTENT_PIPELINE_DEFAULT_USER_ID', 1);
define('CONTENT_PIPELINE_AUTO_WRITE', true);
define('CONTENT_PIPELINE_AUTO_PUBLISH', false);
define('CONTENT_PIPELINE_AUTO_MIN_SCORE', 62);
define('CONTENT_PIPELINE_AUTO_MAX_PER_RUN', 3);
define('CONTENT_PIPELINE_FEEDS', [
    [
        'name' => 'India | NDTV Politics',
        'url' => 'https://news.google.com/rss/search?q=site:ndtv.com+india+politics&hl=en-IN&gl=IN&ceid=IN:en',
        'category_slug' => 'politics',
        'weight' => 1.35,
    ],
    [
        'name' => 'India | Times of India',
        'url' => 'https://news.google.com/rss/search?q=site:timesofindia.indiatimes.com+india&hl=en-IN&gl=IN&ceid=IN:en',
        'category_slug' => 'india',
        'weight' => 1.3,
    ],
    [
        'name' => 'India | Hindustan Times Business',
        'url' => 'https://news.google.com/rss/search?q=site:hindustantimes.com+india+business&hl=en-IN&gl=IN&ceid=IN:en',
        'category_slug' => 'business',
        'weight' => 1.2,
    ],
    [
        'name' => 'India | Indian Express Education',
        'url' => 'https://news.google.com/rss/search?q=site:indianexpress.com+india+education+exam&hl=en-IN&gl=IN&ceid=IN:en',
        'category_slug' => 'education',
        'weight' => 1.1,
    ],
    [
        'name' => 'India | Mint Markets',
        'url' => 'https://news.google.com/rss/search?q=site:livemint.com+india+markets+startup+economy&hl=en-IN&gl=IN&ceid=IN:en',
        'category_slug' => 'markets',
        'weight' => 1.15,
    ],
    [
        'name' => 'US | AP Politics',
        'url' => 'https://news.google.com/rss/search?q=site:apnews.com+us+politics&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'politics',
        'weight' => 1.2,
    ],
    [
        'name' => 'US | New York Times Business',
        'url' => 'https://news.google.com/rss/search?q=site:nytimes.com+us+business+economy&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'business',
        'weight' => 1.15,
    ],
    [
        'name' => 'US | CNN World',
        'url' => 'https://news.google.com/rss/search?q=site:cnn.com+us+world&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'world',
        'weight' => 1.1,
    ],
    [
        'name' => 'US | NPR Science Health',
        'url' => 'https://news.google.com/rss/search?q=site:npr.org+science+health&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'science',
        'weight' => 1.05,
    ],
    [
        'name' => 'US | Wall Street Journal Tech',
        'url' => 'https://news.google.com/rss/search?q=site:wsj.com+technology+ai&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'technology',
        'weight' => 1.1,
    ],
    [
        'name' => 'China | Xinhua',
        'url' => 'https://news.google.com/rss/search?q=site:english.news.cn+china&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'china-watch',
        'weight' => 1.1,
    ],
    [
        'name' => 'China | China Daily Business',
        'url' => 'https://news.google.com/rss/search?q=site:chinadaily.com.cn+china+business+economy&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'business',
        'weight' => 1.05,
    ],
    [
        'name' => 'China | CGTN World',
        'url' => 'https://news.google.com/rss/search?q=site:cgtn.com+china+world&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'world',
        'weight' => 1.0,
    ],
    [
        'name' => 'China | Global Times Defence',
        'url' => 'https://news.google.com/rss/search?q=site:globaltimes.cn+china+military+security&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'defence',
        'weight' => 1.0,
    ],
    [
        'name' => 'China | People Daily Culture',
        'url' => 'https://news.google.com/rss/search?q=site:english.people.com.cn+china+culture+travel&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'culture',
        'weight' => 0.95,
    ],
    [
        'name' => 'Japan | NHK World',
        'url' => 'https://news.google.com/rss/search?q=site:nhk.or.jp+japan&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'japan-news',
        'weight' => 1.1,
    ],
    [
        'name' => 'Japan | Japan Times Politics',
        'url' => 'https://news.google.com/rss/search?q=site:japantimes.co.jp+japan+politics&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'politics',
        'weight' => 1.05,
    ],
    [
        'name' => 'Japan | Nikkei Asia Business',
        'url' => 'https://news.google.com/rss/search?q=site:asia.nikkei.com+japan+business+economy&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'business',
        'weight' => 1.1,
    ],
    [
        'name' => 'Japan | Asahi Science Tech',
        'url' => 'https://news.google.com/rss/search?q=site:asahi.com+japan+science+technology&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'science',
        'weight' => 1.0,
    ],
    [
        'name' => 'Japan | Kyodo World',
        'url' => 'https://news.google.com/rss/search?q=site:english.kyodonews.net+japan+world&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'world',
        'weight' => 1.0,
    ],
    [
        'name' => 'Global | Sports Pulse',
        'url' => 'https://news.google.com/rss/search?q=cricket+football+tennis+olympics&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'sports',
        'weight' => 1.05,
    ],
    [
        'name' => 'Global | Entertainment Buzz',
        'url' => 'https://news.google.com/rss/search?q=movies+ott+celebrity+music&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'entertainment',
        'weight' => 0.95,
    ],
    [
        'name' => 'Global | Health Watch',
        'url' => 'https://news.google.com/rss/search?q=health+medicine+public+health&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'health',
        'weight' => 1.0,
    ],
    [
        'name' => 'Global | Climate Desk',
        'url' => 'https://news.google.com/rss/search?q=climate+environment+weather+emissions&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'climate',
        'weight' => 0.95,
    ],
    [
        'name' => 'Global | Travel Brief',
        'url' => 'https://news.google.com/rss/search?q=travel+tourism+aviation+hotel&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'travel',
        'weight' => 0.9,
    ],
    [
        'name' => 'Global | Auto Mobility',
        'url' => 'https://news.google.com/rss/search?q=auto+car+ev+mobility&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'auto',
        'weight' => 0.95,
    ],
    [
        'name' => 'Global | AI Frontier',
        'url' => 'https://news.google.com/rss/search?q=artificial+intelligence+ai+models+chips&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'ai',
        'weight' => 1.0,
    ],
    [
        'name' => 'Global | Startup Tracker',
        'url' => 'https://news.google.com/rss/search?q=startup+funding+vc+founder&hl=en-US&gl=US&ceid=US:en',
        'category_slug' => 'startups',
        'weight' => 0.95,
    ],
]);
define('TAG_INDEX_WHITELIST', ['modi', 'rahul-gandhi', 'budget-2026']);
define('TAG_NOINDEX_BLACKLIST', ['test', 'news', 'updates', 'temp']);
