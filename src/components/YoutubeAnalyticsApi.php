<?php
namespace sr1871\youtubeApi\components;

use Yii;
use yii\base\Component;
use GuzzleHttp\Client;

/**
 * Component to use youtube api with google validation
 */
class YoutubeAnalyticsApi extends GoogleApi {

  /**
   * @var Google_Service_youtube youtube object
   */
  private $youtubeAnalytics;

  /**
   * Init the authorization of GoogleApi, the youtube object and set the scopes.
   */
  public function init() {
    parent::init();
    $this->youtubeAnalytics = new \Google_Service_YoutubeAnalytics($this->client);
  }
  
  public function query($optParams = []) {
    return $this->youtubeAnalytics->reports->query(array_merge($optParams, ['ids' => 'contentOwner=='.$this->onBehalfOfContentOwner]));
  } 
}