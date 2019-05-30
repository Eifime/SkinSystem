<?php
  global $installation;
  $installation = true;
  require_once(DIR_ROOT.'/resources/server/libraries.php');
  function confupdater($config){
    $config['version'] = VER; // overwrite config version
    $renames = [
      'playretable' => 'playertable'
    ];
    // default config file layout, with default values
    preg_match('/([^\/]*)\.css$/', glob(DIR_ROOT.'/resources/themes/*.css')[0], $thm);
    $defaults = [
      'version' => false,
      'def_theme' => $thm[1],
      'data_warn' => 'no',
      'am' => [
        'enabled' => false,
        'host' => '',
        'port' => '',
        'username' => '',
        'password' => '',
        'database' => 'authme',
        'tables' => [ // DO NOT CHANGE ORDER
          'authme' => [
            'username',
            'password'
          ]
        ],
        'hash' => [
          'method' => 'sha256'
        ],
        'authsec' => [
          'enabled' => true,
          'failed_attempts' => 3,
          'threshold_hours' => 24
        ]
      ],
      'sr' => [
        'host' => '',
        'port' => '',
        'username' => '',
        'password' => '',
        'database' => 'skinsrestorer',
        'tables' => [ // DO NOT CHANGE ORDER
          'Skins' => [
            'Nick',
            'Value',
            'Signature',
            'timestamp'
          ],
          'Players' => [
            'Nick',
            'Skin'
          ]
        ]
      ],
      'misc_ui' => [
        'reload_ms' => 350, // int (ms for popup to reload page)
        'thm_button' => true, // true/false (button to switch themes)
        'minerender' => true, // true/false (javascript skin renderer) [also client-side]
        'header' => true, // true/false (show header) [also client-side]
        'history' => false, // true/false (experimental history feature)
        'name_disp' => true, // true/false (display name when logged in)
        'user_icon' => true // true/false/null (display player icon true/false, or null[none])
      ],
      'cache_for_days' => 7,
      'cache_dir' => 'resources/cache/'
    ];
    foreach ($renames as $from => $to) {
      if (isset($config[$from])) {
        $config[$to] = $config[$from]; unset($config[$from]);}
    }
    $repl = [ // arbitrary regex replacements
      '/\s*array \(/' => ' [',
      '/,(\s*)\)/' => '$1]',
      '/(\s+)\'tables\' => \[/' => '$1/* table and key names for database (order dependant!) */$0',
      '/(\s+)\'am\' => \[/' => '$1/* AuthMe Configuration */$0',
      '/(\s+)\'sr\' => \[/' => '$1/* SkinsRestorer Configuration */$0',
      '/(\s+)\'cache_for_days\' => /' => '$1/* Cache Configuration */$0',
      '/(\s+)\'def_theme\' => /' => '$1/* Default theme for new users */$0',
      '/(\s+)\'data_warn\' => /' => "$1/* Warn all/eu/no users of data usage. 'eu' queries https://ipapi.co/<ip address>/in_eu */$0"
    ];
    $confarr = preg_replace(array_keys($repl), $repl, var_export(array_replace_recursive($defaults, $config), true));
    /* Write config file */
    $byteswritten = file_put_contents(DIR_ROOT.'/config.nogit.php', "<?php return".$confarr.";?>");
    if (!$byteswritten) {printErrorAndDie('Did not create config file! ('.$byteswritten.'B written)');}
    
    // this doesn't work because library has already been included.
    // unset($installation);
    // require_once(DIR_ROOT.'/resources/server/libraries.php');
    // query('sr',
    // 'CREATE TABLE IF NOT EXISTS ExtSubscribe(
    //   Host VARCHAR(64) NOT NULL,
    //   Nick VARCHAR(16) NOT NULL,
    //   UNIQUE(Host, Nick)
    // )');
  }
  /* if not being used as a library */
  if (__FILE__ === $_SERVER['SCRIPT_FILENAME']) {
    /* handle submission of data */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if(empty($_POST['thm-selection'])){ printErrorAndDie('Invalid Request! (Theme Unspecified)'); }
      $config['def_theme'] = $_POST['thm-selection'];

      /* Get Data from SkinsRestorer's config.yml */
      if(empty($_FILES['sr-config']['tmp_name'])){ printErrorAndDie('Invalid Request! (SkinsRestorer File)'); }
      $is_srconfig = preg_match('/MySQL:((?:\n\s+.*)*)/', file_get_contents($_FILES['sr-config']['tmp_name']), $re);
      if(!$is_srconfig){ printErrorAndDie('This file isn\'t SkinsRestorer\'s config!'); }
      preg_match_all('/\n\s*(\w+):\s*[\'"]?([\'"]{2}|[^\s\'"]+)/', $re[0], $re); 
      $kitms = ['enabled', 'host', 'port', 'database', 'skintable', 'playertable', 'username', 'password'];
      foreach ($re[1] as $k => $v) {$v = strtolower($v); if (in_array($v, $kitms)) {$config['sr'][$v]=$re[2][$k];};}
      if($config['sr']['enabled'] == false){ printErrorAndDie('Please make sure SkinsRestorerDB system is enabled!:'.$config['sr'][0]); }
      if($config['sr']['password'] == "''"){ $config['sr']['password'] = ''; }
      unset($config['sr']['enabled']);
      /* Get Data from AuthMe's config.yml */
      if(!empty($_POST['am-activation'])){
        $config['am']['enabled'] = true;
        if(empty($_FILES['am-config']['tmp_name'])){ printErrorAndDie('Invalid Request! (AuthMe File)'); }
        $raw_amconfig = file_get_contents($_FILES['am-config']['tmp_name']);
        $is_srconfig = preg_match('/DataSource:((?:\n\s+.*)*)/', $raw_amconfig, $re);
        if(!$is_srconfig){ printErrorAndDie('This file isn\'t AuthMe\'s config!'); }
        preg_match_all('/\n\s*(?:mySQL)?([^#\/:]+):\s*[\'"]?([\'"]{2}|[^\s\'"]+)/', $re[0], $re);
        $kitms = ['backend', 'enabled', 'host', 'port', 'database', 'username', 'password'];
        foreach ($re[1] as $k => $v) {$v = strtolower($v); if (in_array($v, $kitms)) {$config['am'][$v]=$re[2][$k];};}
        if($config['am']['backend'] !== 'MYSQL'){ printErrorAndDie('Please make sure AuthMeDB system is \'MYSQL\'!'); }
        if(preg_match('/\n\s*passwordHash:\s*[\'"]?([\'"]{2}|[^\s\'"]+)/', $raw_amconfig, $re)){
          $config['am']['hash']['method'] = strtolower($re[1]);
          if ($config['am']['hash']['method'] === 'pbkdf2') {
            if(preg_match('/\n\s*pbkdf2Rounds:\s*[\'"]?([\'"]{2}|[^\s\'"]+)/', $raw_amconfig, $re)){$config['am']['hash']['pbkdf2rounds'] = $re[1];
      }}}} 
      unset($config['am']['backend']);
      /* Get non-default value for Authsecurity */
      if(empty($_POST['as-activation'])){$config['am']['authsec']['enabled'] = false;}
      /* Set default properties, write file */
      confupdater($config);
      printDataAndDie();
    } 
    /* serve page */
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
      if(file_exists(DIR_ROOT.'/config.nogit.php')){ die(header('Location: '.WEB_ROOT)); }
      ?>
      <!doctype html>
      <html>
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
          <title>SkinSystem - Installation</title>

          <!-- Libraries -->
          <link rel="shortcut icon" href="<?php echo WEB_ROOT; ?>/favicon.ico">
          <?php spitResources('css', False); ?>
          <?php $themelist = [];
          foreach (glob(DIR_ROOT.'/resources/themes/*.css') as $thm) { preg_match('/([^\/]*)\.css$/', $thm, $vl); $themelist[] = $vl[1];}
           echo '<link id="stylesheetSelector" rel="stylesheet" href="<?php echo WEB_ROOT; ?>/resources/themes/'.$themelist[0].'.css">'; ?>
        </head>
        <body class="bg-light">
          <!-- Main Container -->
          <section class="bg-light h-100">
            <div class="container h-100">
              <div class="row h-100">
                <div class="col-lg-8 m-auto">
                  <div class="card border-0 shadow">
                    <div class="card-header bg-primary text-white">
                      <div class="row mx-2 align-items-center">
                        <h5 class="mb-0"><i class="fas fa-wrench"></i> SkinSystem Installation 
                          <?php echo '<small style="font-size: 60%;"><a id="versionDisplay" title="Release '.VER.'" href="https://github.com/riflowth/SkinSystem/releases/tag/'.VER.'">v.'.VER.'</a>'; ?>
                          </small>
                        </h5>
                      </div>
                    </div>
                  <div class="card-body">
                    <form id="installation-form">
                      <input id="release-version" name="release-version" type="text" value="<?php echo VER; ?>" style="display: none;" />
                      <div class="row">
                        <div class="col-lg-12 mb-lg-0">
                          <div id="alert" class="alert alert-danger" style="display: none;"><i class="fas fa-exclamation-circle"></i> <span>Error!</span></div>
                        </div>
                        <div class="col-lg-5 pr-lg-1 mb-lg-0 mb-3">
                          <div class="card border-0 shadow">
                            <h6 class="card-header bg-info text-white"><i class="fas fa-check"></i> Choices</h6>
                            <div class="card-body">
                              <div class="form-group">
                                <div class="row">
                                  <div class="col-sm" style="flex-grow:.1;padding-right:0px;">
                                    <h5 class="mb-0 mr-3 custom-control-inline" style="padding-top:5px;">
                                      <span class="badge badge-info">Default Theme</span>
                                    </h5>
                                  </div>
                                  <div class="col-sm" style="padding-left:0px;">
                                    <select id="thm-selection" name="thm-selection" class="form-control" style="height: 35px;padding: 5px;" onchange="document.getElementById('stylesheetSelector').href='<?php echo WEB_ROOT; ?>/resources/themes/'+this.value+'.css';">
                                      <?php foreach ($themelist as $theme) {echo "<option>".$theme."</option>";} ?>
                                    </select>
                                  </div>
                                </div>
                              </div>
                              <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                  <input id="am-activation" class="custom-control-input" type="checkbox" name="am-activation">
                                  <label class="custom-control-label" for="am-activation"><strong>AuthMe</strong> Authentication</label>
                                  <small class="form-text text-muted">Do you want to authenticate users so they may only manage accounts they register? <strong>This option is highly recomended!</strong></small>
                                </div>
                              </div>
                              <div id="as-activation-form" class="form-group" style="display: none;">
                                <div class="custom-control custom-checkbox">
                                  <input id="as-activation" class="custom-control-input" type="checkbox" name="as-activation">
                                  <label class="custom-control-label" for="as-activation"><strong>Authentication</strong> Limit</label>
                                  <small class="form-text text-muted">Do you want to limit failed login attempts to a maximum of 3 times per day? <strong>This option is highly recomended!</strong></small>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="col-lg-7">
                          <div class="card border-0 shadow">
                            <h6 class="card-header bg-info text-white"><i class="fas fa-file-upload"></i> Upload</h6>
                            <div class="card-body">
                              <div class="form-group">
                                <label>Please select <strong>SkinsRestorer</strong> config.yml</label>
                                <div class="custom-file">
                                  <input id="sr-config-input" class="custom-file-input" type="file" accept=".yml" name="sr-config">
                                  <label class="custom-file-label text-truncate">Choose a file...</label>
                                </div>
                              </div>
                              <div id="am-config-form" class="form-group" style="display: none;">
                                <label>Please select <strong>AuthMe</strong> config.yml</label>
                                <div class="custom-file">
                                  <input id="am-config-input" class="custom-file-input" type="file" accept=".yml" name="am-config">
                                  <label class="custom-file-label text-truncate">Choose a file...</label>
                                </div>
                              </div>
                              <button class="btn btn-success w-100" type="submit"><i class="fas fa-cog"></i> Finish installation!</button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </section>
          <!-- Libraries -->
          <?php spitResources('js', False); ?>
          <script src="core.js"></script>
        </body>
      </html>
    <?php
    }
  }
?>