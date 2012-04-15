<?php

/**
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

if (!function_exists('curl_init')) {
    throw new Exception('Picovico needs the CURL PHP extension.');
}

if (!function_exists('json_decode')) {
    throw new Exception('Picovico needs the JSON PHP extension.');
}

/**
 * The Picovico configuration class
 */
class Picovico_Config{

    // HTTP GET/POST method
    const API_GET = "get";
    const API_POST = "post";

    // Incomplete Video - Not Uploaded yet (for API use only)
    const VIDEO_STATUS_INCOMPLETE = -1;
    // Failed Video
    const VIDEO_STATUS_FAILED = 0;
    // Queued Video-
    const VIDEO_STATUS_QUEUED = 1;
    // Processing Video
    const VIDEO_STATUS_PROCESSING = 2;
    // Deferred Video
    const VIDEO_STATUS_DEFERRED = 3;
    // Rendering Video
    const VIDEO_STATUS_RENDERING = 4;
    // Complete Video
    const VIDEO_STATUS_COMPLETE = 5;

    // video sizes
    const VIDEO_SIZE_90     = 90;
    const VIDEO_SIZE_360    = 360;
    const VIDEO_SIZE_480    = 480;    

    // Frames
    const FRAME_TYPE_TEXT = "text_frame";
    const FRAME_TYPE_IMAGE = "image_frame";

    private static $PV_config;
    private static $PV_config_loaded;
    
    public static function _init(){
        if(isset(self::$PV_config_loaded) AND self::$PV_config_loaded == TRUE){
            // do nothing
        }else{

            // mention the necessary configuration
            $pv_config_video_status = array();
            $pv_config_video_status["1-"] = "Video is INCOMPLETE and has not been queued yet.";
            $pv_config_video_status["0"] = "FAILED to receive the requested video.";
            $pv_config_video_status["1"] = "Requested video has been QUEUED for processing.";
            $pv_config_video_status["2"] = "Requested video is currently under PROCESSING.";
            $pv_config_video_status["3"] = "Requested video has been DEFERRED by Picovico.";
            $pv_config_video_status["4"] = "Requested video is under RENDERING process.";

            $pv_config_api = array();
            $pv_config_api["get_themes"] = "themes/";
            $pv_config_api["get_theme"] = "themes/";
            $pv_config_api["get_video"] = "video/";
            $pv_config_api["create_video"] = "create/";

            $pv_config = array();

            $pv_config["api"] = $pv_config_api;
            $pv_config["api_base"] = "https://api.picovico.com/";
            
            $pv_config["video_status"] = $pv_config_video_status;

            /** Do not change */
            self::$PV_config = $pv_config;
            self::$PV_config_loaded = TRUE;
        }
    }

    public static function get($var = null){
        if(($var)){
            return self::$PV_config[$var];
        }else{
            return self::$PV_config;
        }
    }

    public static function get_api_config($var = null){
        if(($var)){
            return self::$PV_config["api"][$var];
        }else{
            return self::$PV_config["api"];
        }
    }
    
    public static function get_api_base(){
        return self::$PV_config["api_base"];
    }

}

// Initialize the configuration
Picovico_Config::_init();

/**
 * The Picovico way of handling Execptions
 *
 * @author acpmasquerade <acpmasquerade@picovico.com>
 */
class Picovico_Exception extends Exception {

    /**
     * The result from the API server that represents the exception information.
     */
    protected $result;
    protected $type; 

    public function __construct($result) {

        $this->type = $result;
        
        if(is_string($result)){
            parent::__construct($result);
        }elseif(is_numeric ($result)){
            parent::__construct(NULL, $result);
        }else{
            // do something else            
            $result = $this->to_array($result);
            if(isset($result["type"]) AND $result["type"] ){
                $this->type = $result["type"];
            }else{
                $this->type = "Picovico_Exception";
            }
            parent::__construct($this->type);
        }

        $this->result = $result;
    }

    private function to_array($var){
        if(is_array($var)){
            return $var;
        }elseif(is_object($var)){
            $return = array();
            foreach($var as $key=>$val){
                $return[$key] = $val;
            }
            return $return;
        }else{
            return array($var);
        }
    }

    /**
     * Return the associated result object returned by the API server.
     *
     * @return array The result from the API server
     */
    public function getResult() {
        return $this->result;
    }

    /**
     * Returns the associated type for the error. This will default to
     * 'UFO_Exception' when a type is not available.
     *
     * @return string
     */
    public function getType() {
        if(!isset($this->type) OR !($this->type)){
            return 'UFO_Exception';
        }else{
            return $this->type;
        }
    }

    /**
     * @return string The string representation of the error
     */
    public function __toString() {
        return "".$this->type;
    }

}

/**
 * Provides access to the Picovico Application Platform
 * 
 * @author acpmasquerade <acpmasquerade@picovico.com>
 */
class Picovico {
    /**
     * Version.
     */
    const API_VERSION = '0.1alpha';
    const VERSION = '0.1alpha';

    /**
     * Default options for curl.
     */
    public static $CURL_OPTIONS = array(
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'Picovico-php-0.1alpha',
        CURLOPT_SSL_VERIFYPEER => FALSE,
    );
    /**
     * The access_token
     *
     * @var string
     */
    protected $access_token = null;

    /**
     * Initialize a Picovico Application.
     *
     * @param array $config The application configuration
     */
    public function __construct($config) {
        if (isset($config["access_token"])) {
            $this->set_access_token($config["access_token"]);
        }
    }

    /**
     * Sets the access token for API calls.
     *
     * @param string $access_token an access token.
     * @return Picovico
     */
    public function set_access_token($access_token) {
        $this->access_token = $access_token;
        return $this;
    }

    /**
     * Determines the access token that should be used for API calls.
     *
     * @return string The access_token
     */
    public function get_access_token() {
        return $this->access_token;
    }

    /**
     * Makes an HTTP request. The CURL way
     *
     * @param string $url The URL to make the request to
     * @param array $params The parameters to use for the GET/POST body
     * @param string $method GET or POST
     *
     * @return string The response text
     */
    protected function make_request($url, $params = array(), $method = Picovico_Config::API_POST) {

        $curl_handler = curl_init();
        
        $options = self::$CURL_OPTIONS;

        if(!$params){
            $params = array();
        }

        // force the access token
        $params["access_token"] = $this->get_access_token();

        $curl_request_params_string = http_build_query($params, null, '&');

        if($method == Picovico_Config::API_POST){
            $options[CURLOPT_POSTFIELDS] = $curl_request_params_string;
            $options[CURLOPT_URL] = $url;
        }else{
            $curl_request_url_parts = parse_url($url."?");
            if(isset($curl_request_url_parts["query"])){
                $curl_request_url = $url . "&" . $curl_request_params_string;
            }else{
                $curl_request_url = $url . "?" . $curl_request_params_string;
            }
            $options[CURLOPT_URL] = $curl_request_url;
        }

        curl_setopt_array($curl_handler, $options);
        
        $result = curl_exec($curl_handler);

        if ($result === FALSE) {
            $e = new Picovico_Exception(array(
                        "type"=>"CurlException",
                        'message' => curl_error($curl_handler),
                        'code' => curl_errno($curl_handler)
                    ));
            curl_close($curl_handler);
            throw $e;
        }
        curl_close($curl_handler);
        return $result;
    }

    /**
     * Makes HTTP POST/GET request, and json_decodes the response
     * @param <type> $url
     * @param <type> $params
     * @param <type> $method
     */
    protected function make_json_request($url, $params = array(), $method = Picovico_Config::API_POST) {
        $json_response = $this->make_request($url, $params, $method);
        return json_decode($json_response, TRUE);
    }

    /**
     * Build the URL for api given parameters.
     *
     * @param $method String the method name.
     * @return string The URL for the given parameters
     */
    protected function get_api_url($method) {
        return Picovico_Config::get_api_base().Picovico_Config::get_api_config($method);
    }

    /**
     * Returns the Current URL,
     *
     * @return string The current URL
     */
    protected function get_current_url() {
        if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)
                || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
        ) {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }
        $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $parts = parse_url($currentUrl);
        
        // use port if non default
        $port =
                isset($parts['port']) &&
                (($protocol === 'http://' && $parts['port'] !== 80) ||
                ($protocol === 'https://' && $parts['port'] !== 443)) ? ':' . $parts['port'] : '';

        // rebuild
        
        return $protocol . $parts['host'] . $port . $parts['path'] . $query;
    }

    /**
     * Analyzes the supplied result to see if it was thrown
     * because the access token is no longer valid.  If that is
     * the case, then the persistent store is cleared.
     *
     * @param $result array A record storing the error message returned
     *                      by a failed API call.
     */
    protected function throw_api_exception($result) {
        $e = new Picovico_Exception($result);
        throw $e;
    }

    /**
     * Prints to the error log if you aren't in command line mode.
     *
     * @param string $msg Log message
     */
    protected static function error_log($msg) {
        // disable error log if we are running in a CLI environment
        if (php_sapi_name() != 'cli') {
            error_log($msg);
        }
    }

    /**
     * Dumps a readable output for a variable
     * 
     * @param <type> $var variable to debug
     */
    public static function debug($var){
        if (php_sapi_name() != 'cli') {
            echo "<pre>";
        }
        print_r($var);
        
        die();
    }

    /**
     * Destroy the current session
     */
    public function destroy_session() {
        $this->set_access_token(null);
    }
}


/**
 * Picovico Themes API wrapper
 *
 * @author acpmasquerade <acpmasquerade@picovico.com>
 */
class Picovico_Theme extends Picovico{

    private $properties;
    
    function  __construct($config = array()) {
        parent::__construct($config);
    }

    /**
     * @return <string> Human Readable name of the theme
     */
    function get_name(){
        return $this->properties["name"];
    }

    /**
     * @return <string> Machine Identifier name of the theme
     */
    function get_machine_name(){
        return $this->properties["machine_name"];
    }

    /**
     * @return <string> The theme description
     */
    function get_description(){
        return $this->properties["description"];
    }

    /**
     * @return <string> Video URL for a sample video created using the theme
     */
    function get_sample_url(){
        return $this->properties["sample_url"];
    }

    /**
     * @return <string> Theme thumbnail
     */
    function get_thumbnail(){
        return $this->properties["thumbnail"];
    }

    /**
     * @param <array> Set the theme properties. 
     */
    private function set_properties($properties){
        $this->properties = $properties;
    }

    /**
     *
     * @return <array> The theme properties as defined by Picovico
     */
    function get_properties(){
        return $this->properties;
    }

    /**
     *
     * @param <Picovico_Theme> $response - JSON decoded response array from themes
     */
    function create_object_from_response($response){
        $this->set_properties($response);
        return $this;
    }

    /**
     * 
     * @return <array> Array of Picovico_Theme (s)
     */
    public function get_available_themes(){
        $url = $this->get_api_url("get_themes");
        $response = $this->make_json_request($url, array(), Picovico_Config::API_GET);

        $themes = $response["themes"];

        $themes_objects_array = array();
        foreach($themes as $t){
            $pv_theme = new Picovico_Theme();
            $pv_theme->create_object_from_response($t);
            $themes_objects_array[] = $pv_theme;
        }

        return $themes_objects_array;
    }

    /**
     * Fetches a Picovico theme for the machine_name
     * 
     * @param <string> $theme_machine_name - The machine name identifier for the theme
     *
     * @return Picovico_Theme if available, otherwise returns NULL
     */
    public function get_theme($theme_machine_name){
        $url = $this->get_api_url("get_themes");
        $response = $this->make_json_request($url, array(), Picovico_Config::API_GET);

        $themes = $response["themes"];

        foreach($themes as $t){
            if($t["machine_name"] == $theme_machine_name){
                return $this->create_object_from_response($t);
            }
        }

        return NULL;
    }

    /**
     * For any available Picovico_Theme machine_name, creats a dummy object
     * This is helpful when the application doesn't want to do the actual fetching,
     * and has some way to cache the list of available videos. 
     * 
     * @param <string> $theme_machine_name - One of the available machine names
     * @return <Picovico_Theme> - A dummy theme with only the machine_name set 
     */
    public static function new_dummy_theme($theme_machine_name){
        $dummy_theme = new Picovico_Theme();
        $dummy_theme_properties = array();
        $dummy_theme_properties["machine_name"] = $theme_machine_name;

        $dummy_theme->set_properties($dummy_theme_properties);

        return $dummy_theme;
    }
}

/**
 * Picovico Videos API wrapper
 *
 * @author acpmasquerade <acpmasquerade@picovico.com>
 */
class Picovico_Video extends Picovico{

    private $title  = null;
    private $theme  = null;
    private $token  = null;

    // the JSON properties as returned by Picovico
    private $properties = array();

    private $status = Picovico_Config::VIDEO_STATUS_INCOMPLETE;
    
    private $frames = array();
    private $frames_counter = 0;
    private $frames_identifers = array();

    protected static $status_explanations;

    function  __construct($config) {
        parent::__construct($config);
    }

    /**
     * Status as defined by Picovico API Documentation
     *
     * 0 Implying FAILED
     * 1 Implying QUEUED
     * 2 Implying PROCESSING
     * 3 Implying DEFFERED
     * 4 Implying RENDERING
     * 
     * @return <type> Status
     */
    public function get_status(){
        return $this->status;
    }

    public function get_status_message(){
        if(!isset (self::$status_explanations)){
            self::$status_explanations = Picovico_Config::get("video_status");
        }

        return @self::$status_explanations[$this->status];
    }

    public function set_properties($properties){
        $this->properties = $properties;
    }

    public function get_properties(){
        return $this->properties;
    }

    public function get_url($video_size = Picovico_Config::VIDEO_SIZE_360){
        if($this->get_status() == Picovico_Config::VIDEO_STATUS_COMPLETE){
            $video_url = @ $this->properties["video"][$video_size]["url"];

            if($video_url){
                if(preg_match("/^(http\|http):\/\/.*/", $video_url)){
                    return $video_url;
                }else{
                    return "http://".$video_url;
                }
            }
        }else{
            $this->throw_api_exception("Incomplete_Video_Exception");
        }
    }

    public function get_size($video_size = Picovico_Config::VIDEO_SIZE_360){
        if($this->get_status() == Picovico_Config::VIDEO_STATUS_COMPLETE){
            $video_size = @ $this->properties["video"][$video_size]["size"];
            return $video_size;
        }else{
            $this->throw_api_exception("Incomplete_Video_Exception");
        }
    }

    public function get_thumbnail($thumbnail_size = Picovico_Config::VIDEO_SIZE_360){
        if($this->get_status() == Picovico_Config::VIDEO_STATUS_COMPLETE){
            $thumbnail_url = @ $this->properties["thumbnail"][$thumbnail_size];

            if($thumbnail_url){
                if(preg_match("/^(http\|http):\/\/.*/", $thumbnail_url)){
                    return $thumbnail_url;
                }else{
                    return "http://".$thumbnail_url;
                }
            }
            
        }else{
            $this->throw_api_exception("Incomplete_Video_Exception");
        }
    }

    public function get_duration(){
        if($this->get_status() == Picovico_Config::VIDEO_STATUS_COMPLETE){
            $duration = @ $this->properties["duration"];
            return $duration;
        }else{
            $this->throw_api_exception("Incomplete_Video_Exception");
        }
    }

    public function set_status($status){
        $this->status = $status;
    }

    public function set_theme(Picovico_Theme $theme){
        if(!is_object($theme) OR get_class($theme) != "Picovico_Theme"){
            $this->throw_api_exception();
        }else{
            $this->theme = $theme;
        }
    }

    public function get_theme(){
        return $this->theme;
    }

    public function get_token(){
        return $this->token;
    }

    private function set_token($token){
        $this->token = $token;
    }

    function get_frames(){

        $ordered_frames_array = array();
        
        foreach($this->frames_identifers as $some_frame_identifier){
            $ordered_frames_array[$some_frame_identifier] = $this->frames[$some_frame_identifier];
        }

        return $ordered_frames_array;
    }

    function get_frames_count(){
        return count($this->frames);
    }

    /**
     * Fetches an available video, created by user / or available publicly
     * 
     * @param <string> $video_identifier - The unique identifier for any available video
     *
     * @return <Picovico_Video>
     */
    function get_video($video_identifier){
        $url = $this->get_api_url("get_video");
        $response = $this->make_json_request($url, array("token"=>$video_identifier), Picovico_Config::API_GET);

        if(isset($response["status"])){
            // video isn't ready
            $picovico_video = new Picovico_Video(array());
            $picovico_video->set_status($response["status"]);
            $picovico_video->set_token($video_identifier);
            return $picovico_video;
        }

        elseif(isset($response["video"])){
            // video is ready
            $picovico_video = new Picovico_Video(array());
            $picovico_video->set_status(Picovico_Config::VIDEO_STATUS_COMPLETE);
            $picovico_video->set_token($video_identifier);

            $picovico_video->set_properties($response);
            
            return $picovico_video;            
        }

        else{
            // something UFO happened :(
            $this->throw_api_exception("API_Request_Exception");
        }
    }

    /**
     * @unimplemented
     * @todo
     * 
     * Fetches videos created by the user identified by the access_token
     *
     * This video is currently unimplemented
     * 
     * @param <string> $access_token (optional) The access_token
     *
     * @return <array> array of Picovico_Video (s)
     */
    function get_my_videos($access_token = null){
        return array();
    }

    private function create_frame_identifier($type){
        return $type . "_". $this->frames_counter++ . "_" . uniqid();
    }
    
    private function create_frame_data($type, $text = null, $url = null, $title = null){
        $frame_data = array();
        $frame_data["frame"] = $type;

        if($type == Picovico_Config::FRAME_TYPE_IMAGE){
            $frame_data["data"] = array("url"=>$url, "text"=>$text);
        }elseif($type == Picovico_Config::FRAME_TYPE_TEXT){
            $frame_data["data"] = array("title"=>$title, "text"=>$text);
        }else{
            $this->throw_api_exception("Invalid_Frame_Type_Exception");
        }

        return $frame_data;
    }

    /**
     * Adds/Appends a frame to video
     * 
     * @param <string> $type Type of the frame, one of the defined Frame types
     * @param <string> $text Caption for Image frame, description for text frame
     * @param <string> $url - URL if any image frame
     * @param <string> $title - Description if a Text frame
     *
     * @return <string> The unique Frame Identifier
     */
    private function add_frame($type, $text = null, $url = null, $title = null){
        $frame_identifier = $this->create_frame_identifier($type);
        $frame_data = $this->create_frame_data($type, $text, $url, $title);

        $this->frames_identifers[] = $frame_identifier;        
        $this->frames[$frame_identifier] = $frame_data;
        
        return $frame_identifier;
    }


    /**
     * Prepends a frame to video
     *
     * @param <string> $type Type of the frame, one of the defined Frame types
     * @param <string> $text Caption for Image frame, description for text frame
     * @param <string> $url - URL if any image frame
     * @param <string> $title - Description if a Text frame
     * 
     * @return <string> The unique Frame Identifier
     */
    private function prepend_frame($type, $text = null, $url = null, $title = null){
        $frame_identifier = $this->create_frame_identifier($type);        
        $frame_data = $this->create_frame_data($type, $text, $url, $title);

        array_unshift($this->frames_identifers, $frame_identifier);
        $this->frames[] = $frame_data;
        
        return $frame_identifier;
    }

    function add_text_frame($title, $text){
        return $this->add_frame(Picovico_Config::FRAME_TYPE_TEXT, $text, null, $title);
    }

    function append_text_frame($title, $text){
        return $this->add_text_frame($title, $text);
    }

    function prepend_text_frame(){
        return $this->prepend_frame(Picovico_Config::FRAME_TYPE_TEXT, $text, null, $title);
    }

    function add_image_frame($url, $text){
        return $this->add_frame(Picovico_Config::FRAME_TYPE_IMAGE, $text, $url);
    }

    function append_image_frame($url, $text){
        return $this->add_image_frame($url, $text);
    }

    function prepend_image_frame($url, $text){
        return $this->prepend_frame(Picovico_Config::FRAME_TYPE_IMAGE, $text, $url);
    }

    function shuffle_frames(){
        shuffle($this->frames_identifers);
        return TRUE;
    }

    function reverse_frames(){
        array_reverse($this->frames_identifers);
        return TRUE;
    }

    function set_callback_url($callback_url){
        $this->callback_url = $callback_url;
    }

    function get_callback_url(){
        return $this->callback_url;
    }

    function set_callback_email($callback_email){
        $this->callback_email = $callback_email;
    }

    function get_callback_email(){
        return $this->callback_email;
    }

    function set_music_url($music_url){
        $this->music_url = $music_url;
    }

    function get_music_url(){
        return $this->music_url;
    }

    function set_title($title){
        $this->title = $title;
    }

    function get_title(){
        return $this->title;
    }

    private function create_video_definition_data(){
        $picovico_video_definition_data = array();

        $picovico_video_definition_data["music_url"] = $this->get_music_url();
        $picovico_video_definition_data["video_title_16"] = $this->get_title();
        $picovico_video_definition_data["theme"] = $this->get_theme()->get_machine_name();
        $picovico_video_definition_data["frames"] = $this->get_frames();
        $picovico_video_definition_data["callback_url"] = $this->get_callback_url();
        $picovico_video_definition_data["callback_email"] = $this->get_callback_email();

        return $picovico_video_definition_data;
    }

    /**
     * Create video
     */
    function create_video(){
        
        // check theme
        if(!$this->get_theme()){
            $this->throw_api_exception("Missing_Theme_Exception");
        }

        // music
        if(!$this->get_music_url()){
            $this->throw_api_exception("Missing_Music_Exception");
        }

        // count frames

        $picovico_video_definition_data = $this->create_video_definition_data();

        $url = $this->get_api_url("create_video");
        $response = $this->make_json_request($url, array("vdd"=>json_encode($picovico_video_definition_data)), Picovico_Config::API_POST);

        if(isset($response["token"])){
            $this->set_token($response["token"]);
            $this->set_status(Picovico_Config::VIDEO_STATUS_QUEUED);
            return $this->get_token();
        }else{
            $this->set_status(Picovico_Config::VIDEO_STATUS_INCOMPLETE);
            $this->throw_api_exception("Error_Creating_Video_Exception");
        }
        
    }

    
}
