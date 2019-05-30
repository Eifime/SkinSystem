<?php 
  $redirect = [
    'https://ipfs.io/ipfs/QmcniBv7UQ4gGPQQW2BwbD4ZZHzN3o3tPuNLZCbBchd1zh' => 'rickroll',
    'http://vk.cc/7iPiB9' => 'doge',
    'https://youtu.be/Nn3CN1rcQ0E?t=0m2s' => 'yoshi',
    'https://youtu.be/qifs5n4hiqU' => 'hier nen euro',
    'https://youtu.be/UQK5VcP72VA' => 'bird sings a tune',
    'https://youtu.be/O6WE5C__a70' => 'on the wing',
    'https://youtu.be/icw57Op09pg' => 'j-turn in the crown vic',
    'https://youtu.be/-k39vx1wbIk' => 'insert clickbait title here',
    'https://youtu.be/JV-wckuLLDU' => 'cursed minecraft',
    'https://youtu.be/XYds-tgauC4' => 'what if it was purple?',
    'https://youtu.be/6-7NDP8V-6A' => 'kitchen gun',
    'https://youtu.be/TaDve409DLE?t=0m18s' => 'toaster fart',
    'https://youtu.be/T4r91mc8pbo' => 'jelli belli pet rat gumi candi',
    'https://media.tenor.co/images/dfaa889dd9d232fa5109816d9b531694/raw' => 'flying taco',
    'https://youtu.be/VoWRSM2_DF0' => 'pant apple'
  ];
  die(header("Location: ".array_rand($redirect,1)));
?>