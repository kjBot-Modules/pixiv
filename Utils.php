<?php
namespace kjBotModule\kj415j45\pixiv;

class Utils{
    public static $pixivCookieHeader;

    //cookie至少需要包含phpsessionid和devide_token
    public static function Init($cookie = NULL){
        static::$pixivCookieHeader = [
            "http" => [
                "header" => "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\n".
                            'cookie: '.$cookie??\Config('pixivCookie')."\n"
            ]
        ];
    }

    public static function GetIllustInfoByID($iID){
        $web = file_get_contents('https://www.pixiv.net/member_illust.php?mode=medium&illust_id='.$iID, false, stream_context_create(static::$pixivCookieHeader));
        if($web===false)q('无法打开 Pixiv');
    
        if(!preg_match('/"illust":([\S\s]*),"user"/', $web, $result)){
            q('没有这张插画');
        }
    
        $pixiv = json_decode(html_entity_decode($result[1]))->$iID;
        return $pixiv;
    }
    
    public static function GetIllustTagsFromPixivJSON($pixiv){
        $tags = $pixiv->tags->tags;
        $tagString = '';
        foreach($tags as $tag){
            $tagString.= $tag->tag.' ';
        }
        return rtrim($tagString);
    }
    
    public static function FetchImage($link){
        return file_get_contents($link, false, stream_context_create([
            'http' => [
                'header' => 'referer: https://pixiv.net'
            ]
        ]));
    }
}
