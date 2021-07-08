<?php

require '../vendor/autoload.php';
require '../helpers.php';
require '../Functions.php';

$configurationFile = __DIR__.'/../configuration.json';
$configurationJson = file_get_contents($configurationFile);

if (!$configurationJson)
{
  die("$configurationFile not found");
}

$configuration = json_decode($configurationJson);

$config = [
  
  'host'        => $configuration->host,

  'apiKey'      => $configuration->shopifyApiKey,
  'secret'      => $configuration->shopifySecret,
  'scope'       => $configuration->shopifyScope,

  'settings' => isset($configuration->settings) ?
    (array) $configuration->settings : []
];

// if (isset($_REQUEST['debug']))
// {
//   $result = (new Functions($configuration))->saveShopifyData(
//     'user1',
//     'domain.myshopify.com',
//     'sdafasdlkjhfljsd'
//   );
  
//   var_dump($result);
//   die();
// }

$app = new \Slim\App($config);

//debug only, delete this endpoint
$app->get('/test-get-all-usermeta', function ($request, $response, $args) {
  session_start();
  global $configuration;

  $arr = [];
  if (isset($_SESSION['username']))
  {
    $arr = (new Functions($configuration))->getAllUserMeta($_SESSION['username']);
  }
  return $response->withJSON($arr, 200, JSON_UNESCAPED_UNICODE);
});

$app->get('/getTelegramCredentials', function ($request, $response, $args) {
  session_start();

  global $configuration;

  $result = ['error'=>true, 'message'=>"Error getting $configuration->metaKey value"];

  $username = isset($_SESSION['username']) ? $_SESSION['username'] : null;

  if(isset($username))
  {
      $metaValue = 
        (new Functions($configuration))->getMetaKeyValue($username, $configuration->metaKey);

      if (!$metaValue)
      {
        $metaValue = '';
      }

      $result = ['error'=>false, 'metaKey'=> $configuration->metaKey, 'metaValue'=>$metaValue];
  }

  return $response->withJSON($result, 200, JSON_UNESCAPED_UNICODE);
});

$app->post('/setTelegramCredentials', function ($request, $response, $args) {
  session_start();

  global $configuration;
  $functions = (new Functions($configuration));
  $metaKey = $configuration->metaKey;

  $metaValue = $_REQUEST[$metaKey];
  $user = $functions->getUser($_SESSION['username']);
  
  $result = ['error'=>true, 'message' => "Error updating $metaKey"];

  if(isset($user) && isset($metaValue) && $metaValue != '')
  {

    $updated = $functions->updateMeta($user, $metaValue);
    if ($updated)
    {
      $result = ['error'=>false, $metaKey => $functions->getMetaKeyValue($user->login, $metaKey)];    
    }
  }

  return $response->withJSON($result, 200, JSON_UNESCAPED_UNICODE);
});

$app->post('/wpLogin', function ($request, $response, $args) {
  $result = [];
  session_start();
  if(isset($_POST['username']) && isset($_POST['password']))
  {
    global $configuration;
    
    $wpAuthEndpointUrl = 'http://localhost/wp-json/basic-auth/v1/check-auth';
    if (isset($configuration->wordPressAuthEndpointUrl))
    {
      $wpAuthEndpointUrl = $configuration->wordPressAuthEndpointUrl;
    }

    $apiAuth =
      (new Functions($configuration))->doWpJsonAuth(
        $_POST['username'],
        $_POST['password'],
        $wpAuthEndpointUrl);

    if($apiAuth['httpCode'] === 200 &&
      json_decode($apiAuth['response'])->code === 'authentication_success')
    {
        $_SESSION['username'] = $_POST['username'];
        $result = [
          'error'=>false,
          'message'=>'Logged in',
          'redirect' => '/authenticated'];
     } 
     else
     {
      $result = [
        'error'=>true,
        'message'=>'Invalid credentials'];
     }
  }
  else {
    $result = [
      'error'=>true,
      'message'=>'Username/password unset'];
  }

  return $response->withJSON($result, 200, JSON_UNESCAPED_UNICODE);
});

$app->get('/authenticated', function ($request, $response, $args)
{
  global $configuration;
  session_start();

  $result = ['error'=>false, 'authenticated'=>true];

  if(!isset($_SESSION['username']))
  {
      $result = ['error'=>false, 'authenticated'=>false];
  }

  if (isset($_SESSION['username']) && 
  isset($_SESSION['shop']) && 
  isset($_SESSION['accessToken']) &&
  !(new Functions($configuration))->saveShopifyData(
      $_SESSION['username'],
      $_SESSION['shop'],
      $_SESSION['accessToken']
    ))
  {
    $result = [
      'error'=>true,
      'authenticated'=>true,
      'message'=>'Error saving Shopify data'];
  }

  return $response->withJSON($result, 200, JSON_UNESCAPED_UNICODE);
});

/**
 * For future use, not called in spa.html
 */
// $app->get('/logout', function ($request, $response, $args)
// {
//   session_start();
//   unset($_SESSION['username']);
//   unset($_SESSION['shop']);
//   unset($_SESSION['accessToken']);
//   session_destroy();
  
//   $spaUrl = $this->router->pathFor('spa');
//   return $response->withRedirect($spaUrl);
// });

$app->get('/spa', function ($request, $response, $args)
{
  return $response->write(file_get_contents(__DIR__.'/spa.html'));
})->setName('spa');

/**
 * Install route
 * https://$configuration->host/shopify-wp/?shop=domain.myshopify.com
 */
$app->get('/', function ($request, $response, $args) {
  session_start();

  $apiKey = $this->get('apiKey');
  $host   = $this->get('host');
  $scope  = $this->get('scope');

  $shop = $request->getQueryParam('shop');

  if (!validateShopDomain($shop)) {
   return $response->getBody()->write("Invalid shop domain!");
  }

  $redirectUri = $host . $this->router->pathFor('oAuthCallback');
  $installUrl = "https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scope}&redirect_uri={$redirectUri}";

  return $response->withRedirect($installUrl);
});

/**
 * After successful installation shopify redirects to 
 * https://$configuration->host/shopify-wp/auth/shopify/callback
 */
$app->get('/auth/shopify/callback', function ($request, $response, $args) {

  global $configuration;
  session_start();

  $accessToken = "";

  $params = $request->getQueryParams();

  $accessToken = isset($params['accessToken']) ? 
    $params['accessToken'] : null;
  
  if (!$accessToken)
  {
    $apiKey = $this->get('apiKey');
    $secret = $this->get('secret');

    $validHmac = validateHmac($params, $secret);
    $validShop = validateShopDomain($params['shop']);

    if ($validHmac && $validShop)
    {
      $accessToken = getAccessToken($params['shop'], $apiKey, $secret, $params['code']);
    }
    else
    {
      return $response->getBody()->write("This request is NOT from Shopify!");
    }

  }
  else
  {
    //saving API key for the first time
    $_SESSION['shop'] = $params['shop'];
    $_SESSION['accessToken'] = $accessToken;
  }

  $dbAccessToken = null;
  if (isset($_SESSION['username']))
  {
    $dbAccessToken = (new Functions($configuration))->getShopifyAccessToken(
      $_SESSION['username']);
  }

  if ($accessToken != $dbAccessToken)
  {
    //each reinstall changes accessKey
    $_SESSION['shop'] = $params['shop'];
    $_SESSION['accessToken'] = $accessToken;
  }

  $host = $this->get('host');

  return $response->withRedirect($host . $this->router->pathFor('spa'));

})->setName('oAuthCallback');

$app->run();