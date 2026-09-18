<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/*
 * Self-hosted deployments of this API commonly terminate TLS upstream (load
 * balancer, nginx, Cloudflare). Without a trusted proxy the forwarded scheme is
 * ignored, Request::secure() is permanently FALSE, and the global HttpsRedirect
 * middleware would redirect every request forever.
 *
 * Nothing is trusted until the operator sets app.trusted_proxies, so spoofed
 * X-Forwarded-* headers are ignored by default.
 */
class TrustProxies extends Middleware
{
    /**
     * Get the trusted proxies.
     *
     * TrustProxies::at() still wins, so tests and service providers can
     * override the configured value.
     *
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        return parent::proxies() ?: config('app.trusted_proxies');
    }
}
