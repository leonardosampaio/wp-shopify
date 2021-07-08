<?php

// Helper method to determine if a shop domain is valid
function validateShopDomain($shop) {
  $substring = explode('.', $shop);

  // 'blah.myshopify.com'
  if (count($substring) != 3) {
    return FALSE;
  }

  // allow dashes and alphanumberic characters
  $substring[0] = str_replace('-', '', $substring[0]);
  return (ctype_alnum($substring[0]) && $substring[1] . '.' . $substring[2] == 'myshopify.com');
}

// Helper method to determine if a request is valid
function validateHmac($params, $secret) {
  $hmac = $params['hmac'];
  unset($params['hmac']);
  ksort($params);

  $computedHmac = hash_hmac('sha256', http_build_query($params), $secret);

  return hash_equals($hmac, $computedHmac);
}

// Helper method for exchanging credentials
function getAccessToken($shop, $apiKey, $secret, $code) {
  $query = array(
  	'client_id' => $apiKey,
  	'client_secret' => $secret,
  	'code' => $code
  );

  // Build access token URL
  $access_token_url = "https://{$shop}/admin/oauth/access_token";

  // Configure curl client and execute request
  $curl = curl_init();
  $curlOptions = array(
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_URL => $access_token_url,
    CURLOPT_POSTFIELDS => http_build_query($query)
  );
  curl_setopt_array($curl, $curlOptions);
  $jsonResponse = json_decode(curl_exec($curl), TRUE);
  curl_close($curl);

  return $jsonResponse['access_token'];
}