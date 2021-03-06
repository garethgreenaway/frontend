<?php
/**
 * SkyDriveAPI.php class file
 *
 *REST reference (Live Connect)
 * http://msdn.microsoft.com/en-us/library/live/hh243648.aspx
 *
 * @author: Spiros Kabasakalis <kabasakalis@gmail.com>
 * @link http://iws.kabasakalis.gr/
 * @link http://www.reverbnation.com/spiroskabasakalis
 * @copyright Copyright &copy; Spiros Kabasakalis 2013
 * @license The MIT License
 * @package
 * @version 1.0
 */

class SkyDriveAPI
{


    /**
     * The access token array
     * This is a short lived (one hour) essential token included in every API call.
     * Stored in session and renewed using refresh token when it expires.
     * @var array
     * Array keys:  ([token_type],[expires_in],[scope],[access_token],[refresh_token],[authentication_token],[created])
     */
    private $access_token;
    /**
     * The Refresh token
     * Long lived  token (one year) used to get new access tokens when they expire with  refreshAccessToken function.
     *  Navigate to /skydrive/getcode and  sign in to Microsoft's login screen,to get a new refresh token.
     * if it is the first time you will be asked to authorize the application.
     * You can hard code it here or store in database.
     * (for demo purposes hard coded refresh token is used,modify for database stored refresh token)
     * @var string
     */
    private $refresh_token = '';

    /**
     * CLIENT_ID
     * @var string
     */
    private $client_id = '';
    /**
     * CLIENT_SECRET
     * @var string
     */
    private $client_secret = '';

    /**
     * SCOPE
     * Basic scopes wl.signin,wl.basic,wl.offline_access ,for complete list see
     * http://msdn.microsoft.com/en-us/library/live/hh243646.aspx
     * @var string
     */
    private $scope = 'wl.signin,wl.basic,wl.offline_access,wl.contacts_skydrive,wl.skydrive_update';
    /**
     * REDIRECT_URI
     * Used when obtaining an authorization code,must belong to the domain you registered in API Settings
     * of your application (https://manage.dev.live.com/Applications/Index)
     * @var string
     */
    private $redirect_uri = '[http://myhost.com/]';

    /**
     * AUTHORIZATION CODE
     * @var string
     */
    private $code = '';

    /**
     * AUTHORIZATION CODE FLAG
     * @var bool
     */
    private $getcode;

    private $session;

    /**
     * Microsoft API endpoints
     */
    const SKYDRIVE_API_BASE_URL = 'https://apis.live.net/v5.0/';
    const SKYDRIVE_AUTHORIZE_BASE_URL = 'https://login.live.com/oauth20_authorize.srf?';
    const SKYDRIVE_BASE_TOKEN_URL = 'https://login.live.com/oauth20_token.srf?';

    /**
     * Constructor
     * @param array $config_array  Use it to override default values for variables
     *
     */
    public function __construct($config_array = null)
    {

        if (!empty($config_array)) {
            foreach ($config_array as $key => $value) {
                $this->$key = $value;
            }
        }

        if (isset($_GET['code'])) $this->code = $_GET['code'];

        //if we are getting a new authorization code,skip the access token renewal
        //if (!$this->getcode) {
        //    if ($this->isAccessTokenExpired()) {
        //        $this->access_token = $this->refreshAccessToken();
        //        $this->session['access_token'] = $this->access_token;
        //    }
        //    ;
        //}
    }


    /**
     * isAccessTokenExpired
     *
     *  Checks to see if access token expires in next 30 secs or if it is unavailable in session.
     * @return bool returns true if the access_token is expired.
     */
    public function isAccessTokenExpired()
    {
        if (!isset($this->access_token)) {
            return true;
        }

        // If the token is set to expire in the next 30 seconds.
        $expired = ($this->access_token['created']
            + ($this->access_token['expires_in'] - 30)) < time();
        return $expired;
    }

    /**
     * setAccessToken
     *
     *  Sets the access token expires
     */
    public function setAccessToken($accessToken)
    {
        $this->access_token = $accessToken;
    }

    /**
     * getAccessToken
     *
     *  Gets the access token expires
     */
    public function getAccessToken()
    {
        return $this->access_token;
    }


    /**
     *  getAuthorizeUrl
  	 *
     * Return the Authorization URL
     * See OAuth2 http://msdn.microsoft.com/en-us/library/live/hh243647.aspx
     */
    public function getAuthorizeUrl()
    {
      $path = self::SKYDRIVE_AUTHORIZE_BASE_URL;
      
      $getParameters = array(
          'display' => 'page',
          'locale' => 'en',
          'client_id' => $this->client_id,
          'scope' => $this->scope,
          'response_type' => 'code',
          'redirect_uri' => $this->redirect_uri
      );
      return $path . http_build_query($getParameters, '', '&');      

    }
    
    /**
     *  getAuthorizationCode
     *
     * Starts the authorization code grant flow
     * See OAuth2 http://msdn.microsoft.com/en-us/library/live/hh243647.aspx
     */
    public function getAuthorizationCode()
    {
        $path = self::SKYDRIVE_AUTHORIZE_BASE_URL;
        $getParameters = array(
            'client_id' => $this->client_id,
            'scope' => $this->scope,
            'response_type' => 'code',
            'redirect_uri' => $this->redirect_uri
        );
        $this->skyDriveApiCall($path, EHttpClient::GET, $getParameters);
    }


    /**
     *  getAccessTokenFromCode
     *
     * Gets  a new access token using an authorization code.
     * See OAuth2 http://msdn.microsoft.com/en-us/library/live/hh243647.aspx
     *
     * @param bool $print Dump on screen or return the result
     * @return array $response_obj  the access token array:  ([token_type],[expires_in],[scope],[access_token],[refresh_token],[authentication_token],[created])
     */
    public function getAccessTokenFromCode($print = false)
    {
        error_log($this->client_id);
        $path = self::SKYDRIVE_BASE_TOKEN_URL;
        $parameters = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'client_secret' => $this->client_secret,
            'grant_type' => 'authorization_code',
            'code' => $this->code
        );
        $response_obj = $this->skyDriveApiCall($path, "POST", null, http_build_query($parameters, '', '&'), null);

        if ($print) {
            $this->print_response($response_obj);
            exit;
        } else
            return $response_obj;
    }


    /**
     *   refreshAccessToken
     *
     * Gets a new access token when the latter has  expired or it's  unavailable in session.
     * Refresh token could also be stored in database.Valid for one year.
     * Returns an array:
     *   ([token_type],[expires_in],[scope],[access_token],[refresh_token],[authentication_token],[created])
     * See OAuth2 http://msdn.microsoft.com/en-us/library/live/hh243647.aspx
     *
     * @return array $access_token
     */
    public function refreshAccessToken()
    {
        $path = self::SKYDRIVE_BASE_TOKEN_URL;
        $parameters = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'client_secret' => $this->client_secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refresh_token,
        );
        $response = $this->skyDriveApiCall($path, "POST", null, http_build_query($parameters, '', '&'), null);
        $access_token = (array)$response;
        $access_token['created'] = time();
        return $access_token;
    }


    /**
     *   skyDriveApiCall
     *
     * Core function of the class,makes all API calls to Skydrive.
     *
     * @param  string $path  the request URL
     * @param  string $method  the request method.GET,PUT,POST,DELETE.
     * @param array $getParameters GET  parameters array ( key=>value)
     * @param          $postbody  body for PUT and POST requests
     * @param   string $body_enctype encoding for request body
     *
     * @return stdClass $ $response_obj
     */
    public function skyDriveApiCall($path, $method = "GET", $parameters = null, $postbody = null, $postbodySize = null, $contentType = "application/x-www-form-urlencoded")
    {
        $ch = curl_init();
        
        // Ensure that the accessToken is included
        $access_token_parameter = array('access_token' => $this->access_token['access_token']);
        if (!empty($parameters)) {
          $Parameters = array_merge($parameters, $access_token_parameter);
        } else {
          $Parameters = $access_token_parameter;
        }
        curl_setopt($ch, CURLOPT_URL, $path . "?" . http_build_query($Parameters, '', '&'));
                    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FAILONERROR, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);  
        //For Debugging
        //curl_setopt($ch, CURLOPT_VERBOSE, TRUE);

        switch($method) {
        
          case "GET":
            //error_log("Attempting a GET");          
            break;

          case "DELETE":
            //error_log("Attempting a GET");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");                     
            break;
          
          case "POST":
            //error_log("Attempting a POST");          
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postbody);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type: $contentType",
            ));
            break;
          
          case "PUT":
            error_log("Attempting a PUT");
				    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
				    curl_setopt($ch, CURLOPT_PUT, TRUE);
				    curl_setopt($ch, CURLOPT_INFILE, $postbody);
				    curl_setopt($ch, CURLOPT_INFILESIZE, $postbodySize);
            break;
        }        
        
        $output = curl_exec($ch);
        //error_log($output);
        $response = json_decode($output);
        
        return $response;    
    }


    /**
     * upload
     *
     * Upload files  to Skydrive.
     * returns stdClass object with id,name,and source(download link)  properties of the
     * newly uploaded file.
     *
     * @param  string $file_path  the path of the file to be uploaded to Skydrive.
     * @param  string $filename The name of the file in Skydrive (can be different than the original) -extension included.
     * @param array $folder_id   ID of the  destination folder.
     * @param bool  $print  Dump response on screen?
     *
     * @return stdClass $ $response_obj
     */
    public function upload($file_path, $filename, $folder_id, $print = false)
    {
        getLogger()->warn("upload params: " . $file_path . " " . $filename . " " . $folder_id);

        $path = self::SKYDRIVE_API_BASE_URL . $folder_id . '/files/' . $filename;
        
        // Clears file status cache
        clearstatcache();
        $bodySize = filesize($file_path);
        getLogger()->warn("Bodysize $bodySize");
                
        $body = fopen($file_path, "r");
        $response = $this->skyDriveApiCall($path, "PUT", null, $body, $bodySize);
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }


    /**
     * download
     *
     * Download  files from  Skydrive.
     * returns stdClass object with  download link  (location property) of the file
     *
     * @param  string $fileID  the id of the file to download  from Skydrive.
     * @param  bool  $returnDownloadLink
     * @param bool  $print  Dump response on screen?
     *
     * @return stdClass $ $response_obj
     */
    public function download($fileID, $returnDownloadLink = true, $print = false)
    {

        $path = self::SKYDRIVE_API_BASE_URL . $fileID . '/content';
        if ($returnDownloadLink) {
            $getParameters = array(
                'suppress_redirects' => 'true',
                'download' => 'false',                
            );
        } else {
            $path = self::SKYDRIVE_API_BASE_URL . $fileID . '/content';
            $getParameters = array(
                'download' => 'true',
            );            
        }

        $response = $this->skyDriveApiCall($path, "GET", $getParameters, null);        
        error_log("inside download $path ". print_r($response,1));
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return ($returnDownloadLink) ? $response->location : $response;
    }


    /**
     * updateFileProperties
     *
     * @param  string $fileID  the id of the file to update.
     * @param  array  $properties  array of key=>value pairs for updated properties  (http://msdn.microsoft.com/en-us/library/live/hh243648.aspx)
     * @param bool  $print  Dump response on screen?
     *
     * @return stdClass $ $response_obj
     */
    public function updateFileProperties($fileID, $properties, $print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . $fileID;
        $response = $this->skyDriveApiCall($path, EHttpClient::PUT, null, json_encode($properties), '');
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }


    /**
     *  getObjectByID
     *
     * Get an object from SkyDrive
     * returns stdClass object
     *
     * @param  string $object_ID  the id of the object to get.Object can be anything in SkyDrive with an ID.
     * @param bool  $print  Dump response on screen?
     *
     * @return stdClass $ $response_obj
     */
    public function getObjectByID($object_ID, $print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . $object_ID;
        $response = $this->skyDriveApiCall($path);
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }




    /**
      *  getFileByName
      *
      * Get a file  from SkyDrive with the specified name,contained in a specified folder.
      * returns stdClass object
      *
      * @param  string $fileName  the filename,extension included.
      * @param  string  $parentID  the parent folder ID
      * @param bool  $print  Dump response on screen?
      *
      * @return stdClass  $file_found
      */
    	public function getFileByName($fileName, $parentID = null,$print=false)
    	{
        $file_found=null;
    		$result = $this->getFolderFilesByID($parentID,null,false);
    		$files = $result->data;
    		foreach($files as $file) {
  		    error_log("$fileName " . $file->name);    		
    			if($file->name == $fileName) {
            $file_found = $file;
    			}
    		}
        if ($print) {
          $this->print_response($file_found);
          exit;
        } else {
          return $file_found;
        }
    	}


    /**
     *  deleteFileByID
     *
     * Delete an object from SkyDrive
     *
     * @param  string $object_ID  the id of the object to delete.Object can be anything in SkyDrive with an ID.
     * @param  bool  $print  Dump response on screen?
     *
     * @return stdClass $ $response
     */
    public function deleteFileByID($object_ID, $print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . $object_ID;
        $response = $this->skyDriveApiCall($path, "DELETE");
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }


    /**
     *  getAlbumsOfUser
     *
     * @param  string $user_ID  the id of the user
     * @param  string $querystring  filter results with a query,ex 'filter=videos,audio'.See http://msdn.microsoft.com/en-us/library/live/hh243648.aspx
     * @param bool  $print  Dump response on screen?
     *
     * @return stdClass $ $response
     */
    public function getAlbumsOfUser($user_ID, $querystring = null, $print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . $user_ID . '/albums';
        if (!empty($querystring)) $path = $path . '?' . $querystring;
        $response = $this->skyDriveApiCall($path);
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }


    /**
     *  getMe
     *
     * @param bool  $print  Dump response on screen?
     *
     * @return stdClass  $response
     */
    public function getMe($print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . '/me';
        $response = $this->skyDriveApiCall($path);
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }


    /**
     *  getStorageInfo
     *
     * Available and quota storage
     *
     * @param bool  $print  Dump response on screen?
     * @return stdClass  $response
     */
    public function  getStorageInfo($print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . 'me/skydrive/quota';
        $response = $this->skyDriveApiCall($path);
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }


    /**
     * getMyAlbums
     *
     *  Folders with photos
     *
     * @param  string $querystring  filter results with a query,See http://msdn.microsoft.com/en-us/library/live/hh243648.aspx
     * @param bool  $print  Dump response on screen?
     * @return stdClass  $response
     */
    public function getMyAlbums($querystring = null, $print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . 'me/albums';
        if (!empty($querystring)) $path = $path . '?' . $querystring;
        $response = $this->skyDriveApiCall($path);

        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }


    /**
     *getPhotosOfAlbum
     *
     * @param string $album_ID
     * @param  string $querystring  filter results with a query,ex 'filter=videos,audio'.See http://msdn.microsoft.com/en-us/library/live/hh243648.aspx
     * @param bool  $print  Dump response on screen?
     * @return stdClass $response
     */
    public function getPhotosOfAlbum($album_ID, $querystring = null, $print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . $album_ID . '/files';

        if (!empty($querystring)) $path = $path . '?' . $querystring;
        $response = $this->skyDriveApiCall($path);
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }

    /**
     *getFolderFilesByID
     *
     * @param string $folder_ID the  ID of the folder containing the files
     * @param  string $querystring  filter results with a query,ex 'filter=videos,audio'.See http://msdn.microsoft.com/en-us/library/live/hh243648.aspx
     * @param bool  $print  Dump response on screen?
     * @return stdClass  $response
     */
    public function getFolderFilesByID($folder_ID, $querystring = null, $print = false)
    {
        error_log("call to getFolderFilesByID " . $folder_ID);
        $path = self::SKYDRIVE_API_BASE_URL . $folder_ID . '/files';
        error_log("call to getFolderFilesByID " . $path);        
        if (!empty($querystring)) $path = $path . '?' . $querystring;
        $response = $this->skyDriveApiCall($path);
        //error_log("call to getFolderFilesByID " . print_r($response, 1));
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }


    /**
     *createFolder
     *
     * @param string $parentFolderID  the  ID of the parent folder
     * @param  string $name the folder name
     * @param  string  $description  folder description
     * @param bool  $print  Dump response on screen?
     * @return stdClass $response
     */
    public function createFolder($parentFolderID, $name, $description, $print = false)
    {

        error_log("Calling createFolder: $parentFolderID $name");


        $postbody = json_encode(array('name' => $name, 'description' => $description));
        $path = self::SKYDRIVE_API_BASE_URL . $parentFolderID;
        $response = $this->skyDriveApiCall($path, "POST", null, $postbody, null, 'application/json');

        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;

    }


    /**
     *getMyRootFolders
     *
     * @param  string $querystring  filter results with a query,ex 'filter=videos,audio'.See http://msdn.microsoft.com/en-us/library/live/hh243648.aspx
     * @param bool  $print  Dump response on screen?
     * @return stdClass  $response
     */
    public function getMyRootFolders($querystring = null, $print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . 'me/skydrive/files';
        if (!empty($querystring)) $path = $path . '?' . $querystring;
        $response = $this->skyDriveApiCall($path);
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }

    /**
     *getMyRoot
     *
     * @param bool  $print  Dump response on screen?
     * @return stdClass  $response
     */
    public function getMyRoot($print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . 'me/skydrive/';
        $response = $this->skyDriveApiCall($path);
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }

    /**
     *getMySharedFiles
     *
     * @param  string $querystring  filter results with a query,ex 'filter=videos,audio'.See http://msdn.microsoft.com/en-us/library/live/hh243648.aspx
     * @param bool  $print  Dump response on screen?
     * @return stdClass $response
     */
    public function getMySharedFiles($querystring = null, $print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . 'me/skydrive/shared/files';
        if (!empty($querystring)) $path = $path . '?' . $querystring;
        $response = $this->skyDriveApiCall($path);
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }

    /**
     *getCommentsByObjectID
     *
     * @param string $objectID  id of the commented object
     * @param  string $querystring  filter results with a query,ex 'filter=videos,audio'.See http://msdn.microsoft.com/en-us/library/live/hh243648.aspx
     * @param bool  $print  Dump response on screen?
     * @return stdClass  $response
     */
    public function getCommentsByObjectID($objectID, $querystring = null, $print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . $objectID . '/comments';

        if (!empty($querystring)) $path = $path . '?' . $querystring;
        $response = $this->skyDriveApiCall($path);
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }


    /**
     *getPermissions
     *
     * @return stdClass $response
     */
    public function getPermissions($print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . 'me/permissions';
        if (!empty($querystring)) $path = $path . '?' . $querystring;
        $response = $this->skyDriveApiCall($path);
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }


    /**
     *   search
     *
     *   Find objects with the specified text supplied by the q parameter
     *
     * @param  string $querystring  filter results with a query,ex 'filter=videos,audio'.See http://msdn.microsoft.com/en-us/library/live/hh243648.aspx
     * @param bool  $print  Dump response on screen?
     * @return stdClass  $response
     */
    public function search($querystring, $print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . '/me/skydrive/search';
        if (!empty($querystring)) $path = $path . '?q=' . $querystring;
        $response = $this->skyDriveApiCall($path);
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }

    /**
     *   getTags
     *
     *   Find tags for object with id objectID
     * @param string  $objectID the object id
     * @param bool  $print  Dump response on screen?
     * @return stdClass  $response
     */
    public function getTags($objectID, $print = false)
    {
        $path = self::SKYDRIVE_API_BASE_URL . $objectID . '/tags';
        $response = $this->skyDriveApiCall($path);
        if ($print) {
            $this->print_response($response);
            exit;
        } else
            return $response;
    }


    private function getRefreshTokenMessage()
    {
        echo('<div style="text-align:center;"><h1>SKYDRIVE</h1>');
        echo('<div style="text-align:center;"><h2>GET A NEW REFRESH TOKEN</h2>');
        echo('<p>The new refresh token is valid up to a year.<br>');
        echo('Your app will automatically call the refreshToken function of SkyDriveAPI class<br>');
        echo('when a valid access token is unavailable in session or has expired.<br>');
        echo('The  current SkyDriveController reads a hard coded refresh token,<br>');
        echo('it can also be stored and read  from a database.</p></div>');
    }

    private function print_response($response)
    {
        echo '<pre>';
        print_r($response);
        echo('</pre>');
    }

}

?>
