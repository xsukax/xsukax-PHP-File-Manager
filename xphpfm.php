<?php
/**
 * xsukax PHP File Manager
 * https://github.com/xsukax/xsukax-PHP-File-Manager
 * License: GNU General Public License v3.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

$use_auth = true;
$auth_users = array('admin' => 'admin@123');
$use_highlightjs = true;
$highlightjs_style = 'github';
$default_timezone = 'UTC';
$root_path = $_SERVER['DOCUMENT_ROOT'];
$root_url = '';
$http_host = $_SERVER['HTTP_HOST'];
$iconv_input_encoding = 'CP1251';
$datetime_format = 'd.m.y H:i';

if (defined('FM_EMBED')) {
    $use_auth = false;
} else {
    @set_time_limit(600);
    date_default_timezone_set($default_timezone);
    ini_set('default_charset', 'UTF-8');
    if (version_compare(PHP_VERSION, '5.6.0', '<') && function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
    if (function_exists('mb_regex_encoding')) mb_regex_encoding('UTF-8');
    session_cache_limiter('');
    session_name('filemanager');
    session_start();
}

if (empty($auth_users)) $use_auth = false;

$is_https = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1))
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');

$root_path = rtrim($root_path, '\\/');
$root_path = str_replace('\\', '/', $root_path);
if (!@is_dir($root_path)) { echo sprintf('<h1>Root path "%s" not found!</h1>', fm_enc($root_path)); exit; }

$root_url = fm_clean_path($root_url);

defined('FM_ROOT_PATH') || define('FM_ROOT_PATH', $root_path);
defined('FM_ROOT_URL')  || define('FM_ROOT_URL',  ($is_https ? 'https' : 'http') . '://' . $http_host . (!empty($root_url) ? '/' . $root_url : ''));
defined('FM_SELF_URL')  || define('FM_SELF_URL',  ($is_https ? 'https' : 'http') . '://' . $http_host . $_SERVER['PHP_SELF']);

if (isset($_GET['logout'])) { unset($_SESSION['logged']); fm_redirect(FM_SELF_URL); }
if (isset($_GET['img']))    { fm_show_image($_GET['img']); }

if ($use_auth) {
    if (isset($_SESSION['logged'], $auth_users[$_SESSION['logged']])) {
        // authenticated
    } elseif (isset($_POST['fm_usr'], $_POST['fm_pwd'])) {
        sleep(1);
        if (isset($auth_users[$_POST['fm_usr']]) && $_POST['fm_pwd'] === $auth_users[$_POST['fm_usr']]) {
            session_regenerate_id(true);
            $_SESSION['logged'] = $_POST['fm_usr'];
            fm_set_msg('You are logged in');
            fm_redirect(FM_SELF_URL . '?p=');
        } else {
            unset($_SESSION['logged']);
            fm_set_msg('Wrong password', 'error');
            fm_redirect(FM_SELF_URL);
        }
    } else {
        unset($_SESSION['logged']);
        fm_show_header(); fm_show_message();
        ?><div class="path"><form action="" method="post" style="margin:10px;text-align:center">
            <input name="fm_usr" placeholder="Username" required autocomplete="off">
            <input type="password" name="fm_pwd" placeholder="Password" required autocomplete="off">
            <input type="submit" value="Login">
        </form></div><?php
        fm_show_footer(); exit;
    }
}

define('FM_IS_WIN', DIRECTORY_SEPARATOR == '\\');
if (!isset($_GET['p'])) fm_redirect(FM_SELF_URL . '?p=');

$p = isset($_GET['p']) ? $_GET['p'] : (isset($_POST['p']) ? $_POST['p'] : '');
$p = fm_clean_path($p);
define('FM_PATH', $p);
define('FM_USE_AUTH', $use_auth);
defined('FM_ICONV_INPUT_ENC')   || define('FM_ICONV_INPUT_ENC',   $iconv_input_encoding);
defined('FM_USE_HIGHLIGHTJS')   || define('FM_USE_HIGHLIGHTJS',   $use_highlightjs);
defined('FM_HIGHLIGHTJS_STYLE') || define('FM_HIGHLIGHTJS_STYLE', $highlightjs_style);
defined('FM_DATETIME_FORMAT')   || define('FM_DATETIME_FORMAT',   $datetime_format);

unset($p, $use_auth, $iconv_input_encoding, $use_highlightjs, $highlightjs_style);

// --- ACTIONS ---

if (isset($_GET['del'])) {
    $del = str_replace('/', '', fm_clean_path($_GET['del']));
    if ($del != '' && $del != '..' && $del != '.') {
        $path   = FM_ROOT_PATH . (FM_PATH != '' ? '/' . FM_PATH : '');
        $is_dir = is_dir($path . '/' . $del);
        fm_set_msg(fm_rdelete($path . '/' . $del)
            ? sprintf($is_dir ? 'Folder <b>%s</b> deleted' : 'File <b>%s</b> deleted', fm_enc($del))
            : sprintf($is_dir ? 'Folder <b>%s</b> not deleted' : 'File <b>%s</b> not deleted', fm_enc($del)), fm_rdelete($path . '/' . $del) ? 'ok' : 'error');
    } else { fm_set_msg('Wrong file or folder name', 'error'); }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

if (isset($_GET['new'])) {
    $new  = str_replace('/', '', fm_clean_path(strip_tags($_GET['new'])));
    $path = FM_ROOT_PATH . (FM_PATH != '' ? '/' . FM_PATH : '');
    if ($new != '' && $new != '..' && $new != '.') {
        $res = fm_mkdir($path . '/' . $new, false);
        if ($res === true)              fm_set_msg(sprintf('Folder <b>%s</b> created', fm_enc($new)));
        elseif ($res === $path.'/'.$new) fm_set_msg(sprintf('Folder <b>%s</b> already exists', fm_enc($new)), 'alert');
        else                             fm_set_msg(sprintf('Folder <b>%s</b> not created', fm_enc($new)), 'error');
    } else { fm_set_msg('Wrong folder name', 'error'); }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

if (isset($_GET['copy'], $_GET['finish'])) {
    $copy = fm_clean_path($_GET['copy']);
    if ($copy == '') { fm_set_msg('Source path not defined', 'error'); fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH)); }
    $from = FM_ROOT_PATH . '/' . $copy;
    $dest = FM_ROOT_PATH . (FM_PATH != '' ? '/' . FM_PATH : '') . '/' . basename($from);
    $move = isset($_GET['move']);
    if ($from != $dest) {
        $msg_from = trim(FM_PATH . '/' . basename($from), '/');
        if ($move) {
            $r = fm_rename($from, $dest);
            if ($r)          fm_set_msg(sprintf('Moved from <b>%s</b> to <b>%s</b>', fm_enc($copy), fm_enc($msg_from)));
            elseif ($r===null) fm_set_msg('File or folder with this path already exists', 'alert');
            else               fm_set_msg(sprintf('Error while moving from <b>%s</b> to <b>%s</b>', fm_enc($copy), fm_enc($msg_from)), 'error');
        } else {
            fm_set_msg(fm_rcopy($from, $dest)
                ? sprintf('Copied from <b>%s</b> to <b>%s</b>', fm_enc($copy), fm_enc($msg_from))
                : sprintf('Error while copying from <b>%s</b> to <b>%s</b>', fm_enc($copy), fm_enc($msg_from)),
                fm_rcopy($from, $dest) ? 'ok' : 'error');
        }
    } else { fm_set_msg('Paths must be not equal', 'alert'); }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

if (isset($_POST['file'], $_POST['copy_to'], $_POST['finish'])) {
    $path        = FM_ROOT_PATH . (FM_PATH != '' ? '/' . FM_PATH : '');
    $copy_to_path = FM_ROOT_PATH;
    $copy_to     = fm_clean_path($_POST['copy_to']);
    if ($copy_to != '') $copy_to_path .= '/' . $copy_to;
    if ($path == $copy_to_path) { fm_set_msg('Paths must be not equal', 'alert'); fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH)); }
    if (!is_dir($copy_to_path) && !fm_mkdir($copy_to_path, true)) { fm_set_msg('Unable to create destination folder', 'error'); fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH)); }
    $move = isset($_POST['move']); $errors = 0; $files = $_POST['file'];
    if (is_array($files) && count($files)) {
        foreach ($files as $f) {
            if ($f == '') continue;
            if ($move) { if (fm_rename($path.'/'.$f, $copy_to_path.'/'.$f) === false) $errors++; }
            else       { if (!fm_rcopy($path.'/'.$f, $copy_to_path.'/'.$f)) $errors++; }
        }
        fm_set_msg($errors == 0 ? ($move ? 'Selected files and folders moved' : 'Selected files and folders copied') : ($move ? 'Error while moving items' : 'Error while copying items'), $errors == 0 ? 'ok' : 'error');
    } else { fm_set_msg('Nothing selected', 'alert'); }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

if (isset($_GET['ren'], $_GET['to'])) {
    $old  = str_replace('/', '', fm_clean_path($_GET['ren']));
    $new  = str_replace('/', '', fm_clean_path($_GET['to']));
    $path = FM_ROOT_PATH . (FM_PATH != '' ? '/' . FM_PATH : '');
    if ($old != '' && $new != '') {
        fm_set_msg(fm_rename($path.'/'.$old, $path.'/'.$new)
            ? sprintf('Renamed from <b>%s</b> to <b>%s</b>', fm_enc($old), fm_enc($new))
            : sprintf('Error while renaming from <b>%s</b> to <b>%s</b>', fm_enc($old), fm_enc($new)),
            fm_rename($path.'/'.$old, $path.'/'.$new) ? 'ok' : 'error');
    } else { fm_set_msg('Names not set', 'error'); }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

if (isset($_GET['dl'])) {
    $dl   = str_replace('/', '', fm_clean_path($_GET['dl']));
    $path = FM_ROOT_PATH . (FM_PATH != '' ? '/' . FM_PATH : '');
    if ($dl != '' && is_file($path . '/' . $dl)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path.'/'.$dl) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path.'/'.$dl));
        readfile($path.'/'.$dl); exit;
    } else { fm_set_msg('File not found', 'error'); fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH)); }
}

if (isset($_POST['upl'])) {
    $path = FM_ROOT_PATH . (FM_PATH != '' ? '/' . FM_PATH : '');
    $errors = 0; $uploads = 0; $total = count($_FILES['upload']['name']);
    for ($i = 0; $i < $total; $i++) {
        $tmp = $_FILES['upload']['tmp_name'][$i];
        $safe = basename($_FILES['upload']['name'][$i]);
        if (empty($_FILES['upload']['error'][$i]) && !empty($tmp) && $tmp != 'none' && $safe != '') {
            move_uploaded_file($tmp, $path.'/'.$safe) ? $uploads++ : $errors++;
        }
    }
    if ($errors == 0 && $uploads > 0) fm_set_msg(sprintf('All files uploaded to <b>%s</b>', fm_enc($path)));
    elseif ($errors == 0) fm_set_msg('Nothing uploaded', 'alert');
    else fm_set_msg(sprintf('Error while uploading files. Uploaded files: %s', $uploads), 'error');
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

if (isset($_POST['group'], $_POST['delete'])) {
    $path = FM_ROOT_PATH . (FM_PATH != '' ? '/' . FM_PATH : '');
    $errors = 0; $files = $_POST['file'];
    if (is_array($files) && count($files)) {
        foreach ($files as $f) { if ($f != '' && !fm_rdelete($path.'/'.$f)) $errors++; }
        fm_set_msg($errors == 0 ? 'Selected files and folder deleted' : 'Error while deleting items', $errors == 0 ? 'ok' : 'error');
    } else { fm_set_msg('Nothing selected', 'alert'); }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

if (isset($_POST['group'], $_POST['zip'])) {
    $path = FM_ROOT_PATH . (FM_PATH != '' ? '/' . FM_PATH : '');
    if (!class_exists('ZipArchive')) { fm_set_msg('Operations with archives are not available', 'error'); fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH)); }
    $files = $_POST['file'];
    if (!empty($files)) {
        chdir($path);
        $zipname = (count($files) == 1) ? basename(reset($files)) . '_' . date('ymd_His') . '.zip' : 'archive_' . date('ymd_His') . '.zip';
        $zipper  = new FM_Zipper();
        $res     = $zipper->create($zipname, $files);
        fm_set_msg($res ? sprintf('Archive <b>%s</b> created', fm_enc($zipname)) : 'Archive not created', $res ? 'ok' : 'error');
    } else { fm_set_msg('Nothing selected', 'alert'); }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

if (isset($_GET['unzip'])) {
    $unzip = str_replace('/', '', fm_clean_path($_GET['unzip']));
    $path  = FM_ROOT_PATH . (FM_PATH != '' ? '/' . FM_PATH : '');
    if (!class_exists('ZipArchive')) { fm_set_msg('Operations with archives are not available', 'error'); fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH)); }
    if ($unzip != '' && is_file($path.'/'.$unzip)) {
        $zip_path = $path.'/'.$unzip;
        if (isset($_GET['tofolder'])) {
            $tofolder = pathinfo($zip_path, PATHINFO_FILENAME);
            if (fm_mkdir($path.'/'.$tofolder, true)) $path .= '/'.$tofolder;
        }
        $zipper = new FM_Zipper();
        $res    = $zipper->unzip($zip_path, $path);
        fm_set_msg($res ? 'Archive unpacked' : 'Archive not unpacked', $res ? 'ok' : 'error');
    } else { fm_set_msg('File not found', 'error'); }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

if (isset($_POST['chmod']) && !FM_IS_WIN) {
    $path = FM_ROOT_PATH . (FM_PATH != '' ? '/' . FM_PATH : '');
    $file = str_replace('/', '', fm_clean_path($_POST['chmod']));
    if ($file == '' || (!is_file($path.'/'.$file) && !is_dir($path.'/'.$file))) { fm_set_msg('File not found', 'error'); fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH)); }
    $mode = 0;
    if (!empty($_POST['ur'])) $mode |= 0400; if (!empty($_POST['uw'])) $mode |= 0200; if (!empty($_POST['ux'])) $mode |= 0100;
    if (!empty($_POST['gr'])) $mode |= 0040; if (!empty($_POST['gw'])) $mode |= 0020; if (!empty($_POST['gx'])) $mode |= 0010;
    if (!empty($_POST['or'])) $mode |= 0004; if (!empty($_POST['ow'])) $mode |= 0002; if (!empty($_POST['ox'])) $mode |= 0001;
    $ok = @chmod($path.'/'.$file, $mode);
    fm_set_msg($ok ? 'Permissions changed' : 'Permissions not changed', $ok ? 'ok' : 'error');
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// --- MAIN LISTING SETUP ---

$path = FM_ROOT_PATH . (FM_PATH != '' ? '/' . FM_PATH : '');
if (!is_dir($path)) fm_redirect(FM_SELF_URL . '?p=');

$parent  = fm_get_parent_path(FM_PATH);
$objects = is_readable($path) ? scandir($path) : array();
$folders = array(); $files = array();
if (is_array($objects)) {
    foreach ($objects as $file) {
        if ($file == '.' || $file == '..') continue;
        if (is_file($path.'/'.$file))      $files[] = $file;
        elseif (is_dir($path.'/'.$file))   $folders[] = $file;
    }
}
if (!empty($files))   natcasesort($files);
if (!empty($folders)) natcasesort($folders);

// --- VIEWS ---

if (isset($_GET['upload'])) {
    fm_show_header(); fm_show_nav_path(FM_PATH);
    ?><div class="path">
        <p><b>Uploading files</b></p>
        <p class="break-word">Destination folder: <?php echo fm_enc(fm_convert_win(FM_ROOT_PATH.'/'.FM_PATH)) ?></p>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="hidden" name="p" value="<?php echo fm_enc(FM_PATH) ?>">
            <input type="hidden" name="upl" value="1">
            <?php for ($i = 0; $i < 5; $i++): ?><input type="file" name="upload[]"><br><?php endfor; ?><br>
            <p>
                <button class="btn"><?php echo fm_icon('apply') ?> Upload</button> &nbsp;
                <b><a href="?p=<?php echo urlencode(FM_PATH) ?>"><?php echo fm_icon('cancel') ?> Cancel</a></b>
            </p>
        </form>
    </div><?php
    fm_show_footer(); exit;
}

if (isset($_POST['copy'])) {
    $copy_files = $_POST['file'];
    if (!is_array($copy_files) || empty($copy_files)) { fm_set_msg('Nothing selected', 'alert'); fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH)); }
    fm_show_header(); fm_show_nav_path(FM_PATH);
    ?><div class="path">
        <p><b>Copying</b></p>
        <form action="" method="post">
            <input type="hidden" name="p" value="<?php echo fm_enc(FM_PATH) ?>">
            <input type="hidden" name="finish" value="1">
            <?php foreach ($copy_files as $cf) echo '<input type="hidden" name="file[]" value="' . fm_enc($cf) . '">' . PHP_EOL; ?>
            <p class="break-word">Files: <b><?php echo implode('</b>, <b>', array_map('fm_enc', $copy_files)) ?></b></p>
            <p class="break-word">Source folder: <?php echo fm_enc(fm_convert_win(FM_ROOT_PATH.'/'.FM_PATH)) ?><br>
                <label for="inp_copy_to">Destination folder:</label>
                <?php echo FM_ROOT_PATH ?>/<input name="copy_to" id="inp_copy_to" value="<?php echo fm_enc(FM_PATH) ?>">
            </p>
            <p><label><input type="checkbox" name="move" value="1"> Move</label></p>
            <p>
                <button class="btn"><?php echo fm_icon('apply') ?> Copy</button> &nbsp;
                <b><a href="?p=<?php echo urlencode(FM_PATH) ?>"><?php echo fm_icon('cancel') ?> Cancel</a></b>
            </p>
        </form>
    </div><?php
    fm_show_footer(); exit;
}

if (isset($_GET['copy']) && !isset($_GET['finish'])) {
    $copy = fm_clean_path($_GET['copy']);
    if ($copy == '' || !file_exists(FM_ROOT_PATH.'/'.$copy)) { fm_set_msg('File not found', 'error'); fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH)); }
    fm_show_header(); fm_show_nav_path(FM_PATH);
    ?><div class="path">
        <p><b>Copying</b></p>
        <p class="break-word">
            Source path: <?php echo fm_enc(fm_convert_win(FM_ROOT_PATH.'/'.$copy)) ?><br>
            Destination folder: <?php echo fm_enc(fm_convert_win(FM_ROOT_PATH.'/'.FM_PATH)) ?>
        </p>
        <p>
            <b><a href="?p=<?php echo urlencode(FM_PATH) ?>&amp;copy=<?php echo urlencode($copy) ?>&amp;finish=1"><?php echo fm_icon('apply') ?> Copy</a></b> &nbsp;
            <b><a href="?p=<?php echo urlencode(FM_PATH) ?>&amp;copy=<?php echo urlencode($copy) ?>&amp;finish=1&amp;move=1"><?php echo fm_icon('apply') ?> Move</a></b> &nbsp;
            <b><a href="?p=<?php echo urlencode(FM_PATH) ?>"><?php echo fm_icon('cancel') ?> Cancel</a></b>
        </p>
        <p><i>Select folder:</i></p>
        <ul class="folders break-word">
            <?php if ($parent !== false): ?>
                <li><a href="?p=<?php echo urlencode($parent) ?>&amp;copy=<?php echo urlencode($copy) ?>"><?php echo fm_icon('arrow_up') ?> ..</a></li>
            <?php endif; ?>
            <?php foreach ($folders as $f): ?>
                <li><a href="?p=<?php echo urlencode(trim(FM_PATH.'/'.$f, '/')) ?>&amp;copy=<?php echo urlencode($copy) ?>"><?php echo fm_icon('folder') ?> <?php echo fm_enc(fm_convert_win($f)) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div><?php
    fm_show_footer(); exit;
}

if (isset($_GET['view'])) {
    $file = str_replace('/', '', fm_clean_path($_GET['view']));
    if ($file == '' || !is_file($path.'/'.$file)) { fm_set_msg('File not found', 'error'); fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH)); }
    fm_show_header(); fm_show_nav_path(FM_PATH);
    $file_url  = FM_ROOT_URL . fm_convert_win((FM_PATH != '' ? '/'.FM_PATH : '') . '/'.$file);
    $file_path = $path.'/'.$file;
    $ext       = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $mime_type = fm_get_mime_type($file_path);
    $filesize  = filesize($file_path);
    $is_zip = $is_image = $is_audio = $is_video = $is_text = false;
    $view_title = 'File'; $filenames = false; $content = '';
    if ($ext == 'zip')                                                                                               { $is_zip = true;   $view_title = 'Archive'; $filenames = fm_get_zif_info($file_path); }
    elseif (in_array($ext, fm_get_image_exts()))                                                                     { $is_image = true;  $view_title = 'Image'; }
    elseif (in_array($ext, fm_get_audio_exts()))                                                                     { $is_audio = true;  $view_title = 'Audio'; }
    elseif (in_array($ext, fm_get_video_exts()))                                                                     { $is_video = true;  $view_title = 'Video'; }
    elseif (in_array($ext, fm_get_text_exts()) || substr($mime_type, 0, 4) == 'text' || in_array($mime_type, fm_get_text_mimes())) { $is_text = true; $content = file_get_contents($file_path); }
    ?><div class="path">
        <p class="break-word"><b><?php echo $view_title ?> "<?php echo fm_enc(fm_convert_win($file)) ?>"</b></p>
        <p class="break-word">
            Full path: <?php echo fm_enc(fm_convert_win($file_path)) ?><br>
            File size: <?php echo fm_get_filesize($filesize) ?><?php if ($filesize >= 1000): ?> (<?php echo sprintf('%s bytes', $filesize) ?>)<?php endif; ?><br>
            MIME-type: <?php echo $mime_type ?><br>
            <?php
            if ($is_zip && $filenames !== false) {
                $tf = $tc = $tu = 0;
                foreach ($filenames as $fn) { if (!$fn['folder']) $tf++; $tc += $fn['compressed_size']; $tu += $fn['filesize']; }
                echo 'Files in archive: '.$tf.'<br>Total size: '.fm_get_filesize($tu).'<br>Size in archive: '.fm_get_filesize($tc).'<br>Compression: '.($tu > 0 ? round(($tc/$tu)*100) : 0).'%<br>';
            }
            if ($is_image) { $sz = getimagesize($file_path); echo 'Image sizes: '.($sz[0]??0).' x '.($sz[1]??0).'<br>'; }
            if ($is_text) {
                $is_utf8 = fm_is_utf8($content);
                if (function_exists('iconv') && !$is_utf8) $content = iconv(FM_ICONV_INPUT_ENC, 'UTF-8//IGNORE', $content);
                echo 'Charset: '.($is_utf8 ? 'utf-8' : '8 bit').'<br>';
            }
            ?>
        </p>
        <p>
            <b><a href="?p=<?php echo urlencode(FM_PATH) ?>&amp;dl=<?php echo urlencode($file) ?>"><?php echo fm_icon('download') ?> Download</a></b> &nbsp;
            <b><a href="<?php echo fm_enc($file_url) ?>" target="_blank"><?php echo fm_icon('chain') ?> Open</a></b> &nbsp;
            <?php if ($is_zip && $filenames !== false): $zip_name = pathinfo($file_path, PATHINFO_FILENAME); ?>
                <b><a href="?p=<?php echo urlencode(FM_PATH) ?>&amp;unzip=<?php echo urlencode($file) ?>"><?php echo fm_icon('apply') ?> Unpack</a></b> &nbsp;
                <b><a href="?p=<?php echo urlencode(FM_PATH) ?>&amp;unzip=<?php echo urlencode($file) ?>&amp;tofolder=1" title="Unpack to <?php echo fm_enc($zip_name) ?>"><?php echo fm_icon('apply') ?> Unpack to folder</a></b> &nbsp;
            <?php endif; ?>
            <b><a href="?p=<?php echo urlencode(FM_PATH) ?>"><?php echo fm_icon('goback') ?> Back</a></b>
        </p>
        <?php
        if ($is_zip) {
            if ($filenames !== false) {
                echo '<code class="maxheight">';
                foreach ($filenames as $fn) echo $fn['folder'] ? '<b>'.fm_enc($fn['name']).'</b><br>' : fm_enc($fn['name']).' ('.fm_get_filesize($fn['filesize']).')<br>';
                echo '</code>';
            } else { echo '<p>Error while fetching archive info</p>'; }
        } elseif ($is_image) {
            if (in_array($ext, array('gif','jpg','jpeg','png','bmp','ico','webp'))) echo '<p><img src="'.fm_enc($file_url).'" alt="" class="preview-img"></p>';
        } elseif ($is_audio) {
            echo '<p><audio src="'.fm_enc($file_url).'" controls preload="metadata"></audio></p>';
        } elseif ($is_video) {
            echo '<div class="preview-video"><video src="'.fm_enc($file_url).'" width="640" height="360" controls preload="metadata"></video></div>';
        } elseif ($is_text) {
            if (FM_USE_HIGHLIGHTJS) {
                $hljs_classes = array('shtml'=>'xml','htaccess'=>'apache','phtml'=>'php','lock'=>'json','svg'=>'xml');
                $hljs_class = isset($hljs_classes[$ext]) ? 'lang-'.$hljs_classes[$ext] : 'lang-'.$ext;
                if (empty($ext) || in_array(strtolower($file), fm_get_text_names()) || preg_match('#\.min\.(css|js)$#i', $file)) $hljs_class = 'nohighlight';
                $content = '<pre class="with-hljs"><code class="'.$hljs_class.'">'.fm_enc($content).'</code></pre>';
            } elseif (in_array($ext, array('php','php4','php5','phtml','phps'))) {
                $content = highlight_string($content, true);
            } else { $content = '<pre>'.fm_enc($content).'</pre>'; }
            echo $content;
        }
        ?>
    </div><?php
    fm_show_footer(); exit;
}

if (isset($_GET['chmod']) && !FM_IS_WIN) {
    $file = str_replace('/', '', fm_clean_path($_GET['chmod']));
    if ($file == '' || (!is_file($path.'/'.$file) && !is_dir($path.'/'.$file))) { fm_set_msg('File not found', 'error'); fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH)); }
    fm_show_header(); fm_show_nav_path(FM_PATH);
    $file_path = $path.'/'.$file;
    $mode = fileperms($file_path);
    ?><div class="path">
        <p><b>Change Permissions</b></p>
        <p>Full path: <?php echo fm_enc($file_path) ?></p>
        <form action="" method="post">
            <input type="hidden" name="p" value="<?php echo fm_enc(FM_PATH) ?>">
            <input type="hidden" name="chmod" value="<?php echo fm_enc($file) ?>">
            <table class="compact-table">
                <tr><td></td><td><b>Owner</b></td><td><b>Group</b></td><td><b>Other</b></td></tr>
                <tr>
                    <td style="text-align:right"><b>Read</b></td>
                    <td><label><input type="checkbox" name="ur" value="1"<?php echo ($mode&0400)?' checked':'' ?>></label></td>
                    <td><label><input type="checkbox" name="gr" value="1"<?php echo ($mode&0040)?' checked':'' ?>></label></td>
                    <td><label><input type="checkbox" name="or" value="1"<?php echo ($mode&0004)?' checked':'' ?>></label></td>
                </tr>
                <tr>
                    <td style="text-align:right"><b>Write</b></td>
                    <td><label><input type="checkbox" name="uw" value="1"<?php echo ($mode&0200)?' checked':'' ?>></label></td>
                    <td><label><input type="checkbox" name="gw" value="1"<?php echo ($mode&0020)?' checked':'' ?>></label></td>
                    <td><label><input type="checkbox" name="ow" value="1"<?php echo ($mode&0002)?' checked':'' ?>></label></td>
                </tr>
                <tr>
                    <td style="text-align:right"><b>Execute</b></td>
                    <td><label><input type="checkbox" name="ux" value="1"<?php echo ($mode&0100)?' checked':'' ?>></label></td>
                    <td><label><input type="checkbox" name="gx" value="1"<?php echo ($mode&0010)?' checked':'' ?>></label></td>
                    <td><label><input type="checkbox" name="ox" value="1"<?php echo ($mode&0001)?' checked':'' ?>></label></td>
                </tr>
            </table>
            <p>
                <button class="btn"><?php echo fm_icon('apply') ?> Change</button> &nbsp;
                <b><a href="?p=<?php echo urlencode(FM_PATH) ?>"><?php echo fm_icon('cancel') ?> Cancel</a></b>
            </p>
        </form>
    </div><?php
    fm_show_footer(); exit;
}

// --- MAIN FILE LISTING ---
fm_show_header();
fm_show_nav_path(FM_PATH);
fm_show_message();
$num_files = count($files); $num_folders = count($folders); $all_files_size = 0;
?>
<form action="" method="post">
<input type="hidden" name="p" value="<?php echo fm_enc(FM_PATH) ?>">
<input type="hidden" name="group" value="1">
<table><tr>
<th style="width:3%"><label><input type="checkbox" title="Invert selection" onclick="checkbox_toggle()"></label></th>
<th>Name</th><th style="width:10%">Size</th><th style="width:12%">Modified</th>
<?php if (!FM_IS_WIN): ?><th style="width:6%">Perms</th><th style="width:10%">Owner</th><?php endif; ?>
<th style="width:13%"></th></tr>
<?php
if ($parent !== false) echo '<tr><td></td><td colspan="'.(!FM_IS_WIN?'6':'4').'"><a href="?p='.urlencode($parent).'">'.fm_icon('arrow_up').' ..</a></td></tr>';

foreach ($folders as $f) {
    $is_link = is_link($path.'/'.$f);
    $img     = $is_link ? 'link_folder' : 'folder';
    $modif   = date(FM_DATETIME_FORMAT, filemtime($path.'/'.$f));
    $perms   = substr(decoct(fileperms($path.'/'.$f)), -4);
    $owner_str = fm_get_owner($path.'/'.$f);
    echo '<tr>';
    echo '<td><label><input type="checkbox" name="file[]" value="'.fm_enc($f).'"></label></td>';
    echo '<td><div class="filename"><a href="?p='.urlencode(trim(FM_PATH.'/'.$f, '/')).'">'.fm_icon($img).' '.fm_enc(fm_convert_win($f)).'</a>'.($is_link?' &rarr; <i>'.fm_enc(readlink($path.'/'.$f)).'</i>':'').'</div></td>';
    echo '<td>Folder</td><td>'.$modif.'</td>';
    if (!FM_IS_WIN) echo '<td><a title="Change Permissions" href="?p='.urlencode(FM_PATH).'&amp;chmod='.urlencode($f).'">'.$perms.'</a></td><td>'.fm_enc($owner_str).'</td>';
    echo '<td>';
    echo '<a title="Delete" href="?p='.urlencode(FM_PATH).'&amp;del='.urlencode($f).'" onclick="return confirm(\'Delete folder?\');">'.fm_icon('cross').'</a> ';
    echo '<a title="Rename" href="#" onclick="rename(\''.fm_enc(FM_PATH).'\',\''.fm_enc($f).'\');return false;">'.fm_icon('rename').'</a> ';
    echo '<a title="Copy to..." href="?p=&amp;copy='.urlencode(trim(FM_PATH.'/'.$f, '/')).'">'.fm_icon('copy').'</a> ';
    echo '<a title="Direct link" href="'.fm_enc(FM_ROOT_URL.(FM_PATH!=''?'/'.FM_PATH:'').'/'.$f.'/').'" target="_blank">'.fm_icon('chain').'</a>';
    echo '</td></tr>';
    flush();
}

foreach ($files as $f) {
    $is_link      = is_link($path.'/'.$f);
    $img          = $is_link ? 'link_file' : fm_get_file_icon_class($path.'/'.$f);
    $modif        = date(FM_DATETIME_FORMAT, filemtime($path.'/'.$f));
    $filesize_raw = filesize($path.'/'.$f);
    $filesize     = fm_get_filesize($filesize_raw);
    $filelink     = '?p='.urlencode(FM_PATH).'&view='.urlencode($f);
    $all_files_size += $filesize_raw;
    $perms        = substr(decoct(fileperms($path.'/'.$f)), -4);
    $owner_str    = fm_get_owner($path.'/'.$f);
    echo '<tr>';
    echo '<td><label><input type="checkbox" name="file[]" value="'.fm_enc($f).'"></label></td>';
    echo '<td><div class="filename"><a href="'.fm_enc($filelink).'" title="File info">'.fm_icon($img).' '.fm_enc(fm_convert_win($f)).'</a>'.($is_link?' &rarr; <i>'.fm_enc(readlink($path.'/'.$f)).'</i>':'').'</div></td>';
    echo '<td><span class="gray" title="'.sprintf('%s bytes', $filesize_raw).'">'.$filesize.'</span></td>';
    echo '<td>'.$modif.'</td>';
    if (!FM_IS_WIN) echo '<td><a title="Change Permissions" href="?p='.urlencode(FM_PATH).'&amp;chmod='.urlencode($f).'">'.$perms.'</a></td><td>'.fm_enc($owner_str).'</td>';
    echo '<td>';
    echo '<a title="Delete" href="?p='.urlencode(FM_PATH).'&amp;del='.urlencode($f).'" onclick="return confirm(\'Delete file?\');">'.fm_icon('cross').'</a> ';
    echo '<a title="Rename" href="#" onclick="rename(\''.fm_enc(FM_PATH).'\',\''.fm_enc($f).'\');return false;">'.fm_icon('rename').'</a> ';
    echo '<a title="Copy to..." href="?p='.urlencode(FM_PATH).'&amp;copy='.urlencode(trim(FM_PATH.'/'.$f, '/')).'">'.fm_icon('copy').'</a> ';
    echo '<a title="Direct link" href="'.fm_enc(FM_ROOT_URL.(FM_PATH!=''?'/'.FM_PATH:'').'/'.$f).'" target="_blank">'.fm_icon('chain').'</a> ';
    echo '<a title="Download" href="?p='.urlencode(FM_PATH).'&amp;dl='.urlencode($f).'">'.fm_icon('download').'</a>';
    echo '</td></tr>';
    flush();
}

if (empty($folders) && empty($files)) {
    echo '<tr><td></td><td colspan="'.(!FM_IS_WIN?'6':'4').'"><em>Folder is empty</em></td></tr>';
} else {
    echo '<tr><td class="gray"></td><td class="gray" colspan="'.(!FM_IS_WIN?'6':'4').'">Full size: <span title="'.sprintf('%s bytes', $all_files_size).'">'.fm_get_filesize($all_files_size).'</span>, files: '.$num_files.', folders: '.$num_folders.'</td></tr>';
}
?>
</table>
<p class="path">
    <a href="#" onclick="select_all();return false;"><?php echo fm_icon('checkbox') ?> Select all</a> &nbsp;
    <a href="#" onclick="unselect_all();return false;"><?php echo fm_icon('checkbox_uncheck') ?> Unselect all</a> &nbsp;
    <a href="#" onclick="invert_all();return false;"><?php echo fm_icon('checkbox_invert') ?> Invert selection</a>
</p>
<p>
    <input type="submit" name="delete" value="Delete" onclick="return confirm('Delete selected files and folders?')">
    <input type="submit" name="zip" value="Pack" onclick="return confirm('Create archive?')">
    <input type="submit" name="copy" value="Copy">
</p>
</form>
<?php
fm_show_footer();

// --- FUNCTIONS ---

function fm_icon($name) {
    $s  = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" style="display:inline-block;vertical-align:middle;flex-shrink:0">';
    $e  = '</svg>';
    $fld = '<path fill="#54aeff" d="M.5 5.5A1.5 1.5 0 0 1 2 4h4.086L7.5 5.5H14A1.5 1.5 0 0 1 15.5 7V13A1.5 1.5 0 0 1 14 14.5H2A1.5 1.5 0 0 1 .5 13z"/>';
    $doc = '<path fill="#d0d7de" d="M3 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V5L9 1H3z"/><path fill="#8c959f" d="M9 1v4h4"/>';
    $fi  = function($c) use ($s,$e) {
        return $s.'<path fill="'.$c.'" d="M3 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V5L9 1H3z"/><path fill="rgba(0,0,0,.18)" d="M9 1v4h4"/>'.$e;
    };
    switch ($name) {
        case 'home':
            return $s.'<path fill="#57606a" d="M8 1.5L.5 8.5H3V15h3.5V11h3v4H13V8.5h2.5z"/>'.$e;
        case 'folder':
            return $s.$fld.$e;
        case 'link_folder':
            return $s.$fld.'<circle cx="13" cy="13" r="2.8" fill="white" stroke="#0969da" stroke-width="1.2"/><path stroke="#0969da" stroke-width="1" fill="none" d="M11.8 13h2.4M13 11.8v2.4"/>'.$e;
        case 'folder_add':
            return $s.$fld.'<path stroke="white" stroke-width="1.5" stroke-linecap="round" fill="none" d="M8 8v4.5M5.75 10.25h4.5"/>'.$e;
        case 'folder_open':
            return $s.'<path fill="#54aeff" d="M.5 4A1.5 1.5 0 0 1 2 2.5h3.5L7 4H15v2.5H.5zM.5 7.5l1.75 7h12l1.75-7z"/>'.$e;
        case 'upload':
            return $s.'<path stroke="#57606a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" d="M8 11.5V2M3.5 6.5 8 2l4.5 4.5M1.5 14h13"/>'.$e;
        case 'arrow_up':
            return $s.'<path stroke="#57606a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" d="M8 13V2.5M3 7.5l5-5 5 5"/>'.$e;
        case 'separator':
            return $s.'<path stroke="#8c959f" stroke-width="1.5" stroke-linecap="round" fill="none" d="M6 3l4 5-4 5"/>'.$e;
        case 'cross':
            return $s.'<path stroke="#cf222e" stroke-width="1.5" stroke-linecap="round" fill="none" d="M3 3l10 10M13 3 3 13"/>'.$e;
        case 'copy':
            return $s.'<rect x="4.5" y="4.5" width="9" height="10" rx="1.5" fill="white" stroke="#57606a" stroke-width="1.5"/><rect x="2.5" y="2.5" width="9" height="10" rx="1.5" fill="#eaeef2" stroke="#57606a" stroke-width="1.5"/>'.$e;
        case 'apply':
            return $s.'<path stroke="#1a7f37" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" d="M1.5 9 6 13.5l8.5-10"/>'.$e;
        case 'cancel':
            return $s.'<path stroke="#57606a" stroke-width="1.5" stroke-linecap="round" fill="none" d="M3 3l10 10M13 3 3 13"/>'.$e;
        case 'rename':
            return $s.'<path fill="#57606a" d="M11.5 2.5a1.5 1.5 0 0 1 2.12 0l.88.88a1.5 1.5 0 0 1 0 2.12L6.25 13.75 2.5 14.5l.75-3.75z"/>'.$e;
        case 'checkbox':
            return $s.'<rect x="2" y="2" width="12" height="12" rx="2" fill="white" stroke="#0969da" stroke-width="1.5"/><path stroke="#0969da" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none" d="M4.5 8l2.5 2.5 5-5"/>'.$e;
        case 'checkbox_uncheck':
            return $s.'<rect x="2" y="2" width="12" height="12" rx="2" fill="white" stroke="#57606a" stroke-width="1.5"/>'.$e;
        case 'checkbox_invert':
            return $s.'<rect x="2" y="2" width="12" height="12" rx="2" fill="#eaeef2" stroke="#57606a" stroke-width="1.5"/><line x1="4.5" y1="8" x2="11.5" y2="8" stroke="#57606a" stroke-width="1.5" stroke-linecap="round"/>'.$e;
        case 'download':
            return $s.'<path stroke="#57606a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" d="M8 1.5v9M3.5 7.5l4.5 5 4.5-5M1.5 14h13"/>'.$e;
        case 'goback':
            return $s.'<path stroke="#57606a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" d="M10.5 2.5 5 8l5.5 5.5M5 8h10"/>'.$e;
        case 'logout':
            return $s.'<path stroke="#57606a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" d="M10 2h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1h-3M6.5 11 10.5 8l-4-3M10.5 8H1"/>'.$e;
        case 'chain':
            return $s.'<path stroke="#57606a" stroke-width="1.5" stroke-linecap="round" fill="none" d="M6.5 9.5a2.5 2.5 0 0 0 3.5.5l2.5-2a2.5 2.5 0 0 0-3.5-3.5L7.5 5.5M9.5 6.5a2.5 2.5 0 0 0-3.5-.5l-2.5 2a2.5 2.5 0 0 0 3.5 3.5L8.5 10.5"/>'.$e;
        case 'document':
            return $s.$doc.$e;
        case 'link_file':
            return $s.$doc.'<circle cx="12.5" cy="12.5" r="2.8" fill="white" stroke="#0969da" stroke-width="1.2"/><path stroke="#0969da" stroke-width="1" fill="none" d="M11.3 12.5h2.4M12.5 11.3v2.4"/>'.$e;
        case 'file_text':        return $fi('#8c959f');
        case 'file_php':         return $fi('#7a43b6');
        case 'file_html':        return $fi('#e34c26');
        case 'file_code':        return $fi('#0969da');
        case 'file_image':       return $fi('#2da44e');
        case 'file_zip':         return $fi('#bf8700');
        case 'file_pdf':         return $fi('#cf222e');
        case 'file_music':       return $fi('#1b7c83');
        case 'file_film':        return $fi('#424a53');
        case 'file_word':        return $fi('#185abd');
        case 'file_excel':       return $fi('#107c41');
        case 'file_powerpoint':  return $fi('#c43e1c');
        case 'file_csv':         return $fi('#217346');
        case 'file_font':        return $fi('#57606a');
        case 'file_playlist':    return $fi('#0550ae');
        case 'file_outlook':     return $fi('#0072c6');
        case 'file_illustrator': return $fi('#ff9a00');
        case 'file_photoshop':   return $fi('#31a8ff');
        case 'file_application': return $fi('#6e7781');
        case 'file_terminal':    return $fi('#1f2328');
        case 'file_swf':
        case 'file_flash':       return $fi('#e25b20');
        default:                 return $s.$doc.$e;
    }
}

function fm_get_owner($path) {
    $uid = @fileowner($path);
    $gid = @filegroup($path);
    if ($uid === false) return '?:?';
    if (function_exists('posix_getpwuid') && function_exists('posix_getgrgid')) {
        $u = posix_getpwuid($uid);
        $g = posix_getgrgid($gid);
        $uname = ($u && isset($u['name'])) ? $u['name'] : $uid;
        $gname = ($g && isset($g['name'])) ? $g['name'] : $gid;
    } else {
        $uname = $uid;
        $gname = $gid;
    }
    return $uname . ':' . $gname;
}

function fm_rdelete($path) {
    if (is_link($path)) return unlink($path);
    if (is_dir($path)) {
        $ok = true; $objects = scandir($path);
        if (is_array($objects)) foreach ($objects as $file) if ($file != '.' && $file != '..' && !fm_rdelete($path.'/'.$file)) $ok = false;
        return $ok ? rmdir($path) : false;
    }
    if (is_file($path)) return unlink($path);
    return false;
}

function fm_rchmod($path, $filemode, $dirmode) {
    if (is_dir($path)) {
        if (!chmod($path, $dirmode)) return false;
        $objects = scandir($path);
        if (is_array($objects)) foreach ($objects as $file) if ($file != '.' && $file != '..' && !fm_rchmod($path.'/'.$file, $filemode, $dirmode)) return false;
        return true;
    }
    if (is_link($path)) return true;
    if (is_file($path)) return chmod($path, $filemode);
    return false;
}

function fm_rename($old, $new) { return (!file_exists($new) && file_exists($old)) ? rename($old, $new) : null; }

function fm_rcopy($path, $dest, $upd = true, $force = true) {
    if (is_dir($path)) {
        if (!fm_mkdir($dest, $force)) return false;
        $ok = true; $objects = scandir($path);
        if (is_array($objects)) foreach ($objects as $file) if ($file != '.' && $file != '..' && !fm_rcopy($path.'/'.$file, $dest.'/'.$file)) $ok = false;
        return $ok;
    }
    if (is_file($path)) return fm_copy($path, $dest, $upd);
    return false;
}

function fm_mkdir($dir, $force) {
    if (file_exists($dir)) { if (is_dir($dir)) return $dir; if (!$force) return false; unlink($dir); }
    return mkdir($dir, 0777, true);
}

function fm_copy($f1, $f2, $upd) {
    $time1 = filemtime($f1);
    if (file_exists($f2) && filemtime($f2) >= $time1 && $upd) return false;
    $ok = copy($f1, $f2);
    if ($ok) touch($f2, $time1);
    return $ok;
}

function fm_get_mime_type($file_path) {
    if (function_exists('finfo_open')) { $fi = finfo_open(FILEINFO_MIME_TYPE); $m = finfo_file($fi, $file_path); finfo_close($fi); return $m; }
    if (function_exists('mime_content_type')) return mime_content_type($file_path);
    if (!stristr(ini_get('disable_functions'), 'shell_exec')) return shell_exec('file -bi ' . escapeshellarg($file_path));
    return '--';
}

function fm_redirect($url, $code = 302) { header('Location: ' . $url, true, $code); exit; }

function fm_clean_path($path) {
    $path = trim($path); $path = trim($path, '\\/');
    $path = str_replace(array('../', '..\\'), '', $path);
    if ($path == '..') $path = '';
    return str_replace('\\', '/', $path);
}

function fm_get_parent_path($path) {
    $path = fm_clean_path($path);
    if ($path != '') { $a = explode('/', $path); if (count($a) > 1) return implode('/', array_slice($a, 0, -1)); return ''; }
    return false;
}

function fm_get_filesize($size) {
    if ($size < 1000)                         return sprintf('%s B',   $size);
    if (($size/1024) < 1000)                  return sprintf('%s KiB', round($size/1024, 2));
    if (($size/1024/1024) < 1000)             return sprintf('%s MiB', round($size/1024/1024, 2));
    if (($size/1024/1024/1024) < 1000)        return sprintf('%s GiB', round($size/1024/1024/1024, 2));
    return sprintf('%s TiB', round($size/1024/1024/1024/1024, 2));
}

function fm_get_zif_info($path) {
    if (!class_exists('ZipArchive')) return false;
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return false;
    $fns = array();
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $s = $zip->statIndex($i);
        $fns[] = array('name'=>$s['name'],'filesize'=>$s['size'],'compressed_size'=>$s['comp_size'],'folder'=>substr($s['name'],-1)=='/');
    }
    $zip->close(); return $fns;
}

function fm_enc($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function fm_set_msg($msg, $status = 'ok') { $_SESSION['message'] = $msg; $_SESSION['status'] = $status; }
function fm_is_utf8($string) { return preg_match('//u', $string); }

function fm_convert_win($filename) {
    if (FM_IS_WIN && function_exists('iconv')) $filename = iconv(FM_ICONV_INPUT_ENC, 'UTF-8//IGNORE', $filename);
    return $filename;
}

function fm_get_file_icon_class($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'ico': case 'gif': case 'jpg': case 'jpeg': case 'jpc': case 'jp2':
        case 'jpx': case 'xbm': case 'wbmp': case 'png': case 'bmp': case 'tif': case 'tiff': case 'webp': return 'file_image';
        case 'txt': case 'css': case 'ini': case 'conf': case 'log': case 'htaccess':
        case 'passwd': case 'ftpquota': case 'sql': case 'js': case 'json': case 'sh':
        case 'config': case 'twig': case 'tpl': case 'md': case 'gitignore':
        case 'less': case 'sass': case 'scss': case 'c': case 'cpp': case 'cs': case 'py':
        case 'map': case 'lock': case 'dtd': return 'file_text';
        case 'zip': case 'rar': case 'gz': case 'tar': case '7z': return 'file_zip';
        case 'php': case 'php4': case 'php5': case 'phps': case 'phtml': return 'file_php';
        case 'htm': case 'html': case 'shtml': case 'xhtml': return 'file_html';
        case 'xml': case 'xsl': case 'svg': return 'file_code';
        case 'wav': case 'mp3': case 'mp2': case 'm4a': case 'aac': case 'ogg':
        case 'oga': case 'wma': case 'mka': case 'flac': case 'ac3': case 'tds': return 'file_music';
        case 'm3u': case 'm3u8': case 'pls': case 'cue': return 'file_playlist';
        case 'avi': case 'mpg': case 'mpeg': case 'mp4': case 'm4v': case 'flv':
        case 'f4v': case 'ogm': case 'ogv': case 'mov': case 'mkv': case '3gp': case 'asf': case 'wmv': case 'webm': return 'file_film';
        case 'eml': case 'msg': return 'file_outlook';
        case 'xls': case 'xlsx': return 'file_excel';
        case 'csv': return 'file_csv';
        case 'doc': case 'docx': return 'file_word';
        case 'ppt': case 'pptx': return 'file_powerpoint';
        case 'ttf': case 'ttc': case 'otf': case 'woff': case 'woff2': case 'eot': case 'fon': return 'file_font';
        case 'pdf': return 'file_pdf';
        case 'psd': return 'file_photoshop';
        case 'ai': case 'eps': return 'file_illustrator';
        case 'fla': return 'file_flash';
        case 'swf': return 'file_swf';
        case 'exe': case 'msi': return 'file_application';
        case 'bat': return 'file_terminal';
        default: return 'document';
    }
}

function fm_get_image_exts()  { return array('ico','gif','jpg','jpeg','jpc','jp2','jpx','xbm','wbmp','png','bmp','tif','tiff','psd','webp'); }
function fm_get_video_exts()  { return array('webm','mp4','m4v','ogm','ogv','mov'); }
function fm_get_audio_exts()  { return array('wav','mp3','ogg','m4a'); }
function fm_get_text_exts()   {
    return array('txt','css','ini','conf','log','htaccess','passwd','ftpquota','sql','js','json','sh','config',
        'php','php4','php5','phps','phtml','htm','html','shtml','xhtml','xml','xsl','m3u','m3u8','pls','cue',
        'eml','msg','csv','bat','twig','tpl','md','gitignore','less','sass','scss','c','cpp','cs','py','map','lock','dtd','svg');
}
function fm_get_text_mimes()  { return array('application/xml','application/javascript','application/x-javascript','image/svg+xml','message/rfc822'); }
function fm_get_text_names()  { return array('license','readme','authors','contributors','changelog'); }

class FM_Zipper {
    private $zip;
    public function __construct() { $this->zip = new ZipArchive(); }
    public function create($filename, $files) {
        if ($this->zip->open($filename, ZipArchive::CREATE) !== true) return false;
        foreach ((array)$files as $f) { if (!$this->addFileOrDir($f)) { $this->zip->close(); return false; } }
        $this->zip->close(); return true;
    }
    public function unzip($filename, $path) {
        if ($this->zip->open($filename) !== true) return false;
        $ok = $this->zip->extractTo($path); $this->zip->close(); return $ok;
    }
    private function addFileOrDir($filename) {
        if (is_file($filename)) return $this->zip->addFile($filename);
        if (is_dir($filename))  return $this->addDir($filename);
        return false;
    }
    private function addDir($path) {
        if (!$this->zip->addEmptyDir($path)) return false;
        $objects = scandir($path);
        if (!is_array($objects)) return false;
        foreach ($objects as $file) {
            if ($file == '.' || $file == '..') continue;
            if (is_dir($path.'/'.$file)) { if (!$this->addDir($path.'/'.$file)) return false; }
            elseif (is_file($path.'/'.$file)) { if (!$this->zip->addFile($path.'/'.$file)) return false; }
        }
        return true;
    }
}

function fm_show_nav_path($path) {
    ?>
<div class="path">
<div class="float-right">
    <a title="Upload files" href="?p=<?php echo urlencode(FM_PATH) ?>&amp;upload"><?php echo fm_icon('upload') ?></a>
    <a title="New folder" href="#" onclick="newfolder('<?php echo fm_enc(FM_PATH) ?>');return false;"><?php echo fm_icon('folder_add') ?></a>
    <?php if (FM_USE_AUTH): ?><a title="Logout" href="?logout=1"><?php echo fm_icon('logout') ?></a><?php endif; ?>
</div>
<?php
    $path    = fm_clean_path($path);
    $breadcrumb = '<a href="?p=" title="' . fm_enc(FM_ROOT_PATH) . '">' . fm_icon('home') . '</a>';
    $sep     = fm_icon('separator');
    if ($path != '') {
        $exploded = explode('/', $path); $parent = ''; $parts = array();
        foreach ($exploded as $seg) {
            $parent  = trim($parent.'/'.$seg, '/');
            $parts[] = "<a href='?p=" . urlencode($parent) . "'>" . fm_enc(fm_convert_win($seg)) . "</a>";
        }
        $breadcrumb .= $sep . implode($sep, $parts);
    }
    echo '<div class="break-word">' . $breadcrumb . '</div>';
    ?>
</div>
<?php
}

function fm_show_message() {
    if (isset($_SESSION['message'])) {
        $class = isset($_SESSION['status']) ? $_SESSION['status'] : 'ok';
        echo '<p class="message ' . $class . '">' . $_SESSION['message'] . '</p>';
        unset($_SESSION['message'], $_SESSION['status']);
    }
}

function fm_show_header() {
    header('Content-Type: text/html; charset=utf-8');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
    header('Pragma: no-cache');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>xsukax PHP File Manager</title>
<style>
html,body,div,span,p,pre,a,code,em,img,small,strong,ol,ul,li,form,label,table,tr,th,td{margin:0;padding:0;vertical-align:baseline;outline:none;font-size:100%;background:transparent;border:none;text-decoration:none}
html{overflow-y:scroll}
body{padding:0;font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans",Helvetica,Arial,sans-serif;color:#1f2328;background:#f6f8fa}
input,select,textarea,button{font-size:inherit;font-family:inherit}
a{color:#0969da;text-decoration:none}
a:hover{color:#0550ae;text-decoration:underline}
img{vertical-align:middle;border:none}
span.gray{color:#57606a}
small{font-size:11px;color:#57606a}
p{margin-bottom:10px}
ul{list-style-type:none;margin:0 0 10px}
ul li{padding:3px 0}
table{border-collapse:collapse;border-spacing:0;margin-bottom:10px;width:100%}
th,td{padding:6px 10px;text-align:left;vertical-align:top;border:1px solid #d0d7de;background:#fff;white-space:nowrap}
th{background:#f6f8fa;font-weight:600;color:#1f2328}
td.gray{background-color:#f6f8fa}
td.gray span{color:#1f2328}
tr:hover td{background-color:#f3f6fc}
tr:hover td.gray{background-color:#f6f8fa}
code,pre{display:block;margin-bottom:10px;font:13px/1.5 ui-monospace,SFMono-Regular,"SF Mono",Menlo,Consolas,"Liberation Mono",monospace;border:1px solid #d0d7de;padding:8px 12px;overflow:auto;background:#f6f8fa;border-radius:6px}
pre.with-hljs{padding:0}
pre.with-hljs code{margin:0;border:0;overflow:visible}
code.maxheight,pre.maxheight{max-height:512px}
input[type="checkbox"]{margin:0;padding:0;accent-color:#0969da}
input[type="text"],input[type="password"]{padding:5px 10px;border:1px solid #d0d7de;border-radius:6px;background:#fff;color:#1f2328;outline:none}
input[type="text"]:focus,input[type="password"]:focus{border-color:#0969da;box-shadow:0 0 0 3px rgba(9,105,218,.1)}
input[type="submit"]{padding:5px 16px;border:1px solid #d0d7de;border-radius:6px;background:#f6f8fa;color:#1f2328;cursor:pointer;font-weight:500}
input[type="submit"]:hover{background:#eaeef2;border-color:#c5ccd3}
#wrapper{max-width:1050px;min-width:400px;margin:16px auto;padding:0 12px}
.path{padding:8px 12px;border:1px solid #d0d7de;background:#fff;margin-bottom:12px;border-radius:6px}
.right{text-align:right}
.center{text-align:center}
.float-right{float:right}
.message{padding:8px 12px;border-radius:6px;border:1px solid #d0d7de;background:#fff;margin-bottom:10px}
.message.ok{border-color:#1a7f37;color:#1a7f37;background:#dafbe1}
.message.error{border-color:#cf222e;color:#cf222e;background:#ffebe9}
.message.alert{border-color:#9a6700;color:#9a6700;background:#fff8c5}
.btn{border:0;background:none;padding:0;margin:0;font-weight:600;color:#0969da;cursor:pointer}
.btn:hover{color:#0550ae;text-decoration:underline}
.preview-img{max-width:100%;border-radius:6px;border:1px solid #d0d7de}
.preview-video{position:relative;max-width:100%;height:0;padding-bottom:62.5%;margin-bottom:10px}
.preview-video video{position:absolute;width:100%;height:100%;left:0;top:0;background:#000}
.compact-table{border:0;width:auto}
.compact-table td,.compact-table th{width:100px;border:1px solid #d0d7de;text-align:center}
.compact-table tr:hover td{background-color:#f3f6fc}
.filename{max-width:420px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.break-word{word-wrap:break-word}
a svg{opacity:.75}
a:hover svg{opacity:1}
</style>
<link rel="icon" href="<?php echo FM_SELF_URL ?>?img=favicon" type="image/svg+xml">
<link rel="shortcut icon" href="<?php echo FM_SELF_URL ?>?img=favicon" type="image/svg+xml">
<?php if (isset($_GET['view']) && FM_USE_HIGHLIGHTJS): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/<?php echo FM_HIGHLIGHTJS_STYLE ?>.min.css">
<?php endif; ?>
</head>
<body>
<div id="wrapper">
<?php
}

function fm_show_footer() {
    ?>
<p class="center"><small><a href="https://github.com/xsukax/xsukax-PHP-File-Manager" target="_blank">xsukax PHP File Manager</a></small></p>
</div>
<script>
function newfolder(p){var n=prompt('New folder name','folder');if(n!==null&&n!==''){window.location.search='p='+encodeURIComponent(p)+'&new='+encodeURIComponent(n);}}
function rename(p,f){var n=prompt('New name',f);if(n!==null&&n!==''&&n!=f){window.location.search='p='+encodeURIComponent(p)+'&ren='+encodeURIComponent(f)+'&to='+encodeURIComponent(n);}}
function change_checkboxes(l,v){for(var i=l.length-1;i>=0;i--){l[i].checked=(typeof v==='boolean')?v:!l[i].checked;}}
function get_checkboxes(){var i=document.getElementsByName('file[]'),a=[];for(var j=i.length-1;j>=0;j--){if(i[j].type==='checkbox'){a.push(i[j]);}}return a;}
function select_all(){change_checkboxes(get_checkboxes(),true);}
function unselect_all(){change_checkboxes(get_checkboxes(),false);}
function invert_all(){change_checkboxes(get_checkboxes());}
function checkbox_toggle(){var l=get_checkboxes();l.push(this);change_checkboxes(l);}
</script>
<?php if (isset($_GET['view']) && FM_USE_HIGHLIGHTJS): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>hljs.highlightAll();</script>
<?php endif; ?>
</body>
</html>
<?php
}

function fm_show_image($img) {
    if ($img === 'favicon') {
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=604800');
        header('Expires: ' . gmdate('D, d M Y H:i:s', strtotime('+7 days')) . ' GMT');
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path fill="#54aeff" d="M.5 5.5A1.5 1.5 0 0 1 2 4h4.086L7.5 5.5H14A1.5 1.5 0 0 1 15.5 7V13A1.5 1.5 0 0 1 14 14.5H2A1.5 1.5 0 0 1 .5 13z"/><path stroke="white" stroke-width="1.2" stroke-linecap="round" fill="none" d="M4 9.5h8M4 11.5h5"/></svg>';
    }
    exit;
}