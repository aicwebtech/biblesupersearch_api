<?php

namespace App;

use App\Models\IpAccess;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use App\Interfaces\AccessLogInterface;

class ApiAccessManager
{
    /**
     * Resolve the access record for a request, or NULL when the supplied API key
     * is unknown or revoked. Callers must treat NULL as "no access granted".
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \App\Interfaces\AccessLogInterface|null
     */
    public static function lookUp(Request $request): ?AccessLogInterface
    {
        // Deliberately not `?: null`: that collapsed the supplied key '0' to "no key
        // given", which then fell through to keyless IP access instead of being refused
        // as unknown. keyWasSupplied() draws the line instead.
        return static::lookUpHelper($request->input('key'), static::trustedDomain());
    }

    /**
     * @param  array  $input
     * @return \App\Interfaces\AccessLogInterface|null
     */
    public static function lookUpByInput($input): ?AccessLogInterface
    {
        $key = is_array($input) && array_key_exists('key', $input) ? $input['key'] : null;

        return static::lookUpHelper($key, static::trustedDomain());
    }

    /**
     * Resolve the requesting site's domain from browser-set request headers only.
     *
     * The resolved domain drives per-domain rate limiting as well as the
     * same-domain and whitelist "unlimited" grants in IpAccess::getAccessLimit().
     * It must therefore never be derived from a client-supplied request parameter
     * (e.g. a `domain` query/post field): an attacker can trivially rotate such a
     * value to obtain unlimited fresh daily quotas, or set it to the server's own
     * host / a whitelisted domain to be granted unlimited access. The Origin and
     * Referer headers are set by the browser and cannot be forged by scripts
     * running on a third-party site.
     *
     * @return string|null
     */
    public static function trustedDomain(): ?string
    {
        foreach(['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
            if(!empty($_SERVER[$header])) {
                return $_SERVER[$header];
            }
        }

        return null;
    }

    /**
     * Resolve the API server's own host (from server-set values), normalized to
     * a bare domain. Used both for the same-host "unlimited" grant and to decide
     * which request domains are privileged enough to receive their own
     * rate-limit bucket.
     *
     * @return string|null
     */
    public static function currentHost(): ?string
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';

        return static::parseDomain($host);
    }

    /**
     * A request domain is "privileged" - and therefore allowed its own
     * rate-limit bucket - only when it is explicitly whitelisted or matches the
     * API's own host.
     *
     * The requesting domain is derived from the browser-set Origin/Referer
     * headers (see trustedDomain()). Those headers cannot be forged by scripts
     * on a third-party site, but a direct (non-browser) HTTP client can set them
     * to anything. We must therefore never give an arbitrary, client-claimed
     * domain its own IpAccess record: doing so would let a client rotate the
     * header to mint unlimited fresh daily quotas. Untrusted domains are instead
     * bucketed by IP in IpAccess::findOrCreateByIpOrDomain().
     *
     * @param  string|null $domain
     * @return bool
     */
    public static function isDomainPrivileged(?string $domain): bool
    {
        if(empty($domain)) {
            return false;
        }

        return static::isWhitelisted(null, $domain) || $domain === static::currentHost();
    }

    /**
     * Was an API key actually supplied with the request?
     *
     * Only an *absent* key may fall through to keyless IP access; a supplied one must be
     * resolved or refused. The distinction cannot be a truthiness test: '0' is a perfectly
     * well-formed key parameter that PHP considers falsey, and it used to be mistaken for
     * "no key given" and quietly granted the IP bucket. An empty or whitespace-only value
     * is treated as absent, since `?key=` is how a client spells "no key". Every other
     * present value -- int, bool, array, object -- counts as supplied: malformed, but an
     * attempt to pass one, and refusing is the fail-closed answer.
     *
     * @param  mixed  $key
     * @return bool
     */
    protected static function keyWasSupplied($key): bool
    {
        if($key === NULL) {
            return FALSE;
        }

        if(is_string($key)) {
            return trim($key) !== '';
        }

        // Anything else present -- int, bool, array, object -- is a supplied value that
        // cannot be a valid key, so it is refused rather than waved through. Casting to
        // string first would be wrong: (string) FALSE is '', which would read as absent.
        return TRUE;
    }

    /**
     * Resolve the access record, or NULL when a supplied key is unknown or revoked.
     *
     * @param  mixed  $key  Raw key parameter; NULL or blank means none was supplied
     * @param  string|null  $dom
     * @return \App\Interfaces\AccessLogInterface|null
     */
    protected static function lookUpHelper($key, $dom): ?AccessLogInterface
    {
        $err  = NULL;
        $code = NULL;
        $Access = null;

        // Gated on app.experimental exactly as before: where keyed access is switched
        // off, a key parameter is ignored entirely and every request is bucketed by IP.
        if(config('app.experimental') && static::keyWasSupplied($key)) {
            // keyed access - look up key. A non-string key (module[]-style input) cannot
            // match a stored hash, so it is refused rather than handed to the query.
            $Access = is_string($key) ? ApiKey::findByKey($key) : null;

            if(!$Access || $Access->isAccessRevoked()) {
                // Key not found - no access granted
                $err  = true;
            }
        }
        
        if($err) {
            // An unknown *or revoked* key grants no access at all. Returning the
            // revoked ApiKey here would match neither the documented contract
            // nor what callers expect: ApiAccess::handle re-checks
            // isAccessRevoked(), but a caller that trusted the NULL contract and
            // skipped that check would let a revoked key through.
            return null;
        }

        // look up IP/domain record for keyless access
        $Access = $Access ?: IpAccess::findOrCreateByIpOrDomain(true, $dom);

        return $Access ?: null;
    }

    public static function isWhitelisted($ip = null, $domain = null)
    {
        $whitelist = config('bss.daily_access_whitelist');

        if(!$whitelist || !$ip && !$domain) {
            return false;
        }

        $items = explode("\n", str_replace(["\r\n", "\r"], "\n", $whitelist));
        
        foreach($items as &$i) {
            $i = self::parseDomain($i);
        }
        unset($i);

        if($ip && in_array($ip, $items) || $domain && in_array($domain, $items)) {
            return true;
        }

        return false;
    }

    static public function parseDomain($host) 
    {
        if(empty($host)) {
            return null;
        }

        $host = str_replace(array('http:','https:'), '', $host);
        $host = trim($host);
        $host = trim($host, '/');
        $pieces = explode('/', $host);
        $domain = $pieces[0];

        if(strpos($domain, 'www.') === 0) {
            $domain = substr($domain, 4);
        }

        $col_pos = strpos($domain, ':');

        if($col_pos !== FALSE) {
            $domain = substr($domain, 0, $col_pos);
        }

        $hash_pos = strpos($domain, '#');

        if($hash_pos !== FALSE) {
            $domain = substr($domain, 0, $hash_pos);
        }

        if($domain == 'localhost') {
            return null;
        }

        return $domain;
    }
}