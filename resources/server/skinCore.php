<?php
  require_once('libraries.php');
  session_start();
  /* if valid request from a user */
  if($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['isSlim']) && !empty($_POST['uploadtype']) && isset($_FILES['file']['tmp_name']) && isset($_POST['url'])){
    /* Initialize playername */
    if($config['am']['enabled'] == true && !empty($_SESSION['username'])){ $playername = $_SESSION['username']; } 
    else if($config['am']['enabled'] != true && !empty($_POST['username'])){ $playername = $_POST['username']; } 
    if(empty($playername)){ printDataAndDie(['type' => 'warning', 'title' => 'Unauthorized', 'refresh'=>True]); }
    /* Initialize Data for sending to MineSkin API */
    $postparams = ['visibility' => 0];
    if($_POST['isSlim'] == 'true'){ $postparams['model'] = 'slim'; }
    if($_POST['uploadtype'] == 'url' && !empty($_POST['url'])){ // send with url
      $data = $_POST['url'];
      if (preg_match('/^(?:(\w+)(?:@(.+))?)$/', $data, $match) && isset($match[1])) {
        if (!isset($match[2])) {
          $fld_deep = 2; // this script is two folders deep from root of skinsystem
          $expl = explode('/', strrev($_SERVER['SCRIPT_NAME']), ($fld_deep+2));
          $match[2] = $_SERVER['HTTP_HOST'].':'.$_SERVER['SERVER_PORT'].strrev($expl[($fld_deep+1)]);
        }
        // user+skinsystem+mineskin should all have access to this url
        $url = $_SERVER['REQUEST_SCHEME'].'://'.$match[2].'/';

        // subscribe to that skinsystem instance
        error_log('sub2server: '.curl($url.'?submodify='.json_encode(["add"=>[$match[1]]])));

        // enable post requests to me
        $canpost = canServerPost2Me($url, True);
        error_log('can post2me: '.json_encode($canpost));
        // if ($canpost) {
        //   // server is posting to us
        // } else {
        //   // server isn't posting to us
        // }
        
        $url = $url.'resources/server/skinRender.php';
        $json = json_decode(curl($url.'?format=signed&user='.$match[1]), True);
        if($json['value'] && $json['signature']) { $data = $json['value']; $sig = $json['signature']; } // try pre-signed
        else { $data = $url.'?format=raw&user='.$match[1]; } // else set url for mineskin to fetch from skinsystem
      }
    } else { // send with file
      $file = $_FILES['file'];
      /* Check If the skin is a Minecraft's skin format */
      if(!in_array($file['type'], ['image/jpeg', 'image/png'])){ 
        printErrorAndDie('Please upload JPEG or PNG file!'); }
      list($skinWidth, $skinHeight) = getimagesize($file['tmp_name']);
      if(( $skinWidth != 64 && $skinHeight != 64 ) || ( $skinWidth != 64 && $skinHeight != 32 )){
        printErrorAndDie('This is not a valid Minecraft skin!');}
      $data = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
    }
    /* write to SkinsRestorer Storage */
    if (!isset($sig)) { $sig = $_POST['uploadtype']; } // if not pre-signed, set "sig" to uploadtype
    setPlayerSkin($playername, $data, $sig); // sign skin if necessary, then write to database
    printDataAndDie(['title' => 'Upload Successful!', 'text' => 'Enjoy your skin', 'refresh'=>True]);
  }
  printErrorAndDie('Re-upload or contact WebMaster!');
?>
