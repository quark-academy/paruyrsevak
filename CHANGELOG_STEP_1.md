# ՓՈՓՈԽՈՒԹՅՈՒՆՆԵՐ — ՔԱՅԼ 1 (Step 1 CHANGELOG)

**Նախագիծ՝** Պարույր Սևակ — բանաստեղծությունների աուդիո/կարաոկե հարթակ
**Ամսաթիվ՝** 2026‑10‑13
**Կարգավիճակ՝** Ավարտված (all tests green)

---

## 0. Կարևոր տարբերություն (Stock versions)

| Պարամետր | Պահանջ | Փաստացի |
|---|---|---|
| Framework | Laravel 8.3 | **Laravel 12.69.3** |
| PHP | 8.3 | **PHP 8.2.30** |
| CSS | Tailwind | **Tailwind v4** (`@tailwindcss/vite`) |
| DB (dev) | MySQL | SQLite (`DB_CONNECTION=sqlite`) |
| DB (prod) | MySQL | MySQL (պատրաստ է, `utf8mb4`) |

> Որոշում․ Կոդը գրված է **փաստացի** կոդային բազայի համաձայն (Laravel 12)։ Laravel 12․ անանուն միգրացիաներ, `casts()` մեթոդ, `bootstrap/app.php` կոնֆիգուրացիա։ Setup‑ը աշխատում է առանց downgrade‑ի։

---

## 1. Միգրացիաներ (Migrations)

| Ֆայլ | Աղյուսակ | Սյուներ |
|---|---|---|
| `2026_10_13_000001_create_poems_table.php` | `poems` | `id`, `title`, `slug` (unique), `content` (longText), `audio_path` (nullable), `is_original` (bool, default false), `meta_title`, `meta_description`, `timestamps` |
| `2026_10_13_000002_create_poem_words_table.php` | `poem_words` | `id`, `poem_id` (FK→`poems`, cascade), `word`, `start_time` (decimal 8,2), `end_time` (decimal 8,2), `word_order` (unsigned int), `timestamps`, ինդեքս `(poem_id, word_order)` |
| `2026_10_13_000003_add_is_admin_to_users_table.php` | `users` | `is_admin` (bool, default false) |

**Ինֆոյի բազա (MySQL)․** `utf8mb4` / `utf8mb4_unicode_ci` աջակցում է հայերեն տեքստին։
**Գործարկում․** `php artisan migrate --force`

---

## 2. Մոդելներ (Models)

### `App\Models\Poem`
- **Fillable․** `title`, `slug`, `content`, `audio_path`, `is_original`, `meta_title`, `meta_description`
- **Cast․** `is_original` → `boolean`
- **Relation․** `words()` → `hasMany(PoemWord)`, պատվիրված `word_order`‑ով
- **Route binding․** `getRouteKeyName()` → `slug`
- **Slug․** `booted()` auto‑generation (`saving`)։ `Str::slug()` հայերենը տրանսլիտավորում է (օրինակ `Սևակ Պարույր` → `sevak-parvouyr`)։ `makeUniqueSlug()` ապահովում է ունիկալությունը։
- **Helpers․** `hasAudio()`, `audioUrl()`, `voiceSourceLabel()`, `excerpt($limit)`

### `App\Models\PoemWord`
- **Fillable․** `poem_id`, `word`, `start_time`, `end_time`, `word_order`
- **Cast․** `start_time`/`end_time` → `float`, `word_order` → `integer`
- **Relation․** `poem()` → `belongsTo(Poem)`
- **Helper․** `duration()` (end − start)

### `App\Models\User` (թարմացում)
- **Fillable․** ավելացվեց `is_admin`
- **Cast․** ավելացվեց `is_admin` → `boolean`

---

## 3. Սերվիսներ (Services)

### `App\Services\ElevenLabsService`
- **Կոնֆիգուրացիա․** `config('services.elevenlabs.*')` (`key`, `voice_id`, `model`, `output_format`, `timeout`, `disk`, `directory`)։
- **Endpoints․** `POST {base}/text-to-speech/{voice_id}` և `/with-timestamps`։
- **Փոքրիկ գեներացիա (Lazy Generation)․**
  1. Որոշիչ ուղի՝ `ai_audio/{slug}-{sha1_16}.mp3` (`pathForPoem()`)։
  2. Ֆայլը կա → վերադարձվում է քեշից (`cached: true`)։
  3. Ֆայլը չկա → API հարցում, `.mp3` պահպանում `public` disk‑ում։
- **Հիմնական մեթոդներ․**
  - `isConfigured(): bool`
  - `audioExists(Poem): bool`
  - `generateAudioForPoem(Poem, bool $force): array{path,url,words,cached}`
  - `synthesize(string, ?Poem, array $options): array{audio,words,alignment}`
  - `alignmentToWords(string, array): array` — նշանային (character) alignment → բառային տայմինգ
  - `distributeWords(string, float, float): array` — fallback համամասնական բաշխում
  - `estimateWordsFromAudio()` — MP3 bitrate‑ից տևողության գնահատում
  - `forget(Poem)` — քեշի մաքրում (`.mp3` + `.json`)
- **Տայմինգի պահպանում․** alignment‑ը պահվում է `.mp3`‑ի կողքին՝ `{name}.json`։
- **Սխալներ․** `RuntimeException` (`isConfigured()` չկա / դատարկ audio) + `Log::error`։

### `App\Services\PoemWordSynchronizer`
- `persist(Poem, array $words): int` — transactional replace (`poem_words`)։
- `syncFromContent(Poem): int` — տեքստը բառերի է բաժանում, պահում է հին տայմինգները ըստ դիրքի։

---

## 4. Օժանդակ (Support)

### `App\Support\WordTokenizer`
- `split(string): array` — whitespace‑ով բառաբաժանում, normalizացիա։
- `count(string): int`, `isWhitespace(string): bool`
- Օգտագործվում է․ alignment → բառերի քարտեզագրում, բառերի ավտո‑գեներացիա։

---

## 5. Միջնորդ ծրագրեր (Middleware)

### `App\Http\Middleware\EnsureUserIsAdmin`
- Ստուգում է `user()->is_admin`։
- Հակառակ դեպքում՝ `403 Forbidden`։
- Գրանցված alias՝ `admin` (`bootstrap/app.php`)։
- Ավելացված է նաև․ `redirectGuestsTo('/admin/login')`, `redirectUsersTo('/admin')`։

---

## 6. Երթուղիներ (Routes)

### Հրապարակային (`routes/web.php`)
| Method | URI | Name | Action |
|---|---|---|---|
| GET | `/` | `home` | `PoemController@index` |
| GET | `/poems` | `poems.index` | `PoemController@index` |
| GET | `/poems/{poem}` | `poems.show` | `PoemController@show` (binding՝ `slug`) |
| GET | `/sitemap.xml` | `sitemap` | `SitemapController` |

### Վահանակ (`routes/admin.php`, prefix `/admin`, name `admin.`)
| Method | URI | Name | Action |
|---|---|---|---|
| GET/POST | `/admin/login` | `login`, `login.attempt` | `AuthController` |
| POST | `/admin/logout` | `logout` | `AuthController@logout` |
| GET | `/admin` | `dashboard` | `DashboardController` |
| — | `/admin/poems` | `poems.*` | `resource` CRUD |
| POST | `/admin/poems/{poem}/audio` | `poems.audio.store` | օրիգինալ .mp3 վերբեռնում |
| POST | `/admin/poems/{poem}/audio/generate` | `poems.audio.generate` | ԱԻ գեներացիա (lazy) |
| POST | `/admin/poems/{poem}/audio/align` | `poems.audio.align` | ԱԻ + alignment import |
| DELETE | `/admin/poems/{poem}/audio` | `poems.audio.destroy` | աուդիոյի ջնջում |
| GET | `/admin/poems/{poem}/timings` | `poems.timings` | կարաոկե խմբագիր |
| POST | `/admin/poems/{poem}/timings` | `poems.timings.update` | տայմինգի պահպանում |
| POST | `/admin/poems/{poem}/timings/sync` | `poems.timings.sync` | բառերի վերագեներացիա |

**Պաշտպանություն․** `auth` + `admin` middleware։

---

## 7. Վահանակ (Admin Panel)

- **Մուտք․** `/admin/login` → `AuthController` (`email`, `password`, `remember`)։ Միայն `is_admin=true`։
- **Գլխավոր․** վիճակագրություն (բանաստեղծություններ, բառեր, արխիվ/ԱԻ, ElevenLabs պատրաստվածություն)։
- **CRUD․** ստեղծում/խմբագրում/ջնջում, որոնում, էջատում (`paginate(15)`)։
- **Աուդիո․** օրիգինալ `.mp3` վերբեռնում (`original_audio/`) կամ ElevenLabs ԱԻ գեներացիա (`ai_audio/`)։
- **Կարաոկե խմբագիր․** աղյուսակ `word` / `start_time` / `end_time`։
  - «S» / «E» կոճակներ՝ ընթացիկ ժամանակի նշում (`audio.currentTime`)։
  - Auto‑focus հաջորդ բառին, `Space` → play/pause։
  - «Վերագեներացնել բառերը» և «Համաժամեցնել ElevenLabs‑ից»։
- **Հեղինակավորում (FormRequest)․** `PoemRequest`, `PoemAudioRequest`, `SaveTimingsRequest`։

---

## 8. Ֆայլեր և Տեսարաններ (Views)

| Ճանապարհ | Նշանակում |
|---|---|
| `layouts/app.blade.php` | հրապարակային mobile‑first layout |
| `layouts/admin.blade.php` | վահանակի layout |
| `poems/index.blade.php` | կատալոգ + որոնում |
| `poems/show.blade.php` | **կարաոկե плеер** (ժամանակացույց, արագություն, բառային ընդգծում) |
| `admin/...` | login, dashboard, poems CRUD, timings |
| `sitemap.blade.php` | XML sitemap (SEO) |

**Frontend․** Tailwind v4, `@vite(['resources/css/app.css', 'resources/js/app.js'])`։ Vite manifest‑ի բացակայության դեպքում styles‑ը lazy‑որեն անջատվում են (graceful fallback)։

---

## 9. Կոնսոլային հրաման (Artisan Command)

```bash
php artisan poem:generate-audio {poem}   # {poem} = id կամ slug
        [--force]                        # վերագեներացիա
        [--no-words]                     # առանց տայմինգի import
```

- Գրանցված է `bootstrap/app.php` → `withCommands(app/Console/Commands)`։
- Օգտագործում է `ElevenLabsService` + `PoemWordSynchronizer`։

---

## 10. Կարգավորում (Config & ENV)

**`config/services.php`** → `elevenlabs` բլոկ․
`key`, `base_url`, `voice_id`, `model`, `output_format`, `language`, `with_timestamps`, `timeout`, `stability`, `similarity_boost`, `style`, `use_speaker_boost`, `disk`, `directory`։

**`.env` / `.env.example` ավելացվածներ․**
```
ELEVENLABS_API_KEY=          # պարտադիր
ELEVENLABS_VOICE_ID=         # պարտադիր
ELEVENLABS_MODEL=eleven_multilingual_v2
ELEVENLABS_OUTPUT_FORMAT=mp3_44100_128
ELEVENLABS_LANGUAGE=hy
ELEVENLABS_WITH_TIMESTAMPS=true
ELEVENLABS_DIRECTORY=ai_audio
FILESYSTEM_DISK=public
APP_LOCALE=hy
ADMIN_NAME / ADMIN_EMAIL / ADMIN_PASSWORD
# MySQL (production) բլոկը նույնպես պատրաստ է
```

---

## 11. Տվյալների լրացում (Seeders & Factories)

- `AdminUserSeeder` — `firstOrNew` + `Hash::make`, `is_admin=true`։ ENV‑ից՝ `ADMIN_EMAIL`, `ADMIN_PASSWORD`։
- `PoemSeeder` — դեմո բանաստեղծություն (`demo-ashun`) + բառեր։
- `DatabaseSeeder` → կանչում է երկուսին էլ։
- Factories՝ `PoemFactory`, `PoemWordFactory` (թեստերի համար)։

---

## 12. Դեպլոյ (Deployment) — իդեալական քայլեր

```bash
# 1. Կախվածություններ
composer install --no-dev --optimize-autoloader

# 2. ENV
cp .env.example .env
php artisan key:generate
#   → սահմանիր APP_ENV=production, APP_DEBUG=false, APP_URL
#   → DB_CONNECTION=mysql + DB_* արժեքները
#   → ELEVENLABS_API_KEY + ELEVENLABS_VOICE_ID

# 3. Ինֆոյի բազա
php artisan migrate --force
php artisan db:seed --force          # վահանակի օգտատեր + դեմո

# 4. Ֆայլեր
php artisan storage:link             # public/storage → storage/app/public

# 5. Frontend
npm ci && npm run build

# 6. Օպտիմիզացիա / քեշ
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Իրավունքներ
chmod -R ug+rwx storage bootstrap/cache
```

**Պահանջներ․** PHP ≥ 8.2 (`fileinfo`, `pdo_mysql`, `mbstring`, `curl`), MySQL 8 (`utf8mb4`), Node ≥ 18, գրավոր `storage/`։
**Webhook/Cron․** պարտադիր չէ (lazy generation)։ Կամայական ժամանակ՝ `php artisan poem:generate-audio {slug}`։

---

## 13. Թեստեր (Tests)

```bash
php artisan test
```

- `PoemModelTest` — slug (հայերեն), ունիկալություն, relation/cascade։
- `AdminPanelTest` — guest redirect, 403, login, CRUD, տայմինգի պահպանում։
- `ElevenLabsServiceTest` — `Http::fake` + `Storage::fake`, alignment → բառեր, քեշ, `cached: true`։
- `PublicPoemPageTest` — home, slug page, sitemap, որոնում։

**Արդյունք․** `19 passed (49 assertions)`։

---

## 14. Սահմանափակումներ (Limitations / Next)

- **Slug տրանսլիտավորում․** կախված է `Str` (voku) քարտեզից; անոմալ դեպքերում ավելացվում է random suffix։
- **Բառաբաժանում․** միայն whitespace‑ով (կետադրական նշանները մնում են բառի մեջ)։ Ապագա՝ կետադրության առանձնացում։
- **Alignment բացակայության դեպքում** տայմինգը գնահատողական է (bitrate ենթադրությամբ), ձեռքով ճշգրտումը պարտադիր է։
- **`is_original=false`** դեպքում աուդիոն համարվում է ԱԻ։ Կարող է ավելացվել `voice_source` enum։
- **Next Step 2 (առաջարկ)․** հեղինակների/ժողովածուների model, playlist, SEO sitemap ինդեքս, rate‑limit, admin roles, queue‑based generation։



