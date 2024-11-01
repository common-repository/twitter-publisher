<?php
	session_start();
	require_once('library/Rebelic_TwitterOauth.php');

	define("CONSUMER_KEY", "onN4JBqDVJ3VvpjrSEeZg");
	define("CONSUMER_SECRET", "5q95Es9p90TAcM45TM2ZzYiBbJBT4mJqhSf6Lw8UV7M");
	define('OAUTH_CALLBACK', 'http://rebelic.nl/wp-content/plugins/twitter-publisher/oauth_redirect.php');

	if(isset($_GET['oauth_token']))
    {

		/* Create TwitteroAuth object with app key/secret and token key/secret from default phase */
		$connection = new Rebelic_TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);

		/* Request access tokens from twitter */
		$access_token = $connection->getAccessToken($_REQUEST['oauth_verifier']);

		/* Save the access tokens. Normally these would be saved in a database for future use. */
		$_SESSION['access_token'] = $access_token;

		/* Remove no longer needed request tokens */
		unset($_SESSION['oauth_token']);
		unset($_SESSION['oauth_token_secret']);

		/* If HTTP response is 200 continue otherwise send to connect page to retry */
		if (200 == $connection->http_code) {
		  /* The user has been verified and the access tokens can be saved for future use */
		  $_SESSION['status'] = 'verified';

		  header('Location: '.$_SESSION['rebelic_redirect_url'].'&twipub_oauth_token='. $access_token['oauth_token'].'&twipub_oauth_token_secret='. $access_token['oauth_token_secret'].'&twipub_screen_name='. $access_token['screen_name']);


		} else {
		  /* Save HTTP status for error dialog on connnect page.*/
		  //header('Location: ./clearsessions.php');
		}


    	//header('Location: '.$_SESSION['rebelic_redirect_url'].'&'.implode('&', $uri));
		//exit;
    }
	else
    {
    	$_SESSION['rebelic_redirect_url'] = $_GET['rebelic_redirect_url'];

		try
		{
			/* Build TwitterOAuth object with client credentials. */
			$connection = new Rebelic_TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);

			/* Get temporary credentials. */
			$request_token = $connection->getRequestToken(OAUTH_CALLBACK);

			/* Save temporary credentials to session. */
			$_SESSION['oauth_token'] = $token = $request_token['oauth_token'];
			$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];

			/* If last connection failed don't display authorization link. */
			switch ($connection->http_code) {
			  case 200:
			    /* Build authorize URL and redirect user to Twitter. */
			    $url = $connection->getAuthorizeURL($token);
			    header('Location: ' . $url);
			    break;
			  default:
			    /* Show notification if something went wrong. */
			    echo 'Could not connect to Twitter. Refresh the page or try again later.';
			}
		}
		catch(OAuthException2 $e)
		{
			echo "Exception" . $e->getMessage();
		}
    }




?>