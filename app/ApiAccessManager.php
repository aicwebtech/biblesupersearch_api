<?php

namespace App;

use App\Models\IpAccess;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use App\Interfaces\AccessLogInterface;

class ApiAccessManager
{
    public static function lookUp(Request $request): AccessLogInterface
    {
        $key = $request->input('key') ?: null;
        return static::lookUpHelper($key, static::trustedDomain());
    }

    public static function lookUpByInput($input): AccessLogInterface
    {
        $key = isset($input['key']) ? $input['key'] : null;
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

    protected static function lookUpHelper($key, $dom): AccessLogInterface
    {
        $err  = NULL;
        $code = NULL;
        $Access = null;

        if(config('app.experimental') && !$err && $key) {
            // keyed access - look up key
            $Access = ApiKey::findByKey($key);

            if(!$Access || $Access->isAccessRevoked()) {
                // Key not found - no access granted
                $err  = true;
            }
        }
        
        if(!$err) {        
            // look up IP/domain record for keyless access                       
            $Access = $Access ?: IpAccess::findOrCreateByIpOrDomain(true, $dom);
        }

        return $Access ?: false;
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