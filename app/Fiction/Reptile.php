<?php


namespace App\Fiction;

use QL\QueryList;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Reptile
{
    private $rand_ip, $timeout, $user_agent, $referer, $reptile;

    function __construct()
    {
        $this->reptile = config('reptile');
        if (!Storage::disk('local')->exists('cate')) {
            Storage::disk('local')->put('log\cate', json_encode($this->reptile['list_cate']));
            Storage::disk('local')->put('log\chapter', 1);
        }

        is_array($this->reptile['domain']) and $this->reptile['domain'] = $this->reptile['domain'][array_rand($this->reptile['domain'])];
        $this->rand_ip = mt_rand(13, 255) . '.' . mt_rand(13, 255) . '.' . mt_rand(13, 255) . '.' . mt_rand(13, 255);
        $this->timeout = 8;
        $this->user_agent = 'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)';
        $this->referer = 'https://www.baidu.com/s?wd=%E7%99%BE%E5%BA%A6&rsv_spt=1&rsv_iqid=0xe5a39f3b0003c303&issp=1&f=8&rsv_bp=0&rsv_idx=2&ie=utf-8&tn=baiduhome_pg&rsv_enter=1&rsv_sug3=6&rsv_sug1=4&rsv_sug7=100';
    }

    public function saveLog($msg = '')
    {
        Storage::disk('local')->put('log\error', $msg);
        return FALSE;
    }

    public function getList(int $cate = 0)
    {
        if (!$cate)
            for ($i = 0; $i < 10; $i++) {
                $cate_k = array_rand($this->reptile['list_cate']);
                if (!Cache::get('reptile:' . $cate_k, FALSE)) {
                    continue;
                }
            }
        else $cate_k = 'k_' . $cate;

        $cate_page = json_decode(Storage::disk('local')->get('log\cate'), TRUE);
        $cate_page[$cate_k]['page'] = $cate_page[$cate_k]['page'] ?? 1;
        $url = str_replace('{page}', $cate_page[$cate_k]['page']++, $this->reptile['list_url']);
        $url = str_replace('{cate}', $cate_page[$cate_k]['cate'], $url);

        $rules['url'] = [$this->reptile['list_selector'], 'href'];
        $rules['title'] = [$this->reptile['list_title_selector'], 'text'];
        $rules['thumb'] = [$this->reptile['list_thumb_selector'], 'src'];
        $rules['author'] = [$this->reptile['list_author_selector'], 'text'];

        $list = $this->getHtml($this->reptile['domain'] . $url, $rules);
        if (!$list) {
            Cache::put('reptile:' . $cate_k, TRUE, 8);
            return FALSE;
        }

        foreach ($list as $item) {
            $data['url'] = str_replace($this->reptile['host'], '', $item['url']);
            $data['title'] = $item['title'];
            $data['thumb'] = $item['thumb'];
            $data['author'] = str_replace('作者：', '', $item['author']);
            $data['category_id'] = $cate_page[$cate_k]['my_cate'];
//            $data['category'] = $category->name;
            $data['status'] = 1;

            $data['created_at'] = date('Y-m-d H:i:s');

            DB::table('articles')->updateOrInsert(['title' => $item['title'], 'author' => $item['author']], $data);
        }
        return Storage::disk('local')->put('log\cate', json_encode($cate_page));
    }

    public function getArticle(int $article_id = 0)
    {
        if ($article_id)
            $row = DB::table('articles')->find($article_id, ['id', 'url']);
        else
            $row = DB::table('articles')->where(['font_count' => 0])->first(['id', 'url']);

        $data['font_count'] = 0;
        if (strpos($row->url, '_') !== FALSE) {
//            $rules['title']    = [$this->reptile['view_title_selector'], 'text'];
            $rules['content'] = [$this->reptile['view_selector'], 'content'];
            $rules['full'] = [$this->reptile['view_full_selector'], 'content'];
            $rules['thumb'] = [$this->reptile['view_thumb_selector'], 'content'];
//            $rules['author']   = [$this->reptile['view_author_selector'], 'content'];
            $rules['category'] = [$this->reptile['view_cate_selector'], 'content'];
            $article = $this->getHtml($this->reptile['domain'] . $row->url, $rules);
            if (!$article) return $this->saveLog('Error: article empty');;

            $article = $article[0];
//            if (strpos($article['thumb'], $this->reptile['domain']) !== FALSE) {
//                $data['thumb'] = '';
//            } else {
//                $thumb = file_get_contents($article['thumb']) ?: '';
//                $data['thumb'] = Storage::disk('public')->put('thumb/' . $row->id . substr($article['thumb'], -5), $thumb)
//                    ? 'thumb/' . $row->id . substr($article['thumb'], -5) : '';
//            }
            $data['is_full'] = $article['full'] === '完本' ? 1 : 0;
            $data['category'] = $article['category'];
            $data['info'] = $article['content'];
            $data['thumb'] = $article['thumb'];

            $reptile_id = substr($row->url, 5, -5);
            $data['url'] = $row->url = 'book/' . floor($reptile_id / 1000) . '/' . $reptile_id;
            DB::table('articles')->where(['id' => $row->id])->update($data);
        }

        $chapter = $this->getHtml($this->reptile['domain'] . $row->url, ['area_html' => [$this->reptile['chapter_area_selector'], 'html']]);
        if (!$chapter) return FALSE;

        //章节目录处理
        $chapter = $chapter[0];
        preg_match_all('/\[link\]|\[title\]|\[string\]/', $this->reptile['chapter_regx'], $matches);
        $link_key = $title_key = $string_key = 0;
        foreach ($matches[0] as $key => $item) {
            switch ($item) {
                case '[link]':
                    $link_key = $key + 1;
                    break;
                case '[title]':
                    $title_key = $key + 1;
                    break;
//                case '[string]':
//                    $string_key = $key + 1;
//                    break;
            }
        }
        //获取规则中关键key的顺序

        //章节顺序
        $num_cn = ['一', '二', '三', '四', '五', '六', '七', '八', '九', '十', '百', '千', '万', '亿'];
        $num_ar = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '', '', '', ''];

        $num_cn += ['第十章', '十章', '百章', '千章', '万章', '亿章', '第'];
        $num_ar += ['10', '0', '00', '000', '0000', '00000000', ''];

        //正则分隔
        $pattern = str_replace(['[link]', '[title]', '[string]', '?', '/', '|', '+', '-', '.', '[', ']', 'XXXX', 'CCCC'], ['XXXX', 'XXXX', 'CCCC', '\\?', '\\/', '\\|', '\\+', '\\-', '\\.', '\\[', '\\]', '([\\w\\W]*?)', '(.*?)'], addslashes($this->reptile['chapter_regx']));
        preg_match_all('/' . $pattern . '/s', $chapter['area_html'], $matches);
        for ($i = 0; $i < count($matches[$link_key]); $i++) {
            //统计字数
            $title = explode('，共', $matches[$title_key][$i]);
            $data['font_count'] += (int)substr($title[1], 0, -1);

            $chapter_list[intval(str_replace($num_cn, $num_ar, $title[0]))] = [
                'article_id'   => $row->id,
                'chapter_id'   => intval(str_replace($this->reptile['host'] . $row->url . '/', '', $matches[$link_key][$i])),
                'chapter_name' => $title[0],
            ];
        }

        if ($chapter_list ?? []) {
            ksort($chapter_list);
            DB::table('articles_chapter')->insertOrIgnore($chapter_list);

            $last = end($chapter_list);
            $data['last_chapter'] = $last['chapter_name'];
            $data['last_chapter_id'] = $last['chapter_id'];
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        DB::table('articles')->where(['id' => $row->id])->update($data);
        return $chapter_list ?? $this->saveLog('Error: article chapter empty');
    }

    public function getChapter(int $chapter_id = 0)
    {
        $Storage = Storage::disk('local');
        if ($chapter_id)
            $chapter = DB::table('articles_chapter')->find($chapter_id);
        else {
            $num = $Storage->get('log\chapter');
            $chapter = DB::table('articles_chapter')->find($num++);
        }
        $article = DB::table('articles')->find($chapter->article_id, ['id', 'url']);

        $content = $this->getHtml(
            $this->reptile['domain'] . $article->url . '/' . $chapter->chapter_id . '.html',
            ['content' => [$this->reptile['chapter_cont_selector'], 'html', '-script -a -ins']],
            'chapter_cont_pre_filter');

        if ($content = $content[0]['content'] ?? FALSE) {
            $storage_id = floor($article->id / 1000) . '/' . $article->id;
            $data['title'] = $chapter->chapter_name;
            $data['content'] = $content;
            $Storage->put($storage_id . '/' . $chapter->chapter_id, json_encode($data));
            if (isset($num)) $Storage->put('log\chapter', $num);
            return TRUE;
        }
        return $this->saveLog('Error: chapter content empty');;
    }

    private function getHtml(string $url, array $rules = [], string $pre_filter = '')
    {
        try {
            $html_contents = $this->curlGetContents($url, TRUE);

            //过滤“文字水印”
            if ($pre_filter) {
                $pre_filter = explode('[line]', $this->reptile[$pre_filter]);
                foreach ($pre_filter as $item) {
                    preg_match('#^\\{filter\\s+replace\\s*=\\s*\'([^\']*)\'\\s*\\}(.*)\\{/filter\\}#', $item, $matches);
                    if (isset($matches[2]) && !empty($matches[2])) {
                        $matches[2] = str_replace('~', '\\~', $matches[2]);
                        $matches[2] = str_replace('"', '\\"', $matches[2]);
                        $html_contents = preg_replace('~' . $matches[2] . '~iUs', $matches[1], $html_contents);
                    } else {
                        $html_contents = str_replace($item, '', $html_contents);
                    }
                }
            }

            $query = QueryList::setHtml($html_contents)->rules($rules)->query();
            !($data = $query->getData()) and $data = $query->encoding('UTF-8', 'UTF-8')->removeHead()->getData();

            return $data->all();
        } catch (\ErrorException $e) {
            return $this->saveLog('Error: http bug');
        }
    }

    //编码过滤
    private function changeCharset(string $html_contents)
    {
        $charset = mb_detect_encoding($html_contents, ["UTF-8", "GBK", "GB2312"]);
        $charset = strtolower($charset);
        if ("cp936" == $charset) {
            $charset = "GBK";
        }
        if ("utf-8" != $charset) {
            $html_contents = iconv($charset, "UTF-8//IGNORE", $html_contents);
        }
        $html_contents = preg_replace('/<meta([^<>]*)charset=[^\\w]?([-\\w]+)([^<>]*)>/', '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />', $html_contents);
        $html_contents = str_replace([
            'gbk',
            'gb2312',
            'GBK',
            'GB2312'], 'utf-8', $html_contents);
        return $html_contents;
    }

    //curl伪装访问
    private function curlGetContents(string $url, bool $un_header = FALSE)
    {
        $client = new Client();
        $proxy = $client->request('GET', "http://47.244.114.115/api/proxies/common", [
            'query'           => ['anonymity' => 'high_anonymous'],
            'connect_timeout' => $this->timeout,
            'timeout'         => config('proxy.timeout'),
        ])->getBody()->getContents();
        if (!$proxy) return $this->saveLog('Error: proxy bug');;
        $proxy = json_decode($proxy)->data;

        $header = [
            'Proxy-Client-IP'    => $this->rand_ip,
            'WL-Proxy-Client-IP' => $this->rand_ip,
            'X-Forwarded-For'    => $this->rand_ip,
            "Cache-Control"      => "no-cache",
            "Accept"             => "*/*",
            "Host"               => explode('/', $this->reptile['host'])[2],
            'User-Agent'         => 'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_8; en-us) AppleWebKit/534.50 (KHTML, like Gecko) Version/5.1 Safari/534.50',
            'Referer'            => 'http://www.quanshuwang.com/',
        ];

        $contents = $client->request('GET', $url, [
            'proxy'           => $proxy->protocol . '://' . $proxy->ip . ':' . $proxy->port,
            'cookies'         => new \GuzzleHttp\Cookie\CookieJar(),
            'headers'         => $header,
            'verify'          => TRUE,
            'connect_timeout' => $this->timeout,
            'timeout'         => config('proxy.timeout'),
        ])->getBody()->getContents();

        if (strtoupper($this->reptile['charset']) !== 'UTF-8')
            $contents = $this->changeCharset($contents);

        return $contents;
    }
}

