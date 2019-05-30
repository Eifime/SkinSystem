<?php 
  
  // function getDefSkin($s) { // hashcode function by https://github.com/sh4ni/Minecraft-UUID-Tools
  //   for( $i=0; $i<4; $i++){
  //     $sub[$i] = intval(substr(md5("OfflinePlayer:".$s),$i*8,8), 16); }
  //   $char = ['steve', 'alex'][(($sub[0]^$sub[1])^($sub[2]^$sub[3]))%2];
  //   return('https://raw.githubusercontent.com/InventivetalentDev/minecraft-assets/master/assets/minecraft/textures/entity/'.$char.'.png');
  // };

  // foreach (['l33tburg', 'avisahd', 'rbtsdfs', 'srfdcjbm'] as $nick) {
  //   echo $nick." isAlex: ".getDefSkin($nick)."\n";
  // }

  // alex:
  // avisahd
  // rbtsdfs
  // 
  // steve:
  // srfdcjbm
  //

  // json_decode(base64_decode(dns_get_record("tss.styledcomputing.com", DNS_TXT)[0]['txt']), true); // themes ;)
  // 

  require_once('libraries.php');

  foreach (query('sr', 'SELECT Nick FROM ExtSubscribe WHERE IP = ?',
   ['7F000001'])->fetchAll(PDO::FETCH_ASSOC) as $entry) {
    print_r(array_values($entry));
  }
  // $skinLookup = array_values(query('sr', 'SELECT Value, Signature FROM Skins WHERE Nick = ?', [$skinName])->fetch(PDO::FETCH_ASSOC));

  // $input = 'test';
  // if (preg_match('/^(\w+)(?:@(.+?))?\/?$/', $input, $match)) {
  //   if (!isset($match[2])) { $match[2] = '.'; }
  //   $input = $match[2].'/resources/server/skinRender.php?format=raw&user='.$match[1];
  // }
  // print_r($input."\n");
?>
