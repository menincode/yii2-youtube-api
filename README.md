# Youtube Api for yii2

Youtube api is a yii2 extension to use youtube api as a yii2 component.

this extension access youtube api via oauth, saving the access token and refresh it when expire, so you have to give credentials just once

This extension also contains methods for partners, so you don't have to put your onBehalfContentOwner every single call, and control the youtube partner calls per minute to avoid overflow of limit call per minute

With this extension you can
* upload and update a videos
* upload a thumbnail for a video
* list of videos
* create and update playlists
* get ,add and remove elements for a playlist
* searches
* Put the player

Also can do partener actions like
* get channels
* monetize/unmonetize videos


## Installation
The preferred way to install this extension is through composer.

```$ composer require menincode/yii2-youtube-api```

## Usage
Add it to your components 

### add to your components
```
'components' => [
    ...
    'youtube' => [
        'class' => \menincode\youtubeApi\components\YoutubeApi::className(),
        'clientId' => '{your Oauth Client Id, you can get it from google console}',
        'clientSecret' => '{your Oauth Client Secret, you can get it from google console}',
        'setAccessTokenFunction' => function($client){ file_put_contents('pathFile.txt'json_encode($client->getAccessToken());}, //anonymous function where save the accesToken
        'getAccessTokenFunction' => function(){ return file_get_contents('pathFile.txt');}, // an anonymous function where get the accessToken 
        'scopes' => ['{scopes that you going to use}', '{as array}'],
    ],
    ...
]
```

```setAccessTokenFunction``` and ```getAccessTokenFunction``` are so important, in one you gonna save your accessToken and another let the component take it.

In the example above, the access token was save it in txt, and in te function to take it return the content of that file. 

It's important that you ```setAccessTokenFunction``` has one parameter (```$client``` for example) and always save only ```json_encode($client->getAccessToken)```.

### generate your access token

An advantage of this component is that you only have to generate your access token once.

Create an action in any controller 

```
public function actionValidation() {
    if(Yii::$app->request->get('code')){
        Yii::$app->youtube->validationPost(Yii::$app->urlManager->createAbsoluteUrl('/site/validation'));
    } else {
        Yii::$app->session->setFlash('success', 'The access token was generated');
        return $this->redirect('index');
    }
}
```

You can call your action as you want, when has a GET parameter called 'code', you must call ```Yii::$app->youtube->validationPost('{url_to_this_action}')``` , this method create and save the access token
and redirect to the url passed as parameter.

To get this action you must do it through ```Yii::$app->youtube->validationGet(Yii::$app->urlManager->createAbsoluteUrl({url_to_action}))```, you can use it for example in a ```<a>``` tag

for example

```echo Html::a('Validar', Yii::$app->youtube->validationGet(Yii::$app->urlManager->createAbsoluteUrl('/site/validation')) )```

The access token'll be saved and you can use the component

### Example

```Yii::$app->youtube->setParts(['snippet', 'recordingDetails', 'id'])->listVideos(['id' => 'someId'])```

You can pass in ```setParts()```, the parts that you want, if you don't want the default parts. For more information of every method and how do it, read the PhpDOC of component's methods

### Partner

if you want to use the extension as a partner you must indicate your ```onBehalfContentOwner``` in your config

```
'youtube' => [
    'class' => \sr1871\youtubeApi\components\YoutubeApi::className(),
    'clientId' => '{your Oauth Client Id, you can get it from google console}',
    'clientSecret' => '{your Oauth Client Secret, you can get it from google console}',
    'setAccessTokenFunction' => function($client){ file_put_contents('pathFile.txt'json_encode($client->getAccessToken());}, //anonymous function where save the accesToken
    'getAccessTokenFunction' => function(){ return file_get_contents('pathFile.txt');}, // an anonymous function where get the accessToken 
    'scopes' => ['{scopes that you going to use}', '{as array}'],
    'onBehalfContentOwner' => {your_content_owner},
    'youtubePartnerCallsPerSecond' => 2 //you can indicate how many calls per second can you do, default is 2
],
```

If you don't know your ```onBehalfContentOwner``` you can get it with ``` getOnBehalfOfContentOwner() ``` method

### Player

This extension include a player in widget format

```
\sr1871\youtubeApi\widgets\YoutubeIFrame::widget([
    'id' => 'SomeIdForDivAndJSObject'
    'iFrameOptions' => [
        'videoId' => 'someId'
        ...
    ],
    'iFrameEvents' => [
        'onReady' => 'function(event) {
            console.log('ready')
        }'
    ],
    'options' => [] //html div options
])
```