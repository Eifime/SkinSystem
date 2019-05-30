<?php
  require_once(__DIR__ . '/libraries.php');
  if($config['am']['enabled'] == false){ printErrorAndDie('Unusable system'); }
  session_start();
  // https://github.com/AuthMe/AuthMeReloaded/tree/master/samples/website_integration
  function isValidPassword($password, $hash){ 
    global $config;
    $method = strtolower($config['am']['hash']['method']);
    $parts = explode('$', $hash);
    if (in_array($method, hash_algos())) { // known hash methods by php (sha256)
      return(count($parts) === 4 && $parts[3] === hash($method,  hash($method, $password) . $parts[2]));
    } elseif ($method === 'pbkdf2') { // pbkdf2
      $iter = $config['am']['hash']['pbkdf2rounds'];
      $chash = 'pbkdf2_sha256$'.$iter.'$'.$parts[2].'$'.hash_pbkdf2('sha256', $password, $parts[2], (int)$iter, 64, false);
      return(strtolower(substr($hash, 0, strlen($chash))) === strtolower($chash));
    } else { // bcrypt/argon2
      return(password_verify($password, $hash));
    }
  }
  /* logout Request */
  if(isset($_GET['logout'])){ session_destroy(); header('Location: '.WEB_ROOT); }

  /* If login request is valid */
  if(!empty($_POST['username']) && !empty($_POST['password'])){
    $username = strtolower($_POST['username']);
    $timeout = $config['am']['authsec']['threshold_hours']*60*60;
    $cdir = DIR_ROOT.'/'.$config['cache_dir']; if (!is_dir($cdir)) {mkdir($cdir, 0775, true);}
    // limit by ip, then by username
    $blk = [$cdir.'.loginratelimit-addr-'.IP, $cdir.'.loginratelimit-user-'.$username];
    $now = time();
    if (!is_file($blk[0]) or !is_file($blk[1]) or (max([filemtime($blk[0]), filemtime($blk[1])]) < $now)) {
      $password = $_POST['password'];
      /* Get user's password from AuthMe Storage */

      $tb = get_table_names('am', 0); // get names (authme)
      $pdo = query('am', "SELECT {$tb[2]} FROM {$tb[0]} WHERE {$tb[1]} = ?", [$username]);
      /* Analyse AuthMe Password Algorithm */
      if(isValidPassword($password, $pdo->fetch(PDO::FETCH_ASSOC)[$tb[2]])){
        $_SESSION['username'] = $username;
        foreach ($blk as $rlfl) {if (is_file($rlfl)) { unlink($rlfl); }}
        printDataAndDie(['title' => 'Login Successful!', 'refresh'=>True]);
      } else {
        /* Login failed, they should stop soon! */
        foreach ($blk as $index => $rlfl) {
          if (!is_file($rlfl) or filemtime($rlfl) < ($now - $timeout)) {
            $failvl = ($now - $timeout);
          } else {
            $failvl = filemtime($rlfl);
          }
          $failvl = ($failvl + ($timeout/($config['am']['authsec']['failed_attempts']+$index)) + 120);
          touch($rlfl, $failvl);
        }
        printErrorAndDie(['type' => 'warning', 'title' => 'Invalid credentials!', 'text' => 'Incorrect username/password']);
      }
    } else {
      printErrorAndDie(['title' => 'You\'re rate limited!', 'text' => 'Please come back later']);
    }
  }

  printErrorAndDie('Invalid Request');
?>
