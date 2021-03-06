<?php
namespace kjBotModule\kj415j45\pixiv;

use Exception;
use kjBot\SDK\CQCode;
use kjBot\Framework\Module;
use kjBot\Framework\Message;
use kjBot\Framework\DataStorage;
use kjBot\Framework\Event\MessageEvent;
use kjBotModule\kj415j45\CoreModule\Access;
use kjBotModule\kj415j45\CoreModule\Economy;
use kjBotModule\kj415j45\CoreModule\AccessLevel;

class Search extends Module{
    const USER_COST = 4;
    const SUPPORTER_COST = 3;

    public function process(array $args, MessageEvent $event): Message{
        $ac = Access::Control($event);
        $cost = static::USER_COST;
        if(!$ac->hasLevel(AccessLevel::Developer)){
            $userEconomy = new Economy($event->getId());
            try{
                $cost = $ac->hasLevel(AccessLevel::Supporter)?static::SUPPORTER_COST:static::USER_COST;
                $userEconomy->decBalance($cost);
            }catch(Exception $e){
                if($e->getCode() == Economy::NO_MONEY){
                    return $event->sendBack(Economy::STR_NO_MONEY_HINT);
                }
            }
        }

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
                    $word.= $args[$index++].'users入り ';
                    break;
                default:
                    $word.= ($arg.' ');
            }
        }while($arg!==NULL);
        
        $word = trim($word);

        if($event->fromGroup())$mode = 'safe';
        //ajax接口对空格只接受%20而不接受+，故采用rawurlencode()
        $webStr = 'https://www.pixiv.net/ajax/search/artworks/'
            .((0===strlen($word))?q('请提供关键词'):rawurlencode($word)).'?order=date_d&s_mode=s_tag&type=all&word='.rawurlencode($word) //s_mode设定为标签模糊搜索
            .'&p='.(int)($page??q('请提供页码'))
            .'&mode='.strtolower($mode)
        ;

        Utils::Init(\Config('pixivCookie'));

        $json = file_get_contents($webStr, false, stream_context_create(Utils::$pixivCookieHeader));
        if($json===false)q('无法打开 Pixiv');

        $result = json_decode($json);
        $illustManga = $result->body->illustManga;
        if($illustManga->total === 0) q('没有结果');
        $total = $illustManga->total;
        
        $data = $illustManga->data;
        $pendingData1 = $result->body->popular->recent;
        $pendingData2 = $result->body->popular->permanent;

        $pixiv = array_merge($data, $pendingData1, $pendingData2);

        //随机时不包含私货数据
        if(isset($target) && 1<=$target && $target<=count($pixiv)){
            $index = $target-1;
        }else{
            $index = rand(0, count($data)-1);
        }
        if($index+1 > count($data)){
            $_index = $index - count($data)+1;
            $pendingTotal = count($pixiv) - count($data);
            $indexText = "这是热门作品中的 {$_index}/{$pendingTotal}";
        }else{
            $_index = $index+1;
            $indexText = "这是第 {$page} 页第 {$_index} 幅";
        }

        $pixiv = $pixiv[$index++];
        $pixiv = Utils::GetIllustInfoByID($pixiv->id);
        $tags = Utils::GetIllustTagsFromPixivJSON($pixiv);
        $pixiv->illustComment = strip_tags(str_replace('<br />', "\n", $pixiv->illustComment));
        $filename = basename(parse_url($pixiv->urls->regular)['path']);
        DataStorage::SetData("pixiv/{$filename}", Utils::FetchImage($pixiv->urls->regular), false, false);
        $msg=<<<EOT
该关键字共有 {$total} 幅作品，{$indexText}
插画ID：{$pixiv->id} 共 {$pixiv->pageCount} P
画师ID：{$pixiv->userId}
标签：{$tags}
收藏：{$pixiv->bookmarkCount} 喜欢：{$pixiv->likeCount} 浏览：{$pixiv->viewCount}

{$pixiv->illustTitle}
{$pixiv->illustComment}
EOT;
        $msg .= sendImg(DataStorage::GetData("pixiv/{$filename}"));
        Access::Log($this, $event, "Result: {$pixiv->id}, Keywords: {$word}");
        $user = $event->getId();
        Access::LogForMe($this, "User: {$user}, Result: {$pixiv->id}, Keywords: {$word}");
        return $event->sendBack($msg);
    }
}