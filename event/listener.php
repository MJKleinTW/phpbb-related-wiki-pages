<?php
/**
 * Related Wiki Pages event listener.
 *
 * @package mjklein/relatedwiki
 */

namespace mjklein\relatedwiki\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
    /** @var \phpbb\template\template */
    protected $template;

    /** @var \phpbb\cache\driver\driver_interface */
    protected $cache;

    /** @var \phpbb\auth\auth */
    protected $auth;

    /*
     * SAFETY DEFAULTS
     *
     * admin_only_test_mode = true means only board admins trigger the wiki lookup
     * and only board admins see the Related Wiki Pages box.
     *
     * When stable, change admin_only_test_mode to false.
     */
    protected $enabled = true;
    protected $admin_only_test_mode = true;

    /*
     * Optional forum controls.
     *
     * Leave allowed_forum_ids empty to allow all forums.
     * Add forum IDs to restrict the box to only those forums.
     */
    protected $allowed_forum_ids = array();

    /*
     * Add forum IDs here if you never want related wiki pages shown there.
     */
    protected $blocked_forum_ids = array();

    /*
     * Wiki settings.
     */
    protected $wiki_api = 'https://behringer.world/mediawiki/api.php';
    protected $wiki_page_base = 'https://behringer.world/mediawiki/index.php/';
    protected $max_results = 5;
    protected $cache_seconds = 86400; // 24 hours
    protected $request_timeout = 2;   // seconds
    protected $min_query_length = 3;
    protected $max_query_length = 180;

    /*
     * First post search settings.
     */
    protected $use_first_post_text = true;
    protected $first_post_excerpt_chars = 500;

    /*
     * Search behavior.
     */
    protected $max_search_attempts = 7;

    /*
     * Domain-specific search aliases.
     *
     * These are not the main search. They are extra hints when common forum wording
     * does not match the exact wiki page title.
     */
    protected $alias_searches = array(
        'dante' => array(
            'WING Dante',
            'Flash WING Dante',
            'Gary Higgins Dante',
        ),
        'internal dante' => array(
            'WING Dante',
            'Flash WING Dante',
            'Dante card WING',
        ),
        'dante card' => array(
            'WING Dante',
            'Flash WING Dante',
            'Gary Higgins Dante',
        ),
        'midi' => array(
            'WING MIDI Table',
            'X32 MIDI Table',
            'X-Air MIDI',
            'P16 MIDI Table',
        ),
        'osc' => array(
            'X-Air OSC',
            'X32 OSC',
        ),
        'hub4' => array(
            'Hub4 DP48 Routing',
            'Ultranet Transfer',
        ),
        'dp48' => array(
            'Hub4 DP48 Routing',
            'Ultranet Transfer',
        ),
        'ultranet' => array(
            'Ultranet Transfer',
            'Hub4 DP48 Routing',
            'P16 MIDI Table',
        ),
        'stagebox' => array(
            'A H Stageboxes With WING',
            'Yamaha TIO Stageboxes',
            'S16 Configuration',
            'WING Rack Stagebox',
        ),
        'stageboxes' => array(
            'A H Stageboxes With WING',
            'Yamaha TIO Stageboxes',
            'S16 Configuration',
            'WING Rack Stagebox',
        ),
        's16' => array(
            'S16 Configuration',
            'X32 Rack As S16',
        ),
        'routing' => array(
            'WING Routing',
            'Hub4 DP48 Routing',
            'Sidechain Bus To Bus',
        ),
        'sidechain' => array(
            'Sidechain Bus To Bus',
        ),
        'scene' => array(
            'Scene Export',
            'Snap Export',
        ),
        'snapshot' => array(
            'Snap Export',
            'Scene Export',
        ),
        'snapshots' => array(
            'Snap Export',
            'Scene Export',
        ),
        'wifi' => array(
            'WiFi Best Practice',
        ),
        'ethercon' => array(
            'Neutrik Ethercon',
        ),
        'yamaha tio' => array(
            'Yamaha TIO Stageboxes',
        ),
        'x32 rack' => array(
            'X32 Rack As S16',
            'X32 Rack Ps Repair',
        ),
    );

    public function __construct(
        \phpbb\template\template $template,
        \phpbb\cache\driver\driver_interface $cache,
        \phpbb\auth\auth $auth
    )
    {
        $this->template = $template;
        $this->cache = $cache;
        $this->auth = $auth;
    }

    public static function getSubscribedEvents()
    {
        return array(
            'core.viewtopic_assign_template_vars_before' => 'show_related_wiki_pages',
        );
    }

    public function show_related_wiki_pages($event)
    {
        if (!$this->enabled)
        {
            return;
        }

        if ($this->admin_only_test_mode && !$this->auth->acl_get('a_'))
        {
            return;
        }

        $topic_data = $event['topic_data'];

        if (empty($topic_data['topic_id']) || empty($topic_data['topic_title']))
        {
            return;
        }

        $forum_id = !empty($topic_data['forum_id']) ? (int) $topic_data['forum_id'] : 0;

        if (!$this->forum_allowed($forum_id))
        {
            return;
        }

        $topic_id = (int) $topic_data['topic_id'];
        $topic_title = $this->clean_query($topic_data['topic_title']);

        if (utf8_strlen($topic_title) < $this->min_query_length)
        {
            return;
        }

        $first_post_data = $this->get_first_post_data($event, $topic_data);
        $first_post_text = $this->use_first_post_text ? $first_post_data['text'] : '';
        $first_post_stamp = $first_post_data['stamp'];

        $cache_key = '_relatedwiki_v04_' . md5($topic_id . ':' . $topic_title . ':' . $first_post_stamp . ':' . $this->short_hash($first_post_text));
        $pages = $this->cache->get($cache_key);

        if ($pages === false)
        {
            $pages = $this->search_wiki_smart($topic_title, $first_post_text);
            $this->cache->put($cache_key, $pages, $this->cache_seconds);
        }

        if (empty($pages))
        {
            return;
        }

        $this->template->assign_var('S_RELATEDWIKI', true);

        foreach ($pages as $page)
        {
            $this->template->assign_block_vars('relatedwiki_pages', array(
                'TITLE' => $this->escape_for_template($page['title']),
                'URL'   => $this->escape_for_template($page['url']),
            ));
        }
    }

    protected function forum_allowed($forum_id)
    {
        if (in_array($forum_id, $this->blocked_forum_ids, true))
        {
            return false;
        }

        if (!empty($this->allowed_forum_ids) && !in_array($forum_id, $this->allowed_forum_ids, true))
        {
            return false;
        }

        return true;
    }

    protected function get_first_post_data($event, $topic_data)
    {
        $data = array(
            'text' => '',
            'stamp' => 0,
        );

        if (empty($topic_data['topic_first_post_id']))
        {
            return $data;
        }

        $first_post_id = (int) $topic_data['topic_first_post_id'];

        if ($first_post_id <= 0)
        {
            return $data;
        }

        $rowset = array();

        try
        {
            $rowset = $event['rowset'];
        }
        catch (\Exception $e)
        {
            $rowset = array();
        }

        if (empty($rowset) || !is_array($rowset) || empty($rowset[$first_post_id]) || !is_array($rowset[$first_post_id]))
        {
            return $data;
        }

        $row = $rowset[$first_post_id];

        $message = '';

        if (!empty($row['post_text']))
        {
            $message = $row['post_text'];
        }
        else if (!empty($row['post_subject']))
        {
            $message = $row['post_subject'];
        }

        $data['text'] = $this->clean_first_post_text($message);

        if (!empty($row['post_edit_time']))
        {
            $data['stamp'] = (int) $row['post_edit_time'];
        }
        else if (!empty($row['post_time']))
        {
            $data['stamp'] = (int) $row['post_time'];
        }
        else
        {
            $data['stamp'] = $this->short_hash($data['text']);
        }

        return $data;
    }

    protected function clean_first_post_text($text)
    {
        $text = (string) $text;

        /*
         * Remove common phpBB BBCode blocks that create noisy search input.
         */
        $text = preg_replace('/\[quote(?:=[^\]]*)?\].*?\[\/quote\]/isu', ' ', $text);
        $text = preg_replace('/\[code\].*?\[\/code\]/isu', ' ', $text);
        $text = preg_replace('/\[url(?:=[^\]]*)?\].*?\[\/url\]/isu', ' ', $text);
        $text = preg_replace('/https?:\/\/\S+/iu', ' ', $text);

        /*
         * Strip remaining BBCode tags.
         */
        $text = preg_replace('/\[[^\]]+\]/u', ' ', $text);

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        if (utf8_strlen($text) > $this->first_post_excerpt_chars)
        {
            $text = utf8_substr($text, 0, $this->first_post_excerpt_chars);
        }

        return $text;
    }

    protected function clean_query($value)
    {
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value);

        if (utf8_strlen($value) > $this->max_query_length)
        {
            $value = utf8_substr($value, 0, $this->max_query_length);
        }

        return $value;
    }

    protected function search_wiki_smart($topic_title, $first_post_text = '')
    {
        $queries = $this->build_search_queries($topic_title, $first_post_text);
        $all_pages = array();

        foreach ($queries as $query)
        {
            if (utf8_strlen($query) < $this->min_query_length)
            {
                continue;
            }

            $pages = $this->search_wiki($query);

            foreach ($pages as $page)
            {
                $all_pages[] = $page;

                if (count($all_pages) >= ($this->max_results * 3))
                {
                    break 2;
                }
            }
        }

        return $this->dedupe_pages($all_pages);
    }

    protected function build_search_queries($topic_title, $first_post_text = '')
    {
        $queries = array();

        $clean_title = $this->clean_query($topic_title);
        $queries[] = $clean_title;

        $without_junk = $this->remove_junk_words($clean_title);
        if ($without_junk !== $clean_title)
        {
            $queries[] = $without_junk;
        }

        $title_keywords = $this->keyword_fallback($without_junk);
        if ($title_keywords !== '' && $title_keywords !== $without_junk && $title_keywords !== $clean_title)
        {
            $queries[] = $title_keywords;
        }

        if ($this->use_first_post_text && $first_post_text !== '')
        {
            $first_keywords = $this->keyword_fallback($first_post_text);

            if ($first_keywords !== '')
            {
                $queries[] = trim($title_keywords . ' ' . $first_keywords);
                $queries[] = $first_keywords;
            }
        }

        $alias_queries = $this->alias_queries_for_text($clean_title . ' ' . $first_post_text);
        foreach ($alias_queries as $alias_query)
        {
            $queries[] = $alias_query;
        }

        $unique = array();
        $seen = array();

        foreach ($queries as $query)
        {
            $query = $this->clean_query($query);
            $key = utf8_strtolower($query);

            if ($query !== '' && empty($seen[$key]))
            {
                $unique[] = $query;
                $seen[$key] = true;
            }

            if (count($unique) >= $this->max_search_attempts)
            {
                break;
            }
        }

        return $unique;
    }

    protected function alias_queries_for_text($text)
    {
        $text = utf8_strtolower($text);
        $matches = array();

        foreach ($this->alias_searches as $trigger => $searches)
        {
            $trigger_lower = utf8_strtolower($trigger);

            if ($trigger_lower === '')
            {
                continue;
            }

            if (preg_match('/\b' . preg_quote($trigger_lower, '/') . '\b/u', $text))
            {
                foreach ($searches as $search)
                {
                    $matches[] = $search;
                }
            }
        }

        return $matches;
    }

    protected function remove_junk_words($query)
    {
        $junk_patterns = array(
            '/\btest\b/iu',
            '/\btesting\b/iu',
            '/\bhelp\b/iu',
            '/\bquestion\b/iu',
            '/\bproblem\b/iu',
            '/\bissue\b/iu',
            '/\bsolved\b/iu',
            '/\bresolved\b/iu',
            '/\bplease\b/iu',
            '/\bneed\b/iu',
            '/\bhow\s+do\s+i\b/iu',
            '/\bhow\s+to\b/iu',
            '/\bcan\s+someone\b/iu',
            '/\banyone\b/iu',
            '/\bre:\b/iu',
        );

        $query = preg_replace($junk_patterns, ' ', $query);
        $query = preg_replace('/\s+/u', ' ', $query);

        return trim($query);
    }

    protected function keyword_fallback($query)
    {
        $query = preg_replace('/[^\p{L}\p{N}\+\-\#\s]/u', ' ', $query);
        $query = preg_replace('/\s+/u', ' ', $query);
        $query = trim($query);

        if ($query === '')
        {
            return '';
        }

        $words = preg_split('/\s+/u', $query);
        $keep = array();

        $stop_words = array(
            'the' => true,
            'and' => true,
            'or' => true,
            'for' => true,
            'with' => true,
            'from' => true,
            'about' => true,
            'into' => true,
            'this' => true,
            'that' => true,
            'using' => true,
            'use' => true,
            'via' => true,
            'to' => true,
            'in' => true,
            'on' => true,
            'of' => true,
            'a' => true,
            'an' => true,
            'is' => true,
            'are' => true,
            'was' => true,
            'were' => true,
            'be' => true,
            'as' => true,
            'by' => true,
            'at' => true,
            'it' => true,
            'my' => true,
            'your' => true,
            'i' => true,
        );

        foreach ($words as $word)
        {
            $word = trim($word);
            $lower = utf8_strtolower($word);

            if ($word === '' || isset($stop_words[$lower]))
            {
                continue;
            }

            /*
             * Keep short known technical tokens.
             */
            if (utf8_strlen($word) < 3 && !preg_match('/^(x32|m32)$/iu', $word))
            {
                continue;
            }

            $keep[] = $word;

            if (count($keep) >= 8)
            {
                break;
            }
        }

        return implode(' ', $keep);
    }

    protected function dedupe_pages($pages)
    {
        $deduped = array();
        $seen = array();

        foreach ($pages as $page)
        {
            if (empty($page['title']) || empty($page['url']))
            {
                continue;
            }

            $key = utf8_strtolower(str_replace('_', ' ', trim($page['title'])));

            if (isset($seen[$key]))
            {
                continue;
            }

            $deduped[] = $page;
            $seen[$key] = true;

            if (count($deduped) >= $this->max_results)
            {
                break;
            }
        }

        return $deduped;
    }

    protected function search_wiki($query)
    {
        $params = array(
            'action'      => 'query',
            'list'        => 'search',
            'srsearch'    => $query,
            'srnamespace' => 0,
            'srlimit'     => max(1, min(10, (int) $this->max_results)),
            'format'      => 'json',
        );

        $url = $this->wiki_api . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $json = $this->http_get($url);

        if ($json === false || $json === '')
        {
            return array();
        }

        $data = json_decode($json, true);

        if (!is_array($data) || empty($data['query']['search']) || !is_array($data['query']['search']))
        {
            return array();
        }

        $pages = array();

        foreach ($data['query']['search'] as $result)
        {
            if (empty($result['title']) || !is_string($result['title']))
            {
                continue;
            }

            $title = trim($result['title']);

            if ($title === '')
            {
                continue;
            }

            $pages[] = array(
                'title' => $title,
                'url'   => $this->make_wiki_page_url($title),
            );
        }

        return $pages;
    }

    protected function make_wiki_page_url($title)
    {
        return $this->wiki_page_base . rawurlencode(str_replace(' ', '_', $title));
    }

    protected function http_get($url)
    {
        if (!$this->url_is_allowed($url))
        {
            return false;
        }

        if (function_exists('curl_init'))
        {
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->request_timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->request_timeout);
            curl_setopt($ch, CURLOPT_USERAGENT, 'BehringerWorldRelatedWiki/0.4');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

            $result = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($result !== false && $status >= 200 && $status < 300)
            {
                return $result;
            }

            return false;
        }

        if (!ini_get('allow_url_fopen'))
        {
            return false;
        }

        $context = stream_context_create(array(
            'http' => array(
                'timeout' => $this->request_timeout,
                'header'  => "User-Agent: BehringerWorldRelatedWiki/0.4\r\n",
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));

        return @file_get_contents($url, false, $context);
    }

    protected function url_is_allowed($url)
    {
        $api = parse_url($this->wiki_api);
        $target = parse_url($url);

        if (empty($api['scheme']) || empty($api['host']) || empty($target['scheme']) || empty($target['host']))
        {
            return false;
        }

        if (strtolower($target['scheme']) !== 'https')
        {
            return false;
        }

        if (strtolower($api['host']) !== strtolower($target['host']))
        {
            return false;
        }

        return true;
    }

    protected function escape_for_template($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function short_hash($value)
    {
        return substr(md5((string) $value), 0, 12);
    }
}
