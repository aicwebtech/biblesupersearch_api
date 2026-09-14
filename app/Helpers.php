<?php

namespace App;

class Helpers {

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

    /**
     * The element names accepted as a highlight tag.
     *
     * Highlighting runs after sanitizeHtml(), so whatever this returns is emitted into the
     * response untouched - an unrestricted tag name would let a caller inject '<script>' or
     * '<iframe>' through the highlight_tag parameter. Inline formatting elements only.
     */
    public const HIGHLIGHT_TAG_WHITELIST = ['b', 'i', 'em', 'strong', 'u', 'span', 'small', 'sub', 'sup'];

    /** The element a rejected tag name falls back to; mirrors config('bss.defaults.highlight_tag'). */
    public const DEFAULT_HIGHLIGHT_TAG = 'b';

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
     * The characters a plain-text marker may not contain.
     *
     * The marker is emitted after sanitizeHtml() and is never escaped, so anything that could
     * open a tag or an entity has to be refused here rather than upstream. '&' is also what
     * SqlSearch::highlightResults() uses for its own internal alias, which a caller-supplied
     * '&&' would collide with.
     */
    private const HIGHLIGHT_MARKER_FORBIDDEN = '/[<>&"\']/';

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
        $tag = (string) $highlight_tag;

        return $tag !== ''
            && !preg_match(self::HIGHLIGHT_ELEMENT_PATTERN, $tag)
            && !preg_match(self::HIGHLIGHT_MARKER_FORBIDDEN, $tag);
    }

    /**
     * Resolves a highlight tag into the pair of markers that wrap a highlighted match.
     *
     * An element name ('b', 'em', 'span') is wrapped into an opening and a closing tag; a name
     * outside HIGHLIGHT_TAG_WHITELIST falls back to DEFAULT_HIGHLIGHT_TAG. A Markdown (or
     * other plain-text) marker such as '**' or '__' is symmetrical and is used verbatim on
     * both sides. Anything that is neither - an angle-bracketed tag, an entity, a fragment of
     * markup - falls back to the default element rather than being echoed into the response.
     *
     * @param string $highlight_tag
     * @return array{0: string, 1: string} The opening and closing markers
     */
    public static function buildHighlightTags($highlight_tag): array
    {
        $tag = (string) $highlight_tag;

        if(self::isPlainTextHighlightMarker($tag)) {
            return [$tag, $tag];
        }

        if(!preg_match(self::HIGHLIGHT_ELEMENT_PATTERN, $tag) || !in_array(strtolower($tag), self::HIGHLIGHT_TAG_WHITELIST, TRUE)) {
            $tag = self::DEFAULT_HIGHLIGHT_TAG;
        }

        return ['<' . $tag . '>', '</' . $tag . '>'];
    }

    /**
     * The elements and attributes sanitizeHtml() lets through.
     *
     */
    public const SANITIZE_HTML_ALLOWED = 'div,p,b,i,u,a[href],ul,ol,li,br,strong,em,sub,sup,small,'
        . 'h1,h2,h3,h4,h5,h6,span[style],table,tr,td,th,tbody,thead,tfoot';

    /** @var \HTMLPurifier|NULL Built once per process - see getHtmlPurifier(). */
    private static $Purifier = NULL;

    /** @var \League\HTMLToMarkdown\HtmlConverter|NULL Built once per process - see getHtmlConverter(). */
    private static $Converter = NULL;

    /** Stands in for a bare '&' across sanitization - a private-use codepoint, absent from Bible text. */
    private const BARE_AMPERSAND = "\u{E000}";

    /**
     * Sanitizes HTML content to allow only a safe subset of tags.
     *
     * NULL is accepted because most of the columns this guards are nullable - a Bible with no
     * description, a Strong's definition with no 'tvm' - and an absent field must not fatal
     * the request. An absent value answers the empty string, so callers get a string back
     * whatever the column held.
     *
     * @param string|null $html The HTML content to sanitize
     * @return string The sanitized HTML content
     */
    public static function sanitizeHtml(?string $html): string
    {
        if($html === NULL || $html === '') {
            return '';
        }

        return trim(static::getHtmlPurifier()->purify(static::flattenHtmlDocument($html)));
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
     * @param string $html
     * @return string
     */
    private static function flattenHtmlDocument(string $html): string
    {
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
        $html = preg_replace('#<head\b[^>]*>.*?</head>#is', '', $html);
        $html = preg_replace('#</?(?:html|body)\b[^>]*>#i', '', $html);

        return $html;
    }

    /**
     * Replaces bare ampersands with a sentinel, leaving existing entities alone.
     *
     * HTMLPurifier normalizes a bare '&' to '&amp;'. That is right for an HTML document and
     * wrong for Bible verse text, which the API emits as text and which the 'italics' field
     * indexes by character offset - the four-character expansion moves every offset past the
     * ampersand, so a client italicizes the wrong span. 4,480 Bishops and 3,535 Geneva verses
     * carry a bare '&'.
     *
     * A bare '&' cannot open a tag, so holding it out of the sanitizer costs nothing in
     * safety. Any sentinel already in the input is dropped first, so none can be smuggled in.
     *
     * @param string|null $text
     * @return string
     */
    public static function protectBareAmpersands(?string $text): string
    {
        $text = str_replace(self::BARE_AMPERSAND, '', (string) $text);

        return preg_replace('/&(?![A-Za-z#][A-Za-z0-9]*;)/', self::BARE_AMPERSAND, $text);
    }

    /**
     * Puts back what protectBareAmpersands() held out. Call it after the whole sanitize and
     * convert chain has run, not between its steps - the v3 engine converts to Markdown after
     * sanitizing, and the sentinel has to survive that too.
     *
     * @param string $text
     * @return string
     */
    public static function restoreBareAmpersands(string $text): string
    {
        return str_replace(self::BARE_AMPERSAND, '&', $text);
    }

    /**
     * The shared HTMLPurifier.
     *
     * The configuration is identical on every call and building it is the expensive part -
     * roughly 4ms against this whitelist, and Engine::_processMarkup() sanitizes once per
     * verse, so a 500-verse page_all request spent over two seconds rebuilding it. Held here
     * instead, that becomes one build per process.
     *
     * The serializer cache is off deliberately. It writes into vendor/, which fails outright
     * on a deployment where vendor/ is read-only and warns on every call when the web user
     * owns the cache directory and the CLI user does not. With the purifier itself held here
     * it saves nothing measurable.
     *
     * @return \HTMLPurifier
     */
    private static function getHtmlPurifier(): \HTMLPurifier
    {
        if(static::$Purifier === NULL) {
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', self::SANITIZE_HTML_ALLOWED);
            $config->set('Cache.DefinitionImpl', NULL);

            static::$Purifier = new \HTMLPurifier($config);
        }

        return static::$Purifier;
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
     * Held for the same reason as the purifier: Engine::_processMarkup() converts once per
     * verse, so a 500-verse page_all request was building 500 of these.
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
