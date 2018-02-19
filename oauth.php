<?php
/**
 * This script handles the user-wise authentication and query of Google Contacts 
 *
 * TODO Copyright 2000-2018 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you did
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author Matthew Sharinghousen <matthew.sharinghousen@uni.kn>
 */

require_once __DIR__ . '/lib/Google/vendor/autoload.php';
require_once __DIR__ . '/lib/Application.php';
Horde_Registry::appInit('turba');

$vars = Horde_Variables::getDefaultVariables();
$vars->set('source', key($addSources));
$source = $vars->get('source');

// TODO Add a check here for the "Google Contacts" book, if no, then make. Pass as source.

/* A source has been selected, connect. */
if ($source) {
    try {
        $driver = $injector->getInstance('Turba_Factory_Driver')->create($source);
    } catch (Turba_Exception $e) {
        $notification->push($e, 'horde.error');
        $driver = null;
    }
}

$access_token = $driver->getToken($driver, 'oauth_access'); 

$client = new Google_Client();
$client->setAuthConfigFile('client_secrets.json');
$client->setRedirectUri('http://' . $_SERVER['HTTP_HOST'] . '/turba/oauth.php');
$client->addScope(Google_Service_People::CONTACTS_READONLY);

//TODO: Add check for token expiration, access is valid for 1 hour
if (!empty($access_token)) {
  $accessToken = unserialize($access_token[0]);
  $syncToken = $driver->getToken($driver, 'oauth_sync')[0];

  //TODO: This list can probably be refined
  $personFields = 'addresses,ageRanges,biographies,birthdays,emailAddresses,locales,names,nicknames,occupations,organizations,phoneNumbers,relations,residences,skills,urls';

  $client->setAccessToken($accessToken);
  $connect = new Google_Service_PeopleService($client);

  do {
    // Meat and potatoes - this is where the contacts are queried, and response contains contacts
    $connections = $connect->people_connections->
	  listPeopleConnections('people/me', 
	  array('pageToken' => $nextPageToken,
	        'pageSize' => 23, // Increasing size will increase server load/runtime, but reduce number of requests 
	        'personFields' => $personFields,
	        'requestSyncToken' => True,
	        'syncToken' => $syncToken));

    $nextPageToken = $connections->nextPageToken;
    $syncToken = $connections->nextSyncToken;

  } while ($nextPageToken != '');  

  // Store sync token to save state
  $driver->setToken($driver, $syncToken, 'oauth_sync'); 

  // Let the user know of success
  $notification->push(sprintf(_("Google Contacts have been synced")), 'horde.success');

  $redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . '/turba';
  header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
} else {
  if (! isset($_GET['code'])) {
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
  } else {
    $client->authenticate($_GET['code']);
    $accessToken = serialize($client->getAccessToken());

    $driver->setToken($driver, $accessToken, 'oauth_access');

    $redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . '/turba/oauth.php';
    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
  }
}
?>
