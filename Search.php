<?php
namespace kjBotModule\kj415j45\pixiv;

use kjBot\Framework\Module;
use kjBot\Framework\Message;
use kjBot\Framework\Event\MessageEvent;
use kjBot\Framework\DataStorage;

class Search extends Module{
    public function process(array $args, MessageEvent $event): Message
    {
        $index = 1;
        $page = 1;
        $word = '';
        do{
            $arg = $args[$index++];
            switch($arg){
                case '-':
                    $target = $args[$index++];
                    break;
                case '-page':
                    $page = $args[$index++];
                    break;  
                case '-mode':
                    $mode = $args[$index++];
                    break;
                case '-like':
                    $word.= $args[$index++].urlencode('users入り ');
                    break;
                default:
                    $word.= ($arg.' ');
            }
        }while($arg!==NULL);
        
        $word = trim($word);

        if($event->fromGroup())$mode = 'safe';
        $webStr = 'https://www.pixiv.net/search.php?type=illust'
            .'&p='.($page??q('请提供页码'))
            .'&mode='.strtolower($mode)
            .'&word='.((0===strlen($word))?q('请提供关键词'):$word)
        ;

        Utils::Init(\Config('pixivCookie'));

        $web = file_get_contents($webStr, false, stream_context_create(Utils::$pixivCookieHeader));
        if($web===false)q('无法打开 Pixiv');

        preg_match('/data-items="([^"]*)"/', $web, $match);
        preg_match('/<span class="count-badge">(\d+)件/', $web, $count);

        $json = html_entity_decode($match[1]);
        if($json == '[]' || $json == '')q('没有结果');
        $result = json_decode($json);

        if(isset($target) && 1<=$target && $target<=count($result)){
            $index = $target-1;
        }else{
            $index = rand(0, count($result)-1);
        }

        $pixiv = $result[$index++];
        $pixiv = Utils::GetIllustInfoByID($pixiv->illustId);
        $tags = Utils::GetIllustTagsFromPixivJSON($pixiv);
        $pixiv->illustComment = strip_tags(str_replace('<br />', "\n", $pixiv->illustComment));
        $msg=<<<EOT
该关键字共有 {$count[1]} 幅作品，这是第 {$page} 页第 {$index} 幅
插画ID：{$pixiv->illustId} 共 {$pixiv->pageCount} P
画师ID：{$pixiv->userId}
标签：{$tags}
收藏：{$pixiv->bookmarkCount} 喜欢：{$pixiv->likeCount} 浏览：{$pixiv->viewCount}

{$pixiv->illustTitle}
{$pixiv->illustComment}
[CQ:image,file={$pixiv->urls->regular}]
EOT;
        return $event->sendBack($msg);
    }
}
