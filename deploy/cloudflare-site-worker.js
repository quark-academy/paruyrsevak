/**
 * Cloudflare Worker — публичный домен поверх подпапки хостинга.
 *
 * Зачем: сайт должен открываться как https://պոեզիա.հայ, а файлы лежат на
 * Timeweb в lab.quark.am/sevak (Laravel в подпапке). Worker переписывает хост
 * и добавляет префикс подпапки, браузер при этом остаётся на своём домене.
 *
 * Куда вставить: dashboard.cloudflare.com → Workers & Pages → ваш Worker →
 * Quick edit → заменить код → Deploy. Затем привязать маршрут к зоне:
 * Workers Routes → պոեզիա.հայ/* → этот Worker.
 *
 * В .env проекта (важно — три URL, см. DEPLOY_TIMEWEB.md, раздел 12):
 *
 *     APP_URL=https://xn--y9ahdu6dl.xn--y9a3aq
 *     ASSET_URL=https://xn--y9ahdu6dl.xn--y9a3aq/sevak
 *     POEMS_CANONICAL_BASE=https://xn--y9ahdu6dl.xn--y9a3aq
 *     APP_FORCE_ROOT_URL=true
 *
 * Проверка после деплоя:
 *   curl -s -o /dev/null -w '%{http_code}\n' https://պոեզիա.հայ/
 *   curl -s -o /dev/null -w '%{http_code}\n' https://պոեզիա.հայ/sevak/build/manifest.json
 *   200 и 200 → работает.
 */

const ORIGIN_HOST = 'lab.quark.am';
const BASE_PATH = '/sevak';

export default {
    async fetch(request) {
        const url = new URL(request.url);
        const publicHost = url.hostname;
        const publicProto = url.protocol.replace(':', '');

        // Сайт уже открыт по адресу origin (например, lab.quark.am/sevak) —
        // не трогаем, иначе получится /sevak/sevak и петля.
        if (url.hostname === ORIGIN_HOST) {
            return fetch(request);
        }

        url.hostname = ORIGIN_HOST;

        // Префикс подпапки добавляется ровно один раз: запрос к самому
        // /sevak/... (ассеты, админка) уже содержит его.
        if (url.pathname !== BASE_PATH && !url.pathname.startsWith(`${BASE_PATH}/`)) {
            url.pathname = url.pathname === '/' ? `${BASE_PATH}/` : `${BASE_PATH}${url.pathname}`;
        }

        // Приложение должно знать, по какому адресу пришёл посетитель: от
        // этого зависят подписи ссылок на аудио и проверка Origin/Referer.
        const headers = new Headers(request.headers);
        headers.set('X-Forwarded-Host', publicHost);
        headers.set('X-Forwarded-Proto', publicProto);

        // Метод, тело и заголовки передаются как есть: POST в админке и
        // загрузка mp3 должны работать без переупаковки.
        return fetch(new Request(url.toString(), {
            method: request.method,
            headers,
            body: ['GET', 'HEAD'].includes(request.method) ? undefined : request.body,
            redirect: 'manual',
        }));
    },
};
