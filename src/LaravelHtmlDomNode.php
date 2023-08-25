<?php

namespace Toxageek\LaravelHtmlDom;

class LaravelHtmlDomNode
{
    public int $nodetype = HDOM::TYPE_TEXT;

    public string $tag = 'text';

    public array $attr = [];

    /**
     * @var LaravelHtmlDomNode[]|null
     */
    public ?array $children = [];

    /**
     * @var LaravelHtmlDomNode[]|null
     */
    public ?array $nodes = [];

    public ?LaravelHtmlDomNode $parent = null;

    /**
     * @var array
     */
    public $_ = [];

    public int $tag_start = 0;

    private ?LaravelHtmlDom $dom = null;

    public function __construct(LaravelHtmlDom $dom)
    {
        $this->dom = $dom;
        $dom->nodes[] = $this;
    }

    public function __destruct()
    {
        $this->clear();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->outertext();
    }

    public function clear(): void
    {
        $this->dom = null;
        $this->nodes = null;
        $this->parent = null;
        $this->children = null;
    }

    public function parent(LaravelHtmlDomNode $parent = null): ?LaravelHtmlDomNode
    {
        // I am SURE that this doesn't work properly.
        // It fails to unset the current node from it's current parents nodes or
        // children list first.
        if ($parent !== null) {
            $this->parent = $parent;
            $this->parent->nodes[] = $this;
            $this->parent->children[] = $this;
        }

        return $this->parent;
    }

    public function has_child(): bool
    {
        return ! empty($this->children);
    }

    public function children(int $idx = -1): null|array|LaravelHtmlDomNode
    {
        if ($idx === -1) {
            return $this->children;
        }

        return $this->children[$idx] ?? null;
    }

    public function first_child(): ?LaravelHtmlDomNode
    {
        if (count($this->children) > 0) {
            return $this->children[0];
        }

        return null;
    }

    public function last_child(): ?LaravelHtmlDomNode
    {
        if (count($this->children) > 0) {
            return $this->children[count($this->children) - 1];
        }

        return null;
    }

    public function next_sibling(): ?LaravelHtmlDomNode
    {
        if ($this->parent === null) {
            return null;
        }

        $idx = array_search($this, $this->parent->children, true);

        if ($idx !== false && isset($this->parent->children[$idx + 1])) {
            return $this->parent->children[$idx + 1];
        }

        return null;
    }

    public function prev_sibling(): ?LaravelHtmlDomNode
    {
        if ($this->parent === null) {
            return null;
        }

        $idx = array_search($this, $this->parent->children, true);

        if ($idx !== false && $idx > 0) {
            return $this->parent->children[$idx - 1];
        }

        return null;
    }

    public function find_ancestor_tag($tag): ?LaravelHtmlDomNode
    {
        if ($this->parent === null) {
            return null;
        }

        $ancestor = $this->parent;

        while (! is_null($ancestor)) {
            if ($ancestor->tag === $tag) {
                break;
            }

            $ancestor = $ancestor->parent;
        }

        return $ancestor;
    }

    public function innertext(): string
    {
        if (isset($this->_[HDOM::INFO_INNER])) {
            return $this->_[HDOM::INFO_INNER];
        }

        if (isset($this->_[HDOM::INFO_TEXT])) {
            return $this->dom->restore_noise($this->_[HDOM::INFO_TEXT]);
        }

        $ret = '';

        foreach ($this->nodes as $n) {
            $ret .= $n->outertext();
        }

        return $ret;
    }

    public function outertext(): string
    {
        if ($this->tag === 'root') {
            return $this->innertext();
        }

        // todo: What is the use of this callback? Remove?
        if ($this->dom && $this->dom->callback !== null) {
            call_user_func($this->dom->callback, $this);
        }

        if (isset($this->_[HDOM::INFO_OUTER])) {
            return $this->_[HDOM::INFO_OUTER];
        }

        if (isset($this->_[HDOM::INFO_TEXT])) {
            return $this->dom->restore_noise($this->_[HDOM::INFO_TEXT]);
        }

        $ret = '';

        if ($this->dom && $this->dom->nodes[$this->_[HDOM::INFO_BEGIN]]) {
            $ret = $this->dom->nodes[$this->_[HDOM::INFO_BEGIN]]->makeup();
        }

        if (isset($this->_[HDOM::INFO_INNER])) {
            // todo: <br> should either never have HDOM::INFO_INNER or always
            if ($this->tag !== 'br') {
                $ret .= $this->_[HDOM::INFO_INNER];
            }
        } elseif ($this->nodes) {
            foreach ($this->nodes as $n) {
                $ret .= $this->convert_text($n->outertext());
            }
        }

        if (isset($this->_[HDOM::INFO_END]) && $this->_[HDOM::INFO_END] !== 0) {
            $ret .= '</'.$this->tag.'>';
        }

        return $ret;
    }

    /**
     * @return mixed|string
     */
    public function text(): mixed
    {
        if (isset($this->_[HDOM::INFO_INNER])) {
            return $this->_[HDOM::INFO_INNER];
        }

        if ($this->nodetype === HDOM::TYPE_TEXT) {
            return $this->dom->restore_noise($this->_[HDOM::INFO_TEXT]);
        }

        if ($this->nodetype === HDOM::TYPE_UNKNOWN || $this->nodetype === HDOM::TYPE_COMMENT) {
            return '';
        }

        if (strcasecmp($this->tag, 'script') === 0) {
            return '';
        }

        if (strcasecmp($this->tag, 'style') === 0) {
            return '';
        }

        $ret = '';

        // In rare cases, (always node type 1 or HDOM::TYPE_ELEMENT - observed
        // for some span tags, and some p tags) $this->nodes is set to NULL.
        // NOTE: This indicates that there is a problem where it's set to NULL
        // without a clear happening.
        // WHY is this happening?
        if (! is_null($this->nodes)) {
            foreach ($this->nodes as $n) {
                // Start paragraph after a blank line
                if ($n->tag === 'p') {
                    $ret = trim($ret)."\n\n";
                }

                $ret .= $this->convert_text($n->text());

                // If this node is a span... add a space at the end of it so
                // multiple spans don't run into each other.  This is plaintext
                // after all.
                if ($n->tag === 'span') {
                    $ret .= $this->dom->default_span_text;
                }
            }
        }

        return $ret;
    }

    public function xmltext(): array|string
    {
        $ret = $this->innertext();
        $ret = str_ireplace('<![CDATA[', '', $ret);

        return str_replace(']]>', '', $ret);
    }

    /**
     * @return mixed|string
     */
    public function makeup(): mixed
    {
        // text, comment, unknown
        if (isset($this->_[HDOM::INFO_TEXT])) {
            return $this->dom->restore_noise($this->_[HDOM::INFO_TEXT]);
        }

        $ret = '<'.$this->tag;
        $i = -1;

        foreach ($this->attr as $key => $val) {
            $i++;

            // skip removed attribute
            if ($val === null || $val === false) {
                continue;
            }

            $ret .= $this->_[HDOM::INFO_SPACE][$i][0];

            //no value attr: nowrap, checked selected...
            if ($val === true) {
                $ret .= $key;
            } else {
                $quote = match ($this->_[HDOM::INFO_QUOTE][$i]) {
                    HDOM::QUOTE_DOUBLE => '"',
                    HDOM::QUOTE_SINGLE => '\'',
                    default => '',
                };

                $ret .= $key
                    .$this->_[HDOM::INFO_SPACE][$i][1]
                    .'='
                    .$this->_[HDOM::INFO_SPACE][$i][2]
                    .$quote
                    .$val
                    .$quote;
            }
        }

        $ret = $this->dom->restore_noise($ret);

        return $ret.$this->_[HDOM::INFO_ENDSPACE].'>';
    }

    /**
     * @param  null  $idx
     */
    public function find($selector, $idx = null, bool $lowercase = false): array|LaravelHtmlDomNode|null
    {
        $selectors = $this->parse_selector($selector);

        if (($count = count($selectors)) === 0) {
            return [];
        }

        $found_keys = [];

        // find each selector
        for ($c = 0; $c < $count; $c++) {
            // The change on the below line was documented on the sourceforge
            // code tracker id 2788009
            // used to be: if (($levle=count($selectors[0]))===0) return array();
            if (($levle = count($selectors[$c])) === 0) {
                return [];
            }
            if (! isset($this->_[HDOM::INFO_BEGIN])) {
                return [];
            }

            $head = [$this->_[HDOM::INFO_BEGIN] => 1];
            $cmd = ' '; // Combinator

            // handle descendant selectors, no recursive!
            for ($l = 0; $l < $levle; $l++) {
                $ret = [];

                foreach ($head as $k => $v) {
                    $n = ($k === -1) ? $this->dom->root : $this->dom->nodes[$k];
                    //PaperG - Pass this optional parameter on to the seek function.
                    $n->seek($selectors[$c][$l], $ret, $cmd, $lowercase);
                }

                $head = $ret;
                $cmd = $selectors[$c][$l][4]; // Next Combinator
            }

            foreach ($head as $k => $v) {
                if (! isset($found_keys[$k])) {
                    $found_keys[$k] = 1;
                }
            }
        }

        // sort keys
        ksort($found_keys);

        $found = [];
        foreach ($found_keys as $k => $v) {
            $found[] = $this->dom->nodes[$k];
        }

        // return nth-element or array
        if (is_null($idx)) {
            return $found;
        }

        if ($idx < 0) {
            $idx = count($found) + $idx;
        }

        return $found[$idx] ?? null;
    }

    protected function seek($selector, &$ret, $parent_cmd, bool $lowercase = false): void
    {
        [$tag, $id, $class, $attributes, $cmb] = $selector;
        $nodes = [];

        if ($parent_cmd === ' ') { // Descendant Combinator
            // Find parent closing tag if the current element doesn't have a closing
            // tag (i.e. void element)
            $end = (! empty($this->_[HDOM::INFO_END])) ? $this->_[HDOM::INFO_END] : 0;
            if ($end === 0) {
                $parent = $this->parent;
                while (! isset($parent->_[HDOM::INFO_END]) && $parent !== null) {
                    $end--;
                    $parent = $parent->parent;
                }
                $end += $parent->_[HDOM::INFO_END];
            }

            // Get list of target nodes
            $nodes_start = $this->_[HDOM::INFO_BEGIN] + 1;
            $nodes_count = $end - $nodes_start;
            $nodes = array_slice($this->dom->nodes, $nodes_start, $nodes_count, true);
        } elseif ($parent_cmd === '>') { // Child Combinator
            $nodes = $this->children;
        } elseif ($parent_cmd === '+'
            && $this->parent
            && in_array($this, $this->parent->children, true)) { // Next-Sibling Combinator
            $index = array_search($this, $this->parent->children, true) + 1;
            if ($index < count($this->parent->children)) {
                $nodes[] = $this->parent->children[$index];
            }
        } elseif ($parent_cmd === '~'
            && $this->parent
            && in_array($this, $this->parent->children, true)) { // Subsequent Sibling Combinator
            $index = array_search($this, $this->parent->children, true);
            $nodes = array_slice($this->parent->children, $index);
        }

        // Go throgh each element starting at this element until the end tag
        // Note: If this element is a void tag, any previous void element is
        // skipped.
        foreach ($nodes as $node) {
            $pass = true;

            // Skip root nodes
            if (! $node->parent) {
                $pass = false;
            }

            // Handle 'text' selector
            if ($pass && $tag === 'text' && $node->tag === 'text') {
                $ret[array_search($node, $this->dom->nodes, true)] = 1;
                unset($node);

                continue;
            }

            // Skip if node isn't a child node (i.e. text nodes)
            if ($pass && ! in_array($node, $node->parent->children, true)) {
                $pass = false;
            }

            // Skip if tag doesn't match
            if ($pass && $tag !== '' && $tag !== $node->tag && $tag !== '*') {
                $pass = false;
            }

            // Skip if ID doesn't exist
            if ($pass && $id !== '' && ! isset($node->attr['id'])) {
                $pass = false;
            }

            // Check if ID matches
            if ($pass && $id !== '' && isset($node->attr['id'])) {
                // Note: Only consider the first ID (as browsers do)
                $node_id = explode(' ', trim($node->attr['id']))[0];

                if ($id !== $node_id) {
                    $pass = false;
                }
            }

            // Check if all class(es) exist
            if ($pass && $class !== '' && is_array($class) && ! empty($class)) {
                if (isset($node->attr['class'])) {
                    $node_classes = explode(' ', $node->attr['class']);

                    if ($lowercase) {
                        $node_classes = array_map('strtolower', $node_classes);
                    }

                    foreach ($class as $c) {
                        if (! in_array($c, $node_classes, true)) {
                            $pass = false;
                            break;
                        }
                    }
                } else {
                    $pass = false;
                }
            }

            // Check attributes
            if ($pass
                && $attributes !== ''
                && is_array($attributes)
                && ! empty($attributes)) {
                foreach ($attributes as $a) {
                    [
                        $att_name,
                        $att_expr,
                        $att_val,
                        $att_inv,
                        $att_case_sensitivity
                    ] = $a;

                    // Handle indexing attributes (i.e. "[2]")
                    /**
                     * Note: This is not supported by the CSS Standard but adds
                     * the ability to select items compatible to XPath (i.e.
                     * the 3rd element within it's parent).
                     *
                     * Note: This doesn't conflict with the CSS Standard which
                     * doesn't work on numeric attributes anyway.
                     */
                    if (is_numeric($att_name)
                        && $att_expr === ''
                        && $att_val === '') {
                        $count = 0;

                        // Find index of current element in parent
                        foreach ($node->parent->children as $c) {
                            if ($c->tag === $node->tag) {
                                $count++;
                            }
                            if ($c === $node) {
                                break;
                            }
                        }

                        // If this is the correct node, continue with next
                        // attribute
                        if ($count === (int) $att_name) {
                            continue;
                        }
                    }

                    // Check attribute availability
                    if ($att_inv) { // Attribute should NOT be set
                        if (isset($node->attr[$att_name])) {
                            $pass = false;
                            break;
                        }
                    } else { // Attribute should be set
                        // todo: "plaintext" is not a valid CSS selector!
                        if ($att_name !== 'plaintext'
                            && ! isset($node->attr[$att_name])) {
                            $pass = false;
                            break;
                        }
                    }

                    // Continue with next attribute if expression isn't defined
                    if ($att_expr === '') {
                        continue;
                    }

                    // If they have told us that this is a "plaintext"
                    // search then we want the plaintext of the node - right?
                    // todo "plaintext" is not a valid CSS selector!
                    if ($att_name === 'plaintext') {
                        $nodeKeyValue = $node->text();
                    } else {
                        $nodeKeyValue = $node->attr[$att_name];
                    }

                    // If lowercase is set, do a case insensitive test of
                    // the value of the selector.
                    if ($lowercase) {
                        $check = $this->match(
                            $att_expr,
                            strtolower($att_val),
                            strtolower($nodeKeyValue),
                            $att_case_sensitivity
                        );
                    } else {
                        $check = $this->match(
                            $att_expr,
                            $att_val,
                            $nodeKeyValue,
                            $att_case_sensitivity
                        );
                    }

                    if (! $check) {
                        $pass = false;
                        break;
                    }
                }
            }

            // Found a match. Add to list and clear node
            if ($pass) {
                $ret[$node->_[HDOM::INFO_BEGIN]] = 1;
            }
            unset($node);
        }
    }

    protected function match($exp, $pattern, $value, $case_sensitivity): bool|int
    {
        if ($case_sensitivity === 'i') {
            $pattern = strtolower($pattern);
            $value = strtolower($value);
        }

        return match ($exp) {
            '=' => ($value === $pattern),
            '!=' => ($value !== $pattern),
            '^=' => preg_match('/^'.preg_quote($pattern, '/').'/', $value),
            '$=' => preg_match('/'.preg_quote($pattern, '/').'$/', $value),
            '*=' => preg_match('/'.preg_quote($pattern, '/').'/', $value),
            '|=' => str_starts_with($value, $pattern),
            '~=' => in_array($pattern, explode(' ', trim($value)), true),
            default => false,
        };
    }

    protected function parse_selector($selector_string): array
    {
        /**
         * Pattern of CSS selectors, modified from mootools (https://mootools.net/)
         *
         * Paperg: Add the colon to the attribute, so that it properly finds
         * <tag attribute="something" > like google does.
         *
         * Note: if you try to look at this attribute, you MUST use getAttribute
         * since $dom->x:y will fail the php syntax check.
         *
         * Notice the \[ starting the attribute? and the @? following? This
         * implies that an attribute can begin with an @ sign that is not
         * captured. This implies that an html attribute specifier may start
         * with an @ sign that is NOT captured by the expression. Farther study
         * is required to determine of this should be documented or removed.
         *
         * Matches selectors in this order:
         *
         * [0] - full match
         *
         * [1] - tag name
         *     ([\w:\*-]*)
         *     Matches the tag name consisting of zero or more words, colons,
         *     asterisks and hyphens.
         *
         * [2] - id name
         *     (?:\#([\w-]+))
         *     Optionally matches a id name, consisting of an "#" followed by
         *     the id name (one or more words and hyphens).
         *
         * [3] - class names (including dots)
         *     (?:\.([\w\.-]+))?
         *     Optionally matches a list of classs, consisting of an "."
         *     followed by the class name (one or more words and hyphens)
         *     where multiple classes can be chained (i.e. ".foo.bar.baz")
         *
         * [4] - attributes
         *     ((?:\[@?(?:!?[\w:-]+)(?:(?:[!*^$|~]?=)[\"']?(?:.*?)[\"']?)?(?:\s*?(?:[iIsS])?)?\])+)?
         *     Optionally matches the attributes list
         *
         * [5] - separator
         *     ([\/, >+~]+)
         *     Matches the selector list separator
         */
        // phpcs:ignore Generic.Files.LineLength
        $pattern = "/([\w:\*-]*)(?:\#([\w-]+))?(?:|\.([\w\.-]+))?((?:\[@?(?:!?[\w:-]+)(?:(?:[!*^$|~]?=)[\"']?(?:.*?)[\"']?)?(?:\s*?(?:[iIsS])?)?\])+)?([\/, >+~]+)/is";

        preg_match_all(
            $pattern,
            trim($selector_string).' ', // Add final ' ' as pseudo separator
            $matches,
            PREG_SET_ORDER
        );

        $selectors = [];
        $result = [];

        foreach ($matches as $m) {
            $m[0] = trim($m[0]);

            // Skip NoOps
            if ($m[0] === '' || $m[0] === '/' || $m[0] === '//') {
                continue;
            }

            // Convert to lowercase
            if ($this->dom->lowercase) {
                $m[1] = strtolower($m[1]);
            }

            // Extract classes
            if ($m[3] !== '') {
                $m[3] = explode('.', $m[3]);
            }

            /* Extract attributes (pattern based on the pattern above!)

             * [0] - full match
             * [1] - attribute name
             * [2] - attribute expression
             * [3] - attribute value
             * [4] - case sensitivity
             *
             * Note: Attributes can be negated with a "!" prefix to their name
             */
            if ($m[4] !== '') {
                preg_match_all(
                    "/\[@?(!?[\w:-]+)(?:([!*^$|~]?=)[\"']?(.*?)[\"']?)?(?:\s+?([iIsS])?)?\]/is",
                    trim($m[4]),
                    $attributes,
                    PREG_SET_ORDER
                );

                // Replace element by array
                $m[4] = [];

                foreach ($attributes as $att) {
                    // Skip empty matches
                    if (trim($att[0]) === '') {
                        continue;
                    }

                    $inverted = (isset($att[1][0]) && $att[1][0] === '!');
                    $m[4][] = [
                        $inverted ? substr($att[1], 1) : $att[1], // Name
                        $att[2] ?? '', // Expression
                        $att[3] ?? '', // Value
                        $inverted, // Inverted Flag
                        (isset($att[4])) ? strtolower($att[4]) : '', // Case-Sensitivity
                    ];
                }
            }

            // Sanitize Separator
            if ($m[5] !== '' && trim($m[5]) === '') { // Descendant Separator
                $m[5] = ' ';
            } else { // Other Separator
                $m[5] = trim($m[5]);
            }

            // Clear Separator if it's a Selector List
            if ($is_list = ($m[5] === ',')) {
                $m[5] = '';
            }

            // Remove full match before adding to results
            array_shift($m);
            $result[] = $m;

            if ($is_list) { // Selector List
                $selectors[] = $result;
                $result = [];
            }
        }

        if (count($result) > 0) {
            $selectors[] = $result;
        }

        return $selectors;
    }

    /**
     * @return array|bool|mixed|string
     */
    public function __get($name)
    {
        if (isset($this->attr[$name])) {
            return $this->convert_text($this->attr[$name]);
        }

        return match ($name) {
            'outertext' => $this->outertext(),
            'innertext' => $this->innertext(),
            'plaintext' => $this->text(),
            'xmltext' => $this->xmltext(),
            default => array_key_exists($name, $this->attr),
        };
    }

    public function __set($name, $value): void
    {
        if ($name === 'outertext') {
            $this->_[HDOM::INFO_OUTER] = $value;
        }

        if ($name === 'innertext') {
            if (isset($this->_[HDOM::INFO_TEXT])) {
                $this->_[HDOM::INFO_TEXT] = $value;
            }

            $this->_[HDOM::INFO_INNER] = $value;
        }

        if (! isset($this->attr[$name])) {
            $this->_[HDOM::INFO_SPACE][] = [' ', '', ''];
            $this->_[HDOM::INFO_QUOTE][] = HDOM::QUOTE_DOUBLE;
        }

        $this->attr[$name] = $value;
    }

    /**
     * @return bool
     */
    public function __isset($name)
    {
        if ($name === 'innertext' || $name === 'plaintext' || $name === 'outertext') {
            return true;
        }

        //no value attr: nowrap, checked selected...
        return array_key_exists($name, $this->attr) || isset($this->attr[$name]);
    }

    /**
     * @return void
     */
    public function __unset($name)
    {
        if (isset($this->attr[$name])) {
            unset($this->attr[$name]);
        }
    }

    /**
     * @return false|mixed|string
     */
    public function convert_text($text): mixed
    {
        $converted_text = $text;

        $sourceCharset = '';
        $targetCharset = '';

        if ($this->dom) {
            $sourceCharset = strtoupper($this->dom->_charset);
            $targetCharset = strtoupper($this->dom->_target_charset);
        }

        if (! empty($sourceCharset)
            && ! empty($targetCharset)
            && (strcasecmp($sourceCharset, $targetCharset) !== 0)) {
            // Check if the reported encoding could have been incorrect and the text is actually already UTF-8
            if ((strcasecmp($targetCharset, 'UTF-8') === 0)
                && (self::is_utf8($text))) {
                $converted_text = $text;
            } else {
                $converted_text = @iconv($sourceCharset, $targetCharset, $text);
            }
        }

        // Lets make sure that we don't have that silly BOM issue with any of the utf-8 text we output.
        if ($targetCharset === 'UTF-8') {
            if (str_starts_with($converted_text, "\xef\xbb\xbf")) {
                $converted_text = substr($converted_text, 3);
            }

            if (str_ends_with($converted_text, "\xef\xbb\xbf")) {
                $converted_text = substr($converted_text, 0, -3);
            }
        }

        return $converted_text;
    }

    public static function is_utf8($str): bool
    {
        $c = 0;
        $b = 0;
        $bits = 0;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($str[$i]);
            if ($c > 128) {
                if (($c >= 254)) {
                    return false;
                }

                if ($c >= 252) {
                    $bits = 6;
                } elseif ($c >= 248) {
                    $bits = 5;
                } elseif ($c >= 240) {
                    $bits = 4;
                } elseif ($c >= 224) {
                    $bits = 3;
                } elseif ($c >= 192) {
                    $bits = 2;
                } else {
                    return false;
                }

                if (($i + $bits) > $len) {
                    return false;
                }

                while ($bits > 1) {
                    $i++;
                    $b = ord($str[$i]);
                    if ($b < 128 || $b > 191) {
                        return false;
                    }
                    $bits--;
                }
            }
        }

        return true;
    }

    public function get_display_size(): bool|array
    {
        $width = -1;
        $height = -1;

        if ($this->tag !== 'img') {
            return false;
        }

        // See if there is aheight or width attribute in the tag itself.
        if (isset($this->attr['width'])) {
            $width = $this->attr['width'];
        }

        if (isset($this->attr['height'])) {
            $height = $this->attr['height'];
        }

        // Now look for an inline style.
        if (isset($this->attr['style'])) {
            // Thanks to user gnarf from stackoverflow for this regular expression.
            $attributes = [];

            preg_match_all(
                '/([\w-]+)\s*:\s*([^;]+)\s*;?/',
                $this->attr['style'],
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $attributes[$match[1]] = $match[2];
            }

            // If there is a width in the style attributes:
            if (isset($attributes['width']) && $width === -1) {
                // check that the last two characters are px (pixels)
                if (strtolower(substr($attributes['width'], -2)) === 'px') {
                    $proposed_width = substr($attributes['width'], 0, -2);
                    // Now make sure that it's an integer and not something stupid.
                    if (filter_var($proposed_width, FILTER_VALIDATE_INT)) {
                        $width = $proposed_width;
                    }
                }
            }

            // If there is a width in the style attributes:
            // check that the last two characters are px (pixels)
            if (
                isset($attributes['height']) &&
                $height === -1 &&
                strtolower(substr($attributes['height'], -2)) === 'px'
            ) {
                $proposed_height = substr($attributes['height'], 0, -2);
                // Now make sure that it's an integer and not something stupid.
                if (filter_var($proposed_height, FILTER_VALIDATE_INT)) {
                    $height = $proposed_height;
                }
            }

        }

        // Future enhancement:
        // Look in the tag to see if there is a class or id specified that has
        // a height or width attribute to it.

        // Far future enhancement
        // Look at all the parent tags of this image to see if they specify a
        // class or id that has an img selector that specifies a height or width
        // Note that in this case, the class or id will have the img subselector
        // for it to apply to the image.

        // ridiculously far future development
        // If the class or id is specified in a SEPARATE css file thats not on
        // the page, go get it and do what we were just doing for the ones on
        // the page.

        return [
            'height' => $height,
            'width' => $width,
        ];
    }

    public function save(string $filepath = ''): string
    {
        $ret = $this->outertext();

        if ($filepath !== '') {
            file_put_contents($filepath, $ret, LOCK_EX);
        }

        return $ret;
    }

    public function addClass($class): void
    {
        if (is_string($class)) {
            $class = explode(' ', $class);
        }

        if (is_array($class)) {
            foreach ($class as $c) {
                if (isset($this->class)) {
                    if ($this->hasClass($c)) {
                        continue;
                    }

                    $this->class .= ' '.$c;
                } else {
                    $this->class = $c;
                }
            }
        }
    }

    public function hasClass($class): bool
    {
        if (is_string($class) && isset($this->class)) {
            return in_array($class, explode(' ', $this->class), true);
        }

        return false;
    }

    public function removeClass($class = null): void
    {
        if (! isset($this->class)) {
            return;
        }

        if (is_null($class)) {
            $this->removeAttribute('class');

            return;
        }

        if (is_string($class)) {
            $class = explode(' ', $class);
        }

        if (is_array($class)) {
            $class = array_diff(explode(' ', $this->class), $class);
            if (empty($class)) {
                $this->removeAttribute('class');
            } else {
                $this->class = implode(' ', $class);
            }
        }
    }

    public function getAllAttributes(): array
    {
        return $this->attr;
    }

    public function getAttribute($name): mixed
    {
        return $this->__get($name);
    }

    public function setAttribute($name, $value): void
    {
        $this->__set($name, $value);
    }

    public function hasAttribute($name): bool
    {
        return $this->__isset($name);
    }

    public function removeAttribute($name): void
    {
        $this->__set($name, null);
    }

    public function remove(): void
    {
        if ($this->parent) {
            $this->parent->removeChild($this);
        }
    }

    public function removeChild($node): void
    {
        $nidx = array_search($node, $this->nodes, true);
        $cidx = array_search($node, $this->children, true);
        $didx = array_search($node, $this->dom->nodes, true);

        if ($nidx !== false && $cidx !== false && $didx !== false) {

            foreach ($node->children as $child) {
                $node->removeChild($child);
            }

            foreach ($node->nodes as $entity) {
                $enidx = array_search($entity, $node->nodes, true);
                $edidx = array_search($entity, $node->dom->nodes, true);

                if ($enidx !== false && $edidx !== false) {
                    unset($node->nodes[$enidx]);
                    unset($node->dom->nodes[$edidx]);
                }
            }

            unset($this->nodes[$nidx], $this->children[$cidx], $this->dom->nodes[$didx]);

            $node->clear();
        }
    }

    public function getElementById($id): mixed
    {
        return $this->find("#$id", 0);
    }

    public function getElementsById($id, $idx = null): mixed
    {
        return $this->find("#$id", $idx);
    }

    public function getElementByTagName($name): mixed
    {
        return $this->find($name, 0);
    }

    public function getElementsByTagName($name, $idx = null): mixed
    {
        return $this->find($name, $idx);
    }

    public function parentNode(): mixed
    {
        return $this->parent();
    }

    /**
     * @return array|mixed|null
     */
    public function childNodes(int $idx = -1): mixed
    {
        return $this->children($idx);
    }

    /**
     * @return mixed|null
     */
    public function firstChild(): mixed
    {
        return $this->first_child();
    }

    /**
     * @return false|mixed|null
     */
    public function lastChild(): mixed
    {
        return $this->last_child();
    }

    public function nextSibling(): mixed
    {
        return $this->next_sibling();
    }

    public function previousSibling(): mixed
    {
        return $this->prev_sibling();
    }

    public function hasChildNodes(): bool
    {
        return $this->has_child();
    }

    public function nodeName(): string
    {
        return $this->tag;
    }

    public function appendChild($node): mixed
    {
        $node->parent($this);

        return $node;
    }
}
