<?php
/**
 * Search-Replace DB per WordPress
 * Sostituisce http://localhost/rewrite con https://rewrite.alfredofiorillo.it
 * gestendo correttamente i dati serializzati PHP.
 *
 * ISTRUZIONI:
 *  1. Carica questo file nella root del sito online.
 *  2. Apri https://rewrite.alfredofiorillo.it/srdb.php nel browser.
 *  3. Cancella il file dal server dopo l'uso.
 */

define( 'ABSPATH', __DIR__ . '/' );
require_once ABSPATH . 'wp-config.php';

$old = 'http://localhost/rewrite';
$new = 'https://rewrite.alfredofiorillo.it';

if ( ! defined( 'DB_HOST' ) ) {
    die( 'Impossibile caricare wp-config.php' );
}

$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
if ( $mysqli->connect_error ) {
    die( 'Connessione DB fallita: ' . $mysqli->connect_error );
}
$mysqli->set_charset( 'utf8mb4' );

// Tabelle e colonne da aggiornare
$columns = [
    'wp_options'   => [ 'option_value' ],
    'wp_posts'     => [ 'post_content', 'post_excerpt', 'guid' ],
    'wp_postmeta'  => [ 'meta_value' ],
    'wp_usermeta'  => [ 'meta_value' ],
    'wp_comments'  => [ 'comment_content', 'comment_author_url' ],
    'wp_termmeta'  => [ 'meta_value' ],
];

// ----- Funzione di sostituzione serialization-aware -----
function replace_recursive( $data, $old, $new ) {
    if ( is_array( $data ) ) {
        $out = [];
        foreach ( $data as $k => $v ) {
            $out[ replace_recursive( $k, $old, $new ) ] = replace_recursive( $v, $old, $new );
        }
        return $out;
    }
    if ( is_object( $data ) ) {
        foreach ( get_object_vars( $data ) as $k => $v ) {
            $data->$k = replace_recursive( $v, $old, $new );
        }
        return $data;
    }
    if ( is_string( $data ) ) {
        return str_replace( $old, $new, $data );
    }
    return $data;
}

function safe_replace( $value, $old, $new ) {
    $unserialized = @unserialize( $value );
    if ( $unserialized !== false || $value === 'b:0;' ) {
        $replaced = replace_recursive( $unserialized, $old, $new );
        return serialize( $replaced );
    }
    return str_replace( $old, $new, $value );
}
// --------------------------------------------------------

$total_rows    = 0;
$total_changed = 0;
$log           = [];

foreach ( $columns as $table => $cols ) {
    foreach ( $cols as $col ) {
        $res = $mysqli->query( "SELECT COUNT(*) AS n FROM `{$table}` WHERE `{$col}` LIKE '%" . $mysqli->real_escape_string( $old ) . "%'" );
        $row = $res->fetch_assoc();
        $candidates = (int) $row['n'];
        if ( $candidates === 0 ) {
            continue;
        }

        $res2 = $mysqli->query( "SELECT * FROM `{$table}` WHERE `{$col}` LIKE '%" . $mysqli->real_escape_string( $old ) . "%'" );
        $changed = 0;

        // Ottieni chiave primaria della tabella
        $pk_res = $mysqli->query( "SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'" );
        $pk_row = $pk_res->fetch_assoc();
        $pk     = $pk_row ? $pk_row['Column_name'] : null;

        while ( $r = $res2->fetch_assoc() ) {
            $old_val = $r[ $col ];
            $new_val = safe_replace( $old_val, $old, $new );
            if ( $old_val !== $new_val && $pk && isset( $r[ $pk ] ) ) {
                $id  = (int) $r[ $pk ];
                $esc = $mysqli->real_escape_string( $new_val );
                $mysqli->query( "UPDATE `{$table}` SET `{$col}` = '{$esc}' WHERE `{$pk}` = {$id}" );
                $changed++;
                $total_changed++;
            }
        }

        $total_rows += $candidates;
        $log[] = "<tr><td>{$table}</td><td>{$col}</td><td>{$candidates}</td><td>{$changed}</td></tr>";
    }
}

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Search-Replace completato</title>
<style>
body { font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
th { background: #f5f5f5; }
.ok { color: green; font-weight: bold; }
.warn { color: orange; }
.delete { background: #fff3cd; padding: 12px; border-left: 4px solid #f0ad4e; margin-top: 20px; }
</style>
</head>
<body>
<h1>Search-Replace completato</h1>
<p><strong>Vecchio URL:</strong> <?= htmlspecialchars($old) ?></p>
<p><strong>Nuovo URL:</strong> <?= htmlspecialchars($new) ?></p>
<p class="ok">Righe aggiornate: <?= $total_changed ?> / <?= $total_rows ?> trovate con il vecchio URL</p>

<table>
<thead><tr><th>Tabella</th><th>Colonna</th><th>Trovate</th><th>Aggiornate</th></tr></thead>
<tbody><?= implode('', $log) ?></tbody>
</table>

<div class="delete">
    <strong>Importante:</strong> Cancella subito questo file dal server!<br>
    Percorso: <code>/srdb.php</code>
</div>
</body>
</html>
