<?php

namespace App;

class Helpers 
{

    /**
     * Memoized bound-variable ceilings, keyed by connection name ('' for the default).
     *
     * @var array<string, int>
     */
    protected static $max_bound_variables = [];

    /*
     * Sorts an array of strings by string length
     */
    public static function sortStringsByLength(&$array, $dir = 'DESC') 
    {
        return usort($array, function($a, $b) use ($dir) {
            $comp = strlen($a) <=> strlen($b);
            $comp = ($dir == 'DESC') ? $comp * -1 : $comp;
            return $comp;
        });
    }

    /**
     * PHP words that cannot be used as a class name.
     *
     * Keywords and magic constants are rejected by the parser; the "soft" types
     * (int, string, ...) are rejected by the engine with "Cannot use X as class
     * name as it is reserved". Comparison is case-insensitive because PHP
     * keywords are.
     *
     * Verified empirically against PHP 8.2, 8.3, 8.4 and 8.5 (the supported
     * range) by attempting `class <word> {}` in a subprocess -- see
     * Tests\Unit\Helpers\ReservedPhpWordTest::testEveryListedWordIsRejectedByPhp.
     * This is the union across those versions, so a word reserved only in a
     * newer release is still listed.
     *
     * Deliberately excluded: names that merely collide with a built-in class
     * (Attribute, Closure, Generator, ...). Those are legal inside a namespace,
     * which is where every generated class lives, so rejecting them would turn
     * working module names away for no reason.
     *
     * @var array<int, string>
     */
    protected static $php_reserved_words = [
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class',
        'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else',
        'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch',
        'endwhile', 'eval', 'exit', 'extends', 'final', 'finally', 'fn', 'for', 'foreach',
        'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once',
        'instanceof', 'insteadof', 'interface', 'isset', 'list', 'match', 'namespace',
        'new', 'or', 'print', 'private', 'protected', 'public', 'readonly', 'require',
        'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset',
        'use', 'var', 'while', 'xor', 'yield',
        // Soft-reserved type names, also illegal as class names.
        'bool', 'false', 'float', 'int', 'iterable', 'mixed', 'never', 'null', 'object',
        'parent', 'self', 'string', 'true', 'void',
        // Magic constants. The parser rejects these as an identifier, so they are
        // illegal as a class name even inside a namespace. __PROPERTY__ is 8.4+,
        // listed anyway because 8.4 and 8.5 are supported.
        '__class__', '__dir__', '__file__', '__function__', '__line__', '__method__',
        '__namespace__', '__property__', '__trait__',
    ];

    /**
     * The reserved word list, for sharing with the front end.
     *
     * @return array<int, string>
     */
    public static function phpReservedWords() 
    {
        return static::$php_reserved_words;
    }

    /**
     * Is the given word illegal as a PHP class name?
     *
     * @param  string|null  $word
     * @return bool
     */
    public static function isReservedPhpWord($word) 
    {
        if(!is_string($word) || $word === '') {
            return FALSE;
        }

        return in_array(strtolower($word), static::$php_reserved_words, TRUE);
    }

    /**
     * Prefix a generated class base name when it would collide with a PHP
     * reserved word, otherwise return it unchanged.
     *
     * Names that are already legal are returned as-is so existing generated
     * classes (En, De, Kjv, ...) keep their names and no migration is needed.
     *
     * @param  string  $base
     * @param  string  $prefix
     * @return string
     */
    public static function safeGeneratedClassName($base, $prefix = 'Lang') 
    {
        return static::isReservedPhpWord($base) ? $prefix . $base : $base;
    }

    /**
     * Return the URL only when it is safe to place in an href, else NULL.
     *
     * Guards against script-capable schemes (javascript:, data:, vbscript:) in
     * operator- or administrator-supplied URLs such as app.client_url, which is
     * editable from the admin config form and rendered on the public docs page.
     *
     * A value carrying no scheme at all -- 'www.example.com/client',
     * '//cdn.example.com/client', '/client' -- cannot invoke script and is
     * passed through, since those are all legitimate ways to configure
     * app.client_url. A scheme-like prefix must be http or https; that means an
     * unschemed 'host:port' form is rejected, which is ambiguous by RFC anyway.
     *
     * The scheme is detected on a copy stripped of whitespace and control
     * characters, because browsers strip those before acting on an href and
     * would otherwise run 'java\nscript:...'.
     *
     * @param  string|null  $url
     * @return string|null
     */
    public static function safeHref($url) 
    {
        if(empty($url) || !is_string($url)) {
            return NULL;
        }

        $probe = preg_replace('/[\x00-\x20\x7F]/', '', $url);

        if(!preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $probe, $match)) {
            return $url; // relative or protocol-relative
        }

        return in_array(strtolower($match[1]), ['http', 'https'], TRUE) ? $url : NULL;
    }

    /* 
     * Check to see if premium code is present and enabled
     */
    public static function isPremium() 
    {
        if(config('app.premium_disabled')) {
            return FALSE;
        }

        return static::premiumCodePresent();
    }

    /* 
     * Check to see if premium code is present and enabled
     */
    public static function premiumCodePresent() 
    {

        // List of classes with known premium versions
        $classes = [
            'Engine',
        ];

        foreach($classes as $basename) {
            $class_name = 'App\Premium\\' . $basename;

            if(!class_exists($class_name)) {
                return FALSE;
            }
        }

        return TRUE;
    }

    public static function make($class_name) 
    {
        $new_class_name = static::find($class_name);
        return new $new_class_name();
    }

    public static function find($class_name) 
    {
        $new_class_name = static::transformClassName($class_name);

        if(class_exists($new_class_name)) {
            return $new_class_name;
        }
        else if(class_exists($class_name)) {
            return $class_name;
        }

        return FALSE;
    }

    public static function transformClassName($class_name) 
    {
        $imp = \App\InstallManager::getImportableDir()[2];
        $class_name_imp = str_replace("App\\", "App\\" . $imp . "\\", $class_name);
        
        if(class_exists($class_name_imp)) {
            return $class_name_imp;
        }

        return config('app.premium') ? str_replace("App\\", "App\Premium\\", $class_name) : $class_name;
    }

    public static function ordinal($number) 
    {
        $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
        
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return $number. 'th';
        }
        else {
            return $number. $ends[$number % 10];
        }
    }

    public static function isCommonWord($word, $lang) 
    {
        $common_en = ['a', 'and', 'the', 'or', 'but'];

        if($lang == 'en' && in_array($word, $common_en)) {
            return TRUE;
        }

        return FALSE;
    }

    public static function maxUploadSize($format = TRUE) 
    {
        $max = \Illuminate\Http\UploadedFile::getMaxFilesize();
        $max_fmt = NULL;

        if(!$format) {
            return $max;
        }

        $map = [
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'k' => 1024
        ];

        foreach($map as $k => $v) {
            if($max >= $v) {
                $max_fmt = $max / $v;
                $max_fmt .=  $k;
                break;
            }
        }

        $max_fmt = $max_fmt ?: $max;
        return ($format === 'both') ? ['raw' => $max, 'fmt' => $max_fmt] : $max_fmt;
    }

    public static function sizeStringToInt($size_str)
    {
        $size_int = (int)$size_str;

        if(is_string($size_str)) {
            $char = substr($size_str, -1);

            $size_int = match($char) {
                'G' => $size_int * 1024 ** 3,
                'M' => $size_int * 1024 ** 2,
                'k' => $size_int * 1024,
                default => $size_int,
            };
        }

        return $size_int;
    }

    public static function compareSize($size1, $size2) 
    {
        return static::sizeStringToInt($size1) <=> static::sizeStringToInt($size2);
    }

    public static function isAuthorized($access_level) 
    {

    }

    public static function trimRequest($request)
    {
        $request = trim($request);
        $request = trim($request, ';,');
        $request = trim($request);
        return $request;
    }

    /**
     * Convert HTML-rich database text entry fields to plain text.
     *
     * Rules:
     * - Keep copyright entities as the copyright symbol.
     * - Convert line-break style tags to new lines.
     * - Convert anchor tags to "link text (url)".
     */
    public static function stripHtmlFromTextEntry(?string $text): string
    {
        if($text === null || $text === '') {
            return '';
        }

        // Keep link URLs after link text before stripping tags.
        $text = preg_replace_callback('/<a\b[^>]*href\s*=\s*("|\')(.*?)\1[^>]*>(.*?)<\/a>/is', function($match) {
            $url = trim(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $label = trim(strip_tags(html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8')));

            if($label === '') {
                return $url;
            }

            if($url === '') {
                return $label;
            }

            return $label . ' (' . $url . ')';
        }, $text);

        // Normalize known copyright encodings.
        $text = str_ireplace(['@copy;', '&copy;', '&#169;', '&#xA9;'], '©', $text);

        // Decode entities so encoded tags like &lt;br&gt; also become new lines.
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Convert common block/line break tags to new lines.
        $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text);
        $text = preg_replace('/<\s*\/\s*br\s*>/i', "\n", $text);
        $text = preg_replace('/<\s*\/\s*(p|div|li)\s*>/i', "\n", $text);

        $text = strip_tags($text);
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace("/\r\n|\r/", "\n", $text);
        $text = preg_replace('/[ \t]+\n/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Builds Laravel query from the admin grid's search data
     *
     * The wire format (searchOper / searchField / searchString, or a filters JSON of
     * groupOp + rules) is inherited from jqGrid. The library itself was removed in BSS-286;
     * only the request shape the grid endpoints still accept survives here.
     *
     * @param $data response data
     * @param $Query Laravel query builder
     */
    public static function buildGridSearchQuery(&$data, \Illuminate\Database\Eloquent\Builder &$Query, $field_map = []) 
    {
        $val = $data['searchString'];
        $op  = NULL;
        $data['_post_filters'] = [];

        if(array_key_exists('filters', $data) && $data['filters']) {
            return self::buildGridSearchMuiltiQuery($data, $Query, $field_map);
        }

        list($op, $val, $special) = self::_mapSearchOperator($data['searchOper'], $data['searchString']);

        if($op && $data['searchString'] != '_no_rest_') {
            $field = (array_key_exists($data['searchField'], $field_map) && $field_map[$data['searchField']]) ? $field_map[$data['searchField']] : $data['searchField']; 

            if($field == 'POSTFILTER') {
                $data['_post_filters'][ $data['searchField'] ] = $val;
            }
            else {
                $Query->where($field, $op, $val);
            }
        }
    }

    public static function buildGridSearchMuiltiQuery(&$data, \Illuminate\Database\Eloquent\Builder &$Query, $field_map = []) 
    {
        if(!array_key_exists('filters', $data) || !$data['filters']) {
            return;
        }

        $filters = json_decode($data['filters']);
        $mapped  = [];
        $data['_post_filters'] = [];

        foreach($filters->rules as $rule) {
            list($op, $val, $special) = self::_mapSearchOperator($rule->op, $rule->data);

            if($op && $rule->data != '_no_rest_') {            
                $field = (array_key_exists($rule->field, $field_map) && $field_map[$rule->field]) ? $field_map[$rule->field] : $rule->field; 

                if($field == 'POSTFILTER') {
                    $data['_post_filters'][ $rule->field ] = $val;
                }
                else {                
                    $mapped[] = [
                        'field' => $field,
                        'op'    => $op,
                        'val'   => $val,
                        'sp'    => $special,
                    ];
                }
            }
        }

        if($filters->groupOp == 'AND') {
            $Query->where(function($q) use ($mapped) {
                foreach($mapped as $m) {
                    $q->where($m['field'], $m['op'], $m['val']);
                }
            });
        }        

        // Known issue: When using a postfilter, the postfilter will always be treated as AND
        if($filters->groupOp == 'OR') {
            $Query->where(function($q) use ($mapped) {
                foreach($mapped as $key => $m) {
                    if($key == 0) {
                        $q->where($m['field'], $m['op'], $m['val']);
                    }
                    else {
                        $q->orWhere($m['field'], $m['op'], $m['val']);
                    }
                }
            });
        }
    }

    protected static function _mapSearchOperator($search_op, $val) 
    {
        $op = NULL;
        $special = NULL;

        switch($search_op) {
            case 'eq':
                $op = '=';
                break;             
            case 'ne':
                $op = '!=';
                break;           
            case 'lt':
                $op = '<';
                break;             
            case 'le':
                $op = '<=';
                break;
            case 'gt':
                $op = '>';
                break;             
            case 'ge':
                $op = '>=';
                break; 
            case 'bw':
                $op = 'LIKE';
                $val .= '%';
                break;             
            case 'bn':
                $op = 'NOT LIKE';
                $val .= '%';
                break;            
            case 'ew':
                $op = 'LIKE';
                $val = '%' . $val;
                break;             
            case 'en':
                $op = 'NOT LIKE';
                $val = '%' . $val;
                break;             
            case 'cn':
                $op = 'LIKE';
                $val = '%' . $val . '%';
                break;             
            case 'nc':
                $op = 'NOT LIKE';
                $val = '%' . $val . '%';
                break; 
        }

        return [$op, $val, $special];
    }

    /**
     * Maximum number of bound variables one prepared statement may carry on a connection.
     *
     * SQLite's ceiling is SQLITE_MAX_VARIABLE_NUMBER, which is compile-time configurable and
     * genuinely varies between builds - Debian and Ubuntu ship 250000, well above the 32766
     * default. The build reports its own value through PRAGMA compile_options whenever it was
     * set explicitly, so that is preferred; only a build that leaves the option at its default
     * (and so does not list it) falls back to the version-derived default. Every other
     * supported driver is far more generous - MySQL caps a statement at 65535 placeholders
     * regardless of version.
     *
     * @param string|null $connection Connection name, NULL for the default connection
     * @return int
     */
    public static function getMaxBoundVariables(?string $connection = NULL): int
    {
        // The ceiling cannot change within a process: it is a compile-time constant of the
        // loaded SQLite build, and a fixed number for every other driver. Probing it costs a
        // PRAGMA and a PDO attribute read, and the importers ask once per buffer flush - about
        // 155 extra round trips on a 31k-verse import.
        $key = $connection ?? '';

        if(!array_key_exists($key, static::$max_bound_variables)) {
            static::$max_bound_variables[$key] = static::_probeMaxBoundVariables($connection);
        }

        return static::$max_bound_variables[$key];
    }

    /**
     * Discards the memoized ceilings, so a reconfigured connection is probed afresh.
     */
    public static function clearMaxBoundVariablesCache(): void
    {
        static::$max_bound_variables = [];
    }

    /** The action an API request names when its path does not name one. */
    public const DEFAULT_API_ACTION = 'query';

    /**
     * Matches a version segment in an API path - 'v2', 'v3', 'v10'.
     *
     * The digits are what make it a version. '/api/version' is the 'version' action on the
     * unversioned route, not version 'ersion' of the API, and the versioned route constrains
     * its {version} parameter to digits for the same reason.
     */
    private const API_VERSION_SEGMENT_PATTERN = '/^v\d+$/';

    /**
     * The API action a request path names, or NULL when the path is not an API request.
     *
     * Both route shapes resolve here - '/api/{action?}' and '/api/v{version}/{action?}' - so
     * the version segment is skipped when one is present and the action that follows is the
     * answer either way. Neither form has to name an action: both default to 'query', as the
     * routes themselves do.
     *
     * Shared deliberately. ApiAccess decides from this whether a request is rate limited and
     * whether the caller's access level permits it, and SetCacheHeaders decides from it how
     * long the response may be cached. Two parsers meant two answers: the cache middleware
     * recognized only the literal 'v2', so every '/api/v3/...' request resolved to the action
     * 'v3', matched nothing in bss.cache_headers.actions, and went out uncached.
     *
     * @param string|null $path Request path, as Request::path() returns it - no leading slash
     * @return string|null The action, or NULL when $path is not under '/api'
     */
    public static function resolveApiAction(?string $path): ?string
    {
        $segments = explode('/', trim((string) $path, '/'));

        if(array_shift($segments) !== 'api') {
            return NULL;
        }

        if(isset($segments[0]) && preg_match(self::API_VERSION_SEGMENT_PATTERN, $segments[0])) {
            array_shift($segments);
        }

        $action = $segments[0] ?? '';

        return ($action === '') ? self::DEFAULT_API_ACTION : $action;
    }

    /**
     * The API version a request path names, or NULL when it names none.
     *
     * The companion to resolveApiAction(): that one skips the version segment to reach the
     * action, this one returns the segment it skipped. Only the versioned route can name a
     * version, so '/api/{action?}' always answers NULL and the caller reads that as "the
     * legacy route", not as "an unknown version".
     *
     * ApiAccess decides from this whether a request is billable. A version outside
     * app.api_version_list never reaches an engine - ApiController::versionedAction()
     * answers 404 or 410 - so charging a daily hit for it would let a client hardcoded to a
     * retired prefix burn its whole allowance on errors. The route constrains {version} to
     * digits, so a returned value is always 'v' followed by digits.
     *
     * @param string|null $path Request path, as Request::path() returns it - no leading slash
     * @return string|null The version segment, e.g. 'v3', or NULL when $path names no version
     */
    public static function resolveApiVersion(?string $path): ?string
    {
        $segments = explode('/', trim((string) $path, '/'));

        if(array_shift($segments) !== 'api') {
            return NULL;
        }

        if(isset($segments[0]) && preg_match(self::API_VERSION_SEGMENT_PATTERN, $segments[0])) {
            return $segments[0];
        }

        return NULL;
    }

    /**
     * The element names accepted as a highlight tag.
     *
     * Highlighting runs after sanitizeHtml(), so whatever this returns is emitted into the
     * response untouched - an unrestricted tag name would let a caller inject '<script>' or
     * '<iframe>' through the highlight_tag parameter.
     *
     * Every element the caller could sensibly highlight a run of verse text with is here:
     * the HTML inline text semantics, the edit elements, and the presentational names HTML 4
     * clients have been sending this endpoint for years ('big', 'strike', 'tt'). The tag is
     * emitted with no attributes, so anything whose meaning lives in an attribute is nothing
     * but a wrapper here, and the list is bounded by what cannot be abused rather than by
     * what HTMLPurifier knows - verse text never reaches the purifier.
     *
     * Deliberately absent, and why:
     * - void elements ('br', 'img', 'hr'), which have no closing tag to wrap a match in;
     * - block elements ('div', 'p', 'h1', 'li'), which break the verse out of its own line;
     * - elements that switch the parser to raw text ('script', 'style', 'title', 'textarea',
     *   'xmp', 'noscript', 'template'), which would swallow the rest of the verse;
     * - embedded content ('iframe', 'object', 'embed', 'svg', 'math') and form controls;
     * - 'a', 'font' and 'ruby', which do nothing without the attributes or the child elements
     *   this never emits.
     */
    public const HIGHLIGHT_TAG_WHITELIST = [
        'abbr', 'b', 'bdi', 'bdo', 'big', 'cite', 'code', 'data', 'del', 'dfn', 'em', 'i',
        'ins', 'kbd', 'mark', 'q', 's', 'samp', 'small', 'span', 'strike', 'strong', 'sub',
        'sup', 'time', 'tt', 'u', 'var',
    ];

    /** The element a rejected tag name falls back to; mirrors config('bss.defaults.highlight_tag'). */
    public const DEFAULT_HIGHLIGHT_TAG = 'b';

    /**
     * The plain-text marker a Markdown response falls back to; mirrors
     * config('bss.defaults.highlight_tag_v3').
     *
     * On HIGHLIGHT_PLAIN_TEXT_MARKERS, so EngineV3 can lean on it when the config is missing
     * rather than letting an element name reach a Markdown response.
     */
    public const DEFAULT_HIGHLIGHT_MARKER = '**';

    /**
     * Matches anything the caller could have meant as an element name - 'b', 'em', 'my-tag'.
     *
     * Deliberately wider than the HTML spec: a name is checked against the whitelist and, off
     * it, answered with DEFAULT_HIGHLIGHT_TAG, so a spelling that is not quite legal ('1b')
     * still ends up highlighted rather than treated as a marker. The hyphen is what matters
     * most - 'my-tag' is a legal custom element, and taking it for a plain-text marker
     * emitted it on both sides of the match and ran it into the surrounding words
     * ('my-tagshepherdmy-tag').
     */
    private const HIGHLIGHT_ELEMENT_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9-]*$/';

    /**
     * The plain-text markers a caller may highlight with.
     *
     * An allowlist rather than a character filter. The marker is emitted after sanitizeHtml()
     * and is never escaped, so excluding the characters that open a tag is not enough on its
     * own: '![x](javascript:alert(1))' contains none of them and is still an executable image
     * once a client renders the v3 Markdown. Only a marker on this list is echoed.
     *
     * Two delimiters are the highlighter's own and must never appear in one:
     * SqlSearch::highlightResults() marks matches internally with '&&' and '%' before
     * str_replace()ing them for the pair this resolves to, so a caller-supplied marker
     * containing either is indistinguishable from the highlighter's own bookkeeping.
     *
     * Symmetrical markers only - the same string opens and closes a match.
     */
    public const HIGHLIGHT_PLAIN_TEXT_MARKERS = ['*', '**', '***', '_', '__', '~', '~~', '`', '``', '=='];

    /**
     * Whether a highlight tag is a plain-text marker - '**', '__', '`' - rather than an HTML
     * element name, and is safe to emit verbatim on both sides of a match.
     *
     * EngineV3 asks this to decide whether the caller gave it something usable in a Markdown
     * response, so the two stay on the same definition of what a marker is.
     *
     * @param string|null $highlight_tag
     * @return bool
     */
    public static function isPlainTextHighlightMarker($highlight_tag): bool
    {
        return in_array((string) $highlight_tag, self::HIGHLIGHT_PLAIN_TEXT_MARKERS, TRUE);
    }

    /**
     * The element names buildHighlightTags() will emit: HIGHLIGHT_TAG_WHITELIST, plus the
     * install's own configured default.
     *
     * The whitelist guards a caller-supplied tag, which reaches verse text unescaped. A
     * configured default is not caller-supplied - an operator who styles '<high>' in their own
     * front end is not attacking themselves - and answering it with DEFAULT_HIGHLIGHT_TAG left
     * that install with no highlighting at all, no error and no log line. It still has to be a
     * bare element name, or there is nothing to wrap a match in.
     *
     * Passed in rather than read from config(), so buildHighlightTags() stays pure.
     *
     * @param string|null $configured_default
     * @return string[]
     */
    public static function highlightElementWhitelist($configured_default = NULL): array
    {
        $whitelist = self::HIGHLIGHT_TAG_WHITELIST;
        $default   = self::highlightElementDefault($configured_default);

        if($default !== NULL && !in_array($default, $whitelist, TRUE)) {
            $whitelist[] = $default;
        }

        return $whitelist;
    }

    /**
     * The configured default reduced to a bare, lower-cased element name, or NULL when it is
     * not one - an unset value, a plain-text marker, a fragment of markup.
     *
     * @param string|null $configured_default
     * @return string|null
     */
    private static function highlightElementDefault($configured_default): ?string
    {
        if(!is_string($configured_default) || !preg_match(self::HIGHLIGHT_ELEMENT_PATTERN, $configured_default)) {
            return NULL;
        }

        return strtolower($configured_default);
    }

    /**
     * Resolves a highlight tag into the pair of markers that wrap a highlighted match.
     *
     * An element name ('b', 'em', 'span') is wrapped into an opening and a closing tag; a name
     * outside highlightElementWhitelist() falls back to the configured default, or to
     * DEFAULT_HIGHLIGHT_TAG when there is no usable one. A marker on
     * HIGHLIGHT_PLAIN_TEXT_MARKERS ('**', '__') is symmetrical and is used verbatim on both
     * sides. Anything that is neither - an angle-bracketed tag, an entity, a fragment of
     * Markdown, a fragment of markup - falls back to the default element rather than being
     * echoed into the response.
     *
     * $configured_default is the install's own setting, not the caller's request, and is
     * accepted off the whitelist - see highlightElementWhitelist(). The marker branch returns
     * before it is consulted, so it has no bearing on API v3, which highlights with markers
     * alone.
     *
     * @param string $highlight_tag
     * @param string|null $configured_default config('bss.defaults.highlight_tag')
     * @return array{0: string, 1: string} The opening and closing markers
     */
    public static function buildHighlightTags($highlight_tag, $configured_default = NULL): array
    {
        $tag = (string) $highlight_tag;

        if(self::isPlainTextHighlightMarker($tag)) {
            return [$tag, $tag];
        }

        $whitelist = self::highlightElementWhitelist($configured_default);

        if(!preg_match(self::HIGHLIGHT_ELEMENT_PATTERN, $tag) || !in_array(strtolower($tag), $whitelist, TRUE)) {
            $tag = self::highlightElementDefault($configured_default) ?? self::DEFAULT_HIGHLIGHT_TAG;
        }

        return ['<' . $tag . '>', '</' . $tag . '>'];
    }

    /**
     * The elements and attributes sanitizeHtml() lets through.
     *
     * 'target' is here for the generated copyright statement - Copyright::getProcessed-
     * CopyrightStatement() opens the licence in a new tab - and it only survives because
     * getHtmlPurifier() names Attr.AllowedFrameTargets. The purifier writes the matching
     * rel="noreferrer noopener" itself, so the tab it opens cannot reach back through
     * window.opener.
     */
    public const SANITIZE_HTML_ALLOWED = 'div,p,b,i,u,a[href|target],ul,ol,li,br,strong,em,sub,sup,small,'
        . 'h1,h2,h3,h4,h5,h6,span[style],table,tr,td,th,tbody,thead,tfoot';

    /**
     * The elements and attributes sanitizeEditorHtml() lets through.
     *
     * Wider than SANITIZE_HTML_ALLOWED because it guards what an administrator typed into a
     * WYSIWYG editor rather than what the API emits. The CKEditor build in
     * admin/postconfig.blade.php ships the image, horizontal-line, strikethrough, code,
     * block-quote and font plugins, and the editor reads the column back through the same
     * accessor that sanitizes it - so anything missing here is stripped when the page loads
     * and then saved over the original, with nothing to restore it from.
     *
     * 'class' comes with the elements CKEditor styles through it; a class name cannot
     * execute anything.
     *
     * Three of the editor's elements are deliberately absent: HTMLPurifier defines neither
     * 'figure', 'figcaption' nor 'mark', so naming them raises "Element 'x' is not supported"
     * on every definition build and strips them regardless. Dropping <figure> is not the same
     * as dropping the picture - the <img> inside it survives on its own, and the caption
     * survives as text.
     */
    public const SANITIZE_EDITOR_HTML_ALLOWED = 'div[class],p[class|style],b,i,u,a[href|target|rel],'
        . 'ul,ol,li,br,strong,em,sub,sup,small,'
        . 'h1,h2,h3,h4,h5,h6,span[style|class],table[class],tr,td,th,tbody,thead,tfoot,'
        . 'img[src|alt|title|width|height|class],hr,s,strike,del,ins,code,pre,blockquote';

    /** 
     * Strict version of SANITIZE_EDITOR_HTML_ALLOWED.
     * Used for sanitizing WYSIWYG editor content that will be displayed in a public-facing context, i.e., 
     * shipped via the public API, where we want to limit the allowed tags and attributes to a safe subset.
     * This is more restrictive than SANITIZE_EDITOR_HTML_ALLOWED, removing potentially unsafe tags and attributes.
     *
     * 'img' is the element this exists to drop - a description imported with a module carries
     * the publisher's badge, and echoing it makes every consumer of the listing fetch a third
     * party. That is also why 'p' keeps 'class' but loses 'style': the editor writes its
     * alignment and indent through style, and a style attribute can name a remote URL too.
     * The CSS route is closed a second time in getHtmlPurifier(): every sanitizer but
     * sanitizeEditorHtml() runs with external resources disabled outright.
     *
     * 'code' is on the list although 'img' is not: CKEditor writes a code block as
     * '<pre><code>', it takes no attributes, and it can load nothing.
     */

    public const SANITIZE_EDITOR_HTML_ALLOWED_STRICT = 'div[class],p[class],b,i,u,a[href|target],ul,ol,li,br,strong,em,sub,sup,small,'
        . 'h1,h2,h3,h4,h5,h6,span[style|class],table[class],tr,td,th,tbody,thead,tfoot,'
        . 'hr,s,strike,del,ins,code,pre,blockquote';

    /**
     * The frame targets an anchor may name.
     *
     * HTMLPurifier drops 'target' outright unless this is set, whatever the allowlist says,
     * so a link written with one used to arrive without it. '_NEW' is deliberately absent -
     * it is not a frame target the HTML spec defines and the purifier refuses it even when it
     * is named here; the links that meant it say '_blank'.
     */
    private const ALLOWED_FRAME_TARGETS = ['_blank', '_self', '_parent', '_top'];

    /** @var \HTMLPurifier[] One per configuration - see getHtmlPurifier(). */
    private static $Purifiers = [];

    /** @var \League\HTMLToMarkdown\HtmlConverter|NULL Built once per process - see getHtmlConverter(). */
    private static $Converter = NULL;

    /**
     * Sanitizes HTML content to allow only a safe subset of tags.
     *
     * NULL is accepted because most of the columns this guards are nullable - a Bible with no
     * description, a Strong's definition with no 'tvm' - and an absent field must not fatal
     * the request. An absent value answers NULL, so a column that held nothing is still
     * reported as nothing rather than as an empty string.
     *
     * @param string|null $html The HTML content to sanitize
     * @return string|null The sanitized HTML content, NULL if there was none
     */
    public static function sanitizeHtml(?string $html): ?string
    {
        return static::_purify($html, self::SANITIZE_HTML_ALLOWED);
    }

    /**
     * Sanitizes HTML that an administrator wrote in a WYSIWYG editor.
     *
     * Same guarantee as sanitizeHtml() - the purifier still refuses scripts, event handlers
     * and 'javascript:' - against the wider SANITIZE_EDITOR_HTML_ALLOWED, so an image or a
     * horizontal rule the editor inserted survives being read back into the editor. Use this
     * for a column an administrator edits; use sanitizeHtml() for what the API emits.
     *
     * @param string|null $html The HTML content to sanitize
     * @return string|null The sanitized HTML content, NULL if there was none
     */
    public static function sanitizeEditorHtml(?string $html): ?string
    {
        return static::_purify($html, self::SANITIZE_EDITOR_HTML_ALLOWED, TRUE);
    }

    /**
     * Sanitizes HTML that an administrator wrote in a WYSIWYG editor, using a strict allowlist.
     *
     * Same guarantee as sanitizeEditorHtml() but with a more restrictive set of allowed tags and attributes.
     *
     * @param string|null $html The HTML content to sanitize
     * @return string|null The sanitized HTML content, NULL if there was none
     */
    public static function sanitizeEditorHtmlStrict(?string $html): ?string
    {
        return static::_purify($html, self::SANITIZE_EDITOR_HTML_ALLOWED_STRICT);
    }

    /**
     * Runs one allowlist over one value.
     *
     * An absent value answers NULL rather than '', so the columns this guards have one shape
     * for "nothing" instead of two - a cleared description used to persist as '' and be
     * reported as "" while an untouched one was reported as null.
     *
     * Absent means NULL or the empty string. '0' is content, and a value that survives to the
     * purifier and is emptied by it answers '' - it held something, all of which was refused.
     *
     * @param string|null $html
     * @param string $allowed An HTML.Allowed specification
     * @param bool $allow_external_resources Whether $allowed is a list that may load one
     * @return string|null
     */
    private static function _purify(?string $html, string $allowed, bool $allow_external_resources = FALSE): ?string
    {
        if($html === NULL || $html === '') {
            return NULL;
        }

        $Purifier = static::getHtmlPurifier($allowed, $allow_external_resources);

        return trim($Purifier->purify(static::flattenHtmlDocument($html)));
    }

    /**
     * Reduces an HTML document to a fragment.
     *
     * HTMLPurifier discards anything that follows </html>, and several imported modules store
     * a whole document with the import credit appended after it - see Importers\MyBible, which
     * builds $description . '<br /><br />' . $source. Stripping the scaffolding first leaves
     * one flat fragment, so the credit survives the purifier instead of being deleted. 16 of
     * the Bibles installed here were losing text this way, and because sanitizeHtml() is the
     * mutator as well as the accessor, a re-import was writing the truncation to the database.
     *
     * A no-op for the ordinary case: a description that is already a fragment is unchanged.
     *
     * Each replacement falls back to its own input. preg_replace() answers NULL rather than a
     * string when PCRE gives up - the lazy '.*?' in the <head> pattern exhausts the default
     * backtrack limit on a description of about a megabyte - and unhandled that NULL loses the
     * value two different ways. An earlier replacement failing hands NULL to the next one as
     * its subject, which coerces to '', so the description is silently emptied; the last one
     * failing returns NULL through the ': string' declaration, a TypeError, which is a 500 on
     * the Bible listing and a fatal in the import that writes this column. An unflattened
     * fragment is better than either, and costs nothing - the purifier drops the scaffolding
     * tags itself, it only discards what follows </html>.
     *
     * '??' and not '?:' because an empty string is a real result here - a document with
     * nothing outside its scaffolding flattens to one.
     *
     * @param string $html
     * @return string
     */
    private static function flattenHtmlDocument(string $html): string
    {
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('#<head\b[^>]*>.*?</head>#is', '', $html) ?? $html;
        $html = preg_replace('#</?(?:html|body)\b[^>]*>#i', '', $html) ?? $html;

        return $html;
    }

    /**
     * The shared HTMLPurifier.
     *
     * The configuration is identical on every call and building it is the expensive part -
     * roughly 4ms against this whitelist - and the callers are per-row, not per-request:
     * Engine::actionBibles() sanitizes a copyright statement and a description for every
     * installed Bible, and the StrongsDefinition accessors sanitize the entry and the root
     * word of every definition a request returns. An install with a few hundred Bibles was
     * rebuilding the purifier a few hundred times for one /api/bibles. Held here instead,
     * that becomes one build per process.
     *
     * The serializer cache is off deliberately. It writes into vendor/, which fails outright
     * on a deployment where vendor/ is read-only and warns on every call when the web user
     * owns the cache directory and the CLI user does not. With the purifier itself held here
     * it saves nothing measurable.
     *
     * There is one of these per configuration, not one per process - the editor allowlist is a
     * second configuration and needs a purifier of its own - and the pages that use the
     * editor one never touch the API one, so neither is built for nothing.
     *
     * URI.DisableExternalResources is on unless the caller asks for it off. Dropping 'img'
     * from an allowlist is not enough on its own: HTMLPurifier filters CSS declarations but
     * permits the URI-valued ones, so 'background-image:url(...)' and 'list-style-image:
     * url(...)' survived on a span[style] and made the consumer fetch a third party anyway.
     * Only sanitizeEditorHtml() turns it off, because the admin editor genuinely inserts
     * images and has to read them back.
     *
     * @param string $allowed An HTML.Allowed specification
     * @param bool $allow_external_resources Whether $allowed is a list that may load one
     * @return \HTMLPurifier
     */
    private static function getHtmlPurifier(string $allowed = self::SANITIZE_HTML_ALLOWED, bool $allow_external_resources = FALSE): \HTMLPurifier
    {
        $key = $allowed . ($allow_external_resources ? '|external' : '');

        if(!array_key_exists($key, static::$Purifiers)) {
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', $allowed);
            $config->set('Cache.DefinitionImpl', NULL);
            $config->set('Attr.AllowedFrameTargets', self::ALLOWED_FRAME_TARGETS);

            if(!$allow_external_resources) {
                $config->set('URI.DisableExternalResources', TRUE);
            }

            static::$Purifiers[$key] = new \HTMLPurifier($config);
        }

        return static::$Purifiers[$key];
    }
    
    /**
     * convertHtmlToMarkdown() - Converts HTML to Markdown using the league/html-to-markdown library
     *
     * $sanitize is turned off by callers that have already sanitized. Engine::_processHtml()
     * and its subclass hooks are documented as receiving values the purifier has already been
     * over - either through Engine::_sanitizeHtml() or from a model accessor - and purifying a
     * second time costs about 0.4ms per call for nothing. It stays on by default so any other
     * caller is still safe.
     *
     * @param string|null $html The HTML content to convert
     * @param bool $sanitize Whether to sanitize $html before converting it
     * @return string The converted Markdown content
     */
    public static function convertHtmlToMarkdown(?string $html, bool $sanitize = TRUE): string
    {
        if($sanitize) {
            $html = self::sanitizeHtml($html);
        }

        if($html === NULL || $html === '') {
            return '';
        }

        return static::getHtmlConverter()->convert($html);
    }

    /**
     * The shared HTML to Markdown converter.
     *
     * Held for the same reason as the purifier, and reached from the same places:
     * EngineV3::_processHtml() converts every value the v3 API emits as Markdown - a
     * copyright statement and a description per Bible, an entry and a root word per Strong's
     * definition - so a single request was building one of these per row.
     *
     * @return \League\HTMLToMarkdown\HtmlConverter
     */
    private static function getHtmlConverter(): \League\HTMLToMarkdown\HtmlConverter
    {
        if(static::$Converter === NULL) {
            static::$Converter = new \League\HTMLToMarkdown\HtmlConverter([
                'strip_tags' => TRUE,
                'hard_break' => TRUE,
            ]);
        }

        return static::$Converter;
    }

    /**
     * Asks the connection itself what its ceiling is. See getMaxBoundVariables().
     *
     * @param string|null $connection Connection name, NULL for the default connection
     * @return int
     */
    protected static function _probeMaxBoundVariables(?string $connection = NULL): int
    {
        $Connection = \DB::connection($connection);

        if($Connection->getDriverName() !== 'sqlite') {
            return 65535;
        }

        foreach(static::getSqliteCompileOptions($connection) as $option) {
            if(preg_match('/^MAX_VARIABLE_NUMBER=([0-9]+)$/', $option, $match) && (int) $match[1] > 0) {
                return (int) $match[1];
            }
        }

        // Documented default for the engine version: 3.32.0 raised it from 999 to 32766.
        $version = (string) $Connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);

        return version_compare($version, '3.32.0', '>=') ? 32766 : 999;
    }

    /**
     * The compile options a SQLite build reports, as raw 'NAME' / 'NAME=value' strings.
     * Returns an empty array for a connection that cannot answer the pragma.
     *
     * @param string|null $connection Connection name, NULL for the default connection
     * @return string[]
     */
    public static function getSqliteCompileOptions(?string $connection = NULL): array
    {
        try {
            $rows = \DB::connection($connection)->select('PRAGMA compile_options');
        }
        catch(\Throwable $e) {
            return [];
        }

        $options = [];

        foreach($rows as $row) {
            $values = (array) $row;
            $options[] = (string) reset($values);
        }

        return $options;
    }

    /**
     * Rows per batched INSERT that keep the statement inside the connection's bound-variable
     * ceiling. Modern SQLite and MySQL both clear $max_rows comfortably; a build with a low
     * ceiling gets a proportionally smaller batch rather than a "too many SQL variables"
     * failure.
     *
     * @param int $columns_per_row Number of columns bound for each row
     * @param string|null $connection Connection name, NULL for the default connection
     * @param int $max_rows Desired batch size, returned whenever the ceiling allows it
     * @return int
     * @throws \InvalidArgumentException When a single row cannot fit inside the ceiling, since
     *                                   no batch size - not even one row - could then succeed.
     */
    public static function getInsertChunkSize(int $columns_per_row, ?string $connection = NULL, int $max_rows = 1000): int
    {
        if($columns_per_row < 1) {
            throw new \InvalidArgumentException('Columns per row must be at least 1, got ' . $columns_per_row);
        }

        $max = static::getMaxBoundVariables($connection);

        if($columns_per_row > $max) {
            throw new \InvalidArgumentException(
                'A single row binds ' . $columns_per_row . ' variables, more than this connection permits in one '
                . 'statement (' . $max . '). Reduce the columns bound per statement.'
            );
        }

        return max(1, min($max_rows, (int) floor($max / $columns_per_row)));
    }

}
