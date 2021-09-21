<?php
namespace menincode\youtubeApi\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\{Html,Json};
use yii\web\JsExpression;


class YoutubeIFrame extends Widget
{
    /**
     * @var int id in div and name of js object
     */
    public $id = 'player';

    /**
     * @var array options of youtube iframe except events, events must be in iFrameEvents array @link https://developers.google.com/youtube/player_parameters?hl=es-419
     */
    public $iFrameOptions;

    /**
     * @var array yo must be every event you want to add as array, e.g. 'onReady' => 'function(e) => {...}', or  'onReady' => 'nameOfExistingFunctionWithOutParenthesis' this widget convert it as expression
     */
    public $iFrameEvents = [];

    /**
     * @var array Html options for the div, you don't need to add the id options, this will add through id variable
     */
    public $options = [];
    
    public function init()
    {
        $this->iFrameOptions['events'] = array_map(function($event){return new JsExpression($event);}, $this->iFrameEvents);
        $this->options['id'] = $this->id;
        parent::init();
    }

    public function run()
    {   

        $this->registerClientScript();
        return Html::tag('div', '', $this->options);
    }

    /**
     * create the js object
     */
    private function registerClientScript() {
        $view = $this->getView();
        $view->registerJs(
            "let $this->id;

            function onYouTubeIframeAPIReady() {
              $this->id = new YT.Player('$this->id', ".Json::encode($this->iFrameOptions).");
            }", \yii\web\View::POS_HEAD
        );
        
         YoutubeIFrameAsset::register($view);
    }

}
