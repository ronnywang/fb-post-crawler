<?php

$content = file_get_contents('debug.html');
$doc = new DOMDocument;
@$doc->loadHTML($content);

$c = 0;

class Parser
{
    public static function getNum($n){
        if (preg_match('#^([0-9.]+)K$#', $n, $matches)) {
            return floatval($matches[1]) * 1000;
        } elseif (preg_match('#^[0-9.]+$#', $n)) {
            return floatval($n);
        } else {
            throw new Exception("unknown number: {$n}");
        }
    }

    public static function getUserId($href)
    {
        if (preg_match('#^https://www.facebook.com/people/[^/]+/(\d+)\?#', $href, $matches)) {
            return $matches[1];
        }

        if (preg_match('#^https://www.facebook.com/([^/]+)/?\?#', $href, $matches)) {
            if ($matches[1] != 'profile.php') {
                return $matches[1];
            }
        }
        return false;
    }

    public static function getPostId($href)
    {
        if (preg_match('#^/permalink\.php\?story_fbid=(\d+)&id=(\d+)#', $href, $matches)) {
            return array($matches[1], $matches[0]);
        }
        if (preg_match('#^/[^/]+/photos/[^/]+/(\d+)/#', $href, $matches)) {
            return array($matches[1], $matches[0]);
        }
        if (preg_match('#^/[^/]+/videos/(\d+)/#', $href, $matches)) {
            return array($matches[1], $matches[0]);
        }
        if (preg_match('#^/[^/]+/posts/([^/?]+)#', $href, $matches)) {
            return array($matches[1], $matches[0]);
        }
        if (preg_match('#^/groups/[^/]+/permalink/(\d+)/#', $href, $matches)) {
            return array($matches[1], $matches[0]);
        }
        if (preg_match('#^/events/(\d+)#', $href, $matches)) {
            return array($matches[1], $matches[0]);
        }
        print_r($href);
        exit;
    }

    public static function parse_dom($dom, $doc, $share = false)
    {
        $obj = new StdClass;

        // 先抓作者
        $author_dom = null;
        foreach ($dom->getElementsByTagName('a') as $a_dom) {
            if ($author_id = self::getUserId($a_dom->getAttribute('href'))) {
                $author_dom = $a_dom;
                break;
            }
        }
        if (is_null($author_dom)) {
            foreach ($dom->getElementsByTagName('span') as $span_dom) {
                $class = explode(' ', $span_dom->getAttribute('class'));
                if (in_array('fwb', $class)) {
                    $author_dom = $span_dom;
                    break;
                }
            }
            if (is_null($author_dom)) {
                echo $doc->saveHTML($dom);
                throw new Exception("找不到 author_dom");
            }
        }
        if ($author_dom->getElementsByTagName('img')->length or !$share) {
            if (!$author_img_dom = $author_dom->getElementsByTagName('img')->item(0)) {
                throw new Exception("找不到 img");
            }
            if (!$obj->author_name = $author_img_dom->getAttribute('aria-label')) {
                throw new Exception("找不到 img[aria-label]");
            }
            $obj->author_avatar = $author_img_dom->getAttribute('src');
        } else {
            $obj->author_name = $author_dom->nodeValue;
        }
        $obj->author_id = self::getUserId($author_dom->getAttribute('href'));
        $obj->author_link = explode('?', $author_dom->getAttribute('href'))[0];

        // 再抓時間和 post id
        if ($share) {
            if ($abbr_dom = $author_dom->parentNode->getElementsByTagName('abbr')->item(0)) {
            } else if ($author_dom->parentNode->parentNode->nextSibling and $abbr_dom = $author_dom->parentNode->parentNode->nextSibling->getElementsByTagName('abbr')->item(0)) {
            } else if ($abbr_dom = $author_dom->parentNode->parentNode->parentNode->parentNode->getElementsByTagName('abbr')->item(0)) {

            } else {
                echo $doc->saveHTML($author_dom->parentNode->parentNode->parentNode->parentNode);
                throw new Exception("找不到時間的 abbr (share = true)");
            }
        } else {

            if (!$abbr_dom = $author_dom->parentNode->getElementsByTagName('abbr')->item(0)) {
                echo $doc->saveHTML($author_dom);
                throw new Exception("找不到時間的 abbr");
            }
        }
        if (!$obj->post_time = $abbr_dom->getAttribute('data-utime')) {
            throw new Exception("找不到時間的 abbr[data-utime]");
        }
        if ($abbr_dom->parentNode->nodeName != 'a') {
            throw new Exception("找不到時間的 abbr 上一層的 a");
        }
        list($obj->post_id, $obj->post_url) = self::getPostId($abbr_dom->parentNode->getAttribute('href'));

        $profile_dom = $author_dom;
        while ($profile_dom = $profile_dom->parentNode) {
            if ($profile_dom->getElementsByTagName('abbr')->length) {
                break;
            }
            if ($share and $profile_dom->nextSibling and $profile_dom->nextSibling->nodeName != '#text' and $profile_dom->nextSibling->getElementsByTagName('abbr')->length) {
                $profile_dom = $profile_dom->nextSibling;
                break;
            }
        }
        if (!$profile_dom) {
            throw new Exception("找 profile_dom 失敗");
        }
        foreach ($profile_dom->getElementsByTagName('a') as $a_dom) {
            if ($a_dom->getAttribute('class') == 'profileLink') {
                if ($a_dom->parentNode->nextSibling->nodeName != '#text' and $a_dom->parentNode->nextSibling->nodeValue != ' shared a ') {
                    echo $doc->saveHTML($a_dom->parentNode);
                    throw new Exception("profileLink 右邊應該是 shared a");
                }
                if (!$share_dom = $a_dom->parentNode->nextSibling->nextSibling) {
                    throw new Exception("profileLink 右邊右邊找不到");
                }
                $obj->type = 'share';
                $obj->share_type = $share_dom->nodeValue;
                $obj->share_url = explode('?', $share_dom->getAttribute('href'))[0];
                break;
            }
        }

        if ($profile_dom->nextSibling) {
            $body_dom = $profile_dom->nextSibling;
        } else {
            $body_dom = $profile_dom->parentNode;
            while (!$body_dom->nextSibling) {
                $body_dom = $body_dom->parentNode;
            }
            $body_dom = $body_dom->nextSibling;
        }
        $obj->post_message = $body_dom->nodeValue;

        $attm_dom = $body_dom;
        while ($attm_dom = $attm_dom->nextSibling) {
            if (!$a_dom = $attm_dom->getElementsByTagName('a')->item(0)) {
                continue;
            }
            if (property_exists($obj, 'type') and $obj->type == 'share') {
                //print_r($obj);
                //$obj->share_content = self::parse_dom($attm_dom, $doc, $share = true);
                continue;
            }

            $href = $a_dom->getAttribute('href');
            if (preg_match('#/[^/]+/photos/#', $href)) {
                $obj->images = array();
                foreach ($attm_dom->getElementsByTagName('a') as $a_dom) {
                    if (!$img_dom = $a_dom->getElementsByTagName('img')->item(0)) {
                        continue;
                    }
                    $obj->images[] = array(
                        'url' => explode('?', $a_dom->getAttribute('href'))[0],
                        'alt' => $img_dom->getAttribute('alt'),
                        'src' => $img_dom->getAttribute('src'),
                    );
                }
            } elseif (strpos($href, 'https://l.facebook.com/l.php?') === 0) {
                $query = parse_url($href, PHP_URL_QUERY);
                parse_str($query, $arr);
                $obj->link = $arr['u'];
                if ($img_dom = $a_dom->getElementsByTagName('img')->item(0)) {
                    $obj->link_image = $img_dom->getAttribute('src');
                    $obj->link_image_alt = $img_dom->getAttribute('aria-label');
                }
                $a_dom = $attm_dom->getElementsByTagName('a')->item(1);
                $obj->link_title = $a_dom->nodeValue;
                $obj->link_content = $a_dom->parentNode->nextSibling->nodeValue;
            } else {
                echo $doc->saveHTML($attm_dom) . "\n";
            }
        }

        foreach ($dom->getElementsByTagName('span') as $span_dom) {
            if ($span_dom->getAttribute('data-testid') == 'UFI2ReactionsCount/sentenceWithSocialContext') {
                $obj->like_count = self::getNum($span_dom->nodeValue);
            }
        }
        foreach ($dom->getElementsByTagName('a') as $a_dom) {
            $href = $a_dom->getAttribute('href');
            if ($a_dom->getAttribute('data-testid') == 'UFI2CommentsCount/root') {
                $text = $a_dom->nodeValue;
                if (preg_match('#^([0-9,.K]+) Comments?$#', $text, $matches)) {
                    $obj->comment_count = self::getNum($matches[1]);
                } else {
                    error_log('comment');
                    var_dump($text);
                    exit;
                }
            } else if ($a_dom->getAttribute('data-testid') == 'UFI2SharesCount/root') {
                $text = $a_dom->nodeValue;
                if (preg_match('#^([0-9,.K]+) Shares?$#', $text, $matches)) {
                    $obj->share_count = self::getNum($matches[1]);
                } else {
                    error_log('shares');
                    var_dump($text);
                    exit;
                }
            }
        }
        foreach ($dom->getElementsByTagName('div') as $div_dom) {
            if ($div_dom->getAttribute('data-testid') == 'UFI2Comment/root_depth_0') {
                $comment_parent = $div_dom;
                $comment = new StdClass;

                while ($comment_parent) {
                    if ($comment_parent->getElementsByTagName('abbr')->length) {
                        break;
                    }
                    $comment_parent = $comment_parent->parentNode;
                }

                $comment->time = $comment_parent->getElementsByTagName('abbr')->item(0)->getAttribute('data-utime');
                foreach ($comment_parent->getElementsByTagName('span') as $span_dom) {
                    if ('ltr' != $span_dom->getAttribute('dir')) {
                        continue;
                    }
                    $comment->body = $span_dom->nodeValue;
                }

                foreach ($comment_parent->getElementsByTagName('a') as $a_dom) {
                    if (!$hovercard = $a_dom->getAttribute('data-hovercard')) {
                        continue;
                    }
                    if ($a_dom->getElementsByTagName('img')->length) {
                        $comment->commentter = $a_dom->getElementsByTagName('img')->item(0)->getAttribute('alt');
                    } else {
                        $comment->commentter = $a_dom->nodeValue;
                    }
                    $query = parse_url($hovercard, PHP_URL_QUERY);
                    parse_str($query, $arr);
                    $comment->commentter_id = $arr['id'];
                }

                foreach ($comment_parent->getElementsByTagName('img') as $img_dom) {
                    $classes = explode(' ', $img_dom->getAttribute('class'));
                    if (!in_array('img', $classes)) {
                        continue;
                    }
                    if ($img_dom->getAttribute('src') == '/images/assets_DO_NOT_HARDCODE/facebook_icons/diamond_filled_12_fds-spectrum-blue-gray.png') {
                        $comment->is_top_fan = true;
                    } elseif ($img_dom->parentNode->nodeName == 'a' and $img_dom->parentNode->getAttribute('data-hovercard')) {
                    } else {
                        $comment->image_src = $img_dom->getAttribute('src');
                        $comment->image_alt = $img_dom->getAttribute('alt');
                    }
                }
                if (!property_exists($obj, 'comments')) {
                    $obj->comments = array();
                }
                $obj->comments[] = $comment;
            }
        }

        return $obj;
    }
};

$obj = Parser::parse_dom($doc->getElementById('stream_pagelet'), $doc);
echo json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
