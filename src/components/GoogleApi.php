<?php
namespace sr1871\youtubeApi\components;

use Yii;
use yii\base\Component;
use GuzzleHttp\Client;

/**
 * Component for the authentication of google's apis
 *
 * To assign first time the permissions you will go to validationGet method url,
 * This method ask a url that redirect after select the google account, that url will has the validationPost
 */
class GoogleApi extends Component {

  /**
   * @var String application name in google console 
   */
  public $applicationName;

  /**
   * @var String oAuth clientId assigned in google console
   */
  public $clientId;

  /**
   * @var String oAuth clientId assigned in google console
   */
  public $clientSecret;

  /**
   * @var function an anonymous function that return the content of the AccessToken
   *
   * This component needs a google authorization, instead of doing manually every time, this component save it -  @see setAccessTokenFunction
   * This function must obtain the content of that authorization
   * function(){ 
   *    return file_get_contents('file_where_save_it_e_g.txt');
   * }
   */
  public $getAccessTokenFunction;

  /**
   * @var function an anonymous function that keep the content of the AccessToken
   *
   * This component needs a google authorization, instead of doing manually every time, this component save it with this function
   * This function always must has a parameter that will be the google client Class,
   * the client content a method getAccessToken() where you obtain the AccessToken to save.
   * It's important that the application doesn't have yet google account's permission yet, for give permission - @see validationGet
   * function($client) {
   *    file_put_contents('file_where_save_it_e_g.txt');
   * }
   */
  public $setAccessTokenFunction;

  /**
   * @var array with scopes that you want pass
   */
  public $scopes = [];

  /**
   * @var boolean if it's a partner account
   */
  public $isPartner = false;

  /**
   * @var String contentOwner.
   *
   * If you are parnet you can keep your contentOwner here for faster accesibility
   * If you don't know your contentOwner you have use a google partner account.
   * You must set partner true in configuration to use it.
   */
  public $onBehalfOfContentOwner = '';

  /**
   * @var Google_Client a Google client
   */
  protected $client;
  
  /**
   * init the component and the authorization
   *
   * This authorization put the accessToken in the google client, and refresh it if was expired.
   */
  public function init() {

    parent::init();

    try{

      $this->client = new \Google_Client();
      $this->client->setClientId($this->clientId);
      $this->client->setClientSecret($this->clientSecret);
      $this->client->setAccessType('offline');
      if(call_user_func($this->getAccessTokenFunction)){
        $this->client->setAccessToken(($this->getAccessTokenFunction)());
        if($this->client->isAccessTokenExpired()) {
          $newToken = $this->client->getAccessToken();
          $this->client->refreshToken($newToken['refresh_token']);
          ($this->setAccessTokenFunction)($this->client);
        }
      }

      foreach ($this->scopes as $scope) {
        $this->client->setScopes($scope);
      }

    } catch (Exception $ex) {
        throw new Exception("Error al conectar con google");
    }
  }

  /**
   * Assign permissions to application from google account manually
   * 
   * This validation select the account that give permission to application
   * This methos will be override in every component to assigned the corresponding scopes
   *
   * @param $redirectUrl String url to redirect after select the google acount
   * @param $scope String google scopes neccesary, this will be assigned in override method
   * @return String url that redirect to google account selection
   */
  protected function validationGet($redirectUrl, $scope) {
    return 'https://accounts.google.com/o/oauth2/auth?'
      .'client_id='.$this->clientId
      .'&redirect_uri='.$redirectUrl
      .'&scope='.$scope
      .'&response_type=code&access_type=offline';
  }
  /**
   * Call first time for the google AccessToken and save it
   *
   * This methos will be call in an action, after validationGet
   *
   * @param $redirectUrl String url to redirect after call and save the accessToken
   * @return redirection to the $redirectUrl param.
   */
  public function validationPost($redirectUrl) {
    if(Yii::$app->request->get('code')) {
      $client = new Client();
 
      $response = $client->request('POST', 'https://accounts.google.com/o/oauth2/token', [
          'form_params' => [
            'code' => Yii::$app->request->get('code'),
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $redirectUrl,
            'grant_type' => 'authorization_code'
          ]
      ]);
      $content = $response->getBody()->getContents();
      if(strpos($content, 'refresh_token') === false) {
          throw new  \Exception("La respuesta no contiene el refresh_token, ingresa a https://myaccount.google.com/u/0/permissions para eliminarlos y vuelve a entrar");
      }
      $this->client->setAccessToken($content);
      ($this->setAccessTokenFunction)($this->client);
      Yii::$app->response->redirect($redirectUrl);
      
    }
  }
}