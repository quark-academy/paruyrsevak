/**
 * Cloudflare Worker — прозрачный прокси к ElevenLabs API.
 *
 * Зачем: IP хостинга timeweb блокируется Cloudflare перед api.elevenlabs.io
 * (ответ «Just a moment...» / 403). Запросы Worker'а идут из сети Cloudflare,
 * поэтому проходят.
 *
 * Куда вставить: dashboard.cloudflare.com → Workers & Pages → ваш Worker →
 * Quick edit → заменить код → Deploy. Затем в .env проекта:
 *
 *     ELEVENLABS_BASE_URL=https://<ваш-worker>.workers.dev/v1
 *
 * ВАЖНО (иначе ломаются /v1/forced-alignment и /v1/speech-to-text):
 *  - передавать method, path, query, заголовки и СЫРОЕ тело запроса целиком;
 *  - НЕ читать тело как текст (request.text()) — это портит бинарные mp3;
 *  - не задавать Content-Type вручную: для multipart в нём лежит boundary.
 *
 * Проверка после деплоя (с сервера):
 *   curl -s -o /dev/null -w '%{http_code}\n' \
 *     -H "xi-api-key: $ELEVENLABS_API_KEY" \
 *     https://<ваш-worker>.workers.dev/v1/models
 *   200 → прокси работает; 403 «Just a moment» → блокировка всё ещё срабатывает.
 */

const UPSTREAM = 'https://api.elevenlabs.io';

export default {
    async fetch(request) {
        const url = new URL(request.url);
        const target = UPSTREAM + url.pathname + url.search;

        const headers = new Headers(request.headers);
        // Заголовки, которые нельзя/бессмысленно копировать upstream.
        headers.delete('host');
        headers.delete('cf-connecting-ip');
        headers.delete('cf-connecting-ipv6');
        headers.delete('cf-ipcountry');
        headers.delete('cf-ray');
        headers.delete('cf-visitor');
        headers.delete('x-forwarded-proto');

        const hasBody = !['GET', 'HEAD'].includes(request.method);

        const upstream = await fetch(target, {
            method: request.method,
            headers,
            // request.body — ReadableStream, поэтому multipart и mp3 не портятся.
            body: hasBody ? request.body : undefined,
            redirect: 'follow',
        });

        const responseHeaders = new Headers(upstream.headers);
        responseHeaders.set('access-control-allow-origin', '*');
        // Content-Length после возможной переупаковки лучше не фиксировать.
        responseHeaders.delete('content-length');

        return new Response(upstream.body, {
            status: upstream.status,
            statusText: upstream.statusText,
            headers: responseHeaders,
        });
    },
};
