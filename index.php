<?php $time_start = microtime(true);
require_once(__DIR__.'/resources/server/libraries.php');
global $config; $cdir = __DIR__.'/'.$config['cache_dir']; if (!is_dir($cdir)) {mkdir($cdir, 0775, True);}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!empty($_POST['notifysub'])) { // get notified of playerskin change
    error_log('recieve playerskin change: '.$_POST['notifysub']);
    $flnm = $cdir.'.skinIn-'.$_POST['notifysub'];
    error_log('file_exists?: '.$flnm.': '.json_encode(file_exists($flnm)));
    // if (file_exists($flnm)) {
    //   $arr = json_decode(curl($flnm), True);
    //   $resp = curl(key($arr), current($arr));
    //   error_log('curl resp: '.json_encode($resp));
    //   if ($resp) {
    //     setPlayerSkin($_POST['notifysub'], key($resp), current($resp));
    //     printDataAndDie();
    //   } else {
    //     touch($cdir.'.untrustworthy-'.IP);
    // }}
  }
  if (!empty($_POST['recieve_secret'])) { // secret link handler
    $flnm = $cdir.'.secret-'.$_POST['recieve_secret'];
    if (is_file($flnm)) { unlink($flnm); }
}}
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (isset($_GET['whoami']) || !empty($_GET['send_me_a_secret']) || !empty($_GET['submodify']) || isset($_GET['checkskinsubs'])) { // recieve subscription
    $resp = [];
    if (!empty($_GET['dns']) && checkDNSRecord($_GET['dns'], $_SERVER['REMOTE_ADDR'])) {
      $hst = $_GET['dns']; // if valid dns, use it
    } else { $hst = $_SERVER['REMOTE_ADDR']; }
    if (!empty($_GET['submodify'])) { // request subscription change
      $dt = json_decode($_GET['submodify'], True);
      $resp['submod'] = 0;
      if (isset($dt['remove']) && is_array($dt['remove'])) {
        foreach ($dt['remove'] as $player) { // error_log('del user '.$hst.': '.$player);
          $resp['submod']++;
          query('sr', "DELETE FROM ExtSubscribe WHERE Host = ? AND Nick = ?", [$hst, (string)$player]);
      }}
      if (isset($dt['add']) && is_array($dt['add'])) {
        $now = time();
        foreach ($dt['add'] as $player) { // error_log('add user '.$hst.': '.$player);
          $resp['submod']++;
          query('sr', "INSERT IGNORE INTO ExtSubscribe (Host, Nick) VALUES (?, ?)", [$hst, (string)$player]);
    }}}
    if (isset($_GET['checkskinsubs'])) { // send any updated data
      $flnm = $cdir.'.sub2skin-'.$hst;
      if (is_file($flnm) && filesize($flnm)) {
        $expl = explode("\n", substr(curl($flnm), 0, -1));
        $resp['update'] = array_unique($expl);
        unlink($flnm);
      }
      touch($flnm);
    }
    if (isset($_GET['whoami'])) { $resp['youare'] = $hst; }
    if (!empty($_GET['send_me_a_secret'])) { // prove that we can reach them by POST, by sending a secret!
      $dt = json_decode($_GET['send_me_a_secret'], True);
      foreach (['page', 'secret', 'port'] as $k) {
        if (empty($dt[$k])) { printErrorAndDie($k.' was not specified'); }}
      $resp['secret_resp'] = curl($hst.':'.$dt['port'].$dt['page'], False, False, ['recieve_secret'=>$dt['secret']]);
      $flnm = $cdir.'.subviapost-'.$hst;
      error_log('reach_me: '.$dt['reach_me']);
      // if they wish to be posted at when a skin of theirs is updated
      if ($dt['reach_me'] === True) {
        file_put_contents($flnm, $hst.$dt['page']);
        error_log('write file: '.$flnm.': '.$hst.$dt['page']);
      } elseif ($dt['reach_me'] === False && file_exists($flnm)) { unlink($flnm); }
    }
    $resp['p_time'] = round(microtime(True)-$time_start, 2);
    header('Content-Type: application/json');
    die(json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  } else {
    if(!file_exists('config.nogit.php')){ session_start(); session_destroy(); die(header('Location: installation')); }
    session_start();
    /* Set username session for non-authme system */
    if(empty($_SESSION['username']) && $config['am']['enabled'] == false){ $_SESSION['username'] = 'SkinSystemUser'; }
?><!doctype html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo L::title; ?></title>
    <!-- Libraries -->
    <?php spitResources('css', False); 
      // spitResources('css'); for some reason cached version doesn't work in FF 60.0.3
    ?>
    <link rel="shortcut icon" href="favicon.ico">
    <?php if (isset($_GET['theme']) and is_file('resources/themes/'.$_GET['theme'].'.css')) { $theme = $_GET['theme']; }
    elseif (isset($_COOKIE['theme']) and is_file('resources/themes/'.$_COOKIE['theme'].'.css')) { $theme = $_COOKIE['theme']; }
    elseif (isset($config['def_theme']) and is_file('resources/themes/'.$config['def_theme'].'.css')) { $theme = $config['def_theme']; }
    else { preg_match('/([^\/]*)\.css$/', glob('resources/themes/*.css')[0], $thm); $theme = $thm[1]; }
    echo '<link id="stylesheetSelector" rel="stylesheet" name="'.$theme.'" href="resources/themes/'.$theme.'.css">'; 
    // pick theme from cookie; if cookie invalid, pick default theme from config ?>
    <script type="text/javascript">
      function setCookie(cname, cvalue) {
        var d = new Date(); d.setTime(d.getTime() + (365*24*60*60*1000)); // cookies will last a year
        document.cookie = cname + "=" + cvalue + ";expires="+ d.toUTCString() + ";path=/";
      } 
      var theme = document.getElementById("stylesheetSelector").getAttribute("name");
      setCookie("theme", theme); // swap that stale cookie for a new one!
      function rotateTheme() { // move a metaphorical carousel by one item
        $.getJSON("resources/themes/",{}, function(lst){ 
          setCookie("theme", lst[((lst.indexOf(theme+".css")+1)%lst.length)].slice(0, -4));
          location.reload();
        });
      }
    </script>
    <!-- Libraries -->
    <?php spitResources('js'); ?>
  </head>
  <body class="bg-light">
    <?php if (!empty($_SESSION['username'])) { 
      echo '<a id="account-name" name="'.$_SESSION['username'].'" style="display: none;"></a>';
    } ?>
    <!-- Page Container -->
    <section class="bg-light h-100">
      <div class="container h-100" style="padding: 0px;">
        <div class="row h-100">
          <div class="col m-auto" style="padding: 0px;max-width: <?php echo((!empty($_SESSION['username']) && $config['misc_ui']['minerender']) ? 650 : 450); ?>px">
            <div class="card border-0 shadow">
              <div class="card-header bg-primary text-white"<?php } if (!$config['misc_ui']['header']) { echo ' style="display: none;"';} ?>>
                <div class="row mx-2 align-items-center">
                  <h5 class="mb-0"><?php echo L::title;
                    echo '<small style="font-size: 60%;"><a style="padding-left:3px;" id="versionDisplay" title="Release '.$config['version'].'" href="https://github.com/riflowth/SkinSystem/releases/tag/'.$config['version'].'">v.'.$config['version'].'</a>';
                      if($config['version'] !== getLatestVersion()){ echo '<a style="padding-left:2px;" title="Latest Release" href="https://github.com/riflowth/SkinSystem/releases/latest">(New version avaliable)</a>'; } ?>
                    </small>
                  </h5>
                  <h6 class="mb-0 ml-auto">
                    <?php if($config['am']['enabled'] === True && !empty($_SESSION['username'])){ 
                      echo '<a class="skinDownload" title="Download skin"';
                      if (!$config['misc_ui']['name_disp']) { echo ' style="display: none;"'; }
                      echo ' href="resources/server/skinRender.php?format=raw&dl=true&user='.$_SESSION['username'].'">';
                      if ($config['misc_ui']['user_icon'] === True) {
                        echo '<img class="skinDownload" style="max-height:29px!important;" src="resources/server/skinRender.php?vr=0&hr=0&headOnly=true&ratio=4&user='.$_SESSION['username'].'">    ';} 
                      elseif ($config['misc_ui']['user_icon'] === False) {
                        echo '<i class="fas fa-user"></i>';}
                      echo htmlspecialchars($_SESSION['username'], ENT_QUOTES);
                      ?><a class="btn btn-sm btn-light ml-2 rounded-circle" title="Log out" href="resources/server/authenCore.php?logout"><i class="fas fa-sign-out-alt"></i></a>
                    <?php } ?>
                  </h6>
                  <?php if($config['misc_ui']['thm_button'] !== False){
                    echo '<a class="btn btn-sm btn-light ml-2 rounded-circle" title="Switch theme" onclick="rotateTheme();"><i class="fas fa-adjust"></i></a>';
                  } ?>
                </div>
              </div>
              <div class="card-body"<?php if (!$config['misc_ui']['header']) { echo ' style="padding: 0px;"'; } ?>>
                <?php if(!empty($_SESSION['username'])){ ?>
                  <script src="resources/js/skinCore.js"></script>
                  <div class="row">
                    <div class="col<?php if ($config['misc_ui']['minerender']) { echo '-sm-8 pr-sm-2'; } ?> mb-sm-0 mb-3">
                      <!-- Uploader -->
                      <div class="card border-0 shadow">
                        <h6 class="card-header bg-info text-white"><i class="fas fa-file-upload text-dark"></i> Upload</h6>
                        <div class="card-body">
                          <form id="uploadSkinForm">
                            <?php if($config['am']['enabled'] === False) { ?>
                              <div class="form-group row">
                                <h5 class="col-lg-3"><span class="badge badge-success">Username</span></h5>
                                <div class="col-lg-9">
                                  <input id="input-username" class="form-control form-control-sm" name="username" type="text" required>
                                </div>
                              </div>
                            <?php } ?>
                            <div class="form-group">
                              <h5 class="mb-0 mr-3 custom-control-inline"><span class="badge badge-info">Skin Type</span></h5>
                              <div class="custom-control custom-radio custom-control-inline">
                                <input id="skintype-steve" class="custom-control-input" name="isSlim" value="false" type="radio">
                                <label class="custom-control-label" for="skintype-steve">Steve</label>
                              </div>
                              <div class="custom-control custom-radio custom-control-inline">
                                <input id="skintype-alex" class="custom-control-input" name="isSlim" value="true" type="radio">
                                <label class="custom-control-label" for="skintype-alex">Alex</label>
                              </div>
                            </div>
                            <div class="form-group mb-4">
                              <h5 class="mb-0 mr-3 custom-control-inline"><span class="badge badge-info">Upload Type</span></h5>
                              <div class="custom-control custom-radio custom-control-inline">
                                <input id="uploadtype-file" class="custom-control-input" name="uploadtype" value="file" type="radio" checked>
                                <label class="custom-control-label" for="uploadtype-file">File</label>
                              </div>
                              <div class="custom-control custom-radio custom-control-inline">
                                <input id="uploadtype-url" class="custom-control-input" name="uploadtype" value="url" type="radio">
                                <label class="custom-control-label" for="uploadtype-url">URL</label>
                              </div>
                            </div>
                            <div id="form-input-file" class="form-group">
                              <div class="custom-file">
                                <input id="input-file" class="custom-file-input" name="file" type="file" accept="image/*" required autofocus>
                                <label class="custom-file-label text-truncate">Choose skin...</label>
                              </div>
                            </div>
                            <div id="form-input-url" class="form-group row" style="display: none;">
                              <div class="col-lg-12">
                                <input id="input-url" class="form-control form-control-sm" name="url" type="text" placeholder="Enter skin URL...">
                              </div>
                            </div>
                            <button class="btn btn-primary w-100"><strong>Upload!</strong></button>
                            <small class="form-text text-muted" id="uploadDisclaimer"<?php 
                              if ($config['data_warn'] === 'no' or ($config['data_warn'] === 'eu' and file_get_contents(cacheGrab('https://ipapi.co/'.IP.'/in_eu', 'in_eu-'.IP)) !== 'True')) {
                                echo ' style="display: none;"';
                              }
                            ?>>Skins are sent to <a href="https://mineskin.org">mineskin.org</a>, <a href="https://mojang.com">mojang.com</a>, and <a href="/"><?php echo $_SERVER['HTTP_HOST']; ?></a></small>
                          </form>
                        </div>
                      </div>
                    </div>
                    <?php if ($config['misc_ui']['minerender']) { ?>
                      <div class="col-sm-4">
                        <!-- Skin Viewer -->
                        <div class="card border-0 shadow">
                          <h6 class="card-header bg-info text-white"><i class="fas fa-eye text-dark"></i> Preview</h6>
                          <div class="card-body">
                            <div id="skinViewerContainer"></div>
                            <script type="text/javascript">
                              window.onresize = function () { // skinViewer height shall match uploadSkin
                                document.getElementById('skinViewerContainer').style.height = document.getElementById('uploadSkinForm').clientHeight+'px'; }
                              window.onresize();
                            </script>
                          </div>
                        </div>
                      </div>
                    <?php } if ($config['misc_ui']['history']) { ?>
                      <!-- Skin History -->
                      <div class="col-sm-12 mt-3">
                        <div class="card border-0 shadow">
                          <h6 class="card-header bg-info text-white"><i class="fas fa-history text-dark"></i> History <small>- You can use these skins by clicking them</small></h6>
                          <div class="card-body">
                            <a id="mineskin-recent" href="<?php echo cacheGrab('https://api.mineskin.org/get/list/0?size=6','mineskin-recent','./',(10*60)); ?>" style="display: none;"></a>
                            <div class="row" id="skinlist"></div>
                            <script type="text/javascript">
                              setCookie('skinHistoryType', 'mineskin');
                              function getCookie(cname) {
                                var value = "; " + document.cookie;
                                var parts = value.split("; " + cname + "=");
                                if (parts.length == 2) return parts.pop().split(";").shift();
                              }
                              var historytype = getCookie('skinHistoryType');
                              if (historytype == 'personal') {
                                
                              } else if (historytype == 'server') {
                                
                              } else if (historytype == 'mineskin') {
                                $.getJSON($('#mineskin-recent')[0].href,{}, function( lst ){ 
                                  $.each( lst.skins.slice(0,6), function( key, val ) {
                                    skinid = val.url.match(/\w+$/);
                                    $('#skinlist').append('<div class="col-2 skinlist-mineskin"><img class="skinlistitem" style="max-width:75px;width:inherit;cursor:pointer;" title="'+
                                      ('Select skin '+val.name).trim()+'" onclick="skinURL(\'resources/server/skinRender.php?format=raw&mojang='+skinid+'\');" src="resources/server/skinRender.php?mojang='+skinid+'"></div>');
                                  });
                                });
                              }
                              function skinURL(url) {
                                $('#uploadtype-url').prop('checked', true).change();
                                $('#input-url').val(url);
                              }
                            </script>
                          </div>
                        </div>
                      </div>
                    <?php } ?>
                  </div>
                  <?php } else { ?>
                    <script src="resources/js/authenCore.js"></script>
                    <div class="card border-0 shadow">
                      <h6 class="card-header bg-info text-white"><i class="fas fa-sign-in-alt"></i> Authenication</h6>
                      <div class="card-body">
                        <form id="loginForm">
                          <div class="input-group mb-3">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                            <input id="login-username" class="form-control" name="username" type="text" placeholder="Username" required autofocus>
                          </div>
                          <div class="input-group mb-3">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                            <input id="login-password" class="form-control" name="password" type="password" placeholder="Password" required>
                          </div>
                          <button class="btn btn-success w-100"><strong>Login!</strong></button>
                        </form>
                      </div>
                    </div>
                  <?php } ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </body>
  </html>
<?php } ?>