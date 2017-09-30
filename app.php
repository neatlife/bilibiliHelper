<?php

// Normal autoload
$autoload = __DIR__ . '/vendor/autoload.php';

require $autoload;
use Gui\Output;
use Gui\OsDetector;
use Gui\Application;
use Gui\Components\Label;
use Gui\Components\Button;
use Gui\Components\Option;
use Gui\Components\Select;
use Gui\Components\InputText;

class Bilibili
{
    public static $playUrls = [];

	public static function getVideoUrl($videoUrl)
    {
        preg_match("/(\d+)/", $videoUrl, $matchs);
        $avId = $matchs[1];
        $videoCidUrl = sprintf("https://api.bilibili.com/view?appkey=8e9fc618fbd41e28&id=%s", $avId);

        $response = (new \GuzzleHttp\Client())->get($videoCidUrl);
        $videoCid = json_decode($response->getBody(), true)['cid'];
        $params = sprintf("_appver=424000&_device=android&_down=0&_hwid=%s&_p=1&_tid=0&appkey=452d3958f048c02a&cid=%s&otype=json&platform=android", rand(10000, 99999), $videoCid);
        $sign = md5("${params}f7c926f549b9becf1c27644958676a21");
        $playListUrl = sprintf("https://interface.bilibili.com/playurl?${params}&sign=${sign}");

        $response = (new \GuzzleHttp\Client())->get($playListUrl);
        $playUrlJson = json_decode($response->getBody(), true);
        $playUrls = [];
        for ($i = 0, $count = count($playUrlJson['durl']); $i < $count; $i++) {
            $playUrls[] = $playUrlJson['durl'][$i]['url'];
        }
        return $playUrls;
    }

    public static function getLiveUrl($liveUrl)
    {
        $response = (new \GuzzleHttp\Client())->get($liveUrl);
        preg_match("/ROOMID\ =\ (\d+)/", $response->getBody(), $matchs);
        $playUrl = sprintf("https://live.bilibili.com/api/playurl?player=1&quality=0&cid=%s", $matchs[1]);
        $response = (new \GuzzleHttp\Client())->get($playUrl);
        preg_match("/<url><\!\[CDATA\[(.+?)\]\]><\/url>/", $response->getBody(), $matchs);
        return $matchs[1];
    }

    public static function playWithMpv($url)
    {
		$loop = \React\EventLoop\Factory::create();
        if (OsDetector::isMacOS()) {
            $command = sprintf("nohup ./osx/mpv '%s' > /dev/null 2>&1 &", $url);
        } else if (OsDetector::isWindows()) {
            $command = sprintf("./win64/mpv.exe '%s'", $url);
        }
        $process = new \React\ChildProcess\Process($command);
        $process->start($loop);
		$process->stdout->on('data', function ($chunk) {
			echo $chunk;
		});
		$loop->run();
    }
}

$application = new Application([
    'title' => 'BiliBili助手',
    'left' => 248,
    'top' => 50,
    'width' => 860,
    'height' => 600,
    'icon' => realpath(__DIR__) . DIRECTORY_SEPARATOR . 'bilibili.png'
]);
$application->on('start', function() use ($application) {
    $label = new Label([
        'text' => '直播:',
        'top' => 16,
        'fontSize' => 20,
        'left' => 80,
    ]);

    $label = new Label([
        'text' => '直播地址',
        'top' => 46,
        'fontSize' => 14,
        'left' => 100,
    ]);

	$liveUrlField = (new InputText())
			->setLeft(180)
			->setTop(46)
			->setWidth(500)
		;

	$livePlayButton = (new Button())
			->setLeft(700)
			->setTop(46)
			->setValue('播放')
			->setWidth(50);
    $livePlayButton->on('click', function() use ($application, $liveUrlField) {
        $liveUrl = $liveUrlField->getValue();
        Bilibili::playWithMpv(Bilibili::getLiveUrl($liveUrl));
    });

    $label = new Label([
        'text' => '点播:',
        'top' => 76,
        'fontSize' => 20,
        'left' => 80,
    ]);

    $label = new Label([
        'text' => '视频地址',
        'top' => 106,
        'fontSize' => 14,
        'left' => 100,
    ]);
	$videoUrlField = (new InputText())
			->setLeft(180)
			->setTop(106)
			->setWidth(500);
	$videoRequestButton = (new Button())
			->setLeft(700)
			->setTop(106)
			->setValue('获取地址')
			->setWidth(50);

	$videoSelectField = (new Select())
			->setLeft(180)
			->setTop(136)
			->setWidth(500);
	$videoPlayButton = (new Button())
			->setLeft(700)
			->setTop(136)
			->setValue('播放')
			->setWidth(50);

    $videoRequestButton->on('click', function() use ($application, $videoUrlField, $videoSelectField) {
        $videoUrl = $videoUrlField->getValue();
        Bilibili::$playUrls = Bilibili::getVideoUrl($videoUrl);
        $selectOptions = [];
        foreach(Bilibili::$playUrls as $index => $url) {
            $selectOptions[] = new Option("视频 #${index}", $index);
        }
        $videoSelectField->setOptions($selectOptions);
    });
    $videoPlayButton->on('click', function() use ($application, $videoSelectField) {
        $videoUrl = Bilibili::$playUrls[$videoSelectField->getChecked()];
        Bilibili::playWithMpv($videoUrl);
    });

});
$application->run();
