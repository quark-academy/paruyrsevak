# ՓՈՓՈԽՈՒԹՅՈՒՆՆԵՐ — ՔԱՅԼ 2 (Step 2 CHANGELOG)

**Նախագիծ՝** Պարույր Սևակ — Shorts-ոճի կարաոկե պլատֆորմ
**Ամսաթիվ՝** 2026‑10‑13
**Կարգավիճակ՝** Ավարտված (36 tests green)

---

## 0. Ամփոփ (Summary)

- Ավելացվեց **հանրային էջ `/{slug}`** (կանոնիկ URL)։
- Ավելացվեց **Vue 3 (JS, առանց TS)** Shorts-ոճի **ուղղահայաց պլեյեր**։
- **SEO**՝ դինամիկ `<title>`, `<meta description>`, **canonical**, Open Graph, Twitter, JSON‑LD։
- **Կարաոկե**՝ բառացի համաժամեցում `audio.currentTime`‑ի հետ (`poem_words` տայմինգներով)։
- **Անիմացված SVG ուրվանկար**՝ լցվում է ոսկեգույնով (progress) / բաբախում է (սպասում)։

**Տեխնոլոգիաներ՝** Laravel 12.69, Vue **3.5.44** (Composition API, `<script setup>`), `@vitejs/plugin-vue` 6, Tailwind v4, Vite 7։

---

## 1. Փոփոխված / նոր ֆայլեր

| Ֆայլ | Դերը |
|---|---|
| `config/poems.php` | canonical base, short URLs, public generation, player defaults |
| `app/Support/Seo.php` | canonical/title/description helper |
| `app/Services/PoemPlayerPayload.php` | Vue-payload շինարար (lines, words, prev/next) |
| `app/Http/Controllers/PoemShortController.php` | `/{slug}` էջ (invokable) |
| `app/Http/Controllers/Api/PoemPlayerController.php` | JSON API (show / generate) |
| `routes/short.php` | `GET /{slug}` (registered **last**) |
| `routes/api.php` | `GET /api/poems/{poem}`, `POST /api/poems/{poem}/generate` |
| `resources/views/poems/short.blade.php` | standalone Blade + payload JSON + noscript |
| `resources/js/poem-shorts.js` | Vue mount entry |
| `resources/js/components/*.vue` | 4 Vue component |
| `resources/js/composables/*.js` | 3 composable |
| `resources/css/app.css` | Tailwind `@source *.vue` + karaoke/animation CSS |
| `vite.config.js` | Vue plugin + `poem-shorts` entry |
| `resources/views/layouts/app.blade.php` | canonical/OG |
| `tests/Feature/PoemShortPageTest.php` | 7 թեստ |
| `tests/Feature/PoemPlayerApiTest.php` | 5 թեստ |
| `tests/Feature/SeoTest.php` | 5 թեստ |

---

## 2. Vue փաստաթղթի կառուցվածք (Architecture)

```
resources/js/
├── poem-shorts.js              # createApp + mount(#poem-shorts)
├── components/
│   ├── PoemShorts.vue          # արմատային компонент (state machine, layout)
│   ├── SevakSilhouette.vue     # անիմացված SVG ուրվանկար
│   ├── KaraokeLyrics.vue       # բառացի կարաոկե (rendering + autoscroll)
│   └── PlayerControls.vue      # play/seek/rate/loop
└── composables/
    ├── useAudioPlayer.js       # HTMLAudioElement + rAF
    ├── useKaraoke.js           # active/sung/pending state
    └── useSwipe.js             # ուղղահայաց swipe navigation
```

**Հոսք (data flow)՝**

1. Blade‑ը render‑ում է `poem-shorts-payload` JSON (`@json`), ոչ թե տվյալները «կապում» ձեռքով։
2. `poem-shorts.js` կարդում է JSON‑ը → `createApp(PoemShorts, { payload })`.
3. `PoemShorts`‑ը `useAudioPlayer()` + `useKaraoke()`‑ով սարքում է reactive վիճակ։
4. Ենթակոմպոնենտները ստանում են **props** և ուղարկում **emits** (`toggle`, `seek`, `rate`, `loop`)։

**State machine (`PoemShorts`)՝** `idle → loading → ready`, ինչպես նաև `generating`, `missing`, `error`։

---

## 3. Կարաոկե համաժամեցման տրամաբանություն

**Տվյալների աղյուսակը՝** `poem_words(word, start_time, end_time, word_order)` → payload`lines: [[{w,s,e}]]`։

1. **rAF loop** (`useAudioPlayer`). Ամեն կադր `time.value = audio.currentTime`։
   `timeupdate` event‑ը (~4Hz) չի օգտագործվում՝ հարթ ընդգծման համար։
2. **Binary search** (`useKaraoke`)՝ `lastStartedIndex` = վերջին բառ, որի `s <= time` → **O(log n)**։
3. **Երեք վիճակ** (`stateFor(i)`)՝
   - `pending` — դեռ չի հնչել (`rgba(255,255,255,.32)`)
   - `sung` — արդեն հնչել է (`rgba(232,196,106,.6)`)
   - `active` — **հիմա** հնչում է → ոսկեգույն + `scale(1.06)` + `text-shadow`
   (հանդուրժողականություն՝ `+0.05s`՝ միկրո‑ընդմիջումների համար)
4. **Autoscroll**՝ `KaraokeLyrics` հետևում է `activeIndex`‑ին և `scrollTo({behavior:'smooth'})` կենտրոնացնում է բառը (container՝ `relative` + hidden scrollbar)։
5. **Word tap** → `emit('seek', word.s)` → `seekRatio` → `audio.currentTime = s` → playback։
6. **Silhouette progress** = `currentTime / duration` → ոսկու լցման բարձրություն (`fillY = 260 * (1 - progress)`)։

---

## 4. SEO — թեգեր և տրամաբանություն

**Աղբյուրները՝** `poems.meta_title`, `poems.meta_description` (fallback՝ `title` / `excerpt`)։

Blade (`resources/views/poems/short.blade.php`)․
```blade
<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<link rel="canonical" href="{{ $seoCanonical }}">
```

**Canonical կանոնը՝** `{POEMS_CANONICAL_BASE}/{slug}`։
`config/poems.php` → `'canonical_base' => env('POEMS_CANONICAL_BASE', env('APP_URL'))`։
`.env`․ `POEMS_CANONICAL_BASE=https://subdomain.domain.am`։

**`App\Support\Seo` helper․**
- `base()` — base domain (առանց trailing slash)
- `canonical($path = null)` — `base` / `base/{path}`
- `forPoem(Poem)` — `base/{slug}`
- `titleForPoem()` / `descriptionForPoem()`

**Այլ թեգեր․** Open Graph (`og:title/description/url/type/site_name`), Twitter (`summary`), `robots: index,follow`, **JSON‑LD** (`schema.org/CreativeWork`, author = «Պարույր Սևակ»)։

**Կանոնականացման քաղաքականություն․**
- `/{slug}` և `/poems/{slug}` → **նույն canonical** (`/{slug}`) → duplicate content չկա։
- `/` և `/poems` → canonical = `base/`։

---

## 5. Tailwind ոճավորում (Mobile-first, dark poetic)

- **Fullscreen՝** `h-[100dvh]`, `overflow-hidden`, safe‑area՝ `pt-[max(0.9rem,env(safe-area-inset-top))]`, `pb-[max(1.1rem,env(safe-area-inset-bottom))]`։
- **Ֆոն՝** `#0b0a09` + radial ոսկե glow (`rgba(232,196,106,.10)`)։
- **Ուրվանկար՝** `fill-stone-800/90` (base) + `<clipPath>`‑ով կտրված ոսկե ուղղանկյուն (fill) + ոսկե outline։
- **Անիմացիա (app.css)․**
  - `@keyframes sevak-breathe` → `.sevak-breathe` (opacity .72→1, scale 1→1.02, 3.4s infinite) — սպասման վիճակ։
  - `.karaoke-word--pending/sung/active` → գույն/մասշտաբ/text‑shadow անցումներ (0.25s)։
  - `.karaoke-mask` → `mask-image` (վերև/ներքև fade)։
  - `.karaoke-scroller` → scrollbar թաքցված, `scroll-behavior: smooth`։
  - `prefers-reduced-motion` → անիմացիան անջատվում է։
- **Tailwind scanning․** `app.css`‑ում ավելացված է `@source '../**/*.vue';`՝ Vue class‑ների հայտնաբերման համար (v4 CSS‑first)։
- **Shorts էֆեկտներ․** վերևի բարակ progress գիծ, `animate-ping` status կետ, ուղղահայաց նավիգացիայի rail, share toast։

---

## 6. Երթուղիներ և API

| Method | URI | Name | Նշանակում |
|---|---|---|---|
| GET | `/{slug}` | `poems.short` | **Shorts էջ** (canonical) |
| GET | `/api/poems/{poem}` | `api.poems.player` | player payload JSON |
| POST | `/api/poems/{poem}/generate` | `api.poems.generate` | lazy ԱԻ գեներացիա (throttle:10,1) |

**Ուղղորդման անվտանգություն․** `/{slug}` գրանցվում է `bootstrap/app.php` **`then`** callback‑ում **admin‑ից ՀԵՏՈ** → `/admin`, `/poems`, `/up`, `/sitemap.xml` չեն գողանում։ Լրացուցիչ՝ negative lookahead (`(?!admin$|up$|api$|…)`):

---

## 7. Player API (`PoemPlayerController`)

**GET `/api/poems/{poem}`** → `PoemPlayerPayload` + `status` (`ready`/`missing`).

**POST `/api/poems/{poem}/generate`** — lazy ԱԻ գեներացիա (սպասման վիճակում ուրվանկարը բաբախում է)․
1. Աուդիոն առկա է → `ready` (`cached: true`), API կանչ **չկա**։
2. `poems.public_generation=false` → **403** `{status:"disabled"}`։
3. `ElevenLabsService::isConfigured()=false` → **422** `{status:"unconfigured"}`։
4. Հակառակ դեպքում → `generateAudioForPoem()` → `.mp3` + `poem_words` → **200** `{status:"ready"}`։
5. Բացառություն → **500** `{status:"error", message}` (UI՝ «Փորձիր նորից»)։

**Անվտանգություն․** `throttle:10,1` (10 կանչ/րոպե), cost-guard flag, `Accept: application/json`։

**Payload-ի կառուցվածքը․**
```json
{
  "slug": "demo-garun",
  "title": "Գարուն (դեմո)",
  "author": "Պարույր Սևակ",
  "content": "…ամբողջ տեքստը…",
  "audioUrl": "/storage/ai_audio/….mp3 | null",
  "isOriginal": false,
  "canonical": "https://subdomain.domain.am/demo-garun",
  "lines": [ [ {"w":"Գարունը","s":0.0,"e":0.4}, … ], … ],
  "readingUrl": "/poems/demo-garun",
  "playerUrl": "/api/poems/demo-garun",
  "generateUrl": null,
  "autoplay": false, "background": "#0b0a09", "gold": "#e8c46a",
  "prev": {"slug":"demo-ashun","title":"…","url":"/demo-ashun"},
  "next": {"slug":"demo-gisher","title":"…","url":"/demo-gisher"}
}
```

**Lines grouping (`PoemPlayerPayload::buildLines`)․** բառերը խմբավորվում են **ըստ `content` տողերի** (whitespace token count)։ Անհամապատասխանության դեպքում → մեկ հոսող բլոկ (fallback)։

---

## 8. ENV / Config

```env
POEMS_CANONICAL_BASE=https://subdomain.domain.am   # canonical domain
POEMS_SHORT_URLS=true                              # /{slug} միացված
POEMS_PUBLIC_GENERATION=false                      # public ԱԻ գեներացիա (ծախս!)
POEMS_AUTOPLAY=false
POEMS_BACKGROUND=#0b0a09
POEMS_GOLD=#e8c46a
```

`config/poems.php` → `canonical_base`, `short_urls`, `public_generation`, `player.{autoplay,background,gold}`։

---

## 9. Դեպլոյ (Deployment)

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # Vue + Tailwind (vite)
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Նախապայման․** `POEMS_CANONICAL_BASE` = իրական արտադրական domain (պարտադիր canonical‑ի ճշգրտության համար)։
**Զգուշացում․** մի գործարկիր `config:cache` **միաժամանակ** `php artisan test`‑ի հետ (cached config‑ը անտեսում է `phpunit.xml` env‑ը)։ Օգտագործիր `composer test` (նա սկսում է `config:clear`‑ով)։

---

## 10. Թեստեր (Tests)

```bash
composer test      # config:clear + php artisan test
```

| Ֆայլ | Ստուգում |
|---|---|
| `PoemShortPageTest` | `/{slug}` 200, SEO թեգեր, canonical, lines grouping, prev/next, reserved prefixes, shared canonical |
| `PoemPlayerApiTest` | payload/status, ready + audio, 403 disabled, 422 unconfigured, գեներացիա (`Http::fake`) |
| `SeoTest` | base normalization, `forPoem`, fallbacks, home/catalogue canonical |

**Արդյունք․** `36 passed (98 assertions)`։

---

## 11. Սահմանափակումներ (Limitations / Next)

- **Autoplay**՝ բրաուզերների քաղաքականությունը կարող է արգելել (բացակայում է «user gesture») → առաջին tap‑ից հետո է նվագարկվում։
- **Գեներացիան սինխրոն է** (`POST …/generate`)՝ երկար տեքստերի համար ցանկալի է queue/job։
- **Shorts-ը՝ մեկ էջ = մեկ բանաստեղծություն** (ոչ անվերջ scroll)․ անցումը՝ swipe/rail/`ArrowUp|Down`։
- **`poem_words` առանց կետադրական տարանջատման** → կետադրությունը մնում է բառի մեջ։
- **Next Step 3 (առաջարկ)․** likes/views model, playlist (անվերջ scroll feed), queue-based generation, Service Worker (offline MP3), OG image generation (SVG→PNG), Lighthouse/SEO աուդիտ։


