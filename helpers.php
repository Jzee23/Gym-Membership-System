<?php
function tableExists($conn, $table)
{
    $t = mysqli_real_escape_string($conn, $table);
    $r = mysqli_query($conn, "SHOW TABLES LIKE '$t'");

    return $r && mysqli_num_rows($r) > 0;
}

function countRows($conn, $table)
{
    if (!tableExists($conn, $table)) {
        return 0;
    }

    $r = mysqli_query($conn, "SELECT COUNT(*) c FROM `$table`");
    if (!$r) {
        return 0;
    }

    $x = mysqli_fetch_assoc($r);

    return (int) $x['c'];
}

function esc($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
?>
