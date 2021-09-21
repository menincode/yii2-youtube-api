<?php
namespace menincode\youtubeApi\widgets;

use yii\web\AssetBundle;

class YoutubeIFrameAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@vendor/menincode/youtubeApi/widgets/';
    public $css = [
    ];
    public $js = [
        'https://www.youtube.com/iframe_api'
    ];
    public $jsOptions = [
        'async' => 'async',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap\BootstrapAsset',
    ];
}