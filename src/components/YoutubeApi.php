<?php
namespace menincode\youtubeApi\components;

use Yii;
use yii\base\Component;
use GuzzleHttp\Client;

/**
 * Component to use youtube api with google validation
 */
class YoutubeApi extends GoogleApi {

  /**
   * @var Google_Service_YouTube youtube object
   */
  private $youtube;

  /**
   * @var string 
   * in some methods you need to indicate the parts that you pass or you want to obtain, you can modified the default parts through this variable
   * to use it @see setParts method
   * to know what parts you can pass or obtain, check the documentation of each method.
   */
  private $parts;

  /**
   * @var Google_Service_YouTubePArtner youtubePartner object
   * Content ID api has a rated call limited per second, to avoid overflow that limit, this attribute only must be use via youtubePartner()
   * @see youtubePartner()
   */
  private $youtubePartner;

  /**
   * @var int
   * Content ID api has a rated call limited per second, this component control the number of calls per second to avoid overflow that limit.
   * You have to specify the number of calls per second enable, to know how many calls you can do, check your project console
   */
  public $youtubePartnerCallsPerSecond = 2;

  /**
   * @var int
   * Number of calls make in this second
   */
  private $youtubePartnerCountCalls = 0;
  
  /**
   * @var int timestamp
   * the current second where the calls will be counted
   */
  private $youtubePartnerNow = 0;

  /**
   * Init the authorization of GoogleApi, the youtube object and set the scopes.
   */  
  public function init() {

    parent::init();
    // $this->client->setScopes('https://www.googleapis.com/auth/youtubepartner');
    $this->youtube = new \Google_Service_YouTube($this->client);
  }

  /**
   * Set the parts in case that the method use parts and you want override the default
   *
   * to know what parts you can pass or obtain, check the documentation of each method.
   * if the method doesn't use parts, don't use this method or can cause error in others methods.
   * this method return itself, so you can concatenate the actions Yii::$app->youtube->setParts(['part1', 'part2'])->otherMethod() ...
   *
   * @param array $parts, an array with the parts you want to pass or obtain
   * @return YoutubeApi return itself so you can concatenate methods.
   */
  public function setParts($parts) {
    $this->parts = is_array($parts) ? implode(',', $parts) : null;
    return $this;
  }

  /**
   * check if there are parts for use or use the default
   * If the parts were used it were cleaned to avoid to use them again
   *
   * @param string $defaultParts the default parts to pass in case configurable parts are empty.
   * @return string return manual configurated parts or use the default in case thern't manual parts
   */
  private function getParts($defaultParts) {
    $parts = $this->parts ?? $defaultParts;
    $this->parts = null;
    return $parts;
  }

  /**
   * Return your onBehalfOfContentOwner
   * This method can help you if you don't know your onBehalfOfContentOwner
   * This method is just informative, you must call it once and save it in configuration array.
   *
   * @return string onBehalfOfContentOwner
   */
  public function getOnBehalfOfContentOwner() {
    if(empty($this->onBehalfOfContentOwner)) {
      $youtubePartner = new \Google_Service_YouTubePartner($this->client);
      $contentOwnersListResponse = $youtubePartner->contentOwners->listContentOwners(['fetchMine' => true]);
      if(!isset($contentOwnersListResponse[0])) {
        throw new  \Exception("You aren't a partner, you don't have a onBehalfOfContentOwner defined");
      }
      return $contentOwnersListResponse[0]->id;
    }
    return $this->onBehalfOfContentOwner;
  }


  /**
   * combine the onBehalfOfContentOwner requiried in partner account with the opcional parameters
   *
   * To use this, you must put $isPartner variable as true,
   * you must define your onBehalfOfContentOwner in config
   * if you don't know what is your onBelhafOfContentOwner, @see getOnBehalfOfContentOwner
   * if you are not partner the optional parms will send without onBehalfOfContentOwner
   *
   * @param optParams array opcional params partner. In every call the opt will be differents
   * @return array with partner data if $isPartner is set to true
   */
  private function partnerData($optParams = []) {
    if($this->isPartner) {
      return array_merge(['onBehalfOfContentOwner' => $this->onBehalfOfContentOwner], $optParams);
    }
    return $optParams;
  }

  /**
   * {@inheritdoc}
   */
  public function validationGet($redirectUrl, $scopes = NULL) {
    return parent::validationGet( $redirectUrl, $scopes ?? implode(' ', $this->scopes) );
  }

  /**
   * Load attribute from array to object
   * 
   * @param $object Object the object where the attributes will be loaded
   * @param $attributes array an array that contains the attributes, ['{attributeName}' => '{attributeValue}']
   * @return void
   */
  private function loadAttributes(&$object, $attributes) {
    foreach ($attributes as $functionName => $attribute) {
      $object->{'set'.ucfirst($functionName)}($attribute);
    }
  }

  /**
   * Create a object and fill its attributes
   * 
   * @param $object Object the new object where the attributes will be loaded
   * @param $attributes array an array that contains the attributes, ['{attributeName}' => '{attributeValue}']
   * @return mixed the object passed with attributes loaded
   */
  private function createLoadedClass($class, $attributes) {
    $object = new $class();
    $this->loadAttributes($object, $attributes);
    return $object;
  }

  /**
   * Upload a video to youtube
   * 
   * @param $videoPath path of video, this path will be relative path.
   * @param $snippetData array an array with attributesName as key, and attributeValue as value of snippet ['{attributeName}' => '{attributeValue}']
   * @param $statusData array an array with attributesName as key, and attributeValue as value of snippet ['{attributeName}' => '{attributeValue}']
   * @param $optParams opcional params, to know wich params you can pass @link https://developers.google.com/youtube/v3/docs/videos/insert?hl=es-419#parmetros
   * @return array the data of video uploaded
   */
  public function uploadVideo($videoPath, $snippetData, $statusData, $optParams = []) {

    $video = $this->createLoadedClass('\Google_Service_YouTube_Video', [
      'snippet' => $this->createLoadedClass('\Google_Service_YouTube_VideoSnippet', $snippetData), 
      'status' => $this->createLoadedClass('\Google_Service_YouTube_VideoStatus',  $statusData)
    ]);

    $this->client->setDefer(true);
    $insertRequest = $this->youtube->videos->insert( $this->getParts('status,snippet'), $video, $this->partnerData($optParams));
    return $this->uploadFile($insertRequest, 'video/*', $videoPath);
  }

  /**
   * Update a youtube video
   * 
   * @param $videoId the youtube ID
   * @param $snippetData array an array with attributesName as key, and attributeValue as value of snippet ['{attributeName}' => '{attributeValue}']
   * @param $statusData array an array with attributesName as key, and attributeValue as value of snippet ['{attributeName}' => '{attributeValue}']
   * @return array data of video updated.
   */
  public function updateVideo($videoId, $snippetData, $statusData = []) {
    $listResponse = $this->youtube->videos->listVideos($this->getParts('snippet, status'), ['id' => $videoId]);

    if (empty($listResponse)) {
      $htmlBody .= sprintf('<h3>Can\'t find a video with video id: %s</h3>', $videoId);
    } else {
      $video = $listResponse[0];

      $snippet = $video->snippet;
      $this->loadAttributes($snippet, $snippetData);

      $status = $video->status;
      $this->loadAttributes($status, $statusData);

      $updateResponse = $this->youtube->videos->update("snippet, status", $video);

      return $updateResponse;
    }
  }

  /**
   * get a list with info videos with the specified parameters
   * It's necessary for google pass at least one filter, so that the reason optParams has at least one parameter and is not optionally
   *
   * @param array $optParams  array to know which params you can pass @link https://developers.google.com/youtube/v3/docs/videos/list?hl=es-419#parmetros
   * @return VideoListResponse with the video info
   */
  public function listVideos($optParams) {
    return $this->youtube->videos->listVideos($this->getParts('snippet, contentDetails'), $this->partnerData($optParams));
  }

  /**
   * Upload File to youtube
   * @param $request mixed an google request make by google object
   * @param $type String type of file
   * @param $filePath String path of file
   * @return array the status of the uploaded file
   */
  private function uploadFile($request, $type, $filePath) {
    $chunkSizeBytes = 1 * 1024 * 1024;

    $media = new \Google_Http_MediaFileUpload(
      $this->client,
      $request,
      $type,
      null,
      true,
      $chunkSizeBytes
    );
    $media->setFileSize(filesize($filePath));
    $status = false;
    $handle = fopen($filePath, "rb");
    while (!$status && !feof($handle)) {
      $chunk = fread($handle, $chunkSizeBytes);
      $status = $media->nextChunk($chunk);
    }
    fclose($handle);

    $this->client->setDefer(false);

    return $status;
  }

  /**
   * Upload a thumbnail
   *
   * @param $videoId Youtube ID of the video to upload thumbnail
   * @param $imagePath Path of the image
   * @return array data of thumnbail uploaded 
   */
  public function uploadThumbnail($videoId, $imagePath) {
    $this->client->setDefer(true);
    $setRequest = $this->youtube->thumbnails->set($videoId, $this->partnerData());

    return $this->uploadFile($setRequest, 'image/png', $imagePath);
  }

  /**
   * Create playlist
   *
   * @param $playlistData array an array with attributesName as key, and attributeValue as value of snippet ['{attributeName}' => '{attributeValue}']
   * @param $statusData array an array with attributesName as key, and attributeValue as value of snippet ['{attributeName}' => '{attributeValue}']
   * @param $optParams optional params in insert, its common used when has a partner youtube
   * @return array data of playlist
   */
  public function createPlaylist($playlistData, $statusData, $optParams = []) {

    $playlistSnippet = $this->createLoadedClass('\Google_Service_YouTube_PlaylistSnippet', $playlistData);
    $playlistStatus = $this->createLoadedClass('\Google_Service_YouTube_PlaylistStatus', $statusData);
    $youTubePlaylist = $this->createLoadedClass('\Google_Service_YouTube_Playlist', ['snippet' => $playlistSnippet, 'status' => $playlistStatus]);

    return $this->youtube->playlists->insert('snippet,status', $youTubePlaylist, $this->partnerData($optParams));
  }

  /**
   * Update playlist
   *
   * @param $playlistId String youtube id of playlist
   * @param $playlistData array an array with attributesName as key, and attributeValue as value of snippet ['{attributeName}' => '{attributeValue}']
   * @param $statusData array an array with attributesName as key, and attributeValue as value of snippet ['{attributeName}' => '{attributeValue}']
   * @return array data of updated playlist
   */
  public function updatePlaylist($playlistId, $playlistData, $statusData = []) {

    $youTubePlaylist = $this->youtube->playlists->listPlaylists('snippet, status', $this->partnerData(['id' => $playlistId, 'maxResults' => 50]))[0];

    $playlistSnippet = $youTubePlaylist->snippet;
    $this->loadAttributes($playlistSnippet, $playlistData);

    $playlistStatus = $youTubePlaylist->status;
    $this->loadAttributes($playlistStatus, $statusData);

    $this->loadAttributes($youTubePlaylist, ['snippet' => $playlistSnippet, 'status' => $playlistStatus]);

    return $this->youtube->playlists->update('snippet,status', $youTubePlaylist, $this->partnerData());
  }

  /**
   * Returns the items of a playlist
   *
   * @param $playlistId string youtube id of playlist
   * @param $optParams optional params in insert, its common used when has a partner youtube
   * @return array items of playlist
   */
  public function getPlaylistItems($playlistId, $optParams = []) {
    $playlistItemsResponse = $this->youtube->playlistItems->listPlaylistItems($this->getParts('snippet'), array_merge([
      'playlistId' => $playlistId,
      'maxResults' => 50,
    ], $this->partnerData($optParams)));

    return $playlistItemsResponse;
  }

  /**
   * Add a item to playlist
   * 
   * @param $resourceData array an array with attributesName as key, and attributeValue as value of snippet ['{attributeName}' => '{attributeValue}']
   * @param $playlistItemSnippetData array an array with attributesName as key, and attributeValue as value of snippet ['{attributeName}' => '{attributeValue}']
   * @return array data of item added
   */
  public function addPlaylistItem($resourceData, $playlistItemSnippetData) {
    $resourceId = $this->createLoadedClass('\Google_Service_YouTube_ResourceId', $resourceData);
    $playlistItemSnippet = $this->createLoadedClass('\Google_Service_YouTube_PlaylistItemSnippet', array_merge($playlistItemSnippetData, ['resourceId' => $resourceId]));
    $playlistItem = $this->createLoadedClass('\Google_Service_YouTube_PlaylistItem', ['snippet' => $playlistItemSnippet]);

    return $this->youtube->playlistItems->insert($this->getParts('snippet,contentDetails'), $playlistItem, $this->partnerData());
  }

  /**
   * update a playlist item
   *
   * @param $playlistId String youtube id of playlist
   * @param $videoId youtube id of video
   * @param $playlistItemSnippetData array an array with attributesName as key, and attributeValue as value of snippet ['{attributeName}' => '{attributeValue}']
   * @return array data of item updated
   */
  public function updatePlaylistItem($playlistId, $videoId,$playlistItemSnippetData = []) {
    $playlistItem = $this->youtube->playlistItems->listPlaylistItems('snippet', $this->partnerData([
      'playlistId' => $playlistId,
      'videoId' => $videoId,
      'maxResults' => 50,
    ]))[0];
    
    $playlistItemSnippet = $playlistItem['snippet'];
    $this->loadAttributes($playlistItemSnippet, $playlistItemSnippetData);

    $playlistItem->setSnippet($playlistItemSnippet);
    return $this->youtube->playlistItems->update('snippet,contentDetails', $playlistItem, $this->partnerData());
  }

  /**
   * Delete item from playlist
   *
   * @param $itemId id of item
   * @return array data of deleted item
   */
  public function deletePlaylistItem($itemId) {
    return $this->youtube->playlistItems->delete($itemId, $this->partnerData());
  }
  /**
   * Get all the channels
   * It's necessary for google pass at least one filter, so that the reason optParams has at least one parameter and is not optionally
   * 
   * @param $optParams array to know which params you can pass @link https://developers.google.com/youtube/v3/docs/channels/list?hl=es-419#parmetros
   * @return Google_Service_YouTube_ChannelListResponse  a list with all channels with the specific optional params
   */
  public function getChannels($optParams) {
    return $this->youtube->channels->listChannels($this->getParts('snippet'), $this->partnerData($optParams));
  }

  /**
   * Get a list of elements with the specific parameters
   * It's necessary for google pass at least one filter, so that the reason optParams has at least one parameter and is not optionally
   * You can get a list of videos, channels, playlist with the optional params type, if you are partner this type is required
   * 
   * @param $optParams array to know which params you can pass @link https://developers.google.com/youtube/v3/docs/search/list#parmetros
   * @return Google_Service_YouTube_SearchListResponse  a elements list  with the specific optional params
   */
  public function search($optParams) {
    return $this->youtube->search->listSearch($this->getParts('id,snippet'), $this->partnerData($optParams));
  }

  /**
   * Init the youtubePartner object
   * This method must be called in the begining of every method that use youtubePartner
   * Throw an exception if isPartner is not true and if onBehalfOfContentOwner is empty
   *
   * @return void
   */
  private function initPartner() {
    if(!$this->isPartner || empty($this->onBehalfOfContentOwner)) {
      throw new  \Exception("This method is only allowed to partners, set partner attribute to true and add your onBehalfOfContentOwner");
    }
    if(!$this->youtubePartner){
      $this->youtubePartner = new \Google_Service_YouTubePartner($this->client);
    }
  }

  /**
   * Get the youtubePartner Object
   * The youtubePartner attribute mustn't be call directly, it be call via this method to control the calls per second.
   *
   * @return Google_Service_YouTubePartner youtubePartner object
   */
  private function youtubePartner() {
    if($this->youtubePartnerCountCalls >= $this->youtubePartnerCallsPerSecond) {
      while($this->youtubePartnerNow >= time());
      $this->youtubePartnerCountCalls = 0;
    }
    $this->youtubePartnerNow = time();
    $this->youtubePartnerCountCalls++;

    return $this->youtubePartner;
  }

  /**
   * Add o remove the policy of monetize
   * Additional to monetize, this method create or update the asset, the claim and the advertising options
   * To monetize you have to pass a monetize policy, you can create your own policy or select one via id with the method policy() @see function policy()
   * To avoid the extra call and overflow Content ID API, many objects are surronded by if's
   *
   * @param string $videoId youtube id
   * @param array $optParams configurations of the necessary components
   *   @optParam array $metadataAssetData required in creation, optional in update. MetadataAsset information
   *   @optParam array $assetData required in creation, optional in update. Metadata information
   *   @optParam array $ownersData required in creation, optional in update. owners information
   *   @optParam array $claimData required in creation, optional in update. claim information, you have to pass the policy in this array ['policy' => {Policy_Object}]
   *   @optParam array $videoAdvertisingOptions required in creation, optional in update. advertising options
   * @return array return the current claim and advertising options of the video.
   */

  public function monetizeVideo($videoId, $optParams) {

    $this->initPartner();
    extract($optParams);

    $claim = ($claimSearch =  $this->youtubePartner()->claimSearch->listClaimSearch(['onBehalfOfContentOwner' => $this->onBehalfOfContentOwner, 'videoId' => $videoId])) != null 
      && isset($claimSearch->items[0]) ?
        $this->youtubePartner()->claims->get($claimSearch->items[0]->id, $this->partnerData()) :
        new \Google_Service_YouTubePartner_Claim();

    if(isset($metadataAssetData) || isset($assetData)) {
      $metadataAsset = !empty($metadataAssetData) ? ['metadata' => $this->createLoadedClass('\Google_Service_YouTubePartner_Metadata', $metadataAssetData)] : [] ;
      if($claim->assetId) {
        $asset = $this->youtubePartner()->assets->get($claim->assetId, $this->partnerData());
        $this->loadAttributes($asset, array_merge($assetData ?? [], $metadataAsset));
        $assetInsertResponse = $this->youtubePartner()->assets->update($asset->id, $asset, $this->partnerData());
      } else {
        $asset = $this->createLoadedClass('\Google_Service_YouTubePartner_Asset', array_merge($assetData, $metadataAsset));
        $assetInsertResponse = $this->youtubePartner()->assets->insert($asset, $this->partnerData());
      }
      $assetId = $assetInsertResponse->id;
    }

    if(isset($ownersData)) {
      $owners = $this->createLoadedClass('\Google_Service_YouTubePartner_TerritoryOwners', array_merge($ownersData, ['owner' => $this->onBehalfOfContentOwner]));
      $ownership = $this->createLoadedClass('\Google_Service_YouTubePartner_RightsOwnership', ['general' => [$owners]]);

      $ownershipUpdateResponse = $this->youtubePartner()->ownership->update($assetId, $ownership, $this->partnerData());
    }
    
    if(isset($claimData)) {
      if($claim->id){ 
        $this->loadAttributes($claim,$claimData);
        $claimResponse = $this->youtubePartner()->claims->update($claim->id, $claim,  $this->partnerData());
      } else {
        
        $this->loadAttributes($claim, array_merge( compact('assetId', 'videoId'), $claimData));
        $claimResponse = $this->youtubePartner()->claims->insert($claim,  $this->partnerData());
      }
    }
    
    $option = $this->youtubePartner()->videoAdvertisingOptions->get($videoId, $this->partnerData()) ?? new \Google_Service_YouTubePartner_VideoAdvertisingOption();
    if(isset($videoAdvertisingOptionsData)) {
      $this->loadAttributes($option,$videoAdvertisingOptionsData);
      $setAdvertisingResponse = $this->youtubePartner()->videoAdvertisingOptions->update($videoId, $option, $this->partnerData());
    }

    return ['claim' => $claimResponse ?? $claim, 'videoAdvertisingOptions' => $setAdvertisingResponse ?? $option];
  }


  /**
   * Get o create a policy
   * If you want to create a policy you have to put id = NULL and pass the policy options in policyData parameter
   * If you want to get a policy just pass the id
   *
   * @param int id id of policy to get, if you want to create a new, put it NULL
   * @param $policyData array data of policy, this parameter is only required when you want to create a new policy
   *  you have to pass the rules in array [ 'rules' => [[{data_rule}], [{another_data_rule}]]]
   * @return Google_Service_YouTubePartner_Policy policy
   */
  public function policy($id, $policyData = []) {
    $youtubePartner = $this->initPartner();
    $policy = $id ? $this->youtubePartner()->policies->get($id, $this->partnerData()) : new \Google_Service_YouTubePartner_Policy();
    if(isset($policyData['rules'])) {
      $policyData ['rules'] = array_map(function($policyRuleData) {return $this->createLoadedClass('Google_Service_YouTubePartner_PolicyRule', $policyRuleData);}, $policyData['rules']);
    }
    $this->loadAttributes($policy, $policyData);

    return $policy;
  }
}