<?php

require_once 'Const.php';
require_once 'InstApiException.php';


class InstApi {
    // Variables
    protected $username;            // Inst username
    protected $password;            // Inst password
    protected $guid;                // GUID
    protected $device_id;           // Device ID
    protected $agent;               // User Agent
    protected $cookies;             // Data cookies
    protected $CookiesDataPath;     // Data storage path
    protected $InstagramUrl;        // Instagram Api url
    /**
    * Default class constructor.
    *
    * @param string $username
    *   Instagram username.
    * @param string $password
    *   Instagram password.
    * @param $CookiesDataPath
    *  Default folder to store data, you can change it.
    */
    public function __construct($username, $password, $CookiesDataPath = null)
    {
        $this->InstagramUrl = 'https://instagram.com/api/v1/';
        $this->username = $username;
        $this->password = $password;
        $this->guid = $this->GenerateGuid();
        $this->device_id = "android-" . $this->guid;
        $this->agent = $this->GenerateUserAgent();

        $data = (object)array(
            'device_id' => $this->device_id,
            'guid' => $this->guid,
            'username' => $this->username,
            'password' => $this->password,
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        );
        $data = json_encode($data);

        $sig = $this->GenerateSignature($data);
        $data = 'signed_body=' . $sig . '.' . urlencode($data) . '&ig_sig_key_version=4';

        $login = SendRequest('accounts/login/', true, $data, false);
     
        if (strpos($login[1], "Sorry, an error occurred while processing this request.")) {
            throw new InstApiException("Request failed, there's a chance that this proxy/ip is blocked\n"); 
        }
     
        if (empty($login[1])) {
            throw new InstApiException("Empty response received from the server while trying to login\n"); 
        }
        $obj = @json_decode($login[1], true);
     
        if (empty($obj)) {
            throw new InstApiException("Could not decode the response\n"); 
        }
        $status = $obj['status'];
     
        if ($status != 'ok') {
            throw new InstApiException("Login failed\n"); 
        }
    }
 /**
   * Generate user agent.
   *
   * @return string
   */
    protected function GenerateUserAgent(){
        $resolutions = array('720x1280', '320x480', '480x800', '1024x768', '1280x720', '768x1024', '480x320');
        $versions = array('GT-N7000', 'SM-N9000', 'GT-I9220', 'GT-I9100');
        $dpis = array('120', '160', '320', '240');
     
        $ver = $versions[array_rand($versions)];
        $dpi = $dpis[array_rand($dpis)];
        $res = $resolutions[array_rand($resolutions)];
     
        return 'Instagram 4.'.mt_rand(1,2).'.'.mt_rand(0,2).' Android ('.mt_rand(10,11).'/'.mt_rand(1,3).'.'.mt_rand(3,5).'.'.mt_rand(0,5).'; '.$dpi.'; '.$res.'; samsung; '.$ver.'; '.$ver.'; smdkc210; en_US)';
    }
 /**
   * Generate guid.
   *
   * @return string
   */
    protected function GenerateGuid(){
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(16384, 20479),
            mt_rand(32768, 49151),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535));
    } 
/**
   * Generate signature.
   * @param string $data
   *
   * @return string
   */
    protected function GenerateSignature($data){
        return hash_hmac('sha256', $data, 'b4a23f5e39b5929e0666ac5de94c89d1618a2916');
    }
/**
   * Generate signature.
   * @param string $filename
   *
   * @return string
   */
    protected function GetPostData($filename){
        if(!$filename) {
            throw new InstApiException("The image doesn't exist ".$filename."\n"); 
        } else {
            $post_data = array('device_timestamp' => time(),
                'photo' => '@'.$filename);
            return $post_data;
        }
    }
/**
   * Send request.
   * @param string $url
   *
   * @return array
   */
    protected function SendRequest($url, $post, $post_data, $cookies){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->InstagramUrl . $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
     
        if($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
     
        if($cookies) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->CookiesDataPath . $this->username . '-cookies.dat');
        } else {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->CookiesDataPath . $this->username . '-cookies.dat');
        }
     
        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
     
        return array($http, $response);
    }
/**
   * Search user.
   * @param string $url
   *
   * @return JSON[].<object user fields> 
   */
    public function searchUser($username)
    {
        $get = $this->SendRequest('users/search?q=' . $username, false, false, $this->agent, true);
        if (empty($get[1])) {
            throw new InstApiException("Empty response received from the server while trying to search user\n"); 
        }
        $obj = @json_decode($get[1], true);          
        if (empty($obj)) {
            throw new InstApiException("Could not decode the response\n"); 
        }
        $status = $obj['status'];
     
        if ($status != 'ok') {
            throw new InstApiException("Status isn't okay\n"); 
        }
        
        return $obj['users'];
    }
/**
   * Folow.
   * @param int $user_id
   *
   * @return boolean 
   */
    public function folowInstagram($user_id)
    {
        $data = (object)array(
            'device_id' => $this->device_id,
            'guid' => $this->guid,
            'user_id' => $user_id,
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        );
        $data = json_encode($data);

        $sig = $this->GenerateSignature($data);
        $data = 'signed_body=' . $sig . '.' . urlencode($data) . '&ig_sig_key_version=4';
        
        $post = $this->SendRequest("friendships/create/{$user_id}/", true, $data, true);
        
        $obj = @json_decode($post[1], true);
     
        if (empty($obj)) {
            throw new InstApiException("Could not decode the response\n"); 
        }
        $status = $obj['status'];

        if ($status != 'ok') {
            throw new InstApiException("Fail\n"); 
        }
        return true;
    }
/**
   * Comment media.
   * @param int $media_id
   * @param string $comment
   *
   * @return boolean 
   */
    public function commentMedia($media_id, $comment)
    {
        $data = (object)array(
            'device_id' => $this->device_id,
            'guid' => $this->guid,
            'comment_text' => $comment,
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        );
        $data = json_encode($data);
        
        $sig = GenerateSignature($data);
        $data = 'signed_body=' . $sig . '.' . urlencode($data) . '&ig_sig_key_version=4';

        $post = SendRequest("media/{$media_id}/comment/", true, $data, $agent, true);
        
        $obj = @json_decode($post[1], true);
        
        if (empty($obj)) {
            throw new InstApiException("Could not decode the response\n"); 
        }
        $status = $obj['status'];
     
        if ($status != 'ok') {
            throw new InstApiException("Status isn't okay\n"); 
        }
        return true;
    }
/**
   * Send file to instagram.
   * @param string $filename
   * @param string $caption
   *
   * @return boolean 
   */
    public function sendInstagram($filename, $caption)
    {
        $data = $this->GetPostData($filename);
        $post = $this->SendRequest('media/upload/', true, $data, true);

        if (empty($post[1])) {
            throw new InstApiException("Empty response received from the server while trying to post the image\n");
        }
        $obj = @json_decode($post[1], true);
     
        if (empty($obj)) {
            throw new InstApiException("Could not decode the response\n");
        }
        $status = $obj['status'];
     
        if ($status != 'ok') {
            throw new InstApiException("Status isn't okay\n");
        }

        $media_id = $obj['media_id'];
        $device_id = "android-" . $guid;
     
        $data = (object)array(
            'device_id' => $this->device_id,
            'guid' => $this->guid,
            'media_id' => $media_id,
            'caption' => trim($caption),
            'device_timestamp' => time(),
            'source_type' => '5',
            'filter_type' => '0',
            'extra' => '{}',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        );
        $data = json_encode($data);

        $sig = $this->GenerateSignature($data);
        $data = 'signed_body=' . $sig . '.' . urlencode($data) . '&ig_sig_key_version=4';

        $conf = $this->SendRequest('media/configure/', true, $data, true);
     
        if (empty($conf[1])) {
            throw new InstApiException("Empty response received from the server while trying to configure the image\n");
        } else {
            if (strpos($conf[1], "login_required")) {
                throw new InstApiException("You are not logged in. There's a chance that the account is banned\n");
            } else {
                $obj = @json_decode($conf[1], true);
                $status = $obj['status'];
                if ($status == 'fail') {
                    throw new InstApiException("Fail\n");
                }
            }
        }
        return true;
    }
}
