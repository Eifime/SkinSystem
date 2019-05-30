<?php ignore_user_abort(true); set_time_limit(0); // act as a diff process
$funcmap = unserialize(base64_decode($argv[2]));
$tmcnt = $funcmap;
$gcd = false;
foreach ($funcmap as $nm) { // calculate greatest common diviser
  if (!$gcd) { // get divisers list for first "number".
    for ($d=$nm; $d > 0; --$d) { 
      if ($nm%$d===0) {
        $gcd[] = $d;
  }}}
  else { // test devisers against other "number"s, remove diviser if not valid.
    foreach ($gcd as $d) {
      if ($nm%$d!==0) {
        unset($gcd[array_keys($gcd, $d)[0]]);
}}}}
$gcd = max($gcd);
while(time()-filemtime(__FILE__)>=(int)$argv[1]) { // loop while it's only instance(filemtime check)
  touch(__FILE__);
  require_once(__DIR__.'/libraries.php'); // require must come after touch, otherwise infinite feedback.
  sleep($gcd); // sleep greatest common diviser in seconds
  foreach ($tmcnt as $key => $value) { 
    $tmcnt[$key] = ($value+$gcd)%$funcmap[$key]; // add slept amount to time counters, 'modulo' when needed
    if ($value === 0 && function_exists($key)) { call_user_func($key); } // if 'modulo'-ed, trigger event.
  }
} ?>