<?php define('VER', '1.7.1-pre1');
  define('DIR_ROOT', realpath(__DIR__.'/../..'));
  define('WEB_ROOT', substr(DIR_ROOT, strlen(realpath($_SERVER['DOCUMENT_ROOT']))));

  // error_log($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].' '.$_SERVER['DOCUMENT_ROOT']);  
  /* remote addr with ipv6 as (/64 block) prefix */
  require_once 'i18n.class.php';
  $i18n = new i18n();
  // $i18n->setCachePath('./langcache/');
  $i18n->setFilePath(DIR_ROOT.'/resources/lang/lang_{LANGUAGE}.ini'); // language file path
  $i18n->setFallbackLang('en');
  // $i18n->setPrefix('I');
  // $i18n->setForcedLang('en'); // force english, even if another user language is available
  // $i18n->setSectionSeparator('_');
  $i18n->setMergeFallback(true); // make keys available from the fallback language
  $i18n->init();
  define('IP', preg_replace('/^(?:((?:[[:xdigit:]]+(:)){1,4})[[:xdigit:]:]*|((?:\d+\.){3}\d+))$/', '\1\2\3', $_SERVER['REMOTE_ADDR']));
  global $installation; // if called from installation, some functions won't work
  if (!isset($installation)) {
    global $config; // get config parameters (can't do if called from installation)
    $config = require_once(DIR_ROOT.'/config.nogit.php');
    if($config['version'] != VER && $_SERVER['REQUEST_METHOD'] === 'GET') {
      require_once(DIR_ROOT.'/installation/index.php');
      confupdater($config);
      die(header("Refresh:0"));
    }
    foreach (['minerender', 'header'] as $k) {
      if (!empty($_GET['ui_'.$k])) { $config['misc_ui'][$k] = $_GET['ui_'.$k]; }
    }
    /* start background loop to trigger functions every x seconds */
    $loopmap = ['cacheClean' => (30*60),
                'skinChangeCheck' => (24*60*60)];
    $lpdelay = (int)min($loopmap);
    if (time()-filemtime(DIR_ROOT.'/resources/server/bgLoop.php')>$lpdelay+60) { // if bgLoop not running (w/60s processing leeway)
      $lpargs = '"'.DIR_ROOT.'/resources/server/bgLoop.php" "'.$lpdelay.'" "'.base64_encode(serialize($loopmap)).'"'; // run this in new php process
      if(strcasecmp(substr(PHP_OS, 0, 3), 'WIN') == 0){ exec('start /B php '.$lpargs); } // windows systems (untested)
      else { exec('nohup php '.$lpargs.' > /dev/null 2>&1 &'); } // unix & other systems (tested w/ubuntu 16.04)
      unset($lpargs); 
    } unset($loopmap); unset($lpdelay);

    /* sign skin data with MineSkin */
    function skinSign($data, $type) {
      $typemap = ['url'=>'url', 'file'=>'upload'];
      if (!array_key_exists($type, $typemap)) { printErrorAndDie('skin sign invalid: uploadType'); }
      // cURL to MineSkin API
      $endpointURL = 'https://api.mineskin.org/generate/'.$typemap[$type];
      $post = [$type=>$data];
      $cr = curl($endpointURL, False, False, $post);
      if ($cr) { $json = json_decode($cr, true)['data']['texture']; }
      if(!$cr || empty($json['value']) || empty($json['signature'])) {
        printErrorAndDie(['text'=>'Could not sign with MineSkin API', 'trace'=>[$endpointURL, $post, $cr]]); }
      return([$json['value'], $json['signature']]);
    }

    // get names of a sql table and it's keys
    function get_table_names($db, $tablenum) {
      global $config;
      $ret[] = array_keys($config[$db]['tables'])[$tablenum];
      foreach ($config[$db]['tables'][$ret[0]] as $v) { $ret[] = $v; }
      return($ret); // first is tablename, rest are keynames
    }

    /* write playerskin to skinsrestorer */
    function setPlayerSkin($user, $value, $sig) {
      if (in_array($sig, ['url', 'file'])) { // if needs to be signed, sign it.
        $json = skinSign($value, $sig);
        $value = $json[0]; $sig = $json[1];
        unset($json);
      }
      global $config; $cdir = DIR_ROOT.'/'.$config['cache_dir']; if (!is_dir($cdir)) { mkdir($cdir, 0775, True); }
      error_log('user: '.$user.' sig: '.$sig);

      $tb = get_table_names('sr', 1); // get names (Players)
      $sknm = query('sr', "SELECT {$tb[2]} FROM {$tb[0]} WHERE {$tb[1]} = ?", [$user])->fetch(PDO::FETCH_ASSOC)[$tb[2]];
      /* Prevent the override of mojang skins */
      $sknm = substr('-'.base64_encode(md5($value, True)), 0, 16); // "-" cannot be used in minecraft name, use hash of skin

      $tb = get_table_names('sr', 0); // get names (Skins)
      query('sr', // Storage Writing (Skins Table)
        "INSERT INTO {$tb[0]} ({$tb[1]}, {$tb[2]}, {$tb[3]}, {$tb[4]}) VALUES (?, ?, ?, ?) ".
        "ON DUPLICATE KEY UPDATE {$tb[1]}=VALUES({$tb[1]}), {$tb[2]}=VALUES({$tb[2]}), ".
        "{$tb[3]}=VALUES({$tb[3]}), {$tb[4]}=VALUES({$tb[4]})",
        [$sknm, $value, $sig, '9223243187835955807'] 
      // info pertaining to the odd timestamp above: https://gist.github.com/ITZVGcGPmO/5f42faded32e63dbafa020e36180f013
      // https://github.com/Th3Tr0LLeR/SkinsRestorer---Maro/blob/9358d5727cfc7a1dce4e2af9412679999be5b519/src/main/java/skinsrestorer/shared/storage/SkinStorage.java#L274
      );
      $tb = get_table_names('sr', 1); // get names (Players)
      query('sr', // Storage Writing (Players Table)
        "INSERT INTO {$tb[0]} ({$tb[1]}, {$tb[2]}) VALUES (?, ?) " .
        "ON DUPLICATE KEY UPDATE {$tb[1]}=VALUES({$tb[1]}), {$tb[2]}=VALUES({$tb[2]})",
        [$user, $sknm]
      );
      query('sr',
      'CREATE TABLE IF NOT EXISTS ExtSubscribe(
        Host VARCHAR(64) NOT NULL,
        Nick VARCHAR(16) NOT NULL,
        UNIQUE(Host, Nick)
      )');
      foreach (query('sr', 'SELECT Host FROM ExtSubscribe WHERE Nick = ?', // update subscribed hosts
       [$user])->fetchAll(PDO::FETCH_ASSOC) as $entry) {
        $sqlhost = array_values($entry)[0];
        error_log('update host '.$sqlhost);
        $flnm = $cdir.'.sub2skin-'.$sqlhost;
        file_put_contents($flnm, $user."\n", FILE_APPEND | LOCK_EX);
        $flnm = $cdir.'.subviapost-'.$sqlhost;
        error_log('is_file '.$flnm.': '.json_encode(is_file($flnm)));
        if (is_file($flnm)) {
          $host = curl($flnm);
          error_log('posting to '.$host.': '.json_encode(['notifysub'=>$user]));
          if (curl($host, False, False, ['notifysub'=>$user], False)) { // post request
            error_log('successful post request');
            touch($flnm); // if successful, update filename
          }
        }
      }
    }

    /* test if another server can do a post request to us */
    function canServerPost2Me($url, $reach_me=False) { error_log('canServerPost2Me: '.$url.': '.json_encode($reach_me));
      global $config; $cdir = DIR_ROOT.'/'.$config['cache_dir']; if (!is_dir($cdir)) { mkdir($cdir, 0775, True); }
      $dta = ['reach_me'=>$reach_me,'port'=>$_SERVER['SERVER_PORT'],'page'=>WEB_ROOT.'/','secret'=>bin2hex(random_bytes(16))];
      if ($_SERVER['SERVER_ADDR'] !== $_SERVER['HTTP_HOST']) { $dta['dns'] = $_SERVER['HTTP_HOST']; }
      $flnm = $cdir.'.secret-'.$dta['secret']; touch($flnm);
      if (!is_file($flnm)) { printErrorAndDie('could not generate a secret link for another server to verify themselves'); }
      curl($url.'?send_me_a_secret='.json_encode($dta));
      if (!is_file($flnm)) { return(True); } // secret link verified
      else { unlink($flnm); return(False); } // secret link not verified
    }
    /* (un)subscribe a players to another skinsystem user */ // needs to be re-done
    function skinSetSubscribe($user, $host='localhost') { error_log('skinSetSubscribe'); // (un)subscribe to skin
      global $config; $cdir = DIR_ROOT.'/'.$config['cache_dir']; if (!is_dir($cdir)) { mkdir($cdir, 0775, True); }
      if ($subscribe) {
        $guessloc = 'http'.($_SERVER['HTTPS'] ? 's' : '').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
        if (!$skinData) { $skinData = curl($url); }
        file_put_contents($cdir.'.skinListen-'.$user, serialize([$url=>['If-Modified-Since: '.date(\DateTime::RFC7231)]]));
        $wr = json_decode($skinData, true);
        if ($wr[0] && $wr[0]) {
          setPlayerSkin($user, $wr[0], $wr[1]);
          $datsub = ['add'=>[$user]];
      }} else { $datsub = ['remove'=>[$user]]; }
      curl($url, False, False, ['submodify' => json_encode($datsub)]);
    }
    /* periodic skin change checks */ // needs to be re-done
    function skinChangeCheck() { // check if playerskins have changed(only if server supports "if-modified-since" header)
      global $config; $cdir = DIR_ROOT.'/'.$config['cache_dir']; if (!is_dir($cdir)) { mkdir($cdir, 0775, True); }
      foreach (glob($cdir.'.skinIn-*') as $cl) {
        $fl = unserialize(curl($cl, False, False));
        if (curl($fl[0], ['If-Modified-Since: '.date(\DateTime::RFC7231)]) === '') { // should return empty if listening to header
          $resp = curl($fl[0], $fl[1]);
          if ($resp !== '') { // should return non-empty if skin changed (we weren't notified, try to re-subscribe)
            skinSetSubscribe($user, True, $fl[0], $resp);
    }}}}
    // downloads to cache if need be, removes unused cache files, return location of cache file.
    function cacheGrab($url, $nm=False, $dirhead='', $max_cache=False, $hchk=False) {
      if ($nm === False) { $nm = $url; }
      global $config; $cdir = $dirhead.$config['cache_dir']; if (!is_dir($cdir)) { mkdir($cdir, 0775, True); }
      $file = $cdir.preg_replace('/[^\w\.]+/', '_', $nm);
      preg_match('/(.*?)([^\/\\\]+)$/', $file, $match);
      $acfl = $match[1].'.access-'.$match[2];
      if (!$max_cache) {if (is_file($file)) {touch($acfl);}} // no max cache? touch so it's not caught by cleanup
      // if no access data, or time since access greater than max cache
      if (!is_file($acfl) || ($max_cache && is_file($file) && (time() - filemtime($acfl) >= $max_cache))) {
        $filecont = curl($url);
        if ($hchk) {
          for ($i = -5; $i < 0; $i++) {
            if (hash($hchk[0], $filecont) != $hchk[1]) {
              if ($i === 0) { printErrorAndDie($url.' != '.$hchk[0].' '.$hchk[1]); }
              $filecont = curl($url);
            } else { break; }
        }}
        file_put_contents($file, $filecont); touch($acfl);
      }
      return $file;
    }
    /* periodic cache cleanups */
    function cacheClean() { // on routine from bgLoop
      // normal files will have duplicate name '.access-' to signify last access time. (most OS only support modify time)
      global $config; $cdir = DIR_ROOT.'/'.$config['cache_dir']; if (!is_dir($cdir)) { mkdir($cdir, 0775, True); }
      $now = time();
      $ts = get_table_names('sr', 0); // get names (Skins)
      $tp = get_table_names('sr', 0); // get names (Players)
      // remove skins not in use by any player
      query('sr', "DELETE FROM {$ts[0]} WHERE {$ts[1]} NOT LIKE '[a-zA-Z_][a-zA-Z_]%' AND {$ts[1]} NOT IN (SELECT {$tp[2]} FROM {$tp[0]});", []);
      foreach (glob($cdir.'*') as $cl) { // for each file in cachedir
        if (!preg_match('/(.*[\/\\\])(?:\.access-([^\/\\\]*)|index\.php)$/', $cl)) {
          preg_match('/(.*?)([^\/\\\]+)$/', $cl, $match);
          $acfl = $match[1].'.access-'.$match[2];
          if (!file_exists($acfl)) { $acfl = $cl; }
          elseif ($now - filemtime($acfl) >= $config['cache_for_days']*24*60*60) {
            if (preg_match('/(.*[\/\\\])(?:\.sub2skin-([^\/\\\]*))$/', $cl, $match)) {
              error_log('del subscription '.$match[2]);
              query('sr', // this needs to be tested (removes stale subscriptions)
                "DELETE FROM ExtSubscribe WHERE Host = ?",
                [$match[2]]
              );
            }
            if (file_exists($cl)) { unlink($cl); }
            if (file_exists($acfl)) { unlink($acfl);
    }}}}}

    /* GitHub getLastestVersion */
    function getLatestVersion() {
      $nwVer = cacheGrab('https://api.github.com/repos/riflowth/SkinSystem/releases/latest','latest_version',DIR_ROOT.'/',(24*60*60));
      return json_decode(curl($nwVer), True)['tag_name'];
    }

    /* Initialize PDO */
    if($config['am']['enabled'] == True){
      $amPDOinstance = new PDO('mysql:host=' . $config['am']['host'] . '; port=' . $config['am']['port'] . '; dbname=' . $config['am']['database'] . ';', $config['am']['username'], $config['am']['password']);
      $amPDOinstance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    $srPDOinstance = new PDO('mysql:host=' . $config['sr']['host'] . '; port=' . $config['sr']['port'] . '; dbname=' . $config['sr']['database'] . ';', $config['sr']['username'], $config['sr']['password']);
    $srPDOinstance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    /* When working with AuthMe or SkinsRestorer database */
    function query($type, $mysqlcommand, $key = []) {
      if($type == 'am'){
        global $amPDOinstance;
        $result = $amPDOinstance->prepare($mysqlcommand);
        $result->execute($key);
      } else if($type == 'sr'){
        global $srPDOinstance;
        $result = $srPDOinstance->prepare($mysqlcommand);
        $result->execute($key);
      }
      return $result;
    }
  } // past this bracket, $config could not be set!

  function printDataAndDie($data = []) {
    global $config; $cdir = DIR_ROOT.'/'.$config['cache_dir']; if (!is_dir($cdir)) { mkdir($cdir, 0775, True); }
    if (is_string($data)) { $data = ['title' => $data]; }
    if (array_key_exists('trace', $data) && is_array($data['trace'])) {
      $json = json_encode($data['trace'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      $flnm = $cdir.md5($json).'.json';
      file_put_contents($flnm, $json); $data['trace'] = $flnm;
      if (!isset($data['footer'])) { $data['footer'] = ''; }
      $data['footer'] = $data['footer']."\n".'<div class="col"><a href="'.$flnm.'"><i class="fas fa-file-code" style="padding-right: 5px;"></i>trace: '.md5($json).'</a></div>';
    }
    $defaults = ['type'=>'success','title'=>'Success!','heightAuto'=>False,'showConfirmButton'=>False];
    if (isset($data['refresh'])) { 
      if (!isset($config)) { $data['refresh'] = 350; } // if no config, use 350ms
      else { $data['refresh'] = $config['misc_ui']['reload_ms']; }}
    $data = array_replace($defaults, $data);
    header('Content-Type: application/json');
    die(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  function printErrorAndDie($data) {
    global $config;
    if (is_string($data)) { $data = ['title' => $data]; }
    $defaults = ['type'=>'error','title'=>'Something went wrong!','showCloseButton'=>True,'footer'=>''];
    $data = array_replace($defaults, $data);
    if (isset($config)) { // can't use cachegrab w/o config set
      $url = 'https://status.mojang.com/check';
      $mjstatus = json_decode(curl(cacheGrab($url,$url,DIR_ROOT.'/',60)), True);
      foreach ($mjstatus as $key) {
        foreach ($key as $site => $status) {
          if ($status !== 'green') {
            $data['footer'] = $data['footer']."\n".'<div class="col"><a href="https://help.mojang.com"><i class="fas fa-exclamation-circle" style="padding-right: 5px;"></i>'.$site.' is having issues</a></div>';
            error_log($site.' is having issues (https://help.mojang.com)');
      }}}
      $url = 'https://status.mineskin.org';
      $dta = curl(cacheGrab($url,$url,DIR_ROOT.'/',(60*60)));
      preg_match('/https:\/\/status\.mineskin\.org\/api\/getMonitorList\/\w+/', $dta, $match);
      $ret = curl(cacheGrab($match[0],$match[0],DIR_ROOT.'/',60));
      foreach (json_decode($ret, True)['psp']['monitors'] as $value) {
        if ($value['name'] == 'Mineskin API' and $value['statusClass'] != 'success') {
          $expl = explode('/', $value['monitorId']);
          $data['footer'] = $data['footer']."\n".'<div class="col"><a href="https://status.mineskin.org"><i class="fas fa-exclamation-circle" style="padding-right: 5px;"></i>'.$value['name'].' is having issues</a></div>';
          error_log($value['name'].' is having issues (https://status.mineskin.org)');
      }}
      if ($data['footer']) { $data['footer'] = '<div class="container">'.$data['footer'].'</div>'; }
    }
    printDataAndDie($data);
  }

  /* if a dns name has a specific record */
  function checkDNSRecord($dns, $record) {
    $dns = dns_get_record($dns);
    if ($dns) {
      foreach ($dns as $entry) {
        foreach ($entry as $value) {
          if ($value === $record) {
            return(True);
    }}}}
    return(False);
  }

  /* cURL use function (basically file_get_contents [with custom user angent and post request]) */
  function curl($url, $headers=False, $touch=True, $post=False, $wait=True) {
    if (is_file($url)) {
      if ($touch) { 
        preg_match('/(.*?)([^\/\\\]+)$/', $url, $match);
        touch($match[1].'.access-'.$match[2]); // if read file, update access time
      }
      return(file_get_contents($url));
    } else {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, False);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, $wait);
      curl_setopt($ch, CURLOPT_USERAGENT, 'The SkinSystem '.VER);
      if ($post) {
        curl_setopt($ch, CURLOPT_POST, True);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);}
      if ($headers) { curl_setopt($ch, CURLOPT_HTTPHEADER, (array)$headers); }
      $response = curl_exec($ch);
      curl_close($ch);
      if($response === False && $wait){
        printErrorAndDie('cURL ERROR : ' . curl_error($ch));
      }
      return($response);
    }
  }

  function spitResources($ext, $usecache=True) { // do you like resources?
    global $config;
    if ($ext === 'js') {
      $res = ['https://code.jquery.com/jquery-3.3.1.min.js' => 'b6c405aa91117aeed92e1055d9566502eef370e57ead76d8945d9ca81f2dc48ffc6996a38e9e01a9df95e83e4882f293',
              'https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js' => '07452097247e8cde8292fbc808e0768e869fe48e25de09bd194e87735a20e8bb3b8ba53f9a491a76e29a0619405eff64',
              'https://cdnjs.cloudflare.com/ajax/libs/three.js/94/three.min.js' => '31f2bcc47e0e0dc0a02b8ba327b9b3eb016eb5d2a00be3b6b845f060b1004605cbb0d030dee19f0d51c948f676886810',
              'https://cdn.jsdelivr.net/npm/sweetalert2@8.8.5' => 'b8d5a8062f041472eb18d443339e18961a79bff393a63ad3e908cbfa92b8807f1464ff30465a36f59720a9a96778f29b'];
      if (isset($config) && $config['misc_ui']['minerender']) {
        $res['https://raw.githubusercontent.com/InventivetalentDev/MineRender/f3784b7cdbc678c72603c5331ffd5aedc3d571b8/dist/skin.min.js'] = '380a12a20aa513797bff69366a445428c64d3932ad6f049957060b37c0cdb98103aea4494009707d4a1facb3901d8cf1';}
    } elseif ($ext === 'css') {
      $res = ['https://use.fontawesome.com/releases/v5.8.1/css/all.css' => 'e74a01507126be943ed655b8cb9ecf4c59a109a5e9d0c2f977ad0cd4ceee1f6fa7a948afcc879b86774e24adbc6a7bdf'];
    }
    foreach ($res as $url => $sha384){
      if ($usecache === True) {
        $url = cacheGrab($url, $url, '', False, ['sha384', $sha384]);}
      if ($ext === 'js') {
        echo '<script type="text/javascript" src="'.$url.'" integrity="sha384-'.base64_encode(hex2bin($sha384)).'" crossorigin="anonymous"></script>';
      } elseif ($ext === 'css') {
        echo '<link rel="stylesheet" href="'.$url.'" integrity="sha384-'.base64_encode(hex2bin($sha384)).'" crossorigin="anonymous">';
      }
    }
  }
?>